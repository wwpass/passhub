<?php

require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();
setDbSessionHandler($mng);
session_start();

function ldap() {
    if (!$_POST['username'] || !$_POST['password']) {
        return "username and password fields should not be empty";
    }
/*
    if (!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
        passhub_err("bad csrf ". $_POST['verifier'] . " != " . User::get_csrf());
        return "Bad Request (46)";
    }
*/
    $username = $_POST['username'];
    //$bind_rdn = "WWPASS\\" . $username;

    if (strpos($username, "@") === false) {
        $username .= "@" . LDAP['domain'];

    }
    $bind_rdn = $username;
    $bind_pwd = $_POST['password'];
  
  
    $ds=ldap_connect(LDAP['url']);

    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ds, LDAP_OPT_NETWORK_TIMEOUT, 10);    
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
        ldapBindExistingUser($mng, $_SESSION['UserID'], $result['email'], $result['userprincipalname']);
        header("Location: index.php");
        exit();
    }
    $_SESSION['userprincipalname'] = $result['userprincipalname'];
    $_SESSION['email'] = $result['email'];
    showCreateUserPage();
    exit();
}

$verifier = User::get_csrf();
passhub_err("Verifier set to " . $verifier);

echo theTwig()->render(
    'ldap.html', 
    [
        'narrow' => true, 
        'verifier' => $verifier,
        'alert' => $result['status'],
        'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
    ]
);

