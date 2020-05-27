<?php

/**
 * safe.php
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

define('ROLE_READONLY', 'readonly');
define('ROLE_EDITOR', 'editor');
define('ROLE_ADMINISTRATOR', 'administrator');

// safe as seen by the user
class Safe
{
    public $id;
    public $name;
    private $user_id;
    public $user_name;
    public $encrypted_key;
    public $encrypted_key_CSE;
    public $confirm_req;
    public $user_role;
    public $user_count;

    function __construct($row) {
        $this->id = $row->SafeID;
        $this->name = $row->SafeName;
        if ($this->name == '') {
            $this->name =  "My First Safe";  // TODO: prevent user to name safe this way
        }
        $this->user_id = $row->UserID;
        $this->user_name = $row->UserName;
        $this->user_role = $row->role;
        // at least one of the two
        //SSE
        $this->encrypted_key = isset($row->encrypted_key) ? $row->encrypted_key:null;
        $this->encrypted_key_CSE = isset($row->encrypted_key_CSE) ? $row->encrypted_key_CSE:null;
        $this->confirm_req = 0;
        $this->user_count = 1;
    }

    function isConfirmed() {
        if (($this->encrypted_key_CSE != null) || ($this->encrypted_key != null) ) {
            return true;
        }
        return false;
    }
}



//********************

function get_user_role($mng, $UserID, $SafeID)
{
    $UserID = (string)$UserID;
    $SafeID = (string)$SafeID;

    $cursor = $mng->safe_users->find(['SafeID' => $SafeID, 'UserID' => $UserID]);
    $a = $cursor->toArray();
    if (count($a) != 1) {
        passhub_err("get_role error 134 count " . count($a) . " UserID " . $UserID . " SafeID " . $SafeID);
        return false;
    }
    $row = $a[0];
    $safe = new Safe($row);

    if ($safe->isConfirmed() == false) {
        return false;
    }
    return $row->role;
}


function can_write($mng, $UserID, $SafeID)
{
    $role = get_user_role($mng, $UserID, $SafeID);
    return (($role == ROLE_ADMINISTRATOR) || ($role == ROLE_EDITOR));
}

function can_read($mng, $UserID, $SafeID)
{
    return (get_user_role($mng, $UserID, $SafeID) != false);
}

function is_admin($mng, $UserID, $SafeID)
{
    return (get_user_role($mng, $UserID, $SafeID) == ROLE_ADMINISTRATOR);
}

// SSE only
function get_aes_key(&$mng, &$UserID, &$SafeID) {

    $cursor = $mng->safe_users->find([ 'UserID' => $UserID, 'SafeID' => $SafeID]);

    $rows = $cursor->toArray();

    if (count($rows) == 0) {
        passhub_err("get aes_key count 0");
        return null;
    }

    $row = $rows[0];

    $hex_crypted = $row->encrypted_key;

    // return NULL if user access is not confirmed yet
    if ($hex_crypted == null) {
        return null;
    }

    $crypted = hex2bin($hex_crypted);
    $privKey = get_private_key();
    $result = openssl_private_decrypt($crypted, $key, $privKey);
    echo " -- $SafeID --  $hex_crypted ++<br>";
    echo bin2hex($key);
    exit();
    return $key;
}

function get_encrypted_aes_key($mng, $UserID, $SafeID) {

    $cursor = $mng->safe_users->find([ 'UserID' => $UserID, 'SafeID' => $SafeID]);

    $rows = $cursor->toArray();

    if (count($rows) == 0) {
        passhub_err("get aes_key count 0");
        return null;
    }

    $row = $rows[0];

    $hex_crypted = $row->encrypted_key;
    return $hex_crypted;
}


function get_encrypted_aes_key_CSE($mng, $UserID, $SafeID) {

    $cursor = $mng->safe_users->find([ 'UserID' => $UserID, 'SafeID' => $SafeID]);

    $rows = $cursor->toArray();

    if (count($rows) == 0) {
        passhub_err("get aes_key count 0");
        return null;
    }

    $row = $rows[0];

    $hex_crypted = $row->encrypted_key_CSE;
    return $hex_crypted;
}


//**************************************************************************************

function create_safe1($mng, $UserID, $safe) {
    if ($safe['name'] == "") {
        return "Please fill in new safe name";
    }
    $mng_res = $mng->safe_users->find(['UserID' => $UserID]);
    $SafeID = (string)new MongoDB\BSON\ObjectId();

    $mng->safe_users->insertOne(
        ['SafeID' => $SafeID, 'UserID' => $UserID, 'SafeName' =>$safe['name'], 'UserName' => null,
        'role' => ROLE_ADMINISTRATOR, 'encrypted_key_CSE' => $safe['aes_key']]
    );
    passhub_log('user ' . $UserID . ' activity safe created');
    _set_current_safe($mng, $UserID, $SafeID);
    return array("status" =>"Ok", "id" => $SafeID);
}

/*
function create_safe($mng, $UserID, $SafeName, $hex_crypted_key = null) {

    if ($SafeName == "") {
        return "Please fill in new safe name";
    }
    $mng_res = $mng->safe_users->find(['UserID' => $UserID]);

    $cursor = $mng->safe_users->find(['UserID' => $UserID, 'SafeName' => $SafeName]);

    foreach ($cursor as $row) {
        return "Password Safe $SafeName already exists";
    }

    if ($hex_crypted_key === null) {
        // test input: UserID - numeric, pubKey has some visible length
        $pubKey = get_public_key_CSE($mng, $UserID);
        if (!$pubKey) {
            return "error safe 112";
        }
        $key =  random_bytes(32);
        $result = openssl_public_encrypt($key, $crypted, $pubKey,  OPENSSL_PKCS1_OAEP_PADDING);
        $hex_crypted_key = bin2hex($crypted);
    }

    $SafeID = (string)new MongoDB\BSON\ObjectId();
    $mng->safe_users->insertOne(
        ['SafeID' => $SafeID, 'UserID' => $UserID, 'SafeName' =>$SafeName, 'UserName' => null,
        'role' => ROLE_ADMINISTRATOR, 'encrypted_key_CSE' => $hex_crypted_key]
    );
    return array("status" =>"Ok", "id" => $SafeID);
}
*/

