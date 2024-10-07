<?php


// https://learn.microsoft.com/en-us/graph/sdks/create-client?tabs=PHP
// scopes: https://learn.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow and subsequent pages

namespace PassHub;

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use GuzzleHttp\Client;


class Azure
{

    public static function Authenticate() {
        Utils::err(AzureCloud);
        
        $url = 'https://login.microsoftonline.com/' . AzureCloud['directory_tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id' => AzureCloud['application_client_id'],
            'response_type' => 'code',
            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/oauth-callback.php',
            'response_mode' => 'query',
            'scope' => 'https://graph.microsoft.com/.default', //optional
            'state' => '12345', // optional
        ]);
        Utils::err($url);

        
        header("Location: $url");
    }
}
