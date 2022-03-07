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
use PassHub\Csrf;

$mng = DB::Connection();

session_start();

function move_record_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $req = json_decode($json);

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    if (!isset($req->id) || ($req->id == "")) {
        Utils::err("error mov 33");
        return "internal error 33";
    }

    if (!isset($req->dst_safe)) {
        Utils::err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($req->src_safe)) {
        Utils::err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($req->operation)) {
        Utils::err("error mov 42");
        return "internal error mov 42";
    }
    $operation = strtolower($req->operation);
    if (($operation != "move") && ($operation != "copy")) {
        Utils::err("error mov 55");
        return "internal error mov 55";
    }

    $entryID = $req->id;

    $UserID = trim($_SESSION['UserID']);
    $SafeID = trim($req->src_safe);
    $TargetSafeID = trim($req->dst_safe);

    if ((ctype_xdigit((string)$UserID) == false) || (ctype_xdigit((string)$SafeID) == false) || (ctype_xdigit((string)$entryID) == false) || (ctype_xdigit((string)$TargetSafeID) == false)) {
        Utils::err("error mov 66");
        return "internal error mov 66";
    }

    if (isset($req->checkRights)) {
        $item = new Item($mng, $entryID);
        return $item->getMoveOperationData($UserID, $SafeID, $TargetSafeID, $operation);
    }

    $new_item = trim($req->item);
    $dst_folder = 0;
    if (isset($req->dst_folder)) {
        $dst_folder = $req->dst_folder;
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
