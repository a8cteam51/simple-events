<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Returns the alignment string based on the provided arguments.
 *
 * @param array $args An array containing the alignment information.
 *
 * @return string The alignment string.
 */
function se_alignment( $args ): string {
	$align = '';

	if ( ! empty( $args['align'] ) ) {
		$align = sprintf( 'align%s', $args['align'] );
	}

	return $align;
}

/**
 * Returns the current month and year from a date
 *
 * @param string $date Date string.
 *
 * @return string
 */
function se_get_current_month_year_from_date( $date ): string {
	try {
		$date_time = new DateTime( $date );

		return $date_time->format( 'F Y' );
	} catch ( Exception $e ) {
		return '';
	}
}

/**
 * Returns element classes based on particular day.
 *
 * @param array $day_data Day's data.
 *
 * @return string
 */
function se_get_day_element_classes( $day_data ): string {
	$classes = array( 'simple-events-calendar-month__day' );

	if ( $day_data['is_other_month'] ) {
		$classes[] = 'simple-events-calendar-month__day--other-month';
	}

	if ( $day_data['is_previous_month'] ) {
		$classes[] = 'simple-events-calendar-month__day--previous-month';
	}

	if ( $day_data['is_next_month'] ) {
		$classes[] = 'simple-events-calendar-month__day--next-month';
	}

	if ( $day_data['is_past'] ) {
		$classes[] = 'simple-events-calendar-month__day--past';
	}

	if ( $day_data['is_today'] ) {
		$classes[] = 'simple-events-calendar-month__day--today simple-events-calendar-month__day--active';
	}

	if ( ! $day_data['is_past'] ) {
		$classes[] = 'simple-events-calendar-month__day--upcoming';
	}

	if ( ! empty( $day_data['events'] ) ) {
		$classes[] = 'simple-events-calendar-month__day--has-events';
	}

	return implode( ' ', $classes );
}

/**
 * Returns element classes based on particular day.
 *
 * @param array $day_data Day's data.
 *
 * @return string
 */
function se_get_day_mobile_classes( $day_data ): string {

	$classes = array( 'simple-events-calendar-month-mobile-events__mobile-day' );

	if ( $day_data['is_today'] ) {
		$classes[] = 'simple-events-calendar-month-mobile-events__mobile-day--active';
	}

	return implode( ' ', $classes );
}

/**
 * Returns the mobile day id.
 *
 * @param array $day_data Day's data.
 *
 * @return string
 */
function se_get_mobile_day_id( $day_data ): string {
	return sprintf( 'simple-events-calendar-mobile__day-%s', $day_data['date_formatted'] );
}


/**
 * Determines if the event should be hidden based on the given block and day attributes.
 *
 * @param array $attributes The attributes of the event.
 * @param array $day        The day of the event.
 *
 * @return boolean Returns true if the event should be hidden, false otherwise.
 */
function se_hide_event( $attributes, $day ): bool {
	if ( ! $attributes['hideNeighbourEvents'] ) {
		return false;
	}

	return $day['is_previous_month'] || $day['is_next_month'];
}

/**
 * Verify if any of the attributes are valid colors.
 *
 * @param array  $attributes The attributes to be checked.
 * @param string $prefix     The prefix for the attribute keys.
 *
 * @return boolean Returns true if any of the attributes are valid colors, false otherwise.
 */
