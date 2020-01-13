<?php

namespace SHC\REST_API_Inspector;

use stdClass;

defined( 'ABSPATH' ) || die;

/**
 * Class used for querying REST API routes, endpoints, parameters and properties.
 *
 * Loosely based on WP_User_Query.
 *
 * Not fully implemented, but enough for what WP_REST_API_List_Table (and its sub-classes)
 * need.
 *
 * @since 0.1.0
 *
 * @see WP_REST_API_Query::prepare_query() for information on accepted arguments.
 *
 * @todo in one sense it might simplify things in there were separate query classes for
 *       item_type...but that might also complicate things because there would probably
 *       be a lot of duplication.  Maybe could have the different item type be sub-classes
 *       of an abstract class to avoid duplication?
 */
class WP_REST_API_Query {
	/**
	 * Query vars, after parsing
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	public $query_vars = array();

	/**
	 * The list of munged routes.
	 *
	 * @since 0.1.0
	 *
	 * @var object[]
	 */
	protected $routes = array();

	/**
	 * List of found items.
	 *
	 * @since 0.1.0
	 *
	 * @var object[]
	 */
	protected $results;

	/**
	 * List of all items.
	 *
	 * @since 0.1.0
	 *
	 * @var object[]
	 */
	protected $all_results;

	/**
	 * Total number of found items for the current query.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	protected $total_items = 0;

	/**
	 * PHP5 constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param null|string|array $query Optional. The query variables.
	 */
	function __construct( $query = '' ) {
		if ( ! empty( $query ) ) {
			$this->prepare_query( $query );
			$this->query();
		}
	}

