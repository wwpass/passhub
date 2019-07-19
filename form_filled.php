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
require_once 'src/template.php'; 

require_once 'src/db/SessionHandler.php';
$mng = newDbConnection();
setDbSessionHandler($mng);

session_start();

if (isset($_GET['check_mail'])) {
    $template = Template::factory('src/templates/check-mail.html');
} else if (isset($_GET['enterprize_form_filled'])) {
    $template = Template::factory('src/templates/form_filled.html');
} else if (isset($_GET['registration_action'])) {
    $top_template = Template::factory('src/templates/top.html');
    $top_template->add('hide_logout', true)
        ->add('narrow', true)
        ->render();

    $template = Template::factory('src/templates/registration_action.html');
    $template->add('success', true);
} else if (defined('PUBLIC_SERVICE') && !isset($_SESSION['PUID'])) {
    $template = Template::factory('src/templates/form_filled.html');
} else {
    $top_template = Template::factory('src/templates/top.html');
    $top_template->add('hide_logout', !isset($_SESSION['PUID']))
        ->add('feedback_page', true)
        ->add('narrow', true)
        ->render();

    $template = Template::factory('src/templates/feedback_action.html');
    $template->add('success', $_SESSION['form_success']);
}

$template->add('email', $_SESSION['form_email']);
$template->render();
