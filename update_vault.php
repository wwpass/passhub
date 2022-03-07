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
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function changSafeName_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    $json = file_get_contents('php://input');
    $req = json_decode($json);

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }
    if (!isset($req->eName)) {
        return "Safe name cannot be empty";
    }

    if (!isset($req->vault)) {
        return "internal error 38: vault not set";
    }
    $SafeID = trim($req->vault);

    if (!ctype_xdigit((string)$SafeID)) {
        return "internal error 42: illegal safe";
    }
    $user = new User($mng, $_SESSION['UserID']);
    return $user->changeSafeName($SafeID, $req->eName);
}

$status = changSafeName_proxy($mng);

$data = array("status" => $status);

// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($data);
