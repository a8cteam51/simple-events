<?php
/**
 * Tests that calendar event-date queries respect the parent event's publish status.
 *
 * Regression coverage: event-date posts are always created as 'publish', and the
 * calendar query (SE_Event_Dates::find_event_dates) filters only on the date post's
 * own status. Without a parent-published guard, a date whose parent se-event is in
 * draft/pending status still leaked into the calendar. find_event_dates() now applies
 * SE_Event_Query_Utils::filter_event_dates_where, matching the archive/feed behaviour.
 *
 * @package Simple_Events
 */
class EventDatesParentStatusTest extends WP_UnitTestCase {

	/**
	 * The calendar day used across these tests.
	 *
	 * @var string
	 */
	private $day = '2030-06-15';

	/**
	 * Create an event of a given status with a single date on $this->day.
	 *
	 * The date itself is always created as 'publish' (mirroring production
	 * behaviour) so the only thing under test is the parent event's status.
	 *
	 * @param string $parent_status Parent se-event post_status (publish, draft, pending...).
	 *
	 * @return array{event_id:int, date_id:int} The parent and child IDs.
	 */
	private function create_event_with_date( $parent_status ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => $parent_status,
			)
		);

		$tz    = wp_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', $this->day . ' 10:00:00', $tz )->getTimestamp();
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', $this->day . ' 11:00:00', $tz )->getTimestamp();

		$date = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $end,
				'all_day'    => false,
			)
		);

		$this->assertNotNull( $date, 'Fixture event-date should have been created.' );
		// The child date is published regardless of the parent's status.
		$this->assertSame( 'publish', get_post_status( $date->ID ) );

		return array(
			'event_id' => $event_id,
			'date_id'  => $date->ID,
		);
	}

	/**
	 * Collect the parent event IDs returned for the test day.
	 *
	 * @return int[]
	 */
	private function event_ids_for_day() {
		$tz    = wp_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', $this->day . ' 00:00:00', $tz )->getTimestamp();
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', $this->day . ' 23:59:59', $tz )->getTimestamp();
		$dates = SE_Event_Query_Utils::get_event_dates_for_range( $start, $end );
		return wp_list_pluck( $dates, 'event_id' );
	}

	/**
	 * Happy path: a date whose parent event is published is returned for the calendar.
	 *
	 * @return void
	 */
	public function test_published_parent_event_date_is_returned() {
		$ids = $this->create_event_with_date( 'publish' );

		$this->assertContains(
			$ids['event_id'],
			$this->event_ids_for_day(),
			'A published event\'s date should appear in the calendar.'
		);
	}

	/**
	 * Sad path: a date whose parent event is in draft status must NOT be returned.
	 *
	 * @return void
	 */
	public function test_draft_parent_event_date_is_excluded() {
		$ids = $this->create_event_with_date( 'draft' );

		$this->assertNotContains(
			$ids['event_id'],
			$this->event_ids_for_day(),
			'A draft event\'s date should not appear in the calendar.'
		);
	}

	/**
	 * Sad path: a date whose parent event is pending must NOT be returned either.
	 *
	 * @return void
	 */
	public function test_pending_parent_event_date_is_excluded() {
		$ids = $this->create_event_with_date( 'pending' );

		$this->assertNotContains(
			$ids['event_id'],
			$this->event_ids_for_day(),
			'A pending event\'s date should not appear in the calendar.'
		);
	}

	/**
	 * Mixed: on a day with both a published and a draft event, only the published
	 * one is returned. Guards against the filter being too broad or too narrow.
	 *
	 * @return void
	 */
	public function test_only_published_parents_returned_when_mixed() {
		$published = $this->create_event_with_date( 'publish' );
		$draft     = $this->create_event_with_date( 'draft' );

		$returned = $this->event_ids_for_day();

		$this->assertContains( $published['event_id'], $returned, 'Published event should be present.' );
		$this->assertNotContains( $draft['event_id'], $returned, 'Draft event should be filtered out.' );
	}

	/**
	 * End-to-end through the calendar grid: the rendered month must not list a
	 * draft event's date in its day cell, but must list the published one.
	 *
	 * @return void
	 */
	public function test_calendar_grid_excludes_draft_parent_events() {
		$published = $this->create_event_with_date( 'publish' );
		$draft     = $this->create_event_with_date( 'draft' );

		$days = SE_Calendar::get_instance()->get_month_days( '2030-06-01' )['days'];

		$event_ids_in_cell = array();
		foreach ( $days as $day ) {
			if ( $this->day === $day['date']->format( 'Y-m-d' ) ) {
				foreach ( $day['events'] as $event ) {
					$event_ids_in_cell[] = $event->event_id;
				}
			}
		}

		$this->assertContains( $published['event_id'], $event_ids_in_cell, 'Published event should render in the calendar cell.' );
		$this->assertNotContains( $draft['event_id'], $event_ids_in_cell, 'Draft event should not render in the calendar cell.' );
	}
}
