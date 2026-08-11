<?php
/**
 * Seeder for the calendar event modal accessibility e2e spec.
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-calendar-modal.php
 *
 * Seeds a single published se-event dated today, with the per-event modal meta
 * set, and a page holding a calendar block configured to show modals. Echoes
 * the page ID for the spec to navigate to. Idempotent — wipes prior run first.
 *
 * showModalWhenNoThumbnails is on so the modal renders without needing a
 * featured image in the test environment.
 *
 * @package Simple_Events
 */

$prefix = 'E2EMODAL';

// Clean slate, so the calendar only ever sees this seeder's data.
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
$event_id = wp_insert_post(
	array(
		'post_type'    => 'se-event',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' EVENT',
		'post_content' => '<!-- wp:simple-events/event-info /-->',
		'post_excerpt' => 'Modal excerpt for the accessibility spec.',
	)
);

// Per-event opt-ins the template checks before rendering the modal.
update_post_meta( $event_id, 'se_event_modal_access', 1 );
update_post_meta( $event_id, 'se_show_modal_title', 1 );
update_post_meta( $event_id, 'se_show_modal_excerpt', 1 );

// Today at noon, so the current month has an event and the grid renders.
$ts = strtotime( 'today noon' );
se_event_create_event_date(
	$event_id,
	array(
		'start_date' => $ts,
		'end_date'   => $ts + 7200,
		'all_day'    => false,
	)
);

$attributes = wp_json_encode(
	array(
		'eventModalAccess'          => true,
		'showModalTitle'            => true,
		'showModalExcerpt'          => true,
		'showModalWhenNoThumbnails' => true,
	)
);

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' PAGE',
		'post_content' => '<!-- wp:simple-events/calendar ' . $attributes . ' /-->',
	)
);

echo (int) $page_id;