//**********************************************************************
// local helper function should only be called from safe_acl

function pending_confirmation($mng, $UserID, $SafeID) {

    $filter = ['UserID' => $UserID, 'SafeID' => $SafeID, 'valid' => true, 'RecipientID' => ['$ne' => null]];
    $cursor = $mng->sharing_codes->find($filter);
    foreach ($cursor as $row) {
        return true;
    }
    return false;
}

// syncrhonize: valid = false/true and encryption_key != null

//function safe_acl($mng, $UserID, $SafeID, $operation, $UserName, $RecipientKey, $role)
function safe_acl($mng, $UserID, $post) {

    $SafeID = $post['vault'];
    $operation = isset($post['operation']) ? $post['operation']: null;
    
    if (!ctype_xdigit($UserID) || !ctype_xdigit($SafeID)) {
        return "Bad arguments";
    }

    $myrole = get_user_role($mng, $UserID, $SafeID);

    if ($operation == "unsubscribe") {
        if ($myrole == ROLE_ADMINISTRATOR) {
            return "Administrators cannot leave the safe users group";
        }
        $mng->sharing_codes->deleteMany(['SafeID' => $SafeID, 'RecipientID' => $UserID]);

        $result = $mng->safe_users->deleteMany(['SafeID' => $SafeID, 'UserID' => $UserID]);
        if ($result->getDeletedCount() == 1) {
            return "Ok";
        }
        passhub_err(print_r($result, true));
        return "Internal error acl 442";
    }
 
    if (!$myrole) {
        return "error 275";
    }

    $UserName = isset($post['name']) ? $post['name']: null;
    $RecipientKey = isset($post['key']) ? $post['key']: null;
    $role = isset($post['role']) ? $post['role']: null;
    
    $update_page_req = false;
    if ($UserName != null) {
        if ($myrole != ROLE_ADMINISTRATOR) {
            return "You do not have administrative rights";
        }

        if ($operation == 'email') { //share by email

            $pregUserName = preg_quote($UserName);
            $a = (
                $mng->users->find(
                    ['email' => new MongoDB\BSON\Regex('^' . $pregUserName . '$', 'i')]
                )
            )->toArray();

            if (count($a) > 1) {
                passhub_err("error acl 300");
                return "error acl 300";
            }
            if (count($a) == 0) {
                $email = htmlspecialchars($UserName);
                $email_link = htmlspecialchars($UserName) 
                    . "?subject=" 
                    . htmlspecialchars("I would like to share a safe with you in " . $_POST['origin'])
                    . "&amp;body="
                    . htmlspecialchars(
                        "I would like to share a safe with you in PassHub. "
                        . "If you’re new to PassHub, it is easy and fast "
                        . "to get started. To access this safe, you will first need "
                        . "to download and initialize the WWPass PassKey mobile app "
                        . "from the android or iOS store. Once your PassKey is ready, "
                        . "please visit " . $_POST['origin'] . " and use the PassKey app to login"
                        . " to your PassHub account."
                    );  
                    
                passhub_err("share by mail: User with " . htmlspecialchars($UserName) . " not registered");
                return "User " . $email . " is not registered."
                ." <a href='mailto:$email_link' class='alert-link'>Send invitation</a>";
            }
            $TargetUserID = (string)($a[0]->_id);
            if ($TargetUserID == $UserID) {
                return "You cannot share the safe with yourself (" . $UserName . ")";
            }
            $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
            $cursor = $mng->safe_users->find($filter);
            if (count($cursor->toArray()) > 0 ) {
                return "The recipient already has access to the safe";
            }
            passhub_log('user ' . $UserID . ' activity to share safe ' . $SafeID . ' with ' . $UserName);
            return ['status' => 'Ok', 'public_key' => $a[0]->publicKey_CSE];
        }
        if ($operation == 'email_final') { //share by email

/*
            $query = new MongoDB\Driver\Query(['email' => $UserName]);
            $cursor = $mng->executeQuery(DB_NAME . ".users", $query);
            $a = $cursor->toArray();
*/
            $pregUserName = preg_quote($UserName);
            $a = (
                $mng->users->find(
                    ['email' => new MongoDB\BSON\Regex('^' . $pregUserName . '$', 'i')]
                )
            )->toArray();
            if (count($a) > 1) {
                passhub_err("error acl 300");
                return "error acl 300";
            }
            if (count($a) == 0) {
                passhub_err("no user found " . $UserName);
                return "no user found " . $UserName;
            }
            $TargetUserID = (string)($a[0]->_id);
            $recipientSafeName = '[Shared]';
            if (isset($post['safeName'])) {
                $recipientSafeName = $post['safeName'];
            }
            if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                $role = ROLE_ADMINISTRATOR;
            } else {
                $role = ROLE_READONLY;
            }

            if (isset($post['role']) && in_array($post['role'], [ROLE_READONLY, ROLE_ADMINISTRATOR, ROLE_EDITOR])) {
                $role = $post['role'];
            }
            $result = $mng->safe_users->insertOne(
                ['UserID' => $TargetUserID,
                'SafeID' => $SafeID,
                'UserName' => null, 
                'SafeName' => $recipientSafeName,
                'role' => $role,
                'encrypted_key_CSE' => $RecipientKey]
            );
            if ($result->getInsertedCount() != 1) {
                return "Internal error acl 318";
            }
            passhub_log(
                'user ' . $UserID
                . ' shared safe ' . $SafeID 
                . ' with ' . $UserName . ' success'
            );
            return ['status' => 'Ok'];
        }
    
        $pending_confirmation_on_entry = pending_confirmation($mng, $UserID, $SafeID);

        $filter = ['UserName' => $UserName, 'SafeID' => $SafeID];
        $cursor = $mng->safe_users->find($filter);
        $a = $cursor->toArray();
        if (count($a) > 1) {
            passhub_err("error acl 420");
            return "error acl 420";
        }
        if (count($a) === 0) { // try mail
            $result = $mng->users->find(['email' => $UserName])->toArray();

            if (count($result) === 1) {
                $TargetUserID = (string)$result[0]->_id;
                $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
                $cursor = $mng->safe_users->find($filter);
                $a = $cursor->toArray();
                if (count($a) !== 1) {
                    passhub_err("error acl 343");
                    return "error acl 343";
                }
            }
        }
        $TargetUserID = $a[0]->UserID;
        if ($operation =="get_public_key") {

            $pubKey = get_public_key_CSE($mng, $TargetUserID);
            if (!$pubKey) {
                passhub_err("ACL 265: recipient pubkey_CSE absent");
                return "Internal error acl 265";
            }

            $my_encrypted_aes_key = get_encrypted_aes_key_CSE($mng, $UserID, $SafeID);
            return ['status' => "Ok", 'public_key' => $pubKey, 'my_encrypted_aes_key' => $my_encrypted_aes_key];
        } elseif ($operation == "role") {
            if ($UserID == $TargetUserID) {
                return "Internal error acl 400";
            }
            if ($role == 'administrator') {
                $role = ROLE_ADMINISTRATOR;
            } else if ($role == 'editor') {
                $role = ROLE_EDITOR;
            } else if ($role == 'readonly') {
                $role = ROLE_READONLY;
            } else {
                return "Internal error acl 315";
            }
            $result = $mng->safe_users->updateOne(['SafeID' => $SafeID, 'UserID' => $TargetUserID], ['$set' => ["role" =>$role]]);
            if ($result->getMatchedCount() != 1) {
                passhub_err(print_r($result, true));
                return "Internal error acl 412";
            }
            passhub_log(
                'user ' . $UserID 
                . ' set role ' . $role . ' to user ' . $TargetUserID 
                . ' safe ' . $SafeID
            );

        } elseif ($operation == "delete") {
            if ($UserID == $TargetUserID) {
                return "Internal error acl 423";
            }
            $mng->sharing_codes->deleteMany(['SafeID' => $SafeID, 'RecipientID' => $TargetUserID]);

            $result = $mng->safe_users->deleteMany(['SafeID' => $SafeID, 'UserID' => $TargetUserID]);
            if ($result->getDeletedCount() != 1) {
                passhub_err(print_r($result, true));
                return "Internal error acl 442";
            }
            passhub_log('user ' . $UserID . ' activity revoked access to safe ' . $SafeID . ' for user ' . $TargetUserID);
        } elseif ($operation == "confirm") {
            $hex_crypted = $RecipientKey;

            // START TRANSACTION
            if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                $role = ROLE_ADMINISTRATOR;
            } else {
                $role = ROLE_READONLY;
            }
            $result = $mng->safe_users->updateMany(
                ['SafeID' => $SafeID, 'UserID' => $TargetUserID],
                ['$set' => ['encrypted_key_CSE' => $hex_crypted, "role" =>$role]]
            );
            if ($result->getModifiedCount() != 1) {
                return "Internal error acl 251";
            }
            $result = $mng->sharing_codes->updateMany(
                ['SafeID' => $SafeID, 'RecipientID' => $TargetUserID],
                ['$set' => ['valid' => false]]
            );
            if ($result->getModifiedCount() != 1) {
                return "Internal error acl 258";
            }
            passhub_log('user ' . $UserID . ' activity confirmed access to safe ' . $SafeID . ' for user ' . $TargetUserID);
        } else {
            return "internal error 261";
        }
        if ($pending_confirmation_on_entry != pending_confirmation($mng, $UserID, $SafeID)) {
            $update_page_req = true;
        }
    } elseif ($operation != null) {
        passhub_err('safe_acl operation ' . $operation);
        return "internal error 482";
    }
    //    $filter = ['UserID' => ['$ne' => $UserID], 'SafeID' => $SafeID];
    $cursor = $mng->safe_users->find(['SafeID' => $SafeID]);

    $UserList = [];
    $emptyNames = [];
    foreach ($cursor as $row) {
        // TODO

        $safe = new Safe($row);
        $UserList[(string)($row->UserID)] = array("name" => htmlspecialchars($row->UserName), "status" => ($safe->isConfirmed()? 1:0), "role" => $row->role);
        if (!$row->UserName) {
            $id =  (strlen($row->UserID) != 24)? $row->UserID : new MongoDB\BSON\ObjectID($row->UserID);
            $emptyNames[] = $id;
        }
    }
    if (sizeof($emptyNames)) {
        $cursor = $mng->users->find(['_id' => ['$in' => $emptyNames]]);
        foreach ($cursor as $row) {
            if (isset($row->email)) { 
                $UserList[(string)($row->_id)]['name'] = $row->email;
            }
        } 
    }
    $UserList[$UserID]['myself'] = true;
    // cleanup UserIDs
    $UserListOut = [];    
    foreach ($UserList as $key => $value) {
        $UserListOut[] = $value;  
    }
    return ['status' => "Ok", 'UserList' => $UserListOut, 'update_page_req' => $update_page_req];
}

