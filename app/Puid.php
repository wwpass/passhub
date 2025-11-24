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

    public function getUserByPuid($last_seen_update = true) {

        $cursor = $this->mng->users->find([ 'PUID' => $this->PUID ]);
        $puids = $cursor->toArray();
        $num_puids = count($puids);
        if ($num_puids == 1) {
            $UserID = (string)($puids[0]->_id);
            Utils::err("PUID " . $puid . " found in users " . $UserID);
            return ["UserID" => $UserID, "status" => "Ok"];
        }
        if ($num_puids == 0) {  // try legacy table
            $cursor = $this->mng->puids->find(['PUID' => $this->PUID]);
            $puids = $cursor->toArray();
            $num_puids = count($puids);
            if ($num_puids == 0) {
                return ["status" => "not found"];
            }
            if ($num_puids == 1) {
                if($last_seen_update) {
                    $user = new User($this->mng, $puids[0]->UserID);
                    $user->updateLastSeen();
                }
                return ["UserID" => $puids[0]->UserID, "status" => "Ok"];
            }
        }
        Utils::err("internal error usr 34 count " . $num_puids . " puid " . $puid);
        return array("status" =>"internal error usr 34"); //multiple PUID records;
    }
    
    public function getVerificationCode($mng, $email, $purpose = "registration") {

        $email = strtolower($email);

        try {
            $existing_user = User::getUserByMail($mng, $email);

            if ($existing_user !== null) {

                if($purpose == "change") {
                    if($_SESSION['UserID'] == (string)$existing_user->_id) {
                        return ["status" => "same email address " . $email];
                    }
                }
                return ["status" => "The email address is already in use. Please provide another email address."];
            } 
        }
        catch (\Exception $e) {
            Utils::err("getUserByMail exception: ", $e->getMessage());
            return ["status" => "The email address is already in use. Please provide another email address."];
        }

        $v1 = random_int(0, 9999);
        $v2 = random_int(0, 9999);
        $v3 = random_int(0, 9999);
        $v4 = random_int(0, 9999);
        $result = false;
        $v = sprintf("%04d-%04d-%04d-%04d", $v1, $v2, $v3, $v4);
        $code6 = sprintf("%06d", random_int(0, 999999));
        $code_array = [
            'PUID' => $this->PUID, 
            'code' => $v, 
            'code6' => $code6, 
            'verified' => false, 
            'created' => Date('c'), 
            'email' => $email];

        if ($purpose == "change") {
            $result = $this->mng->change_mail_codes->insertOne($code_array);

        } else {
            $result = $this->mng->reg_codes->insertOne($code_array);
        }
        if ($result->getInsertedCount() != 1 ) {
            Utils::err("Error puid 106");
            return ["status" => "Internal Error 106"];
        }
        return ["status" => "Ok", "code" => $v, 'code6' => $code6];
    }
    
    public function createUser($req /* $publicKey, $encryptedPrivateKey*/) {

        $record = [
            'publicKey_CSE' =>$req->publicKey,
            'privateKey_CSE' => $req->encryptedPrivateKey,
            'currentSafe' => null
        ];
  
        if (defined('LDAP') || defined('AZURE')) {
            $email = $_SESSION['email'];
            $userprincipalname = $_SESSION['userprincipalname'];
        } else if (defined('MAIL_DOMAIN')) {
            $cursor = $this->mng->reg_codes->find(['PUID' => $this->PUID, 'verified' => true]);
            $puids = $cursor->toArray();
            $num_puids = count($puids);
            if ($num_puids == 1) {  //found, delete all others
                if (property_exists($puids[0], 'email')) {
                    $email = $puids[0]->email;

                    $mail_domains = preg_split("/[\s,]+/", strtolower(MAIL_DOMAIN));

                    for($i = 0; $i < count($mail_domains); $i++) { 
                        if ($mail_domains[$i] === strtolower($email)) {
                            $record['site_admin'] = true;
                        }
                    }    
                }
            }
        }
  
        if (isset($email)) {
            $record['email'] = $email;

        }
        if (isset($userprincipalname)) {
            $record['userprincipalname'] = $userprincipalname;
        }
  
        if (defined('PREMIUM')  && defined('PUBLIC_SERVICE')) {
            $record['plan'] = FREE[0]['NAME'];
        }
  
        if (isset($_SESSION['company'])) {
            $record['company'] = $_SESSION['company'];
        }

        try {
            $r = $this->mng->users->insertOne($record);
        } catch (Exception $e) {
        }
  
        $UserID = (string)$r->getInsertedId();
        if (1) {
            $this->mng->puids->insertOne(['PUID' => $this->PUID, 'UserID' => $UserID]);
        }
        if (isset($req->import)) { 
            $user = new User($this->mng, $UserID);
            $user->importSafes($req);
        }
        if (isset($email)) {
            Utils::err("new user $email $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);

            if(defined('ADMIN_NOTIFICATION_MAIL')) {
                Utils::sendMail(
                    to: ADMIN_NOTIFICATION_MAIL, 
                    subject: "new passhub user notification", 
                    body_txt: "A user with the email address " . $email . "has created an account in " . $_SERVER['HTTP_HOST']);
            }
        }
        Utils::log("new user $UserID " . $_SERVER['REMOTE_ADDR'] . " " .  $_SERVER['HTTP_USER_AGENT']);

        if(!defined('PUBLIC_SERVICE')) {
            if(defined('REGISTRATION_ACCESS_CODE') && isset($_SESSION['REGISTRATION_ACCESS_CODE']) &&  ($_SESSION['REGISTRATION_ACCESS_CODE'] == REGISTRATION_ACCESS_CODE)) {
                Utils::audit_log($this->mng, ["actor" => $email, "operation" => "Create account", "access_code" => "..." . substr(REGISTRATION_ACCESS_CODE,-4)]);
            } else {
               Utils::audit_log($this->mng, ["actor" => $email, "operation" => "Create account"]);
            }
        }
   
        return array("UserID" => (string)$UserID, "status" => "Ok");
    }

    public function processCode6($code6, $purpose = "registration") {
        Utils::err("process_code6 " . $purpose . " " . $code6 . ' PUID ' . $this->PUID);
        if ($purpose == "change") {
            $collection = $this->mng->change_mail_codes;
        } else {
            $collection = $this->mng->reg_codes;
        }
        $cursor = $collection->find(['PUID' => $this->PUID, 'code6' => $code6]);
    
        $codes = $cursor->toArray();
        $num_codes = count($codes);
        if ($num_codes == 0) {
            Utils::err("Verification code " .$code6 . " not found");
            return "Unknown or expired verification code: " . $code6;
        }
        if ($num_codes == 1) {
            if ($codes[0]->verified == false) {
                $collection->updateOne(['PUID' => $this->PUID, 'code6' => $code6],
                    ['$set' => ['verified' => true]]);

                // PUID verified, delete all other codes
                $collection->deleteMany(['PUID' => $this->PUID, 'verified' =>false]);

                $result = $this->getUserByPuid();

                if ($result['status'] != "not found") {
                    $UserID = $result['UserID'];
                    $user = new User($this->mng, $UserID);
                    $user->setEmailAddress($codes[0]->email);
                    if ($purpose == "registration") {
                        Utils::err("user " . $UserID  . " registered mail " . $codes[0]->email);
                    } else {
                        Utils::err("user " . $UserID  . " changed mail to " . $codes[0]->email);
                    }
                }
                return ['status' => "Ok", 'email' => $codes[0]->email];
            }
            return "Verification code already used";
        }
        Utils::err("Internal error usr 312 count " . $num_puids);
        return "Internal error usr 312"; //multiple code records;
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
            return "You must log in with the same WWPass Key that you used when submitting your email address.";
        }
        Utils::err("Internal error usr 312 count " . $num_puids);
        return "Internal error usr 312"; //multiple code records;
    }
    
}
