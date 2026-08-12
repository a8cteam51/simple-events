<?php
/**
 * Seeder for the "Revert Changes" editor e2e spec.
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-revert.php
 *
 * Creates one published se-event with a single child se-event-date. The date has
 * to exist *before* the block mounts — the aliasing bug under test is that
 * originalDates and currentDates share the same objects, which only matters for
 * dates present at mount. A date added in the editor afterwards is legitimately
 * discarded by revert. Echoes the event ID. Idempotent — wipes prior run first.
 *
 * @package Simple_Events
 */

$prefix = 'E2EREVERT';

// Clean slate, matching the other seeders: every se-event and se-event-date.
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

// Event-info block in content so the save_post cleanup hook keeps child dates.
$event_id = wp_insert_post(
	array(
		'post_type'    => 'se-event',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' EVENT',
		'post_content' => '<!-- wp:simple-events/event-info /-->',
	)
);

// Fixed timestamp rather than a relative one, so the expected values do not
// drift between the seed and the assertions.
$start = 1800000000;
se_event_create_event_date(
	$event_id,
	array(
		'start_date' => $start,
		'end_date'   => $start + 7200,
		'all_day'    => false,
	)
);

echo (int) $event_id;
