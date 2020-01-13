<?php

/**
 * Plugin Name: REST API Inspector
 * Description: Inspect the REST API routes, endpoints, parameters and properties registered on a site
 * Version: 0.1.0
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com
 * Plugin URI: https://github.com/pbiron/wp-rest-api-inspector
 * GitHub Plugin URI: https://github.com/pbiron/wp-rest-api-inspector
 * Release Asset: true
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

require __DIR__ . '/vendor/autoload.php';

/**
 * Our main plugin class.
 *
 * @since 0.1.0
 */
class Plugin extends Plugin_Base {
	/**
	 * Our version number.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const VERSION = '0.1.0';

	/**
	 * Path to this file.
	 *
	 * Used in other classes to e.g. generate URLs for assets (with
	 * {@link https://developer.wordpress.org/reference/functions/plugins_url/ plugins_url()}).
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $file = __FILE__;

	/**
	 * Instantiate our tool if necessary.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @action plugins_loaded
	 */
	function setup() {
		parent::_setup();

		if ( is_admin() ) {
			Tool::get_instance();
		}

		return;
	}
}

// instantiate ourselves
Plugin::get_instance();
