<?php

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class used to implement displaying REST API parameters in a list table.
 *
 * @since 0.1.0
 *
 * @link WP_REST_API_List_Table
 */
class WP_REST_Parameters_List_Table extends WP_REST_API_List_Table {
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
	 *                            in the list table, e.g. 'parameters'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'parameter'.
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
			'singular' => 'parameter',
			'plural'   => 'parameters',
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
			'name'            => __( 'Name', 'shc-rest-api-inspector' ),
			'type'            => __( 'Type', 'shc-rest-api-inspector' ),
			'array_items_type'=> __( 'Array Items Type', 'shc-rest-api-inspector' ),
			'description'     => __( 'Description', 'shc-rest-api-inspector' ),
			'default_value'   => __( 'Default', 'shc-rest-api-inspector' ),
			'enum'            => __( 'Enum', 'shc-rest-api-inspector' ),
		);

		return parent::get_columns() + $columns;
	}

	/**
	 * Handles the array items type column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	function column_array_items_type( $item ) {
		$array_items_types = array();

		foreach ( $item->array_items_type as $array_items_type ) {
			$url = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'tools.php' ) );
			if ( ! empty( $_REQUEST['route'] ) ) {
				$url = add_query_arg( 'route', urlencode( $_REQUEST['route'] ), $url );
			}
			if ( ! empty( $_REQUEST['endpoint'] ) ) {
				$url = add_query_arg( 'endpoint', $_REQUEST['endpoint'], $url );
			}
			if ( ! empty( $_REQUEST['methods'] ) ) {
				$url = add_query_arg( 'methods', wp_unslash( $_REQUEST['methods'] ), $url );
			}
			$array_items_types[] = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg( 'array_items_type', $array_items_type, $url ),
				$array_items_type
			);
		}

		return implode( '<br />', $array_items_types );
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

	/**
	 * Retrieves the view properties link for a parameter.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current parameter item.
	 * @return string      The view properties URL for the given parameter.
	 */
	protected function get_view_next_link( $item ) {
		return add_query_arg(
			array(
				'page'      => $_REQUEST['page'],
				'route'     => urlencode( wp_unslash( $_REQUEST['route'] ) ),
				'endpoint'  => $_REQUEST['endpoint'],
				'parameter' => $item->name,
			),
			admin_url( 'tools.php' )
		);
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
		$this->array_items_type_dropdown();

		return;
	}

	/**
	 * Display an array items types dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function array_items_type_dropdown() {
		$array_item_types = $options = array();
		foreach ( $this->all_items as $item ) {
			$array_item_types = array_merge( $array_item_types, $item->array_items_type );
		}

		if ( empty( $array_item_types ) ) {
			// essentially, the same as the 'hide_if_empty' arg on core's category_dropdown().
//			return;
		}

		foreach ( array_unique( $array_item_types ) as $array_item_type ) {
			$options[ $array_item_type ] = $array_item_type;
		}

		$this->dropdown(
			'array_items_type',
			__( 'Filter by array item type', 'shc-rest-api-inspector' ),
			__( 'All array item types', 'shc-rest-api-inspector' ),
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
			'required' => __( 'Required', 'shc-rest-api-inspector' ),
			'optional' => __( 'Optional', 'shc-rest-api-inspector' ),
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

		foreach ( array( 'required' => true, 'optional' => false ) as $view => $value ) {
			$avail_views[ $view ] = count( wp_list_filter( $this->all_items, array( 'required' => $value ) ) );
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

		if ( true === $item->required && 'required' !== $item_status ) {
			$item_states['required'] = __( 'Required', 'shc-rest-api-inspector' );
		}

		return $item_states;
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
}
