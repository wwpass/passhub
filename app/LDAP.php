<?php

/**
 * Utils.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2020 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */


namespace PassHub;

class LDAP 
{
    public static function connect() {
        if(isset(LDAP['LDAP_OPT_X_TLS_REQUIRE_CERT'])) {
            ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP['LDAP_OPT_X_TLS_REQUIRE_CERT']);
        }
        $ds=ldap_connect(LDAP['url']);
        if(!$ds) {
            return false;
        }
    
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ds, LDAP_OPT_NETWORK_TIMEOUT, 10);

        if(!$ds) {
            Utils::err(" error 1070 ldapConnect fail");
            return false;
        }


	Utils::err('bind_dn ' . LDAP['bind_dn'] .  ' bind pwd ' . LDAP['bind_pwd']);
        
        $r=ldap_bind($ds, LDAP['bind_dn'], LDAP['bind_pwd']);

        if (!$r) {
            $result =  "Bind error " . ldap_error($ds) . " " . ldap_errno($ds);
            Utils::err($result);
            $e = ldap_errno($ds); 
            ldap_close($ds);
            return false;
        }
        return $ds;
    }

    private static function isInAdminGroup() {
        $group_count = $user['memberof']['count'];
        for($g = 0; $g < $group_count; $g++ ) {
            if($user['memberof'][strval($g)] == LDAP['admin_group']) {
                return true;
            }
        }
        return false;
    }

    public static function getUsers() {
        $ds=LDAP::connect();

        if (!$ds) {
            return false;
        }

        $user_filter = "(objectClass=user)";
        $group_filter = "(memberof=".LDAP['group'].")";

        
        $ldap_filter = "(&{$user_filter}{$group_filter})";
        $sr=ldap_search($ds, LDAP['base_dn'],  $ldap_filter);

        if ($sr == false) {
            Utils::err("ldap_search fail, ldap_errno " . ldap_errno($ds) . " base_dn * " . LDAP['base_dn'] . " * ldap_filter " . $ldap_filter);
        }
        $info = ldap_get_entries($ds, $sr);

        $user_count = $info['count'];
        $user_upns = [];
        $admin_upns = [];

        for($u = 0; $u < $user_count; $u++) {
            $user = $info[strval($u)];
            $upn = strtolower($user['userprincipalname']['0']);
            array_push($user_upns, $upn);
            if(self::isInAdminGroup($user)) {
                array_push($admin_upns, $upn);  
            }
        }
        return ["user_upns" => $user_upns, "admin_upns" => $admin_upns];
    }

    public static function checkAccess($userprincipalname) {

        $ds=LDAP::connect();

        if (!$ds) {
            return false;
        }


        $user_filter = "(userprincipalname={$userprincipalname})";
        $group_filter = "(memberof=".LDAP['group'].")";
      
        $ldap_filter = "(&{$user_filter}{$group_filter})";
        $search_result=ldap_search($ds, LDAP['base_dn'],  $ldap_filter);
#            Utils::err('LDAP search with filter ' . $ldap_filter);
#            Utils::err('Base dn ' . LDAP['base_dn']);

        if ($search_result == false) {
            Utils::err("ldap_search fail, ldap_errno " . ldap_errno($ds) . " base_dn * " . LDAP['base_dn'] . " * ldap_filter " . $ldap_filter);
        }
        $info = ldap_get_entries($ds, $search_result);
#            Utils::err('User enabled: ' . $info['count']);
        $user_enabled = $info['count'];


        if (defined('LDAP') && (isset(LDAP['admin_group']))) {
#                Utils::err('info');
#                Utils::err($info);

#                Utils::err('memberof');
            $memberof =  $info['0']['memberof'];

#                Utils::err($memberof);

            $group_count = $memberof['count'];
            Utils::err('group count ' . $group_count);
            for( $i = 0; $i < $group_count; $i++) {
                $group = $memberof[strval($i)];
#                    Utils::err('group ' . $i . ' ' . $group);
                if($group == LDAP['admin_group']) {
                    Utils::err('admin group member');
                    $_SESSION['admin'] = true;
                    break;
                }
            }
        }

        if ($user_enabled) {
            return true;
        }
        Utils::err('Ldap: access denied');
        return false;
    }
}
