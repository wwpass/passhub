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

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\Puid;
use PassHub\Iam;

require_once 'Mail.php';

$mng = DB::Connection();

session_start();


/*
if(!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
    http_response_code(400);
    echo "Bad Request (26)";
    exit();
}
*/

if( defined('LDAP')  
    && isset(LDAP['mail_registration']) 
    && (LDAP['mail_registration'] === true)) {
        // do nothing
}  else if (!defined('MAIL_DOMAIN')) {
    Utils::err("mail domain not defined");
    Utils::errorPage("Internal error");
}

if (isset($_SESSION['UserID']) && isset($_GET['later'])) {
    $_SESSION['later'] = true;
    Utils::err("user " . $_SESSION['UserID'] . " mail registration: later");
    header('Location: index.php');
    exit();
} 

if (isset($_POST['code6']) && isset($_POST['purpose'])) {
    Utils::err('Session[PUID] ' . $_SESSION['PUID']);
    $puid = new Puid($mng, $_SESSION['PUID']);
    $result = $puid->processCode6($_POST['code6'], $_POST['purpose']);
    Utils::err(print_r($result, true));

    if (!is_array($result)) {
        $result = array("status" => $result);
    }

    if(defined('LDAP') && ($result['status'] == 'Ok')) {

        if (isset($_SESSION['UserID'])) {
            $user = new User($mng, $_SESSION['UserID']);
            $user->ldapBindExistingUser($result['email'], $result['userprincipalname']);
            header("Location: index.php");
            exit();
        }
        $_SESSION['email'] = $result['email'];
        // $parts = explode("@", $result['email']);
        // $_SESSION['userprincipalname'] = $parts[0];
        $_SESSION['userprincipalname'] = $result['email'];
        $result=['status' => 'Ok'];
    }

    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
    header('Content-type: application/json');
    
    echo json_encode($result);
    exit();
}

$email = $_POST['email'];
$url = strtolower($_POST['base_url']);

$parts = explode("@", $email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = "Invalid email address: " . htmlspecialchars($email);
} else if (count($parts) != 2) {
    $error_msg = "Invalid email address: " . htmlspecialchars($email);
} else if (!Iam::isMailAuthorized($mng, $email)) {
    $error_msg = "<p>The email address " .  htmlspecialchars($email) . " cannot be used to create an account.</p><p> Please contact your system administrator.</p>";
} else {
    $puid = new Puid($mng, $_SESSION['PUID']);
    $result = $puid->getVerificationCode($email);
    if ($result['status'] == "Ok") {
        $subject = "PassHub Account Activation";

        $hostname = "PassHub";
        
        if (isset($_POST['host'])) {
            $hostname = str_replace("passhub", "PassHub", $_POST['host']);
        }
        $cta = "<p> Your 6-digit activation code is</p><p><b>". $result['code6'] . "</b></p>";

        $body = $cta 
        . "<p>Best regards, <br>PassHub Team.</p>"; 

        $result = Utils::sendMail($email, $subject, $body);

        Utils::err('verification mail sent to ' . $email);
/*
        if (!defined('PUBLIC_SERVICE') || !PUBLIC_SERVICE || !isset($_SESSION['UserID'])) {
            $_SESSION = [];
        }
*/        
        $sent = true;
        if ($result['status'] !== 'Ok') {
            Utils::err("error sending email");
            Utils::errorPage("error sending email. Please try again later");
            $sent = false;
        }

    } else {
        Utils::err("error getting registration code: ", $result['status']);
        $error_msg = $result['status'];
    }
}

if (!isset($error_msg)) {
    $_SESSION['form_email'] = htmlspecialchars($email);
    header('Location: form_filled.php?registration_action');
    exit();
}

echo Utils::render(
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
