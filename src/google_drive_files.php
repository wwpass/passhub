<?php
/**
 * google_drive_files.php
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

function google_drive_get_service() {

    $client = new Google_Client();
    $client->setAuthConfig(GOOGLE_CREDS);
    $client->setScopes("https://www.googleapis.com/auth/drive");
    return new Google_Service_Drive($client);
}

function google_drive_upload($fname, $content) {
    $service = google_drive_get_service();
    $file = new Google_Service_Drive_DriveFile();

    $file->setName($fname);
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

function google_drive_download($fname) {
    $pageToken = null;
    $service = google_drive_get_service();
    $response = $service->files->listFiles(
        array(
        'q' => "name = '" . $fname . "'",
        'spaces' => 'drive',
        'pageToken' => $pageToken,
        'fields' => 'nextPageToken, files(id, name, fileExtension, kind, mimeType, size, webViewLink, createdTime)',
        )
    );
    if (count($response->files) != 1) {
        passhub_err("Google drive: file not found " . $fname);
        return ["status" => "count " . count($response->files)];
    }
    $id = $response->files[0]->id;
    passhub_err(" file->id " . $id);
    $response = $service->files->get($id, array('alt' => 'media'));
    $data = $response->getBody()->getContents();
    return ["status" => "Ok", "data" => $data];
}

function google_drive_delete($fname) {
    $pageToken = null;
    $service = google_drive_get_service();
    $response = $service->files->listFiles(
        array(
        'q' => "name = '" . $fname . "'",
        'spaces' => 'drive',
        'pageToken' => $pageToken,
        'fields' => 'nextPageToken, files(id, name, fileExtension, kind, mimeType, size, webViewLink, createdTime)',
        )
    );
    if (count($response->files) != 1) {
        return ["status" => "count " . count($response->files)];
    }
    $id = $response->files[0]->id;
    //        passhub_err(" file->id " . $id);
    $response = $service->files->delete($id);
    return ["status" => "Ok"];
}

