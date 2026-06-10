<?php
/**
 * Seeder for the calendar start-of-week front-end e2e spec.
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-calendar.php <0-6>
 *
 * Sets the WordPress `start_of_week` option to the value passed as the first
 * positional argument (default 1 = Monday), then seeds a single published
 * se-event with a child se-event-date dated *today* so the current month has
 * an event and the calendar renders that month's grid (rather than the
 * "No Events Scheduled" state or jumping to another month). Creates a page
 * holding the real calendar block and echoes its ID for the spec to navigate
 * to. Idempotent — wipes prior run first.
 *
 * @package Simple_Events
 */

$prefix = 'E2ECAL';

// First positional arg from `wp eval-file` is the start_of_week to set.
$start_of_week = isset( $args[0] ) && '' !== $args[0] ? (int) $args[0] : 1;
$start_of_week = max( 0, min( 6, $start_of_week ) );

update_option( 'start_of_week', $start_of_week );

// Full clean slate: delete EVERY se-event and se-event-date (any status) so the
// calendar only ever sees this seeder's data, plus a prior run's page.
$wipe = get_posts(
	array(
		'post_type'   => array( 'se-event', 'se-event-date' ),
		'post_status' => 'any',
		'numberposts' => -1,
	)
);
foreach ( $wipe as $p ) {
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

$event_id = wp_insert_post(
	array(
		'post_type'    => 'se-event',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' EVENT',
		'post_content' => $event_content,
	)
);

// Date today (noon, to stay safely inside the current month) so the current
// month has an event and the grid renders.
$ts = strtotime( 'today noon' );
se_event_create_event_date(
	$event_id,
	array(
		'start_date' => $ts,
		'end_date'   => $ts + 7200,
		'all_day'    => false,
	)
);

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' PAGE',
		'post_content' => '<!-- wp:simple-events/calendar /-->',
	)
);

echo (int) $page_id;
