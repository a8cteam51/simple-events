<?php
/**
 * Event date display formatter.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Display Formatter Class.
 */
class SE_Date_Display_Formatter {

	/**
	 * The event id.
	 *
	 * @var integer
	 */
	private $event_id = 0;

	/**
	 * Group dates with the same start and end times.
	 *
	 * @var boolean
	 */
	private $group_dates = false;

	/**
	 * Is the current view a single event view.
	 *
	 * @var boolean
	 */
	private $is_single_view = false;

	/**
	 * The event date id.
	 *
	 * @var integer|null
	 */
	private $event_date_id = null;

	/**
	 * The event timezone.
	 *
	 * @var string
	 */
	private $event_timezone = '';

	/**
	 * Show the timezone
	 *
	 * @var boolean
	 */
	private $display_timezone = false;

	/**
	 * Hide the end time.
	 *
	 * @var boolean
	 */
	private $hide_end_time = false;

	/**
	 * Hide the start time.
	 *
	 * @var boolean
	 */
	private $hide_start_time = false;

	/**
	 * Show add to calendar button.
	 *
	 * @var boolean
	 */
	private $show_add_to_calendar = false;

	/**
	 * Open event in new tab.
	 *
	 * @var boolean
	 */
	private $open_in_new_tab = false;

	/**
	 * Treat each date as own event for navigation.
	 *
	 * @var boolean
	 */
	private $treat_each_date_as_own_event = false;

	/**
	 * Allow grouping dates with different times.
	 *
	 * @var boolean
	 */
	private $allow_grouping_dates_different_time = false;

