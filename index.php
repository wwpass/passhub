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
use PassHub\Iam;

$mng = DB::Connection();

session_start();

if (!defined('IDLE_TIMEOUT')) {
    define('IDLE_TIMEOUT', 540);
}
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = "undefined";
    Utils::err("HTTP_USER_AGENT undefined (corrected)");
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
            } else if (!$puid->isValidated()) {
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
                exit();
            }

            if(defined('CREATE_USER')) {
                $_SESSION['CREATE_USER'] = true;
                echo Utils::render_react('index.html', ['verifier' => Csrf::get()]); 
                exit();
            } 
            Utils::showCreateUserPage();
            exit();

        } else if ($result['status'] != "Ok") {
            Utils::err("multiple PUID records " . $_SESSION['PUID']);
            exit($result['status']);//multiple PUID records;
        } else {
            $UserID = $result['UserID'];
            $user = new User($mng, $UserID);
            $user->getProfile();
            $_SESSION["UserID"] = $UserID;
            $_SESSION['email'] = $user->profile->email;

            if (defined('LDAP')) {
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

            Utils::log("user " . $UserID . " login " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
            if(!defined('PUBLIC_SERVICE'))  {
                Utils::audit_log($mng, ["actor" => $user->profile->email, "operation" => "Login"]);
            }
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

    if (!defined('LDAP') && (defined('PUBLIC_SERVICE') || defined('MAIL_DOMAIN')) && !isset($_SESSION['later'])) {
//        if (defined('MAIL_DOMAIN') && !isset($_SESSION['later'])) {
        if ($user->profile->email == "") {
            if (!$puid->isValidated()) {
                if (!isset($_SESSION['reg_code'])) {
                    Utils::err("requesting mail for existing user " . $UserID);

                    echo Utils::render(
                        'request_mail.html', 
                        [
                            // layout
                            'narrow' => true, 
                            'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false,
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

echo Utils::render_react('index.html', ['verifier' => Csrf::get()]); 
