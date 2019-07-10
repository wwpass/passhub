<?php

/**
 * user.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Vladimir Korshunov <v.korshunov@wwpass.com>
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'vendor/autoload.php';

function newDbConnection() {
    // ssl mode included into connection line; CA certificate made os-wide
    $client = new MongoDB\Client($uri = MONGODB_CONNECTION_LINE);
    $db = $client->selectDatabase(DB_NAME);
    return $db;

}

function getUserByPuid($mng, $puid)
{
    $cursor = $mng->users->find([ 'PUID' => $puid ]);
    $puids = $cursor->toArray();
    $num_puids = count($puids);
    if ($num_puids == 1) {
        $UserID = (string)($puids[0]->_id);
        passhub_err("PUID " . $puid . " found in users " . $UserID);
        return array("UserID" => $UserID, "status" => "Ok");
    }
    if ($num_puids == 0) {  // try legacy table
        $cursor = $mng->puids->find([ 'PUID' => $puid ]);
        $puids = $cursor->toArray();
        $num_puids = count($puids);
        if ($num_puids == 0) {
            return array("status" => "not found");
        }
        if ($num_puids == 1) {
            passhub_err("PUID " . $puid . " found in puids " . $puids[0]->UserID);
            return array("UserID" => $puids[0]->UserID, "status" => "Ok");
        }
    }
    passhub_err("internal error usr 34 count " . $num_puids);
    return array("status" =>"internal error usr 34"); //multiple PUID records;
}

function getAcessibleStorage($mng, $UserID) {

    if (!ctype_xdigit($UserID)) {
        return ['status' => 'Bad argument'];
    }
    $safes = $mng->safe_users->find([ 'UserID' => $UserID]);
    $total = 0;
    foreach ($safes as $safe) {
        $records = $mng->safe_items->find([ 'SafeID' => $safe->SafeID, 'file' =>['$exists' =>true]]);
        foreach ($records as $row) {
            if (property_exists($row->file, 'size')) {
                $total += $row->file->size;
            }
        }
    }
    return ['status' => "Ok", 'total' => $total];
}

function getSharingStatus($mng, $UserID) {
    //    $filter = [ 'UserID' => $UserID , 'valid' => true];
    $filter = [ '$or' => [ ['UserID' => $UserID], ['RecipientID' => $UserID]],  'valid' => true];

    $mng_res = $mng->sharing_codes->find($filter);

    $invited = [];
    $accepted = [];
    $not_confirmed = [];

    foreach ($mng_res as $row) {
        if ($row->RecipientID) {
            if ($row->RecipientID == $UserID) {
                array_push($not_confirmed, $row->SafeID);
            } else {
                array_push($accepted, $row->SafeID);
            }
        } else if (time() - strtotime($row->created) < SHARING_CODE_TTL) {
            array_push($invited, $row->SafeID);
        }
    }
    $invited = array_unique($invited);
    $accepted = array_unique($accepted);
    $not_confirmed = array_unique($not_confirmed); //should be uniqe by def

    return ["status" => "Ok", "invited" => $invited, "accepted" => $accepted, "not_confirmed" => $not_confirmed];

}

// todo check current safe !null

function get_public_key($mng, $UserID) {

    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);

    $cursor = $mng->users->find(['_id' => $id]);

    foreach ($cursor as $row) {
        return $row->pubKey;
    }
    return null;
}

function get_public_key_CSE($mng, $UserID) {

    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);
    $cursor = $mng->users->find(['_id' => $id]);

    foreach ($cursor as $row) {
        return $row->publicKey_CSE;
    }
    return null;
}


function get_private_key_CSE($mng, $UserID) {

    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);
    $cursor = $mng->users->find(['_id' => $id]);

    foreach ($cursor as $row) {
        return $row->privateKey_CSE;
    }
    return null;
}


function _set_current_safe($mng, $UserID, $SafeID) {

    $SafeID=trim((string)$SafeID);
    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);

    $result = $mng->users->updateMany(['_id' => $id], ['$set' =>['currentSafe' => $SafeID]]);
}

function isSiteAdmin($mng, $UserID) {
    $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);
   
    $cursor = $mng->users->find(['_id' => $id, 'site_admin' => true]);
    $user_array = $cursor->toArray();
    return (count($user_array) == 1);
}

class User
{
    public $UserID;
    public $safe_array;
    public $mng;
    public $status;  //possible errors in constructor
    public $current_safe;
    public $isCSE;
    public $privateKey_CSE;
    public $publicKey_CSE;
    public $email;

    function __construct($db, $ID) {
        $this->UserID = (string)$ID;
        $this->mng = $db;
        $this->isCSE = false;

        if (!ctype_xdigit($this->UserID)) {
            $this->status =  "Bad argument";
            return;
        }
        $mng_res = $this->mng->safe_users->find([ 'UserID' => $this->UserID]);

        $this->safe_array = array();
        foreach ($mng_res as $row) {
            $id = $row->SafeID;
            $this->safe_array[$id] = new Safe($row);
        }

        /*
        $filter = [ 'UserID' => $this->UserID , 'valid' => true, 'RecipientID' => ['$ne' => null]];

        $query = new MongoDB\Driver\Query($filter);
        $mng_res = $this->mng->executeQuery(DB_NAME . ".sharing_codes", $query);


        foreach($mng_res as $row) {
            $id = $row->SafeID;
            if(isset($this->safe_array[$id])) {
                $this->safe_array[$id]->confirm_req = 1;
            }
        }
        */
        $sharing_status = getSharingStatus($this->mng, $this->UserID);

        foreach ($sharing_status['accepted'] as $id) {
            if (isset($this->safe_array[$id])) {
                $this->safe_array[$id]->confirm_req = 1;
            }
        }
        $this->invitation_accept_pending = count($sharing_status['invited']);


        uasort($this->safe_array, 'cmp_vault_names');
        $this->current_safe = key($this->safe_array);

        /*
        $this->getCurrentSafe(); //and privateKey_CSE
        */

        $id =  (strlen($this->UserID) != 24)? $this->UserID : new MongoDB\BSON\ObjectID($this->UserID);

        $mng_res = $this->mng->users->find(['_id' => $id]);
        $res_array = $mng_res->ToArray();

        if (count($res_array) != 1) {
            passhub_err("error user 253 count " . count($res_array));
            exit("error user 253");
        }
        $row = $res_array[0];
        if (isset($row->currentSafe) && ($row->currentSafe != null)) {
            if (isset($this->safe_array[$row->currentSafe])) {
                $this->current_safe = $row->currentSafe;
            }
        }
        // side effect: costs nothing, used when called from constructor
        if (isset($row->publicKey_CSE) && isset($row->privateKey_CSE)) {
            $this->privateKey_CSE = $row->privateKey_CSE;
            $this->publicKey_CSE = $row->publicKey_CSE;
            $this->isCSE = true;
        }
        
        $this->site_admin = isset($row->site_admin) ? $row->site_admin : false; 
        $this->email = isset($row->email) ? $row->email : ''; 

        $this->mng->users->updateMany(['_id' => $id], ['$set' =>['lastSeen' =>Date('c')]]);

        $this->status = "Ok";
    }

    function getData() {
        $response = array();
        foreach ($this->safe_array as $safe) {
            if ($safe->isConfirmed()) {
                $items = get_item_list_cse($this->mng, $this->UserID, $safe->id);
                $folders = get_folder_list_cse($this->mng, $this->UserID, $safe->id);
            } else {
                $items = [];
                $folders = [];
            }
            $safe_entry = [
                "name" => $safe->name,
                "user_name" => $safe->user_name,
                "id" => $safe->id,
                'confirm_req' => $safe->confirm_req,
                'confirmed' => $safe->isConfirmed(),
                "key" => $safe->encrypted_key_CSE,
                "items" => $items,
                "folders" => $folders
            ];
            array_push($response, $safe_entry);
        }
        return $response;
    }

    // NOTE: repeated in constructor, not used anywhere else, comment it out 
    /*    
    function getCurrentSafe() {

        $id =  (strlen($this->UserID) != 24)? $this->UserID : new MongoDB\BSON\ObjectID($this->UserID);
        $query = new MongoDB\Driver\Query(['_id' => $id]);
        $mng_res = $this->mng->executeQuery(DB_NAME . ".users", $query);
        $res_array = $mng_res->ToArray();

        if(count($res_array) == 1) {
            $row = $res_array[0];
            if(isset($row->currentSafe) && ($row->currentSafe != null)) {
                if(isset($this->safe_array[$row->currentSafe])) {
                    $this->current_safe = $row->currentSafe;
                }
            }
            // side effect: costs nothing, used when called from constructor
            if(isset($row->publicKey_CSE) && isset($row->privateKey_CSE)) {
                $this->privateKey_CSE = $row->privateKey_CSE;
                $this->publicKey_CSE = $row->publicKey_CSE;
                $this->isCSE = true;
            }
            return $this->current_safe;
        }
        passhub_err("error user 157 count " . count($res_array) );
        exit("error user 157");
    }
    */

    function setCurrentSafe($safeID) {
        $safeID=trim((string)$safeID);

        if (isset($this->safe_array[$safeID])) {
            $this->current_safe = $safeID;
            $id =  (strlen($this->UserID) != 24)? $this->UserID : new MongoDB\BSON\ObjectID($this->UserID);
            $this->mng->users->updateMany(['_id' => $id], ['$set' =>['currentSafe' =>$safeID]]);
        }
        return $this->current_safe;
    }

    // to CSE
    // TODO test unknown pubicKey,
    // TODO unconfirmed safe does not have a key
    function upgrade($publicKey, $encryptedPrivateKey) {

        $id =  (strlen($this->UserID) != 24)? $this->UserID : new MongoDB\BSON\ObjectID($this->UserID);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(['_id' => $id], ['$set' =>['publicKey_CSE' =>$publicKey, 'privateKey_CSE' => $encryptedPrivateKey]]);
        $this->mng->executeBulkWrite(DB_NAME . ".users", $bulk);
        $privKey = get_private_key();
        foreach ($this->safe_array as $safe) {
            // passhub_err("upgrading user " . $this->UserID . "safe " . $safe->id);
            if ($safe->encrypted_key) {
                $crypted = hex2bin($safe->encrypted_key);
                $result = openssl_private_decrypt($crypted, $key, $privKey);
                if ($result) {
                    $result = openssl_public_encrypt($key, $crypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
                    if ($result) {
                        $encrypted_key_CSE = bin2hex($crypted);
                        $bulk = new MongoDB\Driver\BulkWrite();
                        $bulk->update(['SafeID' => $safe->id, 'UserID' => $this->UserID], ['$set' =>['encrypted_key_CSE' =>$encrypted_key_CSE]]);
                        $result = $this->mng->executeBulkWrite(DB_NAME . ".safe_users", $bulk);
                        // passhub_err(print_r($result, true));

                    } else {
                        passhub_err("error upgrade 190");
                        return ("error upgrade 190");
                    }
                } else {
                    passhub_err("error upgrade 194");
                    return ("error upgrade 194");
                }
            } else {
                passhub_err("upgrading safe " . $safe->id . " unconfirmed, skipped");
            }
        }
        return "Ok";
    }

    static function get_csrf($renew = null) {
        if (!isset($_SESSION['csrf']) || $renew) {
            $bytes = random_bytes(256);
            $csrf = bin2hex($bytes);
            $_SESSION['csrf'] = $csrf;
        }
        return password_hash($_SESSION['csrf'], PASSWORD_DEFAULT);
    }
    static function is_valid_csrf(string $hash) {
        if (isset($_SESSION['csrf']) ) {
            return password_verify($_SESSION['csrf'], $hash);
        }
        return false;
    }
}

