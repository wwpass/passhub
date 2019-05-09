<?php

function delete_user($mng) {
    $id = $_POST['id'];
    $mongo_user_id =  (strlen($id) != 24)? $id : new MongoDB\BSON\ObjectID($id);

    if ($_POST['email']) {
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            passhub_err("usr del 67 " . $_POST['email']);
            return "internal error del 67";
        }
        $result = $mng->mail_invitations->deleteMany(['email' => $_POST['email']]);
        // passhub_err(print_r($result, true));

        $cursor = $mng->users->find(["email" => $_POST['email']], ['projection' => ["_id" => true, "PUID" => true, "lastSeen" => true, "email" => true]]);
    } else if ($_POST['id']) {
        $surcor = $mng->users->find(["_id" => $mongo_user_id], ['projection' => ["_id" => true, "PUID" => true, "lastSeen" => true, "email" => true]]);
    } else {
        passhub_err("usr del 74");
        return "internal error del 74";
    }
    $user_array = $cursor->toArray();
    if (count($user_array) !=1) {
        passhub_err("usr del 84");
        return "internal error del 84";
    }
    $user = $user_array[0];
    if (!isset($user->email)) {
        $user->email = "";
    }
    if (((string)$user->_id != $_POST['id']) || ((string)$user->email != $_POST['email'])) {
        passhub_err("usr del 92");
        return "internal error del 92";
    }

    if (!isset($user->PUID)) {

        $cursor = $mng->puids->find(["UserID" => $id]);
        $puids = $cursor->toArray();
        if (count($puids) > 0) {
            $user->PUID = $puids[0]->PUID;
            $result = $mng->puids->deleteMany(['UserID' => $id]);
            if ($result->getDeletedCount() != 1) {
                passhub_err(print_r($result, true));
                return "Internal error 107";
            }
        } else {
            passhub_err("del user 111");
            return "Internal error 111";
        }
    }
    $result = $mng->reg_codes->deleteMany(['PUID' => $user->PUID]);
    /*    
    if (count($result->getWriteErrors()) != 0) {
        passhub_err(print_r($result, true));
        return "Internal error 100";
    }
    */
    $result = $mng->users->deleteMany(['_id' => $mongo_user_id]);
    if ($result->getDeletedCount() != 1) {
        passhub_err(print_r($result, true));
        return "Internal error 108";
    }
    $result = $mng->safe_users->deleteMany(['UserID' => $id]);
/*    
    if (count($result->getWriteErrors()) != 0) {
        passhub_err(print_r($result, true));
        return "Internal error 116";
    }
*/    
    $removed_safe_user_records = $result->getDeletedCount();
    passhub_err("removed " . $removed_safe_user_records . " records in safe_users");
    return ['status' => "Ok", "access" => $removed_safe_user_records];
}

function edit_user($mng, $id) {
    $mongo_user_id =  (strlen($id) != 24)? $id : new MongoDB\BSON\ObjectID($id);

    $cursor = $mng->users->find(["_id" => $mongo_user_id], ['projection' => ["_id" => true, "site_admin" => true]]);
    $user_array = $cursor->toArray();
    if (count($user_array) !=1) {
        passhub_err("usr ed 84");
        return "internal error ed 84";
    }
    $user = $user_array[0];
    if (isset($user->site_admin) && ($user->site_admin == true)) {
        $mng->users->updateOne(['_id' => $mongo_user_id], ['$set' =>['site_admin' => false]]);
    } else {
        $mng->users->updateOne(['_id' => $mongo_user_id], ['$set' =>['site_admin' => true]]);
    }
    return ['status' => "Ok"];
}

function getUserArray($mng, $UserID) {
    $cursor = $mng->users->find([], ['projection' => ["_id" => true, "lastSeen" => true, "email" => true, "site_admin" =>true]]);
    $user_array = $cursor->toArray();

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

    if ($admins_found === false) {
        $id =  (strlen($UserID) != 24)? $UserID : new MongoDB\BSON\ObjectID($UserID);
        $mng->users->updateOne(['_id' => $id], ['$set' =>['site_admin' => true]]);
        $cursor = $mng->users->find([], ['projection' => ["_id" => true, "lastSeen" => true, "email" => true, "site_admin" =>true]]);
        $user_array = $cursor->toArray();
    } else if ($i_am_admin === false) {
        error_page("not enough rights"); // TODO exception
        return [];
    }
    return $user_array;
}

function getSafeUserArray($mng) {
    $cursor = $mng->safe_users->find([], ['projection' =>["SafeID" => true, "UserID" => true]]);
    return $cursor->toArray();
}

function whiteMailList($mng) {
    $cursor = $mng->mail_invitations->find([], ['projection' => ['_id' => false, 'email' => true]]);
    $mail_array = $cursor->toArray();
    header('Content-type: application/json');
    echo json_encode(["status" =>"Ok", 'mail_array' => $mail_array]);
}

function addWhiteMailList($mng, $email) {
    $cursor = $mng->mail_invitations->find(['email' => $email]);
    foreach ( $cursor as $row) {
        // already invited
        header('Content-type: application/json');
        echo json_encode(['status' => 'already in the list']);
        return; 
    }
    $mng->mail_invitations->insertOne(['email' => $email]);
    whiteMailList($mng);
}

function removeWhiteMailList($mng, $email) {
    $mng->mail_invitations->deleteMany(['email' => $email]);
    whiteMailList($mng);
}

function is_invited($mng, $email) {

    $cursor = $mng->mail_invitations->find(['email' => strtolower($email)]);

    foreach ( $cursor as $row) {
        return true;
    }
    return false;
}

function getRegistrationCode($mng, $PUID, $email) {

    $cursor = $mng->users->find(['email' => $email]);
    $codes = $cursor->toArray();
    $num_users = count($codes);
    if ($num_users  != 0) {
        return ["status" => "This e-mail address is already in use. Please provide another e-mail address."];
    }
    $v1 = random_int(0, 9999);
    $v2 = random_int(0, 9999);
    $v3 = random_int(0, 9999);
    $v4 = random_int(0, 9999);

    $v = sprintf("%04d-%04d-%04d-%04d", $v1, $v2, $v3, $v4);
    $result = $mng->reg_codes->insertOne(['PUID' => $PUID, 'code' => $v, 'verified' => false, 'created' => Date('c'), 'email' => $email]);
    if ($result->getInsertedCount() != 1 ) {
        passhub_err("Error user 294");
        return ["status" => "Internal Error 294"];
    }
    return array("status" => "Ok", "code" => $v);
}