	/**
	 * Fills in missing query variables with default values.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Query vars, as passed to `WP_REST_API_Query`.
	 * @return array Complete query variables with undefined ones filled in with defaults.
	 */
	public static function fill_query_vars( $args ) {
		$defaults = array(
			'item_type'           => 'route',
			'item_status'         => '',
			'endpoint'            => '',
			'method'              => '',
			'search'              => '',
			'search_columns'      => array(),
			'orderby'             => '',
			'order'               => 'ASC',
			'offset'              => '',
			'number'              => '',
			'paged'               => 1,
			'count_total'         => true,
			'fields'              => 'all',
			'route'               => '',
			'schema'              => '',
			'type'                => '',
			'namespace'           => '',
			'parameter'           => '',
			'context'             => '',
			'array_items_type'    => '',
			'link_type'           => '',
			'format'              => '',
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Prepare the query variables.
	 *
	 * @since 0.1.0
	 *
	 * @param string|array $query {
	 *     Optional. Array or string of Query parameters.
	 *
	 *     @todo finish documenting the hash.
	 * }
	 * @return void
	 */
	function prepare_query( $query = array() ) {
		if ( empty( $this->query_vars ) || ! empty( $query ) ) {
			$this->query_limit = null;
			$this->query_vars  = $this->fill_query_vars( $query );
		}

		/**
		 * Fires before the WP_REST_API_Query has been parsed.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_API_Query $this The current WP_REST_API_Query instance,
		 *                                passed by reference.
		 */
		do_action( 'pre_get_rest_items', $this );

		// Ensure that query vars are filled after 'pre_get_rest_items'.
		$qv =& $this->query_vars;
		$qv =  $this->fill_query_vars( $qv );

		if ( ! empty( $qv['item_status'] ) ) {
			switch ( $qv['item_type'] ) {
				case 'route':
				case 'endpoint':
					$qv['item_status_column'] = 'authenticated';

					break;
				case 'parameter':
					$qv['item_status_column'] = 'required';
					$qv['item_status']        = 'required' === $qv['item_status'];

					break;
				case 'property':
				case 'schema_property':
					$qv['item_status_column'] = 'readonly';
					$qv['item_status']        = 'readonly' === $qv['item_status'];

					break;
			}
		}

		// sorting
		$qv['order'] = isset( $qv['order'] ) ? strtoupper( $qv['order'] ) : '';

		$qv['search'] = strtolower( trim( $qv['search'] ) );

		// fill in search_columns depending on the item_type.
		if ( ! empty( $qv['search'] ) && empty( $qv['search_columns'] ) ) {
			switch ( $qv['item_type'] ) {
				case 'route':
					$qv['search_columns'] = array(
						'scalar' => array(
							'name',
							'namespace',
						),
						'array'  => array(
							'methods'
						),
					);
					break;
				case 'endpoint':
					$qv['search_columns'] = array(
						'scalar' => array(
							'name',
						),
						'array' => array(
							'methods',
						)
					);
					break;
				case 'parameter':
					$qv['search_columns'] = array(
						'scalar' => array(
							'name',
							'description',
						),
						'array' => array(
							'type',
							'array_items_type',
							'default_value',
							'enum',
						),
					);
					break;
				case 'property':
				case 'schema_property':
					$qv['search_columns'] = array(
						'scalar' => array(
							'name',
							'description',
							'format',
						),
						'array'  => array(
							'type',
							'context',
						),
					);
					break;
				case 'schema_link':
					$qv['search_columns'] = array(
						'scalar' => array(
							'name',
							'href',
							'title',
						),
						'array' => array(
						)
					);
					break;
			}
		}

		// save the item type in a variable so the filter tag below are cleaner
		// for documentation purposes.
		$item_type = $qv['item_type'];

		/**
		 * Filters the columns to search in a WP_REST_API_Query search.
		 *
		 * The default columns depend on the item type.
		 *
		 * @since 0.1.0
		 *
		 * @param string[]          $search_columns Array of column names to be searched.
		 * @param string            $search         Text being searched.
		 * @param WP_REST_API_Query $this           The current WP_REST_API_Query instance.
		 */
		$qv['search_columns'] = apply_filters( "rest_{$item_type}_search_columns", $qv['search_columns'], $qv['search'], $this );

		/**
		 * Filters the columns to search in a WP_REST_API_Query search.
		 *
		 * The default columns depend on the item type.
		 *
		 * @since 0.1.0
		 *
		 * @param string[]          $search_columns Array of column names to be searched.
		 * @param string            $item_type      The item type being searched.
		 * @param string            $search         Text being searched.
		 * @param WP_REST_API_Query $this           The current WP_REST_API_Query instance.
		 */
		$qv['search_columns'] = apply_filters( 'rest_item_search_columns', $qv['search_columns'], $qv['item_type'], $qv['search'], $this );

		/**
		 * Fires after the WP_REST_API_Query has been parsed, and before
		 * the query is executed.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_API_Query $this The current WP_REST_API_Query instance,
		 *                                passed by reference.
		 */
		do_action_ref_array( 'pre_rest_item_query', array( &$this ) );

		return;
	}

	/**
	 * Execute the query, with the current variables.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function query() {
		// I know it is unefficient to all of the route data at this point
		// (including parameters and properties),
		// without know whether we'll need it for this particular query (e.g.,
		// if the query is for routes w/ namespace = 'foo', we don't really need
		// to massage the paramater data.
		// however, when I first started this plugin, I didn't know a whole lot
		// about what form the data contained in the rest server had, so I began by
		// exploring that...and wrote the _get_routes() method during that time.
		// when I started on this class, since I already had the data in that
		// format I just went ahead and used it, so that I could work on the other
		// aspects of this plugin (e.g., the list tables themselves).
		// eventually, I'll get around to coming back to this and making this query
		// class more efficient.
		$this->_get_routes();

		$qv =& $this->query_vars;

		/**
		 * Filters the items array before the query takes place.
		 *
		 * Return a non-null value to bypass WordPress's default REST API items queries.
		 * Filtering functions that require pagination information are encouraged to set
		 * the `total_users` property of the WP_REST_API_Query object, passed to the filter
		 * by reference. If WP_REST_API_Query does not perform a database query, it will not
		 * have enough information to generate these values itself.
		 *
		 * @since 0.1.0
		 *
		 * @param array|null $results Return an array of item data to short-circuit WP's REST
		 *                            API item query or null to allow WP to run its normal queries.
		 * @param WP_REST_API_Query $this The WP_REST_API_Query instance (passed by reference).
		 */
		list( $this->all_results, $this->results ) = apply_filters_ref_array( 'rest_items_pre_query', array( null, &$this ) );

		if ( null === $this->results ) {
			$this->all_results = $this->results = array();

			switch ( $this->query_vars['item_type'] ) {
				case 'route':
					$this->all_results = $this->results = $this->routes;

					break;
				case 'schema':
					if ( isset( $this->routes[ $qv['route'] ]->schema ) ) {
						$this->all_results = $this->results = array( $this->routes[ $qv['route'] ]->schema );
					}

					break;
				case 'schema_property':
					if ( isset( $this->routes[ $qv['route'] ]->schema->properties ) ) {
						$this->all_results = $this->results = $this->routes[ $qv['route'] ]->schema->properties;
					}

					break;
				case 'schema_link':
					if ( isset( $this->routes[ $qv['route'] ]->schema->links ) ) {
						$this->all_results = $this->results = $this->routes[ $qv['route'] ]->schema->links;
					}

					break;
				case 'endpoint':
					if ( isset( $this->routes[ $qv['route'] ]->endpoints ) ) {
						$this->all_results = $this->results = $this->routes[ $qv['route'] ]->endpoints;
					}

					break;
				case 'parameter':
					if ( isset( $this->routes[ $qv['route'] ]->endpoints[ $qv['endpoint'] ]->args ) ) {
						$this->all_results = $this->results = $this->routes[ $qv['route'] ]->endpoints[ $qv['endpoint'] ]->args;
					}

					break;
				case 'property':
					if ( isset( $this->routes[ $qv['route'] ]->endpoints[ $qv['endpoint'] ]->args[ $qv['parameter'] ]->properties ) ) {
						$this->all_results = $this->results = $this->routes[ $qv['route'] ]->endpoints[ $qv['endpoint'] ]->args[ $qv['parameter'] ]->properties;
					}

					break;
			}

			if ( ! empty( $qv['item_status'] ) ) {
				$this->results = $this->filter_scalar_equal( $this->results, $qv['item_status_column'],	$qv['item_status'] );
			}

			if ( ! empty( $qv['namespace'] ) ) {
				$this->results = $this->filter_scalar_equal( $this->results, 'namespace' );
			}

			if ( ! empty( $qv['method'] ) ) {
				$this->results = $this->filter_in_array( $this->results, 'methods', $qv['method'] );
			}

			if ( ! empty( $qv['link_type'] ) ) {
				$this->results = $this->filter_in_array_keys( $this->results, 'links', $qv['link_type'] );
			}

			if ( ! empty( $qv['type'] ) ) {
				$this->results = $this->filter_in_array( $this->results, 'type' );
			}

			if ( ! empty( $qv['context'] ) ) {
				$this->results = $this->filter_in_array( $this->results, 'context' );
			}

			if ( ! empty( $qv['format'] ) ) {
				$this->results = $this->filter_scalar_equal( $this->results, 'format' );
			}

			if ( ! empty( $qv['array_items_type'] ) ) {
				$this->results = $this->filter_in_array( $this->results, 'array_items_type' );
			}

			// further refine the results if a column search is to be performed.
			if ( ! empty( $qv['search'] ) ) {
				$this->results = $this->filter_search( $this->results, $qv['search_columns'], $qv['search'] );
			}

			// order the results.
			if ( ! empty( $qv['orderby'] ) ) {
				uasort(
					$this->results,
					function( $a, $b ) use ( $qv ) {
						$a = $a->{$qv['orderby']};
						$b = $b->{$qv['orderby']};

						return 'ASC' === $qv['order'] ? $a <=> $b : $b <=> $a;
					}
				);
			}

			if ( isset( $qv['count_total'] ) && $qv['count_total'] ) {
				$this->total_items = count( $this->results );
			}
		}

		// limit
		if ( $this->results && isset( $qv['number'] ) && $qv['number'] > 0 ) {
			$this->results = array_slice( $this->results, $qv['offset'], $qv['number'] );
		}

		return;
	}

	/**
	 * Return the list of items.
	 *
	 * @since 0.1.0
	 *
	 * @return object[] Array of results.
	 */
	function get_results() {
		return $this->results;
	}

	/**
	 * Return the list of all items.
	 *
	 * @since 0.1.0
	 *
	 * @return object[] Array of results.
	 */
	function get_all_results() {
		return $this->all_results;
	}

	/**
	 * Return the total number of items for the current query.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of total items.
	 */
	function get_total() {
		return $this->total_items;
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @since 0.1.0
	 *
	 * @param string $order The 'order' query variable.
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'DESC';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'ASC';
		} else {
			return 'DESC';
		}
	}

