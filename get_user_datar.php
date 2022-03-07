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

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;


$mng = DB::Connection();

session_start();

function getUserDataProxy($mng)
{

    $t0 = microtime(true);
    if (!isset($_SESSION['UserID'])) {
        return ["status" => "login"];
    }

    // Takes raw data from the request
    $json = file_get_contents('php://input');
    $dt = number_format((microtime(true) - $t0), 3);
    Utils::timingLog("fileGetContents " . $dt);

    // Converts it into a PHP object
    $req = json_decode($json);
    // Utils::err(print_r($req, true));
    $dt = number_format((microtime(true) - $t0), 3);
    Utils::timingLog("jsonDecode " . $dt);

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }
    /*
    if (!isset($req->verifier) || !Csrf::isValid($req->verifier)) {
        Utils::err("iam: bad csrf");
        return ['status' => "Bad Request (68)"];
    } 
    */

    $dt = number_format((microtime(true) - $t0), 3);
    Utils::timingLog("csrfValid " . $dt);
    
    $user = new User($mng, $_SESSION['UserID']);
    $dt = number_format((microtime(true) - $t0), 3);
    Utils::timingLog("newUser " . $dt);
    return $user->getData();
}



$t0 = microtime(true);


$result = getUserDataProxy($mng);

$dt = number_format((microtime(true) - $t0), 3);
Utils::timingLog("getUserDataR " . $dt);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);

