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

 if (!file_exists('vendor/autoload.php')) {
    die('Message to sysadmin: <p>Please run <b> sudo composer install</b> in the site root</p>');
}

require_once 'config/config.php';

/*

if (!file_exists(WWPASS_KEY_FILE)) {
    die('Message to sysadmin: <p>Please set <b>config/config.php/WWPASS_KEY_FILE</b> parameter: file does not exist</p>');
}
if (!file_exists(WWPASS_CERT_FILE)) {
    die('Message to sysadmin: <p>Please set <b>config/config.php/WWPASS_CERT_FILE</b> parameter: file does not exist</p>');
}

if (!file_exists('vendor/autoload.php')) {
    die('Message to sysadmin: <p>Please run <b> sudo composer install</b> in the site root</p>');
}

*/
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\DB;
use PassHub\Puid;
use PassHub\Csrf;

if (!file_exists(WWPASS_CERT_FILE)) {
    echo Utils::render(
        'no_crt_file_found.html',
        [
            'message' => 'Please set <b>config/config.php/WWPASS_CERT_FILE</b> parameter: file does not exist.',
//            'wwpass_manage' => TRUE
        ]
    );
    exit();
}

if (!file_exists(WWPASS_KEY_FILE)) {
    echo Utils::render(
        'no_crt_file_found.html',
        [
            'message' => 'Please set <b>config/config.php/WWPASS_KEY_FILE</b> parameter: file does not exist.',
  //          'wwpass_manage' => TRUE
        ]
    );
    exit();
}

$mng = DB::Connection();

session_start();

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = "undefined";
    Utils::err("HTTP_USER_AGENT undefined (corrected)");
}

$incompatible_browser = false;
$h1_text = "Sorry, your browser is no longer supported";
$advise = "Please try another browser, e.g. Chrome or Firefox";

// Safari version 9 on Mac
if (preg_match('/.*Macintosh.*Version\/9.*Safari/', $_SERVER['HTTP_USER_AGENT'], $matches)) {
    $isMacintosh = "Macintosh";
    $incompatible_browser = "Safari";
    $h1_text = "Sorry, your version of Safari browser is too old and no longer supported";
    $advise = "Please upgrade MAC OS X (or Safari) or install Chrome or Firefox browsers";
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
    $ios_version = explode('_', $user_agent[$idx+1]);
    if(count($ios_version) > 1) {
//        Utils::err('iOS version ' . $ios_version[0]);
        if(intval($ios_version[0]) < 10) {
            $incompatible_browser = "iOS";
        }
    }
    $h1_text = "Sorry, older verions of $iOS browsers are no longer supported";
    $advise = "Still you can open PassHub in a desktop or a laptop browser and scan the QR code with WWPass Key app on your $iOS";
}

// IE
if (stripos($_SERVER['HTTP_USER_AGENT'], "Trident")) {
    $incompatible_browser = "IE";
    $h1_text = "Sorry, Internet Explorer is no longer supported";
    $advise = "Please use Chrome, Firefox or Edge browsers";
}

