<?php

/**
 * User.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2023 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

function getPremiumDetails($mng, $UserID) {

    $result = [];

    try {
        $subscriptions = $mng->subscriptions->find([ 'UserID' => $UserID]);


        $current_period_end = 0;
        $active_subscription = true;

        // we want only one subscription for user

        $found = false;

        foreach($subscriptions as $subscription) {
            if($subscription->current_period_end > time()) {
                $found = true;
                $current_period_end = $subscription->current_period_end;

                if($subscription->status == "active") {
                    $result['autorenew'] = true;
                }
    //            Utils::err("susbscription:");
    //            Utils::err(print_r($subscription, true));

                if(property_exists($subscription, 'charge')) {
    //                Utils::err("retrieving charge " . $subscription->charge);

                    $stripe = new \Stripe\StripeClient(STRIPE['key']);
                    $charge = $stripe->charges->retrieve($subscription->charge, []);
                    Utils::err("charge:");
                    Utils::err($charge);
                    $result['receipt_url'] = $charge->receipt_url;
                } else if(property_exists($subscription, 'latest_invoice')) {
                    Utils::log("scenario 2", "payment");
                    $stripe = new \Stripe\StripeClient(STRIPE['key']);
                    $invoice = $stripe->invoices->retrieve($subscription->latest_invoice, []);
                    if($invoice->charge) {
                        $charge = $stripe->charges->retrieve($invoice->charge, []);
                        $result['receipt_url'] = $charge->receipt_url;

                        $mng->subscriptions->updateOne(["subscription" => $subscription->subscription], ['$set'=>[
                            "charge" => $invoice->charge,
                        ]]);

                    }
                } else {
                    Utils::log("scenario 3", "payment");
                    $stripe = new \Stripe\StripeClient(STRIPE['key']);
                    $s = $stripe->subscriptions->retrieve($subscription->subscription); // subscription ID actually
                    $invoice = $stripe->invoices->retrieve($s->latest_invoice, []);
                    $charge = $stripe->charges->retrieve($invoice->charge, []);
                    if($charge) {
                        $result['receipt_url'] = $charge->receipt_url;
                    }

                    $mng->subscriptions->updateOne(["subscription" => $subscription->subscription], ['$set'=>[
                        "latest_invoice" => $s->latest_invoice,
                        "charge" => $invoice->charge,
                    ]]);
                }
                $result['expires'] = $current_period_end;  
            }
        }
        if($found) {
            return $result;
        }

        $user = new User($mng, $UserID);
        $profile = $user->getProfile();
        if(isset($profile['payment_id'])) {
            $cursor = $mng->payments->find(["csID" => $profile['payment_id']]);
            $payments = $cursor->ToArray();
            if(count($payments) == 1) {
                if(isset($payments[0]['subscription'])) {
                    Utils::err($payments[0]['subscription']);
                    $stripe = new \Stripe\StripeClient(STRIPE['key']);
                    $object = $stripe->subscriptions->retrieve($payments[0]['subscription']);
                    Utils::err('got subscription');
                    $r = $mng->subscriptions->insertOne(
                            [
                            'UserID' => $payments[0]->UserID,
                            'subscription' => $object->id,
                            'customer' => $object->customer,
                            'current_period_end' => $object->current_period_end,
                            'status' => $object->status,
                            'latest_invoice' => $object->latest_invoice, 
                            ]
                        );
                    if($object['current_period_end']  > time()) {
                        return getPremiumDetails($mng, $UserID);
                    }
                }
            } 
        }
        return [];
    } catch (\Exception $e) {
        Utils::err("getPremiumDetails exception");
        Utils::err($e->getMessage());
        return [];
    }
}

class User
{
    public const ROLE_LIMITED_READONLY = 'limited view';   
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

    function updateLastSeen() {
        $this->mng->users->updateMany(['_id' => $this->_id], ['$set' =>['lastSeen' =>Date('c')]]);
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
        if (isset($this->profile)) {
            return;
        }

        $mng_res = $this->mng->users->find(['_id' => $this->_id]);
        $res_array = $mng_res->ToArray();

        if (count($res_array) != 1) {
            throw new \Exception("error user 253 count " . count($res_array));
        }
        $profile = $res_array[0];

        $profile->email = isset($profile->email) ? $profile->email : "";

        if(!isset($profile->desktop_inactivity)) {
            if(defined('IDLE_TIMEOUT')) {
                $profile->desktop_inactivity = IDLE_TIMEOUT;
            } else {
                $profile->desktop_inactivity = 4 * 60 * 60;
            }
        }
        $this->profile = $profile;
        return $profile;
    }

    public function getPublicKey() {
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        return $this->profile->publicKey_CSE;
    }

    public function disabled() {
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        return isset($this->profile->disabled) && ($this->profile->disabled == true);
    }

    public function isSiteAdmin($create_if_first = false) {

        //LDAP:
        if( (defined('LDAP') || defined('AZURE')) && isset($_SESSION['admin']) && ($_SESSION['admin'] == true)) {
            return true;
        }

        if (!isset($this->profile)) {
            $this->getProfile();
        }

        if (isset($this->profile->site_admin) 
            && ($this->profile->site_admin == true)
        ) {
            return true;
        }

        // check if we are the first:
        $admins = $this->mng->users->find(['site_admin' => true])->toArray();
        if( (count($admins) > 0) || !$create_if_first) {
            return false;
        }
        Utils::err('first admin');
        $this->mng->users->updateOne(['_id' => $this->_id], ['$set' =>['site_admin' => true]]);
        return true;
    }

    public function setInactivityTimeout($id, $value) {
        if( !is_numeric($value) || ($value < 5*60) || ($value > 5*60*60)) {
            return;
        }
        $timeout = $value;

        if($id =='desktop_inactivity') {
            $this->mng->users->updateOne(['_id' => $this->_id], ['$set' =>['desktop_inactivity' => $timeout]]);
        }
        if($id =='mobile_inactivity') {
            $this->mng->users->updateOne(['_id' => $this->_id], ['$set' =>['mobile_inactivity' => $timeout]]);
        }
    }

    public function isCSE() {
        if (isset($this->profile)) {
            if (!isset($this->profile->publicKey_CSE) || !isset($this->profile->privateKey_CSE)) {
                return false;
            }
        }
        return true;
    }

    // find which group has  higher access rights
    public function isBetterGroup($the_group, $outher_group) { 

        if($the_group->role == 'can edit') {  // highest possible group role
            return true;
        }

        if($other_group->role == 'can edit') {
            return false;
        }
        if($the_group->role == 'can view') {
            return true;
        }
        
        if($other_group->role == 'can view') {
            return false;
        }
        return true;
    }

    public function getGroups() {
        $mng_res = $this->mng->group_users->find([ 'UserID' => $this->UserID]);
        return $mng_res->toArray();
    }

    public function getSafes() {

        $t0 = microtime(true);

//        $aggregate = Safe::getSafes($this->mng, $this->UserID);


        $mng_res = $this->mng->safe_users->find([ 'UserID' => $this->UserID]);

        $safe_array = array();

        foreach ($mng_res as $row) {
            $id = $row->SafeID;
            $safe_array[$id] = new Safe($row);
        
            $safe_users = $this->mng->safe_users->find([ 'SafeID' => $row->SafeID])->toArray(); 
            $safe_array[$id]->user_count = count($safe_users);
        } 

        $mng_res = $this->mng->group_users->find([ 'UserID' => $this->UserID]);

        foreach ($mng_res as $group) {
            $group_safes = $this->mng->safe_groups->find([ 'GroupID' => $group->GroupID])->toArray(); 

            foreach($group_safes as $s) {
                $safe = (object)[
                        'id' => $s->SafeID, 
                        'group' => $s->GroupID,
                        'group_role' => $s->role,
                        'user_role' => $s->role,
                        'encrypted_key_CSE' => $s->encrypted_key,
                        'eName' => $s->eName,
                        "version" => $s->version,
                        "name" => "error"
                    ];
#                Utils::err('safe ' . $s->SafeID);
#                Utils::err($safe);
                if(!isset($safe_array[$s->SafeID])) {
#                    Utils::err('to be inserted');
                    $safe_array[$s->SafeID] = $safe;
                } else {
#                    Utils::err('direct access'); // or other group
                    if(isset($safe_array[$s->SafeID]->group)) {
                        if(isBetterGroup($group, $safe_array[$s->SafeID]->group)) {
                            $safe_array[$s->SafeID]->group = $group;
                        } 
                    }
                }
            }
        } 

        $dt = number_format((microtime(true) - $t0), 3);
        Utils::timingLog("safe_users " . $dt);

        $response = array();
        $storage_used = 0;
        $total_records = 0;
        foreach ($safe_array as $safe) {
//            if ($safe->isConfirmed()) {
            if(true) {

                $t0 = microtime(true);

                $cursor = $this->mng->safe_items->find(['SafeID' => $safe->id]);
                $items =  $cursor->toArray();
                foreach ($items as $item) {
                    $item->_id= (string)$item->_id;
                    if (property_exists($item, 'file')) {
                        $storage_used += $item->file->size;
                    }
                }

                $dt = number_format((microtime(true) - $t0), 3);
                Utils::timingLog("safe items " . $dt);

                $total_records += count($items);

                $t0 = microtime(true);
//                $folders = Folder::get_folder_list_cse($this->mng, $this->UserID, $safe->id);


                $cursor = $this->mng->safe_folders->find(['SafeID' => $safe->id]);
                $folders = $cursor->toArray();
                foreach ($folders as $folder) {
                    $folder->_id= (string)$folder->_id;
                }

                $dt = number_format((microtime(true) - $t0), 3);
                Utils::timingLog("safe folders " . $dt);

            } else {
                $items = [];
                $folders = [];
            }
            $safe_entry = [
                "name" => $safe->name,
                "user_name" => $safe->user_name,
                "id" => $safe->id,
                'confirm_req' => $safe->confirm_req,
//                'confirmed' => $safe->isConfirmed(),
                'confirmed' => true,
                "key" => $safe->encrypted_key_CSE,
                "items" => $items,
                "folders" => $folders,
                "users" => $safe->user_count,
                "user_role" => $safe->user_role
            ];

            if(property_exists($safe,"version")) {
                $safe_entry["version"] = $safe->version; 
                $safe_entry["eName"] = $safe->eName; 
                $safe_entry["name"] = "error";
            }
            if(property_exists($safe,"group")) {
                $safe_entry["group"] = $safe->group;
            }

            array_push($response, $safe_entry);
        }
        $_SESSION['STORAGE_USED'] = $storage_used;
        $_SESSION['TOTAL_RECORDS'] = $total_records;
        return $response;
    }

    public function getData() {
        
        $t0 = microtime(true);
        
        $this->getProfile();

        $dt = number_format((microtime(true) - $t0), 3);
        Utils::timingLog("getProfile " . $dt);
        
        $t0 = microtime(true);

        $safes=$this->getSafes();

//        $safes=Safe::getSafes($this->mng, $this->UserID);

        $dt = number_format((microtime(true) - $t0), 3);
        Utils::timingLog("getSafes " . $dt);


        $data = [
            'publicKeyPem' => $this->profile->publicKey_CSE,
            // 'invitation_accept_pending' => $this->invitation_accept_pending,
            'invitation_accept_pending' => false,
            'currentSafe' => $this->profile->currentSafe,
            'email' => $this->profile->email,
            'ePrivateKey' => $this->profile->privateKey_CSE,
            'safes' => $safes,

//            'safes' => $this->getSafes(),
            'ticket' => $_SESSION['wwpass_ticket'],
//            'plan' => $this->profile->plan
        ];
        if (defined('THEME') ) {
            $data['theme'] = "disabled";
        } else if(property_exists($this->profile, 'theme')) {
            $data['theme'] = $this->profile->theme;
        }

        $groups  = $this->getGroups();
        if(count($groups)) {
            $data['groups'] = $groups;
        }

        $data = array_merge($data, $this->getPlanDetails());

        if (defined('PUBLIC_SERVICE') && PUBLIC_SERVICE) {
            $data['business'] = false;
            if (Survey::showStatus($this)) {
                $data['takeSurvey'] = true;
            }
        } else {
            $data['business'] = true;
            if (defined('HIDDEN_PASSWORDS_ENABLED') && HIDDEN_PASSWORDS_ENABLED) {
               $data['HIDDEN_PASSWORDS_ENABLED'] = true; 
            }
            if(defined('MSP') && MSP  && !isset($this->profile->company)) {
                $data['msp'] = true;
            }
        }

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

        if($this->isSiteAdmin()) {
            $data['site_admin'] = true;
        }        

        if(property_exists($this->profile, 'generator')) {
            $data['generator'] = $this->profile->generator;
        }
 
        $data['websocket'] = false;
        if (defined('WEBSOCKET')) {
            if(WEBSOCKET) {
                $data['websocket'] = true;
            }
        }

        $data['WWPASS_TICKET_TTL'] = WWPASS_TICKET_TTL;
        $data['idleTimeout'] = $this->profile->desktop_inactivity;
        $data['desktop_inactivity'] = $this->profile->desktop_inactivity;
        $data['ticketAge'] =  (time() - $_SESSION['wwpass_ticket_creation_time']);

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
        if(($role == self::ROLE_ADMINISTRATOR)  || ($role == self::ROLE_EDITOR)) {
            return true;
        }

        // TODO: check if a user is a group member

        $mng_res = $this->mng->safe_groups->find(['SafeID' => $SafeID ]);
        $mng_rows = $mng_res->toArray();
        foreach($mng_rows as $group) {
#            Utils::err("group ");
#            Utils::err($group);

            // TODO: editor => self::ROLE_EDITOR

            if($group->role == "can edit") {
#                Utils::err("can write returns true");
                return true;
            }
        }
#        Utils::err("can write returns false");
        return false;
    }
    
    public function canRead($SafeID)
    {
        return ($this->getUserRole($SafeID) != false);
    }
    
    public function isAdmin($SafeID)
    {
        return ($this->getUserRole($SafeID) == self::ROLE_ADMINISTRATOR);
    }


    // not used?
    public function createSafe1($safe) {
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

    static function eNameSanityCheck($eName) {
        if(strlen($eName->data) > 1000) {
            return "Safe name too  long";
        }
        if(strlen($eName->tag) > 1000) {
            Utils::err('create safe 402');
            return "Internal server error";
        }
        if(strlen($eName->iv) > 1000) {
            Utils::err('create safe 406');
            return "Internal server error";
        }
        return "Ok";
    }
    
    public function createSafe($safe) {

        if(property_exists($safe,"name")) {
            if(strlen($safe->name) > 1000) {
                return "Safe name too  long";
            }
            if(strlen(trim($safe->name)) == 0) {
                return "Please fill in new safe name";
            }
        } else if(!property_exists($safe,"eName")) {
            return "Internal server error";
        }

        // sanity check 
        if(property_exists($safe,"eName")) {
            $sanityCheck = self::eNameSanityCheck($safe->eName);
            if( $sanityCheck != "Ok") {
                return $sanityCheck;
            }
        }
       
        if(!property_exists($safe,"aes_key") || (strlen($safe->aes_key) > 1000)) {
            Utils::err('create safe 409');
            return "Internal server error";
        }

        $SafeID = (string)new \MongoDB\BSON\ObjectId();
        
        if($safe->version == 3) {
            $this->mng->safe_users->insertOne(
                ['SafeID' => $SafeID, 'UserID' => $this->UserID, /*'SafeName' =>$safe->name, */
                'eName' => $safe->eName,
                'version' => $safe->version,
                'UserName' => null,
                'role' => self::ROLE_ADMINISTRATOR,
                'encrypted_key_CSE' => $safe->aes_key]
            );
   
        } else {

            $this->mng->safe_users->insertOne(
                ['SafeID' => $SafeID, 'UserID' => $this->UserID, 'SafeName' =>$safe->name,

                'UserName' => null,
                'role' => self::ROLE_ADMINISTRATOR,
                'encrypted_key_CSE' => $safe->aes_key]
            );
        }
        Utils::log('user ' . $this->UserID . ' activity safe created');
        $this->setCurrentSafe($SafeID);
        return array("status" =>"Ok", "id" => $SafeID);
    }


    function changeSafeName($SafeID, $eName) {


        $mng_res = $this->mng->safe_groups->find(['SafeID' => $SafeID ]);
        $mng_rows = $mng_res->toArray();
        if (count($mng_rows) >0) {
            if(!$this->isSiteAdmin()) {
                return "group safe";
            }
//            return "group safe, siteadmin";
        }

        // sanity check 
        $sanityCheck = self::eNameSanityCheck($eName);
        if( $sanityCheck != "Ok") {
            return $sanityCheck;
        }

        $result = $this->mng->safe_users->updateMany(
            ['UserID' => $this->UserID, 'SafeID' => $SafeID], 
            ['$set' =>['eName' =>$eName, "version" => 3],
            '$unset' => ['SafeName'=>""]]
        );


        if ($result->getModifiedCount() == 1) {
            Utils::log('user ' . $this->UserID . ' activity safe renamed');
            return "Ok";
        }

        Utils::err("UserID $this->UserID SafeID $SafeID newName $newName Modified count: " . $result->getModifiedCount());
        return "Internal error 323";
    }
   
    public function importSafes($req) {

        if (!isset($req->import)) {
            Utils::err("import_safes imported trees not defined");
            return "internal error";
        }
        
        $safeIDs = [];

        foreach ($req->import as $safe) {
            if (isset($safe->id)) {  //merge
                $SafeID = $safe->id;
                if (!$this->canWrite($SafeID)) {
                    return "access vioaltion or safe does not exist";
                }

                if (isset($safe->items) && (count($safe->items) >0)) {
                    Item::create_items_cse($this->mng, $this->UserID, $SafeID, $safe->items, 0);
                }
                if (isset($safe->folders)) {
                    foreach ($safe->folders as $folder) {
                        if (isset($folder->_id)) {
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
            } else if (!isset($safe->key) || !ctype_xdigit((string)$safe->key)) {
                Utils::err("import_safes key illegal or undefined");
                return "internal error";
            }

            //TODO truncate name length if required
            // patch naming
            $safe->aes_key = $safe->key;
            $result = $this->createSafe($safe);
            if (is_string($result)) {
                return $result;
            }
            $SafeID = $result['id'];

            if (isset($safe->items) && (count($safe->items) > 0)) {
                Item::create_items_cse($this->mng, $this->UserID, $SafeID, $safe->items, 0);
            }
            if (isset($safe->folders)) {
                foreach ($safe->folders as $folder) {
                    $r = Folder::import($this->mng, $this->UserID, $SafeID, 0, $folder);
                    if ($r['status'] != 'Ok') {
                        return $r;
                    }
                }
            }
            $safeIDs[] = $SafeID;
        }
        return ["status" => "Ok", "safeIDs" => $safeIDs];
    }

    public function deleteSafe($SafeID, $operation) {
    
        // check if it is not the last vault
    
        $mng_res = $this->mng->safe_users->find(['UserID' => $this->UserID ]);
        $mng_rows = $mng_res->toArray();
        if (count($mng_rows) < 2) {
            return "Cannot delete the last safe";
        }
    
        $mng_res = $this->mng->safe_groups->find(['SafeID' => $SafeID ]);
        $mng_rows = $mng_res->toArray();
        if (count($mng_rows) >0) {
            if(!$this->isSiteAdmin()) {
                return "group safe";
            }
            return "group safe, siteadmin";
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

            $result = $this->mng->safe_groups->deleteMany(['SafeID' => $SafeID]);
    
            $result = $this->mng->safe_users->deleteMany(['SafeID' => $SafeID]);
            if ($result->getDeletedCount() != 1) {
                Utils::err(print_r($result, true));
                return "Internal error del 472";
            }
            Utils::log(
                'user ' . $this->UserID .' activity safe deleted with '
                . $deleted['items'] . ' items and '
                . $deleted['folders'] . ' folders'
            );
            return ['status' => 'Ok', 'items' => $deleted['items'], 'folders' => $deleted['folders']];
        }
    
        return "Internal error";
    }

    function getPlanDetails() {

        $result = [];
    
        if(!defined('PUBLIC_SERVICE') || !PUBLIC_SERVICE) {
            $result['maxStorage'] = MAX_STORAGE_PER_USER;
            $result['maxRecords'] = MAX_RECORDS_PER_USER;
            $result['maxFileSize'] = MAX_FILE_SIZE;
            return $result;
        }
    
        if (property_exists($this->profile, 'plan')) {
            if ($this->profile->plan == 'Premium') {
                $result = getPremiumDetails($this->mng, $this->UserID);
                $result['maxRecords'] = MAX_RECORDS_PER_USER;
                $result['maxStorage'] = MAX_STORAGE_PER_USER;
                $result['maxFileSize'] = MAX_FILE_SIZE;
                $result['plan'] = 'PREMIUM';
                return $result;
            }
            
            for($i = 0; $i < count(FREE); $i++) {
                if(!strcasecmp($this->profile->plan, FREE[$i]['NAME'])) {
                    $result['maxRecords'] = FREE[$i]['MAX_RECORDS'];
                    $result['maxStorage'] = FREE[$i]['MAX_STORAGE'];
                    $result['maxFileSize'] = FREE[$i]['MAX_FILE_SIZE'];
                    $result['upgrade'] = [
                        'maxStorage' => MAX_STORAGE_PER_USER,
                        'maxRecords' => MAX_RECORDS_PER_USER,
                        'maxFileSize' => MAX_FILE_SIZE,
                        'price' => PREMIUM[0]['PRICE']
                    ];
                    $result['plan'] = 'FREE';
                    return $result;
                }
            }
        }
        
        $result['plan'] = 'PREMIUM';
        $result['maxStorage'] = MAX_STORAGE_PER_USER;
        $result['maxRecords'] = MAX_RECORDS_PER_USER;
        $result['maxFileSize'] = MAX_FILE_SIZE;
        return $result;
    }
    
    public function account($req = null) {
        if (!isset($this->profile)) {
            $this->getProfile();
        }

        if ($req && isset($req->operation)) {
            if($req->operation === 'generator') {
                $result = $this->mng->users->updateMany(
                    ['_id' => $this->_id], 
                    ['$set' => ['generator' => $req->value]]
                );
                return "Ok";
            }
            if($req->operation === 'theme') {
                $result = $this->mng->users->updateMany(
                    ['_id' => $this->_id], 
                    ['$set' => ['theme' => $req->theme]]
                );
                return "Ok";
            }

            if($req->operation === 'setInactivityTimeout') {
                $id = ($req->id) ? $req->id:"desktop_inactivity";
                $value = $req->value;
                $this->setInactivityTimeOut($id, $value);
                $this->getProfile(); // update return array

            } else if($req->operation === 'cancelSubscription') {
                Utils::err("account operation  = 'cancelSubscription'");
                $this->mng->subscriptions->updateMany(
                    [ 'UserID' => $this->UserID, "status" => "active"], 
                    ['$set' => ["status" => "cancel_request"]]
                );
                $this->cancel_subscriptions();
            }
        }
    
        $total_records = 0;
        $total_storage = 0;
        $total_safes = 0;


        $result = $this->getPlanDetails($this->mng, $this->profile);
        
        $result['email'] = $this->profile->email; 
        $result['desktop_inactivity'] = 
        $result['mobile_inactivity'] = $this->profile->desktop_inactivity;

        
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
    
        if (!defined('PUBLIC_SERVICE')) {
            $result['business'] = true;
        } 
        if($this->isSiteAdmin()) {
            $result['site_admin'] = true;
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

    public function premium_paid($transaction_id) {

        $now = new \DateTime();
		$period = new \DateInterval('P1Y');
		$expiration_date = $now->add($period); 
	
        $result = $this->mng->users->updateOne(
            ['_id' => $this->_id], 
            ['$set' => ['plan' => 'Premium',
             'expires' => $expiration_date->format(DATE_ATOM),
             'payment_id' => $transaction_id]
            ]
        );
        $message = "see logs for details";
        $message = $message . "<br>Server name " . $_SERVER['SERVER_NAME']; 
        $message = $message . "<br>Server IP " . $_SERVER['SERVER_ADDR'];
        $result = Utils::sendMail(SUPPORT_MAIL_ADDRESS,  "passhub PREMIUM paid", $message);
    }
    
    public function cancel_subscriptions() {
        
        if( defined('STRIPE') && isset(STRIPE['key'])) {
            \Stripe\Stripe::setApiKey(STRIPE['key']);

            $cursor = $this->mng->subscriptions->find(["UserID" => $this->UserID]);

            foreach($cursor as $s) {
                Utils::err("s->status1 " . $s->status);
                if($s->status != "canceled") {
                    try {
                        Utils::err('cancel subscription ' . $s->subscription);
                        $subscription = \Stripe\Subscription::retrieve($s->subscription);
                        Utils::err("s->status2 " . $subscription->status);
                        if($subscription->status != "canceled") {
                            $subscription->cancel();
                        }
                    } catch(\Stripe\Exception\CardException $e) {
                        Utils::err("A payment error occurred: {$e->getError()->message}");
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        Utils::err("An invalid request occurred.");
                    } catch (\Throwable $e) { // For PHP 7
                        Utils::err($e->getMessage());
                    } catch (\Exception $e) {
                        Utils::err("Another problem occurred, maybe unrelated to Stripe.");
                    }
                }
            }
        }
    }

    public function deleteAccount() {

        $this->getProfile();

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
        $this->cancel_subscriptions();

        $m = ' - ';
        if(isset($this->email)) {
            $m = $this->email;
        }
        Utils::log("user " . $this->UserID . " account deleted, mail " . $m);
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

    static function findUserByMail($mng, $email) {
        $pregEmail = preg_quote($email);
        $a = (
            $mng->users->find(
                ['email' => new \MongoDB\BSON\Regex('^' . $pregEmail . '$', 'i')]
            )
        )->toArray();
        return $a;
    }

    public static function getUserByMail($mng, $email) {

        $pregEmail = preg_quote($email);
        $a = (
            $mng->users->find(
                ['email' => new \MongoDB\BSON\Regex('^' . $pregEmail . '$', 'i')]
            )
        )->toArray();
        
        if (count($a) > 1) {
            foreach($a as $u) {            
                Utils::err($u);
            }
            throw new \Exception("error  utils 426, found " . count($a) . " users with email " . $email);
        }
        if (count($a) == 1) {
            return $a[0];
        }
        return null;
    } 
    
    
    public function safeAcl($req) {

        $SafeID = $req->vault;
        $operation = isset($req->operation) ? $req->operation: null;
        
        if (!ctype_xdigit($SafeID)) {
            return "Bad arguments";
        }

        $mng_res = $this->mng->safe_groups->find(['SafeID' => $SafeID ]);
        $mng_rows = $mng_res->toArray();
        if (count($mng_rows) >0) {
            if(!$this->isSiteAdmin()) {
                return "group safe";
            }
            return "group safe, siteadmin";
        }

//        if (!$this->isAdmin($SafeID)) {
//            return "unsubscribe";
//        }

/*
        # search the safe in my groups

        $mng_res = $this->mng->group_users->find([ 'UserID' => $this->UserID]);

        $group_role = "";
        foreach ($mng_res as $group) {
            $group_safes = $this->mng->safe_groups->find([ 'GroupID' => $group->GroupID])->toArray(); 

            Utils::err('group ' . $group->GroupID . ' safes');
            Utils::err($group_safes);


            foreach($group_safes as $s) {
                if( $s->SafeID = $SafeID ) {
                    // found 
                    if($s->role == "owner") {
                        $group_role = "owner";
                    } else if($group_role != "owner") {
                        if($s->role == "can edit") {
                               $group_role = "can edit";
                        } else if ($s->role != "can edit") {
                            if($s->role == "can view") {
                                $group_role = "can view";
                            } else {
                                $group_role = "limited_view";
                            }
                         }
                    }
                }
            }
        }

        if($group_role != "") {
            return [
                'status' => "Ok",
                'group_role' => $group_role
            ];
        }
*/

        $myrole = $this->getUserRole($SafeID);
        if (!$myrole) {
            return "error 1157";
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
   
        $UserName = isset($req->name) ? $req->name: null;
        $RecipientKey = isset($req->key) ? $req->key: null;
        $role = isset($req->role) ? $req->role: null;
        
        $update_page_req = false;
        if ($UserName != null) {
            if ($myrole != self::ROLE_ADMINISTRATOR) {
                return "You do not have administrative rights";
            }
    
            if ($operation == 'email') { //share by email


                if (!filter_var($UserName, FILTER_VALIDATE_EMAIL)) {
                    return "Invalid email address: " .  $UserName;
                }

                $UserName = strtolower($UserName);

                try {
                    $recipient = User::getUserByMail($this->mng, $UserName);
                }
                catch (\Exception $e) {
                    Utils::err($e->getMessage());
                    return "Internal server error 1189, please try again later";
                }
                if ($recipient == null) {
                    $email = htmlspecialchars($UserName);
                    $email_link = htmlspecialchars($UserName) 
                        . "?subject=" 
                        . htmlspecialchars("I would like to share a safe with you in " . $req->origin)
                        . "&amp;body="
                        . htmlspecialchars(
                            "I would like to share a safe with you in PassHub. "
                            . "If you’re new to PassHub, it is easy and fast "
                            . "to get started. To access this safe, you will first need "
                            . "to download and initialize the WWPass Key mobile app "
                            . "from the Android or iOS store. Once your WWPass Key is ready, "
                            . "please visit " . $req->origin . " and use the WWPass Key app to login"
                            . " to your PassHub account."
                        );  
                    Utils::err("share by mail: User with " . htmlspecialchars($UserName) . " not registered");
                    return "User " . $email . " is not registered";
                }

                if(defined('MSP') && isset($_SESSION['company']) && isset($recipient->company) && ($recipient->company != $_SESSION['company'])) {
                    return "User " . $email . " not found";
                }

                $TargetUserID = (string)($recipient->_id);
                if ($TargetUserID == $this->UserID) {
                    return "You cannot share the safe with yourself (" . $UserName . ")";
                }
                $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
                $cursor = $this->mng->safe_users->find($filter);
                if (count($cursor->toArray()) > 0 ) {
                    return "The recipient already has access to the safe";
                }
                Utils::log('user ' . $this->UserID . ' activity to share safe ' . $SafeID . ' with ' . $UserName);
                return ['status' => 'Ok', 'public_key' => $recipient->publicKey_CSE];
            }
            if ($operation == 'email_final') { //share by email

                try {
                    $recipient = User::getUserByMail($this->mng, $UserName);
                    if ($recipient == null) {
                        Utils::err("no user found " . $UserName);
                        return "no user found " . $UserName;
                    }
                }   
                catch (\Exception $e) {
                    Utils::err($e->getMessage());
                    return "Internal server error 1263, please try again later";
                }

                $TargetUserID = (string)($recipient->_id);


                if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
                    $role = self::ROLE_ADMINISTRATOR;
                } else {
                    $role = self::ROLE_READONLY;
                }
    
                if (isset($req->role) && in_array(
                    $req->role,
                    [self::ROLE_LIMITED_READONLY, 
                    self::ROLE_READONLY, 
                    self::ROLE_ADMINISTRATOR, 
                    self::ROLE_EDITOR]
                )
                ) {
                    $role = $req->role;
                }
                $result = $this->mng->safe_users->insertOne(
                    [
                    'SafeID' => $SafeID,
                    'UserID' => $TargetUserID,
                    'UserName' => null, 
                    'role' => $role,
                    'encrypted_key_CSE' => $RecipientKey,
                    'eName' => $req->eName,
                    'version' => 3
                    ]
                );

                if ($result->getInsertedCount() != 1) {
                    Utils::err("Internal error acl 1249");
                    return "Internal error acl 1249";
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
                Utils::err("error acl 1272");
                return "error acl 1272";
            }
            if (count($a) === 0) { // try mail
                $result = $this->mng->users->find(['email' => $UserName])->toArray();
    
                if (count($result) === 1) {
                    $TargetUserID = (string)$result[0]->_id;
                    $filter = ['UserID' => $TargetUserID, 'SafeID' => $SafeID];
                    $cursor = $this->mng->safe_users->find($filter);
                    $a = $cursor->toArray();
                    if (count($a) !== 1) {
                        Utils::err("error acl 1284");
                        return "error acl 1284";
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
                } else if ($role == 'limited view') {
                    $role = self::ROLE_LIMITED_READONLY;
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
        return [
            'status' => "Ok",
            'UserList' => $UserListOut, 
            'update_page_req' => $update_page_req,
            'HIDDEN_PASSWORDS_ENABLED' => (defined('HIDDEN_PASSWORDS_ENABLED') && HIDDEN_PASSWORDS_ENABLED)
        ];
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

    public function checkAzureAccess() {
//         Utils::err('checkAzureAccess');
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        if (isset($this->profile->userprincipalname)) {
//            $r = \PassHub\Azure::checkAccess($this->profile->userprincipalname);
            $r = Azure::checkAccess($this->profile->userprincipalname);
            /*
            Utils::err('checkAzureAccess');
            Utils::err($r);
            Utils::err('Session');
            Utils::err($_SESSION);
            Utils::err('TODO 1142');  
            */
            return $r;
        }
        return false;
    }
    
    public function checkLdapAccess() {
        Utils::err('checkLdapAccess');
        if (!isset($this->profile)) {
            $this->getProfile();
        }
        if (isset($this->profile->userprincipalname)) {
            $r = LDAP::checkAccess($this->profile->userprincipalname);
            return $r;
        }
        Utils::err('LDAP: no userprincipalname in user profile');
        return "not bound";
    }
}





/*

        $pipeline = [
            ['$match' => [ 'UserID' => $this->UserID]],
            ['$lookup'=> [
                              'from' => 'safe_items',
                              'as' => 'items',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]]
        ];
        $cursor = $this->mng->safe_users->aggregate($pipeline);
        $safe_items = $cursor->toArray();

        $pipeline = [
            ['$match' => [ 'UserID' => $this->UserID]],
            ['$lookup'=> [
                              'from' => 'safe_folders',
                              'as' => 'folders',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]]
        ];
        $cursor = $this->mng->safe_users->aggregate($pipeline);

        $safe_folders = $cursor->toArray();

#        Utils::err('----------');
#        Utils::err("folders aggregate result");
#        Utils::err($safe_folders);
#        Utils::err('----------');

        $safe_array1 = array();
        $storage_used1 = 0;
        $total_records1 = 0;


        
      
        foreach($safe_items as $s) {

            $safe_entry = [
                "name" => isset($s->SafeName) ? $s->SafeName : "error",
                "user_name" => $s->UserName,
                "id" => $s->SafeID,
                'confirm_req' => $s->confirm_req,
                'confirmed' => true,
                "key" => isset($s->encrypted_key) ? $s->encrypted_key : $s->encrypted_key_CSE,
                "user_role" => $s->role
            ];

            if($s->version == 3) {
                $safe_entry['version'] = $s->version;
                $safe_entry['eName'] = $s->eName;
                $safe_entry['name'] = "error";
            } else {
                $safe_entry['name'] = $s->SafeName;
            }
            $safe_entry['items'] = $s->items;

            foreach($safe_entry['items'] as $i) {
                $i->_id = (string)$i->_id;
                if (property_exists($i, 'file')) {
                    $storage_used1 += $i->file->size;
                }   
            }
            $total_records1 += count($safe_entry['items']);

            $safe_array1[$s->SafeID] = $safe_entry;
        }



        foreach($safe_folders as $s) {

            foreach($s->folders as $f) {
                $f->_id = (string)$f->_id;
            }

            $safe_array1[$s->SafeID]['folders'] = $s->folders;
        }



        $pipeline = [
            ['$match' => [ 'UserID' => $this->UserID]],
            ['$lookup'=> [
                              'from' => 'safe_users',
                              'as' => 'safes',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]],
#            $count: "passing_scores" 
        ];

        $cursor = $this->mng->safe_users->aggregate($pipeline);

        $safe_users = $cursor->toArray();

        foreach($safe_users as $s) {
            $safe_array1[$s->SafeID]['users'] = count($s->safes);
        }

        Utils::err('----------');
#       Utils::err("safe_array1");
#        Utils::err($safe_array1 );
       Utils::err('----------');
        Utils::err(json_encode($safe_array1, JSON_PRETTY_PRINT));
        Utils::err('=======================');


        Utils::err('storage');
        Utils::err($storage_used1);
        Utils::err('total recordse');
        Utils::err($total_records1);





*/