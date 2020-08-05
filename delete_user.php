<?php

/**
 * delete_user.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2019 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;
use PassHub\Iam;

$mng = DB::Connection();

session_start();

function delete_user_proxy($mng) {
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (44)";
    }
    if (!array_key_exists('id', $_POST)) {
        Utils::err("usr del 49");
        return "internal error del 49";
    }
    $adm = new User($mng, $_SESSION['UserID']);
    if (!$adm->isSiteAdmin()) {
        Utils::err("usr ed user 63");
        return "usr ed user 63";
    }
    return Iam::deleteUser($mng, $_POST['id']);
}

$result = delete_user_proxy($mng);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

if (gettype($result) == "string") {
    $result = array("status" => $result);
}


// Send the data.
echo json_encode($result);
