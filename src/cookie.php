<?php 

define('SECONDS_IN_MINUTE', 60);
define('SECONDS_IN_HOUR', SECONDS_IN_MINUTE * 60);
define('SECONDS_IN_DAY', SECONDS_IN_HOUR * 24);

function makeCookieFromGetParams($name, $expires) {
    if (isset($_GET) &&
        isset($_GET[$name])
    ) {
        setcookie($name, $_GET[$name], $expires);
        
        return TRUE;
    }
    
    return FALSE;
}


function sniffCookie($name) {
    if (isset($_COOKIE) &&
        isset($_COOKIE[$name])
    ) {
        return $_COOKIE[$name];
    }
    
    return NULL;
}
