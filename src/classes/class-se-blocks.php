<?php
/**
 * Event Blocks.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks Class.
 */
class SE_Blocks {
	/**
	 * Blocks namespace.
	 *
	 * @var string
	 */
	protected static $namespace = 'simple-events';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		// Load assets if `build` folder exists.
		if ( file_exists( SE_PLUGIN_DIR . '/build' ) ) {
			add_action( 'block_categories_all', array( __CLASS__, 'block_categories' ), 10, 2 );
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_assets' ), 10 );
			add_action( 'init', array( __CLASS__, 'register_block_type' ), 10 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_inline_block_styles' ), 10 );
		} else {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
						<p>
							<?php
							printf(
								/* translators: 1: composer command. 2: plugin directory */
								esc_html__( 'Your installation of the Simple Events plugin is incomplete. Please run %1$s and %2$s within the %3$s directory.', 'simple-events' ),
								'<code>npm install</code>',
								'<code>npm run build</code>',
								'<code>' . esc_html( str_replace( ABSPATH, '', SE_PLUGIN_DIR ) ) . '</code>'
							);
							?>
						</p>
					</div>
					<?php
				}
			);
		}
	}

	/**
	 * Enqueues inline block styles for the simple events calendar block with its attributes.
	 *
	 * @return void
	 */
	public static function enqueue_inline_block_styles() {
		$post_id = get_the_ID();
		if ( has_block( 'simple-events/calendar', $post_id ) ) {
			$all_blocks     = parse_blocks( get_the_content( $post_id ) );
			$calendar_block = array_search( 'simple-events/calendar', array_column( $all_blocks, 'blockName' ), true );
			wp_add_inline_style( 'simple-events-calendar-style', esc_html( se_apply_customization( $all_blocks[ $calendar_block ]['attrs'] ) ) );
		}
	}

	/**
	 * Adds the "simple events" block category to the list of available categories.
	 *
	 * @param array $categories Array of block categories.
	 *
	 * @return array
	 */
	public static function block_categories( $categories ): array {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'simple-events',
					'title' => esc_html__( 'Simple Events', 'simple-events' ),
				),
			)
		);
	}

	/**
	 * Enqueue block assets.
	 *
	 * @return void
	 */
	public static function block_assets() {

		// JS globals.
		$block_settings = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$product_counts = wp_count_posts( 'product' );

			$block_settings['isWCActive']     = true;
			$block_settings['isLargeCatalog'] = $product_counts->publish > 200;
			$block_settings['productCount']   = $product_counts->publish;

			if ( class_exists( 'WC_Box_Office' ) ) {
				$block_settings['isBOActive'] = true;
			}

			if ( function_exists( 'wc_box_office_ticket_field_types' ) ) {
				$block_settings['BOTicketFieldTypes'] = wc_box_office_ticket_field_types();
			}
		}

		$options = get_option( 'se_options' );
		$value   = isset( $options['past_event_notice'] ) ? $options['past_event_notice'] : esc_html__( 'Event has passed', 'simple-events' );

		// For Past events notice block.
		$block_settings['pastEventsNotice'] = $value;
		$block_settings['postType']         = get_post_type();

		// Pass through new event data information.
		$block_settings['eventVersion']      = SE_Event_Post_Type::$current_event_version;
		$block_settings['eventDatePostType'] = SE_Event_Post_Type::$event_date_post_type;
		$block_settings['syncDatesNonce']    = wp_create_nonce( 'se_event_nonce' );

		wp_localize_script(
			'wp-blocks',
			'seSettings',
			$block_settings
		);

		if ( file_exists( SE_PLUGIN_DIR . '/build/variations/index.asset.php' ) ) {
			$variations = include_once SE_PLUGIN_DIR . '/build/variations/index.asset.php';

			wp_enqueue_script(
				'se-block-variations',
				SE_PLUGIN_URL . '/build/variations/index.js',
				$variations['dependencies'],
				$variations['version'],
				true
			);
		}
	}

	/**
	 * Register blocks.
	 *
	 * @return void
	 */
	public static function register_block_type() {
		// Event Info.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/event-info',
			array(
				'render_callback' => array( __CLASS__, 'event_info_render' ),
			)
		);

		// Event Tickets.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/event-tickets',
			array(
				'render_callback' => array( __CLASS__, 'event_tickets_render' ),
			)
		);

		// Inner Blocks.
		register_block_type( SE_PLUGIN_DIR . '/build/blocks/inner-blocks' );

		// Upcoming Events.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/upcoming-events',
			array(
				'render_callback' => array( __CLASS__, 'upcoming_events_render' ),
			)
		);

		// Next Event Countdown.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/countdown',
			array(
				'render_callback' => array( __CLASS__, 'countdown_render' ),
			)
		);

		// Calendar View.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/calendar',
			array(
				'render_callback' => array( __CLASS__, 'calendar_render' ),
			)
		);

		// Event meta in query loop.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/loop-event-info',
			array(
				'render_callback' => array( __CLASS__, 'loop_event_info_render' ),
			)
		);

		// Event external links.
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/external-link',
			array(
				'render_callback' => array( __CLASS__, 'loop_event_external_link_render' ),
			)
		);

		// Past Events Notice
		register_block_type(
			SE_PLUGIN_DIR . '/build/blocks/past-events-notice',
			array(
				'render_callback' => array( __CLASS__, 'past_events_notice_render' ),
			)
		);
	}


	/**
	 * Render event info block.
	 *
	 * @param array  $attributes The attributes for the event.
	 * @param string $content    The content of the event.
	 * @param object $block      The block object.
	 *
	 * @return HTML The rendered event information.
	 */
	public static function event_info_render( $attributes, $content, $block ) {

		// Check if we're looking at the block on the front-end.
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			// If yes, check if we're supposed to show the block on the front-end.
			if ( false === $attributes['showOnFrontEnd'] ) {
				return '';
			}
		}

		$post_ID = isset( $block->context['postId'] ) ? $block->context['postId'] : get_the_ID();

		$output = '';

		// Event time / date.
		$event_dates = get_post_meta( $post_ID, 'se_event_dates', true );

		// Previewing?
		if ( ! empty( $attributes['eventDates'] ) ) {
			$event_dates = $attributes['eventDates'];
		}

		// Set up timezone. Defaults to site settings if the post has no timezone meta.
		$event_timezone = get_post_meta( $post_ID, 'se_event_timezone', true );

		// Previewing?
		if ( isset( $attributes['eventTimezone'] ) ) {
			$event_timezone = $attributes['eventTimezone'];
		}

		$dates_output = '';

		if ( ! empty( $event_dates ) ) {
			$dates_count  = count( $event_dates );
			$date_heading = '<h3>' . _n( 'Date', 'Dates', $dates_count, 'simple-events' ) . '</h3>';

			/**
			 * Filter the markup used for the date heading.
			 *
			 * @param string $date_heading The HTML used to display the date heading.
			 * @param int    $dates_count The number of event dates.
			 */
			$dates_output .= apply_filters( 'se_event_info_date_heading', $date_heading, $dates_count );
			$dates_output .= se_event_get_formatted_dates( $post_ID, false, false, $event_dates );
		}

		// Event location.
		$event_location = get_post_meta( $post_ID, 'se_event_location', true );
		$event_venue    = get_post_meta( $post_ID, 'se_event_venue', true );

		$event_link       = get_post_meta( $post_ID, 'se_event_external_link', true );
		$event_link_label = get_post_meta( $post_ID, 'se_event_external_link_label', true );

		// Previewing?
		if ( isset( $attributes['eventLocation'] ) ) {
			$event_location = $attributes['eventLocation'];
		}

		if ( isset( $attributes['eventVenue'] ) ) {
			$event_venue = $attributes['eventVenue'];
		}

		// Previewing?
		if ( isset( $attributes['externalLink'] ) ) {
			$event_link = $attributes['externalLink'];
		}

		if ( isset( $attributes['externalLinkLabel'] ) ) {
			$event_link_label = $attributes['externalLinkLabel'];
		}

		if ( ! empty( $dates_output ) ) {
			$output .= '<div class="wp-block-se-event-info">';

			if ( ! empty( $dates_output ) ) {
				$output .= $dates_output;
			}

			if ( ! empty( $event_venue ) ) {
				$output .= '<h3>' . __( 'Venue', 'simple-events' ) . '</h3>';
				$output .= '<p>' . wp_kses_post( $event_venue ) . '</p>';
			}

			if ( ! empty( $event_location ) ) {
				$output .= '<h3>' . __( 'Location', 'simple-events' ) . '</h3>';
				$output .= '<p>' . wp_kses_post( $event_location ) . '</p>';
			}

			if ( ! empty( $event_link ) ) {
				$cta = apply_filters( 'se_event_external_link_text', $event_link_label, $event_link );

				$output .= '<p><a class="wp-block-se-event-link" href="' . esc_url( $event_link ) . '" target="_blank" rel="nofollow">' . $cta . '</a></p>';
			}

			$output .= '</div>';
		}

		// Add "Add to calendar links" if the attribute is set to true.
		$calendar_links = get_post_meta( $post_ID, 'se_event_add_calendar_links', true );
		if ( $calendar_links ) {
			$output .= se_template_calendar_links( false );
		}

		return apply_filters( 'simple_events_event_info_render', 'TODO' . $output, $event_dates, $event_timezone, $event_location, $attributes );
	}

	/**
	 * Render event tickets block.
	 *
	 * @param array $attributes The attributes for the event.
	 *
	 * @return HTML The rendered event information.
	 */
	public static function event_tickets_render( $attributes = array() ) {
		$output = '';

		if ( ! empty( $attributes['selected'] ) ) {
			$output .= '<div class="wp-block-se-event-tickets">';
			$output .= '<div class="wp-block-se-event-tickets__wrapper">';
			$output .= '<h3 class="wp-block-se-event-tickets__heading">' . __( 'Tickets', 'simple-events' ) . '</h3>';

			$available_tickets   = '';
			$unavailable_tickets = '';

			foreach ( $attributes['selected'] as $product ) {
				$product = wc_get_product( (int) $product );

				if ( ! $product ) {
					continue;
				}

				if ( $product->is_type( 'variable' ) ) {
					$variations = $product->get_available_variations();

					foreach ( $variations as $variation ) {
						$variation = wc_get_product( $variation['variation_id'] );

						if ( ! $variation ) {
							continue;
						}

						$ticket_render = self::render_ticket( $variation );

						if ( $ticket_render['available'] ) {
							$available_tickets .= $ticket_render['output'];
						} else {
							$unavailable_tickets .= $ticket_render['output'];
						}
					}
				} else {
					$ticket_render = self::render_ticket( $product );

					if ( $ticket_render['available'] ) {
						$available_tickets .= $ticket_render['output'];
					} else {
						$unavailable_tickets .= $ticket_render['output'];
					}
				}
			}

			$output .= $available_tickets;
			$output .= $unavailable_tickets;
			$output .= '</div>';
			$output .= '</div>';
		}

		return apply_filters( 'simple_events_event_tickets_render', $output, $attributes );
	}

	/**
	 * Render single ticket.
	 *
	 * @param WC_Product $product WooCommerce product.
	 *
	 * @return array
	 */
	private static function render_ticket( $product ) {

		$name  = $product->get_name();
		$price = wc_price( $product->get_price() );
		$stock = ( $product->managing_stock() ? $product->get_stock_quantity() : false );

		$available = 'outofstock' !== $product->get_stock_status() && ! empty( $stock ) && 0 < $stock;

		$row_class  = 'wp-block-se-event-tickets__ticket-row';
		$row_class .= ( $available ) ? '' : ' wp-block-se-event-tickets__ticket-row--unavailable';

		$product_output  = '<div class="' . $row_class . '">';
		$product_output .= '<div class="wp-block-se-event-tickets__ticket-column wp-block-se-event-tickets__ticket-column--title">' . $name . '</div>';

		$product_output .= '<div class="wp-block-se-event-tickets__ticket-column wp-block-se-event-tickets__ticket-column--price">';
		$product_output .= $price;

		/* translators: %s: number tickets in stock */
		$product_output .= '<span class="wp-block-se-event-tickets__ticket-stock">' . sprintf( __( '%s available', 'simple-events' ), 0 < $stock ? $stock : 0 ) . '</span>';
		$product_output .= '</div>';

		$product_output .= '<div class="wp-block-se-event-tickets__ticket-column wp-block-se-event-tickets__ticket-column--buy">';

		if ( $available ) {
			$attributes = array(
				/* translators: %s: event title */
				'aria-label' => sprintf( __( 'Buy ticket "%s"', 'simple-events' ), $product->get_name() ),
				'rel'        => 'nofollow',
				'class'      => 'wp-block-se-event-tickets__button button add_to_cart_button',
			);

			$button = sprintf(
				'<a href="%s" %s>%s</a>',
				esc_url( $product->get_permalink() ),
				wc_implode_html_attributes( $attributes ),
				esc_html( __( 'Buy Ticket', 'simple-events' ) )
			);

			$product_output .= $button;
		} else {
			$product_output .= __( 'No longer available for sale', 'simple-events' );
		}

		$product_output .= '</div>';
		$product_output .= '</div>';

		return array(
			'available' => $available,
			'output'    => $product_output,
		);
	}

	/**
	 * Render upcoming events block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 *
	 * @return HTML Upcoming events render.
	 */
	public static function upcoming_events_render( $attributes = array(), $content = '' ) {
		$events_query_args = array();
		$events_query      = null;
		$output            = '';

		if ( ! empty( $attributes['count'] ) ) {

			// By default shows the "mixed" feed type (no meta_query).
			$events_query_args = array(
				'post_type'      => SE_Event_Post_Type::$post_type,
				'post_status'    => 'publish',
				'posts_per_page' => absint( $attributes['count'] ),
			);

			// Handle "future events" feed type
			if ( 'upcoming' === $attributes['feedType'] ) {
				$events_query_args['meta_query'] = array(
					array(
						'key'     => 'se_event_date_end',
						'value'   => wp_date( 'U' ),
						'compare' => '>=',
					),
				);
			}

			// Handle "past events" feed type
			if ( 'past' === $attributes['feedType'] ) {
				$events_query_args['meta_query'] = array(
					array(
						'key'     => 'se_event_date_end',
						'value'   => wp_date( 'U' ),
						'compare' => '<=',
					),
				);
			}

			// Handle "range" feed type
			if (
				'range' === $attributes['feedType']
				&& ! empty( $attributes['dateRange']['from'] )
				&& ! empty( $attributes['dateRange']['to'] )
			) {
				$events_query_args['meta_query'] = array(
					array(
						'key'     => 'se_event_date_start',
						'value'   => strtotime( $attributes['dateRange']['from'] ),
						'compare' => '>=',
					),
					array(
						'key'     => 'se_event_date_end',
						'value'   => strtotime( $attributes['dateRange']['to'] ),
						'compare' => '<=',
					),
				);
			}

			// If feed order is overridden, set the order to custom query var.
			if ( ! empty( $attributes['overrideFeedOrder'] ) ) {
				$events_query_args['se_event_order'] = $attributes['feedOrder'];
			}

			$show_year_dividers = ! empty( $attributes['showYearDividers'] );

			$events_query = new \WP_Query( $events_query_args );

			if ( $events_query->have_posts() ) {
				$container_class[] = 'wp-block-se-upcoming-events';
				$container_class[] = 'wp-block-se-upcoming-events-view-' . $attributes['layout'];
				$container_class[] = 'wp-block-se-upcoming-events-columns-' . $attributes['columns'];
				$container_class[] = 'align' . $attributes['align'];
				$container_class[] = ( ! empty( $attributes['className'] ) ) ? $attributes['className'] : '';
				$current_year      = '';

				$output .= '<div class="' . esc_attr( implode( ' ', $container_class ) ) . '">';
				$output .= '<ul class="simple-events-archive">';

				while ( $events_query->have_posts() ) {
					$events_query->the_post();

					if ( $show_year_dividers ) {
						$year_output  = self::get_year_divider( get_the_ID(), $current_year );
						$current_year = $year_output['current_year'];
						$output      .= $year_output['output'];
					}

					$output .= SE_TEMPLATE_LOADER::get_template_part( 'content', 'archive', true, array(), true );
				}
				$output .= '</ul>';
				$output .= '</div>';
			} else {
				// If nothing was found, output the inner blocks.
				$output = $content;
			}

			wp_reset_postdata();
		}

		/**
		 * A filter to customize the render of the upcoming-events block.
		 *
		 * @param string   $output            The output html. May be an empty string.
		 * @param WP_Query $events_query      The query object used to generate the output.
		 * @param array    $events_query_args The built args passed in to the query.
		 * @param array    $attributes        The attributes passed to the block renderer.
		 */
		return apply_filters( 'se_upcoming_events_render', $output, $events_query, $events_query_args, $attributes );
	}

	/**
	 * Get year divider markup for events list.
	 *
	 * @param integer $post_id           The post ID.
	 * @param string  $current_loop_year The current year in the loop.
	 *
	 * @return array
	 */
	public static function get_year_divider( $post_id, $current_loop_year ): array {
		$event_year = get_post_meta( $post_id, 'se_event_date_start', true );
		$post_year  = gmdate( 'Y', $event_year );
		$output     = '';

		if ( $current_loop_year !== $post_year ) {
			$output .= '<li class="se-event-year-divider">' . esc_html( $post_year ) . '</li>';
		}

		return array(
			'output'       => $output,
			'current_year' => $post_year,
		);
	}

	/**
	 * Render next event countdown block.
	 *
	 * @param array $attributes The attributes passed to the block renderer.
	 *
	 * @return HTML Countdown render.
	 */
	public static function countdown_render( $attributes = array() ) {
		$output = '';

		$events_query_args = array(
			'se_countdown'   => true,
			'post_type'      => SE_Event_Post_Type::$post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'meta_value',
			'meta_key'       => 'se_event_date_start',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => 'se_event_date_start',
					'value'   => wp_date( 'U' ),
					'compare' => '>=',
				),
			),
		);

		$events_query = new \WP_Query( $events_query_args );

		$event_id = ( 'se-event' === get_post_type( get_the_ID() ) ) ? get_the_ID() : false;

		if ( $events_query->have_posts() ) {
			ob_start();

			while ( $events_query->have_posts() ) {
				$events_query->the_post();

				$container_class  = 'wp-block-se-next-event-countdown';
				$container_class .= ( ! empty( $attributes['className'] ) ) ? ' ' . $attributes['className'] : '';

				$start_date = get_post_meta( get_the_ID(), 'se_event_date_start', true );

				/**
				 * Adding filter to manage the use of countdown block in events single post (CPT).
				 */
				$start_date = apply_filters( 'se_countdown_start_date', $start_date, $event_id );

				$time_until_start = $start_date * 1000;
				?>
				<div
					class="<?php echo esc_attr( $container_class ); ?>"
					id="event-timer"
					data-event-start-date="<?php echo esc_attr( $time_until_start ); ?>"
				>
					<div class="event-timer__col">
						<p class="event-timer__time-days">00</p>
						<p class="event-timer__label">days</p>
					</div>
					<div class="event-timer__col">
						<p class="event-timer__time-hours">00</p>
						<p class="event-timer__label">hours</p>
					</div>
					<div class="event-timer__col">
						<p class="event-timer__time-minutes">00</p>
						<p class="event-timer__label">minutes</p>
					</div>
					<div class="event-timer__col">
						<p class="event-timer__time-seconds">00</p>
						<p class="event-timer__label">seconds</p>
					</div>
				</div>
				<?php
			}

			$output .= ob_get_clean();
		}

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Calendar block render.
	 *
	 * @param array $attributes The attributes passed to the block renderer.
	 *
	 * @return HTML Calendar Template.
	 */
	public static function calendar_render( $attributes = array() ) {
		$current_date_time  = SE_Calendar::get_instance()->create_date_time( 'now' );
		$previous_date_time = SE_Calendar::get_instance()->get_previous_month_with_events( $current_date_time );
		$next_date_time     = SE_Calendar::get_instance()->get_next_month_with_events( $current_date_time );

		$current_date = $current_date_time->format( 'Y-m-01' );
		$month_data   = SE_Calendar::get_instance()->get_month_days( $current_date );

		if ( ! $month_data['month_has_events'] ) {
			if ( $next_date_time ) {
				$current_date   = $next_date_time->format( 'Y-m-01' );
				$month_data     = SE_Calendar::get_instance()->get_month_days( $current_date );
				$next_date_time = SE_Calendar::get_instance()->get_next_month_with_events( $next_date_time );
			} elseif ( $previous_date_time ) {
				$current_date       = $previous_date_time->format( 'Y-m-01' );
				$month_data         = SE_Calendar::get_instance()->get_month_days( $current_date );
				$previous_date_time = SE_Calendar::get_instance()->get_previous_month_with_events( $previous_date_time );
			}
		}

		// Passing Attributes to the calendar block. Required as the API request replaces the block attribute with default array
		wp_add_inline_script( 'simple-events-calendar-view-script', 'const attributes = ' . wp_json_encode( $attributes ) . ';', 'before' );

		$output = SE_Template_Loader::get_template_part(
			'calendar/calendar',
			'container',
			true,
			array(
				'attributes'       => $attributes,
				'current_date'     => $current_date,
				'days'             => $month_data['days'],
				'month_has_events' => $month_data['month_has_events'],
				'previous_date'    => $previous_date_time?->format( 'Y-m-01' ),
				'next_date'        => $next_date_time?->format( 'Y-m-01' ),
			),
			true
		);

		return apply_filters( 'simple_events_calendar_render', $output );
	}

	/**
	 * Renders the loop event info block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 *
	 * @return string Returns the filtered post date for the current post wrapped inside "time" tags.
	 */
	public static function loop_event_info_render( $attributes, $content, $block ): string {

		$output  = '';
		$prefix  = '';
		$post_ID = ( isset( $attributes['thePostId'] ) && $attributes['thePostId'] > 0 ) ? $attributes['thePostId'] : $block->context['postId'];

		if ( isset( $attributes['metaPrefix'] ) ) {
			$prefix = '<span class="se-loop-event-info--prefix">' . esc_html( $attributes['metaPrefix'] ) . '</span>';
		}

		// Generate output based on meta name.
		if ( ! empty( $post_ID ) ) {
			switch ( $attributes['metaName'] ) {
				case 'location':
					$output = se_event_get_location( $post_ID );
					break;
				case 'venue':
					$output = se_event_get_venue( $post_ID );
					break;
				case 'dates':
					$output = se_event_get_future_dates( $post_ID );
					break;
				case 'date':
					$output = se_event_get_future_dates( $post_ID, true, false );
					break;
				case 'time':
					$output = se_event_get_future_dates( $post_ID, false, true );
					break;
			}
		}

		// If post ID is empty at this point, we're in the FSE editor.
		if ( empty( $post_ID ) ) {
			// Generate placeholder output based on meta name.
			switch ( $attributes['metaName'] ) {
				case 'location':
					$output = esc_html__( 'Example Location Name', 'simple-events' );
					break;
				case 'venue':
					$output = esc_html__( 'Example Venue Name', 'simple-events' );
					break;
				case 'dates':
					$output = esc_html__( 'June 20, 2023 9:00 am - 10:00 am', 'simple-events' );
					break;
				case 'date':
					$output = esc_html__( 'June 28, 2023', 'simple-events' );
					break;
				case 'time':
					$output = esc_html__( '9:00 am - 10:00 am', 'simple-events' );
					break;
			}
		}

		// Add calendar links if the attribute is set to true.
		if ( isset( $attributes['addCalendarLinks'] ) && $attributes['addCalendarLinks'] ) {
			$output .= se_template_calendar_links( false );
		}

		// Add gutenberg generated wrapper atts.
		$output = sprintf(
			'<div %s>%s%s</div>',
			get_block_wrapper_attributes(
				array(
					'class' => 'has-text-align-' . esc_attr( $attributes['textAlign'] ),
				)
			),
			$prefix,
			$output
		);

		return apply_filters( 'simple_events_loop_info_render', $output, $attributes, $content, $block );
	}

	/**
	 * Renders the loop external link block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 *
	 * @return string Returns the html for the link block.
	 */
	public static function loop_event_external_link_render( $attributes, $content, $block ): string {
		$post_ID = ( isset( $attributes['thePostId'] ) && $attributes['thePostId'] > 0 ) ? $attributes['thePostId'] : $block->context['postId'];

		if ( ! $post_ID ) {
			return sprintf(
				'<a class="wp-block-se-event-link">%s</a>',
				esc_html__( 'Tickets', 'simple-events' )
			);
		}

		$has_meta    = get_post_meta( $post_ID, 'se_event_external_link', true );
		$event_link  = $has_meta ? $has_meta : get_the_permalink( $post_ID );
		$link_text   = $has_meta ? __( 'Tickets', 'simple-events' ) : __( 'Details', 'simple-events' );
		$cta         = apply_filters( 'se_event_loop_external_link_text', $link_text, $has_meta );
		$link_target = $has_meta && ! strstr( wp_parse_url( $has_meta, PHP_URL_HOST ), wp_parse_url( get_site_url(), PHP_URL_HOST ) ) ? 'target="_blank" rel="nofollow"' : '';

		if ( ! $event_link ) {
			return '';
		}

		$output = sprintf(
			'<a href="%s" class="wp-block-se-event-link" %s>%s</a>',
			esc_url( $event_link ),
			$link_target,
			esc_html( $cta )
		);

		return apply_filters( 'simple_events_loop_link_render', $output, $attributes, $content, $block );
	}

	/**
	 * Renders the notice for past events.
	 *
	 * @param array  $attributes The attributes passed to the function.
	 * @param string $content    The content passed to the function.
	 *
	 * @return void
	 */
	public static function past_events_notice_render( $attributes, $content ) {
		if (
			'se-event' !== get_post_type() || // If not an event.
			! se_event_is_expired( get_the_ID() ) || // If event has expired.
			defined( 'REST_REQUEST' ) // If event is being edited.
		) {
			return;
		}

		return wp_kses_post( $content );
	}

	/**
	 * Loads the asset file for the given script or style.
	 * Returns a default if the asset file is not found.
	 *
	 * @param string $file_path The name of the file without the extension.
	 *
	 * @return array The asset file contents.
	 */
	public static function get_asset_file( $file_path ) {
		$asset_path = SE_PLUGIN_DIR . $file_path . '.asset.php';

		return file_exists( $asset_path )
			? include $asset_path
			: array(
				'dependencies' => array(),
				'version'      => SE_VERSION,
			);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars The current query vars.
	 *
	 * @return array $vars The updated query vars.
	 */
	public static function add_event_query_vars( $vars ) {
		$vars[] = 'se_event_order';

		return $vars;
	}
}

SE_Blocks::init();
