<?php
/**
 * Event Date Class.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Date Class.
 */
class SE_Event_Dates {


	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'simple-events';

	/**
	 * The dates rest base.
	 *
	 * @var string
	 */
	protected $rest_base_dates = 'event-dates';

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		$instance = new self();

		add_action( 'rest_api_init', array( $instance, 'register_rest_routes' ) );
	}

	/**
	 * Registers all rest routes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// Get event dates.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base_dates . '/(?P<event_id>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event_dates' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base_dates . '/(?P<event_id>[\d]+)/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sync_event_dates' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'dates' => array(
						'required'    => true,
						'type'        => 'array',
						'description' => 'Array of date objects from dateManager',
					),
				),
			)
		);
	}

	/**
	 * Get event dates.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_event_dates( WP_REST_Request $request ): WP_REST_Response {
		// If we dont have an event ID, return an error.
		$event_id = $request->get_param( 'event_id' );
		if ( empty( $event_id ) || ! is_numeric( $event_id ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_event_id',
					'message' => __( 'Invalid event ID provided.', 'simple-events' ),
				),
				400
			);
		}

		// Check if we have a valid event.
		$event = get_post( $event_id );
		if ( ! $event || 'se-event' !== $event->post_type ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_event',
					'message' => __( 'Invalid event provided.', 'simple-events' ),
				),
				404
			);
		}

		try {
			$dates = se_event_get_event_dates( $event_id );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'code'    => 'server_error',
					'message' => __( 'An error occurred while fetching event dates.', 'simple-events' ),
				),
				500
			);
		}

		// Create the return.
		$data = array(
			'event_id' => $event_id,
			'dates'    => $dates,
			'timezone' => get_post_meta( $event_id, 'se_event_timezone', true ) ?: wp_timezone_string(), // phpcs:ignore
		);

		// Return the response.
		return new WP_REST_Response(
			$data,
			200
		);
	}

	/**
	 * Sync event dates.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function sync_event_dates( WP_REST_Request $request ): WP_REST_Response {
		$event_id = $request->get_param( 'event_id' );
		$dates    = $request->get_param( 'dates' );
		$nonce    = $request->get_param( 'nonce' );

		// Check if the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'se_event_nonce' ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'Invalid nonce provided.', 'simple-events' ),
				),
				403
			);
		}

		// Get the existing dates.
		$existing_date_ids = array_map(
			function ( $date ) {
				return $date['id'];
			},
			se_event_get_event_dates( $event_id )
		);

		// Iterate over the existing dates and delete any that are not in the new dates.
		foreach ( $existing_date_ids as $existing_date_id ) {
			if ( ! in_array( $existing_date_id, array_column( $dates, 'id' ), true ) ) {
				wp_delete_post( $existing_date_id, true );
			}
		}
		// Iterate over the dates and update the event dates.
		foreach ( $dates as $date ) {
			// If we dont have a date ID, create a new date.
			if ( ! isset( $date['id'] ) ) {
				$event_date = se_event_create_event_date( $event_id, $date );
				// If we dont have a WP_Post object, return an error.
				if ( ! $event_date ) {
					return new WP_REST_Response(
						array(
							'code'    => 'server_error',
							'message' => __( 'An error occurred while creating the event date.', 'simple-events' ),
						),
						500
					);
				}
				$date['id'] = $event_date->ID;
			}

			// Update the even dates meta.
			$event_date_id = absint( $date['id'] );
			update_post_meta( $event_date_id, 'se_event_date_start', esc_attr( $date['start_date'] ) );
			update_post_meta( $event_date_id, 'se_event_date_end', esc_attr( $date['end_date'] ) );
			update_post_meta( $event_date_id, 'se_event_all_day', boolval( $date['all_day'] ) );
			update_post_meta( $event_date_id, 'se_event_hide_from_calendar', boolval( $date['hide_from_calendar'] ) );
			update_post_meta( $event_date_id, 'se_event_hide_from_feed', boolval( $date['hide_from_feed'] ) );
		}

		// Update the event version.
		update_post_meta( $event_id, 'se_event_version', SE_MIGRATION_VERSION );

		// Re fetch the event dates.
		try {
			$dates = se_event_get_event_dates( $event_id );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'code'    => 'server_error',
					'message' => __( 'An error occurred while fetching event dates.', 'simple-events' ),
				),
				500
			);
		}

		// Update all legacy meta values.
		self::update_legacy_meta_values( $event_id, $dates );

		// Return the response.
		return new WP_REST_Response(
			array(
				'code'    => 'success',
				'message' => __( 'Event dates synced successfully.', 'simple-events' ),
				'dates'   => $dates,
			),
			200
		);
	}

	/**
	 * Update all legacy meta values.
	 *
	 * @param integer $event_id The event ID.
	 * @param array   $dates    The dates.
	 *
	 * @return void
	 */
	public static function update_legacy_meta_values( $event_id, $dates ): void {
		// Create the legacy date array.
		$legacy_dates = array_map(
			function ( $date ) {
				return array(
					'datetime_start' => $date['start_date'],
					'datetime_end'   => $date['end_date'],
					'all_day'        => $date['all_day'],
				);
			},
			$dates
		);

		// Update the legacy meta values.
		update_post_meta( $event_id, 'se_event_dates', $legacy_dates );

		se_event_update_event_query_dates( $event_id );
	}

	/**
	 * Find event dates.
	 *
	 * @deprecated 2.2.0 Use SE_Event_Query_Utils::get_event_dates_for_range() instead.
	 *
	 * @param string  $start_date         The start date as a timestamp.
	 * @param string  $end_date           The end date as a timestamp.
	 * @param boolean $hide_from_calendar Legacy, no longer used.
	 * @param boolean $hide_from_feed     Legacy, no longer used.
	 *
	 * @return array
	 */
	public static function find_event_dates( $start_date, $end_date, $hide_from_calendar, $hide_from_feed ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		_deprecated_function( __METHOD__, '2.2.0', 'SE_Event_Query_Utils::get_event_dates_for_range()' );

		$results = SE_Event_Query_Utils::get_event_dates_for_range( (int) $start_date, (int) $end_date );

		// Remove the event dates that are hidden from the calendar or feed.
		return array_filter(
			$results,
			function ( $event_date ) use ( $hide_from_calendar, $hide_from_feed ) {
				return ! $event_date['event_hide_from_calendar'] && ! $event_date['event_hide_from_feed'];
			}
		);
	}

	/**
	 * Get the events dates for a given date.
	 *
	 * @deprecated 2.2.0 Use SE_Event_Query_Utils::get_event_dates_for_range() instead (Returns all results, doesnt exclude hidden events).
	 *
	 * @param string  $date               The date to get the events for.
	 * @param boolean $hide_from_calendar Legacy flag, no longer used in replacement function.
	 * @param boolean $hide_from_feed     Legacy flag, no longer used in replacement function.
	 *
	 * @return array
	 */
	public static function get_event_dates_for_date( $date, $hide_from_calendar = false, $hide_from_feed = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		_deprecated_function( __METHOD__, '2.2.0', 'SE_Event_Query_Utils::get_event_dates_for_range()' );

		$date_time = DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' 00:00:00', wp_timezone() );
		if ( false === $date_time ) {
			return array();
		}
		$results = SE_Event_Query_Utils::get_event_dates_for_range(
			$date_time->setTime( 0, 0, 0 )->getTimestamp(),
			$date_time->setTime( 23, 59, 59 )->getTimestamp()
		);

		// Remove the event dates that are hidden from the calendar or feed.
		return array_filter(
			$results,
			function ( $event_date ) use ( $hide_from_calendar, $hide_from_feed ) {
				return ! $event_date['event_hide_from_calendar'] && ! $event_date['event_hide_from_feed'];
			}
		);
	}

	/**
	 * Map the events dates to the event dates.
	 *
	 * @deprecated 2.2.0 Use SE_Event_Query_Utils::map_events_dates_to_event_dates() instead.
	 *
	 * @param array $events_dates The events dates.
	 *
	 * @return array{event_id: int, event_date_id: int, event_start_date: string, event_end_date: string, event_all_day: bool, event_hide_from_calendar: bool, event_hide_from_feed: bool}
	 */
	public static function map_events_dates_to_event_dates( $events_dates ): array {
		_deprecated_function( __METHOD__, '2.2.0', 'SE_Event_Query_Utils::map_events_dates_to_event_dates()' );
		return SE_Event_Query_Utils::map_events_dates_to_event_dates( $events_dates );
	}

	/**
	 * Delete all event dates for a given event.
	 *
	 * @param integer $event_id The event ID.
	 *
	 * @return void
	 */
	public static function delete_all_event_dates( $event_id ): void {
		// Get all the event dates.
		try {
			$event_dates = se_event_get_event_dates( $event_id );
		} catch ( \Exception $e ) {
			// If we can't get the dates, there's nothing to delete
			return;
		}

		// Iterate over the event dates and delete them.
		foreach ( $event_dates as $event_date ) {
			wp_delete_post( $event_date['id'], true );
		}
	}

	/**
	 * Delete a single event date.
	 *
	 * @param integer $event_date_id The event date ID.
	 *
	 * @return void
	 */
	public static function delete_event_date( $event_date_id ): void {
		wp_delete_post( $event_date_id, true );
	}
}
SE_Event_Dates::init();
