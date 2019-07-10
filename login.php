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
//require_once 'src/lib/wwpass.php';
require_once 'vendor/autoload.php';

require_once 'src/functions.php';
require_once 'src/db/user.php';
// require_once 'src/cookie.php';
require_once 'src/template.php';

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
    $advise = "Still you can open passhub.net in a desktop or a laptop browser and scan the QR code with Passkey Lite app on your $iOS";
}

// IE
if (stripos($_SERVER['HTTP_USER_AGENT'], "Trident")) {
    $incompatible_browser = "IE";
    $h1_text = "Sorry, Internet Explorer is no longer supported";
    $advise = "Please use Chrome, Firefox or Edge browsers";
}


$isAndroid = stripos($_SERVER['HTTP_USER_AGENT'], "Android");
/*
// UCBrowser on Android works again!
if( stripos($_SERVER['HTTP_USER_AGENT'],"UCBrowser")) {
    $incompatible_browser = "UCBrowser";
    $h1_text = "Sorry, UC Browser is no longer supported";
    $advise = "Please get Chrome from Google Play and set it as your default browser";
}
*/
if ($incompatible_browser) {
    session_destroy();
    passhub_err("incompatible browser " . $_SERVER['HTTP_USER_AGENT']);
    $top_template = Template::factory('src/templates/top.html');
    $top_template->add('narrow', true)
        ->add('hide_logout', true)
        ->render();

    $notsupported_template = Template::factory('src/templates/notsupported.html');
    $notsupported_template->add('h1_text', $h1_text)
        ->add('advise', $advise)
        ->add('incompatible_browser', $incompatible_browser)
        ->add('iOS_device', $iOS)
        ->render();
    exit();
}

/*
Andoird TAB
Opera: ( does not fit)
  Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36 OPR/49.2.2361.134358
Samsung Browser
  Mozilla/5.0 (Linux; Android 7.0; SAMSUNG SM-T815 Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/8.2 Chrome/63.0.3239.111 Safari/537.36
Firefox on TAB
 Mozilla/5.0 (Android 7.0; Tablet; rv:64.0) Gecko/64.0 Firefox/64.0
UC Browser
(Linux; U; Android 7.0; en-US; SM-T815 Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/12.9.10.1159 Mobile Safari/537.36 
*/

