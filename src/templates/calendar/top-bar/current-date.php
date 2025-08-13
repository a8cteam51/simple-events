<?php
/**
 * Current Date
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="simple-events-top-bar__month">
	<?php echo esc_html( se_get_current_month_year_from_date( $args['current_date'] ) ); ?>
</div>
