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
