<?php
/**
 * calendar_render()'s archive guard has the same shape as the countdown's did.
 *
 * class-se-blocks.php:783 tests get_post_type(), which reads the global $post.
 * An archive that returned no rows never sets it, so the guard is skipped and
 * the event query filters stay attached.
 *
 * @package Simple_Events
 */
class CalendarArchiveGuardTest extends WP_UnitTestCase {

	/**
	 * Turn on treat_each_date_as_own_event.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'se_options', array( 'treat_each_date_as_own_event' => 'on' ) );

		$this->assertTrue( se_event_treat_each_date_as_own_event(), 'Fixture is wrong: the option did not take.' );
	}

	/**
	 * The calendar block's own attribute defaults, so the fixture matches what
	 * the editor sends rather than a hand-picked subset.
	 *
	 * @return array
	 */
	private function calendar_default_attributes() {
		$block = json_decode(
			file_get_contents( dirname( __DIR__, 3 ) . '/src/blocks/calendar/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			true
		);

		$defaults = array();

		foreach ( $block['attributes'] as $key => $spec ) {
			if ( array_key_exists( 'default', $spec ) ) {
				$defaults[ $key ] = $spec['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Create a published event dated mid-way through the current month.
	 *
	 * @param string $title Event title.
	 *
	 * @return integer
	 */
	private function make_event_this_month( $title ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		// The 15th at noon, so it always falls inside the month being rendered.
		$start = strtotime( gmdate( 'Y-m-15 12:00:00' ) );

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
	 * A calendar rendered on an archive that returned nothing must still list
	 * the month's events.
	 *
	 * @return void
	 */
	public function test_calendar_still_lists_events_on_an_empty_archive() {
		$this->make_event_this_month( 'Calendar Guard Event' );

		$empty_term = $this->factory->term->create(
			array(
				'taxonomy' => 'se-event-category',
				'slug'     => 'empty-calendar-cat',
			)
		);

		$this->go_to( get_term_link( $empty_term, 'se-event-category' ) );

		$this->assertTrue( is_archive(), 'Expected an archive request.' );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts, 'Expected the archive to return nothing.' );
		$this->assertFalse( get_post_type(), 'Expected no global $post, which is what the guard reads.' );

		$output = SE_Blocks::calendar_render( $this->calendar_default_attributes() );

		$this->assertStringContainsString(
			'Calendar Guard Event',
			$output,
			'The calendar listed no events on an archive that returned no rows.'
		);
	}
}
