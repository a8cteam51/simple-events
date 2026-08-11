<?php
/**
 * GH-88: a multi-day event must show on every day it spans.
 *
 * bucket_event_dates_by_day() (class-se-calendar.php:489-493) only places a
 * timed date when its end day equals its start day, so an event running from
 * Monday to Wednesday appears on no day at all. The iCal export
 * (class-se-calendar-export.php:121-125) exports the same date as a TimeSpan
 * over its real start and end, so the two surfaces disagree.
 *
 * @package Simple_Events
 */
class CalendarMultiDaySpanTest extends WP_UnitTestCase {

	/**
	 * Create a published event with one child date.
	 *
	 * @param array $date_args Args for se_event_create_event_date().
	 *
	 * @return integer The se-event-date post id.
	 */
	private function make_date( array $date_args ): int {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$date = se_event_create_event_date( $event_id, $date_args );
		$this->assertNotNull( $date, 'Failed to create the event date fixture.' );

		return (int) $date->ID;
	}

	/**
	 * Timestamp for a wall-clock time in the site timezone.
	 *
	 * @param string $datetime Wall-clock time, e.g. '2026-06-15 12:00:00'.
	 *
	 * @return integer
	 */
	private function ts( string $datetime ): int {
		return ( new DateTime( $datetime, wp_timezone() ) )->getTimestamp();
	}

	/**
	 * Grid days (Y-m-d) a given event-date id renders on.
	 *
	 * @param string  $month   First of the month, e.g. '2026-06-01'.
	 * @param integer $date_id The se-event-date post id.
	 *
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
	 * Monday 22:00 to Wednesday 01:00 shows on Monday, Tuesday and Wednesday.
	 *
	 * @return void
	 */
	public function test_a_timed_event_spanning_three_days_shows_on_all_three() {
		// 2026-06-15 is a Monday.
		$date_id = $this->make_date(
			array(
				'start_date' => $this->ts( '2026-06-15 22:00:00' ),
				'end_date'   => $this->ts( '2026-06-17 01:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array( '2026-06-15', '2026-06-16', '2026-06-17' ),
			$this->days_for_date( '2026-06-01', $date_id ),
			'An event running Monday to Wednesday should appear on each of those days.'
		);
	}

	/**
	 * The two-day case: 23:00 to 01:00 the next morning.
	 *
	 * @return void
	 */
	public function test_a_timed_event_crossing_midnight_shows_on_both_days() {
		$date_id = $this->make_date(
			array(
				'start_date' => $this->ts( '2026-06-15 23:00:00' ),
				'end_date'   => $this->ts( '2026-06-16 01:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array( '2026-06-15', '2026-06-16' ),
			$this->days_for_date( '2026-06-01', $date_id ),
			'An event crossing midnight should appear on both days, not vanish.'
		);
	}

	/**
	 * An all-day date spanning days shows on each of them.
	 *
	 * @return void
	 */
	public function test_an_all_day_event_spanning_days_shows_on_each() {
		$date_id = $this->make_date(
			array(
				'start_date' => $this->ts( '2026-06-20 00:00:00' ),
				'end_date'   => $this->ts( '2026-06-22 23:59:59' ),
				'all_day'    => true,
			)
		);

		$this->assertSame(
			array( '2026-06-20', '2026-06-21', '2026-06-22' ),
			$this->days_for_date( '2026-06-01', $date_id ),
			'An all-day event over three days should appear on each of them.'
		);
	}

	/**
	 * A single-day event still shows on exactly one day.
	 *
	 * Guards against fixing the span by placing events on days they do not run.
	 *
	 * @return void
	 */
	public function test_a_single_day_event_still_shows_on_one_day() {
		$date_id = $this->make_date(
			array(
				'start_date' => $this->ts( '2026-06-15 12:00:00' ),
				'end_date'   => $this->ts( '2026-06-15 13:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array( '2026-06-15' ),
			$this->days_for_date( '2026-06-01', $date_id ),
			'A same-day event should still render on exactly its own day.'
		);
	}

	/**
	 * A span reaching into the next month still fills the days it covers.
	 *
	 * @return void
	 */
	public function test_a_span_crossing_a_month_boundary_fills_the_visible_days() {
		$date_id = $this->make_date(
			array(
				'start_date' => $this->ts( '2026-06-29 20:00:00' ),
				'end_date'   => $this->ts( '2026-07-01 02:00:00' ),
				'all_day'    => false,
			)
		);

		$this->assertSame(
			array( '2026-06-29', '2026-06-30', '2026-07-01' ),
			$this->days_for_date( '2026-06-01', $date_id ),
			'A span running into July should still fill the June days and the July cell the grid shows.'
		);
	}

	/**
	 * hide_from_calendar still wins over the span.
	 *
	 * @return void
	 */
	public function test_a_hidden_span_shows_on_no_day() {
		$date_id = $this->make_date(
			array(
				'start_date'         => $this->ts( '2026-06-15 22:00:00' ),
				'end_date'           => $this->ts( '2026-06-17 01:00:00' ),
				'all_day'            => false,
				'hide_from_calendar' => true,
			)
		);

		$this->assertSame(
			array(),
			$this->days_for_date( '2026-06-01', $date_id ),
			'hide_from_calendar must still keep every day of the span clear.'
		);
	}
}
