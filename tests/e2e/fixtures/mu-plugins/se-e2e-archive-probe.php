<?php
/**
 * Plugin Name: Simple Events E2E — block probe
 * Description: Test fixture only. Renders one Simple Events block, chosen by URL
 *              parameter, on whatever page you load — so the same block can be
 *              compared on the events archive and on an ordinary page.
 *
 * Usage: add ?se-probe=<block> to a URL.
 *   ?se-probe=calendar
 *   ?se-probe=countdown
 *   ?se-probe=upcoming-events
 *   ?se-probe=query-loop
 *
 * GH-87: pre_get_posts() attaches posts_where, the_posts and posts_orderby
 * (class-se-event-post-type.php:642-652) and never detaches them, so anything
 * querying event dates later in that request inherits them.
 *
 * Hooks wp_footer at priority 5: late enough that the archive's main loop has
 * already run, which is the whole point, but ahead of wp_print_footer_scripts
 * at priority 20, so the block's own scripts still get printed. wp_body_open
 * would render it before the loop, which tests nothing.
 *
 * @package Simple_Events
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The blocks this probe can render, by URL parameter value.
 *
 * @return array<string, string>
 */
function se_e2e_probe_blocks() {
	$query_loop = '<!-- wp:query {"queryId":99,"query":{"perPage":10,"pages":0,"offset":0,"postType":"se-event","order":"asc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"inheritTaxQuery":true,"feedType":"default"},"namespace":"se-events/query-loop-events"} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- wp:simple-events/loop-event-info {"metaName":"date"} /-->
<!-- wp:simple-events/external-link /-->
<!-- wp:simple-events/past-events-notice /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->';

	return array(
		'calendar'        => '<!-- wp:simple-events/calendar /-->',
		'countdown'       => '<!-- wp:simple-events/countdown /-->',
		'upcoming-events' => '<!-- wp:simple-events/upcoming-events /-->',
		'query-loop'      => $query_loop,
	);
}

/**
 * Render the requested block, exactly as it would render in content.
 *
 * @return void
 */
function se_e2e_block_probe() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$requested = isset( $_GET['se-probe'] ) ? sanitize_key( wp_unslash( $_GET['se-probe'] ) ) : '';

	if ( '' === $requested ) {
		return;
	}

	$blocks = se_e2e_probe_blocks();

	if ( ! isset( $blocks[ $requested ] ) ) {
		return;
	}

	// One block per request. Rendering several together lets the first one's
	// filter changes carry into the next.
	// The wrapper is a bare div purely so the block's own output can be told
	// apart from whatever else the page already contains.
	echo '<div id="se-probe">';
	echo do_blocks( $blocks[ $requested ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
}
add_action( 'wp_footer', 'se_e2e_block_probe', 5 );
