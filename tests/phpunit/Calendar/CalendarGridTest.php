<?php
/**
 * Tests for the calendar month grid aligning to the WordPress start_of_week.
 *
 * Regression coverage for the day-of-week fix (PR #76): the month grid must
 * line up under the correct weekday columns for every start_of_week value
 * (0 = Sunday through 6 = Saturday), not just Monday.
 *
 * @package Simple_Events
 */
class CalendarGridTest extends WP_UnitTestCase {

	/**
	 * Restore the start_of_week option after each test.
	 *
	 * @var mixed
	 */
	private $original_start_of_week;

	/**
	 * Stash the original option before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->original_start_of_week = get_option( 'start_of_week' );
	}

	/**
	 * Restore the original option after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		update_option( 'start_of_week', $this->original_start_of_week );
		parent::tear_down();
	}

	/**
	 * For every start_of_week (0-6) the grid must:
	 *   - start on a cell whose weekday equals start_of_week,
	 *   - end on a cell whose weekday equals start_of_week + 6 (mod 7),
	 *   - contain a whole number of 7-day weeks.
	 *
	 * Checked across several months whose first day falls on different
	 * weekdays so the offset maths is exercised from every starting position.
	 *
	 * @return void
	 */
	public function test_grid_aligns_to_start_of_week_for_all_values() {
		$months = array(
			'2026-02-01', // Sun
			'2026-03-01', // Sun
			'2026-05-01', // Fri
			'2026-06-01', // Mon
			'2026-08-01', // Sat
			'2026-09-01', // Tue
			'2026-10-01', // Thu
		);

		for ( $start_of_week = 0; $start_of_week <= 6; $start_of_week++ ) {
			update_option( 'start_of_week', $start_of_week );

			foreach ( $months as $month ) {
				$days = SE_Calendar::get_instance()->get_month_days( $month )['days'];

				$context = sprintf( 'start_of_week=%d month=%s', $start_of_week, $month );

				// Whole weeks only.
				$this->assertSame(
					0,
					count( $days ) % 7,
					"Grid should be a whole number of weeks ({$context})."
				);

				// First cell sits in the start_of_week column.
				$first_weekday = (int) $days[0]['date']->format( 'w' );
				$this->assertSame(
					$start_of_week,
					$first_weekday,
					"First grid cell weekday should equal start_of_week ({$context})."
				);

				// Last cell closes the week.
				$last_weekday = (int) end( $days )['date']->format( 'w' );
				$this->assertSame(
					( $start_of_week + 6 ) % 7,
					$last_weekday,
					"Last grid cell weekday should be start_of_week + 6 ({$context})."
				);
			}
		}
	}

	/**
	 * The grid must contain every day of the target month exactly once, with
	 * leading/trailing cells flagged as is_other_month.
	 *
	 * @return void
	 */
	public function test_grid_contains_every_day_of_the_month() {
		update_option( 'start_of_week', 1 );

		$month         = '2026-06-01';
		$days          = SE_Calendar::get_instance()->get_month_days( $month )['days'];
		$days_in_month = (int) ( new DateTime( $month ) )->format( 't' );

		$in_month = array();
		foreach ( $days as $day ) {
			if ( ! $day['is_other_month'] ) {
				$in_month[] = $day['date']->format( 'Y-m-d' );
			}
		}

		// Every calendar date June 1-30 present, in order, no gaps or repeats.
		$expected = array();
		for ( $d = 1; $d <= $days_in_month; $d++ ) {
			$expected[] = sprintf( '2026-06-%02d', $d );
		}

		$this->assertSame( $expected, $in_month, 'Grid should list each day of the month once, in order.' );
	}

	/**
	 * The 1st of the month must render in the column predicted by the same
	 * modular offset used by the fix: ( firstWeekday - start_of_week + 7 ) % 7.
	 *
	 * @return void
	 */
	public function test_first_of_month_lands_in_correct_column() {
		$month         = '2026-08-01'; // 1 Aug 2026 is a Saturday (w = 6).
		$first_weekday = 6;

		for ( $start_of_week = 0; $start_of_week <= 6; $start_of_week++ ) {
			update_option( 'start_of_week', $start_of_week );

			$days = SE_Calendar::get_instance()->get_month_days( $month )['days'];

			$column = null;
			foreach ( $days as $index => $day ) {
				if ( '2026-08-01' === $day['date']->format( 'Y-m-d' ) ) {
					$column = $index % 7;
					break;
				}
			}

			$this->assertNotNull( $column, "1 Aug 2026 should appear in the grid (start_of_week={$start_of_week})." );
			$this->assertSame(
				( $first_weekday - $start_of_week + 7 ) % 7,
				$column,
				"1 Aug 2026 should sit in the expected column (start_of_week={$start_of_week})."
			);
		}
	}

	/**
	 * The Sunday-start regression: with start_of_week = 0 a month whose 1st is
	 * a Sunday must begin the grid on that 1st (no leading padding from the
	 * previous month). The pre-fix Monday-only maths pushed it a row down.
	 *
	 * @return void
	 */
	public function test_sunday_start_with_first_day_on_sunday_has_no_leading_padding() {
		update_option( 'start_of_week', 0 );

		// 1 Mar 2026 is a Sunday.
		$days = SE_Calendar::get_instance()->get_month_days( '2026-03-01' )['days'];

		$this->assertSame( '2026-03-01', $days[0]['date']->format( 'Y-m-d' ), 'Grid should open on 1 Mar 2026 with no padding.' );
		$this->assertFalse( $days[0]['is_other_month'], 'First cell should belong to the target month.' );
	}
}
