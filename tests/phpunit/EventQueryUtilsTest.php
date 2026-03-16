<?php
/**
 * Tests for SE_Event_Query_Utils::modify_event_posts.
 *
 * @package Simple_Events
 */

class EventQueryUtilsTest extends WP_UnitTestCase {

	/**
	 * Deleting a parent event while orphaned date posts remain should not
	 * cause a fatal error when the next event query runs.
	 *
	 * Bug: When an event with multiple dates is trashed/deleted, the child
	 * se-event-date posts are left behind. A subsequent WP_Query for events
	 * (e.g. the upcoming-events block) passes these orphaned date posts
	 * through SE_Event_Query_Utils::modify_event_posts(), which calls
	 * get_post( $post->post_parent ). Because the parent no longer exists,
	 * get_post() returns null, and assigning ->post_date on null fatals.
	 *
	 * @see class-se-event-query-utils.php:modify_event_posts()
	 */
	public function test_modify_event_posts_does_not_fatal_when_parent_is_deleted() {
		// 1. Create a parent event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => 'Multi-day Event',
			)
		);

		// 2. Create two child event-date posts.
		$tomorrow     = strtotime( '+1 day' );
		$day_after    = strtotime( '+2 days' );
		$event_date_1 = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $tomorrow,
				'end_date'   => $tomorrow + 7200,
				'all_day'    => false,
			)
		);
		$event_date_2 = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $day_after,
				'end_date'   => $day_after + 7200,
				'all_day'    => false,
			)
		);

		$this->assertNotNull( $event_date_1 );
		$this->assertNotNull( $event_date_2 );

		// 3. Delete the parent event, leaving orphaned date posts.
		wp_delete_post( $event_id, true );
		$this->assertNull( get_post( $event_id ) );

		// Verify orphaned date posts still exist.
		$this->assertInstanceOf( WP_Post::class, get_post( $event_date_1->ID ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $event_date_2->ID ) );

		// 4. Run a query that triggers modify_event_posts — this is the
		//    code path the upcoming-events block uses.
		add_filter(
			'the_posts',
			array( 'SE_Event_Query_Utils', 'modify_event_posts' ),
			10,
			2
		);

		$query = new WP_Query(
			array(
				'post_type'      => 'se-event-date',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'unique_parents' => true,
			)
		);

		remove_filter(
			'the_posts',
			array( 'SE_Event_Query_Utils', 'modify_event_posts' ),
			10
		);

		// 5. If we get here without a fatal, the bug is fixed.
		//    The orphaned posts should be filtered out of the results.
		foreach ( $query->posts as $post ) {
			$this->assertNotNull(
				get_post( $post->post_parent ),
				'Query results should not contain posts with a deleted parent.'
			);
		}
	}
}
