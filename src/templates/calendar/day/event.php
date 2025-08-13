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

$se_event              = $args['event'];
$se_attributes         = $args['attributes'];
$se_event_modal_access = boolval( get_post_meta( $se_event->ID, 'se_event_modal_access', true ) );
$se_show_modal_title   = boolval( get_post_meta( $se_event->ID, 'se_show_modal_title', true ) );
$se_show_modal_excerpt = boolval( get_post_meta( $se_event->ID, 'se_show_modal_excerpt', true ) );
$se_show_no_thumbnail  = $se_attributes['showModalWhenNoThumbnails'] ? true : has_post_thumbnail( $se_event );
$se_hide_start_time    = property_exists( $se_event, 'hide_start_time' ) ? $se_event->hide_start_time : false;
$se_hide_end_time      = property_exists( $se_event, 'hide_end_time' ) ? $se_event->hide_end_time : false;


$se_hide_css = '';
if ( $se_hide_start_time ) {
	$se_hide_css .= 'se-event-hide-start-time';
}
if ( $se_hide_end_time ) {
	$se_hide_css .= ' se-event-hide-end-time';
}
?>

<article class="simple-events-calendar-month__calendar-event <?php echo esc_attr( trim( $se_hide_css ) ); ?>">
	<div class="simple-events-calendar-month__calendar-event-details">
		<div class="simple-events-calendar-month__calendar-event-datetime">
			<time datetime="<?php echo esc_attr( $se_event->event_start_date->format( 'H:i' ) ); ?>">
				<?php echo esc_html( $se_event->event_start_date->format( 'g:i a' ) ); ?>
			</time>
<!--			Todo if display end_time -->
			<?php
			if ( $se_event->event_start_date < $se_event->event_end_date ) {
				?>
				<span class="simple-events-calendar-month__calendar-event-datetime-separator"> - </span>
				<time datetime="<?php echo esc_attr( $se_event->event_end_date->format( 'H:i' ) ); ?>">
					<?php echo esc_html( $se_event->event_end_date->format( 'g:i a' ) ); ?>
				</time>
				<?php
			}
			?>
		</div>

		<h3 class="simple-events-calendar-month__calendar-event-title">
			<a
				href="<?php echo esc_url( se_event_get_calendar_link( $se_event->ID, $se_event->event_date_id ) ); ?>"
				title="<?php echo esc_attr( get_the_title( $se_event ) ); ?>"
				rel="bookmark"
				class="simple-events-calendar-month__calendar-event-title-link"
				<?php if ( property_exists( $se_event, 'open_in_new_window' ) && true === (bool) $se_event->open_in_new_window ) : ?>
					target="_blank"
				<?php endif; ?>
			>
				<?php
				echo esc_html( get_the_title( $se_event ) );
				?>
			</a>
		</h3>
	</div>
</article>

<?php if ( $se_attributes['eventModalAccess'] && $se_event_modal_access && $se_show_no_thumbnail ) : ?>
	<modal class="se-event-modal hidden">
		<div class="se-event-modal__image">
			<?php echo get_the_post_thumbnail( $se_event ); ?>
		</div>
		<div class="se-event-modal__content">
			<?php if ( $se_show_modal_title && $se_attributes['showModalTitle'] ) : ?>
				<div class="se-event-modal__flex">
					<span class="dashicons dashicons-clock"></span>
					<h6 class="se-event-modal__date"><?php echo wp_kses_post( se_event_get_formatted_dates( $se_event->ID ) ); ?></h6>
				</div>
				<div class="se-event-modal__flex">
					<span class="dashicons dashicons-calendar"></span>
					<h6 class="se-event-modal__title"><?php echo wp_kses_post( $se_event->post_title ); ?></h6>
				</div>
			<?php endif; ?>
			<?php if ( $se_show_modal_excerpt && $se_attributes['showModalExcerpt'] ) : ?>
				<p class="se-event-modal__excerpt"><?php echo wp_kses_post( $se_event->post_excerpt ); ?></p>
			<?php endif; ?>
		</div>
	</modal>
<?php endif; ?>
