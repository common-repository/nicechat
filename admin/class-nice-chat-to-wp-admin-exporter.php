<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://developers.nice.chat/
 * @since      0.1.0
 *
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/admin
 * @author     SilverIce <si@nice.chat>
 */


class NiceChat_to_WP_Admin_Exporter {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function product_export__init() {
		/**
		 * Entry point for initial ajax-query
		 * 
		 */
		
		$ts = get_option('nice_chat_product_export_ts');
		// check task creation interval
		if ($ts && $ts + 90 > time()) {
			echo(json_encode(array('nice_chat_status' => 'error', 
								   'nice_chat_msg' => 'Exceeded the interval between requests to start exporting')));
		} else {
			update_option('nice_chat_product_export_ts', "".time());
			// generate new task-key, reset task data-file 
			$token = sha1(time().uniqid(rand(), true));
			@file_put_contents($this->product_export__task_file_name(), "{}");
			update_option('nice_chat_product_export', $token);

			echo(json_encode(array('nice_chat_status' => 'ok', 
								   'nice_chat_token' => $token)));
		}
		exit();
	}

	public function product_export__loop() {
		/**
		 * Entry point for loop ajax-query
		 * 
		 */

		// Validate task-key
		if ($_GET['key'] == get_option('nice_chat_product_export')) {
			// read and validate task data-file
			$task = @file_get_contents($this->product_export__task_file_name());
			if ($task === false) {
				echo(json_encode(array('nice_chat_status' => 'err')));
				return;
			}
			$task = json_decode($task, true);
			if ($task === NULL) {
				echo(json_encode(array('nice_chat_status' => 'err')));
			} else {
				// if task was complete
				if ($task['complete'] === true) {
					$result = array('nice_chat_status' => 'done',
						'progress' => 100);
					echo(json_encode($result));
					exit();
				}
				if (isset($task['step_l2'])) {
					// write part of task processing
					$rez = $this->product_export__push_products($task);
				} else {
					// read part of task processing
					$rez = $this->product_export__query_wp($task);
				}
				// if task-step was complete - update task data-file
				if ($rez) {
					@file_put_contents($this->product_export__task_file_name(), json_encode($task));
				}
				// send status and progress to frontend
				$result = array('nice_chat_status' => 'ok',
					'progress' => $this->product_export__task_get_progress($task));
				if ($rez !== true) {
					$result['msg'] = $rez;
				}
				echo(json_encode($result));
			}
			//update_option('nice_chat_product_export', 0);
		} else {
			echo(json_encode(array('nice_chat_status' => 'stop')));
		}
		exit();
	}

	private function product_export__task_get_progress($task) {
		/**
		 * Get current progress values in percent
		 * 
		 * @param $task - task data-file representation (as array)
		 *
		 * @return progress percent value (int/float from 0.0 to 100)
		 *
		 */

		if (isset($task['step_l2'])) {
			$p = sizeof($task['l2_products']) + sizeof($task['l2d_products']);
			$c = sizeof($task['l2_categories']) + sizeof($task['l2d_categories']);
			$t = $task['l2_products_c'] + $task['l2_categories_c'];
			return $t != 0 ? round(($t-$p-$c)/($t) * 85 + 15, 1) : 20;
		}
		if (!isset($task['categories'])) {
			return 0;
		}
		if (!isset($task['products'])) {
			return 1;
		}
		if (!isset($task['product_variations'])) {
			return 2;
		}
		if (sizeof($task['products']) > 0 || sizeof($task['product_variations']) > 0) {
			$pp = sizeof($task['products']);
			$ppc = $task['products_c'];
			$pv = sizeof($task['product_variations']);
			$pvc = $task['product_variations_c'];
			return $ppc+$pvc != 0 ? round(($ppc+$pvc - $pp - $pv)/($ppc+$pvc) * 4 + 2, 1) : 3;
		}
		if ($task['api_products'] !== 'complete') {
			return $task['r_products_c'] != 0 ? round(sizeof($task['r_products'])/$task['r_products_c'] * 8 + 6, 1): 6;
		}
		if ($task['api_categories'] !== 'complete') {
			return $task['r_categories_c'] != 0 ? round(sizeof($task['r_categories'])/$task['r_categories_c'] * 3 + 14, 1): 14;
		}
		if ($task['api_products'] === 'complete' && $task['api_categories'] === 'complete') {
			return 15;
		}
	}

