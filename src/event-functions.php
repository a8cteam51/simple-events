<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get tickets attached to event.
 *
 * @param integer $event_id Event id.
 *
 * @return array|void
 */
function se_event_get_tickets( $event_id ) {
	$event = get_post( $event_id );

	if ( ! $event ) {
		return;
	}

	$products = array();

	// Parse blocks and get selected tickets.
	$blocks = parse_blocks( $event->post_content );

	foreach ( $blocks as $block ) {
		if ( 'simple-events/event-tickets' === $block['blockName'] ) {
			if ( ! empty( $block['attrs'] ) && ! empty( $block['attrs']['selected'] ) ) {
				$products = $block['attrs']['selected'];
				break;
			}
		}
	}

	// Validate ids.
	foreach ( $products as $key => $product ) {
		if ( ! wc_box_office_is_product_ticket( $product ) ) {
			unset( $products[ $key ] );
		}
	}

	return $products;
}

/**
 * Get tickets attached to event.
 *
 * @param integer $event_id Event id.
 *
 * @return mixed
 */
function se_event_get_ticket_prices( $event_id ) {
	$prices = array();

	$tickets = se_event_get_tickets( $event_id );

	if ( ! empty( $tickets ) ) {
		// Get prices.
		foreach ( $tickets as $ticket ) {
			$ticket = wc_get_product( $ticket );

			if ( ! $ticket ) {
				continue;
			}

			$prices[] = $ticket->get_price();
		}
	}

	// Sort prices.
	sort( $prices );

	if ( ! empty( $prices ) ) {
		return $prices;
	}

	return false;
}

/**
 * Get ticket stock for an event.
 *
 * @param integer $event_id Event id.
 *
 * @return mixed
 */
function se_event_get_tickets_stock( $event_id ) {
	$stock_total = 0;

	// Get ticket products.
	$tickets = se_event_get_tickets( $event_id );

	if ( ! empty( $tickets ) ) {
		foreach ( $tickets as $ticket ) {
			$ticket = wc_get_product( $ticket );

			if ( ! $ticket ) {
				continue;
			}

			$stock = ( $ticket->managing_stock() ? $ticket->get_stock_quantity() : false );

			if ( $stock && $stock > 0 ) {
				$stock_total += $stock;
			}
		}
	}

	if ( $stock_total < 1 ) {
		return false;
	}

	return $stock_total;
}

/**
 * Get event dates.
 *
 * @param integer $event_id    Event id.
 * @param array   $event_dates Event dates.
 *
 * @return mixed
 */
function se_event_get_dates( $event_id, $event_dates = null ) {
	if ( is_null( $event_dates ) ) {
		$event_dates = get_post_meta( $event_id, 'se_event_dates', true );
	}

	if ( empty( $event_dates ) ) {
		return apply_filters( 'se_event_dates', false, $event_id );
	}

	// Sort dates.
	if ( count( $event_dates ) > 1 ) {
		array_multisort(
			array_column( $event_dates, 'datetime_start' ),
			SORT_ASC,
			$event_dates
		);
	}

	return apply_filters( 'se_event_dates', $event_dates, $event_id );
}

/**
 * Gets only the future event dates in a formatted string.
 *
 * @param integer $event_id    Event id.
 * @param boolean $date_only   Whether to return only the date.
 * @param boolean $time_only   Whether to return only the time.
 * @param array   $event_dates Event dates.
 *
 * @return string
 */
function se_event_get_future_dates( $event_id, $date_only = false, $time_only = false, $event_dates = null ) {
	// Get required post meta.
	$event_dates      = se_event_get_dates( $event_id, $event_dates );
	$event_timezone   = get_post_meta( $event_id, 'se_event_timezone', true );
	$hide_end_time    = get_post_meta( $event_id, 'se_event_hide_end_time', true );
	$hide_start_time  = get_post_meta( $event_id, 'se_event_hide_start_time', true );
	$display_timezone = (bool) get_post_meta( $event_id, 'se_event_display_timezone', true );
	$now              = SE_Calendar::get_instance()->create_date_time( 'now' )->format( 'U' );

	if ( ! $event_dates ) {
		return '';
	}

	// Iterate over all the events and remove any where the start and end has passed.
	foreach ( $event_dates as $key => $date ) {
		if ( $date['datetime_start'] < $now && $date['datetime_end'] < $now ) {
			unset( $event_dates[ $key ] );
		}
	}
	if ( empty( $event_dates ) ) {
		return '';
	}

	return se_event_format_dates(
		$event_dates,
		$event_timezone,
		$hide_end_time,
		$hide_start_time,
		$display_timezone,
		false,
		false
	);
}
/**
 * Get the event dates in a formatted string.
 *
 * @param integer $event_id    Event id.
 * @param boolean $date_only   Whether to return only the date.
 * @param boolean $time_only   Whether to return only the time.
 * @param array   $event_dates Event dates.
 *
 * @return string
 */
