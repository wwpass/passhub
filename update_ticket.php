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

require_once 'src/db/user.php';
require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();


passhub_err("old ticket " . $_SESSION['wwpass_ticket']);
$t0 = microtime(true);
try {
  $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
  $old_ticket = $_SESSION['wwpass_ticket'];
  $new_ticket = $wwc->putTicket($old_ticket, WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');

  $dt = number_format((microtime(true) - $t0),3);
  $sp = explode("@", $new_ticket)[1];

  timing_log("update " . $dt . " " . $_SERVER['REMOTE_ADDR'] . " @" . $sp);

  $_SESSION['wwpass_ticket'] = $new_ticket;
  $_SESSION['wwpass_ticket_creation_time'] = time();
  passhub_err("new ticket " .  $_SESSION['wwpass_ticket']);
  passhub_err("ticket_updated");
} catch (Exception $e) {
  passhub_err("updateTicket error " . $e->getMessage());
}

// Prevent caching.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// The JSON standard MIME header.
header('Content-type: application/json');

$data = array("newTicket" => $new_ticket, "oldTicket" => $old_ticket,"ttl" => WWPASS_TICKET_TTL);

// Send the data.
echo json_encode($data);
