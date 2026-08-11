<?php
/**
 * PR review finding: the countdown's archive guard misses an empty archive.
 *
 * SE_Blocks::countdown_render() strips the event query filters at
 * class-se-blocks.php:690, but only when get_post_type() reports an event.
 * That reads the global $post, which nothing has set when the archive returned
 * no rows, so the guard is skipped and the filters stay attached.
 *
 * With treat_each_date_as_own_event enabled, pre_get_posts() attaches
 * modify_event_posts() (class-se-event-post-type.php:652) and the option alone
 * satisfies $should_modify (class-se-event-query-utils.php:268). The countdown
 * queries se-event posts, whose post_parent is 0, so get_post( 0 ) returns null
 * and every row is dropped.
 *
 * @package Simple_Events
 */
class CountdownArchiveGuardTest extends WP_UnitTestCase {

	/**
	 * Turn on treat_each_date_as_own_event.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'se_options', array( 'treat_each_date_as_own_event' => 'on' ) );

		$this->assertTrue(
			se_event_treat_each_date_as_own_event(),
			'Fixture is wrong: the option did not take.'
		);
	}

	/**
	 * Create a published event with a single future date.
	 *
	 * @param string $title Event title.
	 *
	 * @return integer
	 */
	private function make_future_event( $title ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		$start = strtotime( '+30 days' );

		se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $start + 7200,
				'all_day'    => false,
			)
		);

		update_post_meta(
			$event_id,
			'se_event_dates',
			array(
				array(
					'datetime_start' => (string) $start,
					'datetime_end'   => (string) ( $start + 7200 ),
					'all_day'        => false,
				),
			)
		);

		se_event_update_event_query_dates( $event_id );

		return $event_id;
	}

	/**
	 * A countdown rendered on an archive that returned nothing must still count
	 * to the next event.
	 *
	 * @return void
	 */
	public function test_countdown_still_renders_on_an_empty_archive() {
		$this->make_future_event( 'Future Event' );

		// A category with no events, so the archive renders zero rows while an
		// event still exists for the countdown to find.
		$empty_term = $this->factory->term->create(
			array(
				'taxonomy' => 'se-event-category',
				'slug'     => 'empty-countdown-cat',
			)
		);

		$this->go_to( get_term_link( $empty_term, 'se-event-category' ) );

		// Preconditions: an archive, with no results, and no global $post.
		$this->assertTrue( is_archive(), 'Expected an archive request.' );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts, 'Expected the archive to return nothing.' );
		$this->assertFalse( get_post_type(), 'Expected no global $post, which is what the guard reads.' );

		$output = SE_Blocks::countdown_render( array() );

		$this->assertStringContainsString(
			'id="event-timer"',
			$output,
			'The countdown rendered nothing on an archive that returned no rows.'
		);
	}
}
