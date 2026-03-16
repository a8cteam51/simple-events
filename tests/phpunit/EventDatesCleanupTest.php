<?php
/**
 * Tests for cleaning up child event-date posts when a parent event is deleted.
 *
 * @package Simple_Events
 */

class EventDatesCleanupTest extends WP_UnitTestCase {

	/**
	 * Permanently deleting a parent event should also delete its child event-date posts.
	 */
	public function test_deleting_event_removes_child_event_dates() {
		// Create a parent event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => 'Multi-day Event',
			)
		);

		// Create two child event-date posts.
		$tomorrow  = strtotime( '+1 day' );
		$day_after = strtotime( '+2 days' );

		$date_1 = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $tomorrow,
				'end_date'   => $tomorrow + 7200,
				'all_day'    => false,
			)
		);
		$date_2 = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $day_after,
				'end_date'   => $day_after + 7200,
				'all_day'    => false,
			)
		);

		$this->assertNotNull( $date_1 );
		$this->assertNotNull( $date_2 );

		// Delete the parent event.
		wp_delete_post( $event_id, true );

		// Child event-date posts should no longer exist.
		$this->assertNull( get_post( $date_1->ID ), 'Child event-date post was not deleted when parent event was deleted.' );
		$this->assertNull( get_post( $date_2->ID ), 'Child event-date post was not deleted when parent event was deleted.' );
	}
}
