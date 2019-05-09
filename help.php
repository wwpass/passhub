<?php

/**
 * help.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2017-2018 WWPass
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

$top_template = Template::factory('src/templates/top.html');
$top_template->add('hide_logout', !isset($_SESSION['PUID']))
    ->add('help_page', true)
    ->add('narrow', true)
    ->render();

if (defined('PUBLIC_SERVICE') && (PUBLIC_SERVICE == true)) {
    $help_template = Template::factory('src/templates/help-pub.html');
} else {
    $help_template = Template::factory('src/templates/help.html');

}  

$help_template->render();

?>

</div>
</div>

</body>
</html>
