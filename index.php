<?php

/**
 * index.php
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

use PassHub\Utils;
use PassHub\Survey;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;
use PassHub\Puid;

$mng = DB::Connection();

session_start();

if (isset($_GET["show"])) {
    $_SESSION["show"] = $_GET["show"];
    header("Location: index.php");
    exit();
}

if (!defined('IDLE_TIMEOUT')) {
    define('IDLE_TIMEOUT', 540);
}
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = "undefined";
    Utils::err("HTTP_USER_AGENT undefined (corrected)");
}

if (isset($_REQUEST['current_safe']) 
    && isset($_REQUEST['verifier']) 
    && (Csrf::isValid($_REQUEST['verifier']))
    && isset($_SESSION['UserID'])
) {
    $user = new User($mng, $_SESSION['UserID']);
    $user->setCurrentSafe(trim($_REQUEST['current_safe']));
    exit();
}

if (defined('FILE_DIR') && defined('GOOGLE_CREDS')) {
    Utils::err("Error: both local storage and Google drive are enabled");
    Utils::errorPage("Site is misconfigured. Consult system administrator");
}

if (!isset($_SESSION['PUID'])) {
    if (isset($_SERVER['QUERY_STRING'])) {
        header("Location: login.php?". $_SERVER['QUERY_STRING']);
    } else {
        header("Location: login.php");
    }
    exit();
}

$puid = new Puid($mng, $_SESSION['PUID']);

try {
    Utils::testTicket();
    if (!isset($_SESSION['UserID'])) {
        $result = $puid->getUserByPuid();
        if ($result['status'] == "not found") {
            if (defined('LDAP') 
                && ( !isset(LDAP['mail_registration']) 
                    || (LDAP['mail_registration'] !== true))
            ) {
                echo Utils::render(
                    'ldap.html', 
                    [
                        // layout
                        'narrow' => true, 
                        'verifier' => Csrf::get(),
                        'hide_logout' => true,
                        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
                    ]
                );
                exit();
            }

            if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                if (!isset($_SESSION['TermsAccepted'])) {
                    header("Location: accept_terms.php");
                    exit();
                }
            } else {
                if (!$puid->isValidated()) {
                    if (!isset($_SESSION['reg_code'])) {
                        Utils::err("requesting mail for new user");

                        echo Utils::render(
                            'request_mail.html', 
                            [
                                // layout
                                'narrow' => true, 
                                'hide_logout' => true,
                                'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
                            ]
                        );
                        exit();
                    }
                    Utils::err('Should not happen idx 129');
                }
            }

            Utils::showCreateUserPage();
            exit();
        } else if ($result['status'] == "Ok") {
            $UserID = $result['UserID'];
            $_SESSION["UserID"] = $UserID;

            if (defined('LDAP')) {
                $user = new User($mng, $UserID);
                $a = $user->checkLdapAccess();
                if ($a === "not bound") {
                    echo Utils::render(
                        'ldap.html', 
                        [
                            // layout
                            'narrow' => true, 
                            'verifier' => Csrf::get(),
                            'hide_logout' => true,
                            'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
                        ]
                    );
                    exit();
                }
                if (!$a) {
                    $_SESSION = array();
                    session_destroy();
                    echo Utils::render(
                        'error_page.html', 
                        [
                            // layout
                            'narrow' => true, 
                            'hide_logout' => true,
                            'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false,
                            'header' => 'Access denied',
                            'text' => 'Consult system administrator'
                        ]
                    );
                    exit();

                }   
            }

            $_SESSION["UserID"] = $UserID;
            Utils::log("user " . $UserID . " login " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
            $firstResponceAfterLogin = true;
        } else {
            exit($result['status']);//multiple PUID records;
        }
    }
    if(defined('DISCOURSE_SECRET') && isset($_SESSION['sso'])) {
        header("Location: " . $_SESSION['sso']);
        unset($_SESSION['sso']);
        exit();
    }

    $UserID = $_SESSION['UserID'];
    $user = new User($mng, $UserID);
    $user->getProfile();

    if($user->disabled()) {
        Utils::errorPage("The account is disabled. Please consult your system administrator");
    }

    if (defined('MAIL_DOMAIN') && !isset($_SESSION['later'])) {
        if ($user->profile->email == "") {
            if (!$puid->isValidated()) {
                if (!isset($_SESSION['reg_code'])) {
                    Utils::err("requesting mail for existing user " . $UserID);

                    echo Utils::render(
                        'request_mail.html', 
                        [
                            // layout
                            'narrow' => true, 
                            'PUBLIC_SERVICE' => PUBLIC_SERVICE, 
                            'existing_account' => true,
                            'de' => (isset($_COOKIE['site_lang']) && ($_COOKIE['site_lang'] == 'de'))
                        ]
                    );
                    exit();
                }
                Utils::err('Should not happen idx 212');
            }
        } else if (isset($_SESSION['reg_code'])) {
            unset($_SESSION['reg_code']);
            Utils::err('index 183, reg_code used by exisiting user');  
            Utils::messagePage(
                "Your account is already created",
                "<p>The verification code is no more valid.</p>"
                . "<p>Please proceed to your account.</p>"
            );
        }
    }

    // after get_current_safe we know if user is cse-type
    // TODO do we need jquery ui from https://ajax.googleapis.com? - see progress
    // header("Content-Security-Policy: default-src 'unsafe-inline' 'self' https://maxcdn.bootstrapcdn.com https://cdnjs.cloudflare.com  https://cdn.wwpass.com wss://spfews.wwpass.com https://ajax.googleapis.com https://fonts.gstatic.com ; style-src 'unsafe-inline' 'self' https://maxcdn.bootstrapcdn.com https://fonts.googleapis.com");

    if (!$user->isCSE()) {

        $top_template = Template::factory('src/templates/top.html');
        $top_template->add('narrow', true)
            ->render();

        Utils::log("Upgrade User CSE begin " . $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['HTTP_USER_AGENT']);

        $upgrade_user_template = Template::factory('src/templates/upsert_user.html');
        $upgrade_user_template->add('ticket', $_SESSION['wwpass_ticket'])
            ->add('upgrade', true)
            ->render();
        exit();
    }

} catch ( MongoDB\Driver\Exception\Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    Utils::err(get_class($e));
    Utils::err($err_msg);
    Utils::errorPage("Internal server error idx 147");// return 500

} catch (WWPass\Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    Utils::err(get_class($e));
    Utils::err($err_msg);
    $_SESSION['expired'] = true;
} catch (Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    Utils::err(get_class($e));
    Utils::err($err_msg);
    // return 500
    Utils::errorPage("Internal server error idx 159");
}

if (isset($_SESSION['expired'])) {
    header("Location: expired.php");
    exit();
}

$twig_args = [
    // layout
    //'narrow' => true, 
    // 'title' => $title,
    'index_page' => true,
    'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 

    // content
    'verifier' => Csrf::get(),
    'password_font' => Utils::getPwdFont(),
    'MAX_SAFENAME_LENGTH' => defined('MAX_SAFENAME_LENGTH') ? MAX_SAFENAME_LENGTH : 20,
    'MAX_FILENAME_LENGTH' => defined('MAX_FILENAME_LENGTH') ? MAX_FILENAME_LENGTH : 40,
    'MAX_NOTES_SIZE' => defined('MAX_NOTES_SIZE') ? MAX_NOTES_SIZE : 2048,
    'MAX_URL_LENGTH' => defined('MAX_URL_LENGTH') ? MAX_URL_LENGTH : 2500,

    'ANONYMOUS' => (!defined('MAIL_DOMAIN') && !defined('LDAP')),
    'SHARING_CODE_TTL' => defined('SHARING_CODE_TTL') ? SHARING_CODE_TTL/60/60 : 48,  

    // idle_and_removal
    'WWPASS_TICKET_TTL' => WWPASS_TICKET_TTL, 
    'IDLE_TIMEOUT' => IDLE_TIMEOUT,
    'ticketAge' =>  (time() - $_SESSION['wwpass_ticket_creation_time']),

];
if ($user->isSiteAdmin()) {
    $twig_args['isSiteAdmin'] = true;
}

if (file_exists('config/server_name.php')) {
    $twig_args['server_name'] = file_get_contents('config/server_name.php');
}

if (isset($_SESSION["show"])) { 
    $twig_args['show'] = $_SESSION['show'];
    unset($_SESSION["show"]);
}

$searchClearButton = true;

if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'firefox') !==  false) {
    $searchClearButton = false;
}
if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'iphone') !==  false) {
    $searchClearButton = false;
}

if ($searchClearButton) { 
    $twig_args['search_clear_button'] = true;
}

// echo Utils::render('index.html', $twig_args); 
echo Utils::render_react('index.html', $twig_args); 