function getUserData($mng, $UserID)
{
    $user = new User($mng, $UserID);

    $data = [
        'publicKeyPem' => $user->publicKey_CSE,
        'invitation_accept_pending' => $user->invitation_accept_pending,
        'currentSafe' => $user->current_safe,
        'user_mail' => $user->email,
        'ePrivateKey' => $user->privateKey_CSE,
        'safes' => $user->getData(),
        'ticket' => $_SESSION['wwpass_ticket']
    ];
    
    if (array_key_exists('folder', $_GET)) {
        $data['active_folder'] = $_GET['folder'];
    } else {
        $data['active_folder'] = 0;
    }
    if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
        $data['shareModal'] = "#safeShareModal";
    } else {
        $data['shareModal'] = "#shareByMailModal";
    }
    if (isset($_REQUEST['show_table'])) {
        $data['show_table'] = true;
    } else {
        $data['show_table'] = false;
    }
    if (WWPASS_LOGOUT_ON_KEY_REMOVAL 
        && array_key_exists('PUID', $_SESSION)
        && !isset($_SESSION['PasskeyLite'])
    ) {
        $data['onkeyremoval'] = true;
    } else {
        $data['onkeyremoval'] = false;
    }
    return ['status' => 'Ok', 'data' => $data];
}


// assing user a Keypair and UserID and link UserID to PUID; return UserID

