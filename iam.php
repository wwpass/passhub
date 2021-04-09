<?php

/**
 * iam.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2020 WWPass
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

    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $req = json_decode($json);
    Utils::err(print_r($req, true));

    if(!isset($req->operation)) {
        if (!isset($_SESSION['UserID'])) {
            header("Location: login.php");
            exit();
        }
        $user = new User($mng, $UserID);
        $user->getProfile();

        if (!$user->isSiteAdmin(true)) {
            Utils::err('iam: no admin rights');
            Utils::errorPage('You do not have admin rights');
            exit();
        }
        echo Utils::render(
            'iam.html',
            [
                'iam_page' => true,
                'verifier' => Csrf::get(),
                'me' => $UserID,
                // idle_and_removal
                'WWPASS_TICKET_TTL' => WWPASS_TICKET_TTL, 
                'IDLE_TIMEOUT' => IDLE_TIMEOUT,
                'ticketAge' =>  (time() - $_SESSION['wwpass_ticket_creation_time']),
            ]  
        );
        exit();
    }


    if (!isset($_SESSION['UserID'])) {
        return 'login';
    }
    $user = new User($mng, $UserID);
    $user->getProfile();

    if (!$user->isSiteAdmin()) {
        Utils::err('iam: no admin rights');
        return ['status' => "Bad Request (71)"];
    }

    if (!isset($req->verifier) || !Csrf::isValid($req->verifier)) {
        Utils::err("iam: bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    $operation = $req->operation;

    if ($operation == 'users') {
        $data = Iam::getPageData($mng, $UserID);
        Utils::err('Operation users');
        return [
            "status" => "Ok", 
            "stats" => $data["stats"], 
            "users" => $data["user_array"], 
            "me" => $UserID
            // "mail_array" => $data["mail_array"]
        ];
    }

    if($operation == 'delete') {
        return Iam::deleteUser($mng, ['email' => $req->email, 'id'=> $req->id]);
    }

    if(in_array($operation, ['admin', 'active', 'disabled'])) {

        if (isset($req->id)) {
            if ($req->id == $_SESSION['UserID']) {
                Utils::err("iam error 59");
                return "internal error iam 59";
            }
            $user = new User($mng, $req->id);
            return $user->setStatus($operation);
        }
        Utils::err('iam: $operation operation fail');
        return ['status' => 'IAM Internal error 109, see logs'];
    }

    if($operation == 'newuser') {
        if(isset($req->email)) {
            $email = $req->email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'illegal email address'];
            }
            $email = strtolower($email);
            // plus: delete user by mail
            return Iam::addWhiteMailList($mng, $email);
        }
        Utils::err('iam: new user operation fail');
        return ['status' => 'IAM Internal error 109, see logs'];
    }


/*

    if (isset($_POST['newUserMail'])) {
        if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
            Utils::err("bad csrf");
            return ['status' => "Bad Request (68)"];
        } 


        $email = $_POST['newUserMail'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Content-type: application/json');
            return ['status' => 'illegal email address' . htmlspecialchars($email)];
        }
        $email = strtolower($_POST['newUserMail']);
        return Iam::addWhiteMailList($mng, $email);
    }
*/


 //    $pageData = Iam::getPageData($mng, $UserID);
 //   $stats = $pageData["stats"];
 //   $user_array = $pageData["user_array"];    

}

$result = iam_ops_proxy($mng, $UserID);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
