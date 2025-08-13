<?php
/**
 * Mobile Today
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<li class="simple-events-mobile__nav-list-item simple-events-mobile__nav-list-item--today">
	<a
		href="#"
		class="simple-events-mobile__today-button simple-events-mobile__nav-link--today"
		data-js="simple-events-navigation-item"
		data-date="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>"
		aria-label="<?php esc_html_e( 'This Month', 'simple-events' ); ?>"
		title="<?php esc_html_e( 'This Month', 'simple-events' ); ?>"
	>
	<?php esc_html_e( 'This Month', 'simple-events' ); ?>
	</a>
</li>
