<?php

/**
 * feedback.php
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
use PassHub\Csrf;
use PassHub\DB;

$mng = DB::Connection();

session_start();

echo Utils::render(
    'feedback.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
        'feedback_page' => true,
        'hide_logout' => !isset($_SESSION['PUID']),

        //content
        'verifier' => Csrf::get() 
    ]
);  
