<?php
/**
 * Characterisation tests for the calendar grid.
 *
 * Locks current behaviour of SE_Calendar::get_month_days(); in particular that
 * `hide_from_calendar` keeps an event off the calendar grid. No production code
 * is changed.
 *
 * @package Simple_Events
 */
class CalendarHideFromCalendarTest extends WP_UnitTestCase {

	/**
	 * Create a published event with one child date.
	 *
	 * @param array $date_args Args for se_event_create_event_date().
	 * @return array{event_id:int, date_id:int}
	 */
	private function make_event_with_date( array $date_args ): array {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$date = se_event_create_event_date( $event_id, $date_args );
		$this->assertNotNull( $date, 'Failed to create the event date fixture.' );

		return array(
			'event_id' => $event_id,
			'date_id'  => (int) $date->ID,
		);
	}

	/**
	 * Timestamp for a wall-clock time in the site timezone.
	 *
	 * @param string $datetime e.g. '2026-06-15 12:00:00'.
	 * @return int
	 */
	private function ts( string $datetime ): int {
		return ( new DateTime( $datetime, wp_timezone() ) )->getTimestamp();
	}

	/**
	 * Grid days (Y-m-d) a given event-date id renders on.
	 *
	 * @param string $month   First of the month, e.g. '2026-06-01'.
	 * @param int    $date_id The se-event-date post id.
	 * @return string[]
	 */
	private function days_for_date( string $month, int $date_id ): array {
		$data = SE_Calendar::get_instance()->get_month_days( $month );
		$days = array();
		foreach ( $data['days'] as $day ) {
			foreach ( $day['events'] as $event ) {
				if ( (int) $event->event_date_id === $date_id ) {
					$days[] = $day['date_formatted'];
				}
			}
		}
		return $days;
	}

	/**
	 * A normal published, timed event shows on its start day.
	 */
	public function test_published_timed_event_shows_on_its_day() {
		$fixture = $this->make_event_with_date(
			array(
				'start_date' => $this->ts( '2026-06-15 12:00:00' ),
				'end_date'   => $this->ts( '2026-06-15 13:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array( '2026-06-15' ),
			$this->days_for_date( '2026-06-01', $fixture['date_id'] ),
			'A timed event should render on exactly its start day.'
		);
	}

	/**
	 * Headline: hide_from_calendar keeps the event off the grid entirely.
	 */
	public function test_hide_from_calendar_removes_event_from_grid() {
		$fixture = $this->make_event_with_date(
			array(
				'start_date'         => $this->ts( '2026-06-15 12:00:00' ),
				'end_date'           => $this->ts( '2026-06-15 13:00:00' ),
				'all_day'            => false,
				'hide_from_calendar' => true,
			)
		);

		$this->assertSame(
			array(),
			$this->days_for_date( '2026-06-01', $fixture['date_id'] ),
			'hide_from_calendar should keep the event off every calendar day.'
		);
	}

	/**
	 * Current placement rule: a timed event crossing midnight shows on no day.
	 */
	public function test_timed_event_crossing_midnight_shows_on_no_day() {
		$fixture = $this->make_event_with_date(
			array(
				'start_date' => $this->ts( '2026-06-15 23:00:00' ),
				'end_date'   => $this->ts( '2026-06-16 01:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array(),
			$this->days_for_date( '2026-06-01', $fixture['date_id'] ),
			'A timed event whose end runs past its start day shows on no day (current behaviour).'
		);
	}

	/**
	 * An all-day event shows on its start day.
	 */
	public function test_all_day_event_shows_on_start_day() {
		$fixture = $this->make_event_with_date(
			array(
				'start_date' => $this->ts( '2026-06-20 00:00:00' ),
				'end_date'   => $this->ts( '2026-06-20 23:59:59' ),
				'all_day'    => true,
			)
		);

		$this->assertSame(
			array( '2026-06-20' ),
			$this->days_for_date( '2026-06-01', $fixture['date_id'] ),
			'An all-day event should render on its start day.'
		);
	}
}
