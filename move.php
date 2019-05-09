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
require_once 'src/functions.php';

require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/db/item.php';


require_once 'src/db/SessionHandler.php';


$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

function move_record_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    try {
        update_ticket();
    } catch (Exception $e) {
        passhub_err('Caught exception: ' . $e->getMessage());
        $_SESSION['expired'] = true;
        return "login";
    }
    if (!isset($_POST['id']) || ($_POST['id'] == "")) {
        passhub_err("error mov 33");
        return "internal error 33";
    }

    if (!isset($_POST['dst_safe'])) {
        passhub_err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($_POST['src_safe'])) {
        passhub_err("error mov 39");
        return "internal error mov 39";
    }
    if (!isset($_POST['operation'])) {
        passhub_err("error mov 42");
        return "internal error mov 42";
    }
    $operation = $_POST['operation'];
    if (($operation != "move") && ($operation != "copy") && ($operation != "get data")) {
        passhub_err("error mov 55");
        return "internal error mov 55";
    }

    $entryID = $_POST['id'];

    $UserID = trim($_SESSION['UserID']);
    $SafeID = trim($_POST['src_safe']);
    $TargetSafeID = trim($_POST['dst_safe']);

    if ((ctype_xdigit((string)$UserID) == false) || (ctype_xdigit((string)$SafeID) == false) || (ctype_xdigit((string)$entryID) == false) || (ctype_xdigit((string)$TargetSafeID) == false)) {
        passhub_err("error mov 66");
        return "internal error mov 66";
    }

    if ($operation == "get data") {
        $user = new User($mng, $UserID);
        $result = get_item_cse($mng, $UserID, $entryID);

        return array("status" => "Ok", "item" => $result['item'],
            "src_key" => $user->safe_array[$SafeID]->encrypted_key_CSE,
            "dst_key" => $user->safe_array[$TargetSafeID]->encrypted_key_CSE);
    }
    $new_item = trim($_POST['item']);
    $dst_folder = 0;
    if (isset($_POST['dst_folder'])) {
        $dst_folder = $_POST['dst_folder'];
    }
    return move_item_cse($mng, $UserID, $SafeID, $TargetSafeID, $dst_folder, $entryID, $new_item, $operation);
}

$result =  move_record_proxy($mng);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');


if (!is_array($result)) {
    $result = array("status" => $result);
}


echo json_encode($result);
