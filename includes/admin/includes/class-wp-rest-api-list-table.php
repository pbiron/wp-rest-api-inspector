<?php

namespace SHC\REST_API_Inspector;

use WP_List_Table;

defined( 'ABSPATH' ) || die;

/**
 * Class used to implement displaying REST API items in a list table.
 *
 * This should be sub-classed for each specific item type.
 *
 * @since 0.1.0
 *
 * @link WP_List_Table
 */
class WP_REST_API_List_Table extends WP_List_Table {
	/**
	 * Whether the items should be displayed hierarchically or linearly.
	 *
	 * @since 0.1.0
	 *
	 * @var bool
	 */
	protected $hierarchical_display;

	/**
	 * Current level for output.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $current_level = 0;

	/**
	 * Array of all items of the current item type.
	 *
	 * This is used to generate the item status views.  Core list tables
	 * generally have better ways to achieve that, but this works for now.
	 *
	 * @see WP_REST_API_List_Table::avail_views()
	 *
	 * @since 0.1.0
	 *
	 * @var object[]
	 */
	protected $all_items = array();

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @since 0.1.0
	 */
	function prepare_items() {
		$per_page = $this->get_items_per_page( "{$this->screen->rest_item_type}_per_page" );
		$paged = $this->get_pagenum();

		$args = array(
			'item_type' => $this->screen->rest_item_type,
			'number'    => $per_page,
			'offset'    => ( $paged - 1 ) * $per_page,
			'search'    => ! empty( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '',
		);

		if ( ! empty( $_REQUEST['route'] ) ) {
			$args['route'] = wp_unslash( $_REQUEST['route'] );
		}

		if ( ! empty( $_REQUEST['schema'] ) ) {
			$args['route'] = wp_unslash( $_REQUEST['schema'] );
			$args['item_type'] = 'schema';
		}

		if ( ! empty( $_REQUEST['schema-properties'] ) ) {
			$args['route'] = wp_unslash( $_REQUEST['schema-properties'] );
			$args['item_type'] = 'schema_property';
		}

		if ( ! empty( $_REQUEST['schema-links'] ) ) {
			$args['route'] = wp_unslash( $_REQUEST['schema-links'] );
			$args['item_type'] = 'schema_link';
		}

		if ( ! empty( $_REQUEST['namespace'] ) ) {
			$args['namespace'] = wp_unslash( $_REQUEST['namespace'] );
		}

		if ( ! empty( $_REQUEST['method'] ) ) {
			$args['method'] = wp_unslash( $_REQUEST['method'] );
		}

		if ( ! empty( $_REQUEST['endpoint'] ) ) {
			$args['endpoint'] = wp_unslash( $_REQUEST['endpoint'] );
		}

		if ( ! empty( $_REQUEST['item_status'] ) ) {
			$args['item_status'] = wp_unslash( $_REQUEST['item_status'] );
		}

		if ( ! empty( $_REQUEST['type'] ) ) {
			$args['type'] = wp_unslash( $_REQUEST['type'] );
		}

		if ( ! empty( $_REQUEST['parameter'] ) ) {
			$args['parameter'] = wp_unslash( $_REQUEST['parameter'] );
		}

		if ( ! empty( $_REQUEST['context'] ) ) {
			$args['context'] = wp_unslash( $_REQUEST['context'] );
		}

		if ( ! empty( $_REQUEST['array_items_type'] ) ) {
			$args['array_items_type'] = wp_unslash( $_REQUEST['array_items_type'] );
		}

		if ( ! empty( $_REQUEST['link_type'] ) ) {
			$args['link_type'] = wp_unslash( $_REQUEST['link_type'] );
		}

		if ( ! empty( $_REQUEST['format'] ) ) {
			$args['format'] = wp_unslash( $_REQUEST['format'] );
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			$args['order'] = $_REQUEST['order'];
		}

		/**
		 * Filters the query arguments used to retrieve REST API items for the current list table.
		 *
		 * @since 0.1.0
		 *
		 * @param array $args Arguments passed to WP_Rest_API_Query to retrieve items for the current
		 *                    list table.
		 */
		$args = apply_filters( 'rest_api_list_table_query_args', $args );

		// Query the user IDs for this page
		$rest_api_search = new WP_REST_API_Query( $args );

		$this->items     = $rest_api_search->get_results();
		$this->all_items = $rest_api_search->get_all_results();

		$this->set_pagination_args(
			array(
				'total_items' => $rest_api_search->get_total(),
				'per_page'    => $per_page,
			)
		);

		return;
	}

	/**
	 * @param object[] $items
	 * @param int $level
	 * @return void
	 */
	public function display_rows1( $items = array(), $level = 0 ) {
		if ( $this->hierarchical_display ) {
			if ( empty ( $items ) ) {
				$items = $this->all_items;
			}
			$this->_display_rows_hierarchical( $items, $this->get_pagenum(), 20 );
		}
		else {
			if ( empty ( $items ) ) {
				$items = $this->items;
			}
			foreach ( $items as $item ) {
				$this->single_row( $item, $level );
			}
		}

		return;
	}

	/**
	 * @param array $items
	 * @param int $pagenum
	 * @param int $per_page
	 */
	protected function _display_rows_hierarchical( $items, $pagenum = 1 ) {
		$level = 0;

		if ( ! $items ) {
			return;
		}

		/*
		 * Arrange pages into two parts: top level pages and children_pages
		 * children_pages is two dimensional array, eg.
		 * children_pages[10][] contains all sub-pages whose parent is 10.
		 * It only takes O( N ) to arrange this and it takes O( 1 ) for subsequent lookup operations
		 * If searching, ignore hierarchy and treat everything as top level
		 */
		if ( empty( $_REQUEST['s'] ) ) {
			$top_level_items = array();
			$children_items  = array();

			foreach ( $items as $item ) {
				if ( '' === $item->parent ) {
					$top_level_items[] = $item;
				}
				else {
					$children_items[ $item->parent ][] = $item;
				}
			}

			$items = &$top_level_items;
		}

		$per_page   = $this->get_pagination_arg( 'per_page' );
		$count      = 0;
		$start      = ( $pagenum - 1 ) * $per_page;
		$end        = $start + $per_page;
		$to_display = array();

		foreach ( $items as $item ) {
			if ( $count >= $end ) {
				break;
			}

			if ( $count >= $start ) {
				$to_display[ $item->name ] = $level;
			}

			$count++;

			if ( isset( $children_items ) ) {
				$this->_item_rows( $children_items, $count, $item->name, $level + 1, $pagenum, $per_page, $to_display );
			}
		}

		// If it is the last pagenum and there are orphaned pages, display them with paging as well.
		if ( isset( $children_items ) && $count < $end ) {
			foreach ( $children_items as $orphans ) {
				foreach ( $orphans as $op ) {
					if ( $count >= $end ) {
						break;
					}

					if ( $count >= $start ) {
						$to_display[ $op->name ] = 0;
					}

					$count++;
				}
			}
		}

		foreach ( $to_display as $name => $level ) {
			echo "\t";
			$this->single_row( $this->all_items[ $name ], $level );
		}

		return;
	}

	/**
	 * @global WP_Post $post Global post object.
	 *
	 * @param int|WP_Post $item
	 * @param int         $level
	 */
	public function single_row( $item, $level = 0 ) {
		$this->current_level = $level;

		$classes = '';
		if ( isset( $item->parent ) ) {
			$count    = count( get_post_ancestors( $item->name ) );
			$classes .= ' level-' . $count;
		} else {
			$classes .= ' level-0';
		}
		?>
		<tr id="item-<?php echo $item->name; ?>" class="<?php echo $classes; ?>">
			<?php $this->single_row_columns( $item ); ?>
		</tr>
		<?php

		return;
	}

	/**
	 * Given a top level page ID, display the nested hierarchy of sub-pages
	 * together with paging support
	 *
	 * @since 3.1.0 (Standalone function exists since 2.6.0)
	 * @since 4.2.0 Added the `$to_display` parameter.
	 *
	 * @param array $children_items
	 * @param int $count
	 * @param int $parent
	 * @param int $level
	 * @param int $pagenum
	 * @param int $per_page
	 * @param array $to_display List of pages to be displayed. Passed by reference.
	 */
	private function _item_rows( &$children_items, &$count, $parent, $level, $pagenum, $per_page, &$to_display ) {
		if ( ! isset( $children_items[ $parent ] ) ) {
			return;
		}

		$start = ( $pagenum - 1 ) * $per_page;
		$end   = $start + $per_page;

		foreach ( $children_items[ $parent ] as $item ) {
			if ( $count >= $end ) {
				break;
			}

			// If the page starts in a subtree, print the parents.
			if ( $count == $start && '' !== $item->parent ) {
				$my_parents = array();
				$my_parent  = $item->parent;
				while ( $my_parent ) {
					// Get the ID from the list or the attribute if my_parent is an object
					$parent_id = $my_parent;
					if ( is_object( $my_parent ) ) {
						$parent_id = $my_parent;
					}

					$my_parent    = $this->get_item( $parent_id );
					$my_parents[] = $my_parent;
					if ( ! $my_parent ) {
						break;
					}
					$my_parent = $my_parent->parent;
				}
				$num_parents = count( $my_parents );
				while ( $my_parent = array_pop( $my_parents ) ) {
					$to_display[ $my_parent->name ] = $level - $num_parents;
					$num_parents--;
				}
			}

			if ( $count >= $start ) {
				$to_display[ $item->name ] = $level;
			}

			$count++;

			$this->_item_rows( $children_items, $count, $item->name, $level + 1, $pagenum, $per_page, $to_display );
		}

		unset( $children_items[ $parent ] ); //required in order to keep track of orphans

		return;
	}

	protected function get_item( $name ) {
		return $this->all_items[ $name ];
	}

	/**
	 * Retrieves the view "next" link for an item.
	 *
	 * What the "next" is depends on the item type for the given item.
	 * If the given item is a route, then the "next" is the endpoints for
	 * that route; if the given is an endpoint, then the "next" is the
	 * parameters for that endpoint, etc.
	 *
	 * This method is similar to core's
	 * {@link https://developer.wordpress.org/reference/functions/get_edit_post_link/ get_edit_post_link()}.
	 * However, the analogy is pretty tenuous, so maybe it should be renamed to
	 * avoid confusion.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item.
	 * @return string      The view "next" URL for the given item.
	 */
	protected function get_view_next_link( $item ) {
		die( 'function WP_REST_API_List_Table::get_view_next_link() must be over-ridden in a sub-class.' );
	}

	/**
	 * Return an associative array listing all the views that can be used
	 * with this table.
	 *
	 * @since  0.1.0
	 *
	 * @return array An array of HTML links, one for each view.
	 */
	protected function get_views() {
		$total_items = count( $this->all_items );
		$all_views   = $this->_all_views();
		$avail_views = $this->_avail_views();

		$view = ! empty( $_REQUEST['item_status'] ) ?  $_REQUEST['item_status'] : '';
		$current_link_attributes = empty( $view ) ? ' class="current" aria-current="page"' : '';

		$url = add_query_arg( array( 'page' => $_REQUEST['page'] ), admin_url( 'tools.php' ) );
		if ( ! empty( $_REQUEST['route'] ) ) {
			$url = add_query_arg( 'route', urlencode( wp_unslash( $_REQUEST['route'] ) ), $url );
		}
		if ( ! empty( $_REQUEST['endpoint'] ) ) {
			$url = add_query_arg( 'endpoint', $_REQUEST['endpoint'], $url );
		}
		if ( ! empty( $_REQUEST['methods'] ) ) {
			$url = add_query_arg( 'methods', $_REQUEST['methods'], $url );
		}
		if ( ! empty( $_REQUEST['parameter'] ) ) {
			$url = add_query_arg( 'parameter', $_REQUEST['parameter'], $url );
		}
		if ( ! empty( $_REQUEST['schema-properties'] ) ) {
			$url = add_query_arg( 'schema-properties', urlencode( wp_unslash( $_REQUEST['schema-properties'] ) ), $url );
		}

		$view_links        = array();
		$view_links['all'] = sprintf(
			'<a href="%s"%s>%s</a>',
			$url,
			$current_link_attributes,
			sprintf(
				/* translators: %s: Number of users. */
				_nx(
					'All <span class="count">(%s)</span>',
					'All <span class="count">(%s)</span>',
					$total_items,
					'rest_items'
				),
				number_format_i18n( $total_items )
			)
		);

		foreach ( $all_views as $this_view => $name ) {
			if ( ! isset( $avail_views[ $this_view ] ) || 0 === $avail_views[ $this_view ] ) {
				continue;
			}

			$current_link_attributes = '';

			if ( $this_view === $view ) {
				$current_link_attributes = ' class="current" aria-current="page"';
			}

			$name = sprintf(
				/* translators: User role name with count. */
				__( '%1$s <span class="count">(%2$s)</span>' ),
				$name,
				number_format_i18n( $avail_views[ $this_view ] )
			);

			$view_links[ $this_view ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'item_status', $this_view, $url ) ),
				$current_link_attributes,
				$name
			);
		}

