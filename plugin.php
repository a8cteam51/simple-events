<?php
/**
 * Simple Events Plugin bootstrap file.
 *
 * @since       1.0.0
 * @version     2.0.13
 * @author      WordPress.com Special Projects
 * @license     GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:             Simple Events
 * Plugin URI:              https://github.com/a8cteam51/simple-events
 * Update URI:              https://github.com/a8cteam51/simple-events
 * Description:             Event management frontend for WooCommerce Box Office.
 * Requires at least:       6.5
 * Tested up to:            6.9
 * Version:                 2.0.13
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

define( 'SE_VERSION', '2.0.13' );
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
require_once SE_SRC_PATH . '/classes/class-se-event-query-utils.php';
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

// Instruct WordPress to fetch update information from GitHub.
add_filter(
	'update_plugins_github.com',
	static function ( $update, array $plugin_data, string $plugin_file ) {
		if ( SE_BASENAME !== $plugin_file || false !== $update ) {
			return $update;
		}

		$latest_release_info = get_site_transient( 'se_latest_release_info' );
		if ( false === $latest_release_info ) {
			$response = wp_remote_get(
				'https://api.github.com/repos/a8cteam51/simple-events/releases/latest',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/vnd.github+json',
					),
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return $update;
			}
			$latest_release_info = wp_remote_retrieve_body( $response );
			set_site_transient( 'se_latest_release_info', $latest_release_info, HOUR_IN_SECONDS );
		}

		if ( empty( $latest_release_info ) ) {
			return $update;
		}

		$latest_release_info = json_decode( $latest_release_info, true );
		if (
			! is_array( $latest_release_info ) ||
			empty( $latest_release_info['tag_name'] ) ||
			empty( $latest_release_info['html_url'] ) ||
			empty( $latest_release_info['assets'] ) ||
			! is_array( $latest_release_info['assets'] )
		) {
			return $update;
		}

		$package_url = '';
		foreach ( $latest_release_info['assets'] as $asset ) {
			if ( ! empty( $asset['browser_download_url'] ) && str_ends_with( $asset['browser_download_url'], '.zip' ) ) {
				$package_url = $asset['browser_download_url'];
				break;
			}
		}
		if ( '' === $package_url ) {
			return $update;
		}

		$latest_release_version = ltrim( $latest_release_info['tag_name'], 'v' );
		if ( version_compare( $plugin_data['Version'], $latest_release_version, '<' ) ) {
			$update = array(
				'slug'    => $plugin_data['TextDomain'],
				'version' => $latest_release_version,
				'url'     => $latest_release_info['html_url'],
				'package' => $package_url,
			);
		} else {
			$update = false;
		}

		return $update;
	},
	10,
	3
);

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
