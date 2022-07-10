
<?php

/**
 *
 * change_mail.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2021 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\Puid;
use PassHub\Iam;

require_once 'Mail.php';

$mng = DB::Connection();

session_start();

function change_mail_proxy($mng)
{
 
    $req = json_decode(file_get_contents('php://input'));
    
    if(!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return ['status' => "Bad Request (68)"];
    }


    $email = $req->email;
    $url = strtolower($req->base_url);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Utils::err("Invalid email address " . $email);
        return "Invalid email address";
    }
    if (!Iam::isMailAuthorized($mng, $email)) {
        return "The email address " .  htmlspecialchars($email) . " cannot be used to create an account. Please contact your system administrator";
    }
    $puid = new Puid($mng, $_SESSION['PUID']); 
    $result = $puid->getVerificationCode($email, "change");

    if ($result['status'] != "Ok") {
        Utils::err("error getting modification code: ", $result['status']);
        return $result['status'];
    }

    $subject = "PassHub email address change";

    $hostname = "PassHub";
    
    if (isset($req->host)) {
        $hostname = str_replace("passhub", "PassHub", $req->host);
    }
    $cta = "<p> Your 6-digit mail verification code is</p><p><b>". $result['code6'] . "</b></p>";

    $body = $cta . 

    "<p>Best regards, <br>PassHub Team.</p>"; 

    $result = Utils::sendMail($email, $subject, $body);

    Utils::err('verification mail sent to ' . $email);

    if ($result['status'] !== 'Ok') {
        Utils::err("error sending email: " . $result['status']);
        return "error sending email. Please try again later";
    }
    return $result['status'];
}
    
$result = change_mail_proxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);
