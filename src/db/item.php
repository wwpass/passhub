<?php

/**
 *
 * item.php
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

function cmp_items($a, $b) {
    return strcasecmp($a['title'], $b['title']);
}
//***************************************************************************

// TODO get_aes_key
// test non-empty args


function get_item_list_cse($mng, $UserID, $SafeID, $opt = ['no_files' => false]) {

    $UserID = (string)$UserID;
    $SafeID = (string)$SafeID;
    $role = get_user_role($mng, $UserID, $SafeID);
    if ($role) {   //read is enough for a while
        $filter = ['SafeID' => $SafeID];
        if ($opt['no_files'] === true) {
            $filter = ['SafeID' => $SafeID, 'file' => [ '$exists' => false]];
        }
        $cursor = $mng->safe_items->find($filter);
        $item_array =  $cursor->toArray();
        foreach ($item_array as $item) {
            $item->_id= (string)$item->_id;
        }
        return $item_array;
    }
    return array();
}


//******************************************************************

function get_item_safe($mng, $entryID)
{
    $id =  (strlen($entryID) != 24)? $entryID : new MongoDB\BSON\ObjectID($entryID);
    $cursor = $mng->safe_items->find(['_id' => $id]);

    $a = $cursor->toArray();

    if (count($a) != 1) {
        passhub_err("error itm 307, entryID $entryID count is " . count($a));
        echo "Internal server error itm 307";
        exit();
    }
    $row = $a[0];
    return $row->SafeID;
}

function create_items_cse($mng, $UserID, $SafeID, $items, $folder) {

    if (!is_array($items)) {
        $items = [$items];
    }
    if (can_write($mng, $UserID, $SafeID) == false) {
        passhub_err("error itm 360 UserID " . $UserID . " SafeID " . $SafeID);
        return "no rights";
    }

    $records = [];
    foreach ($items as $item) {
        $js = json_decode($item);
        if ($js !== null) {
            if (isset($js->version) && ($js->version ==3) && isset($js->iv) && isset($js->data) && isset($js->tag)) {
                $record = [];
                $record['SafeID'] = $SafeID;
                $js = (array)$js;
                $record = $record + $js;
                $record['folder'] = $folder;
                if (!isset($record['lastModified'])) {
                    $record['lastModified'] = Date('c');
                }
                array_push($records, $record);
            } else {
                passhub_err(print_r($js, true));
                exit();
            }
        } else {  //version 2: data are fields hex encrypted, should not happen
            passhub_err("wrong item format");
        }
    }
    try {
        $result = $mng->safe_items->insertMany($records);

        if ($result->getInsertedCount() == count($items)) {
            passhub_log('user ' . $UserID . ' activity ' .count($items) . ' item(s) created');
            $firstID = (string) $result->getInsertedIds()[0];
            return ["status" =>  "Ok", "firstID" => $firstID];
        }
        passhub_err(print_r($result, true));
        exit();

    } catch (Exception $e) {
        passhub_err(print_r($e, true));
        exit();
    }
}

function get_item_cse($mng, $UserID, $entryID) {

    $id =  (strlen($entryID) != 24)? $entryID : new MongoDB\BSON\ObjectID($entryID);
    $cursor = $mng->safe_items->find(['_id' => $id]);

    $a = $cursor->toArray();
    if (count($a) != 1) {
        passhub_err("error itm 324, entryID $entryID count is " . count($a));
        exit("Internal server error itm 324");
    }
    $row = $a[0];

    $SafeID = $row->SafeID;


    // multiple  db access?? TODO
    $role = get_user_role($mng, $UserID, $SafeID);
    //    $key = get_encrypted_aes_key($mng, $UserID, $SafeID);
    if ($role) {   //read is enough for a while
        return array("item" => $row,"role" => $role);
    }
    return false;
}

function update_item_cse($mng, $UserID, $SafeID, $entryID, $data) {

    // get SafeID and role

    if ($SafeID != get_item_safe($mng, $entryID)) {
        passhub_err("error itm 356: SafeID_requested = '$SafeID', SafeID_real = " . $row->SafeID);
        echo "Internal server error 356";
        exit();
    }

    $role = get_user_role($mng, $UserID, $SafeID);
    if (($role != ROLE_ADMINISTRATOR) && ($role != ROLE_EDITOR)) {
        passhub_err("error itm 363 role = '$role'");
        return "no rights";
    }

    $id =  (strlen($entryID) != 24)? $entryID : new MongoDB\BSON\ObjectID($entryID);

    $js = json_decode($data);
    if ($js !== null) {
        if (isset($js->version) && ($js->version ==3) && isset($js->iv) && isset($js->data) && isset($js->tag)) {
            $result = $mng->safe_items->updateOne(
                ['_id' => $id], 
                ['$set' => ['iv' => $js->iv,
                     'data' => $js->data,
                     'tag' => $js->tag,
                     'lastModified' =>Date('c'),
                     'version' => 3]]
            );
        } else {
            passhub_err(print_r($js, true));
            exit();
        }
    } else {  // version 2: data are fields hex encrypted, should not happen
        // $bulk->insert( ['SafeID' => $SafeID, 'data' => $data, 'lastModified' =>Date('c'), 'version' => 2]);
        $result = $mng->safe_items->updateOne(
            ['_id' => $id], 
            ['$set' => ['SafeID' => $SafeID,
            'data' => $data, 
            'lastModified' =>Date('c'), 
            'version' => 2]]
        );
    }
    // try-catch
    if ($result->getModifiedCount() == 1) {
        // readback to get folder: wish I had findOneAndUpdate
        // returns folder to show in the index page  

        $cursor = $mng->safe_items->find(['_id' => $id]);

        $a = $cursor->toArray();
        if (count($a) != 1) {
            passhub_err("error itm 238, entryID $entryID count is " . count($a));
            error_page("Internal server error itm 324");
        }
        $row = (array)$a[0];
        if (!array_key_exists("folder", $row)) {
            $row['folder'] = 0;
        }
        passhub_log('user ' . $UserID . ' activity item update');
        return ['status' => "Ok", 'item' => $row];
    }
    passhub_err(print_r($result, true));
    exit();
}

function delete_item($mng, $UserID, $declaredSafeID, $itemID) {

    $SafeID = get_item_safe($mng, $itemID);
    if ($SafeID != $declaredSafeID) {
        passhub_err("error 186 delete_item safe mismatch: real SafeID '$SafeID', requested '$declaredSafeID'");
        return "error 186";
    }
    if (can_write($mng, $UserID, $SafeID) == false) {
        passhub_err("error del_item rights user = '$UserID' safe = '$SafeID'");
        return "Error: You do not have enough rights to delete records";
    }

    $id =  (strlen($itemID) != 24)? $itemID : new MongoDB\BSON\ObjectID($itemID);

    //get item, check if there are files
    $cursor = $mng->safe_items->find(['_id' => $id]);

    $a = $cursor->toArray();
    if (count($a) != 1) {
        passhub_err("error itm 259, entryID $entryID count is " . count($a));
        exit("Internal server error itm 259");
    }
    $row = $a[0];
    if (property_exists($row, 'file')) {
        $delete_result = file_delete($row->file->id);
        if (!$delete_result) {
            passhub_err("delete file id " . $row->file->id . " Fail");
        }
    }
    try {
        $result = $mng->safe_items->deleteMany(['_id' => $id]);
    } catch (Exception $e) {

    }
    if ($result->getDeletedCount() == 1) {
        if (property_exists($row, 'file')) {
            passhub_log('user ' . $UserID . ' activity file delete');
        } else {
            passhub_log('user ' . $UserID . ' activity item delete');
        }
        return "Ok";
    }
    passhub_err(print_r($result, true));
    return "Internal error 427";
}

// TODO: preserve modification data
function move_item_cse($mng, $UserID, $sourceSafeID, $targetSafeID, $dst_folder, $entryID, $data, $operation) {

        /*    // TODO
        - if dst_folder exists and belongs to dst_safe
        - if the user has rights to write to dst_folder
        - if the user has rights to access source folder
        */

    if ($operation == "move") {
        $id =  (strlen($entryID) != 24)? $entryID : new MongoDB\BSON\ObjectID($entryID);
        $js = json_decode($data);
        if ($js !== null) {
            if (isset($js->version) 
                && ($js->version ==3) 
                && isset($js->iv) 
                && isset($js->data) 
                && isset($js->tag)
            ) {
                if (isset($js->note)) {
                    $result = $mng->safe_items->updateMany(
                        ['_id' => $id],
                        ['$set' => ['SafeID' => $targetSafeID,
                        'iv' => $js->iv,
                        'data' => $js->data,
                        'tag' => $js->tag,
                        'folder' => $dst_folder,
                        'lastModified' =>Date('c'),
                        'version' => 3,
                        'note'=> $js->note]]
                    );
                } else {
                    $result = $mng->safe_items->updateMany(
                        ['_id' => $id],
                        ['$set' => ['SafeID' => $targetSafeID,
                        'iv' => $js->iv,
                        'data' => $js->data,
                        'tag' => $js->tag,
                        'folder' => $dst_folder,
                        'lastModified' =>Date('c'),
                        'version' => 3]]
                    );
                }    
            } else {
                // TODO process error
            }
        } else {  //version 2: data are fields hex encrypted, should not happen
            $result = $mng->safe_items->updateMany(
                ['_id' => $id],
                ['$set' => ['SafeID' => $targetSafeID,
                'data' => $data,
                'lastModified' =>Date('c'),
                'version' => 2]]
            );
        }
        if ($result->getModifiedCount() == 1) {
            passhub_log('user ' . $UserID . ' activity item move');
            return "Ok";
        }
        passhub_err(print_r($result, true));
        return("Move internal error");
    }

    // else operation = copy
    $js = json_decode($data);
    if ($js !== null) {
        if (isset($js->version) && ($js->version ==3) && isset($js->iv) && isset($js->data) && isset($js->tag)) {
            if (isset($js->note)) {
                $result = $mng->safe_items->insertOne(
                    ['SafeID' => $targetSafeID,
                    'iv' => $js->iv,
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'folder' => $dst_folder,
                    'lastModified' =>Date('c'),
                    'version' => 3,
                    'note'=> $js->note
                    ]
                );
            } else {
                $result = $mng->safe_items->insertOne(
                    ['SafeID' => $targetSafeID,
                    'iv' => $js->iv,
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'folder' =>$dst_folder,
                    'lastModified' =>Date('c'),
                    'version' => 3]
                );
            }
        }
    } else {  //version 2: data are fields hex encrypted, should not happen
        passhub_err("move_item 253");
        return "internal error";
    }
    if ($result->getInsertedCount() == 1) {
        passhub_log('user ' . $UserID . ' activity item copy');
        return "Ok";
    }
    passhub_err(print_r($result, true));
    return("Copy internal error");
}

