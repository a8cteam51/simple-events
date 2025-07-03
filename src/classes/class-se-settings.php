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
		add_action( 'wp_ajax_se_clear_orphaned_events', array( __CLASS__, 'clear_orphaned_events' ), 10 );
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

		// Treat each date as own event for navigation.
		add_settings_field(
			'treat_each_date_as_own_event',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Treat each date as own event%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>When this is selected, next and previous events will treat consecutive dates as unique.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'treat_each_date_as_own_event',
			)
		);

		// Allow grouping of dates with different time.
		add_settings_field(
			'allow_grouping_dates_different_time',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Allow grouping of dates with different times.%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>When enabled, events with different time ranges (e.g., 9AM-5PM vs 10AM-6PM) will be grouped separately. When disabled, only events with identical times will be grouped together.</em></small>' ),
			),
			array( __CLASS__, 'field_cb' ),
			'simple_events',
			'se_section_archives',
			array(
				'label_for' => 'allow_grouping_dates_different_time',
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

		add_settings_field(
			'clear_orphaned_events',
			sprintf(
				// translators: %s is a HTML break tag.
				__( 'Clear orphaned events.%s', 'simple-events' ),
				wp_kses_post( '<br><small><em>Removes events with missing or corrupted data.</em></small>' ),
			),
			array( __CLASS__, 'clear_orphaned_events_cb' ),
			'simple_events',
			'se_section_calendar',
			array(
				'action'   => 'se_clear_orphaned_events',
				'btn_text' => __( 'Clear orphaned events', 'simple-events' ),
			)
		);

		// Add the migrate events button, if we have events to migrate.
		if ( SE_Migrate_Events::has_events_to_migrate() ) {
			add_settings_field(
				'migrate_events',
				esc_html__( 'Migrate Events', 'simple-events' ),
				array( __CLASS__, 'migrate_events_cb' ),
				'simple_events',
				'se_section_calendar',
				array(
					'label_for' => 'migrate_events',
				)
			);
		}
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
	 * A function for generating the clear orphaned events AJAX button.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function clear_orphaned_events_cb( $args ) {
		?>
		<button
			type="button"
			id="se_clear_orphaned_btn"
			class="button button-primary"
			data-action="<?php echo esc_attr( $args['action'] ); ?>"
		>
			<?php echo esc_html( $args['btn_text'] ); ?>
		</button>
		<div id="se_clear_orphaned_response"></div>
		<?php
	}

	/**
	 * A function for generating a migrate events button.
	 *
	 * @param array $args Display arguments.
	 *
	 * @return void
	 */
	public static function migrate_events_cb( $args ) { // phpcs:ignore
		$events = SE_Migrate_Events::get_events_to_migrate();
		?>
		<div id="migrate_events" style="scroll-margin-top: 20px;">
		<div id="se_migrate_events_wrapper" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; max-height: 400px; overflow-y: auto;">
			<h4 style="margin-top: 0; margin-bottom: 15px; color: #23282d; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Events to Migrate:', 'simple-events' ); ?></h4>
			<?php if ( empty( $events ) ) { ?>
				<p style="color: #666; font-style: italic; text-align: center; padding: 20px; margin: 0;"><?php esc_html_e( 'No events need migration.', 'simple-events' ); ?></p>
			<?php } else { ?>
				<?php foreach ( $events as $event ) { ?>
					<div class="se_migrate_event" data-event-id="<?php echo esc_attr( $event->ID ); ?>" data-status="pending" style="background: #fff; border: 1px solid #e1e1e1; border-radius: 4px; padding: 12px 15px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease;">
						<div style="display: flex; align-items: center; gap: 15px; line-height: 1.5; font-size: 13px;">
							<div style="flex: 1; color: #23282d; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
								<strong style="color: #0073aa; font-weight: 600;">#<?php echo esc_html( $event->ID ); ?></strong> - <?php echo esc_html( $event->post_title ); ?>
							</div>
							<div style="flex: 0 0 auto; display: flex; align-items: center; gap: 10px;">
								<span style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px; font-family: monospace; font-size: 12px; color: #666;">
									v<?php echo esc_html( get_post_meta( $event->ID, 'se_event_version', true ) ); ?>
								</span>
								<span class="se_migrate_event_status" style="background: #ffc107; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 500;">
									<?php esc_html_e( 'Pending', 'simple-events' ); ?>
								</span>
							</div>
						</div>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
		<button
			type="button"
			id="se_migrate_events_btn"
			class="button button-primary"
			style="background: #0073aa; border-color: #0073aa; color: #fff; padding: 8px 16px; font-size: 13px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s ease; margin-top: 10px;"
			<?php echo empty( $events ) ? 'disabled' : ''; ?>
		>
			<?php esc_html_e( 'Migrate Events', 'simple-events' ); ?>
		</button>
		</div>

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

	/**
	 * Clear orphaned events that have missing or corrupted data.
	 *
	 * @return void
	 */
	public static function clear_orphaned_events() {
		// Query all event dates where the parent event is missing.
		global $wpdb;

		$orphaned_events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT child.ID, child.post_title, child.post_parent FROM {$wpdb->prefix}posts AS child
				LEFT JOIN {$wpdb->prefix}posts AS parent ON child.post_parent = parent.ID
				WHERE child.post_type = %s AND (child.post_parent = 0 OR parent.ID IS NULL)",
				SE_Event_Post_Type::$event_date_post_type
			)
		);

		$deleted_events = 0;

		// Delete orphaned events
		if ( ! empty( $orphaned_events ) ) {
			foreach ( $orphaned_events as $event ) {
				wp_delete_post( $event->ID, true ); // Force delete permanently
				++$deleted_events;
			}
		}

		// translators: %d is the number of orphaned events deleted.
		wp_send_json_success( sprintf( __( '%d orphaned events deleted successfully', 'simple-events' ), $deleted_events ) );
	}
}

SE_Settings::init();
