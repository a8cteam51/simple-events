<?php
/**
 * Events
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$se_day_id = 'simple-events-calendar-day-' . $args['day']['date_formatted'];
?>

<div id="<?php echo esc_attr( $se_day_id ); ?>" class="simple-events-calendar-month__day-cell simple-events-calendar-month__day-cell--desktop simple-events-hidden-mobile">
	<div class="simple-events-calendar-month__events">
		<?php
		if ( $args['day']['events'] ) {
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
		}
		?>
	</div>
</div>