//*************************************************

function delete_not_empty_folder($mng, $SafeID, $folderID) {

    $deleted  = ['items' => 0, 'folders' => 0];

    $filter = ['parent' => $folderID];
    $mng_cursor = $mng->safe_folders->find($filter);
    $folders = $mng_cursor->ToArray();
    foreach ($folders as $folder) {
        $result = delete_not_empty_folder($mng, $SafeID, (string)$folder->_id);
        $deleted['items'] += $result['items'];
        $deleted['folders'] += $result['folders'];
    }
    $result = $mng->safe_items->deleteMany(['SafeID' => $SafeID, 'folder' => $folderID]);
    $deleted['items'] += $result->getDeletedCount();

    $result = $mng->safe_folders->deleteMany(
        ['SafeID' => $SafeID, '_id' => new MongoDB\BSON\ObjectID($folderID)]
    );
    // try-catch
    if ($result->getDeletedCount() != 1) {
        passhub_err(print_r($result, true));
        return "Internal error";
    }
    $deleted['folders'] +=1;
    return $deleted;
}


// TODO add passhub_err
function folder_ops($mng, $UserID, $data) {

    if (!isset($data['SafeID'])) {
        passhub_err("folder_ops SafeID not defined");
        return "internal error";
    }
    if (!isset($data['folderID'])) {
        passhub_err("folder_ops folderID not defined");
        return "internal error";
    }
    if (!isset($data['operation'])) {
        passhub_err("folder_ops operation not defined");
        return "internal error";
    }

    $SafeID = $data['SafeID'];
    $folderID = $data['folderID'];

    if (!ctype_xdigit((string)$UserID) || !ctype_xdigit((string)$SafeID)
        || !ctype_xdigit((string)$folderID)
    ) {
        passhub_err("folder_ops UserID " . $UserID . " SafeID " . $SafeID . " folderID " . $folderID);
        return "internal error";
    }
    if (can_write($mng, $UserID, $SafeID) == false) {
        passhub_err("error itm 238 role = '$role'");
        return "You do not have enough rights";
    }

    // check if folder exists in SafeID
    if ($folderID != 0) {
        $filter = [ 'SafeID' => $SafeID, '_id' => new MongoDB\BSON\ObjectID($folderID)];
        $mng_res = $mng->safe_folders->find($filter);

        if (count($mng_res->ToArray()) != 1) {
            passhub_err("itm 261 folder " . $folderID . " SafeID " . $SafeID);
            return "internal error";
        }
    }

    if ($data['operation'] == 'delete') {
        if ($folderID == 0) {
            passhub_err("delete folder 0");
            return "internal error";
        }
         // check if empty
        $mng_cursor = $mng->safe_folders->find(['parent' => $folderID]);
        if (count($mng_cursor->ToArray()) > 0) {
            return "Folder not empty";
        }
        $mng_cursor = $mng->safe_items->find(['folder' => $folderID]);
        if (count($mng_cursor->ToArray()) > 0) {
            return "Folder not empty";
        }
        $result = $mng->safe_folders->deleteMany(
            ['SafeID' => $SafeID, '_id' => new MongoDB\BSON\ObjectID($folderID)]
        );
        // try -catch
        if ($result->getDeletedCount() != 1) {
            passhub_err(print_r($result, true));
            return "Internal error";
        }
        passhub_log('user ' . $UserID . ' activity empty folder deleted');
        return "Ok";
    }

    if ($data['operation'] == 'delete_not_empty') {
        if ($folderID == 0) {
            passhub_err("delete folder 0");
            return "internal error";
        }
        $deleted = delete_not_empty_folder($mng, $SafeID, $folderID);
        passhub_log(
            'user ' . $UserID . ' activity folder deleted with '
            . $deleted['items'] . ' items and '
            . $deleted['folders'] . ' subfolders'
        );
        return ['status' => 'Ok', 'items' => $deleted['items'], 'folders' => $deleted['folders']];
    }

    if (($data['operation'] == 'create') || ($data['operation'] == 'rename')) {
        if (!isset($data['name'])) {
            passhub_err("folder_ops name not defined");
            return "internal error";
        }
        $js = json_decode($data['name']);
        if ($js == null) {
            passhub_err("itm 261 cannot decompose json name ");
            return "internal error";
        }
        if ((isset($js->version) && ($js->version ==3) && isset($js->iv) && isset($js->data) && isset($js->tag)) == false ) {
            passhub_err("itm 265 name json structure " . $data['name']);
            return "internal error";
        }

        if ($data['operation'] == 'create') {
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
                passhub_log('user ' . $UserID . ' activity folder ' . $data['operation']);
                return ["status" => "Ok", "id" => (string)$folder_id];
            }
        } else {
            $r = $mng->safe_folders->updateOne(
                ['SafeID' => $SafeID, '_id' => new MongoDB\BSON\ObjectID($folderID)], 
                ['$set' => 
                ['iv' => $js->iv,
                'data' => $js->data,
                'tag' => $js->tag,
                'lastModified' =>Date('c'),
                'version' => 3]]
            );
            if ($r->getModifiedCount() == 1) {
                $folder_id = $r->getUpsertedId();
                passhub_log('user ' . $UserID . ' activity folder ' . $data['operation']);
                return ["status" => "Ok", "id" => (string)$folder_id];
            }
        }

        // try-catch
        passhub_err(print_r($result, true));
        return "Internal error";
    }
    passhub_err("folder_ops operation " . $data['operation']);
    return 'internal error';
}

