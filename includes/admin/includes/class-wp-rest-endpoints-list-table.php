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
class WP_REST_Endpoints_List_Table extends WP_REST_API_List_Table {
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
			'singular' => 'endpoint',
			'plural'   => 'endpoints',
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
			'methods'       => __( 'Methods', 'shc-rest-api-inspector' ),
			'permission_callback' => __( 'Permission Callback', 'shc-rest-api-inspector' ),
			'callback'      => __( 'Callback', 'shc-rest-api-inspector' ),
			'show_in_index' => __( 'Show in Index', 'shc-rest-api-inspector' ),
			'accept_json'   => __( 'Accept JSON', 'shc-rest-api-inspector' ),
			'accept_raw'    => __( 'Accept RAW', 'shc-rest-api-inspector' ),
		);

		return parent::get_columns() + $columns;
	}

	/**
	 * Handles the permission_callback column output.
	 *
	 * Produces a link to the WP Code Reference for the callback, which of course
	 * will be incorrect for non-core endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint object.
	 *
	 * @todo figure out a reliable way to distinquish core from non-core endpoints
	 *       and just output the callback "name" for non-core endpoints.
	 */
	function column_permission_callback( $item ) {
		return $this->_get_code_reference_link( $item->permission_callback );
	}

	/**
	 * Handles the callback column output.
	 *
	 * Produces a link to the WP Code Reference for the callback, which of course
	 * will be incorrect for non-core endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint object.
	 *
	 * @todo figure out a reliable way to distinquish core from non-core endpoints
	 *       and just output the callback "name" for non-core endpoints.
	 */
	function column_callback( $item ) {
		return $this->_get_code_reference_link( $item->callback );
	}

	protected function _get_code_reference_link( $callback ) {
		$url = $this->_get_code_reference_url( $callback );

		if ( is_array( $callback ) ) {
			if ( is_string( $callback[0] ) ) {
				// not a core route.
				// nothing to do, so bail.
				// @todo this isn't 100% reliable, but catches the case of
				//       of the endpoint(s) registered by the wp-rest-api-log plugin.
				return sprintf( '%s::%s()', $callback[0], $callback[1] );
			}

			$link_text = sprintf( '%s::%s()', get_class( $callback[0] ), $callback[1] );
		}
		else {
			$link_text = $callback;
		}

		if ( $url ) {
			return sprintf(
				'<a href="%1$s" target="_blank">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true"></span></a>',
				esc_url( $url ),
				esc_html( $link_text ),
				/* translators: Accessibility text. */
				__( '(opens in a new tab)' )
			);
		}
		else {
			return $link_text;
		}
	}

	/**
	 * Get the URL for a callback in the WP Code Reference.
	 *
	 * @since 0.1.0
	 *
	 * @param callable $callback The callback to get the Code Reference URL for.
	 * @return string
	 *
	 * @todo add error checking to the Reflection stuff.
	 * @todo see if there is a way to validate that the URL actually resolves to
	 *       something in the Code Reference without doing a HEAD request...which
	 *       could get expensive (and probably piss-off the systems folks).
	 */
	protected function _get_code_reference_url( $callback ) {
		if ( is_array( $callback ) ) {
			if ( ! $this->is_core_class( $callback[0] ) ) {
				return '';
			}

			$method          = new ReflectionMethod( $callback[0], $callback[1] );
			$declaring_class = $method->getDeclaringClass()->name;

			$url = sprintf(
				'https://developer.wordpress.org/reference/classes/%s/%s/',
				strtolower( $declaring_class ),
				$callback[1]
			);
		}
		else {
			$url = sprintf(
				'https://developer.wordpress.org/reference/functions/%s/',
				$callback
			);
		}

		return $url;
	}

	/**
	 * Is a given class one of the core REST API classes (e.g., controllers)?
	 *
	 * This is used to know whether to output a link to WP Code Reference entry for callbacks.
	 *
	 * @since 0.1.0
	 *
	 * @param object|string $class THe class to check.
	 * @return bool True if `$class` is a core class, false otherwise.
	 */
	protected function is_core_class( $class ) {
		if ( is_object( $class ) ) {
			$class = get_class( $class );
		}

		switch ( $class ) {
			// the main rest server.
			case 'WP_REST_Server':
			// the core controllers.
			case 'WP_REST_Attachments_Controller':
			case 'WP_REST_Autosaves_Controller':
			case 'WP_REST_Block_Renderer_Controller':
			case 'WP_REST_Blocks_Controller':
			case 'WP_REST_Comments_Controller':
			case 'WP_REST_Post_Statuses_Controller':
			case 'WP_REST_Post_Types_Controller':
			case 'WP_REST_Posts_Controller':
			case 'WP_REST_Revisions_Controller':
			case 'WP_REST_Search_Controller':
			case 'WP_REST_Settings_Controller':
			case 'WP_REST_Taxonomies_Controller':
			case 'WP_REST_Terms_Controller':
			case 'WP_REST_Themes_Controller':
			case 'WP_REST_Users_Controller':
				return true;
			default:
				return false;
		}
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
		return ! empty( $item->args );
	}

	/**
	 * Gext the text to display for the "View" row action.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected function get_view_text() {
		return __( 'Parameters', 'shc-rest-api-inspector' );
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
				'route'    => urlencode( wp_unslash( $_REQUEST['route'] ) ),
				'endpoint' => implode( ' | ', $item->methods ),
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
		$this->methods_dropdown();

		return;
	}
}
