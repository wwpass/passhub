<?php

/**
 * notsupported.php
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
require_once 'src/template.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

session_destroy();

passhub_err("not supported browser " . $_SERVER['HTTP_USER_AGENT']);

if (isset($_GET['js']) && ($_GET['js'] == 2)) {
    $h1_text = "The site is misconfigured";
    $advise = "Ask administrator to switch to HTTPS protocol";
} else {
    $h1_text = "Sorry, your browser is not supported";
    $advise = "Please use latest versions of Chrome or Firefox browsers";
}

$top_template = Template::factory('src/templates/top.html');
$top_template->add('narrow', true)
    ->add('hide_logout', true)
    ->render();

$notsupported_template = Template::factory('src/templates/notsupported.html');
$notsupported_template->add('h1_text', $h1_text)
    ->add('advise', $advise)
    ->render();

?>
</body>
</html>


