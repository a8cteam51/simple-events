<?php

/**
 * Class Calendar
 */
class SE_Calendar {

	public const BASE_PATH           = 'simple-events';
	public const REST_ROUTE_CALENDAR = 'calendar';

	/**
	 * Singleton instance.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Singleton instance constructor.
	 *
	 * @return void
	 */
	private function __construct() {    }

	/**
	 * Get singleton instance.
	 *
	 * @return SE_Calendar|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new SE_Calendar();
		}

		return self::$instance;
	}

	/**
	 * Register Calenar rest routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::BASE_PATH,
			self::REST_ROUTE_CALENDAR,
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'api_get_month_days' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	/**
	 * Return an array with the days of the week, numbered with respect to the start_of_week WP option
	 *
	 * @param string $format The display format for the days of the week.
	 *
	 * @return array Days of the week.
	 **@category Events
	 */
	public function simple_events_get_days_of_week( $format = null ) {
		global $wp_locale;

		for ( $i = 0; $i <= 6; $i++ ) {
			$day                       = $wp_locale->get_weekday( $i );
			$weekdays['full'][ $i ]    = $day;
			$weekdays['short'][ $i ]   = $wp_locale->get_weekday_abbrev( $day );
			$weekdays['initial'][ $i ] = $wp_locale->get_weekday_initial( $day );
		}

		switch ( $format ) {
			case 'min':
				$days_of_week = $weekdays['initial'];
				break;

			case 'short':
				$days_of_week = $weekdays['short'];
				break;

			default:
				$days_of_week = $weekdays['full'];
				break;
		}

		$start_of_week = get_option( 'start_of_week', 0 );
		for ( $i = 0; $i < $start_of_week; $i++ ) {
			$day = $days_of_week[ $i ];
			unset( $days_of_week[ $i ] );
			$days_of_week[ $i ] = $day;
		}

		return apply_filters( 'simple_events_get_days_of_week', $days_of_week );
	}


	/**
	 * A function to create a DateTime object with an optional timezone.
	 *
	 * @param mixed       $date_time The date and time to create the object from.
	 * @param string|null $timezone  The optional timezone to use for the object. If null, the site timezone is used.
	 *
	 * @return DateTime The created DateTime object.
	 */
	public function create_date_time( $date_time, $timezone = null ): DateTime {
		/**
		 * If no timezone is passed, use the site timezone
		 */
		if ( null === $timezone ) {
			$timezone = wp_timezone_string();
		}

		try {
			$date_time_object = new DateTime( $date_time, new DateTimeZone( $timezone ) );
		} catch ( Exception $e ) {
			$date_time_object = new DateTime();
			// todo handle exception
		}

		return $date_time_object;
	}


	/**
	 * Create a DateTime object from a timestamp, with an optional timezone.
	 *
	 * @param mixed       $timestamp The Unix timestamp to create the DateTime object from.
	 * @param string|null $timezone  The timezone to be used, or null to use the site timezone.
	 *
	 * @return DateTime The created DateTime object.
	 */
	public function create_date_time_from_timestamp( $timestamp, $timezone = null ): DateTime {
		/**
		 * If no timezone is passed, use the site timezone
		 */
		if ( null === $timezone ) {
			$timezone = wp_timezone_string();
		}

		try {
			$date_time_object = new DateTime( 'now', new DateTimeZone( $timezone ) );
			$date_time_object->setTimestamp( $timestamp );
		} catch ( Exception $e ) {
			$date_time_object = new DateTime();
			// todo handle exception
		}

		return $date_time_object->setTimestamp( $timestamp );
	}


	/**
	 * Retrieves the days of the month and related event information.
	 *
	 * @param mixed $date The date to retrieve month information for.
	 *
	 * @return array The array containing month and event information.
	 */
	public function get_month_days( $date ): array {
		$start_of_week            = get_option( 'start_of_week', 0 );
		$current_date             = $this->create_date_time( $date );
		$current_day              = $this->create_date_time( 'now' )->setTime( 0, 0, 0 );
		$current_day_formatted    = $current_day->format( 'Y-m-d' );
		$current_month            = $current_date->format( 'n' );
		$data                     = array();
		$data['month_has_events'] = false;

		$start_day = $this->get_start_day( $current_date, $start_of_week );
		$end_day   = $this->get_end_day( $current_date, $start_of_week );

		$period = new DatePeriod( $start_day, new DateInterval( 'P1D' ), $end_day );

		foreach ( $period as $day ) {
			$day->setTime( 0, 0, 0 );
			$day_formatted = $day->format( 'Y-m-d' );
			$day_month     = $day->format( 'n' );
			$events        = $this->get_events_by_date( $day );

			if ( ! $data['month_has_events'] && ! empty( $events ) && $day_month === $current_month ) {
				$data['month_has_events'] = true;
			}

			$data['days'][] = array(
				'date'              => $day,
				'date_formatted'    => $day_formatted,
				'events'            => $events,
				'is_other_month'    => $day_month !== $current_month,
				'is_previous_month' => $day_month < $current_month,
				'is_next_month'     => $day_month > $current_month,
				'is_past'           => $day < $current_day,
				'is_today'          => $day_formatted === $current_day_formatted,
			);
		}
		return $data;
	}



