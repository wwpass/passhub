<?php

/**
 * new.php
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

if (!isset($_SESSION['PUID'])) {
    header("Location: login.php?next=account.php");
    exit();
}

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}


function account_proxy($mng) {
    $UserID = $_SESSION['UserID'];

    $user = new User($mng, $UserID);
    
    
    // axios: Takes raw data from the request
    $json = file_get_contents('php://input');
    
    // Converts it into a PHP object
    $req = json_decode($json);
    

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    if (isset($req->operation)) {
        /*if($req->operation === 'delete') {
    
            $result = delete_account($mng, $UserID);
            session_destroy();
            return 'Ok';
        } else 
        */
        if($req->operation === 'setInactivityTimeout') {
            $id = ($req->id) ? $req->id:"desktop_inactivity";
            $value = $req->value;
            $user->setInactivityTimeOut($id, $value);
        }
    }
    return $user->account();
}

$result = account_proxy($mng);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// Send the data.
echo json_encode($result);

