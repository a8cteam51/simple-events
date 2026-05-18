<?php
/**
 * Seeder for the editor-vs-frontend parity spec.
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-parity.php
 *
 * Full clean slate, then 14 se-event posts with a wide date spread (7 past,
 * 7 future) and perPage 6, so pagination exposes any editor-vs-front
 * ordering/selection divergence on page 1 (a small single-page seed hides
 * it — same set regardless of internal order). Titles are zero-padded
 * (PARITY 01..14) so the regex and ordering are stable.
 *
 * Front-end (event-date asc) page 1 = the 6 earliest by event date.
 * The editor preview must show the SAME page-1 set/order. Echoes the page ID.
 *
 * @package Simple_Events
 */

$prefix = 'PARITY';

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

$event_content = '<!-- wp:simple-events/event-info /-->';

// 7 past + 7 future, wide spread, deliberately created in an order that
// does NOT match event-date order (so created-date vs event-date ordering
// diverge). Index = creation order; value = day offset from now.
$day_offsets = array( -60, 50, -10, 40, -45, 10, -30, 60, -5, 30, -50, 20, -20, 5 );
foreach ( $day_offsets as $i => $days ) {
	$event_id = wp_insert_post(
		array(
			'post_type'    => 'se-event',
			'post_status'  => 'publish',
			'post_title'   => sprintf( '%s %02d', $prefix, $i + 1 ),
			'post_content' => $event_content,
		)
	);

	$ts = strtotime( "{$days} days" );
	se_event_create_event_date(
		$event_id,
		array(
			'start_date' => $ts,
			'end_date'   => $ts + 7200,
			'all_day'    => false,
		)
	);
}

// feedType default, perPage 20, order asc by date.
$markup = <<<'HTML'
<!-- wp:query {"queryId":11,"query":{"perPage":6,"pages":0,"offset":0,"postType":"se-event","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"inheritTaxQuery":true,"feedType":"default"},"namespace":"se-events/query-loop-events"} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:post-title /-->

<!-- wp:simple-events/loop-event-info /-->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No events</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
HTML;

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $prefix . ' PAGE',
		'post_content' => $markup,
	)
);

echo (int) $page_id;