		return $view_links;
	}

	/**
	 * Display a methods dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function methods_dropdown() {
		$this->dropdown(
			'method',
			__( 'Filter by method', 'shc-rest-api-inspector' ),
			__( 'All methods', 'shc-rest-api-inspector' ),
			array(
				'GET'    => 'GET',
				'POST'   => 'POST',
				'PUT'    => 'PUT',
				'PATCH'  => 'PATCH',
				'DELETE' => 'DELETE',
			)
		);

		return;
	}

	/**
	 * Display a namespaces dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function namespaces_dropdown() {
		$rest_server = rest_get_server();
		$_namespaces = $rest_server->get_namespaces();

		$options = array();
		foreach ( $_namespaces as $namespace ) {
			$options[ $namespace ] = $namespace;
		}

		$this->dropdown(
			'namespace',
			__( 'Filter by namespace', 'shc-rest-api-inspector' ),
			__( 'All namespaces', 'shc-rest-api-inspector' ),
			$options
		);

		return;
	}

	/**
	 * Display a types dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @eturn void
	 */
	protected function types_dropdown() {
		$types = $options = array();
		foreach ( $this->all_items as $item ) {
			$types = array_merge( $types, $item->type );
		}

		if ( empty( $types ) ) {
			// essentially, the same as the 'hide_if_empty' arg on core's category_dropdown().
//			return;
		}

		foreach ( array_unique( $types ) as $type ) {
			$options[ $type ] = $type;
		}

		$this->dropdown(
			'type',
			__( 'Filter by type', 'shc-rest-api-inspector' ),
			__( 'All types', 'shc-rest-api-inspector' ),
			$options
		);

		return;
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
		$columns = array();

		// we don't have any bulk actions by default.  If someone has added them
		// by hooking into bulk_actions-{$this->screen->id}, then make sure we
		// have a checkbox column.
		if ( has_filter( "bulk_actions-{$this->screen->id}" ) ) {
			$columns = array( 'cb' => '<input type="checkbox" />' ) + $columns;
		}

		return $columns;
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
		return array(
			'name' => 'name',
		);
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 0.1.0
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @since 0.1.0
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
 ?>
		<div class="alignleft actions">
<?php
		if ( 'top' === $which && ! is_singular() ) {
			ob_start();

			$this->do_dropdowns();

			/**
			 * Fires before the Filter button on the Posts and Pages list tables.
			 *
			 * The Filter button allows sorting by date and/or category on the
			 * Posts list table, and sorting by date on the Pages list table.
			 *
			 * @since 0.1.0
			 *
			 * @param string $rest_item_type The rest item type slug.
			 * @param string $which          The location of the extra table nav markup:
			 *                               'top' or 'bottom'.
			 */
			do_action( 'restrict_manage_rest_items', $this->screen->rest_item_type, $which );

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'rest-item-query-submit' ) );
			}
		}
 ?>
		</div>
