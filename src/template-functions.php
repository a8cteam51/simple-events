<?php
/**
 * Simple Events Templates
 *
 * Functions for the templating system.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks and get the event date id from url if set,
 *
 * @return integer|null The event date id or null if not set.
 */
function se_template_get_event_date_id() {
	$event_date_id = array_key_exists( 'se-date', $_GET ) ? sanitize_text_field( $_GET['se-date'] ) : null; // phpcs:ignore
	return is_numeric( $event_date_id ) ? absint( $event_date_id ) : null;
}

if ( ! function_exists( 'se_template_content_wrapper_start' ) ) {

	/**
	 * Output the start of the page wrapper.
	 *
	 * @return void
	 */
	function se_template_content_wrapper_start() {
		echo '<div id="primary" class="content-area"><main id="main" class="site-main" role="main">';
	}
}

if ( ! function_exists( 'se_template_content_wrapper_end' ) ) {

	/**
	 * Output the end of the page wrapper.
	 *
	 * @return void
	 */
	function se_template_content_wrapper_end() {
		echo '</main></div>';
	}
}

if ( ! function_exists( 'se_template_event_archive_title' ) ) {

	/**
	 * Output the event archive title.
	 *
	 * @return void
	 */
	function se_template_event_archive_title() {
		$permalink = get_permalink();

		global $post;
		// If we have an event date id, add to the permalink.
		if ( isset( $post->event_date_id ) ) {
			$permalink = add_query_arg( 'se-date', $post->event_date_id, $permalink );
		}

		the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( $permalink ) ), '</a></h2>' );
	}
}

if ( ! function_exists( 'se_template_event_single_title' ) ) {

	/**
	 * Output the event single title and past event notice.
	 *
	 * @return void
	 */
	function se_template_event_single_title() {

		the_title( '<h1 class="product_title entry-title">', '</h1>' );
	}
}

if ( ! function_exists( 'se_template_event_thumbnail' ) ) {

	/**
	 * Output the event thumbnail.
	 *
	 * @return void
	 */
	function se_template_event_thumbnail() {
		if ( ! has_post_thumbnail() ) {
			return;
		}
		?>
	<figure class="post-thumbnail">
		<a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'post-thumbnail' ); ?>
		</a>
	</figure>
		<?php
	}
}

if ( ! function_exists( 'se_template_event_date' ) ) {

	/**
	 * Output the event date and time.
	 *
	 * @deprecated 2.0.0 This has been replaced by the new date formatter class.
	 *
	 * @return void
	 */
	function se_template_event_date() {
		_doing_it_wrong( __FUNCTION__, 'Please use the new date formatter class instead.', '2.0.0' );

		$event_dates = se_event_get_dates( get_the_ID() );

		if ( ! empty( $event_dates ) ) {
			$output = false;

			if ( count( $event_dates ) > 1 ) {
				// Get first and last dates.
				$first_date = $event_dates[0];
				$last_date  = array_values( array_slice( $event_dates, -1 ) )[0];

				// Format dates.
				$first_date = wp_date( get_option( 'date_format' ), $first_date['datetime_start'] );
				$last_date  = wp_date( get_option( 'date_format' ), $last_date['datetime_start'] );

				// Output.
				$output = sprintf( '%s &ndash; %s', $first_date, $last_date );
			} else {
				$output = wp_date( get_option( 'date_format' ), $event_dates[0]['datetime_start'] );
			}

			if ( ! empty( $output ) ) {
				echo wp_kses_post( sprintf( '<div class="se-event-date">%s</div>', $output ) );
			}
		}
	}
}

if ( ! function_exists( 'se_template_event_location' ) ) {

	/**
	 * Output the event location.
	 *
	 * @return void
	 */
	function se_template_event_location() {
		$event_location = apply_filters( 'se_archive_event_location', se_event_get_location( get_the_ID() ) );

		if ( $event_location ) {
			echo wp_kses_post( sprintf( '<div class="se-event-location">%s</div>', $event_location ) );
		}
	}
}

