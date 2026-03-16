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
		return SE_Event_Query_Utils::modify_event_posts( $posts, $query, 'variations' );
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
		$args['meta_key']    = 'desc' === strtolower( $feed_order ) ? 'se_event_date_end' : 'se_event_date_start';
		$args['orderby']     = 'meta_value';
		$args['order']       = $feed_order;
		$args['post_status'] = 'publish';

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

		// If we have any taxonomies, we need to ensure they are set correctly.
		if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			// Ensure we only get the correct event date for each
			$post_ids = SE_Event_Query_Utils::get_child_date_posts_from_tax_query( $args['tax_query'] );
			if ( ! empty( $post_ids ) ) {
				$args['post__in'] = $post_ids;
			}

			// Unset the tax query to avoid conflicts.
			unset( $args['tax_query'] );
		}

		// add the arg to denote unique parents.
		$args['unique_parents'] = true;
		$args['feed_order']     = $feed_order; // Store feed order for use in the WHERE filter
		// Ensure we only get the correct event date for each parent.
		add_filter( 'posts_where', array( 'SE_Event_Query_Utils', 'filter_event_dates_where' ), 10, 2 );

		// Add a filter to modify the posts results.
		add_filter( 'the_posts', array( 'SE_Event_Query_Utils', 'modify_event_posts' ), 10, 2 );

		// Add a custom order by.
		add_filter( 'posts_orderby', array( 'SE_Event_Query_Utils', 'fix_sort_order' ), 10, 2 );

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
		return SE_Event_Query_Utils::fix_sort_order( $orderby, $query );
	}
}

( new SE_Block_Variations() )->init();
