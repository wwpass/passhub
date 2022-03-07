<?php

/**
 * Survey.php
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

class Survey
{
    private static function userCreationTime($UserID) {
        if (strlen($UserID) == 24) {
            $date = substr($UserID, 0, 8);
            return intval($date, 16);
        }
        return 0;
    }

    public static function showStatus($user) {

        $mng = $user->mng;
        $UserID = $user->UserID;
       
        if ((time() - self::userCreationTime($UserID)) < 60*60*24*30) {
                Utils::err("survey: no show, new user " . (time() - self::userCreationTime($UserID)));
            return false;
        }
    
        $cursor = $mng->survey->find(['UserID' => $UserID]);
        $result = $cursor->toArray();
        if (count($result) == 0) {
            Survey::shown($mng, $UserID);
            return true;
        }

        if ($result[0]['status'] == 'shown') {
            if ((time() - strtotime($result[0]['modified'])) > 60*60*24*7) {
                Survey::shown($mng, $UserID);
                return true;
            } else {
                Utils::err('survey: too early');
            }
        }
        if ($result[0]['status'] == 'sent') {
            if ((time() - strtotime($result[0]['modified'])) > 60*60*24*180) {
                Survey::shown($mng, $UserID);
                return true;
            } else {
                Utils::err('survey: already received '  . (int)((time() - strtotime($result[0]['modified']))/60/60/24) . ' days ago');
            }
        }
        return false;
    } 

    public static function shown($mng, $UserID) {
        Utils::err('survey shown');
        $mng->survey->updateOne(
            ['UserID' => $UserID], 
            ['$set' => ['status' => 'shown', 'modified' => date('c')]],  
            ['upsert' => true]
        );
    }

    public static function sent($mng, $UserID) {
        Utils::err('survey sent');
        $cursor = $mng->survey->updateOne(
            ['UserID' => $UserID], 
            ['$set' => ['status' => 'sent', 'modified' => date('c')]],
            ['upsert' => true]
        );
    }
}

