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
use PassHub\DB;

$mng = DB::Connection();

session_start();

$t0 = microtime(true);
try {
    $test4 = (intval(explode('.', WWPass\Connection::VERSION)[0]) > 3);


    $old_ticket = $_SESSION['wwpass_ticket'];

    if ($test4) {
        $wwc = new WWPass\Connection(
            ['key_file' => WWPASS_KEY_FILE, 
            'cert_file' => WWPASS_CERT_FILE, 
            'ca_file' => WWPASS_CA_FILE]
        );

        $new_ticket = $wwc->putTicket(
            ['ticket' => $old_ticket,
            'pin' =>  defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false,
            'client_key' => true,
            'ttl' => WWPASS_TICKET_TTL]
        );
        $_SESSION['wwpass_ticket'] = $new_ticket['ticket'];
    } else {
        $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
        $new_ticket = $wwc->putTicket($old_ticket, WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');
        $_SESSION['wwpass_ticket'] = $new_ticket;
    }

    $dt = number_format((microtime(true) - $t0), 3);
    $sp = explode("@", $_SESSION['wwpass_ticket'])[1];

    // Utils::timingLog("update " . $dt . " " . $_SERVER['REMOTE_ADDR'] . " @" . $sp);

    $_SESSION['wwpass_ticket_creation_time'] = time();
} catch (Exception $e) {
    Utils::err("updateTicket error " . $e->getMessage());
}

// Prevent caching.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// The JSON standard MIME header.
header('Content-type: application/json');

 
if ($test4) {
  $data = array("newTicket" => $new_ticket['ticket'], "oldTicket" => $old_ticket,"ttl" => WWPASS_TICKET_TTL);
} else {
  $data = array("newTicket" => $new_ticket, "oldTicket" => $old_ticket,"ttl" => WWPASS_TICKET_TTL);
}

// Send the data.
echo json_encode($data);