function se_verify_attributes( $attributes, $prefix ): bool {
	$text_color_bool   = isset( $attributes[ $prefix . 'Color' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Color' ] );
	$bg_color_bool     = isset( $attributes[ $prefix . 'Bg' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Bg' ] );
	$border_color_bool = isset( $attributes[ $prefix . 'Border' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Border' ] );

	if ( $text_color_bool || $bg_color_bool || $border_color_bool ) {
		return true;
	}

	return false;
}

/**
 * Generates the customized CSS for repeated elements based on the given attributes.
 *
 * @param array  $attributes      The attributes used to generate the CSS.
 * @param string $prefix          The prefix to use for attribute keys.
 * @param string &$customized_css The CSS string to append the generated CSS to.
 *
 * @return string The updated customized CSS string.
 */
function se_generate_repeated_css( $attributes, $prefix, &$customized_css ): string {

	if ( isset( $attributes[ $prefix . 'Color' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Color' ] ) ) {
		$customized_css .= sprintf(
			'.simple-events-calendar-month__calendar-event-title-link {
				color: %1$s;
			}
			color: %1$s;',
			sanitize_hex_color( $attributes[ $prefix . 'Color' ] )
		);
	}

	if ( isset( $attributes[ $prefix . 'Bg' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Bg' ] ) ) {
		$customized_css .= sprintf( 'background-color: %s;', sanitize_hex_color( $attributes[ $prefix . 'Bg' ] ) );
	}

	if ( isset( $attributes[ $prefix . 'Border' ] ) && sanitize_hex_color( $attributes[ $prefix . 'Border' ] ) ) {
		$customized_css .= sprintf( 'border-color: %s;', sanitize_hex_color( $attributes[ $prefix . 'Border' ] ) );
	}

	$customized_css .= "}}\n";

	return $customized_css;
}

/**
 * Applies customizations to the simple events calendar based on the provided attributes.
 *
 * @param array $attributes The attributes used to customize the calendar.
 *
 * @return string The customized CSS string.
 */
function se_apply_customization( $attributes ): string {

	$customized_css = '';

	if ( se_verify_attributes( $attributes, 'upcomingDays' ) ) {
		// Customization for Upcoming Days
		$customized_css .= '.simple-events-calendar {
			.simple-events-calendar-month__day--upcoming {';

		se_generate_repeated_css( $attributes, 'upcomingDays', $customized_css );
	}

	if ( se_verify_attributes( $attributes, 'eventDays' ) ) {
		// Customization for Event Days
		$customized_css .= '.simple-events-calendar {
			.simple-events-calendar-month__day--has-events {';

		se_generate_repeated_css( $attributes, 'eventDays', $customized_css );
	}

	if ( se_verify_attributes( $attributes, 'presentDay' ) ) {
		// Customization for Present Day
		$customized_css .= '.simple-events-calendar {
			.simple-events-calendar-month__day--active {';

		se_generate_repeated_css( $attributes, 'presentDay', $customized_css );
	}

	if ( se_verify_attributes( $attributes, 'pastDays' ) ) {
		// Customization for Past Days
		$customized_css .= '.simple-events-calendar {
			.simple-events-calendar-month__day--past {';

		se_generate_repeated_css( $attributes, 'pastDays', $customized_css );
	}

	if ( isset( $attributes['monthYearColor'] ) && sanitize_hex_color( $attributes['monthYearColor'] ) ) {
		// Customization for Month Year
		$customized_css .= sprintf(
			'.simple-events-top-bar,
			.simple-events-calendar .simple-events-top-bar__today-button {
				color: %s !important;
			}',
			esc_attr( $attributes['monthYearColor'] )
		);
	}

	if ( isset( $attributes['arrowColor'] ) && sanitize_hex_color( $attributes['arrowColor'] ) ) {
		// Customization for Arrow
		$customized_css .= sprintf(
			'.simple-events-calendar .simple-events-top-bar nav ul li a,
			.simple-events-calendar .simple-events-top-bar nav ul li svg,
			.simple-events-calendar .simple-events-mobile__nav-list-item a {
				color: %s;
			}',
			esc_attr( $attributes['arrowColor'] )
		);
	}

	if ( isset( $attributes['eventDotColor'] ) && sanitize_hex_color( $attributes['eventDotColor'] ) ) {
		// Customization for Arrow Hover
		$customized_css .= sprintf(
			'.simple-events-calendar .simple-events-calendar-month__mobile-events-icon {
				background-color: %s;
			}',
			esc_attr( $attributes['eventDotColor'] )
		);
	}

	if ( isset( $attributes['modalBgColor'] ) && sanitize_hex_color( $attributes['modalBgColor'] ) ) {
		// Customization for Modal
		$customized_css .= sprintf(
			'.simple-events-calendar .simple-events-calendar-month__events .se-event-modal {
				background-color: %s;
			}',
			esc_attr( $attributes['modalBgColor'] )
		);
	}

	if ( isset( $attributes['modalTextColor'] ) && sanitize_hex_color( $attributes['modalTextColor'] ) ) {
		// Customization for Modal
		$customized_css .= sprintf(
			'.simple-events-calendar .simple-events-calendar-month__events .se-event-modal,
			 .simple-events-calendar .simple-events-calendar-month__events .se-event-modal h6 {
				color: %s;
			}',
			esc_attr( $attributes['modalTextColor'] )
		);
	}

	if ( isset( $attributes['modalIconColor'] ) && sanitize_hex_color( $attributes['modalIconColor'] ) ) {
		// Customization for Modal
		$customized_css .= sprintf(
			'.simple-events-calendar .simple-events-calendar-month__events .se-event-modal .dashicons:before {
				color: %s;
			}',
			esc_attr( $attributes['modalIconColor'] )
		);
	}

	return $customized_css;
}
