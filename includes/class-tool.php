<?php

namespace SHC\REST_API_Inspector;

use WP_Screen;

defined( 'ABSPATH' ) || die;

/**
 * Class used to setup and manage our tool page.
 *
 * @since 0.1.0
 */
class Tool extends Singleton {
	/**
	 * The hook_suffix returned by
	 * {@link https://developer.wordpress.org/reference/functions/add_management_page add_management_page()}
	 * for our tool.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $hook_suffix = '';

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function add_hooks() {
		parent::add_hooks();

		add_action( 'admin_menu', array( $this, 'add_management_page' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
		add_action( 'current_screen', array( $this, 'current_screen' ) );

		return;
	}

	/**
	 * Add our management page (child of tools.php).
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @action admin_menu
	 */
	function add_management_page() {
		$this->hook_suffix = add_management_page(
			// 	note: the page title will be set in the various admin/rest-api-{item_type}-inspector.php files.
			'',
			__( 'REST API Inspector', 'shc-rest-api-inspector' ),
			REST_API_Item_Type::$view_items_cap,
			Plugin::get_instance()->basename,
			array( $this, 'render_tool_page' )
		);

		add_action( "load-{$this->hook_suffix}", array( $this, 'maybe_add_noheader_arg' ) );

		return;
	}

	/**
	 * One the various `per_page` screen options.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $keep     Whether to save or skip saving the screen option value. Default false.
	 * @param string $option The option name.
	 * @param int $value     The number of rows to use.
	 * @return int|bool      The number of rows to use, or the value of `$keep` if `$option`$this
	 *                       is not one our options.
	 *
	 * @filter set-screen-option
	 */
	function set_screen_option( $keep, $option, $value ) {
		$our_screen_options = array(
			'route_per_page',
			'endpoint_per_page',
			'parameter_per_page',
			'property_per_page',
			'schema_property_per_page',
		);
 		if ( in_array( $option, $our_screen_options ) ) {
			$status = $value;
 		}

		return $status;
	}

	/**
	 * Setup the current screen.
	 *
	 * Modifies the `$base` and `$id` properties to be closer to what they would
	 * be if our screens were real core screens and not a tools page.  Also
	 * adds a dynamic `$rest_item_type` property to the screen analogous to
	 * the `$post_type` property built-in the core screens.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Screen $current_screen Current WP_Screen object.
	 *
	 * @action current_screen
	 */
	function current_screen( $current_screen ) {
		global $current_screen;

		if ( $this->hook_suffix !== $current_screen->id ) {
			// not our screen.
			// nothing to do, so bail.
			return;
		}

		// determine the rest_item_type by what query parameters are set.

		if ( ! empty( $_REQUEST['schema-properties'] ) ) {
			$current_screen->rest_item_type = 'schema_property';
		}
		elseif ( ! empty( $_REQUEST['schema-links'] ) ) {
			$current_screen->rest_item_type = 'schema_link';
		}
		elseif ( ! empty( $_REQUEST['schema'] ) ) {
			$current_screen->rest_item_type = 'schema';
		}
		elseif ( empty( $_REQUEST['route'] ) ) {
			$current_screen->rest_item_type = 'route';
		}
		elseif ( empty( $_REQUEST['endpoint'] ) ) {
			$current_screen->rest_item_type = 'endpoint';
		}
		elseif ( empty( $_REQUEST['parameter'] ) ) {
			$current_screen->rest_item_type = 'parameter';
		}
		else {
			$current_screen->rest_item_type = 'property';
		}

		$current_screen->base = 'rest-api-inspector';
		$current_screen->id   = "{$current_screen->base}-{$current_screen->rest_item_type}";

		return;
	}

	/**
	 * Render our tool page.
	 *
	 * @since 0.1.0
	 *
	 * @global string $rest_item_typenow The global REST item type (analogous to
	 *                                   $typenow for post types).
	 *
	 * @return void
	 *
	 * @action $this->hook_suffix
	 */
	function render_tool_page() {
		global $rest_item_typenow;

		$screen            = get_current_screen();
		$rest_item_typenow = $screen->rest_item_type;

		require Plugin::get_instance()->dirname . "/includes/admin/rest-api-{$screen->rest_item_type}-inspector.php";

		return;
	}

	/**
	 * Ensure that the `noheader` query parameter is added to our URL.
	 *
	 * This is so that the various `admin/rest-api-{$item_type}-inspector.php`
	 * files will work propertly.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @action load-{$this->hook_suffix}
	 */
	function maybe_add_noheader_arg() {
		if ( ! isset( $_GET['noheader'] ) ) {
			wp_redirect( add_query_arg( 'noheader', '' ) );

			exit;
		}

		return;
	}

	/**
	 * Fetches an instance of a WP_REST_API_List_Table class.
	 *
	 * Based on {@link https://developer.wordpress.org/reference/functions/_get_list_table/ _get_list_table().
	 *
	 * @access private
	 * @since 0.1.0
	 *
	 * @global string $hook_suffix
	 *
	 * @param string $class The type of the list table, which is the class name.
	 * @param array  $args  Optional. Arguments to pass to the class. Accepts 'screen'.
	 * @return WP_REST_API_List_Table|bool List table object on success, false if the class does not exist.
	 */
	protected function _get_list_table( $class, $args = array() ) {
		$class = __NAMESPACE__ . "\\{$class}";

		if ( class_exists( $class ) ) {
			if ( isset( $args['screen'] ) ) {
				$args['screen'] = convert_to_screen( $args['screen'] );
			} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
				$args['screen'] = get_current_screen();
			} else {
				$args['screen'] = null;
			}

			return new $class( $args );
		}

		return false;
	}

	/**
	 * Register our scripts and styles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @action init
	 */
	function register_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
			Plugin::get_instance()->basename . '-list-table',
			plugins_url( "assets/css/list-table{$suffix}.css", Plugin::get_instance()->file ),
			array(),
			Plugin::VERSION
		);

		return;
	}
}
