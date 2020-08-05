<?php

/**
 * items.php
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
use PassHub\Item;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function items_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("error items 45");
        return "Internal error";
    }

    if (!isset($_POST['vault']) || (ctype_xdigit($_POST['vault']) == false)) {
        Utils::err("error items 50");
        return "Internal error";
    }

    $SafeID = $_POST['vault'];
    $UserID = $_SESSION["UserID"];

    if (isset($_POST['check'])) {
        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            return "Sorry you do not have editor rights for this safe";
        }
        if (!isset($_POST['entryID']) 
            && isset($_SESSION['plan'])  
            && ($_SESSION['plan'] == "FREE") 
            && defined('FREE_ACCOUNT_MAX_RECORDS')
            && (isset($_SESSION['TOTAL_RECORDS']))
        ) {
            if ($_SESSION['TOTAL_RECORDS'] >= FREE_ACCOUNT_MAX_RECORDS) {
                $result = "Sorry you plan has a limit of " .  FREE_ACCOUNT_MAX_RECORDS;
                $result .= " records. You already have " . $_SESSION['TOTAL_RECORDS'] ." total records";
                return $result;
            }
        }
        return 'Ok';
    }

    $encrypted_data = $_POST['encrypted_data'];

    if (isset($_POST['entryID'])) { // update
        $item = new Item($mng, trim($_POST['entryID']));
        return $item->update($UserID, $SafeID, $encrypted_data);
    }

    $folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;
    return Item::create_items_cse($mng, $UserID, $SafeID, $encrypted_data, $folder);
}

$result = items_proxy($mng);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
