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
require_once 'src/functions.php';
require_once 'src/db/user.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

echo theTwig()->render(
    'feedback.html', 
    [
        // layout
        'narrow' => true, 
        'title' => $title,
        'PUBLIC_SERVICE' => PUBLIC_SERVICE, 
        'feedback_page' => true,
        'hide_logout' => !isset($_SESSION['PUID']),

        //content
        'verifier' => User::get_csrf() 
    ]
);  
