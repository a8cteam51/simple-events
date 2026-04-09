<?php
/**
 * Navigation
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<nav class="simple-events-top-bar__nav simple-events-hidden-mobile">
	<ul class="simple-events-top-bar__nav-list">
		<li class="simple-events-top-bar__nav-list-item <?php echo esc_attr( $args['previous_date'] ? '' : 'disabled' ); ?>" data-js="simple-events-navigation-item" data-date="<?php echo esc_attr( $args['previous_date'] ); ?>">
			<a href="#" class="simple-events-top-bar__nav-link simple-events-top-bar__nav-link--next" aria-label="<?php esc_attr_e( 'Previous month', 'simple-events' ); ?>" title="<?php esc_attr_e( 'Previous month', 'simple-events' ); ?>" data-js="simple-events-view-link" rel="prev">
				<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
				</svg>
			</a>
		</li>
		<li class="simple-events-top-bar__nav-list-item <?php echo esc_attr( $args['next_date'] ? '' : 'disabled' ); ?>" data-js="simple-events-navigation-item" data-date="<?php echo esc_attr( $args['next_date'] ); ?>">
			<a href="#" class="simple-events-top-bar__nav-link simple-events-top-bar__nav-link--prev" aria-label="<?php esc_attr_e( 'Next month', 'simple-events' ); ?>" title="<?php esc_attr_e( 'Next month', 'simple-events' ); ?>" data-js="simple-events-view-link" rel="next">
				<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
				</svg>
			</a>
		</li>
	</ul>
</nav>
