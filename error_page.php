<?php

/**
 * error_page.php
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

passhub_err(
    'error_page: ' . print_r($_GET, true)
    . 'agent ' . $_SERVER['HTTP_USER_AGENT']
);

Template::factory('src/templates/top.html')
    ->add('narrow', true)
    ->render();

$error_template = Template::factory('src/templates/error_page.html');

if (isset($_GET['js']) && ($_GET['js'] == "SafariPrivateMode")) {

    $header = "Your Safari browser is&nbsp;in Private mode";
    $text = "<p style = 'font-size: larger'>Please follow"
        . " <a href = 'https://support.apple.com/en-us/HT203036'>Apple's instructions</a>"
        . " to&nbsp;return to&nbsp;normal mode</p>"
        . "<p>Private mode is not compatible with Passhub, some important browser functions are disabled</p>";
    $error_template->add('header', $header)
        ->add('text', $text)
        ->render();
} else {
    $header = "Error";
    $text=$_SESSION['error_message'];
    $js387 = false;

    if (isset($_GET['js']) ) {
        if ($_GET['js'] == 387) {
            $header = "Error 387";
            $js387 = true;
            if (isset($_GET['error'])) {
                $_SESSION['error_message']=$_GET['error'];
            } else {
                $_SESSION['error_message']="";
            }

            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                if (stripos($_SERVER['HTTP_USER_AGENT'], "iPod")
                    || stripos($_SERVER['HTTP_USER_AGENT'], "iPhone")
                    || stripos($_SERVER['HTTP_USER_AGENT'], "iPad")
                    || stripos($_SERVER['HTTP_USER_AGENT'], "Android")
                ) {
                    $js387 = 'mobile';
                }
            }
            passhub_err("Error 387:" . $_SESSION['error_message']);
            $text = "<p>Possible reasons</p>"
                . "<ul>"
                . "<li>your browser is in private (incognito) mode</li>"
                . "<li>you started PassHub in non-default browser</li>"
                . "</ul>"
                . "<p>Try again in this (default) browser</p>";

        } else if ($_GET['js'] == 'timeout') {
            $header = "Crypto operation takes too long";
            $text = $_SESSION['error_message'] = 'Try to relogin later or check your network quality. (Your data is safe)';
            passhub_err("Crypto timeout");
        } else {
            $header = "Internal server error";
            $text=$_SESSION['error_message'] = 'Consult system administrator';
        }
    }
    passhub_err("error_page message: " . $_SESSION['error_message']);
    $error_template->add('header', $header);

    if (isset($_SESSION['error_message']) && (trim($_SESSION['error_message']) != "")) {
        $error_template->add('text', '<p>' . $text . '</p>');
        $error_template->add('js387', $js387);
    } else {
        passhub_err("error_page 35");
        $error_template->add('text', "");
    }
    $error_template->render();
}

?>
</div>
</div>
</body>
</html>


