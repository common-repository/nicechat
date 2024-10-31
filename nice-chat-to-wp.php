<?php

/*
* Contributors: silverice
* Tags: nicechat, chat, buttonchat, live, chat, livechat
* Plugin Name: NiceChat_to_WP
* Version: 0.1.2
* Author: NiceChat Team
* Author URI: https://nice.chat/
* License: GPLv2
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain: nice-chat-to-wp
* Tested up to: 4.8.2
* Requires PHP: 5.2.10
* Description: The NiceChat-to-WP give you an ability to one-click integration your WordPress/WooCommerce blog with the NiceChat - the first eCommerce oriented chat.
*/


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NICE_CHAT_TO_WP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require(NICE_CHAT_TO_WP_PLUGIN_PATH.'/includes/nice-chat-config.php');
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-nice-chat-to-wp-activator.php
 */
function activate_nice_chat_to_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-nice-chat-to-wp-activator.php';
	NiceChat_to_WP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-nice-chat-to-wp-deactivator.php
 */
function deactivate_nice_chat_to_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-nice-chat-to-wp-deactivator.php';
	NiceChat_to_WP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_nice_chat_to_wp' );
register_deactivation_hook( __FILE__, 'deactivate_nice_chat_to_wp' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-nice-chat-to-wp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function run_nice_chat_to_wp() {

	$plugin = new NiceChat_to_WP();
	$plugin->run();

}
run_nice_chat_to_wp();
