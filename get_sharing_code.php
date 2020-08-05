<?php

/**
 * get_sharing_code.php
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
if (!defined('SHARING_CODE_TTL')) {
    define('SHARING_CODE_TTL', 48*60*60);
}
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\SharingCode;

$mng = DB::Connection();

session_start();

function get_sharing_code_proxy($mng) {

    if (!isset($_SESSION['PUID']) || !isset($_SESSION['UserID']) ) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }

    if (!isset($_POST['vault'])) {
        return "internal error 33";
    }
    $UserID = $_SESSION['UserID'];
    $SafeID = $_POST['vault'];
    $UserName = null;

    if (isset($_POST["name"]) && trim(($_POST["name"]) != "") ) {
        $UserName = trim($_POST["name"]);
    }
    return SharingCode::getSharingCode($mng, $UserID, $SafeID, $UserName);
}


$result = get_sharing_code_proxy($mng);

if (!is_array($result) ) {
    $result = array("status" => $result);
}


// Prevent caching.
header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
