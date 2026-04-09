<?php
/**
 * Plugin Settings.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SE_SRC_PATH . '/classes/class-se-event-post-type.php';

/**
 * Settings Class.
 */
class SE_Settings {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'settings_init' ), 10 );
		add_action( 'admin_menu', array( __CLASS__, 'options_page' ), 10 );

		// Ajax actions.
		add_action( 'wp_ajax_se_mark_existing_orders_as_completed', array( __CLASS__, 'mark_existing_orders_as_completed' ), 10 );
	}

	/**
	 * Adds custom options and settings.
	 *
	 * @return void
	 */
	public static function settings_init() {
		// Register a simple events setting.
		register_setting( 'simple_events', 'se_options' );

		// Register a simple events editor setting.
		add_settings_section(
			'se_section_editor',
			esc_html__( 'Editor', 'simple-events' ),
			array( __CLASS__, 'section_cb_editor' ),
			'simple_events',
		);

		// Register a "Hide Event Tickets Block" field in the `se_section_editor` section.
		add_settings_field(
			'remove_event_tickets_block',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Remove Event Tickets Block from New Events%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Enable this option if you are creating free events only.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_editor',
			array(
				'label_for'   => 'remove_event_tickets_block',
				'description' => esc_html__( 'Select this setting if you are creating free events only.', 'simple-events' ),
			)
		);

		// Register an "Archives" section in the `simple_events` option group.
		add_settings_section(
			'se_section_archives',
			esc_html__( 'Archives', 'simple-events' ),
			array( __CLASS__, 'section_cb_archives' ),
			'simple_events'
		);

		// Register a "Past Events" field in the `se_section_archives` section.
		add_settings_field(
			'hide_past_events',
			esc_html__( 'Hide Past Events ( Feed & Single )', 'simple-events' ),
			array( __CLASS__, 'radio_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'hide_past_events',
			)
		);

		// Register a "Past Event Notice" text field in the `se_section_archives` section.
		add_settings_field(
			'past_event_notice',
			esc_html__( 'Past Event Notice', 'simple-events' ),
			array( __CLASS__, 'text_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'past_event_notice',
			)
		);

		// Register a "Update Start and End Dates" field in the `se_section_archives` section.
		add_settings_field(
			'update_start_end_dates',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Update Query Dates%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Will update the start date used for queries to the next available starting date.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'update_start_end_dates',
			)
		);

		// Register a "reverse order" field in the `se_section_archives` section.
		add_settings_field(
			'reverse_events_order',
			esc_html__( 'Reverse events order in post feed.', 'simple-events' ),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'reverse_events_order',
			)
		);

		// Show next/previous links on event singles.
		add_settings_field(
			'show_next_prev_links',
			__( 'Show next/previous links on event singles.', 'simple-events' ),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'show_next_prev_links',
			)
		);

		// Select link for the caledar page.
		add_settings_field(
			'calendar_page',
			__( 'Select the page for the calendar.', 'simple-events' ),
			array( __CLASS__, 'calendar_page_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'calendar_page',
			)
		);

		// Show next and previous link above content.
		add_settings_field(
			'show_links_above_content',
			__( 'Next/Previous link placement.', 'simple-events' ),
			array( __CLASS__, 'link_position_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'show_links_above_content',
			)
		);

		add_settings_section(
			'se_section_woocommerce',
			esc_html__( 'WooCommerce', 'simple-events' ),
			array( __CLASS__, 'section_cb_woocommerce' ),
			'simple_events'
		);

		// Skip cart for tickets and redirect to checkout.
		add_settings_field(
			'skip_cart_for_ticket',
			__( 'Skip cart for tickets and redirect to checkout', 'simple-events' ),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_woocommerce',
			array(
				'label_for' => 'skip_cart',
			)
		);

		// Empty cart before adding tickets.
		add_settings_field(
			'empty_cart_before_adding_tickets',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Empty cart before a ticket is added%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Clears the cart before a ticket is added to the cart.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_woocommerce',
			array(
				'label_for' => 'empty_cart_before_adding_tickets',
			)
		);

		add_settings_field(
			'autocomplete_ticket_order',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Set Ticket Order Status to Completed.%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Orders that exclusively contain tickets will be automatically marked as completed. This applys to orders placed after this feature is enabled.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_woocommerce',
			array(
				'label_for' => 'autocomplete_ticket_order',
			)
		);

		add_settings_field(
			'mark_existing_orders_as_completed',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Set existing ticket orders as completed.%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Customer emails are disabled during the bulk update.</em></small>' ),
			),
			array( __CLASS__, 'ajax_cb' ),
			'simple_events',
			'se_section_woocommerce',
			array(
				'action'   => 'se_mark_existing_orders_as_completed',
				'btn_text' => __( 'Perform bulk status updates', 'simple-events' ),
			)
		);

		// Register an "Calendar" section in the `simple_events` option group.
		add_settings_section(
			'se_section_calendar',
			esc_html__( 'Calendar', 'simple-events' ),
			array( __CLASS__, 'section_cb_calendar' ),
			'simple_events'
		);

		// Register a "Custom Endpoint" field in the `se_section_calendar` section.
		add_settings_field(
			'cal_download_endpoint',
			esc_html__( 'Custom Endpoint for Downloading Calendar', 'simple-events' ),
			array( __CLASS__, 'cal_download_cb' ),
			'simple_events',
			'se_section_calendar',
			array(
				'label_for' => 'cal_download_endpoint',
			)
		);

		// Register a "Download Calendar" field in the `se_section_calendar` section.
		add_settings_field(
			'disable_download_calendar',
			esc_html__( 'Disable Calendar Download Endpoint', 'simple-events' ),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_calendar',
			array(
				'label_for' => 'disable_download_calendar',
			)
		);
	}

	/**
	 * Displays the "Editor" section.
	 *
	 * @return void
	 */
	public static function section_cb_editor() {
		?>
		<p><?php esc_html_e( 'Simple Events editor options.', 'simple-events' ); ?></p>
		<?php
	}

	/**
	 * Displays the "Archives" section.
	 *
	 * @return void
	 */
	public static function section_cb_archives() {
		?>
		<p><?php esc_html_e( 'Simple Events archive view options.', 'simple-events' ); ?></p>
		<?php
	}

	/**
	 * Displays the "WooCommerce" section.
	 *
	 * @return void
	 */
	public static function section_cb_woocommerce() {
		?>
		<p><?php esc_html_e( 'Simple Events WooCommerce options.', 'simple-events' ); ?></p>
		<?php
	}

	/**
	 * A description of the entire PHP function.
	 *
	 * @return void
	 */
	public static function section_cb_calendar() {
		?>
		<p><?php esc_html_e( 'Simple Events calendar download options.', 'simple-events' ); ?></p>
		<?php
	}

	/**
	 * Callback function for radio buttons.
	 *
	 * @param array $args The arguments passed to the function.
	 *
	 * @return void
	 */
	public static function radio_cb( $args ) {
		$options      = get_option( 'se_options' );
		$radio_labels = array(
			''                    => 'Show Both', // Shows old events in feed and single view. Empty string used due to it being default value if se_options not set previously.
			'hide_events_on_both' => 'Hide Both', // Hides old events on both feed and single view.
			'hide_events_on_feed' => 'Hide on Feed, Show on Single', // Hides old events on both feed only.
		);
		$value        = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';

		foreach ( $radio_labels as $key => $label ) {
			?>
			<label for="<?php echo esc_attr( $args['label_for'] . $label ); ?>" >
				<input
					type="radio"
					name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
					value="<?php echo esc_attr( $key ); ?>"
					<?php
					// Comparison with 'on' for backward compatibility with older version of the plugin
					checked( $value, 'on' === $value && 'hide_events_on_both' === $key ? 'on' : $key );
					?>
				>
				<?php echo esc_html( $label ); ?>
			</label>
			<?php
			if ( 'hide_events_on_feed' !== $key ) {
				echo '<br /><br />';
			}
		}
	}

	/**
	 * Displays the "Past Events" field.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function field_cb( $args ) {
		$options = get_option( 'se_options' );
		?>
		<input
			type="checkbox"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
			<?php checked( isset( $options[ $args['label_for'] ] ) ); ?>
		>
		<?php
	}


	/**
	 * A function for generating a AJAX button based on the given arguments.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function ajax_cb( $args ) {
		?>
		<button
			type="button"
			id="se_ajax_btn"
			class="button button-primary"
			data-action="<?php echo esc_attr( $args['action'] ); ?>"
		>
			<?php echo esc_html( $args['btn_text'] ); ?>
		</button>
		<div id="se_ajax_response"></div>
		<?php
	}
	/**
	 * A function for generating a text input field based on the given arguments.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function text_cb( $args ) {
		$options = get_option( 'se_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : esc_html__( 'Event has passed', 'simple-events' );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			required
		>
		<?php
	}

	/**
	 * Renders the link position field.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function link_position_cb( $args ) {
		$options = get_option( 'se_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 'before';
		?>
		<select
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
		>
			<option
				value="before"
				<?php selected( $value, 'before' ); ?>
			>
				<?php esc_html_e( 'Before content', 'simple-events' ); ?>
			</option>
			<option
				value="after"
				<?php selected( $value, 'after' ); ?>
			>
				<?php esc_html_e( 'After content', 'simple-events' ); ?>
			</option>
		<?php
	}


	/**
	 * Renders the calendar page field.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function calendar_page_cb( $args ): void {
		$options = get_option( 'se_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 0;
		?>
		<select
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
		>
			<option value=""><?php esc_html_e( 'Select a page', 'simple-events' ); ?></option>
			<?php
			$pages = get_pages();
			foreach ( $pages as $page ) {
				?>
				<option
					value="<?php echo esc_attr( $page->ID ); ?>"
					<?php selected( $value, $page->ID ); ?>
				>
					<?php echo esc_html( $page->post_title ); ?>
				</option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * Generates a callback function for calendar download.
	 *
	 * @param array $args An array of arguments for the function.
	 *
	 * @return void
	 */
	public static function cal_download_cb( $args ) {
		$options = get_option( 'se_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 'calendar';
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="se_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
			value="<?php echo esc_attr( $value ? $value : 'calendar' ); ?>"
			required
		/>
		<p>
		<?php
			echo wp_kses_post(
				sprintf(
					// translators: %1$s is the Archive Slug for Simple events concatenated with the download endpoint.
					__( 'Download Endpoint: <a href="%1$s">%1$s</a>', 'simple-events' ),
					esc_url( get_post_type_archive_link( SE_Event_Post_Type::$post_type ) . $value ),
				)
			);
		?>
		</p>
		<?php
	}

	/**
	 * Add a submenu page for plugin settings.
	 *
	 * @return void
	 */
	public static function options_page() {
		add_submenu_page(
			'edit.php?post_type=' . SE_Event_Post_Type::$post_type,
			__( 'Settings', 'simple-events' ),
			__( 'Settings', 'simple-events' ),
			'manage_options',
			'settings',
			array( __CLASS__, 'options_page_html' )
		);
	}

	/**
	 * Displays the plugin settins page.
	 *
	 * @return void
	 */
	public static function options_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'se_messages', 'se_message', __( 'Settings Saved', 'simple-events' ), 'updated' );
			flush_rewrite_rules();
		}

		settings_errors( 'se_messages' );
		?>
		<div class="wrap">

			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">

				<?php
				// Output security fields for the `simple_events` settings.
				settings_fields( 'simple_events' );

				// Output sections and their fields.
				do_settings_sections( 'simple_events' );

				// Output save settings button.
				submit_button( 'Save Settings' );
				?>

			</form>

		</div>
		<?php
	}

	/**
	 * Mark existing ticket only orders as completed.
	 *
	 * @return void
	 */
	public static function mark_existing_orders_as_completed() {
		$orders = wc_get_orders(
			array(
				'limit'   => -1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);

		// Disable email notifications
		add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

		$updated_orders = 0;

		foreach ( $orders as $order ) {
			$order        = wc_get_order( $order );
			$order_status = $order->get_status();

			// Skip if order is already completed or isn't being processed.
			if ( 'completed' === $order_status || 'processing' !== $order_status ) {
				continue;
			}

			$items               = $order->get_items();
			$should_autocomplete = true;

			// Check if order contains only tickets.
			foreach ( $items as $item ) {
				if ( ! wc_box_office_is_product_ticket( $item['product_id'] ) ) {
					$should_autocomplete = false;
				}
			}

			// Mark order as completed if it contains only tickets.
			if ( $should_autocomplete ) {
				$order->update_status( 'completed' );
				++$updated_orders;
			}
		}

		// Enable email notifications again
		remove_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

		// translators: %d is the number of orders updated.
		wp_send_json_success( sprintf( __( '%d orders updated successfully', 'simple-events' ), $updated_orders ) );
	}
}

SE_Settings::init();
