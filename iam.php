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
require_once 'src/db/iam_ops.php';
require_once 'src/template.php';


require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

if (!isset($_SESSION['PUID'])) {
    header("Location: login.php?next=iam.php");
    exit();
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

$UserID = $_SESSION['UserID'];

if (isset($_GET['white_list'])) {
    if (!isSiteAdmin($mng, $UserID)) {
        return json_encode(['status' => "Bad Request (94)"]);
    }
    whiteMailList($mng);
    return;
}

if (isset($_POST['newUserMail'])) {
    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf");
        return json_encode(['status' => "Bad Request (68)"]);
    } 
    if (!isSiteAdmin($mng, $UserID)) {
        return json_encode(['status' => "Bad Request (71)"]);
    }

    $email = $_POST['newUserMail'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Content-type: application/json');
        echo json_encode(['status' => 'illegal email address' . htmlspecialchars($email)]);
        return; 
    }
    $email = strtolower($_POST['newUserMail']);
    addWhiteMailList($mng, $email);
    return;
}

if (isset($_POST['deleteMail'])) {
    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf");
        return json_encode(['status' => "Bad Request (68)"]);
    } 
    if (!isSiteAdmin($mng, $UserID)) {
        return json_encode(['status' => "Bad Request (71)"]);
    }

    $email = $_POST['deleteMail'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Content-type: application/json');
        echo json_encode(['status' => 'illegal email address' . htmlspecialchars($email)]);
        return; 
    }
    $email = strtolower($email);
    removeWhiteMailList($mng, $email);
    return;
}


//function getUserArray($mng) {
/*
$query = new MongoDB\Driver\Query([], ['projection' => ["_id" => true, "lastSeen" => true, "email" => true, "site_admin" =>true]]);
$cursor = $mng->executeQuery(DB_NAME . ".users", $query);
$user_array = $cursor->toArray();

$i_am_admin = false;
$admins_found = false;
foreach ($user_array as $user) {
    if (isset($user->site_admin) && ($user->site_admin == true)) {
        $admins_found = true;
        if ($UserID == (string)$user->_id) {
            $i_am_admin = true;
            break;
        }
    }
}

//    passhub_err("- " . $admins_found . " + " . $i_am_admin . " -");

if ($admins_found === false) {
    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->update(['_id' => $id], ['$set' =>['site_admin' => true]]);
    $mng->executeBulkWrite(DB_NAME . ".users", $bulk);

    $query = new MongoDB\Driver\Query([], ['projection' => ["_id" => true, "lastSeen" => true, "email" => true, "site_admin" =>true]]);
    $cursor = $mng->executeQuery(DB_NAME . ".users", $query);
    $user_array = $cursor->toArray();
} else if ($i_am_admin === false) {
    error_page("not enough rights");
}

*/

$user_array = getUserArray($mng, $UserID);

if (count($user_array) == 0) {
    return 'not enough rights';
}

$_SESSION['site_admin'] = true;

$safe_user_array = getSafeUserArray($mng);

$safe_ids = [];

foreach ($safe_user_array as $record) {
    $safe_ids[] = $record->SafeID;
}

sort($safe_ids);

$safes = array_unique($safe_ids);

$safe_histo = array_count_values($safe_ids);
$shared_safes = [];

foreach ( $safe_histo as $k => $v ) {
    if ($safe_histo[$k] >1 ) {
        $shared_safes[] = $k;
    }
}

$stats = "Users: " . count($user_array) . "\n";
$stats .= "Safes total: " . count($safes) . "\n";
$stats .= "Safes shared: " . count($shared_safes) . "\n";

$stats .= "MAIL DOMAINS: " . MAIL_DOMAIN . "\n";


foreach ($user_array as $user) {
    $user->_id = (string)$user->_id;
    $user->safe_cnt =0;
    $user->shared_safe_cnt =0;

    foreach ($safe_user_array as $r) {
        if ($r->UserID == $user->_id) {
            $user->safe_cnt += 1;
            if (in_array($r->SafeID, $shared_safes)) {
                $user->shared_safe_cnt += 1;
            }
        }
    }
}

$top_template = Template::factory('src/templates/top.html');
$top_template /*->add('narrow', false) */
    ->add('iam_page', true)
    ->render();

$iam_template = Template::factory('src/templates/iam.html');
$iam_template->add('users', $user_array)
    ->add('csrf', User::get_csrf())
    ->add('me', $UserID)
    ->add('stats', $stats)
    ->render();
/*
$invite_template = Template::factory('src/templates/modals/invite_by_mail.html');
$invite_template->render();
*/

$progress_template = Template::factory('src/templates/progress.html');
$progress_template->render();

$idle_and_removal_template = Template::factory('src/templates/modals/idle_and_removal.html');
$idle_and_removal_template->render();

?>
</body>
</html>


