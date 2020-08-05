<?php

/**
 * folder_ops.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Folder;
use PassHub\Csrf;
use PassHub\DB;

$mng = DB::Connection();

session_start();

function folder_ops_proxy($mng) {
    if (!isset($_SESSION['UserID'])) {
        return "login";
    }

    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (46)";
    }
    return Folder::operation($mng, $_SESSION['UserID'],  $_POST);
}

$result = folder_ops_proxy($mng);
if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');


// Send the data.
echo json_encode($result);
