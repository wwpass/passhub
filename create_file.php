<?php

/**
 * create_file.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2023 WWPass
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

    if(!Csrf::validCSRF((object)["verifier" => $_POST['verifier']])) {  //convert POST to OBJ
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    if (!isset($_POST['vault']) || (ctype_xdigit($_POST['vault']) == false)) {
        Utils::err("error create 36");
        return "Internal error";
    }

    $SafeID = $_POST['vault'];
    $UserID = $_SESSION['UserID'];

    /*
    if (isset($_POST['check'])) {
        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            return "Sorry, you do not have editor rights for this safe";
        }
        $result = $user->getPlanDetails();
        $result['storageUsed'] = $_SESSION['STORAGE_USED'];
        return $result;
    }
    */

    $folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

    $meta = $_POST['meta'];
    $file = $_POST['file'];

    $fileContent = file_get_contents($_FILES['blob']['tmp_name']);  // writes into /tmp
    
    if(!$fileContent) {
        Utils::err("file upload error 76");
        return(["status" => "Internal server error 76. Please try again later."]);
    }

    if (!isset($_POST['meta']) || !isset($_POST['file'])) {
        Utils::err("error file create 83");
        return(["status" => "Internal error"]);
    }
    try {
        return File::create($mng, $UserID, $SafeID, $folder, $meta, $file, $fileContent);
    } catch (Exception $e) {
        return $e->getMessage(); 
    }
}

$result = create_file_item_proxy($mng);
if (gettype($result) == "string") {
    $result = array("status" => $result);
}
if($result['status'] != "Ok") {
    Utils::err("File creation error " . $result['status']);
}
// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
