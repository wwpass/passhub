<?php

/**
 * upgrade_user.php
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
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';


//TODO csrf

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);


session_start();

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

function upgradeUser_proxy($mng) {

//    error_log(print_r($_POST, true));
    if(!isset($_POST['publicKey'])) {
        return "internal error 38: publicKey not set";
    }
    if(!isset($_POST['encryptedPrivateKey'])) {
        return "internal error 38: encryptedPrivateKey not set";
    }

    $UserID = $_SESSION['UserID'];

    $user = new User($mng, $UserID);
    return $user->upgrade($_POST['publicKey'], $_POST['encryptedPrivateKey']);
}

$result = upgradeUser_proxy($mng);

if(!is_array($result)) {
  $result = array("status" => $result);
}

passhub_log("Upgrade User CSE: " . $result['status'] . " " . $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['HTTP_USER_AGENT']);

// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