	private function product_export__query_wp(&$task) {
		/**
		 * Main read-function:
		 *  - query WC-Categories
		 *  - query WC-Products
		 *  - fill addtitional info for WC-Products and WC-Categories in interrupted loop
		 *  - query Nice.Chat-Categories and Nice.Chat-Products by API in interrupted loop
		 *  - compare it and put create/update/delete jobs list
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 * @return true - if have job and it was handling
		 *         null - not more job in this section
		 *
		 */

		if (!isset($task['categories'])) {
			$categories = array();
			$this->product_export__get_category($categories, NULL, '.', 0);
			ksort($categories);
			$task['categories'] = $categories;
			return true;
		}
		if (!isset($task['products'])) {
			$products = $this->product_export__get_products();
			$task['products'] = $products;
			$task['products_c'] = sizeof($products);
			$task['products_result'] = array();
			return true;
		}
		if (!isset($task['product_variations'])) {
			$product_variations = $this->product_export__get_product_variations();
			$task['product_variations'] = $product_variations;
			$task['product_variations_c'] = sizeof($product_variations);
			return true;
		}
		if (sizeof($task['products']) > 0) {
			$this->product_export__query_products_info($task);
			return true;
		}
		if (sizeof($task['product_variations']) > 0) {
			$this->product_export__query_product_variations_info($task);
			return true;
		}
		if ($task['api_products'] !== 'complete') {
			$this->product_export__query_product_api($task);
			return true;
		}
		if ($task['api_categories'] !== 'complete') {
			$this->product_export__query_api_category_api($task);
			return true;
		}
		if ($task['api_products'] === 'complete' && $task['api_categories'] === 'complete') {
			$this->product_export__query_api_compare($task);
			return true;
		}
	}