<?php
		/**
		 * Fires immediately following the closing "actions" div in the tablenav for the rest api
		 * list table.
		 *
		 * @since 0.1.0
		 *
		 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
		 */
		do_action( 'manage_rest_items_extra_tablenav', $which );

		return;
	}

	/**
	 * Handles the default column output.
	 *
	 * Note: This method overrides the one in `WP_List_Table` and is not to be confused
	 * with the column in the `WP_REST_Parameters_List_Table` labeled "Default":
	 * that column actually has the name 'default_value' to avoid the ambiguity.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	protected function column_default( $item, $column_name ) {
		if ( is_array( $item->{$column_name} ) ) {
			return implode( '<br />', array_map( 'esc_html', $item->{$column_name} ) );
		}
		elseif ( is_bool( $item->{$column_name} ) ) {
			return esc_html(
				$item->{$column_name} ?
					__( 'true', 'shc-rest-api-inspector' ) :
					__( 'false', 'shc-rest-api-inspector' ) );
		}
		else {
			return esc_html( $item->{$column_name} );
		}
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	function column_cb( $item ) {
 ?>
<label class="screen-reader-text" for="cb-select-<?php echo $item->name ?>">
<?php
			/* translators: %s: Post title. */
			printf( __( 'Select %s' ), esc_html( $item->name ) );
 ?>
