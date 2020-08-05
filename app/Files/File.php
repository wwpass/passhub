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


namespace PassHub\Files;

use \PassHub\User;
use \PassHub\Utils;

abstract class File
{

    abstract protected function upload($content);
    abstract protected function download();
    abstract public function delete();

    protected $name;

    protected function __construct($fname) {
        $this->name = $fname;
    }

    public static function newFile($fname) {
        if (defined('FILE_DIR')) {
            return new FileLocal($fname);
        } elseif (defined('GOOGLE_CREDS')) {
            return new FileGDrive($fname);
        } else if (defined('S3_CONFIG')) {
            return new FileS3($fname);
        }
        Utils::err("Error: no file storage configured");
        throw new \Exception('Site is misconfigured. Consult system administrator.');
    }

    public static function create($mng, $UserID, $SafeID, $folder, $meta, $file) {

        $user = new User($mng, $UserID);

        if ($user->canWrite($SafeID) == false) {
            Utils::err("error file 20 role = '$role'  UserID " . $UserID . " SafeID " . $SafeID);
            return "Sorry you do not have editor rights for this safe";
        }

        $file_js = json_decode($file);
        $data = base64_decode($file_js->data, true);

        if (!defined('MAX_FILE_SIZE')) {
            $max_file_size = 5 * 1024 * 1024;
        } else {
            $max_file_size = MAX_FILE_SIZE;
        }
        Utils::err("upload file size " . strlen($data));
        if (strlen($data) > $max_file_size) {
            return ['status' => "File size exceeds " . Utils::humanReadableFileSize($max_file_size)];
        }

        if (defined('MAX_STORAGE_PER_USER')) {
            $result = $user->account();
            if ($result['status'] == "Ok") {
                if ($result['used'] +  strlen($data) > $result['maxStorage']) {
                    return ['status' => "No room to store the file, used " 
                    . Utils::humanReadableFileSize($result['used']) . " out of ". Utils::humanReadableFileSize($result['maxStorage'])];
                }
            } else {
                return $result;
            }
        }

        $file_id = new \MongoDB\BSON\ObjectID();
        $file_id = (string)$file_id;

        $f = self::newFile($file_id);
        $result = $f->upload($data);
        if ($result['status'] != "Ok") {
            Utils::err($result['status']);
            return $result;
        }

        $meta_js = json_decode($meta);
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
                    Utils::log('user ' . $UserID . ' activity file upload ' . strlen($data) . ' bytes');
                    $firstID = (string) $result->getInsertedId();
                    return ["status" =>  "Ok", "firstID" => $firstID];
                }
            } else {
                Utils::err(print_r($js, true));
                exit();
            }
        } else {  //version 2: data are fields hex encrypted, should not happen
            Utils::err("wrong item format");
        }
        // ?????????????????????????!!!!!!!!!!!!!!!!!!!!!!!?????????????????????
        Utils::err(print_r($result, true));
        exit();
    }

    public static function operation($mng, $UserID, $data) {

        if (!isset($data['SafeID'])) {
            Utils::err("file_ops SafeID not defined");
            return "internal error file 60";
        }

        if (!isset($data['itemId'])) {
            Utils::err("file_ops itemId not defined");
            return "internal error file 65";
        }

        if (!isset($data['operation'])) {
            Utils::err("file_ops operation not defined");
            return "internal error file 70";
        }

        $SafeID = $data['SafeID'];

        if (!ctype_xdigit((string)$UserID) || !ctype_xdigit((string)$SafeID)) {
            Utils::err("file_ops UserID " . $UserID . " SafeID " . $SafeID . " itemId " . $itemId);
            return "internal error file 77";
        }
        $user = new User($mng, $UserID);

        $itemId = $data['itemId'];
        $id =  (strlen($itemId) != 24)? $itemId : new \MongoDB\BSON\ObjectID($itemId);

        if ($data['operation'] == 'download') {

            if ($user->canRead($SafeID) == false) {
                Utils::err("error itm 335 role = '$role'");
                return "Internal server error 335";
            }
    
            $cursor = $mng->safe_items->find(['_id' => $id, 'SafeID' => $SafeID]);

            $a = $cursor->toArray();
            if (count($a) != 1) {
                Utils::err("error itm 349, entryID $itemId count is " . count($a));
                return  "Internal server error itm 324";
            }
            $row = $a[0];

            $file_id = (string)$row->file->id;
            $f = self::newFile($file_id);    
            $result = $f->download();
            if ($result['status'] != "Ok") {
                return $result;
            }
            $data = base64_encode($result['data']);
            Utils::log('user ' . $UserID . ' activity file download ' . strlen($result['data']) . ' bytes');
            return ['status' => "Ok", 'filename' => ['iv' => $row->iv, 'data' => $row->data, 'tag' => $row->tag],
                'file' => ['key' => $row->file->key, 'iv' => $row->file->iv, 'data' => $data, 'tag' => $row->file->tag]];
        } else if ($data['operation'] == 'rename') {
            if ($user->canWrite($SafeID) == false) {
                Utils::err("error file 20 role = '$role'  UserID " . $UserID . " SafeID " . $SafeID);
                return "Not enough rights";
            }
        
            $cursor = $mng->safe_items->find(['_id' => $id, 'SafeID' => $SafeID]);

            $a = $cursor->toArray();
            if (count($a) != 1) {
                Utils::err("error file 216, entryID $itemId count is " . count($a));
                return  "Internal server error 216";
            }
            $row = $a[0];  // found

            $js = json_decode($data['newName']);
            if ($js == null) {
                Utils::err(print_r($js, true));
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
                Utils::log('user ' . $UserID . ' activity file rename');
                return "Ok";
            } else {
                Utils::err("error file 233 " . print_r($result, true));
                return  "Internal server error 233";
            }
        }
        Utils::err("unknown file op " .  $data['operation']);
        return  "Internal server error 239";
    }
}