function create_user($mng, $puid, $post /* $publicKey, $encryptedPrivateKey*/) {

    if (defined('MAIL_DOMAIN')) {
        $cursor = $mng->reg_codes->find(['PUID' => $puid, 'verified' => true]);
        $puids = $cursor->toArray();
        $num_puids = count($puids);
        if ($num_puids == 1) {  //found, delete all others
            if (property_exists($puids[0], 'email')) {
                $email = $puids[0]->email;
            }
        }
    }

    $record = [
        'publicKey_CSE' =>$post['publicKey'],
        'privateKey_CSE' => $post['encryptedPrivateKey'],
        'currentSafe' => null
    ];

    if (isset($email)) {
        $record['email'] = $email;
    }

    try {
        $r = $mng->users->insertOne($record);
    } catch (Exception $e) {
    }

    $UserID = (string)$r->getInsertedId();
    if (1) {
        $mng->puids->insertOne(['PUID' => $puid, 'UserID' => $UserID]);
    }
    if (isset($post['import'])) {  // right way
        import_safes($mng, $UserID, $post);
    }

    /*
    if(isset($post['safes'])) {  // right way

        foreach( $post['safes'] as $safe) {
            $result = create_safe1($mng, $UserID, $safe);
        }
    } else {  //old wrong way
    */

    if (isset($email)) {
        passhub_log("new user $email $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
    } else {
        passhub_log("new user $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
    }

    return array("UserID" => (string)$UserID, "status" => "Ok");
}

function process_reg_code($mng, $code, $PUID) {
    $cursor = $mng->reg_codes->find(['code' => $code]);
    $codes = $cursor->toArray();
    $num_codes = count($codes);
    if ($num_codes == 0) {
        return "no such registration code found: " . $code;
    }
    if ($num_codes == 1) {
        if ($PUID === $codes[0]->PUID) {
            if ($codes[0]->verified == false) {
                $mng->reg_codes->updateOne(['code' => $code], ['$set' =>['verified' =>true]]);

                // PUID verified, delete all other codes
                $mng->reg_codes->deleteMany(['PUID' => $PUID, 'verified' =>false]);

                if ($_SESSION['UserID']) { // adding mail to already existing account (intermediate)
                    $id =  (strlen($_SESSION['UserID']) != 24)? $_SESSION['UserID'] : new MongoDB\BSON\ObjectID($_SESSION['UserID']);
                    $result = $mng->users->updateOne(
                        ['_id' => $id], 
                        ['$set' => ['email' => $codes[0]->email]]
                    );
                    passhub_err(print_r($result, true));
                }
                return "Ok";
            }
            return "Verification code already used";
        }
        return "You must log in with the same PassKey that you used when submitting your e-mail address.";
    }
    passhub_err("internal error usr 312 count " . $num_puids);
    return "internal error usr 312"; //multiple code records;
}

function isPuidValidated($mng, $PUID) {
    $cursor = $mng->reg_codes->find(['PUID' => $PUID, 'verified' => true]);
    $puids = $cursor->toArray();
    $num_puids = count($puids);
    if ($num_puids == 0) {
        return false;
    }
    if ($num_puids == 1) {  //found, delete all others
        return true;
    }
    passhub_err("internal error usr 339 count " . $num_puids);
    return false; //multiple code records;
}