function se_event_get_formatted_dates( $event_id, $date_only = false, $time_only = false, $event_dates = null ) {

	// Get required post meta.
	$event_dates      = se_event_get_dates( $event_id, $event_dates );
	$event_timezone   = get_post_meta( $event_id, 'se_event_timezone', true );
	$hide_end_time    = get_post_meta( $event_id, 'se_event_hide_end_time', true );
	$hide_start_time  = get_post_meta( $event_id, 'se_event_hide_start_time', true );
	$display_timezone = (bool) get_post_meta( $event_id, 'se_event_display_timezone', true );

	if ( ! $event_dates ) {
		return '';
	}

	return se_event_format_dates(
		$event_dates,
		$event_timezone,
		$hide_end_time,
		$hide_start_time,
		$display_timezone,
		$date_only,
		$time_only
	);
}

/**
 * Formats the dates for the event.
 *
 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates      Event dates.
 * @param string                                                                             $timezone         Timezone.
 * @param mixed                                                                              $hide_end_time    If we should hide the end time.
 * @param mixed                                                                              $hide_start_time  If we should hide the start time.
 * @param mixed                                                                              $display_timezone If we should display the timezone.
 * @param mixed                                                                              $date_only        If we should only show the date.
 * @param mixed                                                                              $time_only        If we should only show the time.
 *
 * @return string
 */
