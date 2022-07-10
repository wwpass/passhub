<?php

/**
 * security.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2017-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\DB;

$mng = DB::Connection();

session_start();

if (isset($_GET['check_mail'])) {
    include_once 'src/localized-template.php';
    LocalizedTemplate::factory('check-mail.html')
        ->add('email', $_SESSION['form_email'])
        ->render();
        exit();
}

$public_service = defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false;

if (isset($_GET['registration_action'])) {
    if ($public_service && isset($_SESSION['UserID'])) {
        include_once 'src/localized-template.php';

        $_SESSION['later'] = true;
        $close_action = '\'index.php\'';
    } else {
        $close_action = '\'logout.php\'';
    }
    echo Utils::render(
        'registration_action.html', 
        [
            // layout
            'narrow' => true, 
            'PUBLIC_SERVICE' => $public_service, 
            'hide_logout' => true,
    
            //content
            'email' => $_SESSION['form_email'],
            'success' => true,
            'close_action' => $close_action, 
            'de' => (isset($_COOKIE['site_lang']) && ($_COOKIE['site_lang'] == 'de'))
        ]
    );
    unset($_SESSION['form_email']);
    exit();  
}

if (isset($_GET['change_mail'])) {
    echo Utils::render(
        'registration_action.html', 
        [
            // layout
            'narrow' => true, 
            'PUBLIC_SERVICE' => $public_service, 
            'hide_logout' => true,
    
            //content
            'email' => $_SESSION['form_email'],
            'success' => true,
            'change' => true,
            'close_action' => '"index.php"', 
            'de' => (isset($_COOKIE['site_lang']) && ($_COOKIE['site_lang'] == 'de'))
        ]
    );
    unset($_SESSION['form_email']);
    exit();  
}

if (isset($_GET['setup_account'])) {
    echo Utils::render(
        'setup_account_action.html',
        [
            'narrow' => true, 
            'PUBLIC_SERVICE' => $public_service, 
            'hide_logout' => true,
            'feedback_page' => true,
            'email' => $_SESSION['form_email'],
            'success' => true            
        ]
    );
    exit();
}
echo Utils::render(
    'feedback_action.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => $public_service,
        'feedback_page' => true,
        'hide_logout' => !isset($_SESSION['PUID']),

        //content
        'email' => $_SESSION['form_email'],
        'success' => true
    ]
);
