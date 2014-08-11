<?php


add_action( 'admin_init', 'github2wp_admin_init' );
function github2wp_admin_init() {
	register_setting(
		'github2wp_options',
		'github2wp_options',
		'github2wp_options_validate'
	);	
	


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


function github2wp_second_section_description() {
	echo '<p>' . __( 'Enter here the default settings for the Github connexion.', GITHUB2WP ) . '</p>';
}

function github2wp_main_history_section_description() {
	echo '<p>' . __( 'You can revert to an older version of a resource at any time.', GITHUB2WP ) . '</p>';
}

function github2wp_resource_display_section_description() {
	echo '<p>' . __( 'Here you can manage your Github resources.', GITHUB2WP ) . '</p>';
}

function github2wp_main_section_description() {
	echo '<p>' . __( 'Enter here the required data to set up a new GitHub endpoint.', GITHUB2WP ) . '</p>';
}

function github2wp_main_faq_section_description() {
	echo '<p>' . __( 'If you can\'t find an answer to your problem contact us.', GITHUB2WP ) . '</p>';
}



add_filter( 'plugin_action_links_' . GITHUB2WP_PLUGIN_BASENAME, 'github2wp_settings_link' );
function github2wp_settings_link( $links ) {
	$settings_link = '<a href="' . github2wp_return_settings_link() . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}



add_action( 'admin_menu', 'github2wp_menu' );
function github2wp_menu() {
	add_management_page( __( 'Git to WordPress Options Page', GITHUB2WP ), 'GitHub2WP', 
					   'manage_options', GITHUB2WP_MAIN_PLUGIN_FILE, 'github2wp_options_page' );
}



add_action( 'admin_enqueue_scripts', 'github2wp_add_resources' ); 
function github2wp_add_resources( $hook ) {
	if ( ! github2wp_check_toolpage_hook( $hook ) )
		return;

	github2wp_enqueue_resource( 'github2wp.js', array('jquery'), false );
	github2wp_enqueue_resource( 'github2wp.css', array(), 'ALL' );
}



function github2wp_needs_configuration() {
	$options = get_option( 'github2wp_options' );
	$default = $options['default'];

	return ( empty( $default['master_branch'] ) || empty( $default['client_id'] )
		or empty( $default['client_secret'] ) || empty( $default['access_token'] ) );
}



//TODO refactor this shit !!!
function github2wp_options_validate( $input ) {
	$options = get_option( 'github2wp_options' );

	if ( isset( $_POST['submit_resource'] ) && ! github2wp_needs_configuration() ) {
		$initial_options = $options;
		$resource_list = &$options['resource_list'];

		$repo_link = $_POST['resource_link'];
		$repo_branch = $_POST['master_branch'];

		if ( '' == $repo_branch )
			$repo_branch = $options['default']['master_branch'];

		if ( '' != $repo_link ) {
			$repo_link = trim( $repo_link );

			$data = Github_2_WP::get_data_from_git_clone_link( $repo_link );

			if ( isset( $data['user'] ) && isset( $data['repo'] ) ) {
				$resource_owner = $data['user'];
				$resource_repo_name = $data['repo'];

				$text_resource = '/' . $resource_repo_name;
				$text_resource = '/' . $_POST['resource_type_dropdown'] . $text_resource;
				$link = home_url() . '/wp-content' . $text_resource;
				$unique = true;

				if ( is_array( $resource_list ) && ! empty( $resource_list ) )
					foreach ( $resource_list as $resource ) {
						if ( $resource['repo_name'] === $resource_repo_name ) {
							$unique = false;
							break;
						}
				}

				if ( $unique ) {
					$default = $options['default'];

					$args = array(
						'user'         => $resource_owner,
						'repo'         => $resource_repo_name,
						'access_token' => $default['access_token'],
						'source'       => $repo_branch 
					);

					$git = new Github_2_WP( $args );
					$sw = $git->check_repo_availability();

					if ( $sw ) {
						$on_wp = Github_2_WP::check_svn_avail( $resource_repo_name, substr( $_POST['resource_type_dropdown'], 0, -1 ) );
						$head = $git->get_head_commit();

						$resource_list[] = array(
							'resource_link' => $link,
							'repo_name'     => $resource_repo_name,
							'repo_branch'   => $repo_branch,
							'username'      => $resource_owner,
							'is_on_wp_svn'  => $on_wp,
							'head_commit'   => $head
						);

						add_settings_error(
							'github2wp_settings_errors',
							'repo_connected',
							__( 'Connection was established.', GITHUB2WP ),
							'updated'
						);

						delete_transient( 'github2wp_branches' );
					} else {
						return $initial_options;
					}
				} else {
					add_settings_error( 'github2wp_settings_errors', 'duplicate_endpoint', 
						__( 'Duplicate resources! Repositories can\'t be both themes and plugins ', GITHUB2WP),
						'error' );

					return $initial_options;
				}
			} else {
				add_settings_error( 'github2wp_settings_errors', 'not_git_link', 
					__( 'This isn\'t a git link! eg: https://github.com/dragospl/pressignio.git', GITHUB2WP), 
					'error' );

				return $initial_options;
			}
		}
	}

	// install resources
	$resource_list = &$options['resource_list'];
	$k = 0;

	if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
		foreach ( $resource_list as $key => $resource ) {
			if ( isset( $_POST[ 'submit_install_resource_' . $k++ ] ) ) {
				$repo_type = github2wp_get_repo_type($resource['resource_link']);
				$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';

				$default = $options['default'];
				$git = new Github_2_WP( array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'repo_type'    => $repo_type,
					'access_token' => $default['access_token'],
					'source'       => $resource['repo_branch']
					)
				);
				$sw = $git->store_git_archive();

				if ( $sw ) {
					github2wp_uploadFile( $zipball_path, $resource );
					
					if ( file_exists( $zipball_path ) )
						unlink( $zipball_path );
				}
			}
		}
	}

	// update resources
	$resource_list = &$options['resource_list'];
	$k = 0;

	if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
		foreach ( $resource_list as $key => $resource ) {
			if ( isset( $_POST[ 'submit_update_resource_' . $k++ ] ) ) {
				$repo_type = github2wp_get_repo_type( $resource['resource_link'] );
				$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ) . '.zip';
				$default = $options['default'];

				$git = new Github_2_WP( array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'repo_type'    => $repo_type,
					'access_token' => $default['access_token'],
					'source'       => $resource['head_commit']
					)
				);

				$sw = $git->store_git_archive();

				if ( $sw ) {
					github2wp_uploadFile( $zipball_path, $resource, 'update' );

					if ( file_exists( $zipball_path ) )
						unlink( $zipball_path );
				}
			}
		}
	}

	// delete resources
	$resource_list = &$options['resource_list'];
	$k = 0;

	if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
		foreach ( $resource_list as $key => $resource ) {
			if ( isset( $_POST[ 'submit_delete_resource_' . $k++ ] ) )
					unset( $resource_list[ $key ] );
		}
	}

	// settings
	if ( isset( $_POST['submit_settings'] ) ) {
		$default = &$options['default'];

		$client_id = $default['client_id'];
		$client_secret = $default['client_secret'];

		if ( isset( $_POST['master_branch'] ) ) {
			if ( $_POST['master_branch'] )
				$master_branch = trim( $_POST['master_branch'] );
			else
				$master_branch = 'master';
		}

		if ( isset( $_POST['client_id'] ) )
			if ( $_POST['client_id'] != $default['client_id'] ) {
				$client_id = trim($_POST['client_id']);
				$changed = 1;
			}

		if ( isset( $_POST['client_secret'] ) )
			if ( $_POST['client_secret'] != $default['client_secret'] ) {
				$client_secret = trim($_POST['client_secret']);
				$changed = 1;
			}

		if ( $master_branch && $client_id && $client_secret )
			$default['app_reset']=0;

		$default['master_branch'] = $master_branch;
		$default['client_id'] = $client_id;
		$default['client_secret'] = $client_secret;

		if ( $changed ) {
			$default['access_token'] = NULL;
			$default['changed'] = $changed;
		}
	}

	return $options;
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
		$current_version = github2wp_get_header( $response_index, 'Version' );
		$new_version = substr( $resource['head_commit'], 0, 7);

		if ( $current_version && $new_version && $current_version != $new_version ) {
			$zipball = github2wp_generate_zipball_endpoint( $resource['repo_name'] );
				$theme = array(
					'new_version' => $new_version,
					'package'     => $zipball
				);

				$transient->response[ $response_index ] = $theme;
		} else {
			unset( $transient->response[ $response_index ] );
		}
	}

  return $transient;
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

	if ( !defined( 'IFRAME_REQUEST' ) && in_array($_GET['action'], $bulk_actions ))
		return;

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
