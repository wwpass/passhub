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

require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

function get_sharing_code_proxy($mng) {

    if (!isset($_SESSION['PUID']) || !isset($_SESSION['UserID']) ) {
        return "login";
    }
    try {
        update_ticket();
    } catch  (Exception $e) {
        passhub_err('Caught exception: ' . $e->getMessage());
        $_SESSION['expired'] = true;
        return "login";
    }

    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf");
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

    return getSharingCode($mng, $UserID, $SafeID, $UserName);
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
