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

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;
use PassHub\Group;

$mng = DB::Connection();

session_start();

function create_group_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    $json = file_get_contents('php://input');
    $req = json_decode($json);
    

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    $UserID = $_SESSION['UserID'];

    $user = new User($mng, $UserID);
    $user->getProfile();

    if (!$user->isSiteAdmin(true)) {
        Utils::err('iam: no admin rights');
        Utils::errorPage('You do not have admin rights');
        exit();
    }
    Utils::err('req');
    Utils::err($req);

    if($req->operation == "getUserPublicKey") {
        return Group::getUserPublicKey($mng, $req->groupId, $req->email, $UserID);
    }

    if($req->operation == "addUser") {
        return Group::addUser($mng, $req->groupId, $req->email, $req->key, $UserID);
    }

    if($req->operation == "removeUser") {
        return Group::removeUser($mng, $req->groupId, $req->userId, $UserID);
    }

    if($req->operation == "addSafe") {
        return Group::addSafe($mng, $req);
    }

    if($req->operation == "removeSafe") {
        return Group::removeSafe($mng, $req);
    }


    if($req->operation == "create") {
        return Group::create($mng, $req->group, $UserID);
    }
    Utils::err("Internal server error 61");
    return "Internal server error 61";
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

$result = create_group_proxy($mng);
if (gettype($result) == "string") {
    $result = array("status" => $result);
}

echo json_encode($result);
