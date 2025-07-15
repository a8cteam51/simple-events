<?php
/**
 * Event Block Variations.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks Class.
 */
class SE_Block_Variations {

	/**
	 * Parsed variation block.
	 *
	 * @var array
	 */
	protected $parsed_block = array();

	/**
	 * Query Loop Events namespace.
	 */
	const QUERY_LOOP_EVENTS = 'query-loop-events';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		if ( file_exists( SE_PLUGIN_DIR . '/build' ) ) {
			add_action( 'pre_render_block', array( $this, 'update_query' ), 10, 2 );
			add_filter( 'rest_se-event_query', array( $this, 'set_admin_query' ), 10, 2 );
		}
	}

	/**
	 * Check if a given block is a Query Loop Events block variation.
	 *
	 * @param array $parsed_block The block being rendered.
	 *
	 * @return boolean
	 */
	private function is_events_variation( $parsed_block ) {
		return isset( $parsed_block['attrs']['namespace'] ) && substr( $parsed_block['attrs']['namespace'], 0, 9 ) === 'se-events';
	}

	/**
	 * Update the query for the event query block.
	 *
	 * @param string|null $pre_render   The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 *
	 * @return void
	 */
	public function update_query( $pre_render, $parsed_block ) {
		if ( 'core/query' !== $parsed_block['blockName'] ) {
			return;
		}

		$this->parsed_block = $parsed_block;

		if ( $this->is_events_variation( $parsed_block ) ) {
			add_filter( 'query_loop_block_query_vars', array( $this, 'build_query' ), 10, 1 );
		}
	}

	/**
	 * Return a custom query based on attributes, filters and global WP_Query.
	 *
	 * @param WP_Query $query The WordPress Query.
	 *
	 * @return WP_Query
	 */
	public function build_query( $query ) {
		$parsed_block = $this->parsed_block;
		if ( ! $this->is_events_variation( $parsed_block ) ) {
			return $query;
		}

		$query['sub-type'] = self::QUERY_LOOP_EVENTS;

		if ( ! isset( $parsed_block['attrs']['query']['feedType'] ) ) {
			$parsed_block['attrs']['query']['feedType'] = 'default';
		}

		$feed_type  = $parsed_block['attrs']['query']['feedType'];
		$feed_order = $parsed_block['attrs']['query']['order'];

		// Inherit taxonomy query from global WP_Query if in taxonomy archive context
		if ( ! empty( $parsed_block['attrs']['query']['inheritTaxQuery'] ) ) {
			global $wp_query;
			if ( is_tax() && ! empty( $wp_query->tax_query ) ) {
				$query['tax_query'] = $wp_query->tax_query->queries;
			}
		}

		// Change the post type.
		$query['post_type'] = SE_Event_Post_Type::$event_date_post_type;

		return $this->set_event_query_args( $query, $feed_type, $feed_order );
	}

	/**
	 * Modify event posts results.
	 *
	 * @param array    $posts The array of post objects.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return array
	 */
	public function modify_event_posts( $posts, $query ) {
		// Check if this is our events query
		if ( ! isset( $query->query_vars['sub-type'] ) || self::QUERY_LOOP_EVENTS !== $query->query_vars['sub-type'] ) {
			return $posts;
		}

		// Return back the
		return array_map(
			function ( $post ) {
				$parent = get_post( $post->post_parent );

				// Get the start date from the event.
				$start_date_ts = get_post_meta( $post->ID, 'se_event_date_start', true );

				// Get the event timezone.
				$timezone = get_post_meta( $parent->ID, 'se_event_timezone', true );
				// use the timezone or default to the site timezone.
				$timezone = $timezone ? $timezone : wp_timezone_string();

				// Get the date im this format 2025-07-01 13:14:09
				$start_date     = wp_date( 'Y-m-d H:i:s', $start_date_ts, new \DateTimeZone( $timezone ) );
				$start_date_gmt = wp_date( 'Y-m-d H:i:s', $start_date_ts, new \DateTimeZone( 'UTC' ) );

				// update the parent posts post date
				$parent->post_date         = $start_date;
				$parent->post_date_gmt     = $start_date_gmt;
				$parent->post_modified     = $start_date;
				$parent->post_modified_gmt = $start_date_gmt;
				$parent->event_date_id     = $post->ID;

				return $parent;
			},
			$posts
		);
	}

	/**
	 * Set the query args for the event loop query admin.
	 *
	 * @param mixed $args    The arguments for the query.
	 * @param mixed $request The request object.
	 *
	 * @return mixed The result of the set event query args.
	 */
	public function set_admin_query( $args, $request ) {

		$feed_type  = $request->get_param( 'feedType' );
		$feed_order = $request->get_param( 'order' );#

		return $this->set_event_query_args( $args, $feed_type, $feed_order );
	}

	/**
	 * Set the Event Query Loop Args.
	 *
	 * @param mixed $args       The arguments for the query.
	 * @param mixed $feed_type  The feed type.
	 * @param mixed $feed_order The feed order.
	 *
	 * @return mixed The result of the set event query args.
	 */
	private function set_event_query_args( $args, $feed_type, $feed_order = 'ASC' ) {

		// If we are ordering by desc. we need to sort by end date, else start.
		$args['meta_key'] = 'desc' === strtolower( $feed_order ) ? 'se_event_date_end' : 'se_event_date_start';
		$args['orderby']  = 'meta_value';
		$args['order']    = $feed_order;

		$args['sub-type'] = self::QUERY_LOOP_EVENTS;

		if ( 'upcoming' === $feed_type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'se_event_date_end',
					'value'   => wp_date( 'U' ),
					'compare' => '>=',
				),
			);

			$args['orderby']  = 'meta_value';
			$args['meta_key'] = 'se_event_date_start';
			$args['order']    = $feed_order;
		}

		if ( 'past' === $feed_type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'se_event_date_end',
					'value'   => wp_date( 'U' ),
					'compare' => '<',
				),
			);

			$args['orderby']  = 'meta_value';
			$args['meta_key'] = 'se_event_date_start';
			$args['order']    = $feed_order;
		}

		// add the arg to denote unique parents.
		$args['unique_parents'] = true;
		$args['feed_order']     = $feed_order; // Store feed order for use in the WHERE filter

		// Ensure we only get the correct event date for each parent.
		add_filter( 'posts_where', array( $this, 'filter_unique_parents_where' ), 10, 2 );

		// Add a filter to modify the posts results.
		add_filter( 'the_posts', array( $this, 'modify_event_posts' ), 10, 2 );

		// Add a custom order by.
		add_filter( 'posts_orderby', array( $this, 'fix_editor_sort_order' ), 10, 2 );

		/**
		 * A filter to customize the args of the event query loop.
		 *
		 * @param array    $args The built args passed in to the query.
		 * @param string|null    $feed_type        The feed type.
		 * @param string|null    $feed_order       The feed order.
		 */
		return apply_filters( 'se_pre_set_event_query_loop_args', $args, $feed_type, $feed_order );
	}

	/**
	 * Ensure the sort order is correctly set for the unique parents query.
	 *
	 * This fixes a weird bug where the admin/editor order is always ASC.
	 *
	 * @param string   $orderby The current orderby clause.
	 * @param WP_Query $query   The WP_Query instance.
	 *
	 * @return string
	 */
	public function fix_editor_sort_order( $orderby, $query ) {
		if ( isset( $query->query_vars['unique_parents'] ) && $query->query_vars['unique_parents'] ) {
			if ( str_ends_with( $orderby, '+0 ASC' ) ) {
				$feed_order = isset( $query->query_vars['feed_order'] ) ? $query->query_vars['feed_order'] : 'ASC';
				$new_order  = sprintf( ' %s', strtoupper( $feed_order ) );
				$orderby    = str_replace( '+0 ASC', $new_order, $orderby );
			}
		}

		return $orderby;
	}


	/**
	 * Filter posts to only include the correct event date for each parent.
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function filter_unique_parents_where( $where, $query ) {
		// Check if this is our events query and unique parents is enabled
		if ( ! isset( $query->query_vars['unique_parents'] ) || ! isset( $query->query_vars['feed_order'] ) ) {
			return $where;
		}

		// adump( $query->query_vars['unique_parents'] );

		// Skip if treating each date as own event
		if ( se_event_treat_each_date_as_own_event() ) {
			return $where;
		}

		global $wpdb;

		$feed_order = $query->query_vars['feed_order'];
		$meta_key   = 'desc' === $feed_order ? 'se_event_date_end' : 'se_event_date_start';
		// Get the current time filtering from the main query's meta_query
		$time_filter = '';
		$meta_query  = $query->get( 'meta_query' );
		if ( ! empty( $meta_query ) && is_array( $meta_query ) ) {
			foreach ( $meta_query as $meta_condition ) {
				if ( isset( $meta_condition['key'] ) && 'se_event_date_end' === $meta_condition['key'] ) {
					$compare = $meta_condition['compare'];
					$value   = $meta_condition['value'];

					// Add the same time filtering to the subquery
					if ( '>=' === $compare ) {
						// For upcoming events
						$time_filter = "AND pm3.meta_value >= {$value}";
					} elseif ( '<' === $compare ) {
						// For past events
						$time_filter = "AND pm3.meta_value < {$value}";
					}
					break;
				}
			}
		}

		// Subquery to get the correct post ID for each parent based on sort order
		$subquery = "
			AND {$wpdb->posts}.ID IN (
				SELECT p1.ID
				FROM {$wpdb->posts} p1
				INNER JOIN {$wpdb->postmeta} pm1 ON p1.ID = pm1.post_id AND pm1.meta_key = '{$meta_key}'
				WHERE p1.post_type = '" . SE_Event_Post_Type::$event_date_post_type . "'
				AND p1.post_status = 'publish'
				AND pm1.meta_value = (
					SELECT " . ( 'desc' === $feed_order ? 'MAX' : 'MIN' ) . "(pm2.meta_value)
					FROM {$wpdb->posts} p2
					INNER JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '{$meta_key}'
					" . ( $time_filter ? "INNER JOIN {$wpdb->postmeta} pm3 ON p2.ID = pm3.post_id AND pm3.meta_key = 'se_event_date_end'" : '' ) . "
					WHERE p2.post_parent = p1.post_parent
					AND p2.post_type = '" . SE_Event_Post_Type::$event_date_post_type . "'
					AND p2.post_status = 'publish'
					{$time_filter}
				)
				GROUP BY p1.post_parent
			)
		";

		$where .= $subquery;

		return $where;
	}
}

( new SE_Block_Variations() )->init();
