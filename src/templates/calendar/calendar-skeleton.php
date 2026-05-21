<?php
/**
 * Calendar Skeleton
 *
 * Loading skeleton for the calendar. Hidden by default, shown during API requests.
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="simple-events-calendar-skeleton simple-events-calendar-skeleton--hidden" aria-hidden="true" data-js="simple-events-calendar-skeleton">
	<div class="simple-events-container">
		<header class="simple-events-header">
			<div class="simple-events-calendar-skeleton__top-bar">
				<span></span>
				<span></span>
				<span></span>
			</div>
		</header>
		<div class="simple-events-calendar-skeleton__header">
			<?php for ( $i = 0; $i < 7; $i++ ) : ?>
				<span></span>
			<?php endfor; ?>
		</div>
		<div class="simple-events-calendar-skeleton__body">
			<?php for ( $week = 0; $week < 5; $week++ ) : ?>
				<div class="simple-events-calendar-skeleton__week">
					<?php for ( $day = 0; $day < 7; $day++ ) : ?>
						<div class="simple-events-calendar-skeleton__day"></div>
					<?php endfor; ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</div>
