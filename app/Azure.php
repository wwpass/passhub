<?php


// https://learn.microsoft.com/en-us/graph/sdks/create-client?tabs=PHP
// scopes: https://learn.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow and subsequent pages

namespace PassHub;

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

use GuzzleHttp\Client;

namespace PassHub;

class Azure
{

    public static function Authenticate() {
        Utils::err(AZURE);
        
        $url = 'https://login.microsoftonline.com/' . AZURE['directory_tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id' => AZURE['application_client_id'],
            'response_type' => 'code',
            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/oauth-callback.php',
            'response_mode' => 'query',
            'scope' => 'https://graph.microsoft.com/.default', //optional
            'state' => '12345', // optional
        ]);
        Utils::err($url);

        
        header("Location: $url");
    }
    
    public static function checkAccess($username) {

/*
        Utils::err('Azure checkAccess');
        Utils::err($username);
*/	
        $groupsArray = Azure::getUsers();
        $userUpns = $groupsArray["user_upns"];
        $adminUpns = $groupsArray["admin_upns"];
        $userTrue = False;
    
        for($i = 0; $i < count($userUpns); $i++) {
            if($userUpns[$i] == $username) {
                $userTrue = True;            
                break;
            }
        }
        if($userTrue) {
            for($j = 0; $j < count($adminUpns); $j++) {
                if($adminUpns[$j] == $username) {
                    $_SESSION['admin'] = true;
                    break;
                }
            }
        }
    return $userTrue;
}   

    public static function getUsers() {
	$accessToken = Azure::getAccessToken();
        $userGroupUrl = AZURE['user_group'];
        $adminGroupUrl = AZURE['admin_group'];
        $user_upns = [];
        $admin_upns = [];

	$guzzle = new \GuzzleHttp\Client();

        $getUserGroupId = $guzzle->request('GET', 'https://graph.microsoft.com/v1.0/groups/?$filter=startswith(displayName,' .  "'$userGroupUrl'" . ')&$select=id', [
            'headers' => [
                'Authorization' => $accessToken
            ]
        ]);

        // Extracts User Group ID from JSON
        $groupUserIdBody = $getUserGroupId->getBody();
        $userGroupObject = get_object_vars(json_decode($groupUserIdBody));
        $userGroupIdArray = get_object_vars($userGroupObject["value"][0]); 
        $userGroupId = $userGroupIdArray["id"];

	// Utils::err("userGroupId " . $userGroupId);

        $getAdminGroupId = $guzzle->request('GET', 'https://graph.microsoft.com/v1.0/groups?$filter=startswith(displayName,' . "'$adminGroupUrl'" . ')&$select=id', [
            'headers' => [
                'Authorization' => $accessToken
            ]
        ]);
        
        // Extracts Admin Group ID from JSON
        $groupAdminIdBody = $getAdminGroupId->getBody();
        $adminGroupObject = get_object_vars(json_decode($groupAdminIdBody));
        $adminGroupIdArray = get_object_vars($adminGroupObject["value"][0]);
        $adminGroupId = $adminGroupIdArray["id"];

	// Utils::err("adminGroupId " . $adminGroupId);

        // Call microsoft graph for all members in passhub users group from config 
        $getMembersOfUsers = $guzzle->request('GET', 'https://graph.microsoft.com/v1.0/groups/{' . $userGroupId . '}/members?$select=userPrincipalName', [
            'headers' => [
                'Authorization' => $accessToken
            ]
        ]);        

        $memberOfUsersBody = $getMembersOfUsers->getBody();
        $userMembersObject = get_object_vars(json_decode($memberOfUsersBody));

/*
	Utils::err("Users group");
	Utils::err($userMembersObject);
*/

        // Check if this userprincipalname is same in members group 
// 	Utils::err('userMembersObject count ' . count($userMembersObject['value']));
        for($i = 0; $i < count($userMembersObject['value']); $i++) {
            $userMembersArray = get_object_vars($userMembersObject["value"][$i]);     
            $userMembersName = $userMembersArray["userPrincipalName"];
        
            // If Not Null then Continue on
            if(!is_null($userMembersName)) {  
                $user_upns[] = $userMembersName;
            }    
        }

        // Call microsoft graph for all members in passhub admin group from config 
        $getMembersOfAdmins = $guzzle->request('GET', 'https://graph.microsoft.com/v1.0/groups/{' . $adminGroupId . '}/members?$select=userPrincipalName', [
            'headers' => [
                'Authorization' => $accessToken
            ]
        ]);        

        $memberOfAdminBody = $getMembersOfAdmins->getBody();
        $adminMembersObject = get_object_vars(json_decode($memberOfAdminBody));
/*
	Utils::err("Admin group");
	Utils::err($adminMembersObject);
*/


        // Check if this userprincipalname is same in members group 
        for($i = 0; $i < count($adminMembersObject['value']); $i++) {
            $adminMembersArray = get_object_vars($adminMembersObject["value"][$i]);     
            $adminMembersName = $adminMembersArray["userPrincipalName"];                             
        
            // If Not Null then Continue on
            if(!is_null($adminMembersName)) {  
                $admin_upns[] = $adminMembersName;
            }    
        }
        return ["user_upns" => $user_upns, "admin_upns" => $admin_upns];
    }

    public static function getAccessToken() {
        $clientId = AZURE['application_client_id'];
        $tenantId = AZURE['directory_tenant_id'];
        $clientSecret = AZURE['client_value'];
        $guzzle = new \GuzzleHttp\Client();
        $tokenUrl = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token';
        $token = json_decode($guzzle->post($tokenUrl, [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());
        return $token->access_token;
    }

}
