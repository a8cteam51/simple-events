<?php
/**
 * Seeder for the archive block-probe e2e spec (GH-87).
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-archive-blocks.php
 *
 * Every date is absolute, so nothing drifts as the suite ages:
 *   - two past events   — March 2025
 *   - two future events — March 2035
 *   - one event carrying three dates spanning both
 *
 * That spread means past/future filtering, unique-parent reduction and
 * multi-date grouping all have distinct values to get wrong, rather than
 * coincidentally matching.
 *
 * Also creates a page carrying the same blocks the probe renders on the
 * archive, as the comparison baseline. Echoes the page ID. Idempotent.
 *
 * @package Simple_Events
 */

$prefix = 'E2EBLOCK';

// Clean slate so only this seeder's data is in play.
foreach ( get_posts(
	array(
		'post_type'   => array( 'se-event', 'se-event-date' ),
		'post_status' => 'any',
		'numberposts' => -1,
	)
) as $p ) {
	wp_delete_post( $p->ID, true );
}
foreach ( get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'any',
		'numberposts' => -1,
		's'           => $prefix,
	)
) as $p ) {
	wp_delete_post( $p->ID, true );
}

// Event-info block in content so the save_post cleanup hook keeps child dates.
$event_content = '<!-- wp:simple-events/event-info /-->';

/**
 * Create an event with the given absolute date strings.
 *
 * @param string        $title The event title.
 * @param array<string> $dates Absolute datetime strings.
 *
 * @return integer
 */
$make_event = function ( $title, array $dates ) {
	$event_id = wp_insert_post(
		array(
			'post_type'   => 'se-event',
			'post_status' => 'publish',
			'post_title'  => $title,
		)
	);

	$legacy = array();

	foreach ( $dates as $date ) {
		$ts = strtotime( $date );
		se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $ts,
				'end_date'   => $ts + 7200,
				'all_day'    => false,
			)
		);

		$legacy[] = array(
			'datetime_start' => (string) $ts,
			'datetime_end'   => (string) ( $ts + 7200 ),
			'all_day'        => false,
		);
	}

	// se_event_create_event_date() writes meta on the date post only. A real
	// event also carries se_event_dates, and se_event_date_start/_end derived
	// from it — that derivation is not "earliest date", it is the earliest date
	// that has not already finished (event-functions.php:646-660). Blocks that
	// query se-event posts by date, such as the countdown, rely on it.
	update_post_meta( $event_id, 'se_event_dates', $legacy );
	se_event_update_event_query_dates( $event_id );

	// A real event carries its dates twice: as child posts and in the Event
	// Info block's attributes.
	wp_update_post(
		array(
			'ID'           => $event_id,
			'post_content' => '<!-- wp:simple-events/event-info '
				. wp_json_encode( array( 'eventDates' => se_event_get_event_dates( $event_id ) ) )
				. ' /-->',
		)
	);

	return $event_id;
};

// Past — March 2025.
$make_event( $prefix . ' PAST A', array( '2025-03-10 10:00:00' ) );
$make_event( $prefix . ' PAST B', array( '2025-03-18 14:00:00' ) );

// Future — March 2035.
$make_event( $prefix . ' FUTURE A', array( '2035-03-12 10:00:00' ) );
$make_event( $prefix . ' FUTURE B', array( '2035-03-20 14:00:00' ) );

// One event spanning both, with three dates.
$make_event(
	$prefix . ' MULTI',
	array(
		'2025-03-25 09:00:00',
		'2035-03-05 09:00:00',
		'2035-03-28 09:00:00',
	)
);

// One page per block. Several blocks on one page share filter state, so the
// second block would be measured under conditions the first one left behind.
$pages = array(
	'calendar'        => '<!-- wp:simple-events/calendar /-->',
	'countdown'       => '<!-- wp:simple-events/countdown /-->',
	'upcoming-events' => '<!-- wp:simple-events/upcoming-events /-->',
);

$ids = array();

foreach ( $pages as $slug => $markup ) {
	$ids[ $slug ] = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => 'e2eblock-' . $slug,
			'post_title'   => $prefix . ' ' . strtoupper( $slug ),
			'post_content' => $markup,
		)
	);
}

echo wp_json_encode( $ids );
