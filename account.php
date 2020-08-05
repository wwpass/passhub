<?php

/**
 * new.php
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

if (!isset($_SESSION['PUID'])) {
    header("Location: login.php?next=account.php");
    exit();
}

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

$UserID = $_SESSION['UserID'];

$user = new User($mng, $UserID);

if (isset($_POST['operation']) && ($_POST['operation'] == 'delete')) {
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf account 54");
        return "Internal Error (54)";
    }

    $result = delete_account($mng, $UserID);
    session_destroy();
} else {
    $result = $user->account();
}

if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// Send the data.
echo json_encode($result);