	/**
	 * Filter an array of items by whether a scalar-valued field has a specific value.
	 *
	 * This is essentially the same as calling `wp_list_filter()`, but since
	 * we need to do other kinds of "filter" operations that `wp_list_filter()`
	 * doesn't support, it makes sense to do all of them the same way (and not
	 * use `wp_list_filter()` at all.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The value to filter by.  Default: `$this->query_vars[ $field ]`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_scalar_equal( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				// note the use of weak comparison...just in case.
				return $value == $item->{$field};
			}
		);
	}

	/**
	 * Filter an array of items by whether an array-valued field contains a
	 * specific value.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The query var to filter by.  Default: the same as `$field`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_in_array( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				return in_array( $value, $item->{$field} );
			}
		);
	}

	/**
	 * Filter an array of items by whether the keys of an array-valued field contain a
	 * specific value.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The query var to filter by.  Default: the same as `$field`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_in_array_keys( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				return in_array( $value, array_keys( $item->{$field} ) );
			}
		);
	}

	/**
	 * Filter an array of items by whether the intersection of an array-valued
	 * field and an array-valued value is non-empty.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The query var to filter by.  Default: the same as `$field`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_array_intersect( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				return ! empty( array_intersect( $item->{$field}, $value ) );
			}
		);
	}

	/**
	 * Filter an array of items by whether a scalar valued field contains
	 * a specific sub-string value.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The query var to filter by.  Default: the same as `$field`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_scalar_strpos( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				return false !== strpos( strtolower( $item->{$field} ), $value );
			}
		);
	}

	/**
	 * Filter an array of items by whether any value in an array-valued
	 * field contains a specific sub-string value.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param string $field The field to filter by.
	 * @param string $value The query var to filter by.  Default: the same as `$field`.
	 * @return object[] Array of filtered items.
	 */
	protected function filter_array_strpos( $items, $field, $value = '' ) {
		if ( ! $value ) {
			$value = $this->query_vars[ $field ];
		}

		return array_filter(
			$items,
			function( $item ) use ( $field, $value ) {
				foreach ( $item->{$field} as $field_value ) {
					if ( false !== strpos( strtolower( $field_value ), $value ) ) {
						return true;
					}
				}
				return false;
			}
		);
	}

