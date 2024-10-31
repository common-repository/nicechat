<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://nice.chat
 * @since      0.0.1
 *
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/public
 * @author     SilverIce <si@nice.chat>
 */
class NiceChat_to_WP_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.0.1
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function nice_chat_cart__get() {
		/**
		 * Entry point for receiving current user cart (ajax-query)
		 * 
		 * @since    0.1.2
		 */

		global $woocommerce;

		$result = array();
		if (is_array($woocommerce->cart->cart_contents)) {
			foreach($woocommerce->cart->cart_contents as $item) {
				//print_r($item['data']->get_title());
				array_push($result, array(
					'id' => strval($item['variation_id'] ? $item['variation_id'] : $item['product_id']),
					'qty' => $item['quantity'],
					'summ' => $item['line_total'],
					'price' => $item['line_total'] != 0 ? $item['line_total']/$item['quantity'] : 0,
					'currency' => get_woocommerce_currency()
				));
			}
		}
		$cart = $woocommerce->cart->get_cart_for_session();
		$hash = md5( json_encode( $cart ) );

		echo(json_encode(array('data'=>$result, 'hash'=>$hash)));
		exit();

	}

	public function nice_chat_cart__go_check() {
		/**
		 * Entry point for checkout page redirection
		 * 
		 * @since    0.1.2
		 */

		$url = wc_get_checkout_url();
		header('Location: '.$url);
		echo('<a href="'.$url.'">Redirect...</a>');
		exit();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in NiceChat_to_WP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The NiceChat_to_WP_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		/*wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/plugin-name-public.css', array(), $this->version, 'all' );*/

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {

		/**
		 * This functions add NiceChat widget url to your Blog;		 
		 */

		$nice_chat_partner_id = get_option('nice_chat_partner_id');

		$url = NICE_CHAT_WIDGET_URL;
		if ($nice_chat_partner_id) {
			$url = $url."?"."partner_id=".$nice_chat_partner_id;
		}

		wp_enqueue_script( $this->plugin_name."-widget", $url, array(), $this->version, false );
		wp_enqueue_script( $this->plugin_name."-worker", plugin_dir_url( __FILE__ ) . 'js/nicechat-cart-service.js', array(), $this->version, false );

	}

}
