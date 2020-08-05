<?php

/**
 *
 * safe_acl.php
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

function safe_acl_proxy($mng)
{
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }

    if (!isset($_POST['vault']) ) {
        return "internal error acl 33";
    }

    $UserID = $_SESSION['UserID'];
    /*    $SafeID = $_POST['vault'];
    $operation = isset($_POST['operation']) ? $_POST['operation']: null;
    $UserName = isset($_POST['name']) ? $_POST['name']: null;
    $RecipientKey = isset($_POST['key']) ? $_POST['key']: null;
    $role = isset($_POST['role']) ? $_POST['role']: null;
    */
    // return safe_acl($mng, $UserID, $SafeID, $operation, $UserName, $RecipientKey, $role);

    $user = new User($mng, $UserID);
    return $user->safeAcl($_POST);
}


$result = safe_acl_proxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);

