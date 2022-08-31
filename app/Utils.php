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

class Utils
{

    private static function sendLocalServer($to, $subject, $body, $contentType) {

        $header = 'MIME-Version: 1.0' . "\r\n";
        if (defined('SENDMAIL_FROM') && (SENDMAIL_FROM != "")) {
            $header  .= 'From: ' . SENDMAIL_FROM . "\r\n";
            $header  .= 'Return-Path: ' . SENDMAIL_FROM . "\r\n";
        } else if (isset($_POST['host'])) {
            $header  .= 'From: noreply@' . htmlspecialchars($_POST['host']) . "\r\n";
        }
        
        $header .= 'Content-type: ' . $contentType . "\r\n";

        $header .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
        if (!mb_detect_encoding($subject, 'ASCII', true)) {
            $subject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        }

        if (defined('SENDMAIL_FROM') && (SENDMAIL_FROM != "")) {
            $result = mail($to, $subject, $body, $header, '-f' . SENDMAIL_FROM); 
        } else {
            $result = mail($to, $subject, $body, $header); 
        }

        return $result ? ['status' => 'Ok'] : ['status' => 'fail'];
    }


    private static function sendMailJet($to, $subject, $body, $contentType) {
        Utils::err("Mailjet");
        
//        require 'vendor/autoload.php';
//        use \Mailjet\Resources;
            $mj = new \Mailjet\Client(SMTP_SERVER["username"],SMTP_SERVER["password"],true,['version' => 'v3.1']);
            $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => SMTP_SERVER["from"]
//                        'Name' => "Me"
                    ],
                    'To' => [
                        [
                            'Email' => $to,
//                            'Name' => "You"
                        ]
                    ],
                    'Subject' => $subject,
                    // 'TextPart' => $body,
                    'HTMLPart' => $body
                ]
            ]
        ];
        $response = $mj->post( \Mailjet\Resources::$Email, ['body' => $body]);
        Utils::err(print_r($response->getData(), true));

        if($response->success()) {
            return ['status' => 'Ok'];
        }
        return ['status' => 'fail'];
    }

    private static function sendSMTP($to, $subject, $body, $contentType) {

        if(isset(SMTP_SERVER["host"]) && stristr(SMTP_SERVER["host"],"in-v3.mailjet.com")) {
            return self::sendMailJet($to, $subject, $body, $contentType);
        }
        $from = '<' . SMTP_SERVER["username"] . '>';
        if(isset(SMTP_SERVER["from"])) {
            $from = '<' . SMTP_SERVER["from"] . '>';
        }


        $to = '<' . $to . '>';
    
        $headers = array(
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
            'MIME-Version' => 1,
            'Content-type' => $contentType
        );
        Utils::err(print_r($headers, true));
    
        $smtp = \Mail::factory('smtp', SMTP_SERVER);
    
        $mail = $smtp->send($to, $headers, $body);
    
        if (\PEAR::isError($mail)) {
            self::err('sendSMTP error ' . $mail->getMessage());
            return ['status' => 'fail'];
        }
        return ['status' => 'Ok'];
    }

    public static function sendMail($to, $subject, $body, $contentType = 'text/html; charset=UTF-8') {

        if(!Utils::valid_origin()) {
            return ['status' => 'Ok'];
        }
        if(Utils::blacklisted()) {
            return ['status' => 'Ok'];
        }


        if (defined('SMTP_SERVER')) {
            return self::sendSMTP($to, $subject, $body, $contentType);
        }
        return self::sendLocalServer($to, $subject, $body, $contentType);
    }

    public static function render(string $template, array $context = []): string
    {
        $loader = new \Twig\Loader\FilesystemLoader('views');
        $twig = new \Twig\Environment(
            $loader, 
            [
                // 'cache' => 'views/cache',
                'cache' => false,
            ]
        );
        return $twig->render($template, $context);        
    }

    public static function log($message) {
        if (defined('LOG_DIR') && ($message != "")) {
            $fname = LOG_DIR . '/passhub-' . date("ymd") . ".log";
            if ($fh = fopen($fname, 'a')) {
                fwrite($fh, date("c") . " " . $message . "\n");
                fclose($fh);
            } else {
                error_log("Cannot open log file " . $fname);
            }
        }
        if (defined('SYSLOG') && SYSLOG) {
            openlog("passhub", LOG_NDELAY, LOG_USER);
            syslog(LOG_INFO, $message);
            closelog();
        }
    }

    public static function err($message) {
        if (defined('LOG_DIR') && ($message != "")) {
            $fname = LOG_DIR . '/passhub-' . date("ymd") . ".err";
            if ($fh = fopen($fname, 'a')) {
                fwrite($fh, date("c") . " "  . $_SERVER['REMOTE_ADDR'] . " " . $message . "\n");
                fclose($fh);
            } else {
                error_log("Cannot open err log file " . $fname);
            }
        }
        if (defined('SYSLOG') && SYSLOG) {
            openlog("passhub", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            syslog(LOG_ERR, $message);
            closelog();
        }
    }

    public static function timingLog($message) {
        if (defined('LOG_DIR') && ($message != "")) {
            $fname = LOG_DIR . '/timing-' . date("ymd") . ".log";
            if ($fh = fopen($fname, 'a')) {
                fwrite($fh, date("c") . " " . $message . "\n");
                fclose($fh);
            } else {
                error_log("Cannot open time log file " . $fname);
            }
        }
    }

    public static function errorPage($message) {
        $_SESSION['error_message'] = $message;
        self::err("error_page message: " . $_SESSION['error_message']);
        header("Location: error_page.php");
        exit();
    }

    public static function messagePage($title, $content) {

        Utils::render(
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

    public static function testTicket() {
        if (time() > ($_SESSION['wwpass_ticket_creation_time'] + WWPASS_TICKET_TTL - 10)) {
            throw new \WWPass\Exception('ticket expired');
        }
    } 

    public static function getPwdFont() {
        $password_font = "Monospace";
        if (stripos($_SERVER['HTTP_USER_AGENT'], "MAC OS X") !== false) {
            $password_font = "Menlo";
        } elseif (stripos($_SERVER['HTTP_USER_AGENT'], "Windows") !== false) {
            $password_font = "Consolas";
        }
        return $password_font;
    }

    public static function humanReadableFileSize($bytes) {
        if ($bytes == 0) {
            return "0 B";
        }
        $s = array(' B', ' KB', ' MB', ' GB', ' TB', ' PB');
        $e = floor(log($bytes, 1024));
        return round($bytes/pow(1024, $e), 2).$s[$e];
    }


    public static function showCreateUserPage() {

        self::log("Create User CSE begin " . $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['HTTP_USER_AGENT']);

        $template_safes = file_get_contents('config/template.xml');
        
        if (strlen($template_safes) == 0) {
            self::err("template.xml absent or empty");
            self::errorPage("Internal error. Please come back later.");
        }
        
        echo self::render(
            'r-upsert_user.html', 
            [
                // layout
                'narrow' => true, 
                'PUBLIC_SERVICE' => defined('PUBLIC_SERVICE') ? PUBLIC_SERVICE : false, 
                'upgrade' => false,
                'ticket' => $_SESSION['wwpass_ticket'],
                'template_safes' => json_encode($template_safes)
            ]
        );
    }

    /**
     * Returns decoded AES key; if the user access is not confirmed yet, returns null
     * TODO get_item_list_mongo exception not properly handled
     * used in upgrade_user from SSE
     */
    public static function getPrivateKey()
    {
        $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
        $ticket = $_SESSION['wwpass_ticket'];
        $privKey = $wwc->readData($ticket);
        return $privKey;
    }

    function message_page($title, $content) {

        echo Utils::render(
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


    public static function render_react(string $template, array $context = []): string
    {
        $loader = new \Twig\Loader\FilesystemLoader('frontend');
        $twig = new \Twig\Environment(
            $loader, 
            [
                // 'cache' => 'views/cache',
                'cache' => false,
            ]
        );
        return $twig->render($template, $context);        
    }


    public static function blacklisted() {
        if (defined('IP_BLACKLIST') && is_array(IP_BLACKLIST)) {
            if (in_array($_SERVER['REMOTE_ADDR'], IP_BLACKLIST)) {
                Utils::err('blacklisted ' . $_SERVER['REMOTE_ADDR']);
                return true;
            }
        }
        return false;
    }
    
    public static function valid_origin() {
        Utils::err('check origin: server name  ' .  $_SERVER['SERVER_NAME'] . ' origin ' . $_SERVER['HTTP_ORIGIN'] );

        if(isset($_SERVER['HTTP_ORIGIN']) && is_string($_SERVER['HTTP_ORIGIN']) && (strlen(trim($_SERVER['HTTP_ORIGIN']) ) >0)) {
            return true;
        }
        Utils::err('origin not valid');
        return false;
    }
}
