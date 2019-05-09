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
require_once 'src/template.php';

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

$UserID = $_SESSION['UserID'];

try {
    update_ticket();
} catch (Exception $e) {
    $_SESSION['expired'] = true;
    header("Location: index.php");
    exit();
}

$result = get_item_cse($mng, $UserID, $entryID);

$item = $result['item'];

$note = isset($item->note)? $item->note:0;
$folder = isset($item->folder)? $item->folder:0;
$SafeID = $item->SafeID;

$encrypted_key_CSE = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
$privateKey_CSE = get_private_key_CSE($mng, $UserID);

if (($encrypted_key_CSE == null) || ($privateKey_CSE == null)) {
    passhub_err("edit 53");
    error_page("Error: (edit) 53");
}

$password_font = getPwdFont();

$top_template = Template::factory('src/templates/top.html');
$top_template->add('narrow', true)
    ->render();

passhub_err('note = ' . $note);
//$item_template = Template::factory('src/templates/edit_item.html');
$item_template = Template::factory('src/templates/item_form.html');
$item_template->add('item', json_encode($item))
    ->add('encrypted_key_CSE', $encrypted_key_CSE)
    ->add('privateKey_CSE', $privateKey_CSE)
    ->add('vault_id', htmlspecialchars($SafeID))
    ->add('entry_id', htmlspecialchars($entryID))
    ->add('password_font', $password_font)
    ->add('create', 0)
    ->add('folder', $folder)
    ->add('note', $note)
    ->render();

$gen_password_template = Template::factory('src/templates/modals/gen_password.html');
$gen_password_template->render();

$progress_template = Template::factory('src/templates/progress.html');
$progress_template->render();

$idle_and_removal_template = Template::factory('src/templates/modals/idle_and_removal.html');
$idle_and_removal_template->render();


?>
</body>
</html>
