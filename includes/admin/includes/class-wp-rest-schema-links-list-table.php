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
class WP_REST_Schema_Links_List_Table extends WP_REST_API_List_Table {
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
			'singular' => 'schema_link',
			'plural'   => 'schema_links',
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
			'href'        => __( 'href', 'shc-rest-api-inspector' ),
			'title'      => __( 'Title', 'shc-rest-api-inspector' ),
		);

		return $columns + parent::get_columns();
	}

	/**
	 * Display a contexts dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function formats_dropdown() {
		$formats = $options = array();
		$formats = array_filter( wp_list_pluck( $this->all_items, 'format' ) );

		foreach ( array_unique( $formats ) as $format ) {
			$options[ $format ] = $format;
		}

		$this->dropdown(
			'format',
			__( 'Filter by format', 'shc-rest-api-inspector' ),
			__( 'All formats', 'shc-rest-api-inspector' ),
			$options
		);

		return;
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

	protected function get_views() {
		return array();
	}

	protected function _get_item_states( $item, $item_status ) {
		return array();
	}
}