if ($incompatible_browser) {
    session_destroy();
    Utils::err("incompatible browser " . $_SERVER['HTTP_USER_AGENT']);

    echo Utils::render(
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

if (defined('MAIL_DOMAIN') && isset($_GET['reg_code'])) {
    $_SESSION = [];
    $status = Puid::processRegCode1($mng, $_GET['reg_code']);

    if ($status !== "Ok") {
        Utils::err("reg_code: " . $status);
        Utils::errorPage($status);
    }
    echo Utils::render(
        'login_reg.html',
        [
            'narrow' => true,
            'hide_logout' => true,
            'PUBLIC_SERVICE'=> defined('PUBLIC_SERVICE')
        ]
    );
    exit();
} 
if (defined('MAIL_DOMAIN') && isset($_GET['changemail'])) {
    $_SESSION = [];
    $status = Puid::processRegCode1($mng, $_GET['changemail'], "change");

    if ($status !== "Ok") {
        Utils::err("reg_code: " . $status);
        Utils::errorPage($status);
    }
    echo Utils::render(
        'login_reg.html',
        [
            'narrow' => true,
            'hide_logout' => true,
            'PUBLIC_SERVICE'=> defined('PUBLIC_SERVICE'),
            'change' => true
        ]
    );
    exit();
} 

if (isset($_SESSION['PUID'])) {
    header("Location: index.php");
    exit();
}

if(defined('REGISTRATION_ACCESS_CODE') && isset($_GET['access_code'])) {
    if ($_GET['access_code'] == REGISTRATION_ACCESS_CODE) {
        $_SESSION['REGISTRATION_ACCESS_CODE'] = $_GET['access_code'];
    }
}

if (!isset($_GET['wwp_status'])) {
    unset($_SESSION['reg_code']);
}

$pin_required = defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false;    
if(isset($_SESSION['NOP'])) {
    $pin_required = false;
}

if (array_key_exists('wwp_status', $_REQUEST) && ( $_REQUEST['wwp_status'] != 200)) {
    $_SESSION = [];
    Utils::err("wwp_status: " . print_r($_REQUEST, true));
} else if (array_key_exists('wwp_ticket', $_REQUEST)) {
    if ((strpos($_REQUEST['wwp_ticket'], ':c:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':pc:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':cp:') == false)
    ) {
        // do nothing
    } else {
        // clear all keys but req_code if present
        if(defined('DISCOURSE_SECRET')) {
            $_SESSION = array_intersect_key($_SESSION, array('reg_code' => "", 'sso' =>'', 'REGISTRATION_ACCESS_CODE' => ''));
        } else {
            $_SESSION = array_intersect_key($_SESSION, array('reg_code' => "", 'REGISTRATION_ACCESS_CODE' => ''));
        }

        $ticket = $_REQUEST['wwp_ticket'];

        try {
            $test4 = (intval(explode('.', WWPass\Connection::VERSION)[0]) > 3);



            if ($test4) {
                $wwc = new WWPass\Connection(
                    ['key_file' => WWPASS_KEY_FILE, 
                    'cert_file' => WWPASS_CERT_FILE, 
                    'ca_file' => WWPASS_CA_FILE]
                );
                $new_ticket = $wwc->putTicket(
                    ['ticket' => $ticket,
                    'pin' =>  $pin_required,
                    'client_key' => true,
                    'ttl' => WWPASS_TICKET_TTL]
                );

                $_SESSION['wwpass_ticket'] = $new_ticket['ticket'];
                $_SESSION['wwpass_ticket_renewal_time'] = time() + $new_ticket['ttl'] / 2;
                $puid = $wwc->getPUID(['ticket' => $ticket]);
                $puid = $puid['puid']; 
            } else { // version 3
                $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
                $new_ticket = $wwc->putTicket($ticket, WWPASS_TICKET_TTL, $pin_required ? 'pc' : 'c');
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
            Utils::log("sign-in $puid $ip");
            header("Location: index.php");
            exit();

        }  catch (Exception $e) {
            Utils::err(" 216 wwp exception: " . $e->getMessage());
        }
    }
}

if (defined('PUBLIC_SERVICE')) {
    require_once 'src/localized-template.php';

    include_once 'src/policy.php';
    if (defined('LOGIN_PAGE')) {
        $login_template = LocalizedTemplate::factory(LOGIN_PAGE);
        $login_template
            ->add('csrf', Csrf::get())
            ->add('header_secondary', "")
            ->add('main_class', "")

            ->render();
    }
    exit();
} 


$background_image = "url('public/img/formentera-beach.jpeg')";
if (defined('LOGIN_BACKGROUND')) {
    $background_image=LOGIN_BACKGROUND;
}



echo Utils::render(
    'login.twig', 
    [
        'background_image' => $background_image
    ]
);
