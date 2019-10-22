<?php

/**
 * newfile.php
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

if (!defined('FILE_DIR') && !defined('GOOGLE_CREDS') && !defined('S3_CONFIG')) {
    error_page("site is misconfigured (error 59 F)");
    // exit();
}

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

$title = "Add File";

$can_write = can_write($mng, $UserID, $SafeID);

if (!$can_write) {
    message_page($title, "Sorry you do not have editor rights for this safe");
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

$folder = isset($_REQUEST['folder'])? $_REQUEST['folder'] : 0;

$encrypted_key_CSE = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
$privateKey_CSE = get_private_key_CSE($mng, $UserID);


if (($encrypted_key_CSE == null) || ($privateKey_CSE == null)) {
    passhub_err("new 46");
    error_page("Error: (new) 46");
}

$top_template = Template::factory('src/templates/top.html');
$top_template->add('narrow', true)
    ->render();

if (!defined('MAX_FILE_SIZE')) {
    $max_file_size = 5 * 1024 *1024;
} else {
    $max_file_size = MAX_FILE_SIZE;
}
Template::factory('src/templates/new_file.html')
    ->add('vault_id', $SafeID)
    ->add('folder', $folder)
    ->add('encrypted_key_CSE', $encrypted_key_CSE)
    ->add('privateKey_CSE', $privateKey_CSE)
    ->add('max_file_size', $max_file_size)
    ->add('storage', json_encode($usedResources))
    ->render();

Template::factory('src/templates/progress.html')
    ->render();

Template::factory('src/templates/modals/idle_and_removal.html')
    ->render();

?>
</div>
</body>
</html>


