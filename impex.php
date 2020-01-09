<?php

/**
 * backup.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016 WWPass
 */

// NOTE: Excel 2016 does not quote spaces, does not load multiline values, adds EFBBBF when expoorted to UTF-8 CSV


require_once 'config/config.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/db/item.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();


function impex_proxy($mng) {

    if (!isset($_SESSION['UserID'])) {
        return "login";
    }
    $UserID = $_SESSION['UserID'];
    try {
        update_ticket();
    } catch (Exception $e) {
        passhub_err('Caught exception: ' . $e->getMessage());
        $_SESSION['expired'] = true;
        return "login";
    }

    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("impex bad verifier");
        return "Internal error";
    }

    if (isset($_POST['export'])) {
        $user = new User($mng, $UserID);
        $response = array();
        foreach ($user->safe_array as $safe) {
            $items = get_item_list_cse($mng, $UserID, $safe->id, ['no_files' => true]);
//            $items = get_item_list_cse($mng, $UserID, $safe->id);
            $folders = get_folder_list_cse($mng, $UserID, $safe->id);

//            if(count($items) > 0) {
                $safe_entry = array("name" => $safe->name, "id" => $safe->id, "key" => $safe->encrypted_key_CSE, "items" => $items, "folders" => $folders);
                array_push($response, $safe_entry);
//            }
        }
        if (count($response) == 0) {
            return "No records found";
        } else {
            return array("status" => "Ok", "data" => $response );
        }
    } else if (isset($_POST['import'])) {
        return import_safes($mng, $UserID, $_POST);
    } else {
        passhub_err("Backup internal error 67 " . print_r($_POST,true));
        return  "Backup internal error 67";
    }
}

$result = impex_proxy($mng);

if (gettype($result) == "string") {
    $result = array("status" => $result);
}


header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');
header('Content-type: application/json');

echo json_encode($result);
exit();
