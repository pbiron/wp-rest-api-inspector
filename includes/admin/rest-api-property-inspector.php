<?php
/**
 * View REST API Properties Administration Screen.
 *
 * @since 0.1.0
 */

namespace SHC\REST_API_Inspector;

defined( 'ABSPATH' ) || die;

// we don't need to require the admin.php like similar files in core,
// because it's already been required.
/** WordPress Administration Bootstrap */
//require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! $rest_item_typenow ) {
	wp_die( __( 'Invalid REST API item type.' ) );
}

global $item_type, $item_type_object;
$item_type        = $rest_item_typenow;
$item_type_object = REST_API_Item_Type::get_item_type_object( $item_type );

if ( ! $item_type_object ) {
	wp_die( __( 'Invalid REST API item type.' ) );
}

if ( ! current_user_can( $item_type_object->cap->view_items ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to view REST API Parameters.', 'shc-rest-api-inspector' ) . '</p>',
		403
	);
}

$wp_list_table = Tool::_get_list_table( 'WP_REST_Properties_List_Table' );

$pagenum  = $wp_list_table->get_pagenum();

// set $submenu_file, so that our tool sub-menu will get the 'current' CSS class.
// we have to declare these vars as global.
global $submenu_file;
$parent_file  = "tools.php?page={$_REQUEST['page']}&noheader";
$submenu_file = 'shc-rest-api-inspector';

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-properties' );

	$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), wp_get_referer() );
	if ( ! $sendback ) {
		$sendback = admin_url( $parent_file );
	}
	$sendback = add_query_arg( 'paged', $pagenum, $sendback );

	if ( ! empty( $_REQUEST['name'] ) ) {
		$item_names = $_REQUEST['name'];
	}

	if ( ! isset( $item_names ) ) {
		wp_redirect( $sendback );
		exit;
	}

	switch ( $doaction ) {
		// we don't define any bulk actions, but other plugins could, so handle those.
		default:
			/** This action is documented in wp-admin/edit-comments.php */
			$sendback = apply_filters( 'handle_bulk_actions-' . get_current_screen()->id, $sendback, $doaction, $item_names ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			break;
	}

	$sendback = remove_query_arg( array( 'action', 'action2', 'bulk_edit' ), $sendback );

	wp_redirect( $sendback );
	exit();
} elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
	wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	exit;
}

$wp_list_table->prepare_items();

wp_enqueue_style( 'shc-rest-api-inspector-list-table' );