	/**
	 * Create a new instance of the date display formatter.
	 *
	 * @param integer $event_id The event id.
		*/
	public function __construct( int $event_id ) {
		$options = get_option(
			'se_options',
			array(
				'treat_each_date_as_own_event'        => false,
				'allow_grouping_dates_different_time' => false,
			)
		);
		// dump($options);
		$this->event_id                            = $event_id;
		$this->treat_each_date_as_own_event        = isset( $options['treat_each_date_as_own_event'] ) && 'on' === $options['treat_each_date_as_own_event'];
		$this->allow_grouping_dates_different_time = isset( $options['allow_grouping_dates_different_time'] ) && 'on' === $options['allow_grouping_dates_different_time'];
		$this->group_dates                         = filter_var( get_post_meta( $event_id, 'se_event_display_grouped', true ), FILTER_VALIDATE_BOOLEAN );
		$this->is_single_view                      = is_single();
		$this->event_timezone                      = get_post_meta( $event_id, 'se_event_timezone', true );
		$this->event_date_id                       = se_template_get_event_date_id();
		$this->display_timezone                    = filter_var( get_post_meta( $event_id, 'se_event_timezone', true ), FILTER_VALIDATE_BOOLEAN );
		$this->hide_end_time                       = filter_var( get_post_meta( $event_id, 'se_event_hide_end_time', true ), FILTER_VALIDATE_BOOLEAN );
		$this->hide_start_time                     = filter_var( get_post_meta( $event_id, 'se_event_hide_start_time', true ), FILTER_VALIDATE_BOOLEAN );
		$this->show_add_to_calendar                = filter_var( get_post_meta( $event_id, 'se_event_add_calendar_links', true ), FILTER_VALIDATE_BOOLEAN );
		$this->open_in_new_tab                     = filter_var( get_post_meta( $event_id, 'se_event_open_in_new_window', true ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Modify Timezone.
	 *
	 * @param string $timezone The timezone.
	 *
	 * @return string
	 */
	public function modify_timezone( $timezone ) {
		return $timezone;
	}

	/**
	 * Has event date id.
	 *
	 * @return boolean
	 */
	public function has_event_date_in_url() {
		return $this->event_date_id > 0;
	}

	/**
	 * Treat each date as own event for navigation.
	 *
	 * @return boolean
	 */
	public function is_treating_each_date_as_own_event() {
		return $this->treat_each_date_as_own_event;
	}

	/**
	 * Formats the dates for the event.
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates      Event dates.
	 * @param mixed                                                                              $hide_end_time    If we should hide the end time.
	 * @param mixed                                                                              $hide_start_time  If we should hide the start time.
	 * @param mixed                                                                              $display_timezone If we should display the timezone.
	 *
	 * @return string
	 */
	public function format_dates__OLD( $event_dates ) {
		// Attempt to get the event date id from url.
		$event_date_id = se_template_get_event_date_id();

		$dates_count = is_array( $event_dates ) ? count( $event_dates ) : 1;

		if ( ! empty( $this->event_timezone ) ) {
			$timezone = new DateTimeZone( $this->event_timezone );
		} else {
			$timezone = wp_timezone();
		}

		$timezone_date = new DateTime( '', $timezone );
		$timezone_abbr = $timezone_date->format( 'T' );

		// Begin output as a list if the count is more than 1.
		$dates_output = ( $dates_count > 1 ) ? '<ul>' : '';

		// Get the start and end times from the first date.
		// Assume all start and end times are the same until proven otherwise.
		$event_time_start = wp_date( get_option( 'time_format' ), $event_dates[0]['start_date'], $timezone );
		$event_time_end   = wp_date( get_option( 'time_format' ), $event_dates[0]['end_date'], $timezone );
		$same_times       = ( 1 < $dates_count ) ? true : false;

		// Loop over each available event date.
		foreach ( $event_dates as $date ) {
			dump( $this->render_single_date( $date ) );
			$opening_li = $date['id'] === $event_date_id ? '<li class="active" style="text-decoration: underline;">' : '<li>';

			// Check if start and end times are on the same day.
			$same_day = wp_date( 'Y-m-d', $date['start_date'], $timezone ) === wp_date( 'Y-m-d', $date['end_date'], $timezone );

			// Get start and end times.
			$time_start = ( $this->hide_start_time ) ? '' : wp_date( get_option( 'time_format' ), $date['start_date'], $timezone );
			$time_end   = ( $this->hide_end_time ) ? '' : wp_date( get_option( 'time_format' ), $date['end_date'], $timezone );

			$time_separator = ( 1 === (int) $this->hide_start_time ) ? '' : '&ndash;';

			// Invalidate same times if the start or end times don't match.
			if ( $same_times && ( $time_start !== $event_time_start || $time_end !== $event_time_end ) ) {
				$same_times = false;
			}

			// Ensure we're working with a boolean.
			$date['all_day'] = array_key_exists( 'all_day', $date ) ? filter_var( $date['all_day'], FILTER_VALIDATE_BOOLEAN ) : false;

			// Start the output string.
			$single_date_output = wp_date( get_option( 'date_format' ), $date['start_date'], $timezone );

			// Return early if we only want the date.
			if ( $this->date_only ) {
				$end_date         = wp_date( get_option( 'date_format' ), $date['end_date'], $timezone );
				$date_only_output = ( $same_day ) ? $single_date_output : $single_date_output . ' &ndash; ' . $end_date;
				$dates_output    .= ( $dates_count > 1 ) ? $opening_li . $date_only_output . '</li>' : $date_only_output;
				continue;
			}

			// Return early if we only want the time.
			if ( $this->time_only ) {
				$time          = sprintf( '%s %s %s', $time_start, $time_separator, $time_end );
				$dates_output .= ( $dates_count > 1 ) ? $opening_li . $time . '</li>' : $time;
				continue;
			}

			if ( ! $same_day ) {

				// If the event doesn't start and finish on the same day.
				$single_date_output .= sprintf(
					' %s &ndash; %s %s',
					$time_start,
					wp_date( get_option( 'date_format' ), $date['end_date'], $timezone ),
					$time_end
				);
			} elseif ( false === $date['all_day'] && $time_start !== $time_end ) {

				// Else if the event isn't all day, and the start and end times are different.
				$single_date_output .= sprintf( ' %s %s %s', $time_start, $time_separator, $time_end );
			}

			// Display timezone only after the first date.
			if ( $this->display_timezone && $date === $event_dates[0] ) {
				$single_date_output .= ' (' . $timezone_abbr . ')';
			}

			// Return output for this date.
			$dates_output .= ( $dates_count > 1 ) ? $opening_li . $single_date_output . '</li>' : $single_date_output;
		}

		// Overwrite output if all start and end times are the same and "Group event dates with matching times" otpion is selected.

		if ( $same_times && $this->group_dates ) {
			$event_date_start = wp_date( get_option( 'date_format' ), $event_dates[0]['datetime_start'], $timezone );
			$event_date_end   = wp_date( get_option( 'date_format' ), $event_dates[ $dates_count - 1 ]['datetime_start'], $timezone );

			$dates_output = ( $dates_count > 1 ) ? '<ul><li>' : '';

			$event_time_end = ( $this->hide_end_time ) ? '' : ' &ndash; ' . $event_time_end;

			// Don't display time if it's an all day event.
			if ( $date['all_day'] ) {
				$dates_output .= sprintf( ' %s &ndash; %s', $event_date_start, $event_date_end );
			} else {
				$dates_output .= sprintf( ' %s &ndash; %s %s %s', $event_date_start, $event_date_end, $event_time_start, $event_time_end );
			}

			if ( $this->display_timezone ) {
				$dates_output .= ' (' . $timezone_abbr . ')';
			}

			$dates_output .= ( $dates_count > 1 ) ? '</li>' : '';
		}

		$dates_output .= ( $dates_count > 1 ) ? '</ul>' : '';

		return $dates_output;
	}

	/**
	 * Get the date range for the event.
	 *
	 * @param array<int, array{start_date: integer, end_date: integer, all_day:boolean, hide_from_calendar:boolean, hide_from_feed:boolean, id:integer}> $event_dates Event dates.
	 *
	 * @return array{start_date: string, end_date: string}
	 */
	public function get_date_range( array $event_dates ) {
		$start = null;
		$end   = null;

		// Loop over each date.
		foreach ( $event_dates as $date ) {
			if ( $start === null || $date['start_date'] < $start ) {
				$start = $date['start_date'];
			}

			if ( $end === null || $date['end_date'] > $end ) {
				$end = $date['end_date'];
			}

			// If all day and start is after the latest end date, set the end date to the start date.
			if ( $date['all_day'] && $date['start_date'] > $end ) {
				$end = $date['start_date'];
			}
		}

		return array(
			'start_date' => $start,
			'end_date'   => $end,
		);
	}

	/**
	 * Gets the header date for the event.
	 *
	 * @param array<int, array{datetime_start: integer, end_date: integer, all_day:boolean}> $event_dates Event dates.
	 *
	 * @return string
	 */
	public function get_header_date( array $event_dates ) {
		// If we are treating each date as it own.
		if ( $this->treat_each_date_as_own_event && $this->event_date_id ) {
			$found_date = array_filter(
				$event_dates,
				function ( $date ) {
					return $date['id'] === $this->event_date_id;
				}
			);

			if ( $found_date ) {
				return $this->render_single_date( $found_date[0] );
			}
		}

		// If we are grouping dates, return the first date.
		$date_range           = $this->get_date_range( $event_dates );
		$cloned               = $event_dates[0];
		$cloned['start_date'] = $date_range['start_date'];
		$cloned['end_date']   = $date_range['end_date'];
		$cloned['id']         = $this->event_id;
		return $this->render_single_date( $cloned );
	}

	/**
	 * Render active date.
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates Event dates.
	 *
	 * @return string|null
	 */
	public function render_active_date( array $event_dates ) {
		// If we dont have an event date id, return the first date.
		if ( ! $this->event_date_id ) {
			return null;
		}

		// If we are not treating each date as it own, return null.
		if ( ! $this->treat_each_date_as_own_event ) {
			return null;
		}

		// Find the date in the event dates.
		$found_date = array_filter(
			$event_dates,
			function ( $date ) {
				return $date['id'] === $this->event_date_id;
			}
		);

		// If we found the date, return it.
		if ( $found_date ) {
			return $this->render_single_date( array_values( $found_date )[0] );
		}

		return null;
	}

	/**
	 * Renders a date list.
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates          Event dates.
	 * @param boolean                                                                            $exclude_current_date Exclude the current date.
	 * @param boolean                                                                            $exclude_past_dates   Exclude dates that are in the past.
	 *
	 * @return string
	 */
	public function render_date_list( array $event_dates, bool $exclude_current_date = false, bool $exclude_past_dates = false ) {

		// Filter the event dates.
		$event_dates = array_filter(
			$event_dates,
			function ( $date ) use ( $exclude_current_date, $exclude_past_dates ) {
				// If the date is the current date, exclude it.
				if ( $exclude_current_date && $this->event_date_id && $date['id'] === $this->event_date_id ) {
					return false;
				}

				// If the date is in the past, exclude it.
				if ( $exclude_past_dates && $date['datetime_start'] < SE_Calendar::get_instance()->create_date_time( 'now' )->format( 'U' ) ) {
					return false;
				}

				return true;
			}
		);

		// Sort by the start date.
		usort( $event_dates, function ( $a, $b ) {
			return $a['start_date'] - $b['start_date'];
		} );

		// Get the date count.
		$dates_count = count( $event_dates );

		// If there is only one date, return the single date.
		if ( 1 === $dates_count ) {
			return sprintf( '<ul id="se-event-date-list" class="se-event-date-list__single"><li>%s</li></ul>', $this->render_single_date( $event_dates[0] ) );
		}

		// Start building the output.
		$wrapper_class = array( 'se-event-date-list', $this->group_dates ? 'se-event-date-list__grouped' : '', $this->event_date_id ? 'se-event-date-list__active' : '' );
		$output        = sprintf( '<ul id="se-event-date-list" class="%s">', implode( ' ', $wrapper_class ) );

		// Base if we are grouped, or not.
		if ( $this->can_group_dates( $event_dates ) ) {
			$output .= $this->render_date_list_grouped( $event_dates );
		} else {
			$output .= $this->render_date_list_ungrouped( $event_dates );
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Checks if the dates can be grouped.
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates Event dates.
	 *
	 * @return boolean
	 */
	private function can_group_dates( array $event_dates ) {
		// If we are not grouping dates, return false.
		if ( ! $this->group_dates ) {
			return false;
		}

		$times = array();

		foreach ( $event_dates as $date ) {
			$index = $date['all_day'] ? 'all_day' : $this->format_time( $date['start_date'] ) . ' - ' . $this->format_time( $date['end_date'] );

			// If this index is not in the array, add it.
			if ( ! in_array( $index, $times, true ) ) {
				$times[] = $index;
			}

			// If have more than one time and do not allow_grouping_dates_different_time, return false.
			if ( count( $times ) > 1 && ! $this->allow_grouping_dates_different_time ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Renders the list of date as single items  (not grouped view)
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates     Event dates.
	 * @param string                                                                             $existing_output Existing output.
	 *
	 * @return string
	 */
	private function render_date_list_ungrouped( array $event_dates, string $existing_output = '' ) {
		foreach ( $event_dates as $date ) {
			// If we dont have an id on date, set as null.
			if ( ! isset( $date['id'] ) ) {
				$date['id'] = null;
			}
			$item_class       = array( 'se-event-date-list-item', $date['id'] === $this->event_date_id ? 'se-event-date-list-item__active' : '' );
			$existing_output .= sprintf( '<li id="se-event-date-list-item-%s" class="%s">%s</li>', $date['id'], implode( ' ', $item_class ), $this->render_single_date( $date ) );
		}

		return $existing_output;
	}

	/**
	 * Renders the list of date as grouped items  (grouped view)
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates     Event dates.
	 * @param string                                                                             $existing_output Existing output.
	 *
	 * @return string
	 */
	private function render_date_list_grouped( array $event_dates, string $existing_output = '' ) {
		$groups = array();

		// iterate over the dates and group them by the start and end times.
		foreach ( $event_dates as $date ) {
			// If this event is all day.
			if ( $date['all_day'] ) {
				$groups['all_day'][] = $date;
				continue;
			}
			// Convert the start and end times,/
			$start = $this->format_time( $date['start_date'] );
			$end   = $this->format_time( $date['end_date'] );

			// Add the date to the group.
			$groups[ $start . ' - ' . $end ][] = $date;
		}

		// Iterate over each group, and break them down to the starting month.
		foreach ( $groups as $group ) {
			// Create the time label.
			$time_label = $group[0]['all_day'] ? 'All Day' : null;
			if ( ! $time_label ) {
				$time_start = ( $this->hide_start_time ) ? '' : $this->format_time( $group[0]['start_date'] );
				$time_end   = ( $this->hide_end_time ) ? '' : $this->format_time( $group[0]['end_date'] );
				// Join using &ndash if we have a start and end time.
				if ( ! empty( $time_start ) && ! empty( $time_end ) ) {
					$time_label = $time_start . ' &ndash; ' . $time_end;
				} elseif ( ! empty( $time_start ) ) {
					$time_label = $time_start;
				} elseif ( ! empty( $time_end ) ) {
					$time_label = $time_end;
				} else {
					$time_label = '';
				}
			}

			$dates = array();
			foreach ( $group as $date ) {
				// Get 2020-12 for the start date.
				$month_year             = wp_date( 'Y-m', $date['start_date'], $this->get_timezone_instance() );
				$same_day               = wp_date( 'Y-m-d', $date['start_date'], $this->get_timezone_instance() ) === wp_date( 'Y-m-d', $date['end_date'], $this->get_timezone_instance() );
				$dates[ $month_year ][] = array(
					'date'         => $date,
					'same_day'     => $same_day,
					'display_date' => $same_day ? $this->format_date( $date['start_date'] ) : $this->format_date( $date['start_date'] ) . ' &ndash; ' . $this->format_date( $date['end_date'] ),
				);
			}

			foreach ( $dates as $month_dates ) {
					$dates_string = $this->join_string( array_column( $month_dates, 'display_date' ), ', ', ' and ' );

				// Lets start compiling the output.
				$output = '';
				// If the date is on the same day, we can just render the date.
				$output .= '<li><div class="se-event-date-list-item__grouped" data-se_grouped_date_label="' . $time_label . '">';
				// Add the date.
				$output .= '<div class="se-event-date-list-item__grouped-date">';
				$output .= $dates_string;
				$output .= '</div>';

				// Add the time.
				$output .= '<div class="se-event-date-list-item__grouped-time">';
				$output .= $time_label;
				$output .= '</div>';

				$output          .= '</div>';
				$output          .= '</li>';
				$existing_output .= $output;
			}
		}

		return $existing_output;
	}

	/**
	 * Join a string  with differing separators a the then
	 *
	 * example join_string(['a','b','c'], ',', ' and ') => 'a, b and c'
	 *
	 * @param string[] $items         The items to join.
	 * @param string   $separator     The separator to use.
	 * @param string   $separator_end The final separator to use.
	 *
	 * @return string
	 */
	private function join_string( array $items, string $separator, string $separator_end ) {
		// If arrray only contains one item, return it.
		if ( count( $items ) === 1 ) {
			return $items[0];
		}

		// If array contains two items, return them joined by the separator.
		if ( count( $items ) === 2 ) {
			return implode( $separator_end, $items );
		}

		// Remove the last item.
		$last_item = array_pop( $items );

		// Join the items with the separator.
		$output = implode( $separator, $items );

		// Add the separator_end to the last item.
		return $output . $separator_end . $last_item;
	}

	/**
	 * Formats the dates for the event.
	 *
	 * @param array<int, array{datetime_start: integer, datetime_end: integer, all_day:boolean}> $event_dates      Event dates.
	 * @param mixed                                                                              $hide_end_time    If we should hide the end time.
	 * @param mixed                                                                              $hide_start_time  If we should hide the start time.
	 * @param mixed                                                                              $display_timezone If we should display the timezone.
	 *
	 * @return string
	 */
	public function format_dates( array $event_dates ) {

		// Get the date count.
		$dates_count = count( $event_dates );

		// If there is only one date, return the single date.
		if ( 1 === $dates_count ) {
			return sprintf( '<ul id="se-event-date-list" class="se-event-date-list__single"><li>%s</li></ul>', $this->render_single_date( $event_dates[0] ) );
		}

		// Start building the output.
		$wrapper_class = array( 'se-event-date-list', $this->group_dates ? 'se-event-date-list__grouped' : '', $this->event_date_id ? 'se-event-date-list__active' : '' );
		$output        = sprintf( '<ul id="se-event-date-list" class="%s">', implode( ' ', $wrapper_class ) );

		// Loop over each date.
		foreach ( $event_dates as $date ) {
			$item_class = array( 'se-event-date-list-item', $date['id'] === $this->event_date_id ? 'se-event-date-list-item__active' : '' );
			$output    .= sprintf( '<li id="se-event-date-list-item-%s" class="%s">%s</li>', $date['id'], implode( ' ', $item_class ), $this->render_single_date( $date ) );
		}

		$output .= '</ul>';

		return $output;

		// Attempt to get the event date id from url.
		$event_date_id = se_template_get_event_date_id();

		$dates_count = is_array( $event_dates ) ? count( $event_dates ) : 1;

		if ( ! empty( $this->event_timezone ) ) {
			$timezone = new DateTimeZone( $this->event_timezone );
		} else {
			$timezone = wp_timezone();
		}

		$timezone_date = new DateTime( '', $timezone );
		$timezone_abbr = $timezone_date->format( 'T' );

		// Begin output as a list if the count is more than 1.
		$dates_output = ( $dates_count > 1 ) ? '<ul>' : '';

		// Get the start and end times from the first date.
		// Assume all start and end times are the same until proven otherwise.
		$event_time_start = wp_date( get_option( 'time_format' ), $event_dates[0]['start_date'], $timezone );
		$event_time_end   = wp_date( get_option( 'time_format' ), $event_dates[0]['end_date'], $timezone );
		$same_times       = ( 1 < $dates_count ) ? true : false;

		// Loop over each available event date.
		foreach ( $event_dates as $date ) {
			// dump( $this->render_single_date( $date ) );
			$opening_li = $date['id'] === $event_date_id ? '<li class="active" style="text-decoration: underline;">' : '<li>';

			// Check if start and end times are on the same day.
			$same_day = wp_date( 'Y-m-d', $date['start_date'], $timezone ) === wp_date( 'Y-m-d', $date['end_date'], $timezone );

			// Get start and end times.
			$time_start = ( $this->hide_start_time ) ? '' : wp_date( get_option( 'time_format' ), $date['start_date'], $timezone );
			$time_end   = ( $this->hide_end_time ) ? '' : wp_date( get_option( 'time_format' ), $date['end_date'], $timezone );

			$time_separator = ( 1 === (int) $this->hide_start_time ) ? '' : '&ndash;';

			// Invalidate same times if the start or end times don't match.
			if ( $same_times && ( $time_start !== $event_time_start || $time_end !== $event_time_end ) ) {
				$same_times = false;
			}

			// Ensure we're working with a boolean.
			$date['all_day'] = array_key_exists( 'all_day', $date ) ? filter_var( $date['all_day'], FILTER_VALIDATE_BOOLEAN ) : false;

			// Start the output string.
			$single_date_output = wp_date( get_option( 'date_format' ), $date['start_date'], $timezone );

			// // Return early if we only want the date.
			// if ( $this->date_only ) {
			//  $end_date         = wp_date( get_option( 'date_format' ), $date['end_date'], $timezone );
			//  $date_only_output = ( $same_day ) ? $single_date_output : $single_date_output . ' &ndash; ' . $end_date;
			//  $dates_output    .= ( $dates_count > 1 ) ? $opening_li . $date_only_output . '</li>' : $date_only_output;
			//  continue;
			// }

			// // Return early if we only want the time.
			// if ( $this->time_only ) {
			//  $time          = sprintf( '%s %s %s', $time_start, $time_separator, $time_end );
			//  $dates_output .= ( $dates_count > 1 ) ? $opening_li . $time . '</li>' : $time;
			//  continue;
			// }

			if ( ! $same_day ) {

				// If the event doesn't start and finish on the same day.
				$single_date_output .= sprintf(
					' %s &ndash; %s %s',
					$time_start,
					wp_date( get_option( 'date_format' ), $date['end_date'], $timezone ),
					$time_end
				);
			} elseif ( false === $date['all_day'] && $time_start !== $time_end ) {

				// Else if the event isn't all day, and the start and end times are different.
				$single_date_output .= sprintf( ' %s %s %s', $time_start, $time_separator, $time_end );
			}

			// Display timezone only after the first date.
			if ( $this->display_timezone && $date === $event_dates[0] ) {
				$single_date_output .= ' (' . $timezone_abbr . ')';
			}

			// Return output for this date.
			$dates_output .= ( $dates_count > 1 ) ? $opening_li . $single_date_output . '</li>' : $single_date_output;
		}

		// Overwrite output if all start and end times are the same and "Group event dates with matching times" otpion is selected.

		if ( $same_times && $this->group_dates ) {
			$event_date_start = wp_date( get_option( 'date_format' ), $event_dates[0]['datetime_start'], $timezone );
			$event_date_end   = wp_date( get_option( 'date_format' ), $event_dates[ $dates_count - 1 ]['datetime_start'], $timezone );

			$dates_output = ( $dates_count > 1 ) ? '<ul><li>' : '';

			$event_time_end = ( $this->hide_end_time ) ? '' : ' &ndash; ' . $event_time_end;

			// Don't display time if it's an all day event.
			if ( $date['all_day'] ) {
				$dates_output .= sprintf( ' %s &ndash; %s', $event_date_start, $event_date_end );
			} else {
				$dates_output .= sprintf( ' %s &ndash; %s %s %s', $event_date_start, $event_date_end, $event_time_start, $event_time_end );
			}

			if ( $this->display_timezone ) {
				$dates_output .= ' (' . $timezone_abbr . ')';
			}

			$dates_output .= ( $dates_count > 1 ) ? '</li>' : '';
		}

		$dates_output .= ( $dates_count > 1 ) ? '</ul>' : '';

		return $dates_output;
	}

	/**
	 * Get the posts timezone instance.
	 *
	 * @return DateTimeZone
	 */
	private function get_timezone_instance() {
		return $this->event_timezone !== '' ? new DateTimeZone( $this->event_timezone ) : wp_timezone();
	}

	/**
	 * Get the timezone abbreviation.
	 *
	 * @return string
	 */
	private function get_timezone_abbreviation() {
		$timezone_date = new DateTime( '', $this->get_timezone_instance() );
		return $timezone_date->format( 'T' );
	}

	/**
	 * Formats a date to the sites tiemzone and date format.
	 *
	 * @param integer     $date_timestamp The date timestamp.
	 * @param string|null $timezone       The timezone to use.
	 *
	 * @return string
	 */
	public function format_date( $date_timestamp, $timezone = null ) {
		return wp_date( get_option( 'date_format' ), $date_timestamp, $this->get_timezone_instance() );
	}

	/**
	 * Formats a time to the sites tiemzone and time format.
	 *
	 * @param integer     $time_timestamp The time timestamp.
	 * @param string|null $timezone       The timezone to use.
	 *
	 * @return string
	 */
	public function format_time( $time_timestamp, $timezone = null ) {
		return wp_date( get_option( 'time_format' ), $time_timestamp, $this->get_timezone_instance() );
	}

	/**
	 * Renders a single date.
	 *
	 * @param array $event_date The event date.
	 *
	 * @return string
	 */
	public function render_single_date( $event_date ) {
		// Check if the event starts and ends on the same day.
		$same_day = wp_date( 'Y-m-d', $event_date['start_date'], $this->get_timezone_instance() ) === wp_date( 'Y-m-d', $event_date['end_date'], $this->get_timezone_instance() );

		// Get start and end times.
		$time_start = ( $this->hide_start_time ) ? '' : $this->format_time( $event_date['start_date'] );
		$time_end   = ( $this->hide_end_time ) ? '' : $this->format_time( $event_date['end_date'] );

		// Get the start and end date.
		$start_date = $this->format_date( $event_date['start_date'] );
		$end_date   = $this->format_date( $event_date['end_date'] );

		// Check if it's an all day event.
		$is_all_day = array_key_exists( 'all_day', $event_date ) ? filter_var( $event_date['all_day'], FILTER_VALIDATE_BOOLEAN ) : false;

		// Start building the output.
		$output = $start_date;

		// Handle different cases based on whether it's same day, all day, etc.
		if ( $is_all_day ) {
			// For all day events, just show the date (or date range if different days).
			if ( ! $same_day ) {
				$output .= ' &ndash; ' . $end_date;
			}
		} elseif ( $same_day ) {
			// Same day event with times.
			$time_parts = array();
			if ( ! $this->hide_start_time && ! empty( $time_start ) ) {
				$time_parts[] = $time_start;
			}
			if ( ! $this->hide_end_time && ! empty( $time_end ) && $time_start !== $time_end ) {
				$time_parts[] = $time_end;
			}

			if ( ! empty( $time_parts ) ) {
				$output .= ' ' . implode( ' &ndash; ', $time_parts );
			}
		} else {
			// Multi-day event with times.
			if ( ! $this->hide_start_time && ! empty( $time_start ) ) {
				$output .= ' ' . $time_start;
			}
			$output .= ' &ndash; ' . $end_date;
			if ( ! $this->hide_end_time && ! empty( $time_end ) ) {
				$output .= ' ' . $time_end;
			}
		}

		// Add timezone if the option is set.
		if ( $this->display_timezone ) {
			$output .= ' (' . $this->get_timezone_abbreviation() . ')';
		}

		return $output;
	}
}
