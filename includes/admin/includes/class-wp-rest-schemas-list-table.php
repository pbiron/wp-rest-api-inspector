<?php

namespace SHC\REST_API_Inspector;

use ReflectionMethod;

defined( 'ABSPATH' ) || die;

/**
 * Class used to implement displaying REST API endpoints in a list table.
 *
 * @since 0.1.0
 *
 * @link WP_REST_API_List_Table
 */
class WP_REST_Schemas_List_Table extends WP_REST_API_List_Table {
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
	 *                            in the list table, e.g. 'endpoints'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'endpoint'.
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
			'singular' => 'schema',
			'plural'   => 'schemas',
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
			'name'          => __( 'Route', 'shc-rest-api-inspector' ),
			'title'         => __( 'Title', 'shc-rest-api-inspector' ),
			'$schema'       => __( '$schema', 'shc-rest-api-inspector' ),
//			'links' => __( 'Links', 'shc-rest-api-inspector' ),
		);

		return parent::get_columns() + $columns;
	}

	function column_links( $item ) {
		return '';
	}

	protected function _get_row_actions( $item ) {
		$actions = parent::_get_row_actions( $item );

		if ( $item->links ) {
			$actions['view-links'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				$this->get_view_links_link( $item ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $item->name ) ),
				__( 'Links', 'shc-rest-api-inspector' )
			);
		}

		return $actions;
	}

	protected function _get_item_states( $item, $item_status ) {
		return array();
	}

	/**
	 * Can a given item drill down into the "next" item?
	 *
	 * Parameters can only drill down further if their type is `object`.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item
	 * @return bool True if the given item can drill down into the "next" item; false otherwise.
	 */
	protected function has_view_next( $item ) {
		return ! empty( $item->properties );
	}

	/**
	 * Gext the text to display for the "View" row action.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected function get_view_text() {
		return __( 'Properties', 'shc-rest-api-inspector' );
	}

	protected function get_views() {
		return array();
	}

	/**
	 * Retrieves the view parameters link for an endpoint.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint item.
	 * @return string      The view parameters URL for the given endpoint.
	 */
	protected function get_view_next_link( $item ) {
		return add_query_arg(
			array(
				'page'     => $_REQUEST['page'],
				'schema-properties'    => urlencode( wp_unslash( $_REQUEST['schema'] ) ),
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Retrieves the view parameters link for an endpoint.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint item.
	 * @return string      The view parameters URL for the given endpoint.
	 */
	protected function get_view_links_link( $item ) {
		return add_query_arg(
		array(
			'page'     => $_REQUEST['page'],
			'schema-links'    => urlencode( wp_unslash( $_REQUEST['schema'] ) ),
		),
		admin_url( 'tools.php' )
		);
	}
}