function getSharingCode($mng, $UserID, $SafeID, $UserName = null) {

    if (!ctype_xdigit($UserID) || !ctype_xdigit($SafeID)) {
        return "Bad arguments";
    }

    $role = get_user_role($mng, $UserID, $SafeID);
    if ($role != ROLE_ADMINISTRATOR) {
        passhub_err("get_sharing_code rights violation: UserID '$UserID' SafeID '$SafeID'");
        if ($role == ROLE_EDITOR) {
            $result = "You have 'editor'";
        } else if ($role == ROLE_READONLY) {
            $result = "You have 'readonly'";
        } else {
            return "You do not have rights to share this safe";
        }
        $result .= " rights in this safe. You need to be ";
        $result .= "an owner (administrator) to share the safe.";

        return $result;
    }

    // do we need a name?
    // may be used for is_admin
    $filter = ['UserID' => $UserID, 'SafeID' => $SafeID];
    $cursor = $mng->safe_users->find($filter);

    $row = $cursor->toArray()[0];

    if ($row->UserName == null) {
        if ($UserName != null) {
            $filter = ['UserName' => $UserName, 'SafeID' => $SafeID];
            $cursor = $mng->safe_users->find($filter);
            if (count($cursor->toArray())) {
                return "name '$UserName' already exists";   // theoretically impossible;
            }
            $result = $mng->safe_users->updateMany(
                ['UserID' => $UserID, 'SafeID' => $SafeID],
                ['$set' =>['UserName' =>$UserName]]
            );
            if ($result->getModifiedCount() != 1 ) {
                passhub_err("Error setting name UserID $UserID SafeID $safeID new name " .  $UserName);
                return "Error setting name";
            }
            $row->UserName = $UserName;
        } else {
            return "name required";    // theoretically impossible;
        }
    }

    $v1 = random_int(0, 9999);
    $v2 = random_int(0, 9999);
    $v3 = random_int(0, 9999);
    $v4 = random_int(0, 9999);

    $v = sprintf("%04d-%04d-%04d-%04d", $v1, $v2, $v3, $v4);
    $result = $mng->sharing_codes->insertOne(
        ['SafeID' => $SafeID, 
        'UserID' => $UserID, 
        'code' => $v, 
        'valid' => true, 
        'created' => Date('c'), 
        'RecipientID' => null]
    );
    if ($result->getInsertedCount() != 1 ) {
        passhub_err("Error safe 497");
        return "Internal Error 497";
    }
    passhub_log('user ' . $UserID . ' activity get sharing code');
    return [
        "status" => "Ok", 
        "code" => $v,
        "sharingCodeTTL" => SHARING_CODE_TTL,
        "ownerName" => $row->UserName
    ];
}

