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
class WP_REST_Schema_Properties_List_Table extends WP_REST_Properties_List_Table {
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
			'singular' => 'schema_property',
			'plural'   => 'schema_properties',
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
			'format'      => __( 'Format', 'shc-rest-api-inspector' ),
			'description' => __( 'Description', 'shc-rest-api-inspector' ),
			'context'     => __( 'Context', 'shc-rest-api-inspector' ),
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
	 * Output dropdowns.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function do_dropdowns() {
		parent::do_dropdowns();
		$this->formats_dropdown();

		return;
	}
}
