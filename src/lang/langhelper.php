<?php

require_once('config/config.php');
require_once('lang.php');

$user_lang = NULL;

function setDefaultSiteLanguage ($lang) {
    $user_lang = get_best_match(get_lang($lang));
    setcookie('site_lang', $user_lang);
}

function getDefaultSiteLanguage () {
    if (array_key_exists('lang', $_GET)) {
        $request_lang = $_GET['lang'];
        $lang = get_best_match(get_lang($request_lang));
        setDefaultSiteLanguage($lang);
        return $lang;
    }

    if (array_key_exists('site_lang', $_COOKIE)) {
        $cookie_lang = $_COOKIE['site_lang'];
        return get_best_match(get_lang($cookie_lang));
    }

    $accept_language = array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER) ?
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] :
        DEFAULT_LANGUAGE;
    
    $lang = get_best_match(get_lang($accept_language));
    setDefaultSiteLanguage($lang);
    return $lang;
}

function getUserLanguage () {
    global $user_lang;
    if (! $user_lang) {
        $user_lang = getDefaultSiteLanguage();
    }
    
    return $user_lang;
}


class Pathfinder
{
    function __construct($templates_path, $lang, $fallback_to_en = TRUE) {
        $this->templates_path = $templates_path;
        $this->lang = $lang;
        $this->fallback_to_en = $fallback_to_en;
    }

    static function factory($templates_path = DEFAULT_TEMPLATES_ROOT, $lang = FALSE, $fallback_to_en = TRUE) {
        $path = realpath($templates_path);

        if (file_exists($path)) {
            return new Pathfinder($path, $lang);
        }

        throw new Exception("No such directory $templates_path", 2);
    }

    static function createDefault() {
        return Pathfinder::factory(DEFAULT_TEMPLATES_ROOT, getUserLanguage());
    }

    public function getPath($filename) {
        if ($this->lang) {
            $full_path = $this->templates_path . '/' . $this->lang . '/' . $filename;

            if (file_exists($full_path)) {
                return $full_path;
            } elseif ($this->fallback_to_en) {
                $full_path = $this->templates_path . '/' . $filename;
                return $full_path;
            }

            throw new Exception("No such file $full_path", 3);
        }

        $full_path = $this->templates_path . '/' . $filename;
        return $full_path;
    }
}
