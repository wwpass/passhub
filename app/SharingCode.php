<?php

/**
 * SharingCode.php
 *
 * PHP version 7
 *
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class SharingCode
{

    public static function getSharingStatus($mng, $UserID) {
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
    
    public static function accept($mng, $UserID, $inviteCode, $UserName, $SafeName )
    {
        $cursor = $mng->sharing_codes->find(['code' => $inviteCode]);

        $a = $cursor->toArray();
        $cnt = count($a);
        if ($cnt == 0) {
            return "no such sharing code $inviteCode";
        } elseif ($cnt > 1) {
            Utils::err("multiple records for sharing code $inviteCode");
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
            Utils::err("Error safe 536");
            return "Internal Error 536";
        }
        if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
            $role = User::ROLE_ADMINISTRATOR;
        } else {
            $role = USer::ROLE_READONLY;
        }
        $result = $mng->safe_users->insertOne(['UserID' => $UserID, 'SafeID' => $SafeID, 'UserName' => $UserName, 'SafeName' => $SafeName, 'role' => $role, 'encrypted_key' => null, 'encrypted_key_CSE' => null]);
        if ($result->getInsertedCount() != 1 ) {
            Utils::err("Error safe 545");
            return "Internal Error 545";
        }
        // COMMIT
        Utils::log('user ' . $UserID . ' activity invitation accepted');
        return array("status" => "Ok", "vault" => $SafeID);
    }

    public static function getSharingCode($mng, $UserID, $SafeID, $UserName = null) {

        if (!ctype_xdigit($UserID) || !ctype_xdigit($SafeID)) {
            return "Bad arguments";
        }
        $user = new User($mng, $UserID);
        if (!$user->isAdmin($SafeID)) {
            Utils::err("get_sharing_code rights violation: UserID '$UserID' SafeID '$SafeID'");
            $result = "You need to be ";
            $result .= "a safe owner (administrator) to share the safe.";
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
                    Utils::err("Error setting name UserID $UserID SafeID $safeID new name " .  $UserName);
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
            Utils::err("Error safe 497");
            return "Internal Error 497";
        }
        Utils::log('user ' . $UserID . ' activity get sharing code');
        return [
            "status" => "Ok", 
            "code" => $v,
            "sharingCodeTTL" => SHARING_CODE_TTL,
            "ownerName" => $row->UserName
        ];
    }
    
}
