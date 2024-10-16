<?php

/**
 * Comapnies.php
 *
 *
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class Company
{
    
    public static function addCompany($mng, $req, $admin_email) {
        if($req->name) {
            $mng->companies->insertOne(['name' => $req->name]);
            Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "addCompany", "company" => $req->name]);
            return ['status' => "Ok"];
        }
        return ['status' => "Bad Request"];
    }
/*
    public static function getProfile($mng, $companyId) {
        $result = $mng->companies->findOne(['_id' => new \MongoDB\BSON\ObjectID($companyId)]);
        return ["status" => "Ok", "profile" => $result];
    }
*/

    public static function setProfile($mng, $req, $admin_email) {
        $result = $mng->companies->updateOne(['_id' => new \MongoDB\BSON\ObjectID($req->companyId)], ['$set' => ['licensedUsers' => $req->licensedUsers]]);

//        $result = $mng->users->updateOne(['_id' => $user->_id], ['$set' =>['site_admin' => false, 'disabled' => false]]);        

        Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "setCompanyProfile", "company" => $req->companyId]);

        return ["status" => "Ok"];
    }


    public static function getPageData($mng) 
    {
        $result = $mng->companies->find();
        $companies = $result->toArray();

        foreach($companies as $company) {
            $company->_id= (string)$company->_id;
        }

        return ["companies" => $companies, "status" => "Ok"];
    }

    public static function audit($mng, $req, $admin_email) {
        $cursor = $mng->audit->find([]);
        $data = $cursor->toArray();

        return ["status"=>"Ok", "data" => json_encode($data)];
    }

    public static function whiteMailList($mng) 
    {
        $cursor = $mng->mail_invitations->find([], ['projection' => ['_id' => false, 'email' => true]]);
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

    public static function addWhiteMailList($mng, $email, $admin_email) 
    {
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

        $mng->mail_invitations->insertOne(['email' => $email]);

        Utils::audit_log($mng, ["actor" => $admin_email, "operation" => "invite", "user" => $email]);

        self::sendInvitationMail($email);
        return self::whiteMailList($mng);
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

        Utils::err('USerTo Delete');
        Utils::err($userToDelete);
        Utils::err($userToDelete->email);


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

    private static function isInAdminGroup($user) {
        $group_count = $user['memberof']['count'];
        for($g = 0; $g < $group_count; $g++ ) {
            if($user['memberof'][strval($g)] == LDAP['admin_group']) {
                return true;
            }
        }
        return false;
    }

    private static function getUserArray($mng, $UserID) 
    {
        $cursor = $mng->users->find([], ['projection' => [
                "_id" => true, 
                "lastSeen" => true, 
                "email" => true,
                "userprincipalname" => true,
                "site_admin" =>true, 
                "disabled" => true
                ]
            ]);
        $user_array = $cursor->toArray();

        if(defined('LDAP')) {

            $lu = LDAP::getUsers();
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
            array_push($mail_list,$user['email']);
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
        
        $invited = Iam::whiteMailList($mng)['mail_array'];
        foreach($invited as $i) {
            if(!in_array($i['email'], $mail_list)) {
                array_push($user_array, ["email" => $i['email'], "status" => "invited"]);
            }
        }

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
        return $user_array;
    }

    private static function getSafeUserArray($mng) 
    {
        $cursor = $mng->safe_users->find([], ['projection' =>["SafeID" => true, "UserID" => true]]);
        return $cursor->toArray();
    }


}
