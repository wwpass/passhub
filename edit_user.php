<?php

/**
 * edit_user.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2019 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';

require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;

$mng = DB::Connection();

session_start();

function edit_user_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf");
        return "Bad Request (44)";
    }
    if (!array_key_exists('id', $_POST)) {
        Utils::err("usr ed 49");
        return "internal error ed 49";
    }
    if ($_POST['id'] == $_SESSION['UserID']) {
        Utils::err("usr ed 59");
        return "internal error ed 59";
    }
    $adm = new User($mng, $_SESSION['UserID']);
    if (!$adm->isSiteAdmin()) {
        Utils::err("usr ed user 63");
        return "usr ed user 63";
    }
    $user = new User($mng, $_POST['id']);
    return $user->toggleSiteAdmin();
}

$result = edit_user_proxy($mng);


header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

if (gettype($result) == "string") {
    $result = array("status" => $result);
}


// Send the data.
echo json_encode($result);
