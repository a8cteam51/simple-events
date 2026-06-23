<?php
/**
 * Tests that calendar month navigation ignores events whose parent is not published.
 *
 * Both get_previous_month_with_events() and get_next_month_with_events() decide which
 * month the prev/next arrows jump to by querying se-event-date posts. Those date posts
 * are always 'publish', so without a parent-published guard a draft event still drives
 * the navigation.
 *
 * @package Simple_Events
 */
class CalendarMonthNavDraftTest extends WP_UnitTestCase {

	/**
	 * Create an event of a given status with a single date on $start_day.
	 *
	 * @param string $parent_status Parent se-event post_status.
	 * @param string $start_day     Date in 'Y-m-d' form for the event date.
	 *
	 * @return integer The parent event ID.
	 */
	private function make_event( $parent_status, $start_day ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => $parent_status,
			)
		);

		$tz    = wp_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_day . ' 10:00:00', $tz )->getTimestamp();
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_day . ' 11:00:00', $tz )->getTimestamp();

		$date = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $end,
				'all_day'    => false,
			)
		);
		$this->assertNotNull( $date );

		return $event_id;
	}

	/**
	 * The reference "current" month for navigation (June 2030).
	 *
	 * @return DateTime
	 */
	private function june_2030() {
		return new DateTime( '2030-06-15', wp_timezone() );
	}

	/**
	 * Happy path: a published event in an earlier month is found by prev-nav.
	 *
	 * @return void
	 */
	public function test_prev_month_returns_published_earlier_event() {
		$this->make_event( 'publish', '2030-04-10' );

		$result = SE_Calendar::get_instance()->get_previous_month_with_events( $this->june_2030() );

		$this->assertInstanceOf( DateTime::class, $result );
		$this->assertSame( '2030-04', $result->format( 'Y-m' ) );
	}

	/**
	 * Sad path: a draft event in an earlier month must not drive prev-nav.
	 *
	 * @return void
	 */
	public function test_prev_month_skips_draft_earlier_event() {
		$this->make_event( 'draft', '2030-04-10' );

		$result = SE_Calendar::get_instance()->get_previous_month_with_events( $this->june_2030() );

		$this->assertNull( $result, 'Draft event in an earlier month must not drive prev-month navigation.' );
	}

	/**
	 * Happy path: a published event in a future month is found by next-nav.
	 *
	 * @return void
	 */
	public function test_next_month_returns_published_future_event() {
		$this->make_event( 'publish', '2030-08-10' );

		$result = SE_Calendar::get_instance()->get_next_month_with_events( $this->june_2030() );

		$this->assertInstanceOf( DateTime::class, $result );
		$this->assertSame( '2030-08', $result->format( 'Y-m' ) );
	}

	/**
	 * Sad path: a draft event in a future month must not drive next-nav.
	 *
	 * @return void
	 */
	public function test_next_month_skips_draft_future_event() {
		$this->make_event( 'draft', '2030-08-10' );

		$result = SE_Calendar::get_instance()->get_next_month_with_events( $this->june_2030() );

		$this->assertNull( $result, 'Draft event in a future month must not drive next-month navigation.' );
	}

	/**
	 * Mixed: a nearer draft event must be skipped in favour of a later published one.
	 *
	 * @return void
	 */
	public function test_next_month_skips_nearer_draft_for_later_published() {
		$this->make_event( 'draft', '2030-07-10' );   // nearer, but draft.
		$this->make_event( 'publish', '2030-09-10' ); // further, published.

		$result = SE_Calendar::get_instance()->get_next_month_with_events( $this->june_2030() );

		$this->assertInstanceOf( DateTime::class, $result );
		$this->assertSame( '2030-09', $result->format( 'Y-m' ), 'Next-nav should skip the nearer draft and land on the published event.' );
	}
}