function add_by_invite($mng, $UserID, $inviteCode, $UserName, $SafeName )
{
    $cursor = $mng->sharing_codes->find(['code' => $inviteCode]);

    $a = $cursor->toArray();
    $cnt = count($a);
    if ($cnt == 0) {
        return "no such sharing code $inviteCode";
    } elseif ($cnt > 1) {
        passhub_err("multiple records for sharing code $inviteCode");
        return "internal error 498";
    }
    $row = $a[0];

    if (time() - strtotime($row->created) > SHARING_CODE_TTL) {
        return "sharing code $inviteCode already expired";
    }

    if (($row->valid != true) ||  ($row->RecipientID != null)) {
         return "the one-time sharing code '$inviteCode' already used";
    }
    $SafeID = $row->SafeID;

    $filter = ['SafeID' => $SafeID, 'UserName' => $UserName];
    $cursor = $mng->safe_users->find($filter);
    foreach ($cursor as $x) {
        return "User name $UserName already occupied in the shared safe";
    }

    $filter = ['UserID' => $UserID, 'SafeName' => $SafeName];
    $cursor = $mng->safe_users->find($filter);
    foreach ($cursor as $x) {
        return "Safe name $SafeName already used";
    }

    $filter = ['UserID' => $UserID, 'SafeID' => $SafeID];
    $cursor = $mng->safe_users->find($filter);
    foreach ($cursor as $x) {
        return "You already have access to this safe";
    }

    // START TRANSACTION!
    $result = $mng->sharing_codes->updateMany(
        ['code' => $inviteCode,  'RecipientID' => null], 
        ['$set' => ['RecipientID' => $UserID]]
    );
    if ($result->getModifiedCount() != 1 ) {
        passhub_err("Error safe 536");
        return "Internal Error 536";
    }
    if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
        $role = ROLE_ADMINISTRATOR;
    } else {
        $role = ROLE_READONLY;
    }
    $result = $mng->safe_users->insertOne(['UserID' => $UserID, 'SafeID' => $SafeID, 'UserName' => $UserName, 'SafeName' => $SafeName, 'role' => $role, 'encrypted_key' => null, 'encrypted_key_CSE' => null]);
    if ($result->getInsertedCount() != 1 ) {
        passhub_err("Error safe 545");
        return "Internal Error 545";
    }
    // COMMIT
    passhub_log('user ' . $UserID . ' activity invitation accepted');
    return array("status" => "Ok", "vault" => $SafeID);
}

