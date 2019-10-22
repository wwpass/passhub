<?php

/**
 * new.php
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
require_once 'src/template.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_SESSION['UserID'])) {
    header("Location: logout.php");
    exit();
}

if (!isset($_REQUEST['vault'])|| (ctype_xdigit($_REQUEST['vault']) == false)) {
    passhub_err("error 33 new");
    error_page("error 33 new");
}

try {
    update_ticket();
} catch (Exception $e) {
    $_SESSION['expired'] = true;
    passhub_err('Caught exception: ' . $e->getMessage());
    header("Location: expired.php");
    exit();
}

$SafeID = $_REQUEST['vault'];
$UserID = $_SESSION['UserID'];

$note = (isset($_REQUEST['note']))? 1:0;
$title = $note ? "Create Note" : "Create Entry";

if (!can_write($mng, $UserID, $SafeID)) {
    message_page($title, "You do not have editor rights for this safe");
    exit();
}

$usedResources = used_resources($mng, $UserID);

if (array_key_exists('maxRecords', $usedResources) 
    && ($usedResources['records'] >= $usedResources['maxRecords'])
) {
    message_page(
        $title,
        "Sorry you have already reached maximum alowed number of " 
        . $usedResources['maxRecords'] 
        . " records"
    );
    exit();
}


$encrypted_key_CSE = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
$privateKey_CSE = get_private_key_CSE($mng, $UserID);

if (($encrypted_key_CSE == null) || ($privateKey_CSE == null)) {
    passhub_err("new 46");
    error_page("Error: (new) 46");
}

$folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

Template::factory('src/templates/top.html')
    ->add('narrow', true)
    ->render();

Template::factory('src/templates/item_form.html')
    ->add('title', $title)
    ->add('vault_id', $SafeID)
    ->add('folder', $folder)
    ->add('encrypted_key_CSE', $encrypted_key_CSE)
    ->add('privateKey_CSE', $privateKey_CSE)
    ->add('password_font', getPwdFont())
    ->add('note', $note)
    ->add('create', 1)
    ->add('item', json_encode([]))
    ->render();

Template::factory('src/templates/modals/gen_password.html')
    ->render();

Template::factory('src/templates/progress.html')
    ->render();

Template::factory('src/templates/modals/idle_and_removal.html')
    ->render();

?>
</body>
</html>
