<?php
/**
 * Calendar Body
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="simple-events-calendar-month__body" role="rowgroup">

	<?php
	if ( $args['month_has_events'] ) {
		foreach ( array_chunk( $args['days'], 7, true ) as $se_week ) {
			?>

			<div class="simple-events-calendar-month__week" role="row">

				<?php foreach ( $se_week as $se_day ) { ?>

					<?php
					SE_Template_Loader::get_template_part(
						'calendar/day/day',
						null,
						true,
						array(
							'day'        => $se_day,
							'attributes' => $args['attributes'],
						)
					);
					?>

				<?php } ?>

			</div>

			<?php
		}
	} else {
		?>
		<p class="simple-events-calendar-month__no-events">
			<?php esc_html_e( 'No Events Scheduled', 'simple-events' ); ?>
		</p>
		<?php
	}
	?>

</div>
