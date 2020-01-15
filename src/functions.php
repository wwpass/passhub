<?php

/**
 * functions.php
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
// require_once 'src/lib/wwpass.php';
require_once 'vendor/autoload.php';

// returns decoded AES key; if the user access is not confirmed yet, returns null
// TODO get_item_list_mongo exception not properly handled

//used in upgrade_user from SSE
function get_private_key()
{
    $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
    $ticket = $_SESSION['wwpass_ticket'];
    $privKey = $wwc->readData($ticket);
    return $privKey;
}

function cmp_vault_names($a, $b) {

    if (0 === strpos($a->name, "My First")) {
        if (0 === strpos($b->name, "My First")) {
            return strcasecmp($a->name, $b->name);
        }
        return -1;
    }
    if (0 === strpos($b->name, "My First")) {
        return 1;return strcasecmp($a->name, $b->name);
    }
    return strcasecmp($a->name, $b->name);
}

function getPwdFont() {
    $password_font = "Monospace";
    if (stripos($_SERVER['HTTP_USER_AGENT'], "MAC OS X") !== false) {
        $password_font = "Menlo";
    } elseif (stripos($_SERVER['HTTP_USER_AGENT'], "Windows") !== false) {
        $password_font = "Consolas";
    }
    return $password_font;
}

function update_ticket() {}

function test_ticket() {
    if (time() > ($_SESSION['wwpass_ticket_creation_time'] + WWPASS_TICKET_TTL - 10)) {
        throw new WWPass\Exception('ticket expired');
    } 
    /*
    if (time() > ($_SESSION['wwpass_ticket_creation_time'] + WWPASS_TICKET_TTL/2)) {
        passhub_err("old ticket " . $_SESSION['wwpass_ticket']);
        $t0 = microtime(true);
        $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
        $new_ticket = $wwc->putTicket($_SESSION['wwpass_ticket'], WWPASS_TICKET_TTL, WWPASS_PIN_REQUIRED?'pc':'c');

        $dt = number_format((microtime(true) - $t0),3);
        $sp = explode("@", $new_ticket)[1];

        timing_log("update " . $dt . " " . $_SERVER['REMOTE_ADDR'] . " @" . $sp);

        $_SESSION['wwpass_ticket'] = $new_ticket;
        $_SESSION['wwpass_ticket_creation_time'] = time();
        passhub_err("new ticket " .  $_SESSION['wwpass_ticket']);
        passhub_err("ticket_updated");
    }
    return  + WWPASS_TICKET_TTL/2;
    */
}

function passhub_log($message) {
    if (defined('LOG_DIR') && ($message != "")) {
        $fname = LOG_DIR . '/passhub-' . date("ymd") . ".log";
        if ($fh = fopen($fname, 'a')) {
            fwrite($fh, date("c") . " " . $message . "\n");
            fclose($fh);
        } else {
            error_log("Cannot open log file " . $fname);
        }
    }
}

function passhub_err($message) {
    if (defined('LOG_DIR') && ($message != "")) {
        $fname = LOG_DIR . '/passhub-' . date("ymd") . ".err";
        if ($fh = fopen($fname, 'a')) {
            fwrite($fh, date("c") . " "  . $_SERVER['REMOTE_ADDR'] . " " . $message . "\n");
            fclose($fh);
        } else {
            error_log("Cannot open log file " . $fname);
        }
    }
}

function timing_log($message) {
    if (defined('LOG_DIR') && ($message != "")) {
        $fname = LOG_DIR . '/timing-' . date("ymd") . ".log";
        if ($fh = fopen($fname, 'a')) {
            fwrite($fh, date("c") . " " . $message . "\n");
            fclose($fh);
        } else {
            error_log("Cannot open log file " . $fname);
        }
    }
}

function error_page($message) {
    $_SESSION['error_message'] = $message;
    // error_log("error_page message: " . $_SESSION['error_message']);
    header("Location: error_page.php");
    exit();
}

function theTwig() {
    $loader = new \Twig\Loader\FilesystemLoader('src/templates');
    return new \Twig\Environment(
        $loader, 
        [
 //           'cache' => 'src/templates/cache',
            'cache' => false,
        ]
    );
}

function message_page($title, $content) {

    echo theTwig()->render(
        'message_page.html',
        [
            'narrow' => true,
            'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
            'title' => $title,
            'content' => $content
        ]
    );
    exit();
} 

function sendLocalServer($to, $subject, $body) {

    $header = 'MIME-Version: 1.0' . "\r\n";
    if (defined('SENDMAIL_FROM') && (SENDMAIL_FROM != "")) {
        $header  .= 'From: ' . SENDMAIL_FROM . "\r\n";
        $header  .= 'Return-Path: ' . SENDMAIL_FROM . "\r\n";
    } else if (isset($_POST['host'])) {
        $header  .= 'From: noreply@' . htmlspecialchars($_POST['host']) . "\r\n";
    }
    $header .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
    $header .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
    if (!mb_detect_encoding($subject, 'ASCII', true)) {
        $subject = '=?UTF-8?B?'.base64_encode($subject).'?=';
    }

    if (defined('SENDMAIL_FROM') && (SENDMAIL_FROM != "")) {
        $result = mail($to, $subject, $body, $header, '-f' . SENDMAIL_FROM); 
    } else {
        $result = mail($to, $subject, $body, $header); 
    }

    error_log('sendLocalServer ' . ( $result ? 'Ok' : 'fail'));
    return $result ? ['status' => 'Ok'] : ['status' => 'fail'];
}

function sendSMTP($to, $subject, $body) {
    $from = '<' . SMTP_SERVER["username"] . '>';
    $to = '<' . $to . '>';
  
    $headers = array(
        'From' => $from,
        'To' => $to,
        'Subject' => $subject,
        'MIME-Version' => 1,
        'Content-type' => 'text/html;charset=UTF-8'
    );
  
    $smtp = Mail::factory('smtp', SMTP_SERVER);
  
    $mail = $smtp->send($to, $headers, $body);
  
    if (PEAR::isError($mail)) {
        error_log('sendSMTP error ');
        passhub_err($mail->getMessage());
        return ['status' => 'fail'];
    }
    return ['status' => 'Ok'];
}

function sendMail($to, $subject, $body) {
    if (defined('SMTP_SERVER')) {
        return sendSMTP($to, $subject, $body);
    }
    return sendLocalServer($to, $subject, $body);
}

/*
function humanReadableFileSize( $bytes) {
    if ($bytes < 10*1024) {
        return $bytes . " Bytes";
    }
    if ($bytes < 10*1024*1024) {
        return (int)($bytes/1024) . " kB";
    }
    return (int)($bytes/1024/1024) . " MB";
}
*/

function humanReadableFileSize($bytes)
{
    if ($bytes == 0)
        return "0 B";

    $s = array(' B', ' KB', ' MB', ' GB', ' TB', ' PB');
    $e = floor(log($bytes, 1024));

    return round($bytes/pow(1024, $e), 2).$s[$e];
}
