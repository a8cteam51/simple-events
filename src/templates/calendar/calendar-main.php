<?php
/**
 * Calendar
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Arrow Position for Desktop
$simple_events_arrow_position = isset( $args['attributes']['arrowPosition'] ) ? $args['attributes']['arrowPosition'] : 'top';

// Arrow Position for Mobile
$simple_events_mobile_arrow_position = isset( $args['attributes']['mobileArrowPosition'] ) ? $args['attributes']['mobileArrowPosition'] : 'top';

?>

<div class="simple-events-container">

	<header class="simple-events-header">
		<?php
		Simple_Events_Template_Loader::get_template_part(
			'calendar/calendar',
			'top-bar',
			true,
			array(
				'current_date'   => $args['current_date'],
				'previous_date'  => $args['previous_date'],
				'next_date'      => $args['next_date'],
				'attributes'     => $args['attributes'],
				'arrow_position' => $simple_events_arrow_position,
			)
		);

		if ( 'top' === $simple_events_mobile_arrow_position ) {
			Simple_Events_Template_Loader::get_template_part(
				'calendar/calendar',
				'mobile-events',
				true,
				array(
					'days'          => $args['days'],
					'current_date'  => $args['current_date'],
					'previous_date' => $args['previous_date'],
					'next_date'     => $args['next_date'],
					'attributes'    => $args['attributes'],
				)
			);
		}
		?>
	</header>

	<div
		class="simple-events-calendar-month"
		role="grid"
		aria-labelledby="simple-events-calendar-header"
		aria-readonly="true"
	>
		<?php
		Simple_Events_Template_Loader::get_template_part(
			'calendar/calendar',
			'header'
		);
		?>
		<?php
		Simple_Events_Template_Loader::get_template_part(
			'calendar/calendar',
			'body',
			true,
			array(
				'days'             => $args['days'],
				'month_has_events' => $args['month_has_events'],
				'attributes'       => $args['attributes'],
			)
		);

		if ( 'bottom' === $simple_events_arrow_position ) {
			?>

			<div class="simple-events-top-bar">
			<?php
				Simple_Events_Template_Loader::get_template_part(
					'calendar/top-bar/nav',
					null,
					true,
					array(
						'previous_date' => $args['previous_date'],
						'next_date'     => $args['next_date'],
					)
				);
			?>
			</div> 
			<?php
		}
		?>
	</div>

	<?php

	if ( 'bottom' === $simple_events_mobile_arrow_position ) {
		Simple_Events_Template_Loader::get_template_part(
			'calendar/calendar',
			'mobile-events',
			true,
			array(
				'days'          => $args['days'],
				'current_date'  => $args['current_date'],
				'previous_date' => $args['previous_date'],
				'next_date'     => $args['next_date'],
				'attributes'    => $args['attributes'],
			)
		);
	}

	?>

</div>