if ( ! function_exists( 'se_template_event_price' ) ) {

	/**
	 * Output the event prices.
	 *
	 * @return void
	 */
	function se_template_event_price() {
		$output = '';

		// Get ticket products.
		$prices = se_event_get_ticket_prices( get_the_ID() );

		if ( ! empty( $prices ) ) {
			if ( count( $prices ) > 1 ) {
				// Sort prices.
				sort( $prices );

				// Get min / max price.
				$min_price = array_values( $prices )[0];
				$max_price = array_values( array_slice( $prices, -1 ) )[0];

				if ( $min_price !== $max_price ) {
					$output = wc_price( $min_price ) . ' - ' . wc_price( $max_price );
				} else {
					$output = wc_price( $min_price );
				}
			} else {
				$price  = array_values( $prices )[0];
				$output = wc_price( $price );
			}
		}

		// Output.
		if ( ! empty( $output ) ) {
			echo wp_kses_post( sprintf( '<div class="se-event-price">%s</div>', $output ) );
		}
	}
}

if ( ! function_exists( 'se_template_event_ticket_stock' ) ) {

	/**
	 * Output the event stock (number of tickets available).
	 *
	 * @return void
	 */
	function se_template_event_ticket_stock() {
		$stock_total = se_event_get_tickets_stock( get_the_ID() );

		if ( ! empty( $stock_total ) ) {
			echo wp_kses_post( sprintf( '<div class="se-event-stock">%s %s</div>', $stock_total, __( 'tickets left', 'simple-events' ) ) );
		}
	}
}

if ( ! function_exists( 'se_template_event_more_info' ) ) {

	/**
	 * Output the event more info link.
	 *
	 * @return void
	 */
	function se_template_event_more_info() {
		global $post;
		if ( se_event_treat_each_date_as_own_event() && isset( $post->event_date_id ) ) {
			$permalink = get_permalink( $post->post_parent ) . '?se-date=' . $post->event_date_id;
		} else {
			$permalink = get_permalink();
		}
		?>
	<a href="<?php echo esc_url( $permalink ); ?>" rel="bookmark"><?php esc_html_e( 'More information', 'simple-events' ); ?></a>
		<?php
	}
}

if ( ! function_exists( 'se_template_archive_pagination' ) ) {

	/**
	 * Output the archive paginaton.
	 *
	 * @return void
	 */
	function se_template_archive_pagination() {
		global $wp_query;

		$big = 999999999; // need an unlikely integer.

		$links = paginate_links(
			array(
				'base'    => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
				'format'  => '?paged=%#%',
				'current' => max( 1, get_query_var( 'paged' ) ),
				'total'   => $wp_query->max_num_pages,
			)
		);

		echo wp_kses_post(
			$links ?? ''
		);
	}
}


if ( ! function_exists( 'se_template_calendar_links' ) ) {

	/**
	 * Output the calendar export links.
	 *
	 * @param boolean $echo_output Whether to echo the output or return it.
	 *
	 * @return void|string
	 */
	function se_template_calendar_links( bool $echo_output = true ) {
		$event_id = get_the_ID();

		$links = array();

		// Retrieve custom download endpoint.
		$options = get_option( 'se_options' );
		$ep      = isset( $options['cal_download_endpoint'] ) ? $options['cal_download_endpoint'] : 'calendar';

		// Get iCal link for this event.
		$ical = untrailingslashit( get_feed_link( '/' . $ep . '?id=' . $event_id ) );

		// Google Calendar.
		if ( ! empty( $ical ) ) {
			$links[] = array(
				esc_html__( 'Google Calendar', 'simple-events' ),
				esc_url( 'https://www.google.com/calendar/render?cid=' . rawurlencode( str_replace( 'https://', 'http://', $ical ) ) ),
			);
		}

		// iCal.
		if ( ! empty( $ical ) ) {
			$links[] = array(
				esc_html__( 'iCal', 'simple-events' ),
				esc_url( $ical ),
			);
		}

		$links = apply_filters( 'se_template_calendar_links', $links );

		if ( ! empty( $links ) ) {
			$links_output = array();

			foreach ( $links as $link ) {
				$links_output[] = sprintf( '<a href="%s" target="_blank" rel="nofollow">%s</a>', $link[1], $link[0] );
			}

			$separator = apply_filters( 'se_template_calendar_links_separator', '<span class="se-event-calendar-links-separator">,</span> ' );
			$add_text  = apply_filters( 'se_template_calendar_add_text', esc_html__( 'Add this event to your calendar:', 'simple-events' ) );

			$output = wp_kses_post( sprintf( '<div class="se-event-calendar-export">%s %s</div>', $add_text, implode( $separator, $links_output ) ) );
		}

		// Check if we have output.
		if ( $echo_output ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $output;
		}
	}
}


