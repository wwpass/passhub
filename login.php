<?php

/**
 * login.php
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

if (!file_exists(WWPASS_KEY_FILE)) {
    die('Message to sysadmin: <p>Please set <b>config/config.php/WWPASS_KEY_FILE</b> parameter: file does not exist</p>');
}
if (!file_exists(WWPASS_CERT_FILE)) {
    die('Message to sysadmin: <p>Please set <b>config/config.php/WWPASS_CERT_FILE</b> parameter: file does not exist</p>');
}

if (!file_exists('vendor/autoload.php')) {
    die('Message to sysadmin: <p>Please run <b> sudo composer install</b> in the site root</p>');
}

require_once 'vendor/autoload.php';

require_once 'src/functions.php';
require_once 'src/db/user.php';
// require_once 'src/cookie.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = "undefined";
    passhub_err("HTTP_USER_AGENT undefined (corrected)");
}

$incompatible_browser = false;
$h1_text = "Sorry, your browser is no longer supported";
$advise = "Please try another browser, e.g. Chrome or Firefox";

// Safari version 9 on Mac
if (preg_match('/.*Macintosh.*Version\/9.*Safari/', $_SERVER['HTTP_USER_AGENT'], $matches)) {
    $isMacintosh = "Macintosh";
    $incompatible_browser = "Safari";
    $h1_text = "Sorry, your version of Safari browser is too old and no longer supported";
    $advise = "Please upgrade MAC OS X (or Safari) or install Chrome or Firefox borwsers";
}

// iOS 9 and lower
if (stripos($_SERVER['HTTP_USER_AGENT'], "iPhone")) {
    $iOS = "iPhone";
} else if (stripos($_SERVER['HTTP_USER_AGENT'], "iPad")) {
    $iOS = "iPad";
} else if (stripos($_SERVER['HTTP_USER_AGENT'], "iPod")) {
    $iOS = "iPod";
} else {
    $iOS = false;
}

if ($iOS) {
    $user_agent = explode(' ', $_SERVER['HTTP_USER_AGENT']);
    $idx = array_search('OS', $user_agent);
    $ios_version = $user_agent[$idx+1];
    if (substr($ios_version, 0, 1) != "1") {
        $incompatible_browser = "iOS";
    }
    $h1_text = "Sorry, older verions of $iOS browsers are no longer supported";
    $advise = "Still you can open PassHub in a desktop or a laptop browser and scan the QR code with Passkey app on your $iOS";
}

// IE
if (stripos($_SERVER['HTTP_USER_AGENT'], "Trident")) {
    $incompatible_browser = "IE";
    $h1_text = "Sorry, Internet Explorer is no longer supported";
    $advise = "Please use Chrome, Firefox or Edge browsers";
}


$isAndroid = stripos($_SERVER['HTTP_USER_AGENT'], "Android");

if ($incompatible_browser) {
    session_destroy();
    passhub_err("incompatible browser " . $_SERVER['HTTP_USER_AGENT']);

    echo theTwig()->render(
        'notsupported.html',
        [
            'hide_logout' => true,
            'narrow' => true,
            'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE'), 
            'h1_text'=> $h1_text,
            'advise' => $advise,
            'incompatible_browser' => $incompatible_browser,
            'iOS_device' => $iOS
        ]
    );
    exit();
}

if (isset($_SESSION['PUID'])) {
    header("Location: index.php");
    exit();
}

if (isset($_GET['next']) && !isset($_SESSION['PUID'])) {
    $_SESSION['next'] = $_GET['next'];
}

if (defined('MAIL_DOMAIN') && isset($_GET['reg_code']) && !isset($_SESSION['PUID'])) {
    $_SESSION['reg_code'] = $_GET['reg_code'];
} else if (!isset($_GET['wwp_status'])) {
    unset($_SESSION['reg_code']);
}

if (array_key_exists('wwp_status', $_REQUEST) && ( $_REQUEST['wwp_status'] != 200)) {
    $_SESSION = array();
    if (array_key_exists('wwp_reason', $_REQUEST)) {
        if ($_REQUEST['wwp_status'] == 603) {
            // $err_msg = $_REQUEST['wwp_reason'];
        } else {
            $err_msg = "Error: " . htmlspecialchars($_REQUEST['wwp_reason']);
        }
    } else {
        $err_msg = "General Problem" .  htmlspecialchars($_REQUEST['wwp_status']);
    }
} else if (array_key_exists('wwp_ticket', $_REQUEST)) {
    if ((strpos($_REQUEST['wwp_ticket'], ':c:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':pc:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':cp:') == false)
    ) {
        // do nothing
    } else {
        // clear all keys but req_code if present
        $_SESSION = array_intersect_key($_SESSION, array('reg_code' => "",'next' => ""));
        $ticket = $_REQUEST['wwp_ticket'];
        try {
            $test4 = WWPass\Connection::VERSION == '4.0';

            if ($test4) {
                $wwc = new WWPass\Connection(
                    ['key_file' => WWPASS_KEY_FILE, 
                    'cert_file' => WWPASS_CERT_FILE, 
                    'ca_file' => WWPASS_CA_FILE]
                );
                $new_ticket = $wwc->putTicket(
                    ['ticket' => $ticket,
                    'pin' =>  defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false,
                    'client_key' => true,
                    'ttl' => WWPASS_TICKET_TTL]
                );

                $_SESSION['wwpass_ticket'] = $new_ticket['ticket'];
                $_SESSION['wwpass_ticket_renewal_time'] = time() + $new_ticket['ttl'] / 2;
                $puid = $wwc->getPUID(['ticket' => $ticket]);
                $puid = $puid['puid']; 
            } else { // version 3
                $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
                $new_ticket = $wwc->putTicket($ticket, WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');
                $_SESSION['wwpass_ticket'] = $new_ticket;
                $_SESSION['wwpass_ticket_renewal_time'] = time() + WWPASS_TICKET_TTL/2;
                $puid = $wwc->getPUID($ticket);
            }
            
            $_SESSION['PUID'] = $puid;

            $_SESSION['wwpass_ticket_creation_time'] = time();

            if (!isset($_REQUEST['wwp_hw'])) {
                $_SESSION['PasskeyLite'] = true;
            }
            $ip = $_SERVER['REMOTE_ADDR'];
            passhub_log("sign-in $puid $ip");
            header("Location: index.php");
            exit();

        }  catch (Exception $e) {
            $err_msg = $e->getMessage() . ". Please try again";
        }
    }
}

if (isset($_SESSION['reg_code'])) {

    echo theTwig()->render(
        'login_reg.html',
        [
            'narrow' => true,
            'hide_logout' => true,
            'PUBLIC_SERVICE'=> defined('PUBLIC_SERVICE')
        ]
    );
    exit();
}


if (defined('PUBLIC_SERVICE')) {
    require_once 'src/localized-template.php';

    include_once 'src/policy.php';
    if (defined('LOGIN_PAGE')) {
        $login_template = LocalizedTemplate::factory(LOGIN_PAGE);
        $login_template->render();
    }
    exit();
} 

echo theTwig()->render(
    'login.html'
);
