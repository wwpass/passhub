<?php

/**
 * Iam.php
 *
 * PHP version 7
 *
 * modified code of https://github.com/altmetric/mongo-session-handler
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class Iam
{
    

    public static function audit($mng, $req, $admin_email) {
        $cursor = $mng->audit->find([]);
        $data = $cursor->toArray();

        return ["status"=>"Ok", "data" => json_encode($data)];
    }

    public static function whiteMailList($mng, $company=null) 
    {
        $filter = [];
        if($company && (strlen($company) == 24) && ctype_xdigit($company)) {
            $filter = ['company' => $company];
        }
        $cursor = $mng->mail_invitations->find($filter, ['projection' => ['_id' => false, 'email' => true]]);
        $mail_array = $cursor->toArray();
        return ["status" =>"Ok", 'mail_array' => $mail_array];
    }

    static function sendInvitationMail($email) {
        $invitation_mail_subject = file_get_contents('config/invitation_mail_subject.txt');
        $invitation_mail = file_get_contents('config/invitation_mail.txt');
        
        if (strlen($invitation_mail_subject) == 0) {
            Utils::err("config/invitation_mail_subject.txt absent or empty");
            return;
        }
        if (strlen($invitation_mail) == 0) {
            Utils::err("config/invitation_mail.txt absent or empty");
            return;
        }
        
        Utils::sendMail($email, $invitation_mail_subject, $invitation_mail, $contentType = 'text/html; charset=UTF-8');
    }

    public static function addWhiteMailList($mng, $req, $admin_email) 
    {
        $email = $req->email;
        $cursor = $mng->mail_invitations->find(['email' => $email]);
        foreach ( $cursor as $row) {
            // already invited
            return ['status' => 'already used'];
        }
        $users = User::findUserByMail($mng, $email);
        $c = count($users);
        if($c > 0) {
            return ['status' => 'already used'];
        } 
        $company = null;
        if( $req->company && (strlen($req->company) == 24) && ctype_xdigit($req->company) ) {
            $company = $req->company;
            $mng->mail_invitations->insertOne(['email' => $email, 'company' => $company]);
            
        } else {
            $mng->mail_invitations->insertOne(['email' => $email]);
        }


        Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "invite", "user" => $email]);

        self::sendInvitationMail($email);
        return self::whiteMailList($mng, $company);
    }

    public static function removeWhiteMailList($mng, $email) 
    {
        $mng->mail_invitations->deleteMany(['email' => $email]);
        return self::whiteMailList($mng);
    }
    
    public static function isMailAuthorized($mng, $email) {

        if(defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
            return true;
        }

        if(defined('REGISTRATION_ACCESS_CODE') && isset($_SESSION['REGISTRATION_ACCESS_CODE']) &&  ($_SESSION['REGISTRATION_ACCESS_CODE'] == REGISTRATION_ACCESS_CODE)) {
            Utils::err('REGISTRATION_ACCESS_CODE present');
            return true;
        }

        if( defined('LDAP')  
            && isset(LDAP['mail_registration']) 
            && (LDAP['mail_registration'] == true)) {
                $mail_domains = [LDAP['domain']];
        } else {
            $mail_domains = preg_split("/[\s,]+/", strtolower(MAIL_DOMAIN));
        }

        if ($mail_domains[0] === "any") {
            return true;
        }

        for($i = 0; $i < count($mail_domains); $i++) { 
            if ($mail_domains[$i] === strtolower($email)) {
                return true;
            }
        }        

        // or it is a domain part of the email, e.g. mycompany.com. (hardly usable)
        $parts = explode("@", $email);
        if (in_array(strtolower($parts[1]), $mail_domains)) {
            return true;
        }
        // is invited:
        $cursor = $mng->mail_invitations->find(['email' => strtolower($email)]);
        foreach ( $cursor as $row) {
            if($row->company) {
                $_SESSION['company'] = $row->company;
                return true;
            }

            return true;
        }
        return false;
    }

    public static function setStatus($mng, $new_status, $user_id, $admin_email) {

        $user = new User($mng, $user_id);
        $user->getProfile();
        $user_email = $user->profile['email'];

        if($new_status == 'admin') {
            $mng->users->updateOne(['_id' => $user->_id], ['$set' =>['site_admin' => true, 'disabled' => false]]);
            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "statusAdmin", "user" => $user_email]);
            return ['status' => "Ok"];
        }
        if($new_status == 'active') {
            $result = $mng->users->updateOne(['_id' => $user->_id], ['$set' =>['site_admin' => false, 'disabled' => false]]);
    
            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "statusActive", "user" => $user_email]);
            return ['status' => "Ok"];
        }
        if($new_status == 'disabled') {
            $mng->users->updateOne(['_id' => $user->_id], ['$set' =>['site_admin' => false, 'disabled' => true]]);
            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "statusDisabled", "user" => $user_email]);
            return ['status' => "Ok"];
        }
        Utils::err('usr err 107 operation ' . $operation);
        return ['status' => "Internal error"];
    }

    public static function deleteUser($mng, $userToDelete, $admin_email) {

        if(isset($userToDelete->id) && $userToDelete->id && (strlen($userToDelete->id) > 0)) {
            $user = new User($mng, $userToDelete->id);

            $user->getProfile();
            if ($user->profile['email']) {
                 $result = $mng->mail_invitations->deleteMany(['email' => $user->profile['email']]);
            }
            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "deleteAccount", "user" => $user->profile['email']]);
            return $user->deleteAccount();
        }

        if(isset($userToDelete->email) && $userToDelete->email) {

            $result = $mng->mail_invitations->deleteMany(['email' => $userToDelete->email]);
            $users = User::findUserByMail($mng, $userToDelete->email);
            $c = count($users);

            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "deleteInvitation", "user" => $userToDelete->email]);

            if($c == 1) {

                $id = $users[0];                                
                $user = new User($mng, $users[0]);
                return $user->deleteAccount();
            } 
            if($c == 0) {
                return "Ok";
            }
        }
        return "user not found";
    }


    private static function getGroupSafes($mng, $GroupID) {
        $cursor = $mng->safe_groups->find(["GroupID" => $GroupID], ["projection" => ["_id"=> false, "SafeID" => true, "role" => true]]);
        $safes = $cursor->toArray();
        return $safes;
    }

    private static function getGroupUsers($mng, $GroupID) {
        $cursor = $mng->group_users->find(["GroupID" => $GroupID], ["projection" => ["_id"=> false, "UserID" => true, "role" => true]]);
        $users = $cursor->toArray();
        return $users;
    }

    private static function getGroups($mng, $UserID) {
        $cursor = $mng->group_users->find(["UserID" => $UserID]);
        $groups = $cursor->toArray();
        foreach($groups as $group ) {
            $group->users = self::getGroupUsers($mng, $group->GroupID);
            $group->safes = self::getGroupSafes($mng, $group->GroupID);
        }
        return $groups;
    }

/*
    private static function isInAdminGroup($user) {
        $group_count = $user['memberof']['count'];
        for($g = 0; $g < $group_count; $g++ ) {
            if($user['memberof'][strval($g)] == LDAP['admin_group']) {
                return true;
            }
        }
        return false;
    }
*/

    private static function getUserArray($mng, $req, $UserID) 
    {
        $filter = [];
        $company = null;
        if($req->company  && (strlen($req->company) == 24) && ctype_xdigit($req->company)) {
            $company = $req->company;
            $filter = ['company' => $req->company];
        }

        if(defined('MSP')) {
            $User = new User($mng, $UserID);
            $company = $User->getProfile()->company;
   
            if(isset($company)) {
                $filter = ['company' => $company];
            }
        }

        $cursor = $mng->users->find($filter, ['projection' => [
                "_id" => true, 
                "lastSeen" => true, 
                "email" => true,
                "userprincipalname" => true,
                "site_admin" =>true, 
                "disabled" => true
                ]
            ]);
        $user_array = $cursor->toArray();

        if(defined('AzureCloud') || defined ('LDAP')) {
            $lu = null;
            if(defined('AzureCloud')) {
                $lu = Azure::getUsers();
            } else {
                $lu = LDAP::getUsers();
            }

            $ldap_users = $lu["user_upns"];
            $ldap_admins = $lu["admin_upns"];

            $active_user_upns = [];
            foreach($user_array as $u) {
                array_push($active_user_upns, $u["userprincipalname"]);

                $upn = strtolower($u["userprincipalname"]);
                if(!in_array($upn, $ldap_users)) {
                    $u["disabled"] = true;
                } else {
                    $u["disabled"] = false;
                    if(in_array($upn, $ldap_admins)) {
                        $u["site_admin"] = true;
                    }
                }
            }
            foreach($ldap_users as $lu) {
                if(!in_array($lu, $active_user_upns)) {
                    array_push($user_array, ["email" => $lu, "status" => "invited"]);
                }
            }
            return $user_array;
        }

        $mail_list = [];

        foreach ($user_array as $user) {
            array_push($mail_list, $user['email']);
        }

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

        $cursor = $mng->mail_invitations->find($filter, ['projection' => ['_id' => false, 'email' => true]]);
        $invited = $cursor->toArray();        
        
        foreach($invited as $i) {
            if(!in_array($i['email'], $mail_list)) {
                array_push($user_array, ["email" => $i['email'], "status" => "invited"]);
            }
        }
        if(!defined('MSP')) {
            if ($admins_found === false) {   // first time visit to iam.php 
                $id =  (strlen($UserID) != 24)? $UserID : new \MongoDB\BSON\ObjectID($UserID);
                $mng->users->updateOne(['_id' => $id], ['$set' =>['site_admin' => true]]);
                $cursor = $mng->users->find([], ['projection' => [
                    "_id" => true, 
                    "lastSeen" => true, 
                    "email" => true, 
                    "site_admin" =>true,
                    "disabled" =>true,
                    ]]);
                $user_array = $cursor->toArray();
            } else if ($i_am_admin === false) {
                Utils::errorPage("not enough rights"); // TODO exception
                return [];
            }
        }
        return $user_array;
    }

    private static function getSafeUserArray($mng) 
    {
        $cursor = $mng->safe_users->find([], ['projection' =>["SafeID" => true, "UserID" => true]]);
        return $cursor->toArray();
    }

    public static function getPageData($mng, $req, $UserID) 
    {
        
        $user_array = self::getUserArray($mng, $req, $UserID);

        if(!defined('MSP')) {
            if (count($user_array) == 0) {
                return 'not enough rights';
            }
        }
       
        $_SESSION['site_admin'] = true;
        
        $safe_user_array = self::getSafeUserArray($mng);
        
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
        
        if(defined('MAIL_DOMAIN')) {
            $stats .= "MAIL DOMAINS: " . MAIL_DOMAIN . "\n";
        }
        
        foreach ($user_array as $user) {
            if(isset($user->_id)) {
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
        }
        $groups = self::getGroups($mng, $UserID);
        $result = ["stats" => $stats, "users" => $user_array, "groups" => $groups];

        if(defined('LICENSED_USERS')) {
            $result['LICENSED_USERS'] = LICENSED_USERS;
        }
        if(defined('LDAP') || defined('AzureCloud')) {
            $result['LDAP'] = true;
        }
        return $result;
    }

}