if ( ! function_exists( 'se_template_event_next_previous' ) ) {
	/**
	 * Renders the next and previous links for an event.
	 *
	 * @return void
	 */
	function se_template_event_next_previous(): void {
		// If we are not rendering the links, bail.
		if ( ! se_event_show_next_previous() ) {
			return;
		}
		// Get the link to the calendar page.
		$calendar_page = se_event_get_calendar_page_link();

		$previous_event = se_event_get_previous_event( get_the_ID(), se_template_get_event_date_id() );
		$previous_link  = null === $previous_event
			? ''
			: sprintf(
				// translators: %1$s is the link to the previous event, %2$s is the title of the previous event.
				'<a href="%1$s" class="se-event-previous-link">%2$s</a>',
				esc_url( get_permalink( $previous_event->post_parent ) . '?se-date=' . $previous_event->ID ),
				apply_filters( 'se_event_previous_link_text', esc_html( '<< ' . get_the_title( $previous_event->post_parent ) ), $previous_event )
			);

		$next_event = se_event_get_next_event( get_the_ID(), se_template_get_event_date_id() );
		$next_link  = null === $next_event
			? ''
			: sprintf(
				// translators: %1$s is the link to the next event, %2$s is the title of the next event.
				'<a href="%s" class="se-event-next-link">%s</a>',
				esc_url( get_permalink( $next_event->post_parent ) . '?se-date=' . $next_event->ID ),
				apply_filters( 'se_event_next_link_text', esc_html( get_the_title( $next_event->post_parent ) . ' >>' ), $next_event )
			);

		$calendar_link = null !== $calendar_page
			? sprintf(
				// translators: %1$s is the link to the calendar page, %2$s is the title of the calendar page.
				'<a href="%1$s" class="se-event-calendar-link">%2$s</a>',
				esc_url( $calendar_page ),
				apply_filters( 'se_event_calendar_link_text', esc_html__( 'View Full Calendar', 'simple-events' ) ),
			)
			: '';

		$output = sprintf(
			'<div class="se-event-next-previous-links">
				<div>%1$s</div>
				<div>%2$s</div>
				<div>%3$s</div>
			</div>',
			$previous_link,
			$calendar_link,
			$next_link
		);

		print wp_kses(
			$output,
			array(
				'div' => array(
					'class' => array(),
				),
				'a'   => array(
					'href'  => array(),
					'class' => array(),
				),
			)
		);
	}
}


/**
 * Gets the next event based on a time stamp.
 *
 * @param integer      $event_id      The event ID to get the next event from.
 * @param integer|null $event_date_id The event date ID to get the next event from, if available.
 *
 * @return WP_Post|null The next event or null if none found.
 */
function se_event_get_next_event( int $event_id, ?int $event_date_id = null ): ?WP_Post {
	$options        = get_option( 'se_options' );
	$allow_grouping = isset( $options['treat_each_date_as_own_event'] ) ? 'on' === $options['treat_each_date_as_own_event'] : false;

	// If we dont have an event date id, we need to get the event dates.
	if ( ! $event_date_id ) {
		$event_dates = se_event_get_event_dates( $event_id );
		if ( empty( $event_dates ) ) {
			return null;
		}
		$event_date_id = $event_dates[0]['id'];
	}

	// Define the query to get next events.
	$args = array(
		'post_type'      => SE_Event_Post_Type::$event_date_post_type,
		'posts_per_page' => 1,
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'se_event_date_start',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => 'se_event_date_start',
				'value'   => get_post_meta( $event_date_id, 'se_event_date_start', true ),
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => 'se_event_hide_from_feed',
				'value'   => 1,
				'compare' => '!=',
			),
		),
	);

	// Ensure any events that are not published are not included in the query.
	$args['post__not_in'] = se_get_date_ids_for_non_published_events();

	// If we dont allow grouping, add the event id to parent not in.
	if ( ! $allow_grouping ) {
		$args['post__not_in'] = array_unique(
			array_merge(
				$args['post__not_in'],
				array_map( fn( array $date ): int => $date['id'], se_event_get_event_dates( $event_id ) )
			)
		);
	}

	$query = new WP_Query( $args );

	// If we have no posts, return null.
	if ( ! $query->have_posts() ) {
		return null;
	}

	// Get the first next event.
	$next_event = $query->posts[0];
	wp_reset_postdata();

	return $next_event;
}

/**
 * Gets the previous event based on a time stamp.
 *
 * @param integer      $event_id      The event ID to get the previous event from.
 * @param integer|null $event_date_id The event date ID to get the previous event from, if available.
 *
 * @return WP_Post|null The previous event or null if none found.
 */
