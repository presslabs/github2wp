<?php

require_once('updater.php');



add_action( 'admin_enqueue_scripts', 'github2wp_add_resources' ); 
function github2wp_add_resources( $hook ) {
	if ( ! github2wp_check_toolpage_hook( $hook ) )
		return;

	github2wp_enqueue_resource( 'github2wp.js', array('jquery'), false );
	github2wp_enqueue_resource( 'github2wp.css', array(), 'ALL' );
}



add_action( 'admin_init', 'github2wp_admin_init' );
function github2wp_admin_init() {
	register_setting(
		'github2wp_options',
		'github2wp_options',
		'github2wp_options_validate'
	);	

	github2wp_add_sections();
}



add_action( 'admin_menu', 'github2wp_menu' );
function github2wp_menu() {
	add_management_page( __( 'Git to WordPress Options Page', GITHUB2WP ), 'GitHub2WP', 
					   'manage_options', GITHUB2WP_MAIN_PLUGIN_FILE, 'github2wp_options_page' );
}



add_filter( 'plugin_action_links_' . GITHUB2WP_PLUGIN_BASENAME, 'github2wp_settings_link' );
function github2wp_settings_link( $links ) {
	$settings_link = '<a href="' . github2wp_return_settings_link() . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}



function github2wp_options_validate( $input ) {
	require_once('form.php');

	$options = get_option( 'github2wp_options' );

	if ( isset( $_POST['submit_settings'] ) )
		return github2wp_submit_settings( $options );


	if( github2wp_needs_configuration() )
		return $options;


	if ( isset( $_POST['submit_resource'] ) )
		return github2wp_submit_resource( $options );

	return github2wp_process_resource_request( $options );
}



add_filter( 'site_transient_update_plugins', 'github2wp_change_transient_revert', 999, 1 );
add_filter( 'site_transient_update_themes', 'github2wp_change_transient_revert', 999, 1 );
function github2wp_change_transient_revert( $transient ) {
	$reverts = get_option('github2wp_reverts');

	if( empty($reverts) )
		return $transient;

	$current_filter = current_filter();
	$filter_target = ( $current_filter === 'site_transient_update_themes' ) ? 'theme' : 'plugin';
	
	$response = &$transient->response;

	foreach( $reverts as $res_slug => $version ) {
		$info = pathinfo($res_slug);
		$type = ( isset($info['extension']) ) ? 'plugin' : 'theme';
		if ( $filter_target !== $type)
			continue;

		$new_response = (array) $response[ $res_slug ];
		$new_response['new_version'] = $version;
		$new_response['package'] = github2wp_generate_zipball_endpoint($info['filename']);
		
		$response[ $res_slug ] = ( 'plugin' === $filter_target ) ? (object) $new_response : $new_response;
	}

	return $transient;
}



add_filter( 'pre_set_site_transient_update_themes', 'github2wp_update_check_resources', 10, 1);
add_filter( 'pre_set_site_transient_update_plugins', 'github2wp_update_check_resources', 10, 1);
function github2wp_update_check_resources( $transient ) {
	$options = get_option('github2wp_options');
	$resource_list = $options['resource_list'];

	if ( empty($resource_list) )
		return $transient;

	$current_filter = current_filter();

	switch( $current_filter ) {
		case 'pre_set_site_transient_update_themes':
			$filter_type = 'theme';
			break;
		case 'pre_set_site_transient_update_plugins':
			$filter_type = 'plugin';
			break;
	}

	foreach ( $resource_list as $resource ) {
		$repo_type = github2wp_get_repo_type( $resource['resource_link'] );
		if ( $filter_type !== $repo_type )
			continue;

		$response_index = $resource['repo_name'];
		if ( 'plugin' === $filter_type )
			$response_index .= '/'. $resource['repo_name'] . '.php';

		$current_version = github2wp_get_header( $response_index, 'Version' );
		$new_version = substr( $resource['head_commit'], 0, 7);

		if ( $current_version && $new_version && $current_version != $new_version ) {
			$zipball = github2wp_generate_zipball_endpoint( $resource['repo_name'] );
			$res = array(
				'new_version' => $new_version,
				'package'     => $zipball
			);

			if( 'plugin' === $filter_type )
				$res = (object) $res;

				$transient->response[ $response_index ] = $res;
		} else {
			unset( $transient->response[ $response_index ] );
		}
	}


  return $transient;
}



add_filter( 'plugins_api', 'github2wp_inject_info', 20, 3 );
function github2wp_inject_info( $result, $action = null, $args = null ) {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];

	if( empty($resource_list) )
		return $result;

	foreach ( $resource_list as $resource ) {
		$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

		if ( 'plugin' !== $repo_type )
			continue;

		$response_index = $resource['repo_name'] . '/' . $resource['repo_name'] . '.php';
		$slug = dirname( $response_index );

		$relevant = ( 'plugin_information' == $action ) && isset( $args->slug ) && ( $args->slug == $slug );
		if ( $relevant )
			break;
	}
		
	if ( !$relevant )
		return $result;

	$new_version = substr( $resource['head_commit'], 0, 7 );
	$homepage = github2wp_get_header( $plugin_file, 'AuthorURI' );
	$zipball = github2wp_generate_zipball_endpoint( $resource['repo_name'] );

	$changelog = __( 'No changelog found', GITHUB2WP );

	$sections = array(
		'description' => github2wp_get_header( $response_index, 'Description' ),
		'changelog'   => $changelog,
	);
	

	$plugin = array(
		'slug'            => $slug,
		'new_version'     => $new_version,
		'package'         => $zipball,
		'url'             => null,
		'name'            => github2wp_get_header( $response_index, 'Name' ),
		'version'         => $new_version,
		'homepage'        => null,
		'sections'        => $sections,
		'download_url'    => "https://github.com/{$resource['username']}/{$resource['repo_name']}/",
		'author'          => github2wp_get_header( $response_index, 'Author' ),
		'author_homepage' => github2wp_get_header( $response_index, 'AuthorURI' ),
		'requires'        => null,
		'tested'          => null,
		'upgrade_notice'  => __( 'Here\'s why you should upgrade...', GITHUB2WP ),
		'rating'          => null,
		'num_ratings'     => null,
		'downloaded'      => null,
		'last_updated'    => null
	);

	$pluginInfo = github2wp_toWpFormat( $plugin );
	if ( $pluginInfo )
		return $pluginInfo;

	return $result;
}



function github2wp_add_sections() {
	add_settings_section(
		'github2wp_main_section',
		__( 'GitHub to WordPress - Resources', GITHUB2WP ),
		'github2wp_main_section_description',
		'github2wp'
	);
	
	
	add_settings_section(
		'github2wp_resource_display_section',
		__( 'Your current GitHub resources', GITHUB2WP ),
		'github2wp_resource_display_section_description',
		'github2wp_list'
	);
	

	add_settings_section(
		'github2wp_second_section',
		__( 'GitHub to WordPress - Settings', GITHUB2WP ),
		'github2wp_second_section_description',
		'github2wp_settings'
	);


	add_settings_section(
		'github2wp_main_history_section',
		__( 'GitHub to WordPress - History', GITHUB2WP ),
		'github2wp_main_history_section_description',
		'github2wp_history'
	);


	add_settings_section(
		'github2wp_main_faq_section',
		__( 'GitHub to WordPress - FAQ', GITHUB2WP ),
		'github2wp_main_faq_section_description',
		'github2wp_faq'
	);
}