function delete_safe($mng, $UserID, $SafeID, $operation) {

    if (!ctype_xdigit((string)$UserID) || !ctype_xdigit((string)$UserID)) {
        passhub_err("delete safe UserID " . $UserID . " SafeID " . $SafeID);
        return "internal error";
    }

    // check if it is not the last vault

    $mng_res = $mng->safe_users->find(['UserID' => $UserID ]);
    $mng_rows = $mng_res->toArray();
    if (count($mng_rows) < 2) {
        return "Cannot delete the last safe";
    }

    if (!is_admin($mng, $UserID, $SafeID)) {
        return "unsubscribe";
    }

    // check if the vault is not shared
    $filter = ['UserID' => ['$ne' => $UserID], 'SafeID' => $SafeID];
    $mng_cursor = $mng->safe_users->find($filter);

    foreach ( $mng_cursor as $row) {
        return "The safe is shared. Please revoke other user's access to the safe first";
    }
    if ($operation == 'delete') {
        foreach (["safe_items", "safe_items_v2", "safe_folders"] as $collection_name) {

            $collection = $mng->selectCollection($collection_name);
            $mng_cursor = $collection->find(['SafeID' => $SafeID]);

            foreach ( $mng_cursor as $row) {
                return "not empty";
            }
        }
    }
    if (($operation == 'delete') || ($operation == 'delete_not_empty')) {
        $mng->sharing_codes->deleteMany(['SafeID' => $SafeID]);
        $deleted  = ['items' => 0, 'folders' => 0];

        $result = $mng->safe_folders->deleteMany(['SafeID' => $SafeID]);
        $deleted['folders'] += $result->getDeletedCount();

        $result = $mng->safe_items->deleteMany(['SafeID' => $SafeID]);
        $deleted['items'] += $result->getDeletedCount();

        $result = $mng->safe_users->deleteMany(['SafeID' => $SafeID]);
        if ($result->getDeletedCount() != 1) {
            passhub_err(print_r($result, true));
            return "Internal error del 472";
        }
        passhub_log(
            'user ' . $UserID .' safe deleted with '
            . $deleted['items'] . ' items and '
            . $deleted['folders'] . ' folders'
        );
        return ['status' => 'Ok', 'items' => $deleted['items'], 'folders' => $deleted['folders']];
    }

    return "Internal error";
}

//**********************************************************************


function changeSafeName($mng, $UserID, $SafeID, $newName) {

    $filter = [ 'UserID' => $UserID, 'SafeName' => $newName ];
    $mng_res = $mng->safe_users->find($filter);

    $res_array = $mng_res->ToArray();

    if (count($res_array)) {  // if user already has safe with this name
        $row = $res_array[0];
        if ($row->SafeID != $SafeID) {
            return "Name <b>'$newName'</b> already used";
        }
        return "Ok";
    }

    $result = $mng->safe_users->updateMany(
        ['UserID' => $UserID, 'SafeID' => $SafeID], 
        ['$set' =>['SafeName' =>$newName]]
    );
    if ($result->getModifiedCount() == 1) {
        passhub_log('user ' . $UserID . ' activity safe renamed');
        return "Ok";
    }
    passhub_err("UserID $UserID SafeID $SafeID newName $newName Modified count: " . $result->getModifiedCount());
    return "Internal error 323";
}

//----------------------------------------------------------


