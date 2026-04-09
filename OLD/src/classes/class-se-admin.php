<?php
/**
 * Admin Panel related functions.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SE_SRC_PATH . '/classes/class-se-event-post-type.php';

/**
 * Admin Panel Class.
 */
class SE_Admin {
	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'manage_' . SE_Event_Post_Type::$post_type . '_posts_columns', array( __CLASS__, 'customize_event_columns' ), 11 );
		add_filter( 'manage_' . SE_Event_Post_Type::$post_type . '_posts_custom_column', array( __CLASS__, 'customize_event_columns_data' ), 11, 2 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public static function enqueue_admin_scripts() {
		wp_enqueue_script(
			'se-admin',
			SE_PLUGIN_URL . '/build/js/admin.js',
			array( 'jquery' ),
			SE_VERSION,
			array(
				'in_footer' => false,
				'strategy'  => 'async',
			)
		);
	}

	/**
	 * Add a new column to the se-event admin page.
	 *
	 * @param array $columns The array of existing columns in the event list table.
	 *
	 * @return array The modified array of columns.
	 */
	public static function customize_event_columns( $columns ) {
		$columns['event-date-time'] = esc_html__( 'Event Date/Time', 'simple-events' );
		$columns['event-location']  = esc_html__( 'Event Location', 'simple-events' );
		return $columns;
	}

	/**
	 * Add custom data to the se-event admin page for custom columns.
	 *
	 * @param string  $column  Column key of Admin Panel.
	 * @param integer $post_id Post ID of the row in the Admin Panel.
	 *
	 * @return void
	 */
	public static function customize_event_columns_data( $column, $post_id ) {
		switch ( $column ) {
			case 'event-date-time':
				echo wp_kses_post( se_event_get_formatted_dates( $post_id ) );
				break;
			case 'event-location':
				$location = se_event_get_location( $post_id );
				echo esc_html( $location ? $location : '-' );
				break;
		}
	}
}

SE_Admin::init();
