<?php
/**
 * Calendar Event
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$simple_events_event              = $args['event'];
$simple_events_attributes         = $args['attributes'];
$simple_events_event_modal_access = boolval( get_post_meta( $simple_events_event->ID, 'se_event_modal_access', true ) );
$simple_events_show_modal_title   = boolval( get_post_meta( $simple_events_event->ID, 'se_show_modal_title', true ) );
$simple_events_show_modal_excerpt = boolval( get_post_meta( $simple_events_event->ID, 'se_show_modal_excerpt', true ) );
$simple_events_show_no_thumbnail  = $simple_events_attributes['showModalWhenNoThumbnails'] ? true : has_post_thumbnail( $simple_events_event );
$simple_events_hide_start_time    = property_exists( $simple_events_event, 'hide_start_time' ) ? $simple_events_event->hide_start_time : false;
$simple_events_hide_end_time      = property_exists( $simple_events_event, 'hide_end_time' ) ? $simple_events_event->hide_end_time : false;


$simple_events_hide_css = '';
if ( $simple_events_hide_start_time ) {
	$simple_events_hide_css .= 'se-event-hide-start-time';
}
if ( $simple_events_hide_end_time ) {
	$simple_events_hide_css .= ' se-event-hide-end-time';
}
?>

<article class="simple-events-calendar-month__calendar-event <?php echo esc_attr( trim( $simple_events_hide_css ) ); ?>">
	<div class="simple-events-calendar-month__calendar-event-details">
		<div class="simple-events-calendar-month__calendar-event-datetime">
			<time datetime="<?php echo esc_attr( $simple_events_event->event_start_date->format( 'H:i' ) ); ?>">
				<?php echo esc_html( $simple_events_event->event_start_date->format( 'g:i a' ) ); ?>
			</time>
<!--			Todo if display end_time -->
			<?php
			if ( $simple_events_event->event_start_date < $simple_events_event->event_end_date ) {
				?>
				<span class="simple-events-calendar-month__calendar-event-datetime-separator"> - </span>
				<time datetime="<?php echo esc_attr( $simple_events_event->event_end_date->format( 'H:i' ) ); ?>">
					<?php echo esc_html( $simple_events_event->event_end_date->format( 'g:i a' ) ); ?>
				</time>
				<?php
			}
			?>
		</div>

		<h3 class="simple-events-calendar-month__calendar-event-title">
			<a
				href="<?php echo esc_url( simple_events_event_get_calendar_link( $simple_events_event->ID, $simple_events_event->event_date_id ) ); ?>"
				title="<?php echo esc_attr( get_the_title( $simple_events_event ) ); ?>"
				rel="bookmark"
				class="simple-events-calendar-month__calendar-event-title-link"
				<?php if ( property_exists( $simple_events_event, 'open_in_new_window' ) && true === (bool) $simple_events_event->open_in_new_window ) : ?>
					target="_blank"
				<?php endif; ?>
			>
				<?php
				echo esc_html( get_the_title( $simple_events_event ) );
				?>
			</a>
		</h3>
	</div>
</article>

<?php if ( $simple_events_attributes['eventModalAccess'] && $simple_events_event_modal_access && $simple_events_show_no_thumbnail ) : ?>
	<modal class="se-event-modal hidden">
		<div class="se-event-modal__image">
			<?php echo get_the_post_thumbnail( $simple_events_event ); ?>
		</div>
		<div class="se-event-modal__content">
			<?php if ( $simple_events_show_modal_title && $simple_events_attributes['showModalTitle'] ) : ?>
				<div class="se-event-modal__flex">
					<span class="dashicons dashicons-clock"></span>
					<h6 class="se-event-modal__date"><?php echo wp_kses_post( simple_events_event_get_formatted_dates( $simple_events_event->ID ) ); ?></h6>
				</div>
				<div class="se-event-modal__flex">
					<span class="dashicons dashicons-calendar"></span>
					<h6 class="se-event-modal__title"><?php echo wp_kses_post( $simple_events_event->post_title ); ?></h6>
				</div>
			<?php endif; ?>
			<?php if ( $simple_events_show_modal_excerpt && $simple_events_attributes['showModalExcerpt'] ) : ?>
				<p class="se-event-modal__excerpt"><?php echo wp_kses_post( $simple_events_event->post_excerpt ); ?></p>
			<?php endif; ?>
		</div>
	</modal>
<?php endif; ?>