function se_event_format_dates( $event_dates, $timezone, $hide_end_time, $hide_start_time, $display_timezone, $date_only, $time_only ) {
	$dates_count = is_array( $event_dates ) ? count( $event_dates ) : 1;

	if ( ! empty( $event_timezone ) ) {
		$timezone = new DateTimeZone( $event_timezone );
	} else {
		$timezone = wp_timezone();
	}

	$timezone_date = new DateTime( '', $timezone );
	$timezone_abbr = $timezone_date->format( 'T' );

	// Begin output as a list if the count is more than 1.
	$dates_output = ( $dates_count > 1 ) ? '<ul>' : '';

	// Get the start and end times from the first date.
	// Assume all start and end times are the same until proven otherwise.
	$event_time_start = wp_date( get_option( 'time_format' ), $event_dates[0]['datetime_start'], $timezone );
	$event_time_end   = wp_date( get_option( 'time_format' ), $event_dates[0]['datetime_end'], $timezone );
	$same_times       = ( 1 < $dates_count ) ? true : false;

	// Loop over each available event date.
	foreach ( $event_dates as $date ) {

		// Check if start and end times are on the same day.
		$same_day = wp_date( 'Y-m-d', $date['datetime_start'], $timezone ) === wp_date( 'Y-m-d', $date['datetime_end'], $timezone );

		// Get start and end times.
		$time_start = ( $hide_start_time ) ? '' : wp_date( get_option( 'time_format' ), $date['datetime_start'], $timezone );
		$time_end   = ( $hide_end_time ) ? '' : wp_date( get_option( 'time_format' ), $date['datetime_end'], $timezone );

		$time_separator = ( 1 === (int) $hide_start_time ) ? '' : '&ndash;';

		// Invalidate same times if the start or end times don't match.
		if ( $same_times && ( $time_start !== $event_time_start || $time_end !== $event_time_end ) ) {
			$same_times = false;
		}

		// Ensure we're working with a boolean.
		$date['all_day'] = array_key_exists( 'all_day', $date ) ? filter_var( $date['all_day'], FILTER_VALIDATE_BOOLEAN ) : false;

		// Start the output string.
		$single_date_output = wp_date( get_option( 'date_format' ), $date['datetime_start'], $timezone );

		// Return early if we only want the date.
		if ( $date_only ) {
			$end_date         = wp_date( get_option( 'date_format' ), $date['datetime_end'], $timezone );
			$date_only_output = ( $same_day ) ? $single_date_output : $single_date_output . ' &ndash; ' . $end_date;
			$dates_output    .= ( $dates_count > 1 ) ? '<li>' . $date_only_output . '</li>' : $date_only_output;
			continue;
		}

		// Return early if we only want the time.
		if ( $time_only ) {
			$time          = sprintf( '%s %s %s', $time_start, $time_separator, $time_end );
			$dates_output .= ( $dates_count > 1 ) ? '<li>' . $time . '</li>' : $time;
			continue;
		}

		if ( ! $same_day ) {

			// If the event doesn't start and finish on the same day.
			$single_date_output .= sprintf(
				' %s &ndash; %s %s',
				$time_start,
				wp_date( get_option( 'date_format' ), $date['datetime_end'], $timezone ),
				$time_end
			);
		} elseif ( false === $date['all_day'] && $time_start !== $time_end ) {

			// Else if the event isn't all day, and the start and end times are different.
			$single_date_output .= sprintf( ' %s %s %s', $time_start, $time_separator, $time_end );
		}

		// Display timezone only after the first date.
		if ( $display_timezone && $date === $event_dates[0] ) {
			$single_date_output .= ' (' . $timezone_abbr . ')';
		}

		// Return output for this date.
		$dates_output .= ( $dates_count > 1 ) ? '<li>' . $single_date_output . '</li>' : $single_date_output;
	}

	// Overwrite output if all start and end times are the same and "Group event dates with matching times" otpion is selected.
	$display_grouped = filter_var( get_post_meta( get_the_ID(), 'se_event_display_grouped', true ), FILTER_VALIDATE_BOOLEAN );

	if ( $same_times && $display_grouped ) {
		$event_date_start = wp_date( get_option( 'date_format' ), $event_dates[0]['datetime_start'], $timezone );
		$event_date_end   = wp_date( get_option( 'date_format' ), $event_dates[ $dates_count - 1 ]['datetime_start'], $timezone );

		$dates_output = ( $dates_count > 1 ) ? '<ul><li>' : '';

		$event_time_end = ( $hide_end_time ) ? '' : ' &ndash; ' . $event_time_end;

		// Don't display time if it's an all day event.
		if ( $date['all_day'] ) {
			$dates_output .= sprintf( ' %s &ndash; %s', $event_date_start, $event_date_end );
		} else {
			$dates_output .= sprintf( ' %s &ndash; %s %s %s', $event_date_start, $event_date_end, $event_time_start, $event_time_end );
		}

		if ( $display_timezone ) {
			$dates_output .= ' (' . $timezone_abbr . ')';
		}

		$dates_output .= ( $dates_count > 1 ) ? '</li>' : '';
	}

	$dates_output .= ( $dates_count > 1 ) ? '</ul>' : '';

	return $dates_output;
}

/**
 * Get event title.
 *
 * @param integer $event_id Event id.
 *
 * @return mixed
 */
function se_event_get_title( $event_id ) {
	return get_the_title( $event_id );
}

/**
 * Get event location.
 *
 * @param integer $event_id Event id.
 *
 * @return mixed
 */
function se_event_get_location( $event_id ) {
	$event_location = get_post_meta( $event_id, 'se_event_location', true );

	if ( ! empty( $event_location ) ) {
		return $event_location;
	}

	return false;
}

/**
 * Get event venue.
 *
 * @param integer $event_id Event id.
 *
 * @return mixed
 */
function se_event_get_venue( $event_id ) {
	$event_venue = get_post_meta( $event_id, 'se_event_venue', true );

	if ( ! empty( $event_venue ) ) {
		return $event_venue;
	}

	return false;
}

/**
 * Whether or not an event is past its date.
 *
 * @param integer $event_id Event id.
 *
 * @return boolean
 */
function se_event_is_expired( $event_id ) {
	$event_end_date = get_post_meta( $event_id, 'se_event_date_end', true );

	if ( ! empty( $event_end_date ) && $event_end_date < wp_date( 'U' ) ) {
		return true;
	}

	return false;
}

/**
 * Gets the calendar event link.
 *
 * @param integer $event_id Event id.
 *
 * @return string
 */
