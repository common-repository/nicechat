<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://nice.chat
 * @since      0.0.2
 *
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.0.2
 * @package    NiceChat_to_WP
 * @subpackage NiceChat_to_WP/includes
 * @author     SilverIce <si@nice.chat>
 */
class NiceChat_to_WP_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.0.2
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'nice-chat-to-wp',
			false,
			plugin_basename(NICE_CHAT_TO_WP_PLUGIN_PATH.'languages/')
		);
	}

}
