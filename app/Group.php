<?php

/**
 * Group.php
 *
 * PHP version 7+
 * 
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class Group
{
    public const ROLE_ADMINISTRATOR = 'administrator';

    static private function getGroupById($mng, $groupId) {

        $pregID = preg_quote($groupId);
        $a = (
            $mng->group_users->find(
                ['GroupID' => new \MongoDB\BSON\Regex('^' . $pregID . '$', 'i')]
            )
        )->toArray();
        
        if (count($a) > 0) {
            return $a[0];
        }
        return null;
    } 

    static private function getSafeById($mng, $safeId, $UserID) {

        $pregID = preg_quote($groupId);
        $a = (
            $mng->safe_users->find(
                ['SafeID' => new \MongoDB\BSON\Regex('^' . $pregID . '$', 'i'), 'UserID' => $UserID]
            )
        )->toArray();
        
        if (count($a) > 0) {
            return $a[0];
        }
        return null;
    } 

    static public function getUserPublicKey($mng, $groupId, $email, $UserID) {


        // find user by Email
        $user = Utils::getUserByMail($mng, $email);
        if(is_string($user)) {
            return $user;
        } 
        if(!$user) {
            return  "no user found with this email";
        }
        return ['status' => 'Ok', 'public_key' => $user->publicKey_CSE];
    }

    static public function removeUser($mng, $groupId, $id, $UserID) {

        if($id == $UserID) {
            return "You (group admin) cannot leave the group";
        }

        $filter = ['GroupID' => $groupId, 'UserID' => $id];

        $result = $mng->group_users->deleteOne(
            ['GroupID' => $groupId, 
            'UserID' => $id]
        );
        Utils::err($filter);
        Utils::err('67 remove User result');
        Utils::err($result);
        return ['status' => 'Ok'];
    }

    static public function addUser($mng, $groupId, $email, $key, $UserID) {
        $user = Utils::getUserByMail($mng, $email);
        Utils::err('user');
        Utils::err($user);

        $group = self::getGroupById($mng, $groupId);
        if(!$group) {
            return  "no group found with id " . $groupId;
        }

        Utils::err('group');
        Utils::err($group);

        $mng->group_users->insertOne(
            ['GroupID' => $groupId, 
            'UserID' => (string)($user->_id),
            'eName' => $group->eName,
            'version' => $group->version,
            'encrypted_key_CSE' => $key]
        );
        return ['status' => 'Ok'];
    }


    static public function rename($mng, $req) {
        $mng->group_users->updateMany(
            ['GroupID' => $req->groupId],
            [ '$set' => [ 'eName' => $req->eName]]
        );        
        return ['status' => 'Ok'];
    }

    static public function addSafe($mng, $req) {
        Utils::err('req');
        Utils::err($req);
        $role = isset($req->role) ? $req->role :  "can view";
        $mng->safe_groups->insertOne(
            [
            'SafeID' => $req->SafeID, 
            'GroupID' => $req->groupId,
            'eName' => $req->eName,
            'role' => $role,
            'version' => $req->version,
            'encrypted_key' => $req->encrypted_key]
        );
        
        return ['status' => 'Ok'];
    }

    static public function removeSafe($mng, $req) {
        Utils::err('req');
        Utils::err($req);
        $mng->safe_groups->deleteOne(
            [
                'SafeID' => $req->safeId, 
                'GroupID' => $req->groupId,
            ]
        );
        
        return ['status' => 'Ok'];
    }

    static public function delete($mng, $req) {
        $mng->safe_groups->deleteMany(
            [
                'GroupID' => $req->groupId,
            ]
        );
        $mng->group_users->deleteMany(
            [
                'GroupID' => $req->groupId,
            ]
        );
        return ['status' => 'Ok'];
    }

    static public function setSafeRole($mng, $req) {
        $mng->safe_groups->updateOne(
            [
                'SafeID' => $req->safeId, 
                'GroupID' => $req->groupId,
            ],
            [ '$set' => [ 'role' => $req->role]]
        );
        return ['status' => 'Ok'];
    }

    static public function create($mng, $group, $UserID) {

        // check if group aleady exists


        // sanity check 
        if(property_exists($group, "eName")) {
            $sanityCheck = Utils::eNameSanityCheck($group->eName);
            if( $sanityCheck != "Ok") {
                return $sanityCheck;
            }
        } else {
            return "Internal server error";
        }
               
        if(!property_exists($group,"aes_key") || (strlen($group->aes_key) > 1000)) {
            Utils::err('create group 77');
            return "Internal server error";
        }
        

        $GroupID = (string)new \MongoDB\BSON\ObjectId();
        
        if($group->version == 3) {
            $mng->group_users->insertOne(
                ['GroupID' => $GroupID, 
                'UserID' => $UserID,
                'eName' => $group->eName,
                'version' => $group->version,
                'role' => self::ROLE_ADMINISTRATOR,
                'encrypted_key_CSE' => $group->aes_key]
            );
        } else {
            Utils::err('create group 95');
            return "Internal server error";
        }

        Utils::log('user ' . $UserID . ' activity group created');
        return array("status" =>"Ok");
    }
}
