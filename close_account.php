<?php

/**
 * registration_action.php
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
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

require_once 'Mail.php';

$mng = DB::Connection();

session_start();

if (!isset($_SESSION['PUID'])) {
    header("Location: login.php?next=account.php");
    exit();
}

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

$phase = 1;
if (isset($_POST['verifier1']) && ($_POST['verifier1'] == Csrf::isValid($_POST['verifier1']))) {
    $phase = 2;
} else if (isset($_POST['verifier2']) && ($_POST['verifier2'] ==  Csrf::isValid($_POST['verifier2']))) {
    $UserID = $_SESSION['UserID'];
    $user = new User($mng, $UserID);
    $result = $user->deleteAccount();
    if (gettype($result) == "string") {
        $result = array("status" => $result);
    }
    session_destroy();
    $phase = 3;
}

echo Utils::render(
    'close_account.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
        'hide_logout' => true,

        //content
        // 'email' => $_SESSION['form_email'],
        'verifier' => Csrf::get(),
        'phase' => $phase,
    ]
);