	/**
	 * Filter an array of items by whether any value in it's search_columns
	 * contains a specific sub-string value.
	 *
	 * @since 0.1.0
	 *
	 * @param object[] $items Array of items to filter.
	 * @param array $fields {
	 *     Optional. The fields to filter by.  Default: `$this->query_vars['search_columns']`.
	 *
	 *     @type array $scalar An array of scalar-valued fields.
	 *     @type array $array An array of array-valued fields.
	 * }
	 * @param string $value The value to search for.  Default: `$this->query_vars['search'].
	 * @return object[] Array of filtered items.
	 */
	protected function filter_search( $items, $fields = '', $value = '' ) {
		if ( ! $fields ) {
			$fields = $this->query_vars['search_columns'];
		}
		if ( ! $value ) {
			$value = $this->query_vars['search'];
		}

		foreach ( $fields as $type => $fields ) {
			foreach ( $fields as $field ) {
				switch ( $type ) {
					case 'scalar':
						$_items = $this->filter_scalar_strpos( $this->results, $field, $value );
						if ( $_items ) {
							return $_items;
						}

						break;
					case 'array':
						$_items = $this->filter_array_strpos( $this->results, $field, $value );
						if ( $_items ) {
							return $_items;
						}

						break;
				}
			}
		}

		return array();
	}

	/**
	 * Massage the return value of
	 * {@link https://developer.wordpress.org/reference/classes/wp_rest_server/get_routes/ WP_REST_Server::get_routes()}
	 * into a form that makes querying and displaying them in a list table easier.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @todo see if there are functions/methods in core that would make this massaging easier.
	 */
	protected function _get_routes() {
		$rest_server = rest_get_server();

		foreach ( $rest_server->get_routes() as $name => $endpoints ) {
			$this->routes[ $name ] = $this->get_data_for_route(
				$name,
				$endpoints,
				$rest_server->get_route_options( $name )
			);
		}

		// now, setup the hierarchy of routes.
		foreach ( $this->routes as $name => $route ) {
			if ( '/' === $name ) {
				continue;
			}
			$parents = array_filter(
				array_keys( $this->routes ),
				function ( $_route ) use ( $route ) {
					return '/' !== $_route && $_route !== $route->name && false !== strpos( $route->name, $_route );
				}
			);

			if ( ! empty( $parents ) ) {
				$route->parent = end( $parents );
			}

			$i=0;
		}

		return;
	}

