<?php

/**
 * registration_action.php
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

function close_account_proxy($mng) {
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $req = json_decode($json);
    
    if (!isset($req->operation)) {
        Utils::err("error del account 39");
        return "Internal error 39";
    }

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }
    $UserID = $_SESSION['UserID'];
    $user = new User($mng, $UserID);

    $result = $user->deleteAccount();
    session_destroy();
    return $result;
}

$result =  close_account_proxy($mng);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');


if (!is_array($result)) {
    $result = array("status" => $result);
}

echo json_encode($result);
