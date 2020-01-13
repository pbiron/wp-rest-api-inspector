<?php

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class used to implement displaying REST API routes in a list table.
 *
 * @since 0.1.0
 *
 * @link WP_REST_API_List_Table
 */
class WP_REST_Routes_List_Table extends WP_REST_API_List_Table {
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
	 *                            in the list table, e.g. 'routes'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'route'.
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
			'singular' => 'route',
			'plural'   => 'routes',
		);

		$args = wp_parse_args( $args, $defaults );

		parent::__construct( $args );

//		$this->hierarchical_display = true;
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
			'name'      => __( 'Route', 'shc-rest-api-inspector' ),
			'parent'      => __( 'Parent', 'shc-rest-api-inspector' ),
			'methods'   => __( 'Methods', 'shc-rest-api-inspector' ),
			'namespace' => __( 'Namespace', 'shc-rest-api-inspector' ),
			'links'     => __( 'Links', 'shc-rest-api-inspector' ),
			'endpoints' => __( 'Endpoints', 'shc-rest-api-inspector' ),
		);

		return parent::get_columns() + $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		$columns = array(
			'namespace' => 'namespace',
			'endpoints' => 'endpoints',
		);

		return parent::get_sortable_columns() + $columns;
	}

	/**
	 * Handles the namespace column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current route object.
	 */
	function column_namespace( $item ) {
		return sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				array(
					'page'      => $_REQUEST['page'],
					'namespace' => $item->namespace,
				),
				admin_url( 'tools.php' )
			),
			$item->namespace
		);
	}

	/**
	 * Handles the links column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current route object.
	 */
	function column_links( $item ) {
		$links = array();

		foreach ( $item->_links as $rel => $url ) {
			$links[] = sprintf(
				'<a href="%1$s" target="_blank">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true"></span></a>',
				esc_url( $url ),
				esc_html( $rel ),
				/* translators: Accessibility text. */
				__( '(opens in a new tab)' )
			);
		}

		return implode( '<br />', $links );
	}

	/**
	 * Handles the endpoints column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current route object.
	 */
	function column_endpoints( $item ) {
		return count( $item->endpoints );
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
		$this->namespaces_dropdown();
		$this->link_types_dropdown();

		return;
	}

	/**
	 * Display a link types dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function link_types_dropdown() {
		$link_types = $options = array();

		foreach ( $this->all_items as $item ) {
			$link_types = array_merge( $link_types, array_keys( $item->_links ) );
		}

		if ( empty( $link_types ) ) {
// essentially, the same as the 'hide_if_empty' arg on core's category_dropdown().
//			return;
		}

		foreach ( array_unique( $link_types ) as $link_rel ) {
			$options[ $link_rel ] = $link_rel;
		}

		$this->dropdown(
			'link_type',
			__( 'Filter by link type', 'shc-rest-api-inspector' ),
			__( 'All link types', 'shc-rest-api-inspector' ),
			$options
		);

		return;
	}

	/**
	 * Gext the text to display for the "View" row action.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected function get_view_text() {
		return __( 'Endpoints', 'shc-rest-api-inspector' );
	}

	/**
	 * Retrieves the view endpoints link for a route.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint item.
	 * @return string      The view endpoints URL for the given route.
	 */
	protected function get_view_next_link( $item ) {
		return add_query_arg(
			array(
				'page' => $_REQUEST['page'],
				'route' => urlencode( $item->name ),
				),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Retrieves the view schema link for a route.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current endpoint item.
	 * @return string      The view endpoints URL for the given route.
	 */
	protected function get_view_schema_link( $item ) {
		return add_query_arg(
			array(
				'page' => $_REQUEST['page'],
				'schema' => urlencode( $item->name ),
			),
			admin_url( 'tools.php' )
		);
	}

	protected function _get_row_actions( $item ) {
		$actions = array();

		if ( ! empty( $item->schema ) ) {
			$actions['view-schema'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				$this->get_view_schema_link( $item ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View Schema for &#8220;%s&#8221;' ), $item->name ) ),
				__( 'Schema', 'shc-rest-api-inspector' )
			);

			$handbook_url = $this->get_handbook_url( $item );
			if ( $handbook_url ) {
				$actions['view-handbook'] = sprintf(
					'<a href="%1$s" target="_blank">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true"></span></a>',
					esc_url( $handbook_url ),
					esc_html( __( 'Handbook', 'shc-rest-api-inspector') ),
					/* translators: Accessibility text. */
					__( '(opens in a new tab)' )
				);
			}
		}

		return $actions;
	}

	protected function get_handbook_url( $item ) {
		$url = '';
		// this would be SOOOOO much easier if there were a 1-to-1 mapping between schema->title
		// and the handbook URL :-(
		switch ( $item->schema->title ) {
			case 'post':
				$url = 'posts';
				break;
			case 'page':
				$url = 'pages';
				break;
			case 'post-revision':
			case 'page-revision':
				$url = 'post-revisions';
				break;
			case 'attachment':
				$url = 'media';
				break;
			case 'wp_block':
				$url = 'wp_blocks';
				break;
			case 'wp_block-revision':
				$url = 'wp_block-revisions';
				break;
			case 'type':
				$url = 'post-types';
				break;
			case 'status':
				$url = 'post-statuses';
				break;
			case 'taxonomy':
				$url = 'taxonomies';
				break;
			case 'category':
				$url = 'categories';
				break;
			case 'tag':
				$url = 'tags';
				break;
			case 'user':
				$url = 'users';
				break;
			case 'comment':
				$url = 'comments';
				break;
			case 'search-result':
				$url = 'search-results';
				break;
			case 'rendered-block':
				$url = 'rendered-blocks';
				break;
			case 'settings':
				$url = 'settings';
				break;
			case 'theme':
				$url = 'themes';
				break;
		}

		if ( $url ) {
			$url = "https://developer.wordpress.org/rest-api/reference/{$url}/";
		}

		return $url;
	}
}
