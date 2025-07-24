<?php
/**
 * Event Post Type
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types Class.
 */
class SE_Event_Post_Type {

	/**
	 * The current event version.
	 *
	 * @var string
	 */
	public static $current_event_version = '2.0.0';


	/**
	 * This is the name of this post type.
	 *
	 * @var string
	 */
	public static $post_type = 'se-event';


	/**
	 * The event date post type.
	 *
	 * @var string
	 */
	public static $event_date_post_type = 'se-event-date';

	/**
	 * This is the slug of this post type.
	 *
	 * @var string
	 */
	public static $slug = 'events';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 15 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_expired_events' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'the_archive_title' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'flush_rewrite_rules' ) );
		add_action( 'save_post', array( __CLASS__, 'delete_event_dates_if_no_event_info_block' ) );
		add_filter( 'is_protected_meta', array( __CLASS__, 'is_protected_meta' ), 10, 3 );
	}

	/**
	 * Post type registration.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		// Retrieve options for hiding/showing event info and event tickets blocks.
		$options                    = get_option( 'se_options' );
		$remove_event_tickets_block = isset( $options['remove_event_tickets_block'] ) ? $options['remove_event_tickets_block'] : false;

		$template = array(
			array( 'simple-events/event-info' ),
			array( 'simple-events/inner-blocks', array(), array( array( 'core/paragraph' ) ) ),
		);

		if ( ! $remove_event_tickets_block ) {
			$template[] = array( 'simple-events/event-tickets' );
		}

		register_post_type(
			self::$post_type,
			array(
				'labels'                => array(
					'name'                  => __( 'Events', 'simple-events' ),
					'singular_name'         => __( 'Event', 'simple-events' ),
					'all_items'             => __( 'All Events', 'simple-events' ),
					'archives'              => __( 'Event Archives', 'simple-events' ),
					'attributes'            => __( 'Event Attributes', 'simple-events' ),
					'insert_into_item'      => __( 'Insert into Event', 'simple-events' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Event', 'simple-events' ),
					'featured_image'        => _x( 'Featured Image', 'se-event', 'simple-events' ),
					'set_featured_image'    => _x( 'Set featured image', 'se-event', 'simple-events' ),
					'remove_featured_image' => _x( 'Remove featured image', 'se-event', 'simple-events' ),
					'use_featured_image'    => _x( 'Use as featured image', 'se-event', 'simple-events' ),
					'filter_items_list'     => __( 'Filter Events list', 'simple-events' ),
					'items_list_navigation' => __( 'Events list navigation', 'simple-events' ),
					'items_list'            => __( 'Events list', 'simple-events' ),
					'new_item'              => __( 'New Event', 'simple-events' ),
					'add_new'               => __( 'Add New', 'simple-events' ),
					'add_new_item'          => __( 'Add New Event', 'simple-events' ),
					'edit_item'             => __( 'Edit Event', 'simple-events' ),
					'view_item'             => __( 'View Event', 'simple-events' ),
					'view_items'            => __( 'View Events', 'simple-events' ),
					'search_items'          => __( 'Search Events', 'simple-events' ),
					'not_found'             => __( 'No Events found', 'simple-events' ),
					'not_found_in_trash'    => __( 'No Events found in trash', 'simple-events' ),
					'parent_item_colon'     => __( 'Parent Event:', 'simple-events' ),
					'menu_name'             => __( 'Events', 'simple-events' ),
				),
				'public'                => true,
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_in_nav_menus'     => true,
				'supports'              => array(
					'title',
					'editor',
					'thumbnail',
					'custom-fields',
					'excerpt',
					'author',
				),
				'rewrite'               => array(
					'slug'       => 'event',
					'with_front' => false,
				),
				'has_archive'           => self::$slug,
				'query_var'             => true,
				'menu_position'         => null,
				'menu_icon'             => 'dashicons-calendar-alt',
				'show_in_rest'          => true,
				'rest_base'             => self::$post_type,
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'template'              => $template,
				'template_lock'         => 'insert',
				'taxonomies'            => array(
					'post_tag',
				),
			)
		);

		// Register the event-date post type. This is a child of the above event post type.
		register_post_type(
			'se-event-date',
			array(
				'labels'                => array(
					'name'                  => __( 'Event Dates', 'simple-events' ),
					'singular_name'         => __( 'Event Date', 'simple-events' ),
					'all_items'             => __( 'All Event Dates', 'simple-events' ),
					'archives'              => __( 'Event Date Archives', 'simple-events' ),
					'attributes'            => __( 'Event Date Attributes', 'simple-events' ),
					'insert_into_item'      => __( 'Insert into Event Date', 'simple-events' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Event Date', 'simple-events' ),
					'featured_image'        => _x( 'Featured Image', 'se-event-date', 'simple-events' ),
					'set_featured_image'    => _x( 'Set featured image', 'se-event-date', 'simple-events' ),
					'remove_featured_image' => _x( 'Remove featured image', 'se-event-date', 'simple-events' ),
					'use_featured_image'    => _x( 'Use as featured image', 'se-event-date', 'simple-events' ),
					'filter_items_list'     => __( 'Filter Event Dates list', 'simple-events' ),
					'items_list_navigation' => __( 'Event Dates list navigation', 'simple-events' ),
					'items_list'            => __( 'Event Dates list', 'simple-events' ),
					'new_item'              => __( 'New Event Date', 'simple-events' ),
					'add_new'               => __( 'Add New', 'simple-events' ),
					'add_new_item'          => __( 'Add New Event Date', 'simple-events' ),
					'edit_item'             => __( 'Edit Event Date', 'simple-events' ),
					'view_item'             => __( 'View Event Date', 'simple-events' ),
					'view_items'            => __( 'View Event Dates', 'simple-events' ),
					'search_items'          => __( 'Search Event Dates', 'simple-events' ),
					'not_found'             => __( 'No Event Dates found', 'simple-events' ),
					'not_found_in_trash'    => __( 'No Event Dates found in trash', 'simple-events' ),
					'parent_item_colon'     => __( 'Parent Event Date:', 'simple-events' ),
					'menu_name'             => __( 'Event Dates', 'simple-events' ),
				),
				'public'                => false,
				'hierarchical'          => false,
				'show_ui'               => false,
				'show_in_nav_menus'     => false,
				'supports'              => array(
					'title',
					'editor',
					'thumbnail',
					'custom-fields',
				),
				'rewrite'               => array(
					'slug'       => 'event-date',
					'with_front' => false,
				),
				'has_archive'           => false,
				'query_var'             => false,
				'menu_position'         => null,
				'menu_icon'             => 'dashicons-calendar-alt',
				'show_in_rest'          => true,
				'rest_base'             => 'se-event-date',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capabilities'          => array(
					'create_posts' => 'do_not_allow', // Disable creation of new event dates.
				),
			)
		);
	}

	/**
	 * Taxonomy registration.
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			self::$post_type . '-category',
			self::$post_type,
			array(
				'description'       => __( 'Categories for simple event posts', 'simple-events' ),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'events/category',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Defines protected meta keys for the event post type.
	 *
	 * This method registers meta keys that are used to store event-related data.
	 *
	 * @param boolean $is_protected Whether the meta keys should be protected.
	 * @param string  $meta_key     The meta key to register.
	 * @param string  $meta_type    The type of the meta key.
	 *
	 * @return boolean
	 */
	public static function is_protected_meta( bool $is_protected, string $meta_key, string $meta_type = 'string' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$protected_keys = array( 'se_event_date_end', 'se_event_date_start' );

		if ( in_array( $meta_key, $protected_keys, true ) ) {
			return true;
		}
		return $is_protected;
	}

	/**
	 * Register meta keys.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_meta(
			'post',
			'se_event_location',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_venue',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_dates',
			array(
				'single'            => true,
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => function ( $value ) {
					if ( is_null( $value ) || ! is_array( $value ) ) {
						return array();
					}
					return $value;
				},
			)
		);

		register_meta(
			'post',
			'se_event_date_start',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'auth_callback'  => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_meta(
			'post',
			'se_event_date_end',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'auth_callback'  => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_meta(
			'post',
			'se_event_timezone',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_display_timezone',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_display_grouped',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_event_hide_end_time',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_hide_start_time',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_add_calendar_links',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_open_in_new_window',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);
		register_meta(
			'post',
			'se_event_external_link',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_external_link_label',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'default'        => esc_html__( 'Tickets', 'simple-events' ),
			)
		);

		register_meta(
			'post',
			'se_open_external_link',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_modal_access',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_show_modal_title',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_show_modal_excerpt',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_event_show_on_frontend',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		// is all day (bool)
		register_meta(
			'post',
			'se_event_all_day',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$event_date_post_type,
			)
		);

		// hide from calendar (bool)
		register_meta(
			'post',
			'se_event_hide_from_calendar',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$event_date_post_type,
				'default'        => false,
			)
		);

		// hide from feed (bool)
		register_meta(
			'post',
			'se_event_hide_from_feed',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$event_date_post_type,
				'default'        => false,
			)
		);
	}

	/**
	 * Flush rewrite rules if the flag added during activation exists.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( get_option( 'simple_events_flush_rewrite_rules_flag' ) ) {
			delete_option( 'simple_events_flush_rewrite_rules_flag' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Order events by date.
	 *
	 * @param WP_Query $query WP_Query instance.
	 *
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		if ( is_admin() || ( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST ) || ( isset( $query->query['type'] ) && 'calendar' === $query->query['type'] ) ) {
			return;
		}

		$options    = get_option( 'se_options' );
		$sort_order = ( isset( $options['reverse_events_order'] ) ) ? 'DESC' : 'ASC';

		// Check if there's an override for the sort order through the 'se_pre_get_posts_order_override' filter.
		$order_override = apply_filters( 'se_pre_get_posts_order_override', $query->get( 'se_event_order' ), $query );

		// If a custom order override exists, use it. Otherwise, stick with the existing sort order.
		$sort_order = ! empty( $order_override ) ? $order_override : $sort_order;

		if (
			( $query->is_main_query() && ( is_post_type_archive( self::$post_type )
			|| is_tax( self::$post_type . '-category' ) ) )
			|| ( ! $query->is_main_query() && self::$post_type === $query->get( 'post_type' ) && ! $query->get( 'se_countdown' ) && $query->get( 'sub-type' ) === SE_Block_Variations::QUERY_LOOP_EVENTS )
		) {

			// Handle taxonomy filtering by getting parent event IDs first
			$parent_event_ids = null;
			$tax_query        = $query->get( 'tax_query' );

			// Check if we have taxonomy queries for event categories
			if ( ! empty( $tax_query ) || is_tax( self::$post_type . '-category' ) ) {
				// Create a separate query to get parent events that match taxonomy criteria
				$parent_query_args = array(
					'post_type'      => self::$post_type,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_status'    => 'publish',
				);

				// Add taxonomy query from original query
				if ( ! empty( $tax_query ) ) {
					$parent_query_args['tax_query'] = $tax_query;
				}

				// Handle category archive pages
				if ( is_tax( self::$post_type . '-category' ) ) {
					$term                           = get_queried_object();
					$parent_query_args['tax_query'] = array(
						array(
							'taxonomy' => self::$post_type . '-category',
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						),
					);
				}

				$parent_events    = new WP_Query( $parent_query_args );
				$parent_event_ids = $parent_events->posts;

				// If no parent events match, set to empty array to return no results
				if ( empty( $parent_event_ids ) ) {
					$parent_event_ids = array( 0 );
				}
			}

			// Change query to target event dates instead of events
			$query->set( 'post_type', self::$event_date_post_type );

			// If we have taxonomy filtering, limit to dates of matching parent events
			if ( null !== $parent_event_ids ) {
				$query->set( 'post_parent__in', $parent_event_ids );
				// Remove tax_query since we're now querying date posts
				$query->set( 'tax_query', array() );
			}

			// Order by event date start timestamp
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', 'se_event_date_start' );
			$query->set( 'order', apply_filters( 'se_pre_get_posts_order', $sort_order, $query ) );

			// Values for which passed events should be hidden on Feed.
			$event_options = array( 'hide_events_on_both', 'hide_events_on_feed', 'on' );
			if ( isset( $options['hide_past_events'] ) && ! empty( $options['hide_past_events'] ) && in_array( $options['hide_past_events'], $event_options, true ) ) {
				$existing_meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $existing_meta_query ) ) {
					$existing_meta_query = array();
				}

				$existing_meta_query[] = array(
					'key'     => 'se_event_date_end',
					'value'   => time(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);

				$query->set( 'meta_query', $existing_meta_query );
			}

			// Add unique parents filtering if not treating each date as own event
			if ( ! se_event_treat_each_date_as_own_event() ) {
				$query->set( 'unique_parents', true );
				$query->set( 'feed_order', $sort_order );

				// Add filter for unique parents WHERE clause
				add_filter( 'posts_where', array( 'SE_Event_Query_Utils', 'filter_unique_parents_where' ), 10, 2 );

				// Add filter to modify posts for event_date_id
				add_filter( 'the_posts', array( 'SE_Event_Query_Utils', 'modify_event_posts' ), 10, 2 );

				// Add custom order by filter
				add_filter( 'posts_orderby', array( 'SE_Event_Query_Utils', 'fix_sort_order' ), 10, 2 );
			} else {
				// When treating each date as own event, still convert event date posts to parent events
				// but don't filter for unique parents
				add_filter( 'the_posts', array( 'SE_Event_Query_Utils', 'modify_event_posts' ), 10, 2 );
			}
		}
	}

	/**
	 * Restrict access to event single page.
	 *
	 * @return void
	 */
	public static function handle_expired_events() {
		global $wp_query;

		if ( $wp_query->is_singular( self::$post_type ) ) {
			$options = get_option( 'se_options' );

			// Values for which passed events should be shown on Single View.
			$event_options = array( '', 'hide_events_on_feed' );

			if ( ! isset( $options['hide_past_events'] ) || in_array( $options['hide_past_events'], $event_options, true ) ) {
				return;
			}

			$event = $wp_query->get_queried_object();

			if ( ! empty( $event ) && se_event_is_expired( $event->ID ) ) {
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	}

	/**
	 * Remove "Archive:" from archive title.
	 *
	 * @param string $title Archive title.
	 *
	 * @return string
	 */
	public static function the_archive_title( $title ) {
		if ( is_post_type_archive( self::$post_type ) ) {
			return post_type_archive_title( '', false );
		}

		return $title;
	}

	/**
	 * Remove rewrite rules and then recreate rewrite rules.
	 *
	 * @return void
	 */
	public static function flush_rewrite_rules() {
		self::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Deletes event dates if no event info block is present.
	 *
	 * @param integer $event_id Event ID.
	 *
	 * @return void
	 */
	public static function delete_event_dates_if_no_event_info_block( $event_id ) {
		if ( wp_is_post_revision( $event_id ) || get_post_type( $event_id ) !== 'se-event' ) {
			return;
		}

		$event  = get_post( $event_id );
		$blocks = parse_blocks( $event->post_content );

		$is_event_info_block_present = false;

		foreach ( $blocks as $block ) {
			if ( 'simple-events/event-info' === $block['blockName'] ) {
				$is_event_info_block_present = true;
				break;
			}
		}

		if ( ! $is_event_info_block_present ) {
			// Delete all the event dates.
			SE_Event_Dates::delete_all_event_dates( $event_id );
		}
	}
}

SE_Event_Post_Type::init();
