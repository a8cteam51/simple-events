<?php
/**
 * Tests for SE_Event_Query_Utils::modify_event_posts.
 *
 * @package Simple_Events
 */
class EventQueryUtilsTest extends WP_UnitTestCase {

	/**
	 * Orphaned event-date posts (parent deleted outside of WordPress hooks,
	 * e.g. direct DB deletion) should not cause a fatal when modify_event_posts
	 * encounters them.
	 *
	 * This is a defensive test: even though deleting via wp_delete_post() now
	 * cascades to children, orphans can still arise from direct DB operations
	 * or plugin conflicts. The null-check in modify_event_posts() must handle
	 * this gracefully.
	 *
	 * @see class-se-event-query-utils.php:modify_event_posts()
	 *
	 * @return void
	 */
	public function test_modify_event_posts_does_not_fatal_with_orphaned_date_posts() {
		// 1. Create orphaned event-date posts whose post_parent does not exist.
		$fake_parent_id = 999999;

		$tomorrow  = strtotime( '+1 day' );
		$day_after = strtotime( '+2 days' );

		$orphan_1 = $this->factory->post->create(
			array(
				'post_type'   => 'se-event-date',
				'post_status' => 'publish',
				'post_parent' => $fake_parent_id,
			)
		);
		update_post_meta( $orphan_1, 'se_event_date_start', $tomorrow );
		update_post_meta( $orphan_1, 'se_event_date_end', $tomorrow + 7200 );

		$orphan_2 = $this->factory->post->create(
			array(
				'post_type'   => 'se-event-date',
				'post_status' => 'publish',
				'post_parent' => $fake_parent_id,
			)
		);
		update_post_meta( $orphan_2, 'se_event_date_start', $day_after );
		update_post_meta( $orphan_2, 'se_event_date_end', $day_after + 7200 );

		// Confirm the parent does not exist.
		$this->assertNull( get_post( $fake_parent_id ) );

		// 2. Run a query that triggers modify_event_posts.
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

		// 3. If we get here without a fatal, the null check works.
		//    Orphaned posts should be filtered out of results.
		foreach ( $query->posts as $post ) {
			$this->assertNotNull(
				get_post( $post->post_parent ),
				'Query results should not contain posts with a deleted parent.'
			);
		}
	}
}
