<?php

/**
 * move.php
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
use PassHub\Item;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function move_record_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    if (!isset($_POST['id']) || ($_POST['id'] == "")) {
        Utils::err("error mov 33");
        return "internal error 33";
    }

    if (!isset($_POST['dst_safe'])) {
        Utils::err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($_POST['src_safe'])) {
        Utils::err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($_POST['operation'])) {
        Utils::err("error mov 42");
        return "internal error mov 42";
    }
    $operation = $_POST['operation'];
    if (($operation != "move") && ($operation != "copy") && ($operation != "get data")) {
        Utils::err("error mov 55");
        return "internal error mov 55";
    }

    $entryID = $_POST['id'];

    $UserID = trim($_SESSION['UserID']);
    $SafeID = trim($_POST['src_safe']);
    $TargetSafeID = trim($_POST['dst_safe']);

    if ((ctype_xdigit((string)$UserID) == false) || (ctype_xdigit((string)$SafeID) == false) || (ctype_xdigit((string)$entryID) == false) || (ctype_xdigit((string)$TargetSafeID) == false)) {
        Utils::err("error mov 66");
        return "internal error mov 66";
    }

    if ($operation == "get data") {
        $item = new Item($mng, $entryID);
        return $item->getMoveOperationData($UserID, $SafeID, $TargetSafeID);
    }
    $new_item = trim($_POST['item']);
    $dst_folder = 0;
    if (isset($_POST['dst_folder'])) {
        $dst_folder = $_POST['dst_folder'];
    }
    $item = new Item($mng, $entryID);
    return $item->move($UserID, $SafeID, $TargetSafeID, $dst_folder, $new_item, $operation);
}

$result =  move_record_proxy($mng);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');


if (!is_array($result)) {
    $result = array("status" => $result);
}


echo json_encode($result);
