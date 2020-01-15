<?php

/**
 * edit.php
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
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/db/item.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

if (!isset($_REQUEST['id'])|| (ctype_xdigit($_REQUEST['id']) == false)) {
    echo "error 36: illegal URL";
    exit();
}

$entryID = trim($_REQUEST['id']);
if (ctype_xdigit($entryID) == false) {
    passhub_err("illegal edit URL");
    exit("illegal edit URL");
}

try {
    update_ticket();
} catch (Exception $e) {
    $_SESSION['expired'] = true;
    header("Location: index.php");
    exit();
}

$UserID = $_SESSION['UserID'];

$result = get_item_cse($mng, $UserID, $entryID);

$item = $result['item'];
$SafeID = $item->SafeID;

$note = isset($item->note)? $item->note:0;
$title = $note ? "Edit Note" : "Edit Entry";

if (!can_write($mng, $UserID, $SafeID)) {
    message_page($title, "You do not have editor rights for this safe");
    exit();
}

$encrypted_key_CSE = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
$privateKey_CSE = get_private_key_CSE($mng, $UserID);

if (($encrypted_key_CSE == null) || ($privateKey_CSE == null)) {
    passhub_err("edit 53");
    error_page("Error: (edit) 53");
}

$folder = isset($item->folder)? $item->folder:0;

echo theTwig()->render(
    'item_form.html', 
    [
        // layout
        'narrow' => true, 
        'PUBLIC_SERVICE' => PUBLIC_SERVICE, 

        // content
        'title' => $title,
        'vault_id' => $SafeID,
        'folder' => $folder,
        'entry_id' => htmlspecialchars($entryID),
        'encrypted_key_CSE' => $encrypted_key_CSE,
        'privateKey_CSE' => $privateKey_CSE,
        'password_font' => getPwdFont(),
        'note' => $note,
        'create' => 0,
        'ticket' => $_SESSION['wwpass_ticket'],
        'verifier' =>  User::get_csrf(), 
        'item' => json_encode($item),
        'MAX_NOTES_SIZE' => defined('MAX_NOTES_SIZE') ? MAX_NOTES_SIZE : 2048,

        // idle_and_removal
        'WWPASS_TICKET_TTL' => WWPASS_TICKET_TTL, 
        'IDLE_TIMEOUT' => IDLE_TIMEOUT,
        'ticketAge' =>  (time() - $_SESSION['wwpass_ticket_creation_time'])
    ] 
);