	/**
	 * Retrieves all data for a route.
	 *
	 * Similar in spirit to
	 * {@link https://developer.wordpress.org/reference/classes/wp_rest_server/get_data_for_route/ WP_REST_Server::get_data_for_route()}
	 * but includes non-public data and massaged in such a way to make querying and displaying
	 * in a list table easier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $route     Route to get data for.
	 * @param array  $endpoints Endpoints to convert to data.
	 * @param array $options    Options for the route.
	 * @return object {
	 *     Data for the route.
	 *
	 *     @type string $item_type 'route'.
	 *     @type string $name The name (i.e., path regex) of the route.
	 *     @type string $authenticated The authentication status of the route.
	 *                                 One of 'yes', 'no' or 'maybe'.
	 *     @type string $namespace The namespace of the route.
	 *     @type string[] $methods The methods supported by the route.
	 *     @type object[] $endpoints Array of endpoints for the route.
	 *                               Keys are the method(s) of the endpoint,
	 *                               For endpoints with more than one method,
	 *                               the key is `implode( ' | ', $methods )`.
	 *     @type object $schema The schema for the route.
	 *     @type string[] $_links The links for the route.  Keys are the "rel" type
	 *                         of the link.
	 * }
	 */
	protected function get_data_for_route( $route, $endpoints, $options ) {
		$data = array(
			'item_type' => 'route',
			'name'          => $route,
			'authenticated' => 'maybe',
			'namespace' => '',
			'methods'   => array(),
			'endpoints' => array(),
			'schema'    => array(),
			'_links'    => array(),
			'parent'    => '',
		);

		if ( isset( $options['namespace'] ) ) {
			$data['namespace'] = $options['namespace'];
		}

		foreach ( $endpoints as $endpoint ) {
			$endpoint['name']              = $route;
			$endpoint_data                 = $this->get_data_for_endpoint( $endpoint );
			$methods                       = implode( ' | ', $endpoint_data->methods );
			$data['endpoints'][ $methods ] = $endpoint_data;
		}

		$data['methods'] = array_keys( $data['endpoints'] );

		if ( isset( $options['schema'] ) ) {
			$data['schema'] = $this->get_data_for_schema( $route, call_user_func( $options['schema'] ) );
		}

		// For non-variable routes, generate links.
		if ( 0 === preg_match( '#\(\?P<(\w+?)>.*?\)#', $route ) ) {
			$data['_links'] = array(
				'self' => rest_url( $route ),
			);
		}

		$authenticated = array_values( wp_list_pluck( $data['endpoints'], 'authenticated' ) );
		$authenticated = array_unique( $authenticated );

		if ( array( 'yes' ) === $authenticated ) {
			$data['authenticated'] = 'yes';
		}
		elseif ( array( 'no' ) === $authenticated ) {
			$data['authenticated'] = 'no';
		}

		return (object) $data;
	}

	/**
	 * Retrieves all data for a schema.
	 *
	 * @since 0.1.0
	 *
	 * @param array $endpoint Endpoint to get data for.
	 * @return object {
	 *     Data for the endpoint.
	 *
	 *     @type string $item_type 'endpoint'.
	 *     @type string $name The name (i.e., path regex) of the route the endpoint is in.
	 *     @type string $authenticated The authentication status of the endpoint.
	 *                                 One of 'yes', 'no' or 'maybe'.
	 *     @type string[] $methods The methods supported by the endpoint.
	 *     @type object[] $args Array of parameters for the endpoint.
	 *                               Keys are the names of the paramters.
	 *     @type callable $callback The get/create/update/delete callback for the endpoint.
	 *     @type object[] $args The parameters for the endpoint.
	 *     @type bool $accept_json ?????
	 *     @type bool $accept_raw ?????
	 *     @type bool $show_in_index True if the endpoint is shown in the index, false otherwise.
	 * }
	 */
	protected function get_data_for_schema( $route, $schema ) {
		$defaults = array(
			'item_type'           => 'schema',
			'$schema'             => '',
			'name'                => $route,
			'title'               => '',
			'type'                => '',
			'properties'          => array(),
			'links'               => array(),
		);
		$data            = wp_parse_args( $schema, $defaults );

		foreach ( $data['properties'] as $name => $property ) {
			$property['name']            = $name;
			$data['properties'][ $name ] = $this->get_data_for_schema_property( $property );
		}

		foreach ( $data['links'] as $idx => $link ) {
			$link = $this->get_data_for_schema_link( $link );
			$data['links'][ $link->name ] = $link;
			unset( $data['links'][ $idx ] );
		}

		return (object) $data;
	}

