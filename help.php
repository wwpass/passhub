<?php

/**
 * help.php
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

if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
    include_once 'src/localized-template.php';
    include_once 'src/policy.php';
    $template = LocalizedTemplate::factory('help-public.html');
    $template
        ->add('hide_logout', !isset($_SESSION['PUID']))
        ->add('help_page', true)
        ->add('narrow', true);

    if (!isset($_SESSION['PUID'])) {
        $template->add('google_analytics', true);
    }
    $template->render();
    exit();

}

echo Utils::render(
    'help.html'
);
