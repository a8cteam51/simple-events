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
		the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
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
	 * @return void
	 */
	function se_template_event_date() {
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
		?>
	<a href="<?php the_permalink(); ?>" rel="bookmark"><?php esc_html_e( 'More information', 'simple-events' ); ?></a>
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

		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
					'format'  => '?paged=%#%',
					'current' => max( 1, get_query_var( 'paged' ) ),
					'total'   => $wp_query->max_num_pages,
				)
			)
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

		// Get the current event start date/time.
		$event_start_date = get_post_meta( get_the_ID(), 'se_event_date_start', true );
		// If we have no start date, set with today's date.
		if ( empty( $event_start_date ) ) {
			$event_start_date = time();
		}

		$previous_event = se_event_get_previous_event( $event_start_date );
		$previous_link  = null === $previous_event
			? ''
			: sprintf(
				// translators: %1$s is the link to the previous event, %2$s is the title of the previous event.
				'<a href="%1$s" class="se-event-previous-link">%2$s</a>',
				esc_url( get_permalink( $previous_event->ID ) ),
				apply_filters( 'se_event_previous_link_text', esc_html( '<< ' . get_the_title( $previous_event->ID ) ), $previous_event )
			);

		$next_event = se_event_get_next_event( $event_start_date );
		$next_link  = null === $next_event
			? ''
			: sprintf(
				// translators: %1$s is the link to the next event, %2$s is the title of the next event.
				'<a href="%s" class="se-event-next-link">%s</a>',
				esc_url( get_permalink( $next_event->ID ) ),
				apply_filters( 'se_event_next_link_text', esc_html( get_the_title( $next_event->ID ) . ' >>' ), $next_event )
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
 * @param integer $timestamp The timestamp to get the next event from.
 *
 * @return WP_Post|null The next event or null if none found.
 */
function se_event_get_next_event( int $timestamp ): ?WP_Post {
	$query = new WP_Query(
		array(
			'previous_events' => false,
			'post_type'       => 'se-event',
			'posts_per_page'  => -1,
			'meta_query'      => array(
				array(
					'key'     => 'se_event_date_start',
					'value'   => absint( $timestamp ),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	if ( ! $query->have_posts() ) {
		return null;
	}

	$event = $query->posts[0];

	// Reset the post data.
	wp_reset_postdata();

	return $event;
}

/**
 * Gets the previous event based on a time stamp.
 *
 * @param integer $timestamp The timestamp to get the previous event from.
 *
 * @return WP_Post|null The previous event or null if none found.
 */
function se_event_get_previous_event( int $timestamp ): ?WP_Post {

		// Ensure previous events are sorted by Descending order.
		add_action(
			'pre_get_posts',
			function ( $wp_query ) {
				if ( array_key_exists( 'previous_events', $wp_query->query )
				&& $wp_query->query['previous_events']
				) {
					$wp_query->set( 'order', 'DESC' );
				}
			}
		);

		// Get the first previous event.
		$previous_events = new WP_Query(
			array(
				'previous_events' => true,
				'post_type'       => 'se-event',
				'posts_per_page'  => 1,
				'meta_query'      => array(
					array(
						'key'     => 'se_event_date_start',
						'value'   => absint( $timestamp ),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	if ( ! $previous_events->have_posts() ) {
		return null;
	}

	$event = $previous_events->posts[0];
	wp_reset_postdata();

	return $event;
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
		$show_on_frontend = get_post_meta( get_the_ID(), 'se_event_show_on_frontend', true );

		if ( empty( $show_on_frontend ) ) {
			return;
		}

		// Output the content for archive template.
		se_template_event_date();
		se_template_event_location();
		se_template_event_price();
		se_template_event_ticket_stock();
		the_excerpt();
	}
}
