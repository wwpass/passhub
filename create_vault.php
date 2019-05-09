<?php

/**
 * create_vault.php
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

require_once 'src/db/SessionHandler.php';


$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

function create_safe_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    try {
        update_ticket();
    } catch (Exception $e) {
        passhub_err('Caught exception: ' . $e->getMessage());
        $_SESSION['expired'] = true;
        return "login";
    }

    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf");
        return "Bad Request (46)";
    }
    /*
    if(!isset($_POST['newSafeName'])) {
        return "safe name not defined";
    }
    */
    return create_safe1($mng, $_SESSION['UserID'], $_POST['safe'] /*['snewSafeName']*/);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

$result = create_safe_proxy($mng);
if (gettype($result) == "string") {
    $result = array("status" => $result);
}

// Send the data.
echo json_encode($result);
