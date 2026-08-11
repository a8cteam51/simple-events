<?php
/**
 * GH-88: grouping must key on the actual times, not on how they are printed.
 *
 * can_group_dates() (class-date-display-formatter.php:413) and
 * render_date_list_grouped() (:483) both build their keys from format_time()
 * output. That string depends on the site's time_format option, so a format
 * that hides minutes makes 10:00 and 10:45 compare equal and two events at
 * genuinely different times are merged under one heading.
 *
 * The dates below are deliberately 45 minutes apart: distinct under 'H:i',
 * identical under 'g a'.
 *
 * @package Simple_Events
 */
class DateGroupingTest extends WP_UnitTestCase {

	/**
	 * The parent event, set to display its dates grouped.
	 *
	 * @var integer
	 */
	private $event_id = 0;

	/**
	 * Two dates at genuinely different times.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// HTML output, so grouped and ungrouped are distinguishable in markup.
		// allow_grouping_dates_different_time is left off, so dates at different
		// times must not be grouped.
		update_option( 'se_options', array( 'use_html_in_date_output' => 'on' ) );

		$this->event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		update_post_meta( $this->event_id, 'se_event_display_grouped', true );

		foreach ( array( '2030-06-15 10:00:00' => 7200, '2030-06-20 10:45:00' => 6300 ) as $when => $length ) {
			$start = strtotime( $when );

			$date = se_event_create_event_date(
				$this->event_id,
				array(
					'start_date' => $start,
					'end_date'   => $start + $length,
					'all_day'    => false,
				)
			);

			$this->assertNotNull( $date, 'Fixture event-date should have been created.' );
		}
	}

	/**
	 * Render the event's date list under a given site time format.
	 *
	 * @param string $time_format A PHP time format, as the time_format option holds.
	 *
	 * @return string
	 */
	private function render_with_time_format( $time_format ) {
		update_option( 'time_format', $time_format );

		return ( new SE_Date_Display_Formatter( $this->event_id ) )
			->render_date_list( se_event_get_event_dates( $this->event_id ) );
	}

	/**
	 * Assert the two dates were listed separately, not merged.
	 *
	 * @param string $output    Rendered date list.
	 * @param string $time_format The format used, for the failure message.
	 *
	 * @return void
	 */
	private function assert_not_grouped( $output, $time_format ) {
		$this->assertStringNotContainsString(
			'se-event-date-list-item__grouped',
			$output,
			sprintf( 'Dates 45 minutes apart must not be grouped under time_format "%s".', $time_format )
		);
		$this->assertSame(
			2,
			substr_count( $output, 'id="se-event-date-list-item-' ),
			sprintf( 'Both dates should be listed separately under time_format "%s".', $time_format )
		);
	}

	/**
	 * A format showing minutes keeps the two dates distinct.
	 *
	 * The control: this already passes, so a failure below is the format
	 * dependency and not the grouping rules in general.
	 *
	 * @return void
	 */
	public function test_dates_at_different_times_are_not_grouped_under_a_precise_format() {
		$this->assert_not_grouped( $this->render_with_time_format( 'H:i' ), 'H:i' );
	}

	/**
	 * A format hiding minutes must not change the grouping decision.
	 *
	 * @return void
	 */
	public function test_dates_at_different_times_are_not_grouped_under_an_hour_only_format() {
		$this->assert_not_grouped( $this->render_with_time_format( 'g a' ), 'g a' );
	}

	/**
	 * The same data must group the same way whatever the format.
	 *
	 * Stated on its own because this is the reported symptom: an admin changing
	 * a display setting silently changes which events are treated as the same.
	 *
	 * @return void
	 */
	public function test_the_time_format_does_not_change_the_grouping_decision() {
		$precise   = $this->render_with_time_format( 'H:i' );
		$hour_only = $this->render_with_time_format( 'g a' );

		$this->assertSame(
			substr_count( $precise, 'se-event-date-list-item__grouped' ),
			substr_count( $hour_only, 'se-event-date-list-item__grouped' ),
			'The time format must not change how many groups are produced.'
		);
	}

	/**
	 * The printed time still follows the site's time_format.
	 *
	 * The grouping key is a fixed format so the decision is stable, but the
	 * label is presentation and must keep honouring the site setting.
	 *
	 * @return void
	 */
	public function test_the_printed_time_still_follows_the_site_time_format() {
		$this->assertStringContainsString(
			'10:00',
			$this->render_with_time_format( 'H:i' ),
			"A 24-hour site format should print '10:00'."
		);

		$hour_only = $this->render_with_time_format( 'g a' );

		$this->assertStringContainsString( '10 am', $hour_only, "A 'g a' site format should print '10 am'." );
		$this->assertStringNotContainsString( '10:00:00', $hour_only, 'The internal grouping key must never reach the page.' );
	}

	/**
	 * Dates genuinely at the same time still group.
	 *
	 * Guards against fixing this by never grouping anything.
	 *
	 * @return void
	 */
	public function test_dates_at_the_same_time_still_group() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		update_post_meta( $event_id, 'se_event_display_grouped', true );

		foreach ( array( '2030-06-15 10:00:00', '2030-06-20 10:00:00' ) as $when ) {
			$start = strtotime( $when );

			se_event_create_event_date(
				$event_id,
				array(
					'start_date' => $start,
					'end_date'   => $start + 7200,
					'all_day'    => false,
				)
			);
		}

		update_option( 'time_format', 'H:i' );

		$output = ( new SE_Date_Display_Formatter( $event_id ) )
			->render_date_list( se_event_get_event_dates( $event_id ) );

		$this->assertStringContainsString(
			'se-event-date-list-item__grouped',
			$output,
			'Two dates at the same time should still be grouped.'
		);
	}
}
