<?php
/**
 * Calendar Day
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$simple_events_hide_event = simple_events_hide_event( $args['attributes'], $args['day'] );

?>

<div class="<?php echo esc_attr( simple_events_get_day_element_classes( $args['day'] ) ); ?>" role="gridcell" aria-labelledby="<?php echo esc_attr( 'simple-events-calendar-day-' . $args['day']['date_formatted'] ); ?>" data-js="simple-events-calendar-day" data-mobile-control="<?php echo esc_attr( simple_events_get_mobile_day_id( $args['day'] ) ); ?>">
	<time class="simple-events-calendar-month__day-date-daynum" datetime="<?php echo esc_attr( $args['day']['date_formatted'] ); ?>">
		<?php echo esc_html( $args['day']['date']->format( 'j' ) ); ?>
	</time>
	<?php
	if ( ! empty( $args['day']['events'] ) && $args['attributes']['showDot'] && ! $simple_events_hide_event ) {
		?>
		<em class="simple-events-calendar-month__mobile-events-icon simple-events-hidden-desktop"
			aria-label="<?php esc_attr_e( 'Has Events', 'simple-events' ); ?>"
			title="<?php esc_attr_e( 'Has Events', 'simple-events' ); ?>">
		</em>
		<?php
	}

	if ( ! $simple_events_hide_event ) {
		Simple_Events_Template_Loader::get_template_part(
			'calendar/day/events',
			null,
			true,
			array(
				'day'        => $args['day'],
				'attributes' => $args['attributes'],
			)
		);
	}
	?>
</div>

