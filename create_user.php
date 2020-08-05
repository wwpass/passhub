<?php

/**
 * create_user.php
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
use PassHub\DB;
use PassHub\Puid;

//TODO csrf

$mng = DB::Connection();

session_start();

function createUser_proxy($mng) {

    if (!isset($_SESSION['PUID'])) {
        Utils::err("create 36");
        return "Internal error (create) 36";
    }

    if (isset($_SESSION['UserID'])) {
        Utils::err("create 41");
        return "Internal error (create) 41";
    }
    if (!isset($_POST['publicKey'])) {
        Utils::err("create 47");
        return "internal error (create) 47";
    }
    if (!isset($_POST['encryptedPrivateKey'])) {
        Utils::err("create 51");
        return "internal error (create) 51";
    }
    $puid = new Puid($mng, $_SESSION['PUID']);
    return $puid->createUser($_POST);
}

$result = createUser_proxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

Utils::log("Create User CSE: " . $result['status'] . " "  . $_SERVER['REMOTE_ADDR'] . " " .$_SERVER['HTTP_USER_AGENT']);


// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
