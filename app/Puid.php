<?php

/**
 * Puid.php
 *
 * PHP version 7
 *
 * modified code of https://github.com/altmetric/mongo-session-handler
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class Puid 
{
    function __construct($mng, $PUID) {
        $this->mng = $mng;
        $this->PUID = $PUID;
    }

    public function isValidated() {
        $cursor = $this->mng->reg_codes->find(['PUID' => $this->PUID, 'verified' => true]);
        $puids = $cursor->toArray();
        $num_puids = count($puids);
        if ($num_puids == 0) {
            return false;
        }
        if ($num_puids == 1) {  //found, delete all others
            return true;
        }
        Utils::err("internal error usr 339 count " . $num_puids);
        return false; //multiple code records;
    }

    public function getUserByPuid() {
        $cursor = $this->mng->users->find([ 'PUID' => $this->PUID ]);
        $puids = $cursor->toArray();
        $num_puids = count($puids);
        if ($num_puids == 1) {
            $UserID = (string)($puids[0]->_id);
            Utils::err("PUID " . $puid . " found in users " . $UserID);
            return array("UserID" => $UserID, "status" => "Ok");
        }
        if ($num_puids == 0) {  // try legacy table
            $cursor = $this->mng->puids->find(['PUID' => $this->PUID]);
            $puids = $cursor->toArray();
            $num_puids = count($puids);
            if ($num_puids == 0) {
                return array("status" => "not found");
            }
            if ($num_puids == 1) {
                return array("UserID" => $puids[0]->UserID, "status" => "Ok");
            }
        }
        Utils::err("internal error usr 34 count " . $num_puids . " puid " . $puid);
        return array("status" =>"internal error usr 34"); //multiple PUID records;
    }
    
    public function getVerificationCode($email, $purpose = "registration") {

        $cursor = $this->mng->users->find(['email' => $email]);
        $codes = $cursor->toArray();
        $num_users = count($codes);
        if ($num_users  != 0) {
            return ["status" => "This e-mail address is already in use. Please provide another e-mail address."];
        }
        $v1 = random_int(0, 9999);
        $v2 = random_int(0, 9999);
        $v3 = random_int(0, 9999);
        $v4 = random_int(0, 9999);
        $result = false;
        $v = sprintf("%04d-%04d-%04d-%04d", $v1, $v2, $v3, $v4);
        if ($purpose == "change") {
            $result = $this->mng->change_mail_codes->insertOne(['PUID' => $this->PUID, 'code' => $v, 'verified' => false, 'created' => Date('c'), 'email' => $email]);
        } else {
            $result = $this->mng->reg_codes->insertOne(['PUID' => $this->PUID, 'code' => $v, 'verified' => false, 'created' => Date('c'), 'email' => $email]);
        }
        if ($result->getInsertedCount() != 1 ) {
            Utils::err("Error user 294");
            return ["status" => "Internal Error 294"];
        }
        return array("status" => "Ok", "code" => $v);
    }
    
    public function createUser($post /* $publicKey, $encryptedPrivateKey*/) {

        if (defined('LDAP')) {
            $email = $_SESSION['email'];
            $userprincipalname = $_SESSION['userprincipalname'];
        } else if (defined('MAIL_DOMAIN')) {
            $cursor = $this->mng->reg_codes->find(['PUID' => $this->PUID, 'verified' => true]);
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
        if (isset($userprincipalname)) {
            $record['userprincipalname'] = $userprincipalname;
        }
  
        if (defined('PREMIUM')) {
            $record['plan'] = 'FREE';
        }
  
        try {
            $r = $this->mng->users->insertOne($record);
        } catch (Exception $e) {
        }
  
        $UserID = (string)$r->getInsertedId();
        if (1) {
            $this->mng->puids->insertOne(['PUID' => $this->PUID, 'UserID' => $UserID]);
        }
        if (isset($post['import'])) { 
            $user = new User($this->mng, $UserID);
            $user->importSafes($post);
        }
        if (isset($email)) {
            Utils::err("new user $email $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
        }
        Utils::log("new user $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);
    
        return array("UserID" => (string)$UserID, "status" => "Ok");
    }

    static function processRegCode1($mng, $code, $purpose = "registration") {

        Utils::err("process_reg_code1 " . $purpose . " " . $code);
        if ($purpose == "change") {
            $collection = $mng->change_mail_codes;
        } else {
            $collection = $mng->reg_codes;
        }
        $cursor = $collection->find(['code' => $code]);
    
        $codes = $cursor->toArray();
        $num_codes = count($codes);
        if ($num_codes == 0) {
            return "Unknown or expired verification code: " . $code;
        }
        if ($num_codes == 1) {
            if (true) {
    //        if ($PUID === $codes[0]->PUID) {
                if ($codes[0]->verified == false) {
                    $collection->updateOne(['code' => $code], ['$set' => ['verified' => true]]);
                    $PUID = $codes[0]->PUID;
    
                    // PUID verified, delete all other codes
                    $collection->deleteMany(['PUID' => $PUID, 'verified' =>false]);

                    $puid = new Puid($mng, $PUID);
    
                    $result = $puid->getUserByPuid();
                    if ($result['status'] != "not found") {
                        $UserID = $result['UserID'];
                        $user = new User($mng, $UserID);
                        $user->setEmailAddress($codes[0]->email);
                        Utils::err("user " . $UserID  . " registered mail " . $codes[0]->email);
                    }
                    return "Ok";
                }
                return "Verification code already used";
            }
            return "You must log in with the same PassKey that you used when submitting your e-mail address.";
        }
        Utils::err("Internal error usr 312 count " . $num_puids);
        return "Internal error usr 312"; //multiple code records;
    }
    
}