	private function product_export__query_api_compare(&$task) {
		/**
		 * Compare WC and Nice.Chat lists of Categories/Products and distribute it for job queues
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 */

		$products_to = array();
		$products_do = array();
		$categories_to = array();
		$categories_do = array();

		foreach ($task['products_result'] as $p_id => $p_value) {
			if (isset($p_value['vars'])) {
				foreach($p_value['vars'] as $v_id => $v_value) {
					if ($v_value['price']) {
						$products_to[$v_id] = 1;
						$products_do[$v_id] = $v_value;
					}
				}
			} else {
				if ($p_value['price']) {
					$products_to[$p_id] = 1;
					$products_do[$p_id] = $p_value;
				}
			}
		}

		foreach ($task['categories'] as $c_id => $c_value) {
			$categories_to[$c_value['id']] = 1;
			$categories_do[$c_value['id']] = $c_value;
			$categories_do[$c_value['id']]['path'] = $c_id;
		}

		foreach ($task['r_products'] as $p_id => $p_value) {
			$products_to[$p_id] = isset($products_to[$p_id]) ? 3: 2;
		}

		foreach ($task['r_categories'] as $c_id => $c_value) {
			$categories_to[$c_id] = isset($categories_to[$c_id]) ? 3: 2;
		}
		
		$l2_products = array();
		$l2_categories = array();
		$l2d_products = array();
		$l2d_categories = array();

		foreach ($products_to as $p_id => $state) {
			if ($p_id === '' || $p_id == null) continue;
			switch ($state) {
				case 1:
					$l2_products[$p_id] = $products_do[$p_id];
					$l2_products[$p_id]['task'] = 'add';
					break;
				case 3:
					if (!$this->product_export__compare_product($task['r_products'][$p_id], $products_do[$p_id])) {
						$l2_products[$p_id] = $products_do[$p_id];
						$l2_products[$p_id]['task'] = 'upd';
					}
					break;
				case 2:
					$l2d_products[$p_id] = $p_id;
			}
		}
		foreach ($categories_to as $c_id => $state) {
			if ($c_id === '' || $c_id == null) continue;
			switch ($state) {
				case 1:
					$l2_categories[$c_id] = $categories_do[$c_id];
					$l2_categories[$c_id]['task'] = 'add';
					break;
				case 3:
					if (!$this->product_export__compare_category($task['r_categories'][$c_id], $categories_do[$c_id])) {
						$l2_categories[$c_id] = $categories_do[$c_id];
						$l2_categories[$c_id]['task'] = 'upd';
					}
					break;
				case 2:
					$l2d_categories[$c_id] = $c_id;
			}
		}

		function nice_chat_l2_categories_cmp($a, $b)
		{
		    return strcmp($a['path'], $b['path']);
		}

		usort($l2_categories, "nice_chat_l2_categories_cmp");

		foreach ($task as $j => $value) {
		    unset($task[$j]);
		}

		$task['l2_products'] = $l2_products;
		$task['l2_categories'] = $l2_categories;
		$task['l2d_products'] = $l2d_products;
		$task['l2d_categories'] = $l2d_categories;
		$task['l2_products_c'] = sizeof($l2_products) + sizeof($l2d_products);
		$task['l2_categories_c'] = sizeof($l2_categories) + sizeof($l2d_categories);
		$task['step_l2'] = true;
	}

	private function product_export__compare_product($a, $b) {
		/**
		 * Compare Products from Nice.Chat API and WC
		 * 
		 * @param $a - array - NiceChat representation of Product
		 * @param $b - array - WC representation of Product
		 *
		 * @return true - if objects are identical, else false
		 *
		 */
		return $a['version'] === $b['version'];
	}

	private function product_export__compare_category($a, $b) {
		/**
		 * Compare Categories from Nice.Chat API and WC
		 * 
		 * @param $a - array - NiceChat representation of Category
		 * @param $b - array - WC representation of Category
		 *
		 * @return true - if objects are identical, else false
		 *
		 */
		return $a['version'] === md5($b['name'].$b['path']);
	}

	private function product_export__query_api_category_api(&$task) {
		/**
		 * Read paginated list of Categories from Nice.Chat API
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 */

		// if have prepared query - use it
		if (!isset($task['api_categories']) && $task['api_categories'] !== '') {
			$prod = $this->send_request("GET", '/marketplaces/v1/categories/', '');
		} else {
			$prod = $this->send_request("GET", $task['api_categories'], '');
		}

		// validate response
		if ($prod['status'] == 'ok') {
			$prod = $prod['result'];
		} else {
			$task['api_categories'] = 'complete';	
			return false;
		}

		// if response have next-query - put it (if not have - task was complete)
		$task['api_categories'] = $prod['next'] !== '' && $prod['next'] !== null ? $prod['next'] : 'complete';

		// put data
		if (!isset($task['r_categories'])) {
			$task['r_categories'] = array();
		}
		foreach ($prod['results'] as $item) {
			$task['r_categories'][$item['id']] = $item;
		}
		// put total categoris count for progress info
		$task['r_categories_c'] = $prod['count'];
	}

