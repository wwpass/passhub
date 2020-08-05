<?php

/**
 * backup.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016 WWPass
 */

// NOTE: Excel 2016 does not quote spaces, does not load multiline values, adds EFBBBF when expoorted to UTF-8 CSV


require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Folder;
use PassHub\Item;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function impex_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    $UserID = $_SESSION['UserID'];
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("impex bad verifier");
        return "Internal error";
    }
    if (isset($_POST['export'])) {
        $user = new User($mng, $UserID);
        return array("status" => "Ok", "data" => $user->getSafes());
    } else if (isset($_POST['import'])) {
        $user = new User($mng, $UserID);
        return $user->importSafes($_POST);
    } else {
        Utils::err("Backup internal error 67 " . print_r($_POST,true));
        return  "Backup internal error 67";
    }
}

$result = impex_proxy($mng);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);
exit();
