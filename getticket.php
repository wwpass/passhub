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
//require_once 'src/lib/wwpass.php';
require_once 'vendor/autoload.php';
require_once 'src/functions.php';


try {
    $t0 = microtime(true);
    $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
    if (isset($_REQUEST['ref'])) {  //older versioins of Passkey Lite
        $ticket = $wwc->getTicket(WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'':'');
    } else {
        $ticket = $wwc->getTicket(WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'p:c':'c');
        $dt = number_format((microtime(true) - $t0), 3);
        $sp = explode("@", $ticket)[1];

        timing_log("get    " . $dt . " " . $_SERVER['REMOTE_ADDR'] . " @" . $sp);
    }
} catch (Exception $e) {
    $err_msg = 'Caught exception: '. $e->getMessage();
    passhub_err($err_msg);
    exit();
}

// Prevent caching.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// The JSON standard MIME header.
header('Content-type: application/json');

$data = array("ticket" => $ticket, "ttl" => WWPASS_TICKET_TTL);

// Send the data.
echo json_encode($data);
