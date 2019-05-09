<?php
require_once 'config/config.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();
setDbSessionHandler($mng);

session_start();
$_SESSION = array();
session_destroy();
header("location:login.php");
