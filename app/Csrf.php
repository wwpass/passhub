<?php

/**
 * Csrf.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

namespace PassHub;

class Csrf
{
    public static function get($renew = null) {
        if (!isset($_SESSION['csrf']) || $renew) {
            $bytes = random_bytes(256);
            $csrf = bin2hex($bytes);
            $_SESSION['csrf'] = $csrf;
        }
        return password_hash($_SESSION['csrf'], PASSWORD_DEFAULT);
    }

    public static function isValid(string $hash) {
        if (isset($_SESSION['csrf']) ) {
            return password_verify($_SESSION['csrf'], $hash);
        }
        return false;
    }
}
