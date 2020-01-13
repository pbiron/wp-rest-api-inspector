<?php

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class to provide functionality analogous to core's
 * {@link https://developer.wordpress.org/reference/functions/get_post_type_object/ get_post_type_object()}.
 *
 * At this point, this is just bare-bones, but can be expaned as the need arrises.
 *
 * @since 0.1.0
 */
class REST_API_Item_Type extends Singleton {
	/**
	 * The capability need to view a REST API item type.
	 *
	 * This is analogous to the `edit_posts` cap for post type objects.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	static $view_items_cap = 'manage_options';

	/**
	 * Retrieves a REST API item type type object by name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $item_type The name of a registered item type.
	 * @return object
	 */
	static function get_item_type_object( $item_type ) {
		$item_type_object =  (object) array(
			'cap'         => (object) array( 'view_items' => self::$view_items_cap ),
			'labels'      => (object) array(),
		);

		switch ( $item_type ) {
			case 'route':
				$item_type_object->labels->search_items = __( 'Search Routes', 'shc-rest-api-inspector' );
				break;
			case 'schema':
				$item_type_object->labels->search_items = __( 'Search Schemas', 'shc-rest-api-inspector' );
				break;
			case 'endpoint':
				$item_type_object->labels->search_items = __( 'Search Endpoints', 'shc-rest-api-inspector' );
				break;
			case 'parameter':
				$item_type_object->labels->search_items = __( 'Search Parameters', 'shc-rest-api-inspector' );
				break;
			case 'property':
			case 'schema_property':
				$item_type_object->labels->search_items = __( 'Search Properties', 'shc-rest-api-inspector' );
				break;
			case 'schema_link':
				$item_type_object->labels->search_items = __( 'Search Links', 'shc-rest-api-inspector' );
				break;
		}

		return $item_type_object;
	}
}
