<?php
/**
 * Today
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<a
	href="#"
	class="simple-events-top-bar__today-button simple-events-hidden-mobile"
	data-js="simple-events-navigation-item"
	data-date="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>"
	aria-label="<?php echo esc_html__( 'This Month', 'simple-events' ); ?>"
	title="<?php echo esc_html__( 'This Month', 'simple-events' ); ?>"
>
<?php echo esc_html__( 'This Month', 'simple-events' ); ?>
</a>
