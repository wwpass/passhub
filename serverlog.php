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
use PassHub\Csrf;
use PassHub\DB;

$mng = DB::Connection();

session_start();
if (isset($_POST['msg'])) {
    Utils::err($_POST['msg']);
} else {
    if (strpos($_SERVER['REQUEST_URI'], "serverlog.php?learnmore") != -1) {
        Utils::err('Serverlog: learnmore ');
    }
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode(["status" => "Ok"]);
