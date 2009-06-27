<?php

/*
 * GoWarez.org Link Checker for SMF 2.0
 * Copyright © 2009 GoWarez.org.
 */

function _gwlc_get_behavior($uri) {
    static $overrides = array(
        'rapidshare.com' => '_gwlc_check_rapidshare'
    );
    
    $parsed = @parse_url($uri);
    if (!$parsed)
        return false;
    $host = preg_replace('/^(?:www|web)\./', '', strtolower($parsed['host']));
    
    foreach ($overrides as $pattern => $fn) {
        if (preg_match('/^#.*#i?$/', $pattern)) {
            // PCRE pattern
            if (preg_match($pattern, $uri))
                return $fn;
        } else {
            // Hostname
            if ($host == $pattern)
                return $fn;
        }
    }
    
    // No match; use default behavior.
    return '_gwlc_check_default';
}

function gwlc_check_link($uri) {
    $behavior = _gwlc_get_behavior($uri);
    if (!$behavior)
        return false;

}