	/**
	 * Get month days from the API request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public function api_get_month_days( WP_REST_Request $request ) {
		// Get the body from the request.
		$request_body = $request->get_json_params();

		// Check if the request has a date.
		$request_date = $request_body['date'];

		if ( ! $request_date ) {
			$request_date = 'now';
		}

		$request_date_time  = $this->create_date_time( $request_date );
		$previous_date_time = self::get_instance()->get_previous_month_with_events( $request_date_time );
		$next_date_time     = self::get_instance()->get_next_month_with_events( $request_date_time );

		// Retrieve attributes for template part.
		$request_attributes = $request_body['attributes'];

		if ( ! $request_attributes ) {
			$request_attributes = array( 'align' => 'wide' );
		}

		$month_data = $this->get_month_days( $request_date );

		$output = SE_Template_Loader::get_template_part(
			'calendar/calendar',
			'main',
			true,
			array(
				'attributes'       => $request_attributes,
				'current_date'     => $request_date_time->format( 'Y-m-01' ),
				'days'             => $month_data['days'],
				'month_has_events' => $month_data['month_has_events'],
				'previous_date'    => $previous_date_time?->format( 'Y-m-01' ),
				'next_date'        => $next_date_time?->format( 'Y-m-01' ),
			),
			true
		);

		$output = apply_filters( 'simple_events_api_calendar_render', $output );

		$response_data = array(
			'html' => $output,
		);

		$response = new WP_REST_Response( $response_data );

		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Get first previous event with events.
	 *
	 * @param DateTime $current_date Current date.
	 *
	 * @return DateTime|null
	 */
	public function get_previous_month_with_events( $current_date ) {
		global $wpdb;

		$previous_date_time = clone $current_date;
		$previous_date_time->modify( 'first day of this month' );
		$previous_date_time->settime( 0, 0, 0 );

		$sql_query = $wpdb->prepare(
			"SELECT start_meta.meta_value from {$wpdb->prefix}postmeta as start_meta WHERE start_meta.meta_key = 'se_event_date_start' AND  start_meta.meta_value < %s ORDER BY start_meta.meta_value DESC LIMIT 1;",
			$previous_date_time->getTimestamp()
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$previous_event = $wpdb->get_row( $sql_query ); // the query is prepared above

		if ( empty( $previous_event ) ) {
			return null;
		}

		return $this->create_date_time_from_timestamp( $previous_event->meta_value );
	}

	/**
	 * Get first next event with events.
	 *
	 * @param DateTime $current_date Current date.
	 *
	 * @return DateTime|null
	 */
	public function get_next_month_with_events( $current_date ) {
		global $wpdb;

		$next_date_time = clone $current_date;
		$next_date_time->modify( 'last day of this month' );
		$next_date_time->settime( 23, 23, 59 );

		$sql_query = $wpdb->prepare(
			"SELECT start_meta.meta_value from {$wpdb->prefix}postmeta as start_meta WHERE start_meta.meta_key = 'se_event_date_start' AND  start_meta.meta_value > %s ORDER BY start_meta.meta_value ASC LIMIT 1;",
			$next_date_time->getTimestamp()
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$next_event = $wpdb->get_row( $sql_query ); // the query is prepared above

		// If we dont have a next date, check if we have any end dates.
		if ( empty( $next_event ) ) {
			$sql_query = $wpdb->prepare(
				"SELECT end_meta.meta_value from {$wpdb->prefix}postmeta as end_meta WHERE end_meta.meta_key = 'se_event_date_end' AND  end_meta.meta_value > %s ORDER BY end_meta.meta_value ASC LIMIT 1;",
				$next_date_time->getTimestamp()
			);

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$next_event = $wpdb->get_row( $sql_query ); // the query is prepared above
		}

		if ( empty( $next_event ) ) {
			return null;
		}

		return $this->create_date_time_from_timestamp( $next_event->meta_value );
	}


	/**
	 * Retrieves events from the database for a given date.
	 *
	 * @param mixed $date The date to retrieve events for.
	 *
	 * @return array The array of events for the given date.
	 */
	private function get_events_by_date( $date ): array {
		global $wpdb;

		$day_events = array();

		$start_timestamp = $date->setTime( 0, 0, 0 )->getTimeStamp();
		$end_timestamp   = $date->setTime( 23, 59, 59 )->getTimestamp();

		$sql_query = $wpdb->prepare(
			"
SELECT * from {$wpdb->prefix}posts
INNER JOIN {$wpdb->prefix}postmeta AS start_meta ON {$wpdb->prefix}posts.ID = start_meta.post_id AND start_meta.meta_key = 'se_event_date_start'
INNER JOIN {$wpdb->prefix}postmeta AS end_meta ON {$wpdb->prefix}posts.ID = end_meta.post_id AND end_meta.meta_key = 'se_event_date_end'
WHERE wp_posts.post_type = %s AND (wp_posts.post_status = 'publish') AND
((start_meta.meta_value >= %s AND start_meta.meta_value < %s)
OR
(start_meta.meta_value < %s AND end_meta.meta_value > %s)
OR
(end_meta.meta_value <= %s AND end_meta.meta_value > %s))
GROUP BY {$wpdb->prefix}posts.ID
ORDER BY start_meta.meta_value ASC;",
			'se-event',
			$start_timestamp,
			$end_timestamp,
			$start_timestamp,
			$end_timestamp,
			$end_timestamp,
			$start_timestamp
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$all_events = $wpdb->get_results( $sql_query ); // the query is prepared above
		if ( $all_events ) {
			foreach ( $all_events as $event ) {
				$event_dates = se_event_get_dates( $event->ID );

				if ( ! $event_dates ) {
					continue;
				}

				foreach ( $event_dates as $event_date ) {
					$event->event_start_date = $this->create_date_time_from_timestamp( $event_date['datetime_start'] );
					$event->event_end_date   = $this->create_date_time_from_timestamp( $event_date['datetime_end'] );
					$event->hide_start_time  = '1' === get_post_meta( $event->ID, 'se_event_hide_start_time', true );
					$event->hide_end_time    = '1' === get_post_meta( $event->ID, 'se_event_hide_end_time', true );
					if ( $event_date['datetime_start'] >= $start_timestamp && $event_date['datetime_start'] <= $end_timestamp ) {
						$new_event                     = clone $event;
						$new_event->event_start_date   = $this->create_date_time_from_timestamp( $event_date['datetime_start'] );
						$new_event->event_end_date     = $this->create_date_time_from_timestamp( $event_date['datetime_end'] );
						$new_event->open_in_new_window = (bool) get_post_meta( $event->ID, 'se_event_open_in_new_window', true );

						$day_events[] = $new_event;
					}
				}
			}
		}
		return $day_events;
	}



	/**
	 * Get the start day based on the given date and start of the week.
	 *
	 * @param mixed   $date          The date for which to calculate the start day.
	 * @param integer $start_of_week The start of the week (0-6, where 0 is Sunday).
	 *
	 * @return DateTime The start day based on the given date and start of the week.
	 */
	private function get_start_day( $date, $start_of_week ) {
		$start_date         = clone $date;
		$start_day_interval = 0;

		$start_day_week_position = $date->format( 'w' );

		if ( $start_of_week > 0 && 0 === intval( $start_day_week_position ) ) {
			$start_day_week_position = 7;
		}

		if ( 0 !== intval( $start_day_week_position ) ) {
			$start_day_interval = abs( 1 - $start_day_week_position );
		}

		return $start_date->sub( new DateInterval( 'P' . $start_day_interval . 'D' ) );
	}


	/**
	 * Get the end day based on the given date and start of the week.
	 *
	 * @param Date    $date          The date to calculate the end day from.
	 * @param integer $start_of_week The start of the `week (0-6, where 0 is Sunday).
	 *
	 * @return DateTime The end day based on the given date and start of the week.
	 */
	private function get_end_day( $date, $start_of_week ) {
		$end_date          = clone $date;
		$last_day_interval = 0;

		$last_day_of_month      = $end_date->modify( 'last day of this month' );
		$last_day_week_position = $last_day_of_month->format( 'w' );

		if ( $start_of_week > 0 && 0 === intval( $last_day_week_position ) ) {
			$last_day_week_position = 7;
		}

		if ( 0 !== intval( $last_day_week_position ) ) {
			$last_day_interval = 7 - $last_day_week_position;
		}

		$end_date->setTime( 23, 59, 59 );

		return $end_date->add( new DateInterval( 'P' . $last_day_interval . 'D' ) );
	}
}
