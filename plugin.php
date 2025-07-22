<?php
/**
 * Simple Events Plugin bootstrap file.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @author      WordPress.com Special Projects
 * @license     GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:             Simple Events
 * Plugin URI:              https://wpspecialprojects.wordpress.com
 * Description:             Event management frontend for WooCommerce Box Office.
 * Requires at least:       6.2
 * Tested up to:            6.4
 * Version:                 2.0.0
 * Requires PHP:            8.0
 * Author:                  WordPress.com Special Projects
 * Author URI:              https://wpspecialprojects.wordpress.com
 * License:                 GPL v3 or later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             simple-events
 **/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
function_exists( 'get_plugin_data' ) || require_once ABSPATH . 'wp-admin/includes/plugin.php';
define( 'SE_METADATA', get_plugin_data( __FILE__, false, false ) );

define( 'SE_VERSION', '2.0.0' );
define( 'SE_BASENAME', plugin_basename( __FILE__ ) );
define( 'SE_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'SE_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SE_SRC_PATH', untrailingslashit( SE_PLUGIN_DIR . '/src' ) );
define( 'SE_TEMPLATE_PATH', untrailingslashit( SE_SRC_PATH . '/templates' ) );

// This should only be updated if there are changes to the way we handle dates and there are migration method to handle.
// This is used to determine if we need to run migrations.
define( 'SE_MIGRATION_VERSION', '2.0.0' );

// Load the autoloader.
if ( ! is_file( SE_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			$message      = __( 'It seems like <strong>Simple Events</strong> is corrupted. Please reinstall!', 'simple-events' );
			$html_message = wp_sprintf( '<div class="error notice wpcomsp-se-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		}
	);
	return;
}
require_once SE_PLUGIN_DIR . '/vendor/autoload.php';


require_once SE_SRC_PATH . '/classes/class-se-event-post-type.php';
require_once SE_SRC_PATH . '/classes/class-se-blocks.php';
require_once SE_SRC_PATH . '/classes/class-se-block-variations.php';
require_once SE_SRC_PATH . '/classes/class-se-template-loader.php';
require_once SE_SRC_PATH . '/classes/class-se-settings.php';
require_once SE_SRC_PATH . '/classes/class-se-admin.php';
require_once SE_SRC_PATH . '/classes/class-se-calendar-export.php';
require_once SE_SRC_PATH . '/classes/class-se-calendar.php';
require_once SE_SRC_PATH . '/classes/class-se-event-query-dates.php';
require_once SE_SRC_PATH . '/classes/class-se-event-dates.php';
require_once SE_SRC_PATH . '/classes/class-date-display-formatter.php';
require_once SE_SRC_PATH . '/classes/class-se-migrate-events.php';

require_once SE_SRC_PATH . '/calendar-functions.php';
require_once SE_SRC_PATH . '/event-functions.php';
require_once SE_SRC_PATH . '/template-functions.php';
require_once SE_SRC_PATH . '/template-hooks.php';
require_once SE_SRC_PATH . '/woocommerce-hooks.php';
require_once SE_SRC_PATH . '/rest-api.php';
require_once SE_SRC_PATH . '/back-compat.php';


/**
 * Add a flag to leverage for flushing rewrite rules.
 *
 * @return void
 */
function simple_events_activate() {
	if ( ! get_option( 'simple_events_flush_rewrite_rules_flag' ) ) {
		add_option( 'simple_events_flush_rewrite_rules_flag', true );
	}
}
register_activation_hook( __FILE__, 'simple_events_activate' );
