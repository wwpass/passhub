<?php

/**
 * feedback_action.php
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
    define('SUPPORT_MAIL_ADDRESS', 'support@wwpass.com');
}

$mng = DB::Connection();

session_start();

if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
    http_response_code(400);
    echo "Bad Request (26)";
    exit();
}

$name = $_POST['name'];
$email = $_POST['email'];

$subject = "passhub report";

$message = "From: '$email' (name '$name')" . "<br><br>" . $_POST['message'];
$message = $message . "<br><br>" .  $_SERVER['HTTP_USER_AGENT'] . "<br>" . $_SERVER['REMOTE_ADDR'] ;
if (array_key_exists('UserID', $_SESSION)) {
    $message = $message . "<br>UserID " .  $_SESSION['UserID'];
} else {
    $message = $message . "<br>user not logged in";
}

$result = Utils::sendMail(SUPPORT_MAIL_ADDRESS,  $subject, $message);

if ($result['status'] !== 'Ok') {
    Utils::err("error sending message '$message'");
    Utils::errorPage("error sending email. Please try again later");
}

$_SESSION['form_success'] = ($result['status'] == 'Ok') ? 1 : 0; 
header('Location: form_filled.php?contact_us');
