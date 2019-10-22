<?php
/**
 * s3_files.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2019 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'vendor/autoload.php';

use Aws\S3\S3Client;

function s3_client() {
    return  new Aws\S3\S3Client(S3_CONFIG);

    /*
        [
          'version' => 'latest',
          'region'  => 'nyc3',
          'endpoint' => 'https://nyc3.digitaloceanspaces.com',
          'credentials' => [
              'key'    => 'ACCESS_KEY',
              'secret' => 'SECRET_KEY',
          ],
        ]
    );
    */
}

function s3_upload($fname, $content) {
    $client = s3_client();
    $insert = $client->putObject(
        [
            'Bucket' => S3_BUCKET,
            'Key'    => $fname,
            'Body'   => $content
        ]
    );
    return ["status" => "Ok"];
}

function s3_download($fname) {
    $client = s3_client();
    $response = $client->getObject(
        [
          'Bucket' => S3_BUCKET,
          'Key' => $fname
        ]
    );  
    $data = $response['Body'];
    return ["status" => "Ok", "data" => $data];
}

function s3_delete($fname) {
    $client = s3_client();
    $result = $client->deleteObject(
        [
          'Bucket' => S3_BUCKET,
          'Key' => $fname
        ]
    );
    return ["status" => "Ok"];
}
