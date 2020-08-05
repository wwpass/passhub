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

use \PassHub\Utils;

class FileGDrive extends File
{
    private static function getService() {

        $client = new \Google_Client();
        $client->setAuthConfig(GOOGLE_CREDS);
        $client->setScopes("https://www.googleapis.com/auth/drive");
        return new \Google_Service_Drive($client);
    }
    
    protected function upload($content) {
        $service = self::getService();
        $file = new \Google_Service_Drive_DriveFile();
    
        $file->setName($this->name);
        $result = $service->files->create(
            $file,
            array(
                'data' => $content,
                'mimeType' => 'application/octet-stream',
                'uploadType' => 'media'
            )
        );
        return ["status" => "Ok", "google_drive_id" => $result->id];
    }
    
    protected function download() {
        $pageToken = null;
        $service = self::getService();
        $response = $service->files->listFiles(
            array(
            'q' => "name = '" . $this->name . "'",
            'spaces' => 'drive',
            'pageToken' => $pageToken,
            'fields' => 'nextPageToken, files(id, name, fileExtension, kind, mimeType, size, webViewLink, createdTime)',
            )
        );
        if (count($response->files) != 1) {
            Utils::err("Google drive: file not found " . $this->name);
            return ["status" => "count " . count($response->files)];
        }
        $id = $response->files[0]->id;
        Utils::err(" file->id " . $id);
        $response = $service->files->get($id, array('alt' => 'media'));
        $data = $response->getBody()->getContents();
        return ["status" => "Ok", "data" => $data];
    }
    
    public function delete() {
        $pageToken = null;
        $service = self::getService();
        $response = $service->files->listFiles(
            array(
            'q' => "name = '" . $this->name . "'",
            'spaces' => 'drive',
            'pageToken' => $pageToken,
            'fields' => 'nextPageToken, files(id, name, fileExtension, kind, mimeType, size, webViewLink, createdTime)',
            )
        );
        if (count($response->files) != 1) {
            return ["status" => "count " . count($response->files)];
        }
        $id = $response->files[0]->id;
        $response = $service->files->delete($id);
        return ["status" => "Ok"];
    }
}