// we must declare $title as global so that get_admin_page_title() doesn't override it.
global $title;
$title = sprintf(
	_n(
	'REST API Properties for parameter &#8220;%s&#8221;, on route &#8220;%s&#8221; with method &#8220;%s&#8221;',
	'REST API Properties for parameter &#8220;%s&#8221;, on route &#8220;%s&#8221; with methods &#8220;%s&#8221;',
	count( explode( ' | ', $_GET['endpoint'] ) ),
	'shc-rest-api-inspector'
	),
	esc_html( wp_unslash( $_GET['parameter'] ) ),
	esc_html( wp_unslash( $_GET['route'] ) ),
	esc_html( wp_unslash( $_GET['endpoint'] ) )
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'This screen provides access to all REST API Propertis for a given route, method and parameter. You can customize the display of this screen to suit your workflow.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'screen-content',
		'title'   => __( 'Screen Content' ),
		'content' =>
			'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:' ) . '</p>' .
			'<ul>' .
				'<li>' . __( 'You can hide/display columns based on your needs and decide how many properties to list per screen using the Screen Options tab.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can filter the list of properties by whether they are readonly or not using the text links above the list to only show properties with that readonly type. The default view is to show all properties.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can refine the list to show only properties with a specifc type or context by using the dropdown menu above the list. Click the Filter button after making your selection. You also can refine the list by clicking on the type or context in the list.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'action-links',
		'title'   => __( 'Available Actions' ),
		'content' =>
			'<p>' . __( 'Hovering over a row in the proprties list will display action links that allow you to manage your property. You can perform the following actions:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ul>' .
				'<li>' . __( '<strong>View</strong> takes you to the properties screen for that paremter. You can also reach that screen by clicking on the parameter name.  Only parameters of type <strong>object</strong> can be viewed.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>' .
			'<p>' . __( 'Like all good list tables, additional row actions can be added with the <em>rest_property_row_actions</em> and <em>rest_item_row_actions</em> filters.', 'shc-rest-api-inspector' )
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'bulk-actions',
		'title'   => __( 'Bulk Actions' ),
		'content' =>
			'<p>' . __( 'By default, this screen does not have any bulk actions.', 'shc-rest-api-inspector' ) . '</p>' .
			'<p>' . __( 'However, they can be added using the <em>rest-api-inspector-property</em> filter.  If such custom bulk actions are added, then they will work just like bulk actions in any other list table-enabled screen.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'statuses',
		'title'   => __( 'Statuses', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'Below is an explanation of the various statuses.  These are the equivalent of "Published", "Pending", etc on the "All Posts" screen.', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Readonly</strong>: The parameter is readonly :-)', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Writable</strong>: The parameter is writable :-).  I assume that <em>writable</em> properties only apply for methods other than <em>GET</em>.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ol>' .
			'<p>' . __( 'Only the <em>Readonly</em> status is added as a "state" on each parameter, under the assumption that most parameters are <em>optional</em>.  This is like on the "All Posts" screen, where the <em>Published</em> status is not added as a "state".', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'columns',
		'title'   => __( 'Columns', 'shc-rest-api-inspector' ),
		'content' =>
		'<p>' . __( 'Below is an explanation of the various columns:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Name</strong>: Lists the name of the property.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Type</strong>: Lists the type(s) of the property.  See the <em>Questions</em> help tab for questions about property types.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Description</strong>: Lists the description of the property.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Context</strong>: Lists the context in which the property exists.', 'shc-rest-api-inspector' ) .
			'</ol>' .
			'<p>' . __( 'The <strong>Type</strong> column acts like <em>taxonomy</em> columns on the "All Posts" screen.  That is, it gets a dropdown and it\'s values are links that act like they were selected from the dropdown.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'search-columns',
		'title'   => __( 'Search Columns', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'By default, the following columns are used when performing a search:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Name</strong>', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Type</strong>', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Description</strong>', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Context</strong>', 'shc-rest-api-inspector' ) .
			'</ol>' .
			'<p>' . __( 'That default can be changed with the <em>rest_property_search_column</em> and <em>rest_item_search_column</em> filters.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'questions',
		'title'   => __( 'Questions', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'I have the following questions about the implementation behind this screen:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( 'The information displayed on this screen is produced directly from massaging the output of <em>WP_REST_Server::get_routes()</em>, rather than parsing the JSON produced by the API on the front-end.  Am I leaving anything out by doing it that way?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'I chose <em>readonly or writable</em> for the <em>Status</em> because that seemed the obvious choice, but is there something else that would be better?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'I haven\'t come across any properties in core endpoints where type has more than one value (like <em>string</em> and <em>null</em> for parameter types).  Is it possible to register such properties?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Is it possible to register a property with type <em>object</em> or are only scalar or array types allowed?  If so, the current implementation of this plugin will not allow you to drill down into property\'s object properties.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Some core endpoints register paramters with type <em>object</em> (e.g., the &#8220;meta&#8221;" property of route &#8220;/wp/v2/posts&#8221; with method &#8220;POST&#8221;) but do <strong>not</strong> specify any properties for that <em>object</em> type.  Is that just a "bug" in the way that parameter is registered in core?  Or is it possible for plugins to register such parameters?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Should I add a "view switcher" (like on the WP_MS_Sites_Table and WP_Media_List_Table)?  If so, what should the other "mode" be?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Are there any other columns that should be added?', 'shc-rest-api-inspector' ) .
			'</ol>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'todos',
		'title'   => __( 'TODO\'s', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'The following are things that I eventually want to get to:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ul>' .
				'<li>' . __( 'Support drilling down into a property whose type is <em>object</em> (see the "Questions" help tab).', 'shc-rest-api-inspector' ) .
			'</ul>'
	)
);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://developer.wordpress.org/rest-api/">Documentation on the REST API</a>', 'shc-rest-api-inspector' ) . '</p>' .
	'<p>' . __( '<a href="https://github.com/pbiron/shc-rest-api-inspector/issues/">Support</a>' ) . '</p>'
);

add_screen_option(
	'per_page',
	array(
		'default' => 20,
		'option'  => 'properties_per_page',
	)
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );
 ?>
<div class="wrap">
	<h1 class="wp-heading-inline">
	<?php
	echo esc_html( __( 'Properties', 'shc-rest-api-inspector' ) );
	 ?>
	</h1>
<?php

if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	/* translators: %s: Search query. */
	printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', esc_attr( $_REQUEST['s'] ) );
}
?>
	<h2>
	<?php
		printf(
			_n(
				'Route: &#8220;%s&#8221;, Method: &#8220;%s&#8221;, Parameter: &#8220;%s&#8221;',
				'Route: &#8220;%s&#8221;, Methods: &#8220;%s&#8221;, Parameter: &#8220;%s&#8221;',
				count( explode( ' | ', $_GET['endpoint'] ) ),
				'shc-rest-api-inspector'
			),
			esc_html( wp_unslash( $_GET['route'] ) ),
			esc_html( wp_unslash( $_GET['endpoint'] ) ),
			esc_html( wp_unslash( $_GET['parameter'] ) )
		);
	?>
	</h2>
<hr class="wp-header-end">

<?php $wp_list_table->views(); ?>

<form id="rest-api-properties-filter" method="get">
<?php $wp_list_table->search_box( $item_type_object->labels->search_items, 'rest_api_property' ); ?>

<input type="hidden" name="noheader" class="noheader" value="" />
<input type="hidden" name="page" class="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>" />
<input type="hidden" name="route" class="route" value="<?php echo esc_attr( wp_unslash( $_REQUEST['route'] ) ) ?>" />
<input type="hidden" name="endpoint" class="endpoint" value="<?php echo esc_attr( wp_unslash( $_REQUEST['endpoint'] ) ) ?>" />
<input type="hidden" name="parameter" class="parameter" value="<?php echo esc_attr( wp_unslash( $_REQUEST['parameter'] ) ) ?>" />
<input type="hidden" name="rest_item_type" class="rest_item_type_route" value="<?php echo $rest_item_typenow; ?>" />

<?php $wp_list_table->display(); ?>

</form>
<div id="ajax-response"></div>
<br class="clear" />
</div>

<?php
// we don't need to include the admin-footer.php like similar files in core,
// because wp-admin/admin.php will do it for us.
//include( ABSPATH . 'wp-admin/admin-footer.php' );
