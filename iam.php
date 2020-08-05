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

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

$UserID = $_SESSION['UserID'];

function iam_ops_proxy($mng, $UserID) {

    $user = new User($mng, $UserID);
    $user->getProfile();
    
    if (isset($_GET['white_list'])) {
        if (!$user->isSiteAdmin()) {
            return ['status' => "Bad Request (94)"];
        }
        return Iam::whiteMailList($mng);
    }

    if (isset($_POST['newUserMail'])) {
        if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
            Utils::err("bad csrf");
            return ['status' => "Bad Request (68)"];
        } 

        if (!$user->isSiteAdmin()) {
            return ['status' => "Bad Request (71)"];
        }

        $email = $_POST['newUserMail'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Content-type: application/json');
            return ['status' => 'illegal email address' . htmlspecialchars($email)];
        }
        $email = strtolower($_POST['newUserMail']);
        return Iam::addWhiteMailList($mng, $email);
    }

    if (isset($_POST['deleteMail'])) {
        if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
            Utils::err("bad csrf");
            return ['status' => "Bad Request (68)"];
        } 
        if (!$user->isSiteAdmin()) {
            return ['status' => "Bad Request (71)"];
        }
    
        $email = $_POST['deleteMail'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Content-type: application/json');
            echo ['status' => 'illegal email address' . htmlspecialchars($email)];
            return; 
        }
        $email = strtolower($email);
        return Iam::removeWhiteMailList($mng, $email);
    }

    $pageData = Iam::getPageData($mng, $UserID);
    $stats = $pageData["stats"];
    $user_array = $pageData["user_array"];    

    echo Utils::render(
        'iam.html',
        [
            'iam_page' => true,
            'verifier' => Csrf::get(),
            'me' => $UserID,
            'stats' => $stats,
            'users' => json_encode($user_array),
    
            // idle_and_removal
            'WWPASS_TICKET_TTL' => WWPASS_TICKET_TTL, 
            'IDLE_TIMEOUT' => IDLE_TIMEOUT,
            'ticketAge' =>  (time() - $_SESSION['wwpass_ticket_creation_time']),
        ]  
    );
    exit();
}

$result = iam_ops_proxy($mng, $UserID);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

/*
if (!is_array($result)) {
    $result = array("status" => $result);
}
*/

echo json_encode($result);



