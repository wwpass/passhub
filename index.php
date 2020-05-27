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
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/db/item.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

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
    passhub_err("HTTP_USER_AGENT undefined (corrected)");
}

if (isset($_REQUEST['current_safe']) && isset($_SESSION['UserID'])) {
    _set_current_safe($mng, $_SESSION['UserID'], $_REQUEST['current_safe']);
    exit();
}

if (defined('FILE_DIR') && defined('GOOGLE_CREDS')) {
    passhub_err("Error: both local storage and Google drive are enabled");
    error_page("Site is misconfigured. Consult system administrator");
}

if (!isset($_SESSION['PUID'])) {
    if ($_SERVER['QUERY_STRING']) {
        header("Location: login.php?". $_SERVER['QUERY_STRING']);
    } else {
        header("Location: login.php");
    }
    exit();
}

try {
    // update_ticket();
    test_ticket();
    if (!isset($_SESSION['UserID'])) {
        $result = getUserByPuid($mng, $_SESSION['PUID']);
        if ($result['status'] == "not found") {
            if (defined('LDAP')) {
                echo theTwig()->render(
                    'ldap.html', 
                    [
                        // layout
                        'narrow' => true, 
                        'verifier' => User::get_csrf(),
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
                if (!isPuidValidated($mng, $_SESSION['PUID'])) {
                    if (!isset($_SESSION['reg_code'])) {
                        passhub_err("requesting mail for new user");

                        echo theTwig()->render(
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
                    $status = process_reg_code($mng, $_SESSION['reg_code'], $_SESSION['PUID']);
                    if ($status !== "Ok") {
                        passhub_err("reg_code: " . $status);
                        error_page($status);
                    }
                    unset($_SESSION['reg_code']);
                }
            }

            showCreateUserPage();
            exit();
        } else if ($result['status'] == "Ok") {
            $UserID = $result['UserID'];
            $_SESSION["UserID"] = $UserID;
            passhub_log("user " . $UserID . " login " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
        } else {
            exit($result['status']);//multiple PUID records;
        }
    }

    $UserID = $_SESSION['UserID'];
    $user = new User($mng, $UserID);

    if (defined('MAIL_DOMAIN') && !isset($_SESSION['later'])) {
        if (!$user->email) {
            if (!isPuidValidated($mng, $_SESSION['PUID'])) {
                if (!isset($_SESSION['reg_code'])) {
                    passhub_err("requesting mail for existing user " . $UserID);

                    echo theTwig()->render(
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
                $status = process_reg_code($mng, $_SESSION['reg_code'], $_SESSION['PUID']);
                if ($status !== "Ok") {
                    passhub_err("reg_code: " . $status);
                    error_page($status);
                }
                $user = new User($mng, $UserID);
            }
        } else if (isset($_SESSION['reg_code'])) {
            unset($_SESSION['reg_code']);
            passhub_err('index 183, reg_code used by exisiting user');  
            message_page(
                "Your account is already created",
                "<p>The verification code is no more valid.</p>"
                . "<p>Please proceed to your account.</p>"
            );
        }
    }

    if (isset($_REQUEST['vault'])) {
        $user->setCurrentSafe($_REQUEST['vault']);
    }

    // after get_current_safe we know if user is cse-type
    // TODO do we need jquery ui from https://ajax.googleapis.com? - see progress
    // header("Content-Security-Policy: default-src 'unsafe-inline' 'self' https://maxcdn.bootstrapcdn.com https://cdnjs.cloudflare.com  https://cdn.wwpass.com wss://spfews.wwpass.com https://ajax.googleapis.com https://fonts.gstatic.com ; style-src 'unsafe-inline' 'self' https://maxcdn.bootstrapcdn.com https://fonts.googleapis.com");

    if (!$user->isCSE) {

        $top_template = Template::factory('src/templates/top.html');
        $top_template->add('narrow', true)
            ->render();

        passhub_log("Upgrade User CSE begin " . $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['HTTP_USER_AGENT']);

        $upgrade_user_template = Template::factory('src/templates/upsert_user.html');
        $upgrade_user_template->add('ticket', $_SESSION['wwpass_ticket'])
            ->add('upgrade', true)
            ->render();
        exit();
    }
    $safe_array = $user->safe_array;

} catch ( MongoDB\Driver\Exception\Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    passhub_err(get_class($e));
    passhub_err($err_msg);
    error_page("Internal server error idx 147");// return 500

} catch (WWPass\Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    passhub_err(get_class($e));
    passhub_err($err_msg);
    $_SESSION['expired'] = true;
} catch (Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    passhub_err(get_class($e));
    passhub_err($err_msg);
    // return 500
    error_page("Internal server error idx 159");
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
    'verifier' => User::get_csrf(),
    'password_font' => getPwdFont(),
    'MAX_SAFENAME_LENGTH' => defined('MAX_SAFENAME_LENGTH') ? MAX_SAFENAME_LENGTH : 20,
    'MAX_FILENAME_LENGTH' => defined('MAX_FILENAME_LENGTH') ? MAX_FILENAME_LENGTH : 40,
    'MAX_NOTES_SIZE' => defined('MAX_NOTES_SIZE') ? MAX_NOTES_SIZE : 2048,
    'MAX_URL_LENGTH' => defined('MAX_URL_LENGTH') ? MAX_URL_LENGTH : 2500,

    'MAIL_DOMAIN' => defined('MAIL_DOMAIN'),
    'SHARING_CODE_TTL' => defined('SHARING_CODE_TTL') ? SHARING_CODE_TTL/60/60 : 48,  

    // idle_and_removal
    'WWPASS_TICKET_TTL' => WWPASS_TICKET_TTL, 
    'IDLE_TIMEOUT' => IDLE_TIMEOUT,
    'ticketAge' =>  (time() - $_SESSION['wwpass_ticket_creation_time']),

];
if ($user->site_admin) {
    $twig_args['isSiteAdmin'] = true;
}

if (file_exists('config/server_name.php')) {
    $twig_args['server_name'] = file_get_contents('config/server_name.php');
}


if (isset($_SESSION["show"])) { 
    $twig_args['show'] = $_SESSION['show'];
    unset($_SESSION["show"]);
}

echo theTwig()->render('index.html', $twig_args); 