function se_event_get_previous_event( int $event_id, ?int $event_date_id = null ): ?WP_Post {
	$options        = get_option( 'se_options' );
	$allow_grouping = isset( $options['treat_each_date_as_own_event'] ) ? 'on' === $options['treat_each_date_as_own_event'] : false;

	// If we dont have an event date id, we need to get the event dates.
	if ( ! $event_date_id ) {
		$event_dates = se_event_get_event_dates( $event_id );
		if ( empty( $event_dates ) ) {
			return null;
		}
		$event_date_id = $event_dates[0]['id'];
	}

	// Define the query to get previous events.
	$args = array(
		'post_type'      => SE_Event_Post_Type::$event_date_post_type,
		'posts_per_page' => 1,
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'se_event_date_start',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => 'se_event_date_start',
				'value'   => get_post_meta( $event_date_id, 'se_event_date_start', true ),
				'compare' => '<',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => 'se_event_hide_from_feed',
				'value'   => 1,
				'compare' => '!=',
			),
		),
	);

	// Ensure any events that are not published are not included in the query.
	$args['post__not_in'] = se_get_date_ids_for_non_published_events();

	// If we dont allow grouping, add the event id to parent not in.
	if ( ! $allow_grouping ) {
		$args['post__not_in'] = array_unique(
			array_merge(
				$args['post__not_in'],
				array_map( fn( array $date ): int => $date['id'], se_event_get_event_dates( $event_id ) )
			)
		);
	}

	$query = new WP_Query( $args );

	// If we have no posts, return null.
	if ( ! $query->have_posts() ) {
		return null;
	}

	// Get the first previous event.
	$previous_event = $query->posts[0];
	wp_reset_postdata();

	return $previous_event;
}

