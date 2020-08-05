<?php

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\DB;

$mng = DB::Connection();

session_start();

$_SESSION = array();
session_destroy();
header("location:login.php");
