<?php

/**
 *
 * file.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2017-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


if (defined('GOOGLE_CREDS')) {
    require_once 'src/google_drive_files.php';
}

function local_fs_upload($file_id, $content) {

    $fname = FILE_DIR . '/' . $file_id;
    if ($fh = fopen($fname, 'w')) {
        fwrite($fh, $content);
        fclose($fh);
        return ["status" => "Ok"];
    }
    return ["status" => "Error 24: file"];
}

function local_fs_download($file_id) {

    $fname = FILE_DIR . '/' . $file_id;
    if ($fh = fopen($fname, 'r')) {
        $data = fread($fh, filesize($fname));
        fclose($fh);
        return ["status" => "Ok", "data" => $data];
    }
    return ["status" => "Fail"];
}

function local_fs_delete($file_id) {
    $fname = FILE_DIR . '/' . $file_id;
    $result = unlink($fname);
    if ($result) {
        return ["status" => "Ok"];
    }
    return ["status" => "Fail"];
}

function file_upload($file_id, $data) {

    if (defined('FILE_DIR') && !defined('GOOGLE_CREDS')) {
        return local_fs_upload($file_id, $data);
    } else if (!defined('FILE_DIR') && defined('GOOGLE_CREDS')) {
        return google_drive_upload($file_id, $data);
    }
    passhub_err("Error: no file storage configured");
    error_page("Site is misconfigured. Consult system administrator");
}

function file_download($file_id) {

    if (defined('FILE_DIR') && !defined('GOOGLE_CREDS')) {
        return local_fs_download($file_id);
    } else if (!defined('FILE_DIR') && defined('GOOGLE_CREDS')) {
        return google_drive_download($file_id);
    }
    passhub_err("Error: no file storage configured");
    error_page("Site is misconfigured. Consult system administrator");
}

function file_delete($file_id) {

    if (defined('FILE_DIR') && !defined('GOOGLE_CREDS')) {
        return local_fs_delete($file_id);
    } else if (!defined('FILE_DIR') && defined('GOOGLE_CREDS')) {
        return google_drive_delete($file_id);
    }
    passhub_err("Error: no file storage configured");
    error_page("Site is misconfigured. Consult system administrator");
}

function create_file_item_cse($mng, $UserID, $SafeID, $folder, $meta, $file) {

    if (can_write($mng, $UserID, $SafeID) == false) {
        passhub_err("error file 20 role = '$role'  UserID " . $UserID . " SafeID " . $SafeID);
        return "Not enough rights";
    }

    $file_id = new MongoDB\BSON\ObjectID();
    $file_id = (string)$file_id;


    $meta_js = json_decode($meta);
    $file_js = json_decode($file);

    $data = base64_decode($file_js->data, true);
    // $data = $file_js->data;

    if (!defined('MAX_FILE_SIZE')) {
        $max_file_size = 5;
    } else {
        $max_file_size = MAX_FILE_SIZE;
    }
    passhub_err("upload file size " . strlen($data));
    if (strlen($data) > $max_file_size*1024*1024) {
        return ['status' => "File size exceeds " . $max_file_size . " MBytes"];
    }

    if (defined('MAX_STORAGE')) {
        $result = getAcessibleStorage($mng, $UserID);
        if ($result['status'] == "Ok") {
            if ($result['total'] +  strlen($data) > MAX_STORAGE*1024*1024) {
                return ['status' => "No room to store file, used " . (int)($result['total']/1024/1024) . " out of ". MAX_STORAGE . " MBytes"];
            }
        } else {
            return $result;
        }
    }

    $result = file_upload($file_id, $data);
    if ($result['status'] != "Ok") {
        passhub_err($result['status']);
        return $result;
    }

    if ($meta_js !== null) {
        if (isset($meta_js->version) && ($meta_js->version ==3) && isset($meta_js->iv) && isset($meta_js->data) && isset($meta_js->tag)) {
            $result = $mng->safe_items->insertOne(
                ['SafeID' => $SafeID,
                'iv' => $meta_js->iv,
                'data' => $meta_js->data,
                'tag' => $meta_js->tag,
                'folder' => $folder,
                'lastModified' =>Date('c'),
                'version' => 3,
                'file' => ['id' => (string)$file_id,
                    'size' => strlen($data),
                    'key' =>$file_js->key,
                    'iv' => $file_js->iv,
                    'tag' => $file_js->tag]
                ]        
            );
            if ($result->getInsertedCount() == 1) {
                passhub_log('user ' . $UserID . ' activity file upload ' . strlen($data) . ' bytes');
                return ["status" => "Ok"];
            }
        } else {
            passhub_err(print_r($js, true));
            exit();
        }
    } else {  //version 2: data are fields hex encrypted, should not happen
        passhub_err("wrong item format");
    }
    // ?????????????????????????!!!!!!!!!!!!!!!!!!!!!!!?????????????????????
    passhub_err(print_r($result, true));
    exit();
}


// TODO add passhub_err
function file_ops($mng, $UserID, $data) {

    //    passhub_err(print_r($data,true));
    if (!isset($data['SafeID'])) {
        passhub_err("file_ops SafeID not defined");
        return "internal error file 60";
    }

    if (!isset($data['itemId'])) {
        passhub_err("file_ops itemId not defined");
        return "internal error file 65";
    }

    if (!isset($data['operation'])) {
        passhub_err("file_ops operation not defined");
        return "internal error file 70";
    }

    $SafeID = $data['SafeID'];

    if (!ctype_xdigit((string)$UserID) || !ctype_xdigit((string)$SafeID)) {
        passhub_err("file_ops UserID " . $UserID . " SafeID " . $SafeID . " itemId " . $itemId);
        return "internal error file 77";
    }

    if (can_read($mng, $UserID, $SafeID) == false) {
        passhub_err("error itm 335 role = '$role'");
        return "Internal server error 335";
    }


    $itemId = $data['itemId'];
    $id =  (strlen($itemId) != 24)? $itemId : new MongoDB\BSON\ObjectID($itemId);

    if ($data['operation'] == 'download') {
        $cursor = $mng->safe_items->find(['_id' => $id, 'SafeID' => $SafeID]);

        $a = $cursor->toArray();
        if (count($a) != 1) {
            passhub_err("error itm 349, entryID $itemId count is " . count($a));
            return  "Internal server error itm 324";
        }
        $row = $a[0];

        $file_id = $row->file->id;

        $result = file_download((string)$file_id);
        if ($result['status'] != "Ok") {
            return $result;
        }
        $data = base64_encode($result['data']);
        passhub_log('user ' . $UserID . ' activity file download ' . strlen($result['data']) . ' bytes');
        return ['status' => "Ok", 'filename' => ['iv' => $row->iv, 'data' => $row->data, 'tag' => $row->tag],
            'file' => ['key' => $row->file->key, 'iv' => $row->file->iv, 'data' => $data, 'tag' => $row->file->tag]];
    } else if ($data['operation'] == 'rename') {
        if (can_write($mng, $UserID, $SafeID) == false) {
            passhub_err("error file 20 role = '$role'  UserID " . $UserID . " SafeID " . $SafeID);
            return "Not enough rights";
        }
    
        $cursor = $mng->safe_items->find(['_id' => $id, 'SafeID' => $SafeID]);

        $a = $cursor->toArray();
        if (count($a) != 1) {
            passhub_err("error file 216, entryID $itemId count is " . count($a));
            return  "Internal server error 216";
        }
        $row = $a[0];  // found

        $js = json_decode($data['newName']);
        if ($js == null) {
            passhub_err(print_r($js, true));
            return ['status' => 'internal error 225'];
        }
        if (isset($js->version) && ($js->version == 3) 
            && isset($js->iv) 
            && isset($js->data) 
            && isset($js->tag)
        ) {
            $result = $mng->safe_items->updateMany(
                ['_id' => $id],
                ['$set' => ['iv' => $js->iv, 'data' => $js->data, 'tag' => $js->tag]]
            );
        }
        if ($result->getModifiedCount() == 1) {
            passhub_log('user ' . $UserID . ' activity file rename');
            return "Ok";
        } else {
            passhub_err("error file 233 " . print_r($result, true));
            return  "Internal server error 233";
        }
    }
    passhub_err("unknown file op " .  $data['operation']);
    return  "Internal server error 239";
}
//*************************************************