function se_event_get_calendar_link( $event_id, $event_date_id = null ) {
	// Set the link.
	$external_link      = esc_url( get_post_meta( $event_id, 'se_event_external_link', true ) );
	$open_external_link = (bool) get_post_meta( $event_id, 'se_open_external_link', true );

	return ( $external_link && $open_external_link )
		? $external_link
		: get_the_permalink( $event_id ) . ( $event_date_id ? '#event-' . $event_date_id : '' );
}

/**
 * Checks if the next and previous links should be shown.
 *
 * @return boolean
 */
function se_event_show_next_previous(): bool {
	$options = (array) get_option( 'se_options', array() );

	// If is not set, return as false.
	if ( ! array_key_exists( 'show_next_prev_links', $options ) ) {
		return false;
	}

	return 'on' === $options['show_next_prev_links'];
}

/**
 * Gets the link to the calendar page if set.
 *
 * @return string|null
 */
function se_event_get_calendar_page_link(): ?string {
	$options = (array) get_option( 'se_options', array() );

	// If is not set, return as false.
	if ( ! array_key_exists( 'calendar_page', $options ) ) {
		return null;
	}

	return get_permalink( $options['calendar_page'] ) ?: null; //phpcs:ignore Universal.Operators.DisallowShortTernary.Found
}

/**
 * Checks if the event links are shown above or below the content.
 *
 * After is the default.
 *
 * @return boolean
 */
function se_event_show_links_above_content(): bool {
	$options = (array) get_option( 'se_options', array() );

	// If is not set, return as false.
	if ( ! array_key_exists( 'show_links_above_content', $options ) ) {
		return false;
	}

	return 'after' === $options['show_links_above_content'];
}

/**
 * Updates an event start and end meta dates.
 * This will ensure that the start and end dates will not be in the past.
 *
 * @param integer $event_id Event id.
 *
 * @return void
 */
function se_event_update_event_query_dates( $event_id ) {
	$now         = SE_Calendar::get_instance()->create_date_time( 'now' )->format( 'U' );
	$event_dates = get_post_meta( $event_id, 'se_event_dates', true );

	$start_date = null;
	$end_date   = null;

	foreach ( $event_dates as $key => $date ) {
		// If the end date has passed, continue.
		if ( $date['datetime_end'] < $now ) {
			continue;
		}

		// If we have no start date, or this date is later, set it.
		if ( null === $start_date || $date['datetime_start'] < $start_date ) {
			$start_date = $date['datetime_start'];
		}
		// If we have no end date, or this date is later, set it.
		if ( null === $end_date || $date['datetime_end'] > $end_date ) {
			$end_date = $date['datetime_end'];
		}
	}

	// If we have no start date, but we have dates, set to the earliest.
	if ( null === $start_date && ! empty( $event_dates ) ) {
		$all_start_dates = wp_list_pluck( $event_dates, 'datetime_start' );
		rsort( $all_start_dates );
		$start_date = $all_start_dates[0];
	}
	// If we have no end date, but we have dates, set to the latest.
	if ( null === $end_date && ! empty( $event_dates ) ) {
		// Get the latest end date.
		$all_end_dates = wp_list_pluck( $event_dates, 'datetime_end' );
		rsort( $all_end_dates );
		$end_date = $all_end_dates[0];
	}

	// If we have a start date, set it.
	if ( null !== $start_date ) {
		update_post_meta( $event_id, 'se_event_date_start', esc_attr( $start_date ) );
	}

	// If we have an end date, set it.
	if ( null !== $end_date ) {
		update_post_meta( $event_id, 'se_event_date_end', esc_attr( $end_date ) );
	}
}

	/**
	 * Create event date.
	 *
	 * @param integer                                                                                                                          $event_id    Event id.
	 * @param array{ datetime_start: integer, datetime_end: integer, all_day: boolean, hide_from_calendar: boolean, hide_from_feed: boolean, } $event_dates Event dates.
	 *
	 * @return \WP_Post|null
	 */
