<?php

/**
 * DB.php
 *
 * PHP version 7
 *
 * modified code of https://github.com/altmetric/mongo-session-handler
 *
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class DB 
{
    public static function Connection() {
        $client = new \MongoDB\Client($uri = MONGODB_CONNECTION_LINE);
        $mng = $client->selectDatabase(DB_NAME);
        $handler = new MongoSessionHandler($mng);
        session_set_save_handler($handler);
        return $mng;
    }
}
