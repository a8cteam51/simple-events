<?php
/**
 * Tests for cleaning up child event-date posts when a parent event is deleted or trashed.
 *
 * @package Simple_Events
 */
class EventDatesCleanupTest extends WP_UnitTestCase {

	/**
	 * Permanently deleting a parent event should also delete its child event-date posts.
	 *
	 * @return void
	 */
	public function test_deleting_event_removes_child_event_dates() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

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

		wp_delete_post( $event_id, true );

		$this->assertNull( get_post( $date_1->ID ), 'Child event-date post was not deleted when parent event was deleted.' );
		$this->assertNull( get_post( $date_2->ID ), 'Child event-date post was not deleted when parent event was deleted.' );
	}

	/**
	 * Trashing a parent event should also trash its child event-date posts.
	 *
	 * @return void
	 */
	public function test_trashing_event_trashes_child_event_dates() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

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

		wp_trash_post( $event_id );

		$this->assertSame( 'trash', get_post_status( $date_1->ID ), 'Child event-date post was not trashed when parent event was trashed.' );
		$this->assertSame( 'trash', get_post_status( $date_2->ID ), 'Child event-date post was not trashed when parent event was trashed.' );
	}

	/**
	 * Trashing then permanently deleting a parent event should also delete its child event-date posts.
	 *
	 * This mirrors the real WordPress admin flow: trash first, then "Delete Permanently".
	 * The children are already in trash status when the parent is force-deleted.
	 *
	 * @return void
	 */
	public function test_trashing_then_deleting_event_removes_child_event_dates() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

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

		// Step 1: Trash the parent (children move to trash).
		wp_trash_post( $event_id );
		$this->assertSame( 'trash', get_post_status( $date_1->ID ) );
		$this->assertSame( 'trash', get_post_status( $date_2->ID ) );

		// Step 2: Permanently delete the trashed parent.
		wp_delete_post( $event_id, true );

		$this->assertNull( get_post( $date_1->ID ), 'Child event-date post was not deleted when trashed parent was permanently deleted.' );
		$this->assertNull( get_post( $date_2->ID ), 'Child event-date post was not deleted when trashed parent was permanently deleted.' );
	}

	/**
	 * Deleting one event should not affect another event's child event-date posts.
	 *
	 * @return void
	 */
	public function test_deleting_event_does_not_affect_other_events_dates() {
		$event_a = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);
		$event_b = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$tomorrow  = strtotime( '+1 day' );
		$day_after = strtotime( '+2 days' );

		$date_a = se_event_create_event_date(
			$event_a,
			array(
				'start_date' => $tomorrow,
				'end_date'   => $tomorrow + 7200,
				'all_day'    => false,
			)
		);
		$date_b = se_event_create_event_date(
			$event_b,
			array(
				'start_date' => $day_after,
				'end_date'   => $day_after + 7200,
				'all_day'    => false,
			)
		);

		// Trash then permanently delete event A.
		wp_trash_post( $event_a );
		wp_delete_post( $event_a, true );

		// Event A's date should be gone.
		$this->assertNull( get_post( $date_a->ID ), 'Deleted event\'s date should be removed.' );

		// Event B and its date should be untouched.
		$this->assertSame( 'publish', get_post_status( $event_b ), 'Unrelated event should still exist.' );
		$this->assertSame( 'publish', get_post_status( $date_b->ID ), 'Unrelated event\'s date should still be published.' );
	}

	/**
	 * Untrashing a parent event should restore its child event-date posts to publish.
	 *
	 * @return void
	 */
	public function test_untrashing_event_restores_child_event_dates() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

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

		wp_trash_post( $event_id );
		wp_untrash_post( $event_id );

		$this->assertSame( 'publish', get_post_status( $date_1->ID ), 'Child event-date post was not restored when parent event was untrashed.' );
		$this->assertSame( 'publish', get_post_status( $date_2->ID ), 'Child event-date post was not restored when parent event was untrashed.' );
	}
}
