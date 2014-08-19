<?php



function github2wp_update_resource( $path, array $resource, $mode='install' ) {
	$resource_type = github2wp_get_repo_type( $resource['resource_link'] );
	$resource_name = $resource['repo_name'];

	$destination_dir = github2wp_get_content_dir_by_type($resource_type);

	require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	$res_slug = '';
	if( 'theme' == $resource_type ) {
		$processor = new Theme_Upgrader( new Theme_Installer_Skin( compact('type', 'title', 'nonce') ) );
		$res_slug = $resource_name;
	} else {
		$processor = new Plugin_Upgrader( new Plugin_Installer_Skin( compact('type', 'title', 'nonce') ) );
		$res_slug = "$resource_name/$resource_name.php";
	}

	require_once( ABSPATH . 'wp-admin/admin-header.php' );
	if ( 'update' == $mode )
		$processor->upgrade( $res_slug );
	else
		$processor->install( $path );
	require_once( ABSPATH . 'wp-admin/admin-footer.php' );
}



add_filter( 'upgrader_pre_download', 'github2wp_pre_download', 999, 3 );
function github2wp_pre_download( $reply, $package, $upgrader) {
	if ( !$package )
		return $reply;

	$url_parts = parse_url($package);
	if ( !isset($url_parts['query'] ) )
		return $reply;

	parse_str($url_parts['query']);

	if ( !isset($github2wp_action) || !isset($resource) )
		return $reply;

	if ( 'download' === $github2wp_action ) {
		$resource_path = GITHUB2WP_ZIPBALL_DIR_PATH . $resource . '.zip';

		if ( file_exists($resource_path) )
			return $resource_path;
	}


	return $reply;
}



add_action( 'admin_head', 'github2wp_admin_head' );
function github2wp_admin_head() {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];
	$default = $options['default'];

	$res = github2wp_admin_update_parser();

	if( empty($res) )
		return;

	foreach ( $resource_list as $key => $resource ) {
		if ( !in_array( $resource['repo_name'], $res, true ) )
			continue;

		$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

		$git = new Github_2_WP( array(
			'user'         => $resource['username'],
			'repo'         => $resource['repo_name'],
			'repo_type'    => $repo_type,
			'access_token' => $default['access_token'],
			'source'       => $resource['head_commit']
			)
		);

		$sw = $git->store_git_archive();
	}
}



add_action( 'admin_footer', 'github2wp_admin_footer' );
function github2wp_admin_footer() {
	$bulk_actions = array(
		'update-selected',
		'update-selected-themes'
	);


	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];
	$default = $options['default'];

	$res = github2wp_admin_update_parser();

	if( empty($res) )
		return;

	foreach ( $resource_list as $key => $resource ) {
		if ( !in_array( $resource['repo_name'], $res, true ) )
			continue;

		$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ).'.zip';
		github2wp_cleanup($zipball_path);
	}
}



function github2wp_admin_update_parser() {
	static $computed = false;
	static $res = array();

	if ( $computed )
		return $res;

	$computed = true;

	if ( !isset( $_GET['action'] ) )
		return $res;


	switch($_GET['action']) {
		case 'upgrade-plugin':
			$res = array( basename($_GET['plugin'], '.php') );
			break;

		case 'upgrade-theme':
			$res = array( $_GET['theme'] );
			break;

		case 'update-selected':
			$res = explode( ',', stripslashes( $_GET['plugins']) );
			foreach( $res as &$r ) { $r = basename($r, '.php'); }
			break;

		case 'update-selected-themes':
			$res = explode( ',', stripslashes( $_GET['themes']) );
			break;

		default: break;
	}

	return $res;	
}
