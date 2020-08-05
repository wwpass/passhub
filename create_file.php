<?php

/**
 * create_file.php
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
use PassHub\Csrf;
use PassHub\Files\File;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function create_file_item_proxy($mng) {
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        return "Internal error";
    }
    if (!isset($_POST['vault']) || (ctype_xdigit($_POST['vault']) == false)) {
        Utils::err("error create 36");
        return "Internal error";
    }

    $SafeID = $_POST['vault'];
    $UserID = $_SESSION['UserID'];

    if (isset($_POST['check'])) {
        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            return "Sorry you do not have editor rights for this safe";
        }
        $result = [];
        if (defined('MAX_STORAGE_PER_USER')) {
            $result['maxStorage'] = MAX_STORAGE_PER_USER;
        } 
        if (isset($_SESSION['plan'])  && ($_SESSION['plan'] == "FREE") && defined('FREE_ACCOUNT_MAX_STORAGE')) {
            $result['maxStorage'] = FREE_ACCOUNT_MAX_STORAGE;
        }
        if (!defined('MAX_FILE_SIZE')) {
            $result['maxFileSize'] = 5 * 1024 * 1024;
        } else {
            $result['maxFileSize'] = MAX_FILE_SIZE;
        }
        $result['storageUsed'] = $_SESSION['STORAGE_USED'];
        $result['status'] = 'Ok';
        return $result;
    }

    $folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

    $meta = $_POST['meta'];
    $file = $_POST['file'];

    if (!isset($_POST['meta']) || !isset($_POST['file'])) {
        Utils::err("error file create 83");
        return(["status" => "Internal error"]);
    }
    try {
        return File::create($mng, $UserID, $SafeID, $folder, $meta, $file);
    } catch (Exception $e) {
        return $e->getMessage(); 
    }
}

$result = create_file_item_proxy($mng);
if (gettype($result) == "string") {
    $result = array("status" => $result);
}
// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
