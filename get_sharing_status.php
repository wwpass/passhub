<?php

/**
 * get_sharing_status.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\SharingCode;


$mng = DB::Connection();

session_start();

function get_sharing_status_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    /*
    if(!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }
    */
    $UserID = $_SESSION['UserID'];

    return SharingCode::getSharingStatus($mng, $UserID);
}

$result = get_sharing_status_proxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
exit();
