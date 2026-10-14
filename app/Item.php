<?php

/**
 *
 * Item.php
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

use \PassHub\Files\File;

class Item {

    function __construct($mng, $entryID) {
        $this->mng = $mng;
        $this->entryID = $entryID;
        $this->_id = (strlen($this->entryID) != 24) ? $this->entryID :
            new \MongoDB\BSON\ObjectID($this->entryID);
    }

    //******************************************************************

    private function getSafe() {
        $cursor = $this->mng->safe_items->find(['_id' => $this->_id]);

        $a = $cursor->toArray();

        if (count($a) != 1) {
            Utils::err("error itm 307, entryID $this->entryID count is " . count($a));
            return -1;
        }
        $row = $a[0];
        return $row->SafeID;
    }

    public static function create_items_cse($mng, $UserID, $SafeID, $items, $folder) {

        if (!is_array($items)) {
            $items = [$items];
        }

        $user = new User($mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            Utils::err("error itm 360 UserID " . $UserID . " SafeID " . $SafeID);
            return "Sorry, you do not have editor rights for this safe";
        }

        $records = [];
        foreach ($items as $item) {
            $js = json_decode($item);
            if ($js !== null) {

                if (isset($js->version) && (($js->version == 3) || ($js->version == 4) || ($js->version == 5)  || ($js->version == 6)) && isset($js->iv) && isset($js->data) && isset($js->tag)) {
                    $record = [];
                    $record['SafeID'] = $SafeID;
                    $js = (array)$js;
                    $record = $record + $js;
                    $record['folder'] = $folder;
                    $record['revision'] = 1;

                    if (!isset($record['lastModified'])) {
                        $record['lastModified'] = Date('c');
                    }
                    array_push($records, $record);
                } else {
                    Utils::err(print_r($js, true));
                    return ('Internal system error 91');
                }
            } else {  //version 2: data are fields hex encrypted, should not happen
                Utils::err("wrong item format, error 95");
                return ('Internal system error 95');
            }
        }
        try {
            $result = $mng->safe_items->insertMany($records);

            if ($result->getInsertedCount() == count($items)) {
                Utils::log('user ' . $UserID . ' activity ' . count($items) . ' item(s) created');
                $firstID = (string) $result->getInsertedIds()[0];
                return ["status" =>  "Ok", "firstID" => $firstID];
            }
            Utils::err(print_r($result, true));
            return ('Internal system error 107');
        } catch (Exception $e) {
            Utils::err(print_r($e, true));
            return ('Internal system error 110');
        }
    }

    
    public function update($UserID, $SafeID, $data, $expectedRevision = null) {

        if ($SafeID != $this->getSafe()) {
            Utils::err("error itm 356: SafeID_requested = '$SafeID', SafeID_real = " . $row->SafeID);
            return "Internal server error 356";
        }
        $user = new User($this->mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            Utils::err("error 150 (no rights) UserID " . $UserID . " SafeID " . $SafeID);
            return "Sorry, you do not have editor rights for this safe";
        }

        $js = json_decode($data);
        if ($js !== null) {

            // Passkey records (version 6) use revision/CAS and do not support
            // History yet. Preserve the existing History flow for versions 3-5.
            if (isset($js->version) && in_array($js->version, [3, 4, 5], true)) {
                $cursor = $this->mng->safe_items->find(['_id' => $this->_id]);
                $result = $cursor->toArray();

                if (count($result)) {
                    $old_record = $result[0];
                    Utils::err("old_record");
                    Utils::err($old_record);

                    if (
                        property_exists($old_record, "iv") &&
                        property_exists($old_record, "data") &&
                        property_exists($old_record, "tag") &&
                        property_exists($old_record, "lastModified") &&
                        property_exists($old_record, "version")
                    ) {
                        $history_record = [
                            "iv" => $old_record["iv"],
                            "data" => $old_record["data"],
                            "tag" => $old_record["tag"],
                            "lastModified" => $old_record["lastModified"],
                            "version" => $old_record["version"]
                        ];
                        Utils::err("history_record");
                        Utils::err($history_record);
                        $history = [];
                        if (property_exists($old_record, "history")) {
                            Utils::err("history found");
                            $history = $old_record["history"];

                            Utils::err("history");
                            Utils::err(gettype($history));

                            $history = (array) $old_record["history"];

                            Utils::err("(array) history");
                            Utils::err(gettype($history));
                            Utils::err($history);
                        }
                        array_unshift($history, $history_record);
                        $this->mng->safe_items->updateOne(['_id' => $this->_id], ['$set' => ['history' => $history]]);
                    }
                }
            }

            if (isset($js->version) && (($js->version == 3) || ($js->version == 4)  || ($js->version == 5) || ($js->version == 6)) && isset($js->iv) && isset($js->data) && isset($js->tag)) {
                $updateData = [
                    'iv' => $js->iv,
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'lastModified' => Date('c'),
                    'version' => $js->version
                ];
            } else {
                Utils::err(print_r($js, true));
                return "Internal error 169";
            }
        } else {  // version 2: data are fields hex encrypted, should not happen
            $updateData = [
                'SafeID' => $SafeID,
                'data' => $data,
                'lastModified' => Date('c'),
                'version' => 2
            ];
        }

        $filter = ['_id' => $this->_id];
        if ($expectedRevision !== null) {
            $filter['SafeID'] = $SafeID;
            if ($expectedRevision === 0) {
                $filter['$or'] = [
                    ['revision' => 0],
                    ['revision' => ['$exists' => false]]
                ];
            } else {
                $filter['revision'] = $expectedRevision;
            }
        }

        $result = $this->mng->safe_items->updateOne(
            $filter,
            [
                '$set' => $updateData,
                '$inc' => ['revision' => 1]
            ]
        );

        // try-catch
        if ($result->getModifiedCount() == 1) {
            // readback to get folder: wish I had findOneAndUpdate
            // returns folder to show in the index page  

            $cursor = $this->mng->safe_items->find(['_id' => $this->_id]);

            $a = $cursor->toArray();
            if (count($a) != 1) {
                Utils::err("error itm 238, entryID $entryID count is " . count($a));
                Utils::errorPage("Internal server error itm 324");
            }
            $row = (array)$a[0];
            if (!array_key_exists("folder", $row)) {
                $row['folder'] = 0;
            }
            Utils::log('user ' . $UserID . ' activity item update');
            return ['status' => "Ok", 'item' => $row];
        }
        if ($expectedRevision !== null) {
            return ['status' => "Conflict"];
        }
        Utils::err(print_r($result, true));
        return "Internal error 201";
    }

    public function delete($UserID, $declaredSafeID) {

        $SafeID = $this->getSafe();
        if ($SafeID == -1) {
            return "Record not found";
        }
        if ($SafeID != $declaredSafeID) {
            Utils::err("error 186 delete_item safe mismatch: real SafeID '$SafeID', requested '$declaredSafeID'");
            return "Record not found";
        }
        $user = new User($this->mng, $UserID);
        if ($user->canWrite($SafeID) == false) {
            Utils::err("error del_item rights user = '$UserID' safe = '$SafeID'");
            return "Sorry, you do not have editor rights for this safe";
        }

        //get item, check if there are files
        $cursor = $this->mng->safe_items->find(['_id' => $this->_id]);

        $a = $cursor->toArray();
        if (count($a) != 1) {
            Utils::err("error itm 259, entryID $entryID count is " . count($a));
            exit("Internal server error itm 259");
        }
        $row = $a[0];
        if (property_exists($row, 'file')) {
            $f = File::newFile($row->file->id);
            $delete_result = $f->delete();
            if (!$delete_result) {
                Utils::err("delete file id " . $row->file->id . " Fail");
            }
        }
        $result = $this->mng->safe_items->deleteMany(['_id' => $this->_id]);
        if ($result->getDeletedCount() == 1) {
            if (property_exists($row, 'file')) {
                Utils::log('user ' . $UserID . ' activity file delete');
            } else {
                Utils::log('user ' . $UserID . ' activity item delete');
            }
            return "Ok";
        }
        Utils::err(print_r($result, true));
        return "Internal error 427";
    }

    // TODO: preserve modification data
    public function move($UserID, $sourceSafeID_not_used, $targetSafeID, $dst_folder, $data, $operation) {

        /*    // TODO
        - if dst_folder exists and belongs to dst_safe
        - if the user has rights to write to dst_folder
        - if the user has rights to access source folder
        */

        $srcSafeID = $this->getSafe();

        $user = new User($this->mng, $UserID);

        if (!$user->canRead($srcSafeID)) {
            return "no src read";
        }
        if (($operation == "move") && !$user->canWrite($srcSafeID)) {
            return "no src write";
        }

        if (!$user->canWrite($targetSafeID)) {
            return "no dst write";
        }

        $js = json_decode($data);
        if ($js !== null) {
            if (
                isset($js->version)
                && (($js->version == 3) || ($js->version == 4) || ($js->version == 5) || ($js->version == 6))
                && isset($js->iv)
                && isset($js->data)
                && isset($js->tag)
            ) {

                $record = [
                    'SafeID' => $targetSafeID,
                    'iv' => $js->iv,
                    'data' => $js->data,
                    'tag' => $js->tag,
                    'folder' => $dst_folder,
                    'lastModified' => Date('c'),
                    'version' => $js->version
                ];
                if (isset($js->history)) {
                    $record['history'] = $js->history;
                }

                if (isset($js->note)) {
                    $record['note'] = $js->note;
                }
                if (isset($js->file)) {
                    $record['file'] = $js->file;
                }

                if ($operation == "move") {
                    $result = $this->mng->safe_items->updateMany(
                        ['_id' => $this->_id],
                        [
                            '$set' => $record,
                            '$inc' => ['revision' => 1]
                        ]
                    );
                    if ($result->getModifiedCount() == 1) {
                        Utils::log('user ' . $UserID . ' activity item move');
                        return "Ok";
                    }
                    Utils::err(print_r($result, true));
                    return ("Move internal error");
                }
                // else operation = copy

                $record['revision'] = 1;

                $result = $this->mng->safe_items->insertOne(
                    ['SafeID' => $targetSafeID] + $record
                );
                if ($result->getInsertedCount() == 1) {
                    Utils::log('user ' . $UserID . ' activity item copy');
                    return "Ok";
                }
                Utils::err(print_r($result, true));
                return ("Copy internal error");
            }
            return "Internal error itm 317";
        }
        return "Internal error itm 319";
    }
}



/*
    public function getMoveOperationData($UserID, $srcSafeID, $dstSafeID, $operation) {

        $cursor = $this->mng->safe_items->find(['_id' => $this->_id]);

        $a = $cursor->toArray();
        if (count($a) != 1) {
            Utils::err("error itm 324, entryID $this->entryID count is " . count($a));
            return "Internal server error itm 324";
        }
        $itemData = $a[0];

        $SafeID = $itemData->SafeID;

        $user = new User($this->mng, $UserID);

        if (!$user->canRead($srcSafeID)) {
            return "no src read";
        }
        if (($operation == "move") && !$user->canWrite($srcSafeID)) {
            return "no src write";
        }

        if (!$user->canWrite($dstSafeID)) {
            return "no dst write";
        }

        $srcKey = $user->getEncryptedAesKey($srcSafeID);
        $dstKey = $user->getEncryptedAesKey($dstSafeID);
        return array(
            "status" => "Ok",
            "item" => $itemData,
            "src_key" => $srcKey,
            "dst_key" => $dstKey
        );
    }
*/