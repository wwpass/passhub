<?php

/**
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

require_once 'Mail.php';

if (!defined('SUPPORT_MAIL_ADDRESS')) {
    define('SUPPORT_MAIL_ADDRESS', 'passhub@wwpass.com');
}

$mng = DB::Connection();

session_start();

function send_mail_proxy() {

    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $req = json_decode($json);

    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }

    $to = $req->to;
    $subject = $req->subject;
    $message = $req->message;
  
    $result = Utils::sendMail($to,  $subject, $message, 'text/plain; charset="UTF-8"');
    
    if ($result['status'] !== 'Ok') {
        Utils::err("Error sending message '$message'");

        return "Error sending email. Please try again later";
    }
    return $result;
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

$result = send_mail_proxy();
if (gettype($result) == "string") {
    $result = array("status" => $result);
}

// Send the data.
echo json_encode($result);
