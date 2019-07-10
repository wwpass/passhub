<?php

/**
 * registration_action.php
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
require_once 'Mail.php';
require_once 'src/functions.php';
require_once 'src/db/user.php';
require_once 'src/template.php';
require_once 'src/db/iam_ops.php';

require_once 'src/db/SessionHandler.php';

$mng = newDbConnection();

setDbSessionHandler($mng);

session_start();

/*
if(!isset($_POST['verifier']) || !User::is_valid_csrf($_POST['verifier'])) {
    http_response_code(400);
    echo "Bad Request (26)";
    exit();
}
*/

if (!defined('MAIL_DOMAIN')) {
    passhub_err("mail domain not defined");
    error_page("Internal error");
}

$mail_domains = preg_split("/[\s,]+/", strtolower(MAIL_DOMAIN));
$email = $_POST['email'];
$url = strtolower($_POST['base_url']);

$parts = explode("@", $email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = "Invalid e-mail address: " . htmlspecialchars($email);
} else if (count($parts) != 2) {
    $error_msg = "Invalid e-mail address: " . htmlspecialchars($email);
} else if (!in_array(strtolower($parts[1]), $mail_domains) && !is_invited($mng, $email)) {
    $error_msg = "<p>The e-mail address " .  htmlspecialchars($email) . " cannot be used to create an account.</p><p> Please contact your system administrator.</p>";
} else {
    $result = getRegistrationCode($mng, $_SESSION['PUID'], $email);
    if ($result['status'] == "Ok") {
        $subject = "PassHub Account Activation";
        $body = "<p>Dear PassHub Customer,</p> <p>Please click the link below to activate your account:</p>"
         . "<a href=" . $url . "login.php?reg_code=" . $result['code'] . ">"
         . $url . "login.php?reg_code=" . $result['code'] . "</a>"

         . "<p>Best regards,<br>PassHub Team.</p>"; 

         $result = sendMail($email, $subject, $body);
 
        passhub_log('verification mail sent to ' . $email);
        $_SESSION = [];
        passhub_err(print_r($result, true));
        $sent = true;
        if ($result['status'] !== 'Ok') {
            passhub_err("error sending email");
            error_page("error sending email. Please try again later");
            $sent = false;
        }

    } else {
        passhub_err("error getting registration code: ", $result['status']);
        $error_msg = $result['status'];
    }
}

if (!isset($error_msg)) {
    $_SESSION['form_email'] = htmlspecialchars($email);
    passhub_err(print_r($_SESSION, true));
    header('Location: form_filled.php?registration_action');
    exit();
}

$top_template = Template::factory('src/templates/top.html');
$top_template->add('hide_logout', true)
    ->add('narrow', true)
    ->render();

$request_mail_template  = Template::factory('src/templates/request_mail.html');
passhub_err($error_msg);
$request_mail_template->add('error_msg', $error_msg)
    ->render();

?>

</div>
</div>
</body>
</html>
