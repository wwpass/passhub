<?php

/**
 * delete.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2021 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Item;
use PassHub\Csrf;
use PassHub\DB;

$mng = DB::Connection();

session_start();

function delete_item_proxy($mng)
{
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    
    $json = file_get_contents('php://input');
    $req = json_decode($json);
    
    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    if (!isset($req->id) || !isset($req->vault) ) {
        return "error del 32";
    }
    $entryID = trim($req->id);
    $item = new Item($mng, $entryID);
    return $item->delete(trim($_SESSION['UserID']), $req->vault);
}

$result = delete_item_proxy($mng);

$data = array("status" => $result);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($data);
