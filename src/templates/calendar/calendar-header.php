<?php
/**
 * Calendar Header
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_locale;
?>
<header class="simple-events-calendar-month__header" role="rowgroup">

	<div role="row" class="simple-events-calendar-month__header-row">
		<?php foreach ( Simple_Events_Calendar::get_instance()->simple_events_get_days_of_week() as $simple_events_day ) { ?>
			<div
				class="simple-events-calendar-month__header-column"
				role="columnheader"
				aria-label="<?php echo esc_attr( $simple_events_day ); ?>"
			>
				<h3 class="simple-events-calendar-month__header-column-title">
					<span class="simple-events-calendar-month__header-column-title simple-events-hidden-desktop">
						<?php echo esc_html( $wp_locale->get_weekday_initial( $simple_events_day ) ); ?>
					</span>
					<span class="simple-events-calendar-month__header-column-title simple-events-hidden-mobile">
						<?php echo esc_html( $wp_locale->get_weekday_abbrev( $simple_events_day ) ); ?>
					</span>
				</h3>
			</div>
		<?php } ?>
	</div>
</header>