	private function product_export__query_product_api(&$task) {
		/**
		 * Read paginated list of Products from Nice.Chat API
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 */

		// if have prepared query - use it
		if (!isset($task['api_products']) && $task['api_products'] !== '') {
			$prod = $this->send_request("GET", '/marketplaces/v1/products/', '');
		} else {
			$prod = $this->send_request("GET", $task['api_products'], '');
		}

		// validate response
		if ($prod['status'] == 'ok') {
			$prod = $prod['result'];
		} else {
			$task['api_products'] = 'complete';	
			return false;
		}

		// if response have next-query - put it (if not have - task was complete)
		$task['api_products'] = $prod['next'] !== '' && $prod['next'] !== null ? $prod['next'] : 'complete';

		// put data
		if (!isset($task['r_products'])) {
			$task['r_products'] = array();
		}
		foreach ($prod['results'] as $item) {
			$task['r_products'][$item['id']] = $item;
		}
		$task['r_products_c'] = $prod['count'];
	}

	private function product_export__get_category(&$categories, $parent_id, $parent_root, $deep) {
		/**
		 * Recursive function for WC-Categories querying
		 * 
		 * @param byref $categories - list of categoris
		 * @param $parent_id - int parent ID
		 * @param $parent_root - parents catagories string representation
		 * @param $deep - recusion protection (enlarge on each level)
		 *
		 * @return true - if task handilng
		 *
		 */

		// recusion-loop protection
		if ($deep > 15)
			return;

		// prepare query
		$categ_query_args = array(
	       'taxonomy'     => 'product_cat',
	       'orderby'      => 'name',
	       'parent'		  => $parent_id !== NULL ? $parent_id : 0,
	       'show_count'   => 0,		// 1 for yes, 0 for no
	       'pad_counts'   => 0,		// 1 for yes, 0 for no
	       'hierarchical' => 1,		// 1 for yes, 0 for no
	       'hide_empty'   => 0		// 1 for yes, 0 for no
		);

		$all_categories = get_categories( $categ_query_args );

		// put
		foreach ($all_categories as $cat) {
			$categories[$parent_root.$cat->term_id] = array(
				'name' => $cat->name,
				'id' => $cat->term_id,
				'parent' => $cat->parent,
				'count' => $cat->count
			);
			$this->product_export__get_category($categories, $cat->term_id, $parent_root.$cat->term_id.".", $deep +1);
		}

		return true;
	}

	private function product_export__task_file_name() {
		/**
		 * Get task data-file name in temp folder
		 *
		 * @return string with full file name
		 *
		 */

		return get_temp_dir().'nice_chat_product_export_task.json';
	}

	private function product_export__get_products() {
		/**
		 * Query WC for basic data of Standard-Products 
		 *
		 * @return array with Standard-Products list
		 *
		 */

		$product_list = array();
		$loop = new WP_Query( array( 'post_type' => array('product'), 'posts_per_page' => -1 ) );
		while ( $loop->have_posts() ) {
			$post = $loop->next_post();
			if ($post->post_status == 'publish') {
				$product_list[$post->ID] = array(
					'id'=>$post->ID,
					'title'=>$post->post_title,
					'content'=>$post->post_content
				);
			}
	    }; 
	    wp_reset_query();
	    return $product_list;
	}

	private function product_export__get_product_variations() {
		/**
		 * Query WC for basic data of Variable-Products 
		 *
		 * @return array with Variable-Products list
		 *
		 */

		$product_list = array();
		$loop = new WP_Query( array( 'post_type' => array('product_variation'), 'posts_per_page' => -1 ) );
		while ( $loop->have_posts() ) {
			$post = $loop->next_post();
			if ($post->post_status == 'publish') {
				$product_list[$post->ID] = array(
					'id'=>$post->ID,
					'title'=>$post->post_title,
					'content'=>$post->post_content,
					'parent'=>$post->post_parent
				);
			}
	    }; 
	    wp_reset_query();
	    return $product_list;
	}