if ( ! function_exists( 'se_get_date_ids_for_non_published_events' ) ) {

	/**
	 * Return an array of all event dates, where the parent is not published.
	 *
	 * @since 2.0.4
	 *
	 * @return int[]
	 */
	function se_get_date_ids_for_non_published_events() {
		static $dates = null;
		if ( is_array( $dates ) ) {
			return $dates;
		}

		// Get all events that not published (draft or pending or private).
		$args        = array(
			'post_type'      => SE_Event_Post_Type::$post_type,
			'post_status'    => array_diff( get_post_stati(), array( 'publish' ) ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$draft_dates = get_posts( $args );

		$dates = array();

		foreach ( $draft_dates as $draft_date ) {
			// Get all dates for this event.
			$event_dates = se_event_get_event_dates( $draft_date );
			if ( ! empty( $event_dates ) ) {
				foreach ( $event_dates as $date ) {
					$dates[] = $date['id'];
				}
			}
		}
		return $dates;
	}
}

if ( ! function_exists( 'se_expired_event_notice' ) ) {
	/**
	 * Output the expired event notice.
	 *
	 * @return void
	 */
	function se_expired_event_notice() {
		$options = get_option( 'se_options' );

		// If event is expired and option is enabled, display expired event notice.
		if ( se_event_is_expired( get_the_ID() ) ) {
			$value = isset( $options['past_event_notice'] ) ? $options['past_event_notice'] : esc_html__( 'Event has passed', 'simple-events' );
			printf( '<p class="past-event-notice">%s</p>', esc_html( $value ) );
		}
	}
}

if ( ! function_exists( 'se_template_event_content' ) ) {
	/**
	 * Events Content Template for Events Feed Block.
	 *
	 * @return void
	 */
	function se_template_event_content() {
		global $post;
		$show_on_frontend = get_post_meta( get_the_ID(), 'se_event_show_on_frontend', true );
		if ( empty( $show_on_frontend ) ) {
			return;
		}

		$date_display_formatter = new SE_Date_Display_Formatter( get_the_ID() );
		$dates                  = se_event_get_event_dates( get_the_ID() );

		// If we have an event date and we treating each date as own event, we need to get the event date id.
		if ( se_event_treat_each_date_as_own_event() && isset( $post->event_date_id ) ) {
			$dates = array_filter(
				$dates,
				function ( $date ) use ( $post ) {
					return $date['id'] === $post->event_date_id;
				}
			);

			$dates = array_values( $dates );
		} else {
			$date_display_formatter->set_date_only( true );
		}
		// Output the content for archive template.
		echo wp_kses_post( $date_display_formatter->get_header_date( $dates ) );
		se_template_event_location();
		se_template_event_price();
		se_template_event_ticket_stock();
		the_excerpt();
	}
}


if ( ! function_exists( 'se_fix_se_events_fse_archive_template' ) ) {
	/**
	 * Fix the template hierarchy for the SE Events FSE archive.
	 *
	 * @param array $templates The template hierarchy.
	 *
	 * @return array The modified template hierarchy.
	 */
	function se_fix_se_events_fse_archive_template( $templates ) {
		if ( 'se-event-date' === get_query_var( 'post_type' ) ) {
			// Create proper hierarchy: archive-se-events.html, then archive.html
			$custom_hierarchy = array(
				'archive-se-event.html',
				'archive.html',
			);

			return $custom_hierarchy;
		}
		return $templates;
	}
}

// Filter to modify the body class for the event date archive.
if ( ! function_exists( 'se_modify_event_date_archive_body_class' ) ) {
	/**
	 * Modify the body class for the event date archive.
	 *
	 * @param array $classes The existing body classes.
	 *
	 * @return array The modified body classes.
	 */
	function se_modify_event_date_archive_body_class( $classes ) {
		$classes = array_map(
			function ( $body_class ) {
				return 'post-type-archive-se-event-date' === $body_class ? 'post-type-archive-se-event' : $body_class;
			},
			$classes
		);
		return $classes;
	}
}

// Modify the archive page title.
if ( ! function_exists( 'se_modify_event_date_archive_template_title' ) ) {
	/**
	 * Modify the archive page title for the event date archive.
	 *
	 * @param string $title The existing archive title.
	 *
	 * @return string The modified archive title.
	 */
	function se_modify_event_date_archive_template_title( $title ) {
		if ( is_post_type_archive( 'se-event-date' ) ) {
			$original_title = $title;
			// Get the se-event post type object to use its archive title
			$post_type_obj = get_post_type_object( 'se-event' );

			// If this is a post_typw archive, use the post type archive title.
			if ( is_post_type_archive() ) {
				$title  = apply_filters( 'post_type_archive_title', $post_type_obj->labels->name, 'se-event' ); // phpcs:ignore
				$prefix = _x( 'Archives:', 'post type archive title prefix' ); // phpcs:ignore
			} elseif ( is_tax() && $post_type_obj ) {
				$tax    = get_taxonomy( $post_type_obj->taxonomy );
				$title  = single_term_title( '', false );
				$prefix = sprintf(
				/* translators: %s: Taxonomy singular name. */
					_x( '%s:', 'taxonomy term archive title prefix' ), // phpcs:ignore
					$tax->labels->singular_name
				);
			} else {
				$prefix = '';
			}

			/**
			 * Filters the archive title prefix.
			 *
			 * @since 5.5.0
			 *
			 * @param string $prefix Archive title prefix.
			 */
			$prefix = apply_filters( 'get_the_archive_title_prefix', $prefix ); // phpcs:ignore
			if ( $prefix ) {
				$title = sprintf(
					/* translators: 1: Title prefix. 2: Title. */
					_x( '%1$s %2$s', 'archive title' ), // phpcs:ignore
					$prefix,
					'<span>' . $title . '</span>'
				);
			}
		}
		return $title;
	}
}



// Modify the page HTML title for the event date archive.
if ( ! function_exists( 'se_modify_event_date_archive_page_title' ) ) {
	/**
	 * Modify the page HTML title for the event date archive.
	 *
	 * @param string $title The existing page title.
	 *
	 * @return string The modified page title.
	 */
	function se_modify_event_date_archive_page_title( $title ) {
		if ( is_post_type_archive( 'se-event-date' ) ) {
			$event_post_type = get_post_type_object( 'se-event' );
			$title           = $event_post_type->labels->name;
		} elseif ( is_tax() ) {
			$taxonomy = get_queried_object();
			if ( $taxonomy ) {
				$title = $taxonomy->name;
			}
		}
		return $title;
	}
}
/**
 * Ensure that legacy themes will call the se-event archive template.
 *
 * @since 2.0.0
 *
 * @param string $template The template file to use.
 *
 * @return string The modified template file.
 */
function se_event_archive_template( $template ) {
	if ( is_post_type_archive( 'se-event-date' ) ) {
		// If the template is not set, use the default archive template.
		$date_archive_template = locate_template( 'archive-se-event.php' );
		if ( $date_archive_template ) {
			return $date_archive_template;
		}
	}
	return $template;
}
