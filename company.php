<?php

/**
 * comapnies.php
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2024 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';


use PassHub\Company;
use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\Iam;
use PassHub\User;

$mng = DB::Connection();

session_start();

$UserID = $_SESSION['UserID'];

function companies_proxy($mng, $UserID) {

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
        Utils::err('companies error 47');
        return ['status' => "Bad Request (48)"];
    }

    $operation = $req->operation;

    if ($operation == 'companies') {
        $data = Company::getPageData($mng);
	
        $data['status'] = 'Ok';
        return $data;
    }

    if (isset($req->companyId) && (strlen($req->companyId) == 24) && ctype_xdigit($req->companyId)) {
        if ($operation == 'setProfile') {
            return Company::setProfile($mng, $req, $admin->profile['email']);
        }
        Utils::err('bad request 66');
        Utils::err($req);
        return array("status" => 'bad request 66');
    }


/*
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
*/
    if($operation == 'newcompany') {
            return Company::addCompany($mng, $req, $admin->profile['email']);
    }
    Utils::err('Company: unknown operation ' .  $operation);
    return ['status' => 'internal error 97'];
}

$result = companies_proxy($mng, $UserID);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
