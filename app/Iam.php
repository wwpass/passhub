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

    public static function addWhiteMailList($mng, $email) 
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

    private static function getUserArray($mng, $UserID) 
    {
        $cursor = $mng->users->find([], ['projection' => [
                "_id" => true, 
                "lastSeen" => true, 
                "email" => true, 
                "site_admin" =>true, 
                "disabled" => true
                ]
            ]);
        $user_array = $cursor->toArray();

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
        Utils::err(print_r($mail_list, true));
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

    public static function getPageData($mng, $UserID) 
    {
        $user_array = self::getUserArray($mng, $UserID);

        if (count($user_array) == 0) {
            return 'not enough rights';
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
        
        $stats .= "MAIL DOMAINS: " . MAIL_DOMAIN . "\n";
        
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
        return $result;
    }

    public static function deleteUser($mng, $userToDelete) {

        if(isset($userToDelete['id']) && $userToDelete['id']) {
            $user = new User($mng, $userToDelete['id']);

            $user->getProfile();
            if ($user->profile['email']) {
                $result = $mng->mail_invitations->deleteMany(['email' => $user->profile['email']]);
            }
            return $user->deleteAccount();
        }

        if(isset($userToDelete['email']) && $userToDelete['email']) {
            $result = $mng->mail_invitations->deleteMany(['email' => $userToDelete['email']]);
            $users = User::findUserByMail($mng, $userToDelete['email']);
            $c = count($users);
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
}
