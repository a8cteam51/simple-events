<?php
/**
 * Template Loader.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Domain\ValueObject\DateTime as ICalDateTime;


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
		$events   = array();
		$post_id  = false;
		$v_events = array();

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
				$event_dates = se_event_get_event_dates( $event_id );

				foreach ( $event_dates as $event_date ) {
					// If the date is hidden from the calendar, skip it.
					if ( true === (bool) $event_date['hide_from_calendar'] ) {
						continue;
					}

					if ( empty( $event_date['start_date'] ) || empty( $event_date['end_date'] ) ) {
						continue;
					}

					$date_start = new \DateTimeImmutable();
					$date_start = $date_start->setTimestamp( $event_date['start_date'] );

					$date_end = new \DateTimeImmutable();
					$date_end = $date_end->setTimestamp( $event_date['end_date'] );

					// Set the start and end as UTC.
					$start_utc = $date_start->setTimezone( new \DateTimeZone( 'UTC' ) );
					$end_utc   = $date_end->setTimezone( new \DateTimeZone( 'UTC' ) );

					// Build the occurrence to match old semantics:
					$same_day   = $date_start->format( 'Y-m-d' ) === $date_end->format( 'Y-m-d' );
					$is_all_day = filter_var( $event_date['all_day'], FILTER_VALIDATE_BOOLEAN );

					if ( ! $is_all_day ) {
						$occurrence = new TimeSpan(
							new ICalDateTime( $start_utc, true ),
							new ICalDateTime( $end_utc, true )
						);
					} elseif ( $same_day ) {
						$occurrence = new SingleDay( new Date( $date_start ) );
					} else {
						// so add +1 day to include the final day in the all-day span.
						$end_date_exclusive = $date_end->modify( '+1 day' );
						$occurrence         = new MultiDay( new Date( $start_utc ), new Date( $end_date_exclusive ) );
					}

					// Create the event, with a unique but reproducible ID.
					$uid   = md5( get_site_url() . '_' . $event_id . '_' . $event_date['start_date'] . '_' . $event_date['end_date'] );
					$event = ( new Event( new UniqueIdentifier( $uid ) ) )
						->setOccurrence( $occurrence )
						->setSummary( get_the_title( $event_id ) )
						->setDescription( esc_html( get_the_excerpt( $event_id ) ) );

					// Ensure we're working with a boolean.
					$event_date['all_day'] = filter_var( $event_date['all_day'], FILTER_VALIDATE_BOOLEAN );

					// Get the event location from the post meta.
					$location = get_post_meta( $event_id, 'se_event_location', true );
					if ( ! empty( $location ) ) {
						$location_object = new Location( $location );
						$location_object = apply_filters( 'se_calendar_export_event_location', $location_object, $event_id, $event_date );
						$event->setLocation( $location_object );
					}

					// Allow 3rd parties to modify the event.
					$event = apply_filters( 'se_calendar_export_event', $event, $event_id, $event_date );

					$v_events[] = $event;
				}
			}
		}
		// Create the calendar.
		$calendar = new Calendar( $v_events );
		$calendar->addTimeZone( new TimeZone( 'UTC' ) );

		// Allow 3rd parties to modify the calendar.
		$calendar = apply_filters( 'se_calendar_export_calendar', $calendar );

		// Create the presenter.
		$calendar_presenter = new CalendarFactory();

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cal.ics"' );

		$renderable = (string) $calendar_presenter->createCalendar( $calendar );

		// Allow 3rd parties to modify the output.
		$renderable = apply_filters( 'se_calendar_export_rendered', $renderable );

		echo $renderable; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}

SE_Calendar_Export::init();