function get_folder_list_cse($mng, $UserID, $SafeID) {

    $UserID = (string)$UserID;
    $SafeID = (string)$SafeID;

    $cursor = $mng->safe_folders->find(['SafeID' => $SafeID]);

    $role = get_user_role($mng, $UserID, $SafeID);
    if ($role) {   //read is enough for a while
        $folder_array = $cursor->toArray();
        foreach ($folder_array as $folder) {
            $folder->_id= (string)$folder->_id;
        }
    } else {
        $folder_array = array();
        passhub_err("error itm 266, no role: UserID " . $UserID . " SafeID" . $SafeID);
    }
    return $folder_array;
}


function import_folder($mng, $UserID, $SafeID, $parent, $folder) {
    //    passhub_err(print_r($folder, true));
    $result = folder_ops($mng, $UserID, ['SafeID' => $SafeID, 'folderID' => $parent, 'operation' => 'create', 'name' => $folder['name']]);
    if ($result['status'] == 'Ok') {
        $id = $result['id'];
        if (isset($folder['entries']) && (count($folder['entries']) > 0)) {
            create_items_cse($mng, $UserID, $SafeID, $folder['entries'], $id);
        }
        if (isset($folder['folders'])) {
            foreach ($folder['folders'] as $child) {
                $r = import_folder($mng, $UserID, $SafeID, $id, $child);
                if ($r['status'] != 'Ok') {
                    return $r;
                }
            }
        }
    }
    return $result;
}

