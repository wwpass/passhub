<?php

//include_once __DIR__ . '/../vendor/autoload.php';

require_once 'vendor/autoload.php';

function google_drive_get_service() {

    $client = new Google_Client();
    $client->setAuthConfig(GOOGLE_CREDS);
    $client->setScopes("https://www.googleapis.com/auth/drive");
    return new Google_Service_Drive($client);
}

function google_drive_upload($fname, $content) {
    try {
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
    } catch(Exception $e) {
        return ["status" =>  $e->getMessage()];
    }
    return ["status" => "Ok", "google_drive_id" => $result->id];
}

function google_drive_download($fname) {

    try {
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
    } catch(Exception $e) {
        passhub_err("file download error " . $e->getMessage);
        return ["status" => $e->getMessage];
    }
}

function google_drive_delete($fname) {

    try {
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
    } catch(Exception $e) {
        return ["status" => $e->getMessage];
    }
}

