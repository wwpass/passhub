<?php

/**
 * registration_action.php
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
require_once 'src/db/iam_ops.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

/*
if(!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
    http_response_code(400);
    echo "Bad Request (26)";
    exit();
}
*/

if (!defined('MAIL_DOMAIN')) {
    passhub_err("mail domain not defined");
    error_page("Internal error");
}


if (isset($_SESSION['UserID']) && isset($_GET['later'])) {
    $_SESSION['later'] = true;
    passhub_err("user " . $_SESSION['UserID'] . " mail registration: later");
    header('Location: index.php');
    exit();
} 

$email = $_POST['email'];
$url = strtolower($_POST['base_url']);

$parts = explode("@", $email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = "Invalid e-mail address: " . htmlspecialchars($email);
} else if (count($parts) != 2) {
    $error_msg = "Invalid e-mail address: " . htmlspecialchars($email);
} else if (!is_authorized($mng, $email)) {
    $error_msg = "<p>The e-mail address " .  htmlspecialchars($email) . " cannot be used to create an account.</p><p> Please contact your system administrator.</p>";
} else {
    $result = getRegistrationCode($mng, $_SESSION['PUID'], $email);
    if ($result['status'] == "Ok") {
        $subject = "PassHub Account Activation";

        $hostname = "PassHub";
        
        if (isset($_POST['host'])) {
            $hostname = str_replace("passhub", "PassHub", $_POST['host']);
        }

        $body = "<p>Dear " . $hostname . " Customer,</p>"
         .  "<p>Please click the link below to activate your account:</p>"
         . "<a href=" . $url . "login.php?reg_code=" . $result['code'] . ">"
         . $url . "login.php?reg_code=" . $result['code'] . "</a>"

         . "<p>Best regards, <br>PassHub Team.</p>"; 

         $result = sendMail($email, $subject, $body);
 
        passhub_err('verification mail sent to ' . $email);
        $_SESSION = [];
        $sent = true;
        if ($result['status'] !== 'Ok') {
            passhub_err("error sending email");
            error_page("error sending email. Please try again later");
            $sent = false;
        }

    } else {
        passhub_err("error getting registration code: ", $result['status']);
        $error_msg = $result['status'];
    }
}

if (!isset($error_msg)) {
    $_SESSION['form_email'] = htmlspecialchars($email);
    header('Location: form_filled.php?registration_action');
    exit();
}

echo theTwig()->render(
    'request_mail.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
        'hide_logout' => true,

        //content
        'email' => $_SESSION['form_email'],
        'success' => true,
        'error_msg' => $error_msg,
        'de' => (isset($_COOKIE['site_lang']) && ($_COOKIE['site_lang'] == 'de'))
    ]
);