function merge_folder($mng, $UserID, $SafeID, $parent, $folder) {

    if (isset($folder['entries']) && (count($folder['entries']) > 0)) {
        create_items_cse($mng, $UserID, $SafeID, $folder['entries'], $folder['_id']);
    }
    if (isset($folder['folders'])) {
        foreach ($folder['folders'] as $child) {

            if (isset($child['_id'])) {
                merge_folder($mng, $UserID, $SafeID, $folder['_id'], $child);
                // look inside
            } else {
                $r = import_folder($mng, $UserID, $SafeID, $folder['_id'], $child);
                if ($r['status'] != 'Ok') {
                    return $r;
                }
            }
        }
    }
}

function import_safes($mng, $UserID, $post) {

    if (!ctype_xdigit((string)$UserID) ) {
        passhub_err("import_safes UserID " . $UserID);
        return "internal error";
    }

    if (!isset($post['import'])) {
        passhub_err("import_safes imported trees not defined");
        return "internal error";
    }

    foreach ($post['import'] as $safe) {
        if (isset($safe['id'])) {  //merge
            $SafeID = $safe['id'];
            if (!can_write($mng, $UserID, $SafeID)) {
                return "access vioaltion or safe does not exist";
            }

            if (isset($safe['entries']) && (count($safe['entries']) >0)) {
                create_items_cse($mng, $UserID, $SafeID, $safe['entries'], 0);
            }
            if (isset($safe['folders'])) {
                foreach ($safe['folders'] as $folder) {
                    if (isset($folder['_id'])) {
                        merge_folder($mng, $UserID, $SafeID, 0, $folder);
                        // look inside
                    } else {
                        $r = import_folder($mng, $UserID, $SafeID, 0, $folder);
                        if ($r['status'] != 'Ok') {
                            return $r;
                        }
                    }
                }
            }
            continue;
        } else if (!isset($safe['key']) || !ctype_xdigit((string)$safe['key'])) {
            passhub_err("import_safes key illegal or undefined");
            return "internal error";
        }
        //TODO truncate name length if required
        // patch naming
        $safe['aes_key'] = $safe['key'];
        $result = create_safe1($mng, $UserID, $safe);
        if (is_string($result)) {
            return $result;
        }
        $SafeID = $result['id'];
        if (isset($safe['entries']) && (count($safe['entries']) > 0)) {
            create_items_cse($mng, $UserID, $SafeID, $safe['entries'], 0);
        }
        if (isset($safe['folders'])) {
            foreach ($safe['folders'] as $folder) {
                $r = import_folder($mng, $UserID, $SafeID, 0, $folder);
                if ($r['status'] != 'Ok') {
                    return $r;
                }
            }
        }
    }
    return ["status" => "Ok"];
}
