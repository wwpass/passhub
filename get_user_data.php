<?php

/**
 *
 * get_user_data.php
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

function getUserDataProxy($mng)
{
    if (!isset($_SESSION['UserID'])) {
        return ["status" => "login"];
    }

    if (isset($_POST['verifier']) && !Csrf::isValid($_POST['verifier'])) {
        Utils::err("get user data bad verifier " . $_POST['verifier'] . " vs " . $_SESSION['csrf']);
        return "Internal error";
    } else if (isset($_POST['csrf']) && !Csrf::isValid($_POST['csrf'])) {
        Utils::err("get user data bad verifier " . $_POST['csrf'] . " vs " . $_SESSION['csrf']);
        return "Internal error";
    } else if (!isset($_POST['verifier']) && !isset($_POST['csrf']) ) {
        Utils::err("get user data no verifier ");
        return "Internal error";
    }

    $user = new User($mng, $_SESSION['UserID']);
    return $user->getData();
}

$result = getUserDataProxy($mng);

if (!is_array($result)) {
    $result = array("status" => $result);
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);

