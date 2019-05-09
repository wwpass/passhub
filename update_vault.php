<?php

/**
 * update_vault.php
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

function changSafeName_proxy($mng) {

    try {
        update_ticket();
    } catch (Exception $e) {
        $_SESSION['expired'] = true;
        passhub_err('Caught exception: ' . $e->getMessage());
        return "login";
    }

    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf");
        return "Bad Request (46)";
    }

    if (!isset($_POST['newSafeName'])) {
        return "Vault name cannot be empty";
    }
    if (!isset($_POST['vault'])) {
        return "internal error 38: vault not set";
    }
    $SafeID = trim($_POST['vault']);

    if (!ctype_xdigit((string)$SafeID)) {
        return "internal error 42: illegal vault";
    }
    $newName = trim($_POST['newSafeName']);
    if ($newName == "") {
        return "Vault name cannot be empty";
    }
    $newName = mb_strimwidth($newName, 0, MAX_SAFENAME_LENGTH);

    return changeSafeName($mng, $_SESSION['UserID'], $SafeID, $newName);
}

$status = changSafeName_proxy($mng);

$data = array("status" => $status);

// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($data);