	private function product_export__query_products_info(&$task) {
		/**
		 * Get from WC info about Standard-Products with time protection
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 */

		$ts_start = time();
		while(time() - $ts_start < 2) {
			// get Product from task['products']
			$prod = array_pop($task['products']);
			// query WP
			$image = wp_get_attachment_image_src( get_post_thumbnail_id($prod['id']), 'single-post-thumbnail' );
			$prod['image'] = is_array($image) ? $image[0] : '';
			$product = new WC_Product($prod['id']);
			$prod['price'] = $product->get_price();
			$prod['currency'] = get_woocommerce_currency();
			$prod['sku'] = $product->get_sku();
			$prod['url'] = get_permalink($prod['id']);
			$prod['category'] = array_pop($product->get_category_ids());
			$prod['instock'] = $product->get_stock_status() === "instock";
		    $prod['version'] = $product->get_date_modified() ? $product->get_date_modified()->__toString() : 'new';
			// put goted info
			$task['products_result'][$prod['id']] = $prod;
		}
	}

	private function product_export__query_product_variations_info(&$task) {
		/**
		 * Get from WC info about Variable-Products with time protection
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 */

		$ts_start = time();
		while(time() - $ts_start < 2) {
			// get Product from task['product_variations']
			$prod = array_pop($task['product_variations']);
			// if Variation-parent if valid and available to public - ask variation defails
			if (isset($task['products_result'][$prod['parent']])) {
				$image = wp_get_attachment_image_src( get_post_thumbnail_id($prod['id']), 'single-post-thumbnail' );
				$prod['image'] = is_array($image) ? $image[0] : '';
				$product = new WC_Product_Variation($prod['id']);
				$prod['price'] = $product->get_price();
				$prod['currency'] = get_woocommerce_currency();
				$prod['sku'] = $product->get_sku();
				$prod['category'] = array_pop($product->get_category_ids());
				$prod['instock'] = $product->get_stock_status() === "instock";
  			    $prod['version'] = $product->get_date_modified() ? $product->get_date_modified()->__toString() : 'new';
				$prod['attr'] = $product->get_attributes();
				// put
				if (!isset($task['products_result'][$prod['parent']]['vars'])) {
					$task['products_result'][$prod['parent']]['vars'] = array();
				}
				$task['products_result'][$prod['parent']]['vars'][$prod['id']] = $prod;
			}
		}
	}
	
	private function product_export__push_products(&$task) {
		/**
		 * Main write functions - delete and renew Categories and Products
		 * 
		 * @param byref $task - task data-file representation (as array)
		 *
		 * @return true - if step was completed success
		 *         String - warning message
		 *
		 */

		// if have Product for create/update
		if (sizeof($task['l2d_products']) !== 0) {
			$rem_prod_id = array_pop($task['l2d_products']);
			$res = $this->send_request("DELETE", '/marketplaces/v1/products/'.$rem_prod_id.'/', '');
			if ($res['status'] == 'ok') {
				return true;
			} else {
				return $res['description'].' (Product delete) ID:'.$rem_prod_id;
			}
		}

		// if have Category for delete
		if (sizeof($task['l2d_categories']) !== 0) {
			$rem_ctg_id = array_pop($task['l2d_categories']);
			$res = $this->send_request("DELETE", '/marketplaces/v1/categories/'.$rem_ctg_id.'/', '');
			if ($res['status'] == 'ok') {
				return true;
			} else {
				return $res['description'].' (Category delete) ID:'.$rem_ctg_id;
			}
		}

		// if have Category for create/update
		if (sizeof($task['l2_categories']) !== 0) {
			// get it
			$categ = array_pop($task['l2_categories']);
			// prepare payload			
			$post_categ = json_encode(array(
				'id' => $categ['id'],
				'name' => $categ['name'],
				'version' => md5($categ['name'].$categ['path']),
				'parent' => $categ['parent'] ? $categ['parent'] : null
			));
			// run different queries by task
			if ($categ['task'] === 'add') {
				$res = $this->send_request("POST", '/marketplaces/v1/categories/', $post_categ);
			} else {
				$res = $this->send_request("PUT", '/marketplaces/v1/categories/'.$categ['id'].'/', $post_categ);
			}
			// parse response
			if ($res['status'] == 'ok') {
				return true;
			} else {
				return $res['description'].' (Category renew) ID:'.$categ['id'];
			}
		}

		// if have Product for create/update
		if (sizeof($task['l2_products']) !== 0) {
			// get it
			$product = array_pop($task['l2_products']);
			// convert variable-products attributes to Nice.Chat Product extended property
			$attr = array();
			if (isset($product['attr'])) {
				$attr['type'] = 'var';
				foreach($product['attr'] as $k => $v) 
					$attr['attr_'.$k] = $v;
				$attr['master_id'] = $product['parent'];
			} else {
				$attr['type'] = 'std';
			}
			// prepare payload
			$post_data = json_encode(array(
				'id' => $product['id'],
				'title' => $product['title'],
				'price' => $product['price'],
				'currency' => strtolower($product['currency']),
				'category' => $product['category'],
				'description' => $product['content'],
				'image_url' => $product['image'],
				'url' => $product['url'],
				'version' => $product['version'],
				'props' => $attr,
				'in_stock' => $product['instock'] ? $product['instock'] : false
			));
			// run
			if ($product['task'] === 'add') {
				$res = $this->send_request("POST", '/marketplaces/v1/products/', $post_data);
			} else {
				$res = $this->send_request("PUT", '/marketplaces/v1/products/'.$product['id'].'/', $post_data);
			}
			// parse response
			if ($res['status'] == 'ok') {
				return true;
			} else {
				return $res['description'].' (Product renew) ID:'.$product['id'];
			}
		}


		$task['complete'] = true;
		return true;
	}

