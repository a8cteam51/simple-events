<?php
/**
 * Simple Events Template Hooks
 *
 * Action/filter hooks used for plugin functions/templates.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global.
 */
add_action( 'se_before_main_content', 'se_template_content_wrapper_start' );
add_action( 'se_after_main_content', 'se_template_content_wrapper_end' );

/**
 * Archive.
 */
add_action( 'se_archive_content', 'se_template_event_thumbnail', 10 );
add_action( 'se_archive_content', 'se_template_event_archive_title', 20 );
add_action( 'se_archive_content', 'se_template_event_content', 30 );
add_action( 'se_archive_content', 'se_template_event_more_info', 40 );

add_action( 'se_archive_end', 'se_template_archive_pagination', 10 );

/**
 * Single.
 */
add_action( 'se_single_content', 'se_template_event_single_title', 10 );
add_action( 'se_single_content', 'se_expired_event_notice', 10 );
add_action( 'se_single_content', 'the_content', 20 );
add_action( 'se_single_content', 'se_template_calendar_links', 30 );
// Show the next and previous links either above or below content (based on settings).
add_action( 'se_single_content', 'se_template_event_next_previous', se_event_show_links_above_content() ? 40 : 15 );
