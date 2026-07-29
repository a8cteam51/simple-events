<?php
/**
 * Mobile Events
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="simple-events-calendar-month-mobile-events" data-js="simple-events-calendar-month-mobile-events">

	<?php foreach ( $args['days'] as $simple_events_day ) { ?>

		<?php
		if ( ! empty( $simple_events_day['events'] ) ) {
			Simple_Events_Template_Loader::get_template_part(
				'calendar/mobile-events/day',
				null,
				true,
				array(
					'day'        => $simple_events_day,
					'attributes' => $args['attributes'],
				)
			);
		}

		?>

	<?php } ?>

	<?php
	Simple_Events_Template_Loader::get_template_part(
		'calendar/mobile-events/nav',
		null,
		true,
		array(
			'current_date'  => $args['current_date'],
			'previous_date' => $args['previous_date'],
			'next_date'     => $args['next_date'],
		)
	);
	?>

</section>