function se_event_create_event_date( $event_id, $event_dates ) {
	$default_args = array(
		'datetime_start'     => 0,
		'datetime_end'       => 0,
		'all_day'            => false,
		'hide_from_calendar' => false,
		'hide_from_feed'     => false,
	);
	// Merge the default args with the provided event dates.
	$event_dates = wp_parse_args( $event_dates, $default_args );
	// Validate the event dates, start date should be a timestamp and end date should be a timestamp.
	if ( ! is_numeric( $event_dates['datetime_start'] ) ) {
		return null;
	}

	// Create the event date post.
	$event_date_post = array(
		'post_title'   => sprintf(
			// translators: %s is the event title.
			__( 'Event Date for %s', 'simple-events' ),
			get_the_title( $event_id )
		),
		'post_content' => '',
		'post_status'  => 'publish',
		'post_type'    => SE_Event_Post_Type::$event_date_post_type,
		'post_parent'  => $event_id,
	);

	// Insert the post into the database.
	$event_date_id = wp_insert_post( $event_date_post );

	if ( is_wp_error( $event_date_id ) || ! $event_date_id ) {
		return null; // Failed to create the event date.
	}

	// Update the post meta for the event date.
	update_post_meta( $event_date_id, 'se_event_date_start', esc_attr( $event_dates['datetime_start'] ) );
	update_post_meta( $event_date_id, 'se_event_date_end', esc_attr( $event_dates['datetime_end'] ) );
	update_post_meta( $event_date_id, 'se_event_all_day', boolval( $event_dates['all_day'] ) );
	update_post_meta( $event_date_id, 'se_event_hide_from_calendar', boolval( $event_dates['hide_from_calendar'] ) );
	update_post_meta( $event_date_id, 'se_event_hide_from_feed', boolval( $event_dates['hide_from_feed'] ) );

	return get_post( $event_date_id );
}

/**
 * Get the dates for an event.
 *
 * @param integer $event_id Event id.
 *
 * @return array{
 *  datetime_start: integer,
 *  datetime_end: integer,
 * all_day: boolean,
 * hide_from_calendar: boolean,
 * hide_from_feed: boolean,
 * }[]
 *
 * @throws \Exception If the event ID is invalid or if the event dates cannot be retrieved.
 *
 * @since 2.0.0
 */
function se_event_get_event_dates( $event_id ): array {
	if ( ! is_numeric( $event_id ) || $event_id <= 0 ) {
		throw new \Exception( __( 'Invalid event ID provided.', 'simple-events' ) );
	}

	$event_dates = get_posts(
		array(
			'post_type'      => SE_Event_Post_Type::$event_date_post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post_parent'    => $event_id,
			'fields'         => 'ids',
		)
	);

	// Map with meta.
	$dates = array_map(
		function ( $date_id ) {
			$start_date         = get_post_meta( $date_id, 'se_event_date_start', true );
			$end_date           = get_post_meta( $date_id, 'se_event_date_end', true );
			$all_day            = get_post_meta( $date_id, 'se_event_all_day', true );
			$hide_from_calendar = get_post_meta( $date_id, 'se_event_hide_from_calendar', true );
			$hide_from_feed     = get_post_meta( $date_id, 'se_event_hide_from_feed', true );

			return array(
				'id'                 => $date_id,
				'start_date'         => esc_attr( $start_date ),
				'end_date'           => esc_attr( $end_date ),
				'all_day'            => boolval( $all_day ),
				'hide_from_calendar' => '' === $hide_from_calendar ? false : boolval( $hide_from_calendar ),
				'hide_from_feed'     => '' === $hide_from_feed ? false : boolval( $hide_from_feed ),
			);
		},
		$event_dates
	);

	return apply_filters( 'se_event_get_event_dates', $dates, $event_id );
}

/**
 * Create a DateTime object from a timestamp, with an optional timezone.
 *
 * @param mixed       $timestamp The Unix timestamp to create the DateTime object from.
 * @param string|null $timezone  The timezone to be used, or null to use the site timezone.
 *
 * @return DateTime The created DateTime object.
 */
function se_create_date_time_from_timestamp( $timestamp, $timezone = null ): DateTime {
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
 * Fires after WordPress has finished loading but before any headers are sent.
 */
// add_action(
// 	'init',
// 	function (): void {
// 		$id    = 7379;
// 		$dates = get_post_meta( $id, 'se_event_dates', true );
// 		foreach ( $dates as $date ) {
// 			$r = se_event_create_event_date( $id, $date );
// 			dump(
// 				array(
// 					'created for id' => $id,
// 					'date'           => $date,
// 					'r'              => $r,
// 				)
// 			);
// 		}
// 		dd( array( $dates, 'se_event_dates', se_event_get_event_dates( $id ) ) );
// 	}
// );
