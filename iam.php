<?php

/**
 * iam.php
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2024 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\Iam;
use PassHub\User;

$mng = DB::Connection();

session_start();

$UserID = $_SESSION['UserID'];

function iam_ops_proxy($mng, $UserID) {

    $json = file_get_contents('php://input');
    $req = json_decode($json);

    if (!isset($_SESSION['UserID'])) {
        return 'login';
    }


    if (!isset($req->verifier) || !Csrf::isValid($req->verifier)) {
        Utils::err("iam: bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    $admin = new User($mng, $UserID);
    $admin->getProfile();

    if (!$admin->isSiteAdmin()) {
        Utils::err('iam error 47');
        return ['status' => "Bad Request (48)"];
    }

    $operation = $req->operation;

    if ($operation == 'users') {
        $data = Iam::getPageData($mng, $req, $UserID);
//        if(defined('MSP')) {
            $data['me'] = $UserID;
//        }
        $data['status'] = 'Ok';
        return $data;
}

    if($operation == 'delete') {
        return Iam::deleteUser($mng, $req, $admin->profile['email']);
    }

    if($operation == 'audit') {
        return Iam::audit($mng, $req, $admin->profile['email']);
    }

    if(in_array($operation, ['admin', 'active', 'disabled'])) {

        if (isset($req->id)) {
            if ($req->id == $_SESSION['UserID']) {
                Utils::err("iam error 59");
                return "internal error";
            }
            $user_id = $req->id;
            return Iam::setStatus($mng, $operation, $user_id, $admin->profile['email']);
        }
        Utils::err('iam error 76');
        return ['status' => 'internal error'];
    }

    if($operation == 'newuser') {
        if(isset($req->email)) {
            $email = $req->email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'illegal email address'];
            }
            $req->email = strtolower($req->email);
            //  $email = strtolower($email);
            // plus: delete user by mail

            return Iam::addWhiteMailList($mng, $req, $admin->profile['email']);
        }
        Utils::err('iam error 91');
        return ['status' => 'internal error'];
    }
    Utils::err('Iam: unknown operation ' .  $operation);
    return ['status' => 'internal error 97'];
}

$result = iam_ops_proxy($mng, $UserID);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
