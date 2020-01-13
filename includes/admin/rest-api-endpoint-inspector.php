<?php
/**
 * View REST API Endpoints Administration Screen.
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
		'<p>' . __( 'Sorry, you are not allowed to view REST API Endpoints.', 'shc-rest-api-inspector' ) . '</p>',
		403
	);
}

$wp_list_table = Tool::_get_list_table( 'WP_REST_Endpoints_List_Table' );

$pagenum  = $wp_list_table->get_pagenum();

// set $submenu_file, so that our tool sub-menu will get the 'current' CSS class.
// we have to declare these vars as global.
global $submenu_file;
$parent_file  = "tools.php?page={$_REQUEST['page']}&noheader";
$submenu_file = 'shc-rest-api-inspector';

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-endpoints' );

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
	__( 'Endpoints for route &#8220;%s&#8221;', 'shc-rest-api-inspector' ),
	esc_html( wp_unslash( $_GET['route'] ) )
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'This screen provides access to all REST API Endpoints for a given route. You can customize the display of this screen to suit your workflow.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'screen-content',
		'title'   => __( 'Screen Content' ),
		'content' =>
			'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:' ) . '</p>' .
			'<ul>' .
				'<li>' . __( 'You can hide/display columns based on your needs and decide how many endpoints to list per screen using the Screen Options tab.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can filter the list of endpoints by whether they are authenticated or not using the text links above the list to only show endpoints with that authentication type. The default view is to show all endpoints.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( 'You can refine the list to show only endpoints with a specifc method by using the dropdown menu above the list. Click the Filter button after making your selection. You also can refine the list by clicking on the method in the list.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'action-links',
		'title'   => __( 'Available Actions' ),
		'content' =>
			'<p>' . __( 'Hovering over a row in the endpoints list will display action links that allow you to manage your endpoint. You can perform the following actions:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ul>' .
				'<li>' . __( '<strong>View</strong> takes you to the parameters screen for that endpoint. You can also reach that screen by clicking on the endpoint name.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ul>' .
			'<p>' . __( 'Like all good list tables, additional row actions can be added with the <em>rest_endpoint_row_actions</em> and <em>rest_item_row_actions</em> filters.', 'shc-rest-api-inspector' )
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'bulk-actions',
		'title'   => __( 'Bulk Actions' ),
		'content' =>
			'<p>' . __( 'By default, this screen does not have any bulk actions.', 'shc-rest-api-inspector' ) . '</p>' .
			'<p>' . __( 'However, they can be added using the <em>rest-api-inspector-endpoint</em> filter.  If such custom bulk actions are added, then they will work just like bulk actions in any other list table-enabled screen.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'statuses',
		'title'   => __( 'Statuses', 'shc-rest-api-inspector' ),
		'content' =>
		'<p>' . __( 'Below is an explanation of the various statuses.  These are the equivalent of "Published", "Pending", etc on the "All Posts" screen.', 'shc-rest-api-inspector' ) . '</p>' .
		'<ol>' .
			'<li>' . __( '<strong>Unauthenticated</strong>: the endpoint does not require authentication.  This is is the case if there is no <em>permissions_callback</em> on the endpoint.', 'shc-rest-api-inspector' ) . '</li>' .
			'<li>' . __( '<strong>Authenticated</strong>: the endpoint requires authentication.   This is is the case if there is a <em>permissions_callback</em> on the endpoint <strong>AND</strong> it does <strong>NOT</strong> have the <em>GET</em> method.', 'shc-rest-api-inspector' ) . '</li>' .
			'<li>' . __( '<strong>Maybe Authenticated</strong>: the endpoint <strong>MIGHT</strong> require authentication.  This is the case if the endpoint has a <em>permissions_callback</em> <strong>AND</strong> the <em>GET</em> method, under the assumption that if the "context" is anything other than <em>view</em> it requires authentication, otherwise it <strong>MIGHT</strong> require authentication depending on how the <em>permission_callback</em> is written.', 'shc-rest-api-inspector' ) . '</li>' .
		'</ol>' .
		'<p>' . __( 'Each status is also added as a "state" on each route.  Unlike the "states" for the "All Posts" screen, each status is added as a state, whereas on the "All Posts" screen the "Published" status (i.e., the most prevalent status) is not added as a "state".', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'columns',
		'title'   => __( 'Columns', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'Below is an explanation of the various columns:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Route</strong>: Lists the regex for the route the endpoint is in.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Methods</strong>: Lists the methods of all endpoints for the route.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Callback</strong>: Has link to the WP Code Reference entry for the <em>callback</em> of the endpoint.  See the "TODO\'s" tab for more details.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Permission Callback</strong>: Has link to the WP Code Reference entry for the <em>permission_callback</em> of the endpoint.  See the "TODO\'s" tab for more details.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Show in Index</strong>: Lists whether the endpoint is shown in the index.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Accept JSON</strong>: Lists whether the endpoint accepts JSON.', 'shc-rest-api-inspector' ) . '</li>' .
				'<li>' . __( '<strong>Accept RAW</strong>: Lists whether the endpoint accepts RAW.', 'shc-rest-api-inspector' ) . '</li>' .
			'</ol>' .
			'<p>' . __( 'The <strong>Methods</strong> column act like <em>taxonomy</em> columns on the "All Posts" screen.  That is, they get dropdowns and their values are links that act like they were selected from the dropdown.', 'shc-rest-api-inspector' ) . '</p>'
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'search-columns',
		'title'   => __( 'Search Columns', 'shc-rest-api-inspector' ),
		'content' =>
			'<p>' . __( 'By default, the following columns are used when performing a search:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ol>' .
				'<li>' . __( '<strong>Route</strong>', 'shc-rest-api-inspector' ) .
				'<li>' . __( '<strong>Methods</strong>', 'shc-rest-api-inspector' ) .
			'</ol>' .
			'<p>' . __( 'That default can be changed with the <em>rest_endpoint_search_column</em> and <em>rest_item_search_column</em> filters.', 'shc-rest-api-inspector' ) . '</p>'
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
				'<li>' . __( 'Is the method of determining whether an endpoint is authenticated correct (see the "Statuses" help tab)?  I\'m pretty sure it is unreliable, for instance:', 'shc-rest-api-inspector' ) .
					'<ul>' .
						'<li>' . __( 'The endpoint for &#8220/wp/v2/search&#8221; with method &#8220;GET&#8221; has status <em>Maybe Authenticaed</em>, yet it\'s <em>permission_callback</em> always returns true; hence, it\'s status should be <em>Unauthenticaed</em>.', 'shc-rest-api-inspector' ) . '</li>' .
						'<li>' . __( 'The endpoint for &#8220/wp/v2/users/me&#8221; with method &#8220;GET&#8221; has no <em>permission_callback</em> and hence has status <em>Unauthenticated</em>.  Yet it\'s <em>callback</em> checks that the user is logged in (in essence, authenticating them).  I can see the logic behind that, but it almost seems like a bug in the way that endpoint is defined.', 'shc-rest-api-inspector' ) . '</li>' .
						'<li>' . __( 'A plugin could register an endpoint with method <em>DELETE</em> and it\'s status would be <em>Authenticated</em>.  Yet, the there is nothing stopping that plugin from writing the <em>permission_callback</em> such that it always returns true, effectively making the endpoint <em>Unauthenticated</em>.', 'shc-rest-api-inspector' ) . '</li>' .
					'</ul>' .
				'</li>' .
				'<li>' . __( 'I chose <em>autenticated or not</em> for the <em>Status</em> because that is important to me, but is there something else that would be better?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'Should I add a <em>Namespace</em> column?  Is it correct to say that an endpoint is in a namespace or are only routes in namespaces?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'What do the values in the <em>Accept JSON</em> and <em>Accept RAW</em> columns mean?', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'As far as I can tell, <strong>all</strong> core endpoints have "Yes" for <em>Show in Index</em> and "No" for <em>Accept JSON</em> and <em>Accept RAW</em>.  Therefore, I\'m wondering how useful these columns are.', 'shc-rest-api-inspector' ) .
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
				'<li>' . __( 'figure out a reliable way to distinquish core from non-core endpoints and just output the <em>permission_callback</em> "name" for non-core endpoints.', 'shc-rest-api-inspector' ) .
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
		'option'  => 'endpoints_per_page',
	)
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );
 ?>
<div class="wrap">
	<h1 class="wp-heading-inline">
	<?php
	echo esc_html( __( 'Endpoints', 'shc-rest-api-inspector' ) );
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
			__( 'Route: &#8220;%s&#8221;', 'shc-rest-api-inspector' ),
			esc_html( wp_unslash( $_GET['route'] ) )
		);
	 ?>
	 </h2>
<hr class="wp-header-end">

<?php $wp_list_table->views(); ?>

<form id="rest-api-endpoint-filter" method="get">
<?php $wp_list_table->search_box( $item_type_object->labels->search_items, 'rest_api_endpoint' ); ?>

<input type="hidden" name="noheader" class="noheader" value="" />
<input type="hidden" name="page" class="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>" />
<input type="hidden" name="route" class="route" value="<?php echo esc_attr( wp_unslash( $_REQUEST['route'] ) ) ?>" />
<input type="hidden" name="rest_item_type" class="rest_item_type" value="<?php echo $rest_item_typenow; ?>" />

<?php $wp_list_table->display(); ?>

</form>
<div id="ajax-response"></div>
<br class="clear" />
</div>

<?php
// we don't need to include the admin-footer.php like similar files in core,
// because wp-admin/admin.php will do it for us.
//include( ABSPATH . 'wp-admin/admin-footer.php' );
