<?php

/**
 * notsupported.php
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
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\DB;

$mng = DB::Connection();

session_start();

session_destroy();

Utils::err("not supported browser " . $_SERVER['HTTP_USER_AGENT']);

if (isset($_GET['js']) && ($_GET['js'] == 2)) {
    $h1_text = "The site is misconfigured";
    $advise = "Ask administrator to switch to HTTPS protocol";
} else {
    $h1_text = "Sorry, your browser is not supported";
    $advise = "Please use latest versions of Chrome or Firefox browsers";
}

echo Utils::render(
    'notsupported.html',
    [
        'hide_logout' => true,
        'narrow' => true,
        'PUBLIC_SERVICE' => PUBLIC_SERVICE,
        'h1_text'=> $h1_text,
        'advise' => $advise
    ]
);