	private function send_request($method, $action, $payload) {
		/**
		 * Entry point for loop ajax-query
		 * 
		 * @param $method - HTTP request method of query
		 * @param $action - URI-path of task
		 * @param $payload - payload - json-string or array of key-value
		 *
		 * @return array with parsed API-response (both - success and faild)
		 *
		 */

		// get API credentails
		$key = get_option('nice_chat_api_id');
		$secret = get_option('nice_chat_api_secret');

		// check and cut API-anchor from action
		if (strpos($action, NICE_CHAT_API_BASE) === 0) {
			$action = substr($action, strlen(NICE_CHAT_API_BASE));
		}

		// prepare query-headers
		$sign = $this->make_sign($payload, $secret, preg_replace('/\?.*/', '', $action), $method);
		$headers = array(
	 	    'Content-Type: application/json',
  		    'X-API-Key: '.$key,
		    'X-API-Sign: '.$sign
		);

		// run cURL query
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_URL, NICE_CHAT_API_BASE.$action);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($ch, CURLOPT_VERBOSE, 1);
		if (strlen($payload) > 0) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_output = curl_exec ($ch);
		$rez = json_decode($server_output, true);

		// if query crashed - describe it
		if ($rez === false || $rez === null) {
			$rez = array('status' => 'err');
			$code = curl_getinfo($ch);
			$code = $code['http_code'];	
			switch ($code) {
				case 404:
					$rez['description'] = 'Item not found';
					break;
				default:
					$rez['description'] = 'HTTP Error: '.$code;
			}
		}
		curl_close ($ch);
		return $rez;
	}

	private function make_sign($payload, $secret, $action, $request_method) {
		/**
		 * Make query signature by Nice.Chat API algo
		 * 
		 * @param $payload - payload - json-string or array of key-value
		 * @param $secret - Nice.Chat API-Secret key
		 * @param $action - URI-path of task
		 * @param $request_method - HTTP request method of query
		 *
		 * @return hexdecimal-string signature hash
		 *
		 */

	    $_payload = $payload;

	    if (is_array($_payload)) {
	        ksort($_payload);
	        $_payload = http_build_query($_payload);
	    }
	    return hash('sha256', $secret.$action.$_payload.$request_method);

	}	

}
