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
	 */
	public static function init() {
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
			'timezone' => get_post_meta( $event_id, 'se_event_timezone', true ) ?: wp_timezone_string(),
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
		$existing_date_ids = array_map( function( $date ) {
			return $date['id'];
		}, se_event_get_event_dates( $event_id ) );

		// Iterate over the existing dates and delete any that are not in the new dates.
		foreach ( $existing_date_ids as $existing_date_id ) {
			if ( ! in_array( $existing_date_id, array_column( $dates, 'id' ) ) ) {
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
	 * @param int $event_id The event ID.
	 * @param array $dates The dates.
	 *
	 * @return void
	 */
	public static function update_legacy_meta_values( $event_id, $dates ): void {
		// Create the legacy date array.
		$legacy_dates = array_map( function( $date ) {
			return array(
				'start_datetime' => $date['start_datetime'],
				'end_datetime' => $date['end_datetime'],
				'all_day' => $date['all_day'],
			);
		}, $dates );

		// Update the legacy meta values.
		update_post_meta( $event_id, 'se_event_dates', $legacy_dates );

		se_event_update_event_query_dates( $event_id );
	}

	/**
	 * Fiind event dates.
	 *
	 * @param string $start_date The start date as a timestamp.
	 * @param string $end_date The end date as a timestamp.
	 * @param bool $hide_from_calendar Whether the event is hidden from the calendar.
	 * @param bool $hide_from_feed Whether the event is hidden from the feed.
	 *
	 * @return array
	 */
	public static function find_event_dates( $start_date, $end_date, $hide_from_calendar, $hide_from_feed ): array {
		// Create the timestamp for the start and end of the $start_date.
		$start_date_time = se_create_date_time_from_timestamp( $start_date );
		$start_date_range = [
			$start_date_time->setTime( 0, 0, 0 )->getTimestamp(),
			$start_date_time->setTime( 23, 59, 59 )->getTimestamp(),
		];

		// Query the event dates.
		$query = new WP_Query(
			array(
				'post_type' => SE_Event_Post_Type::$event_date_post_type,
				'meta_query' => array(
					'relation' => 'OR',
					// Exact match for all conditions
					array(
						'relation' => 'AND',
						array(
							'key' => 'se_event_date_start',
							'value' => $start_date,
							'compare' => '>=',
						),
						array(
							'key' => 'se_event_date_end',
							'value' => $end_date,
							'compare' => '<=',
						),
					),
					// All day events with matching start date
					array(
						'relation' => 'AND',
						array(
							'key' => 'se_event_date_start',
							'value' => $start_date_range,
							'compare' => 'BETWEEN',
						),
						array(
							'key' => 'se_event_all_day',
							'value' => '1',
							'compare' => '=',
						),
					),
				),
				'posts_per_page' => -1,
				'orderby' => 'meta_value',
				'meta_key' => 'se_event_date_start',
				'order' => 'ASC',
				'post_status' => 'publish',
			)
		);


		$mapped = self::map_events_dates_to_event_dates( $query->posts );

		// Remove the event dates that are hidden from the calendar or feed.
		return array_filter( $mapped, function( $event_date ) use ( $hide_from_calendar, $hide_from_feed ) {
			return ! $event_date['event_hide_from_calendar'] && ! $event_date['event_hide_from_feed'];
		} );
	}

	/**
	 * Get the events dates for a given date.
	 *
	 * @param string $date The date to get the events for.
	 * @param bool $hide_from_calendar Whether the event is hidden from the calendar.
	 * @param bool $hide_from_feed Whether the event is hidden from the feed.
	 *
	 * @return array
	 */
	public static function get_event_dates_for_date( $date, $hide_from_calendar = false, $hide_from_feed = false ): array {
		// Get the start of the day for the date.
		$date_time = new DateTime( $date );
		$date_time->setTime( 0, 0, 0 );
		// set as a timestamp.
		$start_date = $date_time->getTimestamp();

		// Get the end of the day for the date.
		$date_time->setTime( 23, 59, 59 );
		$end_date = $date_time->getTimestamp();

		// Get the events dates.
		$events_dates = self::find_event_dates( $start_date, $end_date, $hide_from_calendar, $hide_from_feed );

		// Return the events dates.
		return $events_dates;
	}

	/**
	 * Map the events dates to the event dates.
	 *
	 * @param array $events_dates The events dates.
	 *
	 * @return array{event_id: int, event_date_id: int, event_start_date: string, event_end_date: string, event_all_day: bool, event_hide_from_calendar: bool, event_hide_from_feed: bool}
	 */
	public static function map_events_dates_to_event_dates( $events_dates ): array {
		$compiled_events = array();
		foreach ( $events_dates as $event_date ) {
			// Get the parent event.
			$event = get_post( $event_date->post_parent );
			if ( ! $event ) {
				continue;
			}

			// Get the event date.
			$start_date = get_post_meta( $event_date->ID, 'se_event_date_start', true );
			$end_date = get_post_meta( $event_date->ID, 'se_event_date_end', true );
			$all_day = get_post_meta( $event_date->ID, 'se_event_all_day', true );
			$hide_from_calendar = get_post_meta( $event_date->ID, 'se_event_hide_from_calendar', true );
			$hide_from_feed = get_post_meta( $event_date->ID, 'se_event_hide_from_feed', true );

			// Add the event date to the compiled events.
			$compiled_events[] = array(
				'event_id' => absint( $event->ID ),
				'event_date_id' => absint( $event_date->ID ),
				'event_start_date' => esc_attr( $start_date ),
				'event_end_date' => esc_attr( $end_date ),
				'event_all_day' => boolval( $all_day ),
				'event_hide_from_calendar' => boolval( $hide_from_calendar ),
				'event_hide_from_feed' => boolval( $hide_from_feed ),
			);
		}

		// Return the compiled events.
		return $compiled_events;
	}
}
SE_Event_Dates::init();
