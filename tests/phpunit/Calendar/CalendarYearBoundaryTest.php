<?php
/**
 * GH-88: the calendar's previous/next month flags must respect the year.
 *
 * get_month_days() compares month numbers alone
 * (class-se-calendar.php:169-170), so at a year boundary they invert: in a
 * January grid the trailing December cells read as "next month", and in a
 * December grid the leading January cells read as "previous month".
 *
 * Every existing fixture sits inside 2026, so nothing covers this.
 *
 * @package Simple_Events
 */
class CalendarYearBoundaryTest extends WP_UnitTestCase {

	/**
	 * The grid cells that belong to a month other than the one being viewed.
	 *
	 * @param string $month_start First day of the month to view, 'Y-m-d'.
	 *
	 * @return array<int, array>
	 */
	private function other_month_cells( $month_start ) {
		$days = SE_Calendar::get_instance()->get_month_days( $month_start )['days'];

		return array_values(
			array_filter(
				$days,
				function ( $day ) {
					return $day['is_other_month'];
				}
			)
		);
	}

	/**
	 * Assert every out-of-month cell is flagged on the correct side.
	 *
	 * Compares 'Y-m' against the viewed month, so the year is part of the
	 * comparison rather than the month number alone.
	 *
	 * @param string $month_start First day of the month being viewed, 'Y-m-d'.
	 *
	 * @return void
	 */
	private function assert_cells_flagged_correctly( $month_start ) {
		$viewed = gmdate( 'Y-m', strtotime( $month_start ) );
		$cells  = $this->other_month_cells( $month_start );

		$this->assertNotEmpty( $cells, sprintf( 'The %s grid should have out-of-month cells to check.', $viewed ) );

		foreach ( $cells as $cell ) {
			$cell_month  = $cell['date']->format( 'Y-m' );
			$is_earlier  = $cell_month < $viewed;
			$description = sprintf( 'Cell %s in the %s grid', $cell['date_formatted'], $viewed );

			$this->assertSame(
				$is_earlier,
				$cell['is_previous_month'],
				$description . ' has the wrong is_previous_month flag.'
			);
			$this->assertSame(
				! $is_earlier,
				$cell['is_next_month'],
				$description . ' has the wrong is_next_month flag.'
			);
		}
	}

	/**
	 * A January grid's trailing December cells belong to the previous month.
	 *
	 * @return void
	 */
	public function test_january_grid_flags_december_cells_as_previous_month() {
		$this->assert_cells_flagged_correctly( '2027-01-01' );
	}

	/**
	 * A December grid's leading January cells belong to the next month.
	 *
	 * @return void
	 */
	public function test_december_grid_flags_january_cells_as_next_month() {
		$this->assert_cells_flagged_correctly( '2026-12-01' );
	}

	/**
	 * A mid-year grid, where month numbers alone are already enough.
	 *
	 * The control: this passes before the fix, so a failure above is the year
	 * boundary and not the flagging in general.
	 *
	 * @return void
	 */
	public function test_mid_year_grid_flags_cells_correctly() {
		$this->assert_cells_flagged_correctly( '2026-06-01' );
	}

	/**
	 * No cell is ever both, and an in-month cell is neither.
	 *
	 * @return void
	 */
	public function test_flags_are_mutually_exclusive_across_a_year_boundary() {
		$days = SE_Calendar::get_instance()->get_month_days( '2027-01-01' )['days'];

		foreach ( $days as $day ) {
			$this->assertFalse(
				$day['is_previous_month'] && $day['is_next_month'],
				sprintf( 'Cell %s is flagged as both previous and next month.', $day['date_formatted'] )
			);

			if ( ! $day['is_other_month'] ) {
				$this->assertFalse( $day['is_previous_month'], sprintf( 'In-month cell %s should not be previous.', $day['date_formatted'] ) );
				$this->assertFalse( $day['is_next_month'], sprintf( 'In-month cell %s should not be next.', $day['date_formatted'] ) );
			}
		}
	}
}
