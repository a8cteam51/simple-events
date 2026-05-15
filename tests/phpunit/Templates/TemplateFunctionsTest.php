<?php
/**
 * Tests for template functions handling orphaned event-date posts.
 *
 * @package Simple_Events
 */
class TemplateFunctionsTest extends WP_UnitTestCase {

	/**
	 * Reset the static cache between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		se_get_date_ids_for_non_published_events( true );
	}

	/**
	 * The se_event_get_next_event() function should not return an orphaned event-date
	 * whose parent event has been deleted.
	 *
	 * @return void
	 */
	public function test_get_next_event_skips_orphaned_dates() {
		// Create event A with a date.
		$event_a = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$now    = time();
		$date_a = se_event_create_event_date(
			$event_a,
			array(
				'start_date' => $now,
				'end_date'   => $now + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_a->ID, 'se_event_hide_from_feed', 0 );

		// Create event B (will be deleted) with a later date.
		$event_b = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$later  = $now + 86400;
		$date_b = se_event_create_event_date(
			$event_b,
			array(
				'start_date' => $later,
				'end_date'   => $later + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_b->ID, 'se_event_hide_from_feed', 0 );

		// Create event C with an even later date (should be the next event).
		$event_c = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$even_later = $now + 172800;
		$date_c     = se_event_create_event_date(
			$event_c,
			array(
				'start_date' => $even_later,
				'end_date'   => $even_later + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_c->ID, 'se_event_hide_from_feed', 0 );

		// Delete event B's parent, orphaning date_b.
		// Remove the before_delete_post hook temporarily so the child survives.
		remove_action( 'before_delete_post', array( 'SE_Event_Post_Type', 'delete_child_event_dates' ) );
		wp_delete_post( $event_b, true );
		add_action( 'before_delete_post', array( 'SE_Event_Post_Type', 'delete_child_event_dates' ) );

		// Verify date_b is orphaned.
		$this->assertNull( get_post( $event_b ) );
		$this->assertNotNull( get_post( $date_b->ID ) );

		// Reset the cache so orphaned dates are detected.
		se_get_date_ids_for_non_published_events( true );

		// Get the next event after event A — should skip orphaned date_b and return date_c.
		$next = se_event_get_next_event( $event_a, $date_a->ID );

		$this->assertNotNull( $next, 'Expected a next event to be returned.' );
		$this->assertSame( $date_c->ID, $next->ID, 'Next event should be date_c, not the orphaned date_b.' );
	}

	/**
	 * The se_event_get_previous_event() function should not return an orphaned event-date
	 * whose parent event has been deleted.
	 *
	 * @return void
	 */
	public function test_get_previous_event_skips_orphaned_dates() {
		// Create event A with an early date (should be the previous event).
		$event_a = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$now    = time();
		$early  = $now - 172800;
		$date_a = se_event_create_event_date(
			$event_a,
			array(
				'start_date' => $early,
				'end_date'   => $early + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_a->ID, 'se_event_hide_from_feed', 0 );

		// Create event B (will be deleted) with a middle date — the immediate previous.
		$event_b = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$middle = $now - 86400;
		$date_b = se_event_create_event_date(
			$event_b,
			array(
				'start_date' => $middle,
				'end_date'   => $middle + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_b->ID, 'se_event_hide_from_feed', 0 );

		// Create event C with the latest date (the "current" event).
		$event_c = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$date_c = se_event_create_event_date(
			$event_c,
			array(
				'start_date' => $now,
				'end_date'   => $now + 3600,
				'all_day'    => false,
			)
		);
		update_post_meta( $date_c->ID, 'se_event_hide_from_feed', 0 );

		// Delete event B's parent, orphaning date_b (the immediate previous).
		remove_action( 'before_delete_post', array( 'SE_Event_Post_Type', 'delete_child_event_dates' ) );
		wp_delete_post( $event_b, true );
		add_action( 'before_delete_post', array( 'SE_Event_Post_Type', 'delete_child_event_dates' ) );

		// Verify date_b is orphaned.
		$this->assertNull( get_post( $event_b ) );
		$this->assertNotNull( get_post( $date_b->ID ) );

		// Reset the cache so orphaned dates are detected.
		se_get_date_ids_for_non_published_events( true );

		// Get the previous event before event C — should skip orphaned date_b and return date_a.
		$previous = se_event_get_previous_event( $event_c, $date_c->ID );

		$this->assertNotNull( $previous, 'Expected a previous event to be returned.' );
		$this->assertSame( $date_a->ID, $previous->ID, 'Previous event should be date_a, not the orphaned date_b.' );
	}
}
