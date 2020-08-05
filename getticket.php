<?php

/**
 * getticket.php
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

try {
    $t0 = microtime(true);
    $version4 = WWPass\Connection::VERSION == '4.0';

    $pin_required = defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false;
    
    if ($version4) {
        $wwc = new WWPass\Connection(
            ['key_file' => WWPASS_KEY_FILE, 
            'cert_file' => WWPASS_CERT_FILE, 
            'ca_file' => WWPASS_CA_FILE]
        );
        $ticket = $wwc->getTicket(
            ['pin' => $pin_required,
            'client_key' => true,
            'ttl' => WWPASS_TICKET_TTL]
        );
        $sp = explode("@", $ticket['ticket'])[1];
    } else {
        $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
        $ticket = $wwc->getTicket(WWPASS_TICKET_TTL, $pin_required?'pc':'c');
        $sp = explode("@", $ticket)[1];
    }
    $dt = number_format((microtime(true) - $t0), 3);
    Utils::timingLog("get    " . $dt . " " . $_SERVER['REMOTE_ADDR'] . " @" . $sp);
} catch (WWPass\Exception $e) {
    $err_msg = 'Caught WWPass exception: ' . $e->getMessage();
    Utils::err(get_class($e));
    Utils::err($err_msg);
    $_SESSION['expired'] = true;
} catch (Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    Utils::err(get_class($e));
    Utils::err($err_msg);
    // return 500
    Utils::errorPage("Internal server error idx 159");
}

// Prevent caching.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// The JSON standard MIME header.
header('Content-type: application/json');

if ($version4) {
    $data = $ticket;
} else {
    $data = array("ticket" => $ticket, "ttl" => WWPASS_TICKET_TTL);
}

// Send the data.
echo json_encode($data);