function es6_compatible() {
    global $iOS;
    $es6_compatible = false;

    if (preg_match("/Macintosh|Linux|Windows NT 10/", $_SERVER['HTTP_USER_AGENT']) 
        || !preg_match("/Android/", $_SERVER['HTTP_USER_AGENT'])
    ) {
        if (preg_match("/ Chrome\/(\d{2}).* OPR\/(\d{2}).*/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 70) &&  ($matches[2] >= 57)) {
                // opera
                $es6_compatible = true;
            }
        } elseif (preg_match("/ Chrome\/(\d{2}).* YaBrowser\/(\d{2}).*/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 70) &&  ($matches[2] >= 18)) {
                // YaBrowser
                $es6_compatible = true;
            }
        } elseif (preg_match("/ Chrome\/(\d{2}).* Edge\/(\d{2}).*/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 64) &&  ($matches[2] >= 17)) {
                // Edge
                $es6_compatible = true;
            }
        } elseif (preg_match("/ Chrome\/(\d{2}).* Vivaldi\/(\d{1}).*/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 71) &&  ($matches[2] >= 2)) {
                // Vivaldi
                $es6_compatible = true;
            }
        } elseif (preg_match("/ Chrome\/(\d{2})/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 71)) {
                // Chrome ? 
                $es6_compatible = true;
            }
        } elseif (preg_match("/ Firefox\/(\d{2})/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 64)) {
                // Firefox ? 
                $es6_compatible = true;
            }
        }  
    }

    if ($iOS) {
        if (preg_match("/ Version\/(\d{2}).* Safari\/(\d{3})/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 12) &&  ($matches[2] >= 604)) {
                // Safari
                $es6_compatible = true;
            }
        }
    }
    if (preg_match("/Macintosh/", $_SERVER['HTTP_USER_AGENT'])) {
        if (preg_match("/ Version\/(\d{2}).* Safari\/(\d{3})/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
            if (($matches[1] >= 12) &&  ($matches[2] >= 605)) {
                // Safari
                $es6_compatible = true;
            }
        }
    }
    // make it unconditional
    
    $es6_compatible = true;
/*    
    if ($es6_compatible) {
        passhub_err(
            "es6: " . $_SERVER['HTTP_USER_AGENT']
        );
    } else {
        passhub_err(
            "not es6: " . $_SERVER['HTTP_USER_AGENT']
        );
    }
*/
    return $es6_compatible;
}

if (isset($_SESSION['PUID'])) {
    header("Location: index.php");
    exit();
}

// Referred and Passkey Lite
/*
if (isset($_REQUEST['ref'])) {  // ?ref=ios-passkey-lite
    if ($iOS || $isAndroid) {
        if (!array_key_exists('wwp_status', $_REQUEST) && !array_key_exists('wwp_ticket', $_REQUEST)) {
            // move to functions.cpp
            try {
                $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
                $ticket = $wwc->getTicket(WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'p:c':'c');
            } catch (Exception $e) {
                $err_msg = 'Caught exception: '. $e->getMessage();
                exit($err_msg);
            }
            $top_template = Template::factory('src/templates/top.html');
            $top_template->add('narrow', true)
                ->render();

            $lom_template = Template::factory('src/templates/login_on_mobile.html');
            $lom_template->add('ticket', $ticket)
                ->render();
            exit();
        }
    }
}
*/

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
    if ((strpos($_REQUEST['wwp_ticket'], ':c:') == false) && (strpos($_REQUEST['wwp_ticket'], ':pc:') == false) && (strpos($_REQUEST['wwp_ticket'], ':cp:') == false)) {
        /*
        if($iOS) {
            $err_msg = "Old Passkey Lite app, please update";
         } else {
            $err_msg = "Old Passkey Lite app, please update";
         }
        */
    } else {
        // clear all keys but req_code if present
        $_SESSION = array_intersect_key($_SESSION, array('reg_code' => "",'next' => ""));
        $ticket = $_REQUEST['wwp_ticket'];
        try {
            $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
            
            $puid = $wwc->getPUID($ticket);
            $new_ticket = $wwc->putTicket($ticket, WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');
            $_SESSION['PUID'] = $puid;
            $_SESSION['wwpass_ticket'] = $new_ticket;
            $_SESSION['wwpass_ticket_renewal_time'] = time() + WWPASS_TICKET_TTL/2;
            $_SESSION['wwpass_ticket_creation_time'] = time();

            $_SESSION['es6'] = es6_compatible();

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

$login_page_url = "src/templates/login.html";
if (defined('LOGIN_PAGE')) {
    $login_page_url = LOGIN_PAGE;
}
if (isset($_SESSION['reg_code'])) {

    $top_template = Template::factory('src/templates/top.html');
    $top_template->add('hide_logout', !isset($_SESSION['PUID']))
        ->add('narrow', true)
        ->render();

    $login_page_url = "src/templates/login_reg.html";
}

$login_template = Template::factory($login_page_url);
if (isset($err_msg)) {
    $login_template->add('err_msg', $err_msg);
}

/*
$hideInstructions = sniffCookie('hideInstructions');
if (!$hideInstructions) {
    if (isset($_REQUEST['ref'])) {  // ?ref=ios-passkey-lite
//        $hideInstructions = true;
//        setcookie('hideInstructions', true, time() + SECONDS_IN_DAY * 50);
    }
}
*/

$login_template->add('complete_registration', isset($_SESSION['reg_code']))
    ->add('isAndroid', $isAndroid)
    ->add('isIOS', $iOS)
    // ->add('hideInstructions', $hideInstructions)
    // ->add('showHardwareLogin', sniffCookie('showHardwareLogin'))
    ->render();
