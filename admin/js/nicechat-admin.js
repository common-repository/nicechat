
window.start_products_export = function() {
	var nice_chat_wp_base_url = window['nice_chat_wp_ajaxurl'] !== undefined ? window['nice_chat_wp_ajaxurl'] : '/wp-admin/admin-ajax.php';
	
	var poll = function($key) {
		$url = nice_chat_wp_base_url + "?action=product_export__loop&key=" + $key;
	    jQuery.ajax({
	        url: $url,
	        type: "GET",
	        success: function(data) {
	            if (data) {
            		if (data.nice_chat_status === 'ok') {
		        	    setTimeout(function() {poll($key)}, 500);
		        	}
	            	if (data.nice_chat_status === 'ok' || data.nice_chat_status === 'done') {
			            if (data['msg'] !== undefined && data['msg'] !== null) {
			            	jQuery('#nice_chat_log_errors').append(jQuery("<span/>").text(data['msg']).append(jQuery("<br/>")));
			            }
		            	jQuery('div', jQuery('#nice_chat_update_products')).css({'width': data['progress'] * 5});
		            	jQuery('#nice_chat_update_products_txt').text(data['progress'] + "%");
		        	} else {
	            		return;	
	            	}
	            }
	        },
	        dataType: "json",
	        timeout: 30000
	    })
	};
	jQuery.ajax({
        url: nice_chat_wp_base_url + "?action=product_export__init",
        type: "GET",
        success: function(data) {
        	if (data.nice_chat_status == 'ok') {
            	setTimeout(function() {poll(data.nice_chat_token)}, 500);
            	jQuery('#nice_chat_update_products').show();
            	jQuery('div', jQuery('#nice_chat_update_products')).css({'width': 0});
            } else {
            	alert(data['nice_chat_msg']);
            }
        },
        dataType: "json",
        timeout: 30000
    })
};