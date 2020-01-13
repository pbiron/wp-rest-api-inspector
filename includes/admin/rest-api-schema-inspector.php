<?php
/**
 * View REST API Routes Administration Screen.
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
		'<p>' . __( 'Sorry, you are not allowed to view REST API Schemas.' ) . '</p>',
		403
	);
}

$wp_list_table = Tool::_get_list_table( 'WP_REST_Schemas_List_Table' );

$pagenum  = $wp_list_table->get_pagenum();

// set $submenu_file, so that our tool sub-menu will get the 'current' CSS class.
// we have to declare these vars as global.
global $submenu_file;
$parent_file  = "tools.php?page={$_REQUEST['page']}&noheader";
$submenu_file = 'shc-rest-api-inspector';

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-schemas' );

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
$title = sprintf( __( 'Schema for &#8220;%s&#8221;', 'shc-rest-api-inspector' ), wp_unslash( $_REQUEST['schema'] ) );

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'This screen provides access to the Schema for a route. You can customize the display of this screen to suit your workflow.', 'shc-rest-api-inspector' )
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'todos',
		'title'   => __( 'TODO\'s', 'shc-rest-api-inspector' ),
		'content' =>
		'<p>' . __( 'The following are things that I eventually want to get to:', 'shc-rest-api-inspector' ) . '</p>' .
			'<ul>' .
				'<li>' . __( 'Write the content for the other help tabs for this screen.', 'shc-rest-api-inspector' ) .
				'<li>' . __( 'Not sure how useful this screen is.  Since there will only ever be one schema per route, displaying it in a list table is prtty superfluous.  May be better to just have "Schema Properties and Schema Links row actions on the route list table?', 'shc-rest-api-inspector' ) .
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
		'option'  => 'schema_per_page',
	)
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );
 ?>
<div class="wrap">
	<h1 class="wp-heading-inline">
	<?php
	echo esc_html( $title );
	 ?>
	</h1>
<?php

if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	/* translators: %s: Search query. */
	printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', esc_attr( $_REQUEST['s'] ) );
}
?>
<hr class="wp-header-end">

<?php $wp_list_table->views(); ?>

<form id="rest-api-routes-filter" method="get">
<?php //$wp_list_table->search_box( $item_type_object->labels->search_items, 'rest_api_route' ); ?>

<input type="hidden" name="noheader" class="noheader" value="" />
<input type="hidden" name="page" class="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>" />
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
