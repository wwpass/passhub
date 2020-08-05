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

class FileS3 extends File
{
    protected function upload($content) {
        $client = new \Aws\S3\S3Client(S3_CONFIG);
        $insert = $client->putObject(
            [
                'Bucket' => S3_BUCKET,
                'Key'    => $this->name,
                'Body'   => $content
            ]
        );
        return ["status" => "Ok"];
    }
    
    protected function download() {
        $client = new \Aws\S3\S3Client(S3_CONFIG);
        $response = $client->getObject(
            [
              'Bucket' => S3_BUCKET,
              'Key' => $this->name
            ]
        );  
        $data = $response['Body'];
        return ["status" => "Ok", "data" => $data];
    }
    
    public function delete() {
        $client = new \Aws\S3\S3Client(S3_CONFIG);
        $result = $client->deleteObject(
            [
              'Bucket' => S3_BUCKET,
              'Key' => $this->name
            ]
        );
        return ["status" => "Ok"];
    }
}
