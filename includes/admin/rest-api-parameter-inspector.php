<?php
/**
 * View REST API Parameters Administration Screen.
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

$wp_list_table = Tool::_get_list_table( 'WP_REST_Parameters_List_Table' );

$pagenum  = $wp_list_table->get_pagenum();

// set $submenu_file, so that our tool sub-menu will get the 'current' CSS class.
// we have to declare these vars as global.
global $submenu_file;
$parent_file  = "tools.php?page={$_REQUEST['page']}&noheader";
$submenu_file = 'shc-rest-api-inspector';

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-parameters' );

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
		'REST API Parameters for route &#8220;%s&#8221; with method &#8220;%s&#8221;',
		'REST API Parameters for route &#8220;%s&#8221; with methods &#8220;%s&#8221;',
		count( explode( ' | ', $_GET['endpoint'] ) ),
		'shc-rest-api-inspector'
	),
	esc_html( wp_unslash( $_GET['route'] ) ),
	esc_html( wp_unslash( $_GET['endpoint'] ) )
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'This screen provides access to all REST API Parameters for a given route and method. You can customize the display of this screen to suit your workflow.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'screen-content',
		'title'   => __( 'Screen Content' ),
		'content' =>
			'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:' ) . '</p>' .
			'<ul>' .
				'<li>' . __( 'You can hide/display columns based on your needs and decide how many parameters to list per screen using the Screen Options tab.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can filter the list of parameters by whether they are required or not using the text links above the list to only show parameters with that required type. The default view is to show all parameters.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can refine the list to show only parameters with a specifc type by using the dropdown menu above the list. Click the Filter button after making your selection. You also can refine the list by clicking on the type in the list.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'action-links',
		'title'   => __( 'Available Actions' ),
		'content' =>
			'<p>' . __( 'Hovering over a row in the parameter list will display action links that allow you to manage your parameter. You can perform the following actions:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ul>' .
				'<li>' . __( '<strong>View</strong> takes you to the properties screen for that paremter. You can also reach that screen by clicking on the parameter name.  Only parameters of type <strong>object</strong> can be viewed.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>' .
			'<p>' . __( 'Like all good list tables, additional row actions can be added with the <em>rest_parameter_row_actions</em> and <em>rest_item_row_actions</em> filters.', 'shc-rest-api-inspector' )
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'bulk-actions',
		'title'   => __( 'Bulk Actions' ),
		'content' =>
			'<p>' . __( 'By default, this screen does not have any bulk actions.', 'shc-rest-api-inspector' ) . '</p>' .
			'<p>' . __( 'However, they can be added using the <em>rest-api-inspector-parameter</em> filter.  If such custom bulk actions are added, then they will work just like bulk actions in any other list table-enabled screen.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'statuses',
		'title'   => __( 'Statuses', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'Below is an explanation of the various statuses.  These are the equivalent of "Published", "Pending", etc on the "All Posts" screen.', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Required</strong>: The parameter is required :-)', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Optional</strong>: The parameter is optional :-)', 'shc-rest-api-inspector' ) . '</li>' .
			'</ol>' .
			'<p>' . __( 'Only the <em>Required</em> status is added as a "state" on each parameter, under the assumption that most parameters are <em>optional</em>.  This is like on the "All Posts" screen, where the <em>Published</em> status is not added as a "state".', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'columns',
		'title'   => __( 'Columns', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'Below is an explanation of the various columns:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Name</strong>: Lists the name of the parameter.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Type</strong>: Lists the type(s) of the parameter.  See the <em>Questions</em> help tab for questions about parameter types.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Array Items Type</strong>: Lists the type(s) of items in the array if the parameter has type <em>array</em>.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Description</strong>: Lists the description of the parameter.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Default</strong>: Lists the default value (if there is one) for the parameter.', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Enum</strong>: Lists the enumerated values for the parameter if it\'s value must come from a fixed list.', 'shc-rest-api-inspector' ) .
			'</ol>' .
			'<p>' . __( 'The <strong>Type</strong> and <strong>Array Item Types</strong> columns act like <em>taxonomy</em> columns on the "All Posts" screen.  That is, they get a dropdowns and their values are links that act like they were selected from the dropdown.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'search-columns',
		'title'   => __( 'Search Columns', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'By default, the following columns are used when performing a search:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Name</strong>', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Type</strong>', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Array Items Type</strong>', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Description</strong>', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Default</strong>', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Enum</strong>', 'shc-rest-api-inspector' ) . '</li>' .
			'</ol>' .
			'<p>' . __( 'That default can be changed with the <em>rest_parameter_search_column</em> and <em>rest_item_search_column</em> filters.', 'shc-rest-api-inspector' ) . '</p>'
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
				'<li>' . __( 'I chose <em>required or not</em> for the <em>Status</em> because that seemed the obvious choice, but is there something else that would be better?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'What is the <em>null</em> type?  As far as I can tell, it only occurs along with the <em>string</em> type.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'As far as I can tell, the only parameters in core endpoints that have more than one <em>type</em> have <em>string</em> or <em>null</em>.  Is it possible to declare a parameter to have more than one type where those "types" aren\'t <em>string</em> and <em>null</em>, e.g. <em>array</em> and <em>boolean</em>?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Some paramters in core endpoints have no type, e.g., <em>context</em> on the route &#8220;/wp/v2&#8221; with method &#8220;GET&#8221;.  Is that just a "bug" in the way that parameter is registered in core?  Or is it possible for plugins to register such parameters?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Is there a better name for the <em>Enum</em> column?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'Should I add a "view switcher" (like on the WP_MS_Sites_Table and WP_Media_List_Table)?  If so, what should the other "mode" be?', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'Are there any other columns that should be added?', 'shc-rest-api-inspector' ) .
			'</ol>'
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
		'option'  => 'parameter_per_page',
	)
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );
 ?>
<div class="wrap">
	<h1 class="wp-heading-inline">
	<?php
	echo esc_html( __( 'Parameters', 'shc-rest-api-inspector' ) );
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
				'Route: &#8220;%s&#8221;, Method: &#8220;%s&#8221;',
				'Route: &#8220;%s&#8221;, Methods: &#8220;%s&#8221;',
				count( explode( ' | ', $_GET['endpoint'] ) ),
				'shc-rest-api-inspector'
			),
			esc_html( wp_unslash( $_GET['route'] ) ),
			esc_html( wp_unslash( $_GET['endpoint'] ) )
		);
	?>
	</h2>

<hr class="wp-header-end">

<?php $wp_list_table->views(); ?>

<form id="rest-api-parameters-filter" method="get">
<?php $wp_list_table->search_box( $item_type_object->labels->search_items, 'rest_api_parameter' ); ?>

<input type="hidden" name="noheader" class="noheader" value="" />
<input type="hidden" name="page" class="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>" />
<input type="hidden" name="route" class="route" value="<?php echo esc_attr( wp_unslash( $_REQUEST['route'] ) ) ?>" />
<input type="hidden" name="endpoint" class="endpoint" value="<?php echo esc_attr( wp_unslash( $_REQUEST['endpoint'] ) ) ?>" />
<input type="hidden" name="rest_item_type" class="rest_item_type_routee" value="<?php echo $rest_item_typenow; ?>" />

<?php $wp_list_table->display(); ?>

</form>
<div id="ajax-response"></div>
<br class="clear" />
</div>

<?php
// we don't need to include the admin-footer.php like similar files in core,
// because wp-admin/admin.php will do it for us.
//include( ABSPATH . 'wp-admin/admin-footer.php' );
