<?php

/**
 * User.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class User
{
    public const ROLE_READONLY = 'readonly';   
    public const ROLE_EDITOR = 'editor';   
    public const ROLE_ADMINISTRATOR = 'administrator';   

    public $profile;

    function __construct($mng, $UserID) {
        $this->mng = $mng;
        $this->UserID = $UserID;
        $this->_id = (strlen($this->UserID) != 24) ? $this->UserID : 
            new \MongoDB\BSON\ObjectID($this->UserID);
    }

    public function setCurrentSafe($SafeID) {
        if (ctype_xdigit($SafeID)) {
            $result = $this->mng->users->updateMany(
                ['_id' => $this->_id], 
                ['$set' =>['currentSafe' => $SafeID]]
            );
        }
    }

    public function setEmailAddress($email) {
        $result = $this->mng->users->updateOne(
            ['_id' => $this->_id], 
            ['$set' => ['email' => $email]]
        );
    }                            

    public function getProfile() {
        $mng_res = $this->mng->users->find(['_id' => $this->_id]);
        $res_array = $mng_res->ToArray();

        if (count($res_array) != 1) {
            throw new \Exception("error user 253 count " . count($res_array));
        }
        $profile = $res_array[0];

        $profile->email = isset($profile->email) ? $profile->email : "";
        $this->profile = $profile;
    }

    public function getPublicKey() {
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        return $this->profile->publicKey_CSE;
    }

    public function isSiteAdmin() {
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        if (isset($this->profile->site_admin) 
            && ($this->profile->site_admin == true)
        ) {
            return true;
        }
        return false;
    }

    public function toggleSiteAdmin() {
        if ($this->isSiteAdmin()) {
            $this->mng->users->updateOne(['_id' => $this->_id], ['$set' =>['site_admin' => false]]);
        } else {
            $this->mng->users->updateOne(['_id' => $this->_id], ['$set' =>['site_admin' => true]]);
        }
        return ['status' => "Ok"];
    }

    public function isCSE() {
        if (isset($this->profile)) {
            if (!isset($this->profile->publicKey_CSE) || !isset($this->profile->privateKey_CSE)) {
                return false;
            }
        }
        return true;
    }

    public function getSafes() {

        $mng_res = $this->mng->safe_users->find([ 'UserID' => $this->UserID]);

        $this->safe_array = array();
        foreach ($mng_res as $row) {
            $id = $row->SafeID;
            $this->safe_array[$id] = new Safe($row);
        
            $safe_users = $this->mng->safe_users->find([ 'SafeID' => $row->SafeID])->toArray(); 
            $this->safe_array[$id]->user_count = count($safe_users);
        } 

        $response = array();
        $storage_used = 0;
        $total_records = 0;
        foreach ($this->safe_array as $safe) {
            if ($safe->isConfirmed()) {
                $items = Item::get_item_list_cse($this->mng, $this->UserID, $safe->id);
                foreach ($items as $record) {
                    if (property_exists($record, 'file')) {
                        $storage_used += $record->file->size;
                    }
                } 
                $total_records += count($items);
                $folders = Folder::get_folder_list_cse($this->mng, $this->UserID, $safe->id);
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
                "folders" => $folders,
                "users" => $safe->user_count
            ];
            // $response[$safe->id] = $safe_entry;
            array_push($response, $safe_entry);
        }
        $_SESSION['STORAGE_USED'] = $storage_used;
        $_SESSION['TOTAL_RECORDS'] = $total_records;
        return $response;
    }

    public function getData() {

        $this->getProfile();

        $data = [
            'publicKeyPem' => $this->profile->publicKey_CSE,
            // 'invitation_accept_pending' => $this->invitation_accept_pending,
            'invitation_accept_pending' => false,
            'currentSafe' => $this->profile->currentSafe,
            'user_mail' => $this->profile->email,
            'ePrivateKey' => $this->profile->privateKey_CSE,
            'safes' => $this->getSafes(),
            'ticket' => $_SESSION['wwpass_ticket']
        ];

        if (array_key_exists('folder', $_GET)) {
            $data['active_folder'] = $_GET['folder'];
        } else {
            $data['active_folder'] = 0;
        }
        if (defined('MAIL_DOMAIN') || defined('LDAP')) {
            $data['shareModal'] = "#shareByMailModal";
        } else {
            $data['shareModal'] = "#safeShareModal";
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

    public function getEncryptedAesKey($SafeID)
    {
        $SafeID = (string)$SafeID;

        $cursor = $this->mng->safe_users->find([ 'UserID' => $this->UserID, 'SafeID' => $SafeID]);

        $rows = $cursor->toArray();
    
        if (count($rows) == 0) {
            Utils::err("get aes_key count 0");
            return null;
        }
    
        $row = $rows[0];
    
        $hex_crypted = $row->encrypted_key_CSE;
        return $hex_crypted;
    }

    public function getUserRole($SafeID)
    {
        $SafeID = (string)$SafeID;
    
        $cursor = $this->mng->safe_users->find(['SafeID' => $SafeID, 'UserID' => $this->UserID]);
        $a = $cursor->toArray();
        if (count($a) != 1) {
            Utils::err("get_role error 134 count " . count($a) . " UserID " . $this->UserID . " SafeID " . $SafeID);
            return false;
        }
        $row = $a[0];
        $safe = new Safe($row);
    
        if ($safe->isConfirmed() == false) {
            return false;
        }
        return $row->role;
    }
    
    public function canWrite($SafeID)
    {
        $role = $this->getUserRole($SafeID);
        return (($role == self::ROLE_ADMINISTRATOR) 
                || ($role == self::ROLE_EDITOR));
    }
    
    public function canRead($SafeID)
    {
        return ($this->getUserRole($SafeID) != false);
    }
    
    public function isAdmin($SafeID)
    {
        return ($this->getUserRole($SafeID) == self::ROLE_ADMINISTRATOR);
    }

    public function createSafe($safe) {
        if ($safe['name'] == "") {
            return "Please fill in new safe name";
        }
        $SafeID = (string)new \MongoDB\BSON\ObjectId();
    
        $this->mng->safe_users->insertOne(
            ['SafeID' => $SafeID, 'UserID' => $this->UserID, 'SafeName' =>$safe['name'],
            'UserName' => null,
            'role' => self::ROLE_ADMINISTRATOR,
            'encrypted_key_CSE' => $safe['aes_key']]
        );
        Utils::log('user ' . $this->UserID . ' activity safe created');
        $this->setCurrentSafe($SafeID);
        return array("status" =>"Ok", "id" => $SafeID);
    }
    
    function changeSafeName($SafeID, $newName) {

        $filter = [ 'UserID' => $this->UserID, 'SafeName' => $newName ];
        $mng_res = $this->mng->safe_users->find($filter);
    
        $res_array = $mng_res->ToArray();
    
        if (count($res_array)) {  // if user already has safe with this name
            $row = $res_array[0];
            if ($row->SafeID != $SafeID) {
                return "Name <b>'$newName'</b> already used";
            }
            return "Ok";
        }
    
        $result = $this->mng->safe_users->updateMany(
            ['UserID' => $this->UserID, 'SafeID' => $SafeID], 
            ['$set' =>['SafeName' =>$newName]]
        );
        if ($result->getModifiedCount() == 1) {
            Utils::log('user ' . $this->UserID . ' activity safe renamed');
            return "Ok";
        }
        Utils::err("UserID $this->UserID SafeID $SafeID newName $newName Modified count: " . $result->getModifiedCount());
        return "Internal error 323";
    }
   
    public function importSafes($post) {

        if (!isset($post['import'])) {
            Utils::err("import_safes imported trees not defined");
            return "internal error";
        }
        
        foreach ($post['import'] as $safe) {
            if (isset($safe['id'])) {  //merge
                $SafeID = $safe['id'];
                if (!$this->canWrite($SafeID)) {
                    return "access vioaltion or safe does not exist";
                }

                if (isset($safe['entries']) && (count($safe['entries']) >0)) {
                    Item::create_items_cse($this->mng, $this->UserID, $SafeID, $safe['entries'], 0);
                }
                if (isset($safe['folders'])) {
                    foreach ($safe['folders'] as $folder) {
                        if (isset($folder['_id'])) {
                            Folder::merge($this->mng, $this->UserID, $SafeID, 0, $folder);
                            // look inside
                        } else {
                            $r = Folder::import($this->mng, $this->UserID, $SafeID, 0, $folder);
                            if ($r['status'] != 'Ok') {
                                return $r;
                            }
                        }
                    }
                }
                continue;
            } else if (!isset($safe['key']) || !ctype_xdigit((string)$safe['key'])) {
                Utils::err("import_safes key illegal or undefined");
                return "internal error";
            }
            //TODO truncate name length if required
            // patch naming
            $safe['aes_key'] = $safe['key'];
            $result = $this->createSafe($safe);
            if (is_string($result)) {
                return $result;
            }
            $SafeID = $result['id'];
            if (isset($safe['entries']) && (count($safe['entries']) > 0)) {
                Item::create_items_cse($this->mng, $this->UserID, $SafeID, $safe['entries'], 0);
            }
            if (isset($safe['folders'])) {
                foreach ($safe['folders'] as $folder) {
                    $r = Folder::import($this->mng, $this->UserID, $SafeID, 0, $folder);
                    if ($r['status'] != 'Ok') {
                        return $r;
                    }
                }
            }
        }
        return ["status" => "Ok"];
    }

    public function deleteSafe($SafeID, $operation) {
    
        // check if it is not the last vault
    
        $mng_res = $this->mng->safe_users->find(['UserID' => $this->UserID ]);
        $mng_rows = $mng_res->toArray();
        if (count($mng_rows) < 2) {
            return "Cannot delete the last safe";
        }
    
        if (!$this->isAdmin($SafeID)) {
            return "unsubscribe";
        }
    
        // check if the vault is not shared
        $filter = ['UserID' => ['$ne' => $this->UserID], 'SafeID' => $SafeID];
        $mng_cursor = $this->mng->safe_users->find($filter);
    
        foreach ( $mng_cursor as $row) {
            return "The safe is shared. Please revoke other user's access to the safe first";
        }
        if ($operation == 'delete') {
            foreach (["safe_items", "safe_items_v2", "safe_folders"] as $collection_name) {
    
                $collection = $this->mng->selectCollection($collection_name);
                $mng_cursor = $collection->find(['SafeID' => $SafeID]);
    
                foreach ( $mng_cursor as $row) {
                    return "not empty";
                }
            }
        }
        if (($operation == 'delete') || ($operation == 'delete_not_empty')) {
            $this->mng->sharing_codes->deleteMany(['SafeID' => $SafeID]);
            $deleted  = ['items' => 0, 'folders' => 0];
    
            $result = $this->mng->safe_folders->deleteMany(['SafeID' => $SafeID]);
            $deleted['folders'] += $result->getDeletedCount();
    
            $result = $this->mng->safe_items->deleteMany(['SafeID' => $SafeID]);
            $deleted['items'] += $result->getDeletedCount();
    
            $result = $this->mng->safe_users->deleteMany(['SafeID' => $SafeID]);
            if ($result->getDeletedCount() != 1) {
                Utils::err(print_r($result, true));
                return "Internal error del 472";
            }
            Utils::log(
                'user ' . $this->UserID .' safe deleted with '
                . $deleted['items'] . ' items and '
                . $deleted['folders'] . ' folders'
            );
            return ['status' => 'Ok', 'items' => $deleted['items'], 'folders' => $deleted['folders']];
        }
    
        return "Internal error";
    }
    
    public function account() {
        $total_records = 0;
        $total_storage = 0;
        $total_safes = 0;
        $result = [];
    
        $cursor = $this->mng->users->find(['_id' => $this->_id]);
    
        foreach ($cursor as $row) {
            if (property_exists($row, 'email')) {
                $result['email'] = $row->email;
            }
            if (property_exists($row, 'plan')) {
                $result['plan'] = $row->plan;
                if ($row->plan == 'Premium') {
                    $result['expires'] = $row->expires;
                }
                if ($row->plan == 'FREE') {
                    $result['upgrade_button'] = true;
                }
            } else if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                $result['plan'] = 'Premium';
                if (property_exists($row, 'expires')) {
                    $result['expires'] = $row->expires->__toString();
                } else {
                    $result['expires'] = 'never';
                }
            }
            break;
        }
    
        $safes = $this->mng->safe_users->find([ 'UserID' => $this->UserID]);
        foreach ($safes as $safe) {
            $total_safes += 1;
            $records = $this->mng->safe_items->find([ 'SafeID' => $safe->SafeID]);
            foreach ($records as $record) {
                $total_records += 1;
                if (property_exists($record, 'file')) {
                    $total_storage += $record->file->size;
                }
            }
        }
        $result['records'] = $total_records;
        $result['used'] = $total_storage;
        $result['safes'] = $total_safes;
    
        if (defined('MAX_RECORDS_PER_USER')  
            && (!isset($result['plan']) || ($result['plan'] != 'Premium'))) {
            $result['maxRecords'] = MAX_RECORDS_PER_USER;
        } 
    
        if (isset($result['plan'])  && ($result['plan'] == "FREE") && defined('FREE_ACCOUNT_MAX_RECORDS')) {
            Utils::err("account is free, setting max records to " . FREE_ACCOUNT_MAX_RECORDS);       
            $result['maxRecords'] = FREE_ACCOUNT_MAX_RECORDS;
        }
    
        if (defined('MAX_STORAGE_PER_USER')) {
            $result['maxStorage'] = MAX_STORAGE_PER_USER;
        } 
        if (isset($result['plan'])  && ($result['plan'] == "FREE") && defined('FREE_ACCOUNT_MAX_STORAGE')) {
            Utils::err("account is free, setting max storage to " . FREE_ACCOUNT_MAX_STORAGE);       
            $result['maxStorage'] = FREE_ACCOUNT_MAX_STORAGE;
        }
    
        $result['status'] = 'Ok';
        return $result;
    }

    public function save_paypal_transaction($transaction, $expires) {

        $record = [];
        $record['UserID'] = $this->UserID;
        $record['gateway'] = 'Paypal'; 
        $record['transaction'] =  $transaction; 
        $this->mng->payments->insertOne($record);
    
        $result = $this->mng->users->updateOne(
            ['_id' => $this->_id], 
            ['$set' => ['plan' => 'Premium', 'expires' => $expires]]
        );
    }
    
    public function deleteAccount() {

        $this->getProfile();

        if (!isset($this->email)) {
            $this->email = "";
        }
        $result = $this->mng->safe_users->deleteMany(['UserID' => $this->UserID]);
        $removed_safe_user_records = $result->getDeletedCount();
    
        if (!isset($this->PUID)) {
    
            $cursor = $this->mng->puids->find(["UserID" => $this->UserID]);
            $puids = $cursor->toArray();
            if (count($puids) > 0) {
                $this->PUID = $puids[0]->PUID;
                $result = $this->mng->puids->deleteMany(['UserID' => $this->UserID]);
                if ($result->getDeletedCount() != 1) {
                    Utils::err(print_r($result, true));
                    return "Internal error 107";
                }
            } else {
                Utils::err("del user 111");
                return "Internal error 111";
            }
        }
        $result = $this->mng->reg_codes->deleteMany(['PUID' => $this->PUID]);
        $result = $this->mng->change_mail_codes->deleteMany(['PUID' => $this->PUID]);
        $result = $this->mng->users->deleteMany(['_id' => $this->_id]);
        Utils::err("removed " . $removed_safe_user_records . " records in safe_users");
        Utils::log("user " . $this->UserID . " account deleted, mail " . $this->email);
        return ['status' => "Ok", "access" => $removed_safe_user_records];
    }
    
    public function pendingConfirmation($SafeID) {

        $filter = [
            'UserID' => $this->UserID,
            'SafeID' => $SafeID,
            'valid' => true,
            'RecipientID' => ['$ne' => null]
        ];
        $cursor = $this->mng->sharing_codes->find($filter);
        foreach ($cursor as $row) {
            return true;
        }
        return false;
    }
    
    public function safeAcl($post) {

        $SafeID = $post['vault'];
        $operation = isset($post['operation']) ? $post['operation']: null;
        
        if (!ctype_xdigit($SafeID)) {
            return "Bad arguments";
        }
    
        $myrole = $this->getUserRole($SafeID);
        if (!$myrole) {
            return "error 275";
        }
    
        if ($operation == "unsubscribe") {
            if ($myrole == self::ROLE_ADMINISTRATOR) {
                return "Administrators cannot leave the safe users group";
            }
            $this->mng->sharing_codes->deleteMany(['SafeID' => $SafeID, 'RecipientID' => $this->UserID]);
    
            $result = $this->mng->safe_users->deleteMany(['SafeID' => $SafeID, 'UserID' => $this->UserID]);
            if ($result->getDeletedCount() == 1) {
                return "Ok";
            }
            Utils::err(print_r($result, true));
            return "Internal error acl 442";
        }
   
        $UserName = isset($post['name']) ? $post['name']: null;
        $RecipientKey = isset($post['key']) ? $post['key']: null;
        $role = isset($post['role']) ? $post['role']: null;
        
        $update_page_req = false;
        if ($UserName != null) {
            if ($myrole != self::ROLE_ADMINISTRATOR) {
                return "You do not have administrative rights";
            }
    
            if ($operation == 'email') { //share by email
    
                $pregUserName = preg_quote($UserName);
                $a = (
                    $this->mng->users->find(
                        ['email' => new \MongoDB\BSON\Regex('^' . $pregUserName . '$', 'i')]
                    )
                )->toArray();
    
                if (count($a) > 1) {
                    Utils::err("error acl 300");
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
                            . "to download and initialize the WWPass Key mobile app "
                            . "from the Android or iOS store. Once your WWPass Key is ready, "
                            . "please visit " . $_POST['origin'] . " and use the WWPass Key app to login"
                            . " to your PassHub account."
                        );  
                        
                    Utils::err("share by mail: User with " . htmlspecialchars($UserName) . " not registered");
                    return "User " . $email . " is not registered";
                    // ." <a href='mailto:$email_link' class='alert-link'>Send invitation</a>";
                }
                $TargetUserID = (string)($a[0]->_id);
                if ($TargetUserID == $this->UserID) {
                    return "You cannot share the safe with yourself (" . $UserName . ")";
                }
                $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
                $cursor = $this->mng->safe_users->find($filter);
                if (count($cursor->toArray()) > 0 ) {
                    return "The recipient already has access to the safe";
                }
                Utils::log('user ' . $this->UserID . ' activity to share safe ' . $SafeID . ' with ' . $UserName);
                return ['status' => 'Ok', 'public_key' => $a[0]->publicKey_CSE];
            }
            if ($operation == 'email_final') { //share by email
                $pregUserName = preg_quote($UserName);
                $a = (
                    $this->mng->users->find(
                        ['email' => new \MongoDB\BSON\Regex('^' . $pregUserName . '$', 'i')]
                    )
                )->toArray();
                if (count($a) > 1) {
                    Utils::err("error acl 300");
                    return "error acl 300";
                }
                if (count($a) == 0) {
                    Utils::err("no user found " . $UserName);
                    return "no user found " . $UserName;
                }
                $TargetUserID = (string)($a[0]->_id);
                $recipientSafeName = '[Shared]';
                if (isset($post['safeName'])) {
                    $recipientSafeName = $post['safeName'];
                }
                if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                    $role = self::ROLE_ADMINISTRATOR;
                } else {
                    $role = self::ROLE_READONLY;
                }
    
                if (isset($post['role']) && in_array(
                    $post['role'],
                    [self::ROLE_READONLY, 
                    self::ROLE_ADMINISTRATOR, 
                    self::ROLE_EDITOR]
                )
                ) {
                    $role = $post['role'];
                }
                $result = $this->mng->safe_users->insertOne(
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
                Utils::log(
                    'user ' . $this->UserID
                    . ' shared safe ' . $SafeID 
                    . ' with ' . $UserName . ' success'
                );
                return ['status' => 'Ok'];
            }
        
            $pending_confirmation_on_entry = $this->pendingConfirmation($SafeID);
    
            $filter = ['UserName' => $UserName, 'SafeID' => $SafeID];
            $cursor = $this->mng->safe_users->find($filter);
            $a = $cursor->toArray();
            if (count($a) > 1) {
                Utils::err("error acl 420");
                return "error acl 420";
            }
            if (count($a) === 0) { // try mail
                $result = $this->mng->users->find(['email' => $UserName])->toArray();
    
                if (count($result) === 1) {
                    $TargetUserID = (string)$result[0]->_id;
                    $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
                    $cursor = $this->mng->safe_users->find($filter);
                    $a = $cursor->toArray();
                    if (count($a) !== 1) {
                        Utils::err("error acl 343");
                        return "error acl 343";
                    }
                }
            }
            $TargetUserID = $a[0]->UserID;
            if ($operation =="get_public_key") {
                $targetUser = new User($this->mng, $TargetUserID);
                $pubKey = $targetUser->getPublicKey();
                if (!$pubKey) {
                    Utils::err("ACL 265: recipient pubkey_CSE absent");
                    return "Internal error acl 265";
                }
    
                $my_encrypted_aes_key = $this->getEncryptedAesKey($SafeID);
                return ['status' => "Ok", 'public_key' => $pubKey, 'my_encrypted_aes_key' => $my_encrypted_aes_key];
            } elseif ($operation == "role") {
                if ($this->UserID == $TargetUserID) {
                    return "Internal error acl 400";
                }
                if ($role == 'administrator') {
                    $role = self::ROLE_ADMINISTRATOR;
                } else if ($role == 'editor') {
                    $role = self::ROLE_EDITOR;
                } else if ($role == 'readonly') {
                    $role = self::ROLE_READONLY;
                } else {
                    return "Internal error acl 315";
                }
                $result = $this->mng->safe_users->updateOne(
                    ['SafeID' => $SafeID, 'UserID' => $TargetUserID], 
                    ['$set' => ["role" =>$role]]
                );
                if ($result->getMatchedCount() != 1) {
                    Utils::err(print_r($result, true));
                    return "Internal error acl 412";
                }
                Utils::log(
                    'user ' . $this->UserID 
                    . ' set role ' . $role . ' to user ' . $TargetUserID 
                    . ' safe ' . $SafeID
                );
    
            } elseif ($operation == "delete") {
                if ($this->UserID == $TargetUserID) {
                    return "Internal error acl 423";
                }
                $this->mng->sharing_codes->deleteMany(['SafeID' => $SafeID, 'RecipientID' => $TargetUserID]);
    
                $result = $this->mng->safe_users->deleteMany(['SafeID' => $SafeID, 'UserID' => $TargetUserID]);
                if ($result->getDeletedCount() != 1) {
                    Utils::err(print_r($result, true));
                    return "Internal error acl 442";
                }
                Utils::log('user ' . $this->UserID . ' activity revoked access to safe ' . $SafeID . ' for user ' . $TargetUserID);
            } elseif ($operation == "confirm") {
                $hex_crypted = $RecipientKey;
    
                // START TRANSACTION
                if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                    $role = self::ROLE_ADMINISTRATOR;
                } else {
                    $role = self::ROLE_READONLY;
                }
                $result = $this->mng->safe_users->updateMany(
                    ['SafeID' => $SafeID, 'UserID' => $TargetUserID],
                    ['$set' => ['encrypted_key_CSE' => $hex_crypted, "role" =>$role]]
                );
                if ($result->getModifiedCount() != 1) {
                    return "Internal error acl 251";
                }
                $result = $this->mng->sharing_codes->updateMany(
                    ['SafeID' => $SafeID, 'RecipientID' => $TargetUserID],
                    ['$set' => ['valid' => false]]
                );
                if ($result->getModifiedCount() != 1) {
                    return "Internal error acl 258";
                }
                Utils::log('user ' . $this->UserID . ' activity confirmed access to safe ' . $SafeID . ' for user ' . $TargetUserID);
            } else {
                return "internal error 261";
            }
            if ($pending_confirmation_on_entry != $this->pendingConfirmation($SafeID)) {
                $update_page_req = true;
            }
        } elseif ($operation != null) {
            Utils::err('safe_acl operation ' . $operation);
            return "internal error 482";
        }
        //    $filter = ['UserID' => ['$ne' => $UserID], 'SafeID' => $SafeID];
        $cursor = $this->mng->safe_users->find(['SafeID' => $SafeID]);
    
        $UserList = [];
        $emptyNames = [];
        foreach ($cursor as $row) {
            // TODO
    
            $safe = new Safe($row);
            $UserList[(string)($row->UserID)] = array("name" => htmlspecialchars($row->UserName), "status" => ($safe->isConfirmed()? 1:0), "role" => $row->role);
            if (!$row->UserName) {
                $id =  (strlen($row->UserID) != 24)? $row->UserID : new \MongoDB\BSON\ObjectID($row->UserID);
                $emptyNames[] = $id;
            }
        }
        if (sizeof($emptyNames)) {
            $cursor = $this->mng->users->find(['_id' => ['$in' => $emptyNames]]);
            foreach ($cursor as $row) {
                if (isset($row->email)) { 
                    $UserList[(string)($row->_id)]['name'] = $row->email;
                }
            } 
        }
        $UserList[$this->UserID]['myself'] = true;
        // cleanup UserIDs
        $UserListOut = [];    
        foreach ($UserList as $key => $value) {
            $UserListOut[] = $value;  
        }
        return ['status' => "Ok", 'UserList' => $UserListOut, 'update_page_req' => $update_page_req];
    }

    public function ldapBindExistingUser($email, $userprincipalname) {
        $cursor = $this->mng->users->find(['_id' => $this->_id]);
    
        foreach ($cursor as $row) {
            if (property_exists($row, 'email')) {
                $email = $row->email;
            }
            $result = $this->mng->users->updateOne(
                ['_id' => $this->_id], 
                ['$set' => ['email' => $email, 'userprincipalname' => $userprincipalname]]
            );
        }
    }

    public function checkLdapAccess() {
        Utils::err('checkLdapAccess');
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        if (isset($this->profile->userprincipalname)) {
            $ds=ldap_connect(LDAP['url']);
            Utils::err('Url ' . LDAP['url']);
            Utils::err(print_r($ds,true));

            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ds, LDAP_OPT_NETWORK_TIMEOUT, 10);    
            
            $r=ldap_bind($ds, LDAP['bind_dn'], LDAP['bind_pwd']);
            Utils::err('Bind to ' . LDAP['bind_dn'] . ' ' . LDAP['bind_pwd']);
            Utils::err('bind result ' . print_r($r, true));

            if (!$r) {
                $result =  "Bind error " . ldap_error($ds) . " " . ldap_errno($ds) . " ". $i . "<br>";
                Utils::err($result);
                $e = ldap_errno($ds); 
                ldap_close($ds);
                return false;
            }
            $user_filter = "(userprincipalname={$this->profile->userprincipalname})";
            $group_filter = "(memberof=".LDAP['group'].")";
          
            $ldap_filter = "(&{$user_filter}{$group_filter})";
            $sr=ldap_search($ds, LDAP['base_dn'],  $ldap_filter);
            Utils::err('LDAP search with filter ' . $ldap_filter);
            Utils::err('Base dn ' . LDAP['base_dn']);

            if ($sr == false) {
                Utils::err("ldap_search fail, ldap_errno " . ldap_errno($ds) . " base_dn * " . LDAP['base_dn'] . " * ldap_filter " . $ldap_filter);
            }
            $info = ldap_get_entries($ds, $sr);
            Utils::err('User enabled: ' . $info['count']);
            $user_enabled = $info['count'];
            if ($user_enabled) {
                return true;
            }
            Utils::err('Ldap: access denied');

            return false;
        }
        Utils::err('LDAP: no userprincipalname n user profile');
        return "not bound";
    }
}
