<?php

function get_lang($accept_lang) {
    $list = '';
    if (preg_match_all('/([A-z\*]{1,8}(?:-[A-z]{1,8})?)(?:;q=([0-9.]+))?/', $accept_lang, $list)) {
        $lang = array_combine($list[1], $list[2]);
        foreach ($lang as $n => $v) {
            $lang[$n] = $v ? $v : 1;
        }
        
        arsort($lang, SORT_NUMERIC);
    }

    return $lang;
};


function get_best_match($langs, $supported = array('de', 'en'), $default = 'de') {
    if ( ! count($langs)) {
        return $default;
    }
    
    $lang_keys = array_keys($langs);
    $len = count($lang_keys);

    $i = 0;
    $first_pick = FALSE;

    do {
        $first_pick = array_search($lang_keys[$i], $supported, true);
        $i++;
    } while ( $first_pick === FALSE and $i < $len);

    if ($first_pick === FALSE) {
        return $default;
    }

    return $supported[$first_pick];
};



