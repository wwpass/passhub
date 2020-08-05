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
        $this->id = $row->SafeID;
        $this->name = $row->SafeName;
        if ($this->name == '') {
            $this->name =  "My First Safe";  // TODO: prevent user to name safe this way
        }
        $this->user_id = $row->UserID;
        $this->user_name = $row->UserName;
        $this->user_role = $row->role;
        // at least one of the two
        //SSE
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
}