	/**
	 * Retrieves all data for a schema link.
	 *
	 * @since 0.1.0
	 *
	 * @param array $endpoint Endpoint to get data for.
	 * @return object {
	 *     Data for the endpoint.
	 *
	 *     @type string $item_type 'endpoint'.
	 *     @type string $name The name (i.e., path regex) of the route the endpoint is in.
	 *     @type string $authenticated The authentication status of the endpoint.
	 *                                 One of 'yes', 'no' or 'maybe'.
	 *     @type string[] $methods The methods supported by the endpoint.
	 *     @type object[] $args Array of parameters for the endpoint.
	 *                               Keys are the names of the paramters.
	 *     @type callable $callback The get/create/update/delete callback for the endpoint.
	 *     @type object[] $args The parameters for the endpoint.
	 *     @type bool $accept_json ?????
	 *     @type bool $accept_raw ?????
	 *     @type bool $show_in_index True if the endpoint is shown in the index, false otherwise.
	 * }
	 */
	protected function get_data_for_schema_link( $link ) {
		$defaults = array(
			'item_type'           => 'schema_link',
			'title'               => '',
			'name'                => $link['rel'],
			'href'                => '',
			'targetSchema'        => array(),
		);
		$data            = wp_parse_args( $link, $defaults );
		unset( $data['rel'] );

// 		foreach ( $data['targetSchema'] as $name => $property ) {
// 			$property['name']            = $name;
// 			$data['properties'][ $name ] = $this->get_data_for_schema_property( $property );
// 		}

		return (object) $data;
	}

	/**
	 * Retrieves all data for an endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param array $endpoint Endpoint to get data for.
	 * @return object {
	 *     Data for the endpoint.
	 *
	 *     @type string $item_type 'endpoint'.
	 *     @type string $name The name (i.e., path regex) of the route the endpoint is in.
	 *     @type string $authenticated The authentication status of the endpoint.
	 *                                 One of 'yes', 'no' or 'maybe'.
	 *     @type string[] $methods The methods supported by the endpoint.
	 *     @type object[] $args Array of parameters for the endpoint.
	 *                               Keys are the names of the paramters.
	 *     @type callable $callback The get/create/update/delete callback for the endpoint.
	 *     @type object[] $args The parameters for the endpoint.
	 *     @type bool $accept_json ?????
	 *     @type bool $accept_raw ?????
	 *     @type bool $show_in_index True if the endpoint is shown in the index, false otherwise.
	 * }
	 */
	protected function get_data_for_endpoint( $endpoint ) {
		$defaults = array(
			'item_type'           => 'endpoint',
			'name'                => '',
			'authenticated'       => 'maybe',
			'methods'             => array(),
			'callback'            => '',
			'permission_callback' => '',
			'args'                => array(),
			'accept_json'         => false,
			'accept_raw'          => false,
			'show_in_index'       => false,
		);
		$data            = wp_parse_args( $endpoint, $defaults );
		$data['methods'] = array_keys( $data['methods'] );

		foreach ( $data['args'] as $name => $arg ) {
			$arg['name']           = $name;
			$data['args'][ $name ] = $this->get_data_for_arg( $arg );
		}

		if ( empty( $data['permission_callback'] ) ) {
			$data['authenticated'] = 'no';
		}
		elseif ( ! in_array( 'GET', $data['methods'] ) ) {
			$data['authenticated'] = 'yes';
		}

		return (object) $data;
	}

