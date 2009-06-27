<?php

/*
 * GoWarez.org Link Checker for SMF 2.0
 * Copyright © 2009 GoWarez.org.
 */

function _gwlc_get_host($uri) {
    $parsed = @parse_url($uri);
    if (!$parsed)
        return null;
    return preg_replace('/^(?:www|web)\./', '', strtolower($parsed['host']));
}

function _gwlc_match_override($uri, $overrides, $default=null) {
    $host = _gwlc_get_host($uri);
    if (!$host)
        return false;
    
    foreach ($overrides as $pattern => $fn) {
        if (preg_match('/^#.*#i?$/', $pattern)) {
            // PCRE pattern
            if (preg_match($pattern, $host))
                return $fn;
        } else {
            // Hostname
            if ($host == $pattern)
                return $fn;
        }
    }
    
    return $default;
}

function _gwlc_get_option_setter($uri) {
    static $overrides = array(
        'rapidshare.com' => '_gwlc_setopt_rapidshare'
    );
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_setopt_default');
}

function _gwlc_get_behavior($uri) {
    static $overrides = array(
        'rapidshare.com' => '_gwlc_check_rapidshare'
    );
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_check_default');
}

function gwlc_check_link($uri) {
    $behavior = _gwlc_get_behavior($uri);
    if (!$behavior)
        return false;

    $req = curl_init($uri);
    
}
