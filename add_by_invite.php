<?php

/**
 * add_by_invite.php
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

if(!defined('SHARING_CODE_TTL')) {
    define('SHARING_CODE_TTL', 48*60*60);
}

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\SharingCode;

$mng = DB::Connection();

session_start();

function add_by_invite_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }

    if (!isset($_POST['inviteCode']) || (trim($_POST['inviteCode']) == "")) {
        return "Please enter an invitation code";
    }
    if (!isset($_POST['newSafeName']) || (trim($_POST['newSafeName']) == "")) {
        return "Safe name cannot be empty";
    }
    if (!isset($_POST['newUserName']) || (trim($_POST['newUserName']) == "")) {
        return "Please define your name";
    }
    $UserID = $_SESSION['UserID'];

    return SharingCode::accept($mng, $UserID, trim($_POST['inviteCode']), trim($_POST['newUserName']), trim($_POST['newSafeName']));
}

$result = add_by_invite_proxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');


// Send the data.
echo json_encode($result);
exit();
