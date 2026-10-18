<?php

/**
 * bind_key.php
 *
 * Bind an additional WWPass key to the account, list and unbind keys.
 *
 * The user private key is encrypted with the WWPass client key, which is
 * different for each WWPass key. So the browser re-encrypts the private key
 * with the client key of the new WWPass key, and the result is stored in the
 * puids record of the new key.
 *
 * Operations:
 *   list    - list keys bound to the account
 *   ticket  - authenticate the new key: req->ticket is a ticket of the new key;
 *             returns the tickets and the private key (encrypted with the current key)
 *   bind    - req->ePrivateKey: private key encrypted with the new key client key
 *   unbind  - req->id: remove an additional key
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2026 WWPass
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

// time to complete the bind operation after the new key is authenticated, seconds
define('BIND_KEY_TIMEOUT', 300);

function isPuidInUse($mng, $PUID) {
    if ($mng->puids->countDocuments(['PUID' => $PUID]) > 0) {
        return true;
    }
    return $mng->users->countDocuments(['PUID' => $PUID]) > 0;
}

function bindKeyTicket($mng, $user, $req) {
    if (!isset($req->ticket) || !is_string($req->ticket)) {
        return "Bad Request (bk 51)";
    }
    $ticket = $req->ticket;
    // the ticket must carry a client key, see loginSPA.php
    if ((strpos($ticket, ':c:') === false)
        && (strpos($ticket, ':pc:') === false)
        && (strpos($ticket, ':cp:') === false)
    ) {
        return "Bad Request (bk 58)";
    }

    try {
        $test4 = (intval(explode('.', WWPass\Connection::VERSION)[0]) > 3);
        if ($test4) {
            $wwc = new WWPass\Connection(
                ['key_file' => WWPASS_KEY_FILE,
                'cert_file' => WWPASS_CERT_FILE,
                'ca_file' => WWPASS_CA_FILE]
            );
            $new_ticket = $wwc->putTicket(
                ['ticket' => $ticket,
                'pin' =>  defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false,
                'client_key' => true,
                'ttl' => WWPASS_TICKET_TTL]
            );
            $new_ticket = $new_ticket['ticket'];
            $PUID = $wwc->getPUID(['ticket' => $ticket]);
            $PUID = $PUID['puid'];
        } else { // version 3
            $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
            $new_ticket = $wwc->putTicket($ticket, WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');
            $PUID = $wwc->getPUID($ticket);
        }
    } catch (Exception $e) {
        Utils::err("bind key: wwp exception: " . $e->getMessage());
        return "WWPass authentication failed. Please try again";
    }

    if ($PUID === $_SESSION['PUID']) {
        return "You are signed in with this WWPass key. Please use another key";
    }
    if (isPuidInUse($mng, $PUID)) {
        Utils::err("bind key: PUID already in use, user " . $user->UserID);
        return "This WWPass key is already used by a PassHub account";
    }

    $_SESSION['bind_key'] = ['PUID' => $PUID, 'time' => time()];

    return [
        'status' => "Ok",
        'ticket' => $new_ticket,
        'currentTicket' => $_SESSION['wwpass_ticket'],
        'ePrivateKey' => $user->getEncryptedPrivateKey(),
    ];
}

function bindKey($mng, $user, $req) {
    if (!isset($_SESSION['bind_key']) || ((time() - $_SESSION['bind_key']['time']) > BIND_KEY_TIMEOUT)) {
        unset($_SESSION['bind_key']);
        return "Time is out. Please try again";
    }
    if (!isset($req->ePrivateKey) || !is_string($req->ePrivateKey) || (strlen($req->ePrivateKey) == 0)) {
        return "Bad Request (bk 111)";
    }
    $PUID = $_SESSION['bind_key']['PUID'];
    unset($_SESSION['bind_key']);

    if (isPuidInUse($mng, $PUID)) {
        return "This WWPass key is already used by a PassHub account";
    }

    $mng->puids->insertOne([
        'PUID' => $PUID,
        'UserID' => $user->UserID,
        'privateKey_CSE' => $req->ePrivateKey,
        'created' => Date('c')
    ]);

    Utils::log("user " . $user->UserID . " bound key " . $PUID . " " . $_SERVER['REMOTE_ADDR']);
    if (!defined('PUBLIC_SERVICE')) {
        $user->getProfile();
        Utils::audit_log($mng, ["actor" => $user->profile->email, "operation" => "Bind WWPass key"]);
    }
    return ['status' => "Ok", 'keys' => $user->getKeys()];
}

function unbindKey($mng, $user, $req) {
    if (!isset($req->id) || !is_string($req->id) || !preg_match('/^[0-9a-f]{24}$/', $req->id)) {
        return "Bad Request (bk 137)";
    }
    $_id = new \MongoDB\BSON\ObjectID($req->id);
    $record = $mng->puids->findOne(['_id' => $_id, 'UserID' => $user->UserID]);

    // only additional keys may be removed, the primary one keeps the profile private key
    if (!$record || !isset($record->privateKey_CSE)) {
        return "Key not found";
    }
    if ($record->PUID === $_SESSION['PUID']) {
        return "You cannot remove the key you are signed in with";
    }
    $mng->puids->deleteOne(['_id' => $_id, 'UserID' => $user->UserID]);

    Utils::log("user " . $user->UserID . " unbound key " . $record->PUID . " " . $_SERVER['REMOTE_ADDR']);
    if (!defined('PUBLIC_SERVICE')) {
        $user->getProfile();
        Utils::audit_log($mng, ["actor" => $user->profile->email, "operation" => "Unbind WWPass key"]);
    }
    return ['status' => "Ok", 'keys' => $user->getKeys()];
}

function bind_key_proxy($mng) {
    if (!isset($_SESSION['PUID']) || !isset($_SESSION['UserID'])) {
        return "login";
    }

    $json = file_get_contents('php://input');
    $req = json_decode($json);

    if (!Csrf::validCSRF($req)) {
        Utils::err("bad csrf");
        return "Bad Request (68)";
    }

    $user = new User($mng, $_SESSION['UserID']);
    $operation = isset($req->operation) ? $req->operation : "";

    if ($operation === "list") {
        return ['status' => "Ok", 'keys' => $user->getKeys()];
    }
    if ($operation === "ticket") {
        return bindKeyTicket($mng, $user, $req);
    }
    if ($operation === "bind") {
        return bindKey($mng, $user, $req);
    }
    if ($operation === "unbind") {
        return unbindKey($mng, $user, $req);
    }
    return "Bad Request (bk 186)";
}

$result = bind_key_proxy($mng);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}

header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

echo json_encode($result);
