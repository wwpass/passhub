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

class GoPremium
{

    function __construct($user) {
        Utils::err('GoPremium construct');
        $this->user = $user;

    }

    private static function userCreationTime($UserID) {
        if (strlen($UserID) == 24) {
            $date = substr($UserID, 0, 8);
            return intval($date, 16);
        }
        return 0;
    }

    public function shown() {
        Utils::err('goPremium shown');
        $this->user->mng->goPremium->updateOne(
            ['UserID' => $this->user->UserID], 
            ['$set' => ['status' => 'shown', 'modified' => date('c')]],  
            ['upsert' => true]
        );
    }

    public function showStatus() {

        if($this->user->profile->plan != 'FREE') {
            Utils::err('goPremium not shown, plan not FREE');
            return false;
        }
        if ((time() - self::userCreationTime($this->user->UserID)) < 60*60*23) {
            Utils::err('goPremium not shown, new user');
            return false;
        }
        $cursor = $this->user->mng->goPremium->find(['UserID' => $this->user->UserID]);
        $result = $cursor->toArray();
        if (count($result) == 0) {
            $this->shown();
            return true;
        }

        if ($result[0]['status'] == 'shown') {
            if ((time() - strtotime($result[0]['modified'])) > 60*60*23) {
                $this->shown();
                return true;
            } else {
                Utils::err('goPremium not shown, already seen');
                return false;
            }
        }
        Utils::err('goPremium not shown, other');
        return false;
    } 

}

