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

<div class="<?php echo esc_attr( se_get_day_mobile_classes( $args['day'] ) ); ?>" id="<?php echo esc_attr( se_get_mobile_day_id( $args['day'] ) ); ?>">
	<time class="simple-events-calendar-month-mobile-events__day-date-daynum" datetime="<?php echo esc_attr( $args['day']['date_formatted'] ); ?>">
		<?php echo esc_html( $args['day']['date']->format( 'F j' ) ); ?>
	</time>
	<hr>
	<?php
	foreach ( $args['day']['events'] as $se_event ) {
		SE_Template_Loader::get_template_part(
			'calendar/day/event',
			null,
			true,
			array(
				'event'      => $se_event,
				'attributes' => $args['attributes'],
			)
		);
	}
	?>
</div>
