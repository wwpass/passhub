<?php

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\DB;

$mng = DB::Connection();

session_start();

$_SESSION = array();
session_destroy();
$result = array("status" => "Ok");

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);
exit();
