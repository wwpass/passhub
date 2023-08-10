<?php

/**
 *
 * Folder.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Vladimir Korshunov <v.korshunov@wwpass.com>
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2017-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

namespace PassHub;

class Folder 
{
    private static function deleteNotEmpty($mng, $SafeID, $folderID) {
        $deleted  = ['items' => 0, 'folders' => 0];

        $filter = ['parent' => $folderID];
        $mng_cursor = $mng->safe_folders->find($filter);
        $folders = $mng_cursor->ToArray();
        foreach ($folders as $folder) {
            $result = self::deleteNotEmpty($mng, $SafeID, (string)$folder->_id);
            $deleted['items'] += $result['items'];
            $deleted['folders'] += $result['folders'];
        }
        $result = $mng->safe_items->deleteMany(['SafeID' => $SafeID, 'folder' => $folderID]);
        $deleted['items'] += $result->getDeletedCount();

        $result = $mng->safe_folders->deleteMany(
            ['SafeID' => $SafeID, '_id' => new \MongoDB\BSON\ObjectID($folderID)]
        );
        // try-catch
        if ($result->getDeletedCount() != 1) {
            Utils::err(print_r($result, true));
            return "Internal error";
        }
        $deleted['folders'] +=1;
        return $deleted;
    }

    static function eNameSanityCheck($eName) {
        if(strlen($eName->data) > 1000) {
            return "folder name too  long";
        }
        if(strlen($eName->tag) > 1000) {
            Utils::err('folder name 402');
            return "Internal server error";
        }
        if(strlen($eName->iv) > 1000) {
            Utils::err('folder name 406');
            return "Internal server error";
        }
        return "Ok";
    }

    public static function get_parent_safe($mng, $folderID) {
        $filter = ['_id' => new \MongoDB\BSON\ObjectID($folderID)];
        $cursor = $mng->safe_folders->find($filter);
        $records = $cursor->ToArray();
        if (count($records) != 1) {        
            return "folder error 66";
        }
        Utils::err("safe_folder record");
        Utils::err($records[0]);
        return $records[0]['SafeID'];
    }

    public static function move($mng, $UserID, $req) {
        
        // isset($req->folder) && isset($req->dstSafe) && isset($req->dstFolder)
        // get user edit rights for dstSafe 

        $user = new User($mng, $UserID);
        if (!$user->canWrite($req->dstSafe)) {
            return "no dst write";
        }
        $srcSafeID = self::get_parent_safe($mng, $req->folder->_id);

        if (!$user->canWrite($srcSafeID)) {
            return "no src write";
        }

        $result = self::import($mng, $UserID, $req->dstSafe, $req->dstFolder, $req->folder);
        Utils::err("import result");
        Utils::err($result);
        if($result['status'] == "Ok") {
             $result = self::deleteNotEmpty($mng, $srcSafeID, $req->folder->_id);
             Utils::err("deleteNotEmpty result");
             Utils::err($result);
             return ['status' => 'Ok'];
        }
        return $result;
    }

    // TODO add Utils::err
    public static function operation($mng, $UserID, $data) {

        if (!isset($data->SafeID)) {
            Utils::err("folder_ops SafeID not defined");
            return "internal error";
        }
        if (!isset($data->folderID)) {
            Utils::err("folder_ops folderID not defined");
            return "internal error";
        }
        if (!isset($data->operation)) {
            Utils::err("folder_ops operation not defined");
            return "internal error";
        }

        $SafeID = $data->SafeID;
        $folderID = $data->folderID;

        if (!ctype_xdigit((string)$UserID) || !ctype_xdigit((string)$SafeID)
            || !ctype_xdigit((string)$folderID)
        ) {
            Utils::err("folder_ops UserID " . $UserID . " SafeID " . $SafeID . " folderID " . $folderID);
            return "internal error";
        }
        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            Utils::err("error itm 238");
            return "You do not have enough rights";
        }

        // check if folder exists in SafeID
        if ($folderID != 0) {
            $filter = [ 'SafeID' => $SafeID, '_id' => new \MongoDB\BSON\ObjectID($folderID)];
            $mng_res = $mng->safe_folders->find($filter);

            if (count($mng_res->ToArray()) != 1) {
                Utils::err("itm 261 folder " . $folderID . " SafeID " . $SafeID);
                return "internal error";
            }
        }

        if ($data->operation == 'delete') {
            if ($folderID == 0) {
                Utils::err("delete folder 0");
                return "internal error";
            }
            // check if empty
            $mng_cursor = $mng->safe_folders->find(['parent' => $folderID]);
            if (count($mng_cursor->ToArray()) > 0) {
                return "not empty";
            }
            $mng_cursor = $mng->safe_items->find(['folder' => $folderID]);
            if (count($mng_cursor->ToArray()) > 0) {
                return "not empty";
            }
            $result = $mng->safe_folders->deleteMany(
                ['SafeID' => $SafeID, '_id' => new \MongoDB\BSON\ObjectID($folderID)]
            );
            // try -catch
            if ($result->getDeletedCount() != 1) {
                Utils::err(print_r($result, true));
                return "Internal error";
            }
            Utils::log('user ' . $UserID . ' activity empty folder deleted');
            return "Ok";
        }

        if ($data->operation == 'delete_not_empty') {
            if ($folderID == 0) {
                Utils::err("delete folder 0");
                return "internal error";
            }
            $deleted = self::deleteNotEmpty($mng, $SafeID, $folderID);
            Utils::log(
                'user ' . $UserID . ' activity folder deleted with '
                . $deleted['items'] . ' items and '
                . $deleted['folders'] . ' subfolders'
            );
            return ['status' => 'Ok', 'items' => $deleted['items'], 'folders' => $deleted['folders']];
        }

        if (($data->operation == 'create') || ($data->operation == 'rename')) {
            if (!isset($data->name)) {
                Utils::err("folder_ops name not defined");
                return "internal error";
            }
            $js = json_decode($data->name);
            if ($js == null) {
                Utils::err("itm 261 cannot decompose json name ");
                return "internal error";
            }
            if ((isset($js->version) && ($js->version ==3) && isset($js->iv) && isset($js->data) && isset($js->tag)) == false ) {
                Utils::err("itm 265 name json structure " . $data->name);
                return "internal error";
            }

            $sanityCheck = self::eNameSanityCheck($js);
            if( $sanityCheck != "Ok") {
                return $sanityCheck;
            }

            if ($data->operation == 'create') {
                $r = $mng->safe_folders->insertOne(
                    ['SafeID' => $SafeID, 
                    'iv' => $js->iv, 
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'parent' => $folderID,
                    'lastModified' =>Date('c'),
                    'version' => 3]
                );
                if ($r->getInsertedCount() == 1) {
                    $folder_id = $r->getInsertedId();
                    Utils::log('user ' . $UserID . ' activity folder ' . $data->operation);
                    $user->setCurrentSafe($folder_id);
                    return ["status" => "Ok", "id" => (string)$folder_id];
                }
            } else {
                $r = $mng->safe_folders->updateOne(
                    ['SafeID' => $SafeID, '_id' => new \MongoDB\BSON\ObjectID($folderID)], 
                    ['$set' => 
                    ['iv' => $js->iv,
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'lastModified' =>Date('c'),
                    'version' => 3]]
                );
                if ($r->getModifiedCount() == 1) {
                    $folder_id = $r->getUpsertedId();
                    Utils::log('user ' . $UserID . ' activity folder ' . $data->operation);
                    return ["status" => "Ok", "id" => (string)$folder_id];
                }
            }

            // try-catch
            Utils::err(print_r($result, true));
            return "Internal error";
        }
        Utils::err("folder_ops operation " . $data->operation);
        return 'internal error';
    }

    public static function import($mng, $UserID, $SafeID, $parent, $folder) {
        $result = self::operation($mng, $UserID, (object)['SafeID' => $SafeID, 'folderID' => $parent, 'operation' => 'create', 'name' => $folder->name]);
        if ($result['status'] == 'Ok') {
            $id = $result['id'];
            if (isset($folder->items) && (count($folder->items) > 0)) {
                Item::create_items_cse($mng, $UserID, $SafeID, $folder->items, $id);
            }
            if (isset($folder->folders)) {
                foreach ($folder->folders as $child) {
                    $r = self::import($mng, $UserID, $SafeID, $id, $child);
                    if ($r['status'] != 'Ok') {
                        return $r;
                    }
                }
            }
        }
        return $result;
    }

    public static function merge($mng, $UserID, $SafeID, $parent, $folder) {
        Utils::err('Hello1');
        Utils::err(print_r($folder, true));

        if (isset($folder->items) && (count($folder->items) > 0)) {
            Utils::err('Hello2');
            Item::create_items_cse($mng, $UserID, $SafeID, $folder->items, $folder->_id);
        }
        if (isset($folder->folders)) {
            foreach ($folder->folders as $child) {

                if (isset($child->_id)) {
                    self::merge($mng, $UserID, $SafeID, $folder->_id, $child);
                    // look inside
                } else {
                    $r = self::import($mng, $UserID, $SafeID, $folder->_id, $child);
                    if ($r['status'] != 'Ok') {
                        return $r;
                    }
                }
            }
        }
    }

    static function get_folder_list_cse($mng, $UserID, $SafeID) {

        $UserID = (string)$UserID;
        $SafeID = (string)$SafeID;

        $cursor = $mng->safe_folders->find(['SafeID' => $SafeID]);
        $user = new User($mng, $UserID);
        $role = $user->getUserRole($SafeID);
        if ($role) {   //read is enough for a while
            $folder_array = $cursor->toArray();
            foreach ($folder_array as $folder) {
                $folder->_id= (string)$folder->_id;
            }
        } else {
            $folder_array = array();
            Utils::err("error itm 266, no role: UserID " . $UserID . " SafeID" . $SafeID);
        }
        return $folder_array;
    }
}
