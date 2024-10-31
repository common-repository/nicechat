<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://nice.chat
 * @since      0.0.1
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
class NiceChat_to_WP_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register page on Settings in admin-area.
	 *
	 * @since    0.0.1
	 */
	public function add_nice_chat_menu() {

		add_options_page('NiceChat_to_WP', 'NiceChat', 8, 'nice_chat_options_page', array($this, nice_chat_options_page));
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/nicechat-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Show and apply NiceChat page (on WP-Settings)
	 *
	 * @since    0.0.1
	 */
	public function nice_chat_options_page() {
		if($_POST['nice_chat_partner_id']) {
			// set the post formatting options
			if (preg_match("/^\s?[a-z0-9]{20,60}\s?$/", $_POST['nice_chat_partner_id'])) {
				$nice_chat_partner_id = trim($_POST['nice_chat_partner_id']);
				update_option('nice_chat_partner_id', $nice_chat_partner_id);
				echo ('<div class="updated"><p>'.__('Settings updated', 'nice-chat-to-wp').'</p></div>');
			} else {
				echo ('<div class="error"><p>'.__('Wrong Partner ID', 'nice-chat-to-wp').'</p></div>');
			}
		}
		if($_POST['nice_chat_api_id'] && $_POST['nice_chat_api_secret']) {
			// set the post formatting options
			if (preg_match("/^\s?[A-Z0-9]{16,16}\s?$/", $_POST['nice_chat_api_id']) &&
				preg_match("/^\s?[a-z0A-Z0-9]{32,32}\s?$/", $_POST['nice_chat_api_secret'])
			) {
				$nice_chat_api_id = trim($_POST['nice_chat_api_id']);
				$nice_chat_api_secret = trim($_POST['nice_chat_api_secret']);
				update_option('nice_chat_api_id', $nice_chat_api_id);
				update_option('nice_chat_api_secret', $nice_chat_api_secret);
				echo ('<div class="updated"><p>'.__('Settings updated', 'nice-chat-to-wp').'</p></div>');
			} else {
				echo ('<div class="error"><p>'.__('Wrong API-ID/Secret', 'nice-chat-to-wp').'</p></div>');
			}
		}
		$nice_chat_partner_id = get_option('nice_chat_partner_id');
		$nice_chat_api_id = get_option('nice_chat_api_id');
		$nice_chat_api_secret = get_option('nice_chat_api_secret');

		?>
		<div class="wrap">
			<h2><?php echo (__('Setup NiceChat Widget', 'nice-chat-to-wp')) ?></h2>
			<script type="text/javascript">
				window.nice_chat_wp_ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
			</script>
			<form method="post">
			<table class="form-table">
				<tbody>
					<tr>
						<td colspan="2">
							<h3><?php echo (__('NiceChat general', 'nice-chat-to-wp')) ?></h3>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nice_chat_partner_id"><?php echo (__('Partner ID (optional)', 'nice-chat-to-wp')) ?></label></th>
						<td>
							<input type="text" name="nice_chat_partner_id" value="<?php echo($nice_chat_partner_id)?>"/>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<h3><?php echo (__('Product synchronization API', 'nice-chat-to-wp')) ?></h3>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nice_chat_api_id"><?php echo (__('API-ID (optional)', 'nice-chat-to-wp')) ?></label></th>
						<td>
							<input type="text" name="nice_chat_api_id" value="<?php echo($nice_chat_api_id)?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="nice_chat_api_secret"><?php echo (__('API-Secret (optional)', 'nice-chat-to-wp')) ?></label></th>
						<td>
							<input type="password" name="nice_chat_api_secret" value="<?php echo($nice_chat_api_secret)?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<input type="submit" value="<?php echo (__('Save settings', 'nice-chat-to-wp')) ?>" />
						</td>
					</tr>
				</tbody></table>
			</form>
			<table class="form-table">
				<tbody>
					<tr>
						<td colspan="2">
							<h1><?php echo (__('Export products to NiceChat', 'nice-chat-to-wp')) ?></h1>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<p class="description"><?php echo(__('"Export products" will fill the Nice.Chat database by valid and published products.', 'nice-chat-to-wp')) ?></p>
							<div id="nice_chat_update_products" style="display: none; width: 500px; height: 25px; border: 1px solid #333;">
								<div style="height: 25px; background: #88f; width: 0px;"></div>
							</div>
							<div id="nice_chat_update_products_txt" style="width: 500px; height: 25px; text-align: center; margin-top: -22px;">
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<p>
								<input type="button" onClick="window.start_products_export()" value="<?php echo (__('Export products', 'nice-chat-to-wp')) ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<p class="description" id="nice_chat_log_errors"></p>
							<div id="nice_chat_update_products">
								<div></div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

}
