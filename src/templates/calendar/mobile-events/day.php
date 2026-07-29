<?php
/**
 * Mobile Day
 *
 * @var array  $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="<?php echo esc_attr( simple_events_get_day_mobile_classes( $args['day'] ) ); ?>" id="<?php echo esc_attr( simple_events_get_mobile_day_id( $args['day'] ) ); ?>">
	<time class="simple-events-calendar-month-mobile-events__day-date-daynum" datetime="<?php echo esc_attr( $args['day']['date_formatted'] ); ?>">
		<?php echo esc_html( $args['day']['date']->format( 'F j' ) ); ?>
	</time>
	<hr>
	<?php
	foreach ( $args['day']['events'] as $simple_events_event ) {
		Simple_Events_Template_Loader::get_template_part(
			'calendar/day/event',
			null,
			true,
			array(
				'event'      => $simple_events_event,
				'attributes' => $args['attributes'],
			)
		);
	}
	?>
</div>
