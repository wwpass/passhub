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

    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $req = json_decode($json);
   

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    if (!isset($req->vault) || (ctype_xdigit($req->vault) == false)) {
        Utils::err("error items 50");
        return "Internal error";
    }

    $SafeID = $req->vault;
    $UserID = $_SESSION["UserID"];

    if (isset($req->check)) {
        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            return "Sorry, you do not have editor rights for this safe";
        }
        if (!isset($req->entryID) 
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

    $encrypted_data = $req->encrypted_data;

    if (isset($req->entryID)) { // update
        $item = new Item($mng, trim($req->entryID));
        return $item->update($UserID, $SafeID, $encrypted_data);
    }

    $folder = isset($req->folder)? $req->folder : 0;
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
