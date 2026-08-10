<?php
/**
 * Seeder for the admin-ajax authorisation e2e spec (S-02).
 *
 * Run via: wp eval-file tests/e2e/fixtures/seed-orphan.php
 *
 * Creates a single orphaned se-event-date — a date post with no parent event —
 * which is exactly what SE_Settings::clear_orphaned_events() targets. Echoes its
 * ID so the spec can assert whether it survived a given request.
 *
 * Idempotent: removes any orphan left by a prior run first.
 *
 * @package Simple_Events
 */

$prefix = 'E2EORPHAN';

// Clear any orphan from a previous run.
foreach ( get_posts(
	array(
		'post_type'   => 'se-event-date',
		'post_status' => 'any',
		'numberposts' => -1,
		's'           => $prefix,
	)
) as $p ) {
	wp_delete_post( $p->ID, true );
}

$orphan_id = wp_insert_post(
	array(
		'post_type'   => 'se-event-date',
		'post_status' => 'publish',
		'post_title'  => $prefix . ' DATE',
		'post_parent' => 0,
	)
);

echo (int) $orphan_id;
