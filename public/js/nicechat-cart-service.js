/*
 * @package    NiceChat_to_WP (NiceChat connector script)
 * @version    0.1.2
 * 
 */


var nice_chat_cart_items = {'hash': '', 'data': []};
var nice_chat_cart_last_attempt = new Date(2000, 1, 1);

if (window['localStorage']) {
    var jj = window.localStorage['nice_chat_cart_items']
    if (jj !== undefined) {
        try {
            var trx = JSON.parse(jj);
            if (trx['hash'] === nice_chat_get_cart_cookie()) {
                nice_chat_cart_items = trx;
            } else {
                nice_chat_real_request_cart();
            }
        } catch (err) {};
    }
}

var nice_chat_get_cookie_by_name = function(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ')
            c = c.substring(1);
        if (c.indexOf(nameEQ) != -1)
            return c.substring(nameEQ.length,c.length);
    }
    return null;
};

var nice_chat_get_cart_cookie = function(name) {
    return nice_chat_get_cookie_by_name('woocommerce_cart_hash');
};

var nice_chat_real_request_cart = function() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?wc-ajax=nice_chat_cart__get');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            var not_blink = nice_chat_cart_items['hash'] === data['hash'] || nice_chat_cart_items['hash'] === '';
            nice_chat_cart_items = data;
            if (window['nice_chat_cart_changed'] !== undefined) {
                window.nice_chat_cart_changed(not_blink);
            }
            if (window['localStorage']) {
                window.localStorage['nice_chat_cart_items'] = JSON.stringify(data);
            }

        }
    };
    xhr.send();
};

var nice_chat_monitor_cookies = function() {
    setTimeout(nice_chat_monitor_cookies, 200);
    if (nice_chat_cart_items['hash'] !== nice_chat_get_cart_cookie()) {
        if (new Date() - nice_chat_cart_last_attempt > 5000) {
            nice_chat_real_request_cart();
            nice_chat_cart_last_attempt = new Date();
        }
    }
};

window.nice_chat_push_product = function(p_id, price, curr, attr) {
    var mode = attr !== undefined && attr !== null && attr['type'] !== undefined ? attr['type'] : 'std';

    var post = {};
    var to_url = '?wc-ajax=add_to_cart';
    if (mode === 'std') {
        post['product_id'] = p_id;
        post['quantity'] = 1;
    } else {
        post['add-to-cart'] = attr['master_id'];
        post['variation_id'] = p_id;
        post['quantity'] = 1;
        for(var k in attr) {
            if (k.substring(0,5) === 'attr_') 
                post['attribute_' + k.substring(5)] = attr[k];
         }
         to_url = "?"
    }

    var out = [];
    for(var key in post){
        out.push(encodeURIComponent(key) + '=' + encodeURIComponent(post[key]));
    }
    var post_data = out.join('&');


    var xhr = new XMLHttpRequest();
    xhr.open('POST', to_url);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send(post_data);
};

window.nice_chat_checkout_cart = function() {
    location.href = '?wc-ajax=nice_chat_cart__go_check';
};

window.nice_chat_request_cart_state = function() {
    if (nice_chat_cart_items['hash'] === nice_chat_get_cart_cookie()) {
        return nice_chat_cart_items['data'];
    } else {
        nice_chat_real_request_cart(true);
        return [];
    }
};

nice_chat_monitor_cookies();
