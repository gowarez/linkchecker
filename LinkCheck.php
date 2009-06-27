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
    static $overrides = array();
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_setopt_default');
}

function _gwlc_get_checker($uri) {
    static $overrides = array(
        'rapidshare.com' => '_gwlc_check_rapidshare'
    );
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_check_default');
}

function _gwlc_setopt_default($req) {
    curl_setopt($req, CURLOPT_NOBODY, true);
}

function gwlc_check_link($uri) {
    $setter = _gwlc_get_option_setter($uri);
    $behavior = _gwlc_get_checker($uri);
    if (!$setter || !$behavior)
        return false;

    $req = curl_init($uri);
    curl_setopt($req, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($req, CURLOPT_MAXREDIRS, 6);
    
    call_user_func($setter, $req, $uri);
    
    if (!curl_exec($req)) {
        @curl_close($req);
        return false;
    }
    
    $result = call_user_func($behavior, $req, $uri);
    @curl_close($req);
    return $result;
}

// Default check behavior: see if the server returned a 2xx response.
function _gwlc_check_default($req) {
    return preg_match('/^2\d{2}/', curl_getinfo($req, CURLINFO_HTTP_CODE));
}

function _gwlc_check_rapidshare($req) {
    $type = curl_getinfo($req, CURLINFO_CONTENT_TYPE);
    if (!$type)
        return false;
    
    return !preg_match('#^text/html#i', $type);
}

if (false !== strpos($_SERVER['PHP_SELF'], 'LinkCheck.php')) {
    $uri = @$_GET['uri'];
    
    echo '<form action="" method="get"><label for="uri">Check: </label>'.
        '<input type="text" id="uri" name="uri" value="'.$uri.'" '.
        'size="50" /><br /><input type="submit" value="Check" /></form>';
    
    if ($uri) {
        echo "<dl><dt>URI:</dt><dd>$uri</dd><dt>Setter:</dt><dd>".
            _gwlc_get_option_setter($uri)."</dd><dt>Checker</dt><dd>".
            _gwlc_get_checker($uri)."</dd><dt>Result:</dt><dd>".
            (gwlc_check_link($uri) ? "OK" : "FAILURE").
            "</dd></dl>";
    }
}
