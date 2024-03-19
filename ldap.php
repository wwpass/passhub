<?php

require_once 'config/config.php';
require_once 'vendor/autoload.php';

use PassHub\Utils;
use PassHub\Csrf;
use PassHub\DB;
use PassHub\User;


$mng = DB::Connection();

session_start();

function ldap() {
    if (!$_POST['username'] || !$_POST['password']) {
        return "username and password fields should not be empty";
    }
/*
    if (!isset($_POST['verifier']) || !Csrf::isValid($_POST['verifier'])) {
        Utils::err("bad csrf ". $_POST['verifier'] . " != " . Csrf::get());
        return "Bad Request (46)";
    }
*/
    $username = trim($_POST['username']);
    //$bind_rdn = "WWPASS\\" . $username;

    if (strpos($username, "@") === false) {
        $username .= "@" . LDAP['domain'];

    }
    $bind_rdn = $username;
    $bind_pwd = trim($_POST['password']);
  
    $ds=Utils::ldapConnect();

    if(!$ds) {
        Utils::err(" error 1070 ldapConnect fail");
        return "LDAP Connect fail, consult system administrator";
    }    
    
    for ( $i = 0; $i < 3; $i++) {
        $r=ldap_bind($ds, $bind_rdn, $bind_pwd);

        if ($r) {
            break;
        } 
        if (ldap_errno($ds) == -1 ) {
            continue;
        }
        break;
    }
   
    if (!$r) {
      
        $result =  "Bind error " . ldap_error($ds) . " " . ldap_errno($ds) . " ". $i . "<br>";
        Utils::err($result);  
        $e = ldap_errno($ds); 
        ldap_close($ds);
        if ($e == -1) {
            return "Cannot connect to Active Directory server. Try again later"; 
        }
        if ($e == 49) {
            return "Incorrect username or password. Please Try again"; 
        }
        return "Login to Active directory error " . $e;
    }
  
    $user_filter = "(userprincipalname={$username})";
    $group_filter = "(memberof=".LDAP['group'].")";
  
    $ldap_filter = "(&{$user_filter}{$group_filter})";
    $sr=ldap_search($ds, LDAP['base_dn'],  $ldap_filter);
    $info = ldap_get_entries($ds, $sr);

//    Utils::err('ldap search result:');
//    Utils::err($info);

    $user_enabled = $info['count'];

    if ($user_enabled) {
        $user_mail = $info[0]['mail'][0];
    } else {
        return "You are not authorized for this service. Consult your system administrator";
    }
    ldap_close($ds);

    $_SESSION['userprincipalname'] = $username;
    $_SESSION['email'] = $user_mail;

    return ['status' => 'Ok', 'email' => $user_mail, 'enabled' => $user_enabled, 'userprincipalname' => $username];
}

$result = ldap();

if (!is_array($result)) {
    $result = array("status" => $result);
}

if ($result['status'] == 'Ok') {
    if (isset($_SESSION['UserID'])) {
        $user = new User($mng, $_SESSION['UserID']);
        $user->ldapBindExistingUser($result['email'], $result['userprincipalname']);
        header("Location: index.php");
        exit();
    }
    $_SESSION['userprincipalname'] = $result['userprincipalname'];
    if($result['email']) {
        $_SESSION['email'] = $result['email'];
    } else {
        $_SESSION['email'] = $result['userprincipalname'];
    }
    Utils::showCreateUserPage();
    exit();
}

echo Utils::render(
    'ldap.html', 
    [
        'narrow' => true, 
        'verifier' => Csrf::get(),
        'alert' => $result['status'],
        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
    ]
);
