<?php

/**
 * delete_vault.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function delete_safe_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }

    if (!isset($_POST['SafeID'])) {
        return "internal error del 36";
    }
    if (!isset($_POST['operation'])) {
        return "internal error del 52";
    }

    $SafeID = trim($_POST['SafeID']);
    $user = new User($mng, $_SESSION['UserID']);
    return $user->deleteSafe($SafeID, $_POST['operation']);
}

$result = delete_safe_proxy($mng);


header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

if (gettype($result) == "string") {
    $result = array("status" => $result);
}


// Send the data.
echo json_encode($result);
