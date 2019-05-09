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
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/db/file.php';

require_once 'src/db/SessionHandler.php';


// require_once 'src/google_drive_files.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

try {
    update_ticket();
} catch (Exception $e) {
    passhub_err('Caught exception: ' . $e->getMessage());
    $_SESSION['expired'] = true;
    header("Location: expired.php");
    exit();
}

if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
    http_response_code(400);
    echo "Internal error";
    exit();
}

if (!isset($_POST['vault']) || (ctype_xdigit($_POST['vault']) == false)) {
    passhub_err("error create 36");
    exit("internal error 36");
}

$SafeID = $_POST['vault'];
$UserID = $_SESSION['UserID'];

if (isset($_POST['ios_cancel']) && ($_POST['ios_cancel'] == "1" )) {
    header("Location: index.php?show_table&vault=$SafeID");
    exit();
}

$folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

$meta = $_POST['meta'];
$file = $_POST['file'];
$result = create_file_item_cse($mng, $UserID, $SafeID, $folder, $meta, $file);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}
// Prevent caching.

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
