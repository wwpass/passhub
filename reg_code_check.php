<?php

/**
 * new.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

require_once 'config/config.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/db/safe.php';
require_once 'src/template.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();
session_destroy();
$_SESSION = array();

if (!isset($_GET['reg_code'])) {
    header("Location: login.php");
}

$top_template = Template::factory('src/templates/top.html');
$top_template->add('narrow', true)
    ->render();

$account_template = Template::factory('src/templates/reg_code_check.html');
$account_template->add('reg_code', $_GET['reg_code'])
    ->render();

?>
</body>
</html>