	/**
	 * Retrieves all data for a parameter.
	 *
	 * @since 0.1.0
	 *
	 * @param array $arg Parameter to get data for.
	 * @return object {
	 *     Data for the parameter.
	 *
	 *     @type string $item_type 'parameter'.
	 *     @type string $name The name of the parameter.
	 *     @type bool $requried True if the parameter is requried, false otherwise.
	 *     @type string[] $type The type(s) of the parameter.
	 *     @type string[] $array_items_type Type of items if the parameter is an array type.
	 *     @type string $description The description of the parameter.
	 *     @type string|number|bool $default_value The default value of the parameter.
	 *     @type string|number|bool[] $enum The enumerated values of the parameter, if it's
	 *                                      value must come from a fixed list.
	 *     @type object[] $properties The properties of the parameter if it's type is 'object',
	 *                                empty array otherwise.
	 * }
	 */
	protected function get_data_for_arg( $arg ) {
		$defaults = array(
			'item_type'       => 'parameter',
			'name'            => '',
			'required'        => false,
			'type'            => '',
			'array_items_type'=> array(),
			'description'     => '',
			'default'         => array(),
			'enum'            => array(),
			'properties'      => array(),
		);

		$data = wp_parse_args( $arg, $defaults );

		if ( ! empty( $data['items'] ) ) {
			$defaults = array(
				'type' => array(),
				'enum' => array(),
			);

			$data['items'] = wp_parse_args( $data['items'], $defaults );

			if ( ! is_array( $data['items']['type'] ) ) {
				$data['items']['type'] = array( $data['items']['type'] );
			}

			$data['array_items_type'] = $data['items']['type'];
			$data['enum']             = $data['items']['enum'];

			unset( $data['items'] );
		}

		foreach ( $data['properties'] as $name => $property ) {
			$property['name'] = $name;
			$data['properties'][ $name ] = $this->get_data_for_property( $property );
		}

		if ( ! is_array( $data['type'] ) ) {
			$data['type'] = array( $data['type'] );
		}

		// strip out "empty string" types.
		$data['type'] = array_filter( $data['type'] );

		if ( ! is_array( $data['default'] ) ) {
			$data['default'] = array( $data['default'] );
		}

		// rename the "default" field to "default_value" to make it
		// easier to display in a list table, i.e., avoid a conflict
		// with WP_List_Table::column_default() which outputs column
		// values for columns that don't have their own
		// column_{$column_name} method.
		$data['default_value'] = $data['default'];
		unset( $data['default'] );

		return (object) $data;
	}

	/**
	 * Retrieves all data for a schema property.
	 *
	 * @since 0.1.0
	 *
	 * @param array $property Parameter to get data for.
	 * @return object {
	 *     Data for the parameter.
	 *
	 *     @type string $item_type 'parameter'.
	 *     @type string $name The name of the parameter.
	 *     @type bool $requried True if the parameter is requried, false otherwise.
	 *     @type string[] $type The type(s) of the parameter.
	 *     @type string[] $array_items_type Type of items if the parameter is an array type.
	 *     @type string $description The description of the parameter.
	 *     @type string|number|bool $default_value The default value of the parameter.
	 *     @type string|number|bool[] $enum The enumerated values of the parameter, if it's
	 *                                      value must come from a fixed list.
	 *     @type object[] $properties The properties of the parameter if it's type is 'object',
	 *                                empty array otherwise.
	 * }
	 */
	protected function get_data_for_schema_property( $property ) {
		$defaults = array(
			'item_type'       => 'schema_property',
			'name'            => '',
			'readonly'       => false,
			'type'            => array(),
			'description'     => '',
			'format'          => '',
			'context'         => array(),
		);

		$data = wp_parse_args( $property, $defaults );

		if ( ! is_array( $data['type'] ) ) {
			$data['type'] = array( $data['type'] );
		}

		return (object) $data;
	}

	/**
	 * Retrieves all data for a property.
	 *
	 * @since 0.1.0
	 *
	 * @param array $property Property to get data for.
	 * @return object {
	 *     Data for the property.
	 *
	 *     @type string $item_type 'property'.
	 *     @type string $name The name of the property.
	 *     @type bool $readonly True if the property is readonly, false otherwise.
	 *     @type string[] $type The type(s) of the property.
	 *     @type string $description The description of the property.
	 *     @type string[] $context The contexts the property is available in.
	 * }
	 */
	 protected function get_data_for_property( $property ) {
		$defaults = array(
			'item_type'   => 'property',
			'name'        => '',
			'readonly'    => false,
			'type'        => array(),
			'description' => '',
			'context'     => array(),
		);
		$data = wp_parse_args( $property, $defaults );

		// @todo can a property have 'array' or 'object' as it's type?
		//       core endpoints don't seem to have any such propoperties.
		//       but if plugins can register them we'll have to add additional
		//       functionality to handle those cases.
		if ( ! is_array( $data['type'] ) ) {
			$data['type'] = array( $data['type'] );
		}

		return (object) $data;
	}
}
