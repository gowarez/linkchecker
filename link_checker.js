// encoding: utf-8

/*
 * GoWarez.org Link Checker for SMF 2.0
 * Copyright © 2009 GoWarez.org.
 */

function observe_event(element, event_name, listener) {
    if (element.addEventListener) {
        element.addEventListener(event_name, listener, false);
    } else if (element.attachEvent) {
        element.attachEvent("on" + event_name, listener);
    }
}

function make_request(uri, options) {
    if (!options)
        options = {};
    var method = options.method || 'GET';
    var async = (typeof(options.async) != 'undefined') ? options.async : true;
    
    var req = new XMLHttpRequest();
    
    req.onreadystatechange = function request_state_change() {
        if (req.readyState != 4)
            return;
        
        var success = (req.status && req.status >= 200 && req.status < 300);
        if (success && options.success)
            options.success(req);
        else if (!success && options.failure)
            options.failure(req);
        
        if (options.complete)
            options.complete(req);
    };
    
    req.open(method, uri, async);
    req.send(options.body || null);
    return req;
}

function get_previous_element(element) {
    var node;
    for (node = element.previousSibling; node; node = node.previousSibling) {
        if (node.nodeType == 1)
            return node;
    }
    return null;
}

var _code_header = /\bcodeheader\b/;
function get_code_blocks() {
    var code = document.getElementsByTagName('CODE');
    var blocks = [];
    
    var i, sib;
    for (i = 0; i < code.length; i++) {
        sib = get_previous_element(code[i]);
        if (sib && _code_header.test(sib.className)) {
            blocks.push([sib, code[i]]);
        }
    }
    
    return blocks;
}

var _uri = /^(https?:)?(\/\/([^\/?#]*))?([^?#\n]*)(\?([^#\n]*))?(#([^\n]*))?/gm;
function get_uris(target) {
    if (typeof(target) != 'string')
        target = (target.innerText || target.textContent);
    
    var uris = [];
    var result;
    while (result = _uri.exec(target)) {
        uris.push(result[0]);
    }
    
    return uris;
}

var _code_operation = /\bcodeoperation\b/;
function get_last_codeop(header) {
    var c;
    for (c = header.lastChild; c; c = c.previousSibling) {
        if (_code_operation.test(c.className))
            return c;
    }
    
    return header.lastChild;
}

function image_path(image) {
    return smf_images_url + '/linkcheck/' + image;
}

var _gwlc_images = {
    'loading': image_path('loading.gif'),
    'broken': image_path('broken.gif'),
    'good': image_path('good.gif'),
    'error': image_path('error.gif')
};

observe_event(window, 'load', function make_links_checkable() {
    var blocks = get_code_blocks();
    
    // Preload our icons.
    var name;
    for (name in _gwlc_images) {
        new Image().src = _gwlc_images[name];
    }
    
    function check(wrapper_span, completion_callback) {
        var uri = wrapper_span.innerText || wrapper_span.textContent;
        var check_uri = smf_scripturl + '?action=linkcheck.ajax&uri=' +
            escape(uri);
        wrapper_span.className += ' testing_link';
        
        var req = make_request(check_uri, {
            success: function link_checked(req) {
                wrapper_span.className = wrapper_span.className.replace(
                    ' testing_link', '');
                if (req.responseText == "OK") {
                    wrapper_span.className += ' good_link';
                } else {
                    wrapper_span.className += ' broken_link';
                }
                
                if (completion_callback)
                    completion_callback();
            },
            
            failure: function link_check_failed(req) {
                wrapper_span.className = wrapper_span.className.replace(
                    'testing_link', 'test_failure');
                
                if (completion_callback)
                    completion_callback();
            }
        });
    }
    
    function create_check_link(content) {
        var link = content.ownerDocument.createElement('A');
        link.className = 'linkchecker codeoperation';
        link.href = '#check';
        link.innerHTML = "[Check Links]";
        link.title = "Click here to check if the links below are still valid.";
        
        var anchors = content.getElementsByTagName('A');
        observe_event(link, 'click', function check_links(ev) {
            if (!ev)
                ev = window.event;
            if (ev.preventDefault)
                ev.preventDefault();
            
            var links = [];
            var i;
            for (i = 0; i < anchors.length; i++) {
                if (anchors[i].className == 'detected_link')
                    links.push(anchors[i]);
            }
            
            i = 0;
            function check_next() {
                if (!links[i])
                    return;
                
                check(links[i++], function() { setTimeout(check_next, 200); });
            }
            setTimeout(check_next, 10);
            
            return false;
        });
        
        return link;
    }
    
    function modify_block(header, content) {
        var uris = get_uris(content);
        if (uris.length <= 0)
            return;
        
        var html = content.innerHTML;
        var i;
        for (i = 0; i < uris.length; i++) {
            html = html.replace(uris[i], '<a class="detected_link">' +
                uris[i] + '</a>');
        }
        content.innerHTML = html;
        
        var links = content.getElementsByTagName('A');
        var link;
        for (i = 0; i < links.length; i++) {
            link = links[i];
            if (link.className == 'detected_link') {
                link.href = (link.innerText || link.textContent);
            }
        }
        
        var target = get_last_codeop(header).nextSibling;
        header.insertBefore(document.createTextNode(" "), target);
        header.insertBefore(create_check_link(content), target);
    }
    
    var i, block;
    for (i = 0; i < blocks.length; i++) {
        block = blocks[i];
        modify_block(block[0], block[1]);
    }
});
