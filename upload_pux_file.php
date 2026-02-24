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

function upload_pux_file_proxy($mng) {
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

    if (!isset($_POST['puxId']) || !isset($_POST['fileInfo'])) {
        Utils::err("error file create 83");
        return(["status" => "Internal error"]);
    }

    $puxId = $_POST['puxId'];
    $fileInfo = $_POST['fileInfo'];

    $fileContent = file_get_contents($_FILES['blob']['tmp_name']);  // writes into /tmp
    
    if(!$fileContent) {
        Utils::err("pux file upload error 76");
        return(["status" => "Internal server error 76. Please try again later."]);
    }

    try {
        return File::upload_pux_file($mng, $UserID,  $SafeID, $puxId, $fileInfo, $fileContent);
    } catch (Exception $e) {
        return $e->getMessage(); 
    }
}

$result = upload_pux_file_proxy($mng);
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
