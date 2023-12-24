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

$json = file_get_contents('php://input');

$req = json_decode($json);

if(!Csrf::validCSRF($req)) {
    Utils::err("error 32 bad csrf");
    return ['status' => "Bad Request (68)"];
}

if(isset($req->msg) ) {
    Utils::err($_SERVER['REMOTE_ADDR'] . " "  . $req->msg);
}
/* ???
else {
    if (strpos($_SERVER['REQUEST_URI'], "serverlog.php?learnmore") != -1) {
        Utils::err('Serverlog: learnmore ');
    }
}
*/

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode(["status" => "Ok"]);
