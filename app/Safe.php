<?php

/**
 * Safe.php
 *
 * PHP version 7
 *
 * modified code of https://github.com/altmetric/mongo-session-handler
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class Safe
{
    public $id;
    public $name;
    private $user_id;
    public $user_name;
    public $encrypted_key;
    public $encrypted_key_CSE;
    public $confirm_req;
    public $user_role;
    public $user_count;

    function __construct($row) {
/*
        $vars=is_object($row)?get_object_vars($row):$row;
        if(!is_array($row)) throw Exception('no props to import into the object!');
        foreach ($vars as $key => $value) {
            $this->$key = $value;
        }
*/

        if(property_exists($row,"version") && ($row->version == 3)) {
            $this->version = $row->version;
            $this->eName = $row->eName;
        } else {
            $this->name = $row->SafeName;
            if ($this->name == '') {
                $this->name =  "My First Safe";  // TODO: prevent user to name safe this way
            }
        }

        // $this->name = $row->SafeName;
        $this->id = $row->SafeID;

        $this->user_id = $row->UserID;
        $this->user_name = $row->UserName;
        $this->user_role = $row->role;
        $this->encrypted_key = isset($row->encrypted_key) ? $row->encrypted_key:null;
        $this->encrypted_key_CSE = isset($row->encrypted_key_CSE) ? $row->encrypted_key_CSE:null;
        $this->confirm_req = 0;
        $this->user_count = 1;
    }

    function isConfirmed() {
        if (($this->encrypted_key_CSE != null) || ($this->encrypted_key != null) ) {
            return true;
        }
        return false;
    }


    public static function getSafes($mng, $UserID) {

        $pipeline = [
            ['$match' => [ 'UserID' => $UserID]],
            ['$lookup'=> [
                              'from' => 'safe_items',
                              'as' => 'items',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]]
        ];
        $cursor = $mng->safe_users->aggregate($pipeline);
        $safe_items = $cursor->toArray();

        $pipeline = [
            ['$match' => [ 'UserID' => $UserID]],
            ['$lookup'=> [
                              'from' => 'safe_folders',
                              'as' => 'folders',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]]
        ];
        $cursor = $mng->safe_users->aggregate($pipeline);

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
            ['$match' => [ 'UserID' => $UserID]],
            ['$lookup'=> [
                              'from' => 'safe_users',
                              'as' => 'safes',
                              'localField' => 'SafeID',
                              'foreignField' => 'SafeID'
                  ]],
#            $count: "passing_scores" 
        ];

        $cursor = $mng->safe_users->aggregate($pipeline);

        $safe_users = $cursor->toArray();

        foreach($safe_users as $s) {
            $safe_array1[$s->SafeID]['users'] = count($s->safes);
        }

        
#       Utils::err('----------');
#       Utils::err("safe_array1");
#       Utils::err($safe_array1 );
#        Utils::err('----------');
#        Utils::err(json_encode($safe_array1, JSON_PRETTY_PRINT));
#        Utils::err('=======================');


#        Utils::err('storage');
#        Utils::err($storage_used1);
#        Utils::err('total recordse');
#        Utils::err($total_records1);

#        $safe_array1;

        $result = [];

        foreach ($safe_array1 as $id => $safe) {
            array_push($result, $safe);
        }

        return $result;

    }
}
