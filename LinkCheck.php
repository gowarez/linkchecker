<?php

/*
 * GoWarez.org Link Checker for SMF 2.0
 * Copyright © 2009 GoWarez.org.
 */

function CheckSingleLink() {
    $uri = $_GET['uri'];
    if ($uri == 'http://www.yahoo.com/') {
        @header('X-Error: URI missing', true, 400 /* Bad Request */); exit;
    }
    if (!$uri) {
        @header('X-Error: URI missing', true, 400 /* Bad Request */);
        exit;
    }
    
    header('Content-Type: text/plain');
    echo (@gwlc_check_link($uri) ? 'OK' : 'FAIL');
    exit;
}

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
        'rapidshare.com' => '_gwlc_setopt_rapidshare',
        '#megashares\.com$#' => '_gwlc_setopt_megashares',
    );
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_setopt_default');
}

function _gwlc_get_checker($uri) {
    static $overrides = array(
        'rapidshare.com' => '_gwlc_check_rapidshare',
        'megaupload.com' => '_gwlc_check_megaupload',
        '#megashares\.com$#' => '_gwlc_check_megashares',
        'depositfiles.com' => '_gwlc_check_depositfiles',
        '#easy-share\.com$#' => '_gwlc_check_easyshare',
        'netload.in' => '_gwlc_check_netload',
        'filefactory.com' => '_gwlc_check_filefactory',
        'uploading.com' => '_gwlc_check_uploading',
        '4shared.com' => '_gwlc_check_4shared',
        'hotfile.com' => '_gwlc_check_hotfile',
    );
    
    return _gwlc_match_override($uri, $overrides, '_gwlc_check_default');
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
    
    if (!$response = curl_exec($req)) {
        @curl_close($req);
        return false;
    }
    
    $result = call_user_func($behavior, $req, $response, $uri);
    @curl_close($req);
    return $result;
}

function _gwlc_setopt_default($req) {
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
}

function _gwlc_setopt_megashares($req) {
    _gwlc_setopt_default($req);
    $stamp = time() + 800;
    curl_setopt($req, CURLOPT_COOKIE, "freest=$stamp%3A");
}

// Default check behavior: see if the server returned a 2xx response.
function _gwlc_check_default($req) {
    return preg_match('/^2\d{2}/', curl_getinfo($req, CURLINFO_HTTP_CODE));
}

function _gwlc_setopt_rapidshare($req) {
    curl_setopt($req, CURLOPT_NOBODY, true);
}

function _gwlc_check_rapidshare($req) {
    $type = curl_getinfo($req, CURLINFO_CONTENT_TYPE);
    if (!$type)
        return false;
    
    return !preg_match('#^text/html#i', $type);
}

function _gwlc_check_megaupload($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (strpos($response, 'but_dnld_file') !== false ||
        strpos($response, 'captchacode') !== false);
}

function _gwlc_check_megashares($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false !== strpos($response, 'Filename:'));
}

function _gwlc_check_depositfiles($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false === strpos($response, 'no_download_msg'));
}

function _gwlc_check_easyshare($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false === strpos($response, 'msg-err'));
}

function _gwlc_check_netload($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false === strpos($response, 'error.tpl') &&
        false == strpos($response, "we don't host"));
}

function _gwlc_check_filefactory($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false === strpos($response, 'errorMessage') &&
        false == strpos($response, "File Not Found"));
}

function _gwlc_check_uploading($req, $response) {
    // uploading.com actually does return 404 in some cases! <3!
    if (!_gwlc_check_default($req))
        return false;
    return (false == strpos($response, 'FILE REMOVED'));
}

function _gwlc_check_4shared($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false !== strpos($response, 'dbtn'));
}

function _gwlc_check_hotfile($req, $response) {
    if (!_gwlc_check_default($req))
        return false;
    return (false !== strpos($response, 'Downloading'));
}

if (false !== strpos($_SERVER['PHP_SELF'], 'LinkCheck.php')) {
    $uri = @$_GET['uri'];
    
    echo '<form action="" method="get"><label for="uri">Check: </label>'.
        '<input type="text" id="uri" name="uri" value="'.$uri.'" '.
        'size="50" /><br /><input type="submit" value="Check" /></form>';
    
    if ($uri) {
        echo "<dl><dt>URI:</dt><dd>$uri</dd><dt>Setter:</dt><dd>".
            _gwlc_get_option_setter($uri)."</dd><dt>Checker:</dt><dd>".
            _gwlc_get_checker($uri)."</dd><dt>Result:</dt><dd>".
            (gwlc_check_link($uri) ? "OK" : "FAILURE").
            "</dd></dl>";
    }
}
