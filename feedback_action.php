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
require_once 'Mail.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/template.php';

if (!defined('SUPPORT_MAIL_ADDRESS')) {
    define('SUPPORT_MAIL_ADDRESS', 'support@wwpass.com');
}

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
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

$result = sendMail(SUPPORT_MAIL_ADDRESS,  $subject, $message);

if ($result['status'] !== 'Ok') {
    passhub_err("error sending message '$message'");
    error_page("error sending email. Please try again later");
}

$_SESSION['form_success'] = ($result['status'] == 'Ok') ? 1 : 0; 
header('Location: form_filled.php?contact_us');

