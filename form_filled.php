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
require_once 'src/functions.php';
require_once 'src/db/user.php';

require_once 'src/db/SessionHandler.php';
$mng = newDbConnection();
setDbSessionHandler($mng);

session_start();

if (isset($_GET['check_mail'])) {
    LocalizedTemplate::factory('check-mail.html')
        ->add('email', $_SESSION['form_email'])
        ->render();
        exit();
}
/* if (isset($_GET['enterprize_form_filled'])) {
    $template = Template::factory('src/templates/form_filled.html'); 
} else */

$twig = theTwig();

if (isset($_GET['registration_action'])) {
    $public_service = defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false;
    if ($public_service && isset($_SESSION['UserID'])) {
        include_once 'src/localized-template.php';

        $_SESSION['later'] = true;
        $close_action = '\'index.php\'';
    } else {
        $close_action = '\'logout.php\'';
    }
    echo $twig->render(
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
if (isset($_GET['setup_account'])) {
    echo $twig->render(
        'setup_account_action.html',
        [
            'narrow' => true, 
            'PUBLIC_SERVICE' => PUBLIC_SERVICE, 
            'hide_logout' => true,
            'feedback_page' => true,
            'email' => $_SESSION['form_email'],
            'success' => true            
        ]
    );
    exit();
}
echo $twig->render(
    'feedback_action.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => PUBLIC_SERVICE, 
        'feedback_page' => true,
        'hide_logout' => !isset($_SESSION['PUID']),

        //content
        'email' => $_SESSION['form_email'],
        'success' => true
    ]
);
