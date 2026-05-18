<?php
/**
 * Seeder for the loop-event-info front-end e2e spec.
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-loop.php
 *
 * Creates 3 published se-event posts (each with one child se-event-date so
 * loop-event-info has a date to render), then a page whose content is the
 * real query-loop-events markup with offset:1 and a loop-event-info block
 * configured with tagName=h2 and a Y/m/d dateFormat override. Echoes the
 * page ID so the spec can navigate to it. Idempotent — wipes prior run first.
 *
 * @package Simple_Events
 */

$prefix = 'E2ELOOP';

// Full clean slate: delete EVERY se-event and se-event-date (any status) so
// the query loop only ever sees this seeder's data. Also drop a prior run's
// page.
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

foreach ( array( 10, 20, 30 ) as $i => $days ) {
	$event_id = wp_insert_post(
		array(
			'post_type'    => 'se-event',
			'post_status'  => 'publish',
			'post_title'   => sprintf( '%s %s', $prefix, chr( 65 + $i ) ),
			'post_content' => $event_content,
		)
	);

	$ts = strtotime( "+{$days} days" );
	se_event_create_event_date(
		$event_id,
		array(
			'start_date' => $ts,
			'end_date'   => $ts + 7200,
			'all_day'    => false,
		)
	);
}

// Real query-loop-events markup. offset:1 (skip the first of 3 → expect 2
// rendered), loop-event-info set to date / h2 / Y-m-d override.
$markup = <<<'HTML'
<!-- wp:query {"queryId":3,"query":{"perPage":6,"pages":0,"offset":1,"postType":"se-event","order":"asc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"inheritTaxQuery":true,"feedType":"default"},"namespace":"se-events/query-loop-events"} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:post-title /-->

<!-- wp:simple-events/loop-event-info {"metaName":"date","tagName":"h2","dateFormat":"Y/m/d"} /-->
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
