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
/*
if( !isset($_REQUEST['vault'])|| (ctype_xdigit($_REQUEST['vault']) == false)) {
   passhub_err("error 33 new");
   error_page("error 33 new");
}
*/
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

if (defined('FILE_DIR')  || defined('GOOGLE_CREDS')) {
    $show_file_button = true;
} else {
    $show_file_button = false;
}
if (defined('TEST_USERS') && !in_array($UserID, TEST_USERS)) {
    $show_file_button = false;
}

if (!$show_file_button) {
    error_page("site is misconfigured (error 59 F)");
    // exit();
}

$folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

$encrypted_key_CSE = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
$privateKey_CSE = get_private_key_CSE($mng, $UserID);


if (($encrypted_key_CSE == null) || ($privateKey_CSE == null)) {
    passhub_err("new 46");
    error_page("Error: (new) 46");
}

$result = getAcessibleStorage($mng, $UserID);
if ($result['status'] == "Ok") {
    if ($result['total'] < 10*1024) {
        $human_readable_total = $result['total'] . " Bytes";
    } else if ($result['total'] < 10*1024*1024) {
        $human_readable_total = (int)($result['total']/1024) . " kB";
    } else {
        $human_readable_total = (int)($result['total']/1024/1024) . " MB";
    }
    if (!defined('MAX_STORAGE')) {
        $total = $human_readable_total;
    } else {
        $total = $human_readable_total . " out of " . MAX_STORAGE . " MBytes (" . sprintf("%.2f", $result['total']/MAX_STORAGE*100/1024/1024) . "%)";
    }
}

$top_template = Template::factory('src/templates/top.html');
$top_template->add('narrow', true)
    ->render();

if (!defined('MAX_FILE_SIZE')) {
    $max_file_size = 5;
} else {
    $max_file_size = MAX_FILE_SIZE;
}
$item_template = Template::factory('src/templates/new_file.html');
$item_template->add('vault_id', $SafeID)
    ->add('folder', $folder)
    ->add('encrypted_key_CSE', $encrypted_key_CSE)
    ->add('privateKey_CSE', $privateKey_CSE)
    ->add('max_file_size', $max_file_size)
    ->add('used', $total)
    ->render();

$progress_template = Template::factory('src/templates/progress.html');
$progress_template->render();

$idle_and_removal_template = Template::factory('src/templates/modals/idle_and_removal.html');
$idle_and_removal_template->render();

?>
</body>
</html>


