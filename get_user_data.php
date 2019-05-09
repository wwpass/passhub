<?php

/**
 *
 * get_user_data.php
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
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/item.php';
require_once 'src/db/safe.php';
require_once 'src/db/SessionHandler.php';


$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

function getUserDataProxy($mng)
{
    if (!isset($_SESSION['UserID'])) {
        return ["status" => "login"];
    }
    if (!isset($_POST['csrf']) || !User::is_valid_csrf($_POST['csrf'])) {
        passhub_err("get user data bad verifier");
        return "Internal error";
    }

    try {
        update_ticket();
    } catch (Exception $e) {
        $_SESSION['expired'] = true;
        passhub_err('Caught exception: ' . $e->getMessage());
        return "login";
    }
    $UserID = $_SESSION['UserID'];
    return getUserData($mng, $UserID);
}

$result = getUserDataProxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);