</label>
<input id="cb-select-<?php echo esc_attr( $item->name ) ?>" type="checkbox" name="name[]" value="<?php echo esc_attr( $item->name ) ?>" />
<?php

		return;
	}

	/**
	 * Generates and display row actions links for the list table.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item        The item being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string The row actions HTML, or an empty string if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = array();
		if ( $this->has_view_next( $item ) ) {
			$actions['view'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				$this->get_view_next_link( $item ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $item->name ) ),
				$this->get_view_text()//__( 'View' )
			);
		}

		$actions = array_merge( $actions, $this->_get_row_actions( $item ) );

		/**
		 * Filters the array of row action links on the Pages list table.
		 *
		 * The filter is evaluated only for hierarchical post types.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $actions An array of row action links. Defaults are 'View'.
		 * @param object  $item    The rest api item object.
		 */
		$actions = apply_filters( "rest_{$this->screen->rest_item_type}_row_actions", $actions, $item );

		/**
		 * Filters the array of row action links on the Pages list table.
		 *
		 * The filter is evaluated only for hierarchical post types.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $actions An array of row action links. Defaults are 'View'.
		 * @param object  $item    The rest api item object.
		 */
		$actions = apply_filters( 'rest_item_row_actions', $actions, $item );

		return $this->row_actions( $actions );
	}

	protected function _get_row_actions( $item ) {
		$actions = array();

		if ( $this->has_view_next( $item ) ) {
			$actions['view'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				$this->get_view_next_link( $item ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $item->name ) ),
				$this->get_view_text()//__( 'View' )
			);
		}

		return $actions;
	}

	/**
	 * Handles the title column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	function column_name( $item ) {
		if ( $this->hierarchical_display ) {
			if ( 0 === $this->current_level && '' !== $item->parent ) {
				// Sent level 0 by accident, by default, or because we don't know the actual level.
				$find_main_page = $item->parent;
				while ( '' !== $find_main_page ) {
					$parent = $this->get_item( $find_main_page );

					if ( is_null( $parent ) ) {
						break;
					}

					$this->current_level++;
					$find_main_page = $parent->parent;

 					if ( ! isset( $parent_name ) ) {
 						$parent_name = $parent->name;
 					}
				}
			}
		}

		$pad = str_repeat( '&#8212; ', $this->current_level );
		echo '<strong>';

		if ( $this->has_view_next( $item ) ) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				$this->get_view_next_link( $item ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (View)' ), $item->name ) ),
				$pad,
				esc_html( $item->name )
			);
		}
		else {
			printf(
				'<span>%s%s</span>',
				$pad,
				esc_html( $item->name )
			);
		}

		$this->_item_states( $item );

		if ( isset( $parent_name ) ) {
			echo ' | ' . ':' . ' ' . esc_html( $parent_name );
		}

		echo '</strong>';

		return;
	}

	/**
	 * Handles the methods column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	function column_methods( $item ) {
		$methods = $_methods = array();

		foreach ( $item->methods as $method ) {
			$method = explode( ' | ', $method );
			$_methods = array_merge( $_methods, $method );
		}
		foreach ( $_methods as $method ) {
			$url = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'tools.php' ) );
			if ( ! empty( $_REQUEST['route'] ) ) {
				$url = add_query_arg( 'route', urlencode( wp_unslash( $_REQUEST['route'] ) ), $url );
			}
			$methods[] = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg( 'method', $method, $url ),
				$method
			);
		}

		return implode( '<br />', $methods );
	}

	/**
	 * Handles the type column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current item object.
	 */
	function column_type( $item ) {
		$types = array();

		foreach ( $item->type as $type ) {
			$url = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'tools.php' ) );
			if ( ! empty( $_REQUEST['route'] ) ) {
				$url = add_query_arg( 'route', urlencode( $_REQUEST['route'] ), $url );
			}
			if ( ! empty( $_REQUEST['endpoint'] ) ) {
				$url = add_query_arg( 'endpoint', $_REQUEST['endpoint'], $url );
			}
			if ( ! empty( $_REQUEST['methods'] ) ) {
				$url = add_query_arg( 'methods', $_REQUEST['methods'], $url );
			}
			if ( ! empty( $_REQUEST['parameter'] ) ) {
				$url = add_query_arg( 'parameter', $_REQUEST['parameter'], $url );
			}
			if ( ! empty( $_REQUEST['schema-properties'] ) ) {
				$url = add_query_arg( 'schema-properties', urlencode( wp_unslash( $_REQUEST['schema-properties'] ) ), $url );
			}
			$types[] = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg( 'type', $type, $url ),
				$type
			);
		}

		return implode( '<br />', $types );
	}

	/**
	 * Handles the context column output.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The current route object.
	 */
	function column_context( $item ) {
		$contexts = array();

		foreach ( $item->context as $context ) {
			$url = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'tools.php' ) );
			if ( ! empty( $_REQUEST['route'] ) ) {
				$url = add_query_arg( 'route', urlencode( $_REQUEST['route'] ), $url );
			}
			if ( ! empty( $_REQUEST['methods'] ) ) {
				$url = add_query_arg( 'methods', $_REQUEST['methods'], $url );
			}
			if ( ! empty( $_REQUEST['parameter'] ) ) {
				$url = add_query_arg( 'parameter', $_REQUEST['parameter'], $url );
			}
			if ( ! empty( $_REQUEST['schema-properties'] ) ) {
				$url = add_query_arg( 'schema-properties', urlencode( wp_unslash( $_REQUEST['schema-properties'] ) ), $url );
			}
			$contexts[] = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg( 'context', $context, $url ),
				$context
			);
		}

		return implode( '<br />', $contexts );
	}

	/**
	 * Echo or return the post states as HTML.
	 *
	 * The name of this method comes from core's
	 * {@link https://developer.wordpress.org/reference/functions/_post_states _post_states().
	 *
	 * @since 0.1.0
	 *
	 * @see WP_REST_API_List_Table::get_item_states()
	 *
	 * @param object $item The REST API item to retrieve states for.
	 * @param bool    $echo Optional. Whether to echo the post states as an HTML string. Default true.
	 * @return string Item states string.
	 */
	function _item_states( $item, $echo = true ) {
		$item_states        = $this->get_item_states( $item );
		$item_states_string = '';

		if ( ! empty( $item_states ) ) {
			$state_count = count( $item_states );
			$i           = 0;

			$item_states_string .= ' &mdash; ';
			foreach ( $item_states as $state ) {
				++$i;
				( $i == $state_count ) ? $sep = '' : $sep = ', ';
				$item_states_string          .= "<span class='post-state'>$state$sep</span>";
			}
		}

		if ( $echo ) {
			echo $item_states_string;
		}

		return $item_states_string;
	}

	/**
	 * Retrieve an array of item states from a REST API item.
	 *
	 * The name of this method comes from core's
	 * {@link https://developer.wordpress.org/reference/functions/_post_states/ get_post_states()}.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item The item to retrieve states for.
	 * @return array The array of translated item states.
	 */
	protected function get_item_states( $item ) {
		$item_states = array();
		if ( isset( $_REQUEST['item_status'] ) ) {
			$item_status = $_REQUEST['item_status'];
		} else {
			$item_status = '';
		}

		$item_states = $this->_get_item_states( $item, $item_status );

		/**
		 * Filters the default item display states used in the REST API list table.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $item_states An array of item display states.
		 * @param object  $item        The current REST API item object.
		 */
		return apply_filters( 'display_rest_item_states', $item_states, $item );
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
			'maybe' => __( 'Maybe Authenticated', 'shc-rest-api-inspector' ),
			'yes'   => __( 'Authenticated', 'shc-rest-api-inspector' ),
			'no'    => __( 'Unauthenticated', 'shc-rest-api-inspector' ),
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

		foreach ( array( 'maybe', 'yes', 'no' ) as $view ) {
			$avail_views[ $view ] = count( wp_list_filter( $this->all_items, array( 'authenticated' => $view ) ) );
		}

		return $avail_views;
	}

	/**
	 * Helper for displaying dropdowns for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name for the select element.
	 * @param string $screen_reader_text The text for the screen reader.
	 * @param string $all_items_text The text for the "All items" option in the select element.
	 * @param array $options Associative array of options for the select element.
	 *                       Keys are option values and values are option labels.
	 *                       The labels should *not* be previously HTML escaped.
	 * @return void
	 */
	protected function dropdown( $name, $screen_reader_text, $all_items_text, $options ) {
		$displayed = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : '';
 ?>
		<label for="filter-by-<?php echo $name ?>" class="screen-reader-text"><?php echo esc_html( $screen_reader_text ) ?></label>
		<select name="<?php echo esc_attr( $name ) ?>" id="filter-by-<?php echo esc_attr( $name ) ?>">
			<option<?php selected( $displayed, '' ); ?> value=""><?php echo esc_html( $all_items_text ) ?></option>
<?php
		foreach ( $options as $value => $label ) {
 ?>
			<option<?php selected( $displayed, $value ); ?> value="<?php echo esc_attr( $value ) ?>"><?php echo esc_html( $label ) ?></option>
<?php
		}
 ?>
		</select>
<?php

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
		return;
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

		if ( 'maybe' === $item->authenticated && 'maybe' !== $item_status ) {
			$item_states['authenticated'] = __( 'Maybe Authenticated', 'shc-rest-api-inspector' );
		}
		if ( 'yes' === $item->authenticated && 'yes' !== $item_status ) {
			$item_states['authenticated'] = __( 'Authenticated', 'shc-rest-api-inspector' );
		}
		elseif ( 'no' === $item->authenticated && 'no' !== $item_status ) {
			$item_states['not_authenticated'] = __( 'Unauthenticated', 'shc-rest-api-inspector' );
		}

		return $item_states;
	}

	/**
	 * Can a given item drill down into the "next" item?
	 *
	 * For example, if the item is a route, then it can drill down into it's endpoints;
	 * if it is an endpoint, it can drill down into it's parameters, etc.
	 *
	 * @since 0.1.0
	 *
	 * @param object $item
	 * @return bool True if the given item can drill down into the "next" item; false otherwise.
	 */
	protected function has_view_next( $item ) {
		return true;
	}
}
