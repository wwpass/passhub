<?php

/**
 * delete.php
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
use PassHub\Csrf;
use PassHub\DB;

$mng = DB::Connection();

session_start();

function delete_item_proxy($mng)
{
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (26)";
    }

    if (!isset($_POST['id']) || !isset($_POST['vault']) ) {
        return "error del 32";
    }
    $entryID = trim($_POST['id']);
    $item = new Item($mng, $entryID);
    return $item->delete(trim($_SESSION['UserID']), $_POST['vault']);
}

$result = delete_item_proxy($mng);

$data = array("status" => $result);

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($data);
