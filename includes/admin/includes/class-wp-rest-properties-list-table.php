<?php

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class used to implement displaying REST API properties in a list table.
 *
 * @since 0.1.0
 *
 * @link WP_REST_API_List_Table
 */
class WP_REST_Properties_List_Table extends WP_REST_API_List_Table {
	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array|string $args {
	 *     Array or string of arguments.
	 *
	 *     @type string $plural   Plural value used for labels and the objects being listed.
	 *                            This affects things such as CSS class-names and nonces used
	 *                            in the list table, e.g. 'properties'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'property'.
	 *                            Default empty
	 *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
	 *                            and sorting data, for example. If true, the class will call
	 *                            the _js_vars() method in the footer to provide variables
	 *                            to any scripts handling Ajax events. Default false.
	 *     @type string $screen   String containing the hook name used to determine the current
	 *                            screen. If left null, the current screen will be automatically set.
	 *                            Default null.
	 * }
	 */
	function __construct( $args = array() ) {
		$defaults = array(
			'singular' => 'property',
			'plural'   => 'properties',
		);

		$args = wp_parse_args( $args, $defaults );

		parent::__construct( $args );
	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array(
			'name'        => __( 'Name', 'shc-rest-api-inspector' ),
			'type'        => __( 'Type', 'shc-rest-api-inspector' ),
			'description' => __( 'Description', 'shc-rest-api-inspector' ),
			'context'     => __( 'Context', 'shc-rest-api-inspector' ),
		);

		return parent::get_columns() + $columns;
	}

	/**
	 * Output dropdowns.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function do_dropdowns() {
		$this->types_dropdown();
		$this->contexts_dropdown();

		return;
	}

	/**
	 * Display a contexts dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function contexts_dropdown() {
		$contexts = $options = array();
		foreach ( $this->all_items as $item ) {
			$contexts = array_merge( $contexts, $item->context );
		}

		foreach ( array_unique( $contexts ) as $context ) {
			$options[ $context ] = $context;
		}

		$this->dropdown(
			'context',
			__( 'Filter by context', 'shc-rest-api-inspector' ),
			__( 'All contexts', 'shc-rest-api-inspector' ),
			$options
		);

		return;
	}

	/**
	 * Get all views for this list table.
	 *
	 * @since 0.1.0
	 *
	 * @return string[] Array of views.  Keys are item statuses, values are
	 *                                   the text to display for the view.
	 */
	protected function _all_views() {
		return array(
			'readonly'  => __( 'Readonly', 'shc-rest-api-inspector' ),
			'writeable' => __( 'Writable', 'shc-rest-api-inspector' ),
		);
	}

	/**
	 * Get all available views.
	 *
	 * The return value is analogous to the `$avail_post_stati` global var that is
	 * set in
	 * {@link https://developer.wordpress.org/reference/classes/wp_posts_list_table/prepare_items/ WP_Posts_List_Table::prepare_items()}.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of available views.  Keys are item status, values are
	 *               counts of items with that item status.
	 */
	protected function _avail_views() {
		$avail_views = array();

		foreach ( array( 'readonly' => true, 'writeable' => false ) as $view => $value ) {
			$avail_views[ $view ] = count( wp_list_filter( $this->all_items, array( 'readonly' => $value ) ) );
		}

		return $avail_views;
	}

	/**
	 * Retrieve an array of item states for an item.
	 *
	 * @since 0.1.0
	 *
	 * @see WP_REST_API_List_Table::get_item_states()
	 *
	 * @param object $item The item to retrieve states for.
	 * @return array Array of item states.
	 */
	protected function _get_item_states( $item, $item_status ) {
		$item_states = array();

		if ( true === $item->readonly && 'readonly' !== $item_status ) {
			$item_states['readonly'] = __( 'Readonly', 'shc-rest-api-inspector' );
		}

		return $item_states;
	}

	/**
	 * Can a given item drill down into the "next" item?
	 *
	 * As of 0.1.0, we can't drill down further than properties.  However, that may
	 * change in the future.  For instance, if the type of a property can be `object`,
	 * then we'll need to implement drilling down into the properties of that property.
	 * I'm not sure if that is legal when registering routes.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item
	 * @return bool True if the given item can drill down into the "next" item; false otherwise.
	 */
	protected function has_view_next( $item ) {
		return false;
	}
}
