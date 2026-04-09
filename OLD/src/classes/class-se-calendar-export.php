<?php
/**
 * Template Loader.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Loader Class.
 */
class SE_Calendar_Export {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_feed' ) );
	}

	/**
	 * Add custom feeds.
	 *
	 * @return void
	 */
	public static function add_feed() {
		$options = get_option( 'se_options' );

		// Bail if the download calendar is disabled.
		if ( isset( $options['disable_download_calendar'] ) ) {
			return;
		}

		$ep = isset( $options['cal_download_endpoint'] ) ? $options['cal_download_endpoint'] : 'calendar';
		add_feed( $ep, array( __CLASS__, 'icalendar' ) );
	}

	/**
	 * Build iCal output.
	 *
	 * @return void
	 */
	public static function icalendar() {
		$events     = array();
		$post_id    = false;
		$v_calendar = new \Eluceo\iCal\Component\Calendar( get_site_url() );

		if ( ! empty( $_REQUEST['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = intval( $_REQUEST['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( ! empty( $post_id ) && get_post_type( $post_id ) === SE_Event_Post_Type::$post_type ) {
			$events[] = $post_id;
		}

		// Get all events, if no event provided so far.
		if ( empty( $events ) ) {
			$events_query_args = array(
				'post_type'      => SE_Event_Post_Type::$post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
			);

			$events = get_posts( apply_filters( 'se_calendar_export_query_args', $events_query_args ) );
		}

		// Get dates.
		if ( ! empty( $events ) ) {
			foreach ( $events as $event_id ) {
				$event_dates = se_event_get_dates( $event_id );

				foreach ( $event_dates as $event_date ) {
					$v_event = new \Eluceo\iCal\Component\Event();

					if ( empty( $event_date['datetime_start'] ) || empty( $event_date['datetime_end'] ) ) {
						continue;
					}

					$date_start = new \DateTime();
					$date_start->setTimestamp( $event_date['datetime_start'] );

					$date_end = new \DateTime();
					$date_end->setTimestamp( $event_date['datetime_end'] );

					$v_event
						->setDtStart( $date_start )
						->setDtEnd( $date_end )
						->setSummary( se_event_get_title( $event_id ) );

					// Ensure we're working with a boolean.
					$event_date['all_day'] = filter_var( $event_date['all_day'], FILTER_VALIDATE_BOOLEAN );

					if ( $event_date['all_day'] ) {
						$v_event->setNoTime( true );
					}

					$v_calendar->addComponent( $v_event );
				}
			}
		}

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cal.ics"' );

		echo $v_calendar->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}
}

SE_Calendar_Export::init();
