<?php

/**
 * MongoSessionHandler.php
 *
 * PHP version 7
 *
 * modified code of https://github.com/altmetric/mongo-session-handler
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class MongoSessionHandler implements \SessionHandlerInterface
{
    private $collection;
    private $mng;

    public function __construct($mng)
    {
        $this->collection = $mng->phpsession;
        // $this->mng = $mng;
    }
    public function open($_save_path, $_name)
    {
        return true;
    }
    public function close()
    {
        return true;
    }
    public function read($id)
    {
        $cursor = $this->collection->find(['_id' => $id]);

        $sessions = $cursor->toArray();
        $num_sessions = count($sessions);
        if ($num_sessions == 0) {
            return '';
        }
        if ($num_sessions == 1) {
            return $sessions[0]->data->getData(); ;
        }
        Utils::err("error sessions 52 count " . $num_sessions);
        exit("internal error sessions 52"); //multiple PUID records;

    }
    public function write($id, $data)
    {
        $session = [
            '$set' => [
                'data' => new \MongoDB\BSON\Binary($data, \MongoDB\BSON\Binary::TYPE_OLD_BINARY),
                'last_accessed' => new \MongoDB\BSON\UTCDateTime(floor(microtime(true) * 1000))
            ]
        ];
        try {
            $result = $this->collection->updateOne(['_id' => $id], $session, ['upsert' => true]);
            return true;
        } catch (MongoDBException $e) {
            Utils::err("Error when saving {$data} to session {$id}: {$e->getMessage()}");
            return false;
        }
    }
    public function destroy($id)
    {
        try {
            $result = $this->collection->deleteOne(['_id' => $id]);
            return true;
        } catch (MongoDBException $e) {
            Utils::err("Error removing session {$id}: {$e->getMessage()}");
            return false;
        }
    }
    public function gc($maxlifetime)
    {
        $lastAccessed = new \MongoDB\BSON\UTCDateTime(floor((microtime(true) - $maxlifetime) * 1000));
        try {
            Utils::err("Removing any sessions older than {$lastAccessed}");
            $result = $this->collection->deleteMany(['last_accessed' => ['$lt' => $lastAccessed]]);
            return true;
        } catch (MongoDBException $e) {
            Utils::err("Error removing sessions older than {$lastAccessed}: {$e->getMessage()}");
            return false;
        }
    }
}
