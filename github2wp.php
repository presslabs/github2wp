<?php
/*
 * Plugin Name: github2wp
 * Plugin URI: http://wordpress.org/extend/plugins/git2wp/ 
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 1.3.5
 */

define( 'GITHUB2WP_MAX_COMMIT_HIST_COUNT', 100 );
define( 'GITHUB2WP_ZIPBALL_DIR_PATH', ABSPATH . 'wp-content/uploads/' . basename( dirname(__FILE__) ) . '/' );
define( 'GITHUB2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . basename( dirname( __FILE__ ) ) );
define( 'GITHUB2WP', basename( __FILE__, '.php' ) );

require_once( 'class-github-2-wp.php' );
require_once( 'class-github-2-wp-file.php' );
require_once( 'github2wp_render.php' );

if ( ! function_exists( 'cron_add_6h' ) ):
	add_filter( 'cron_schedules', 'cron_add_6h' );
	function cron_add_6h( $schedules ) {
			$schedules['6h'] = array(
				'interval' => 21600,
				'display' => __( 'Once every 6 hours' , GITHUB2WP )
			);
	   return $schedules;
	}
endif;


//------------------------------------------------------------------------------
function github2wp_activate() {
    add_option( 'github2wp_options', array(
			'resource_list' => array(
				0 => array(
					'resource_link'  => home_url() . '/wp-content/plugins/' . basename( __FILE__, '.php' ),
          'repo_name'      => 'github2wp',
          'repo_branch'    => 'master',
          'username'       => 'PressLabs',
          'is_on_wp_svn'   => false
					)
				),
				'default' => array(
					'token_alt' => '15f16816a092c034995fcde4924dffe0f9216cb3'
				)
    	)
		);

    wp_schedule_event( current_time ( 'timestamp' ), '6h', 'github2wp_cron_hook' );
}
register_activation_hook( __FILE__, 'github2wp_activate' );

//------------------------------------------------------------------------------
function github2wp_deactivate() {
	github2wp_delete_options();
	delete_transient( 'github2wp_branches' );

	wp_clear_scheduled_hook( 'github2wp_cron_hook' );
}
register_deactivation_hook( __FILE__, 'github2wp_deactivate' );

//------------------------------------------------------------------------------
function github2wp_admin_notices_action() {
	settings_errors( 'github2wp_settings_errors' );
}
add_action( 'admin_notices', 'github2wp_admin_notices_action' );

//------------------------------------------------------------------------------
function github2wp_delete_options() {
	delete_option( 'github2wp_options' );
}

//------------------------------------------------------------------------------
// Add settings link on plugin page
function github2wp_settings_link( $links ) {
	$settings_link = '<a href="' . github2wp_return_settings_link() . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'github2wp_settings_link' );

//------------------------------------------------------------------------------
function github2wp_return_settings_link( $query_vars = '' ) {
	return admin_url( 'tools.php?page=' . plugin_basename( __FILE__ ) . $query_vars );
}

//----------------------------------------------------------------------------
function github2wp_head_commit_cron() {
	$options = get_option( 'github2wp_options' );
	$default = &$options['default'];

	$resource_list = &$options['resource_list'];

	if ( is_array( $resource_list ) and ! empty( $resource_list ) ) {
		foreach ( $resource_list as $index => &$resource ) {
			if ( 0 == $index )
				$args = array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'source'       => $resource['repo_branch'],
					'access_token' => $default['token_alt']
				);
			else
				$args = array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'source'       => $resource['repo_branch'],
					'access_token' => $default['access_token']
				);

			$git = new Github_2_WP( $args );
			$head = $git->get_head_commit();

			if ( $head )
				$resource['head_commit'] = $head;
		}
	}

	github2wp_update_options( 'github2wp_options', $options );
}

//------------------------------------------------------------------------------
function github2wp_token_cron() {
	$options = get_option( 'github2wp_options' );
	$default = &$options['default'];

	if ( isset( $default['access_token'] ) ) {
		$args = array(
			access_token => $default['access_token']
		);

		$git = new Github_2_WP( $args );

		if ( ! $git->check_user() ) {
			$default['access_token'] = null;
			$default['client_id'] = null;
			$default['client_secret'] = null;
			$default['app_reset'] = 1;
			
			github2wp_update_options( 'github2wp_options', $options );
		}
	}
}
add_action( 'github2wp_cron_hook', 'github2wp_head_commit_cron' );
add_action( 'github2wp_cron_hook', 'github2wp_token_cron' );


//------------------------------------------------------------------------------
// Dashboard integration
function github2wp_menu() {
	add_management_page( __( 'Git to WordPress Options Page', GITHUB2WP ), 'GitHub2WP', 
					   'manage_options', __FILE__, 'github2wp_options_page' );
}
add_action( 'admin_menu', 'github2wp_menu' );

//------------------------------------------------------------------------------
function github2wp_update_check_themes( $transient ) {
    $options = get_option('github2wp_options');
    $resource_list = $options['resource_list'];

    if ( count( $resource_list ) > 0 ) {
			foreach ( $resource_list as $resource ) {
				$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

        if ( 'theme' == $repo_type ) {
          $response_index = $resource['repo_name'];
					$current_version = github2wp_get_theme_version( $response_index );

          if ( $resource['head_commit'] ) {
            $new_version = substr( $resource['head_commit'], 0, 7 );
            $trans_new_version = $transient->response[ $response_index ]->new_version;

						if ( isset( $trans_new_version ) and ( 7 != strlen( $trans_new_version ) or false != strpos( $trans_new_version, '.') ) )
							unset($transient->response[ $response_index ]);

						if ( '-' != $current_version and '' != $current_version
							and $current_version != $new_version and false != $new_version ) {

								$update_url = 'http://themes.svn.wordpress.org/responsive/1.9.3.2/readme.txt';
								$zipball = GITHUB2WP_ZIPBALL_URL . '/' . wp_hash( $resource['repo_name'] ) . '.zip';

								$theme = array(
									'new_version' => $new_version,
									'url'         => $update_url,
									'package'     => $zipball
								);

								$transient->response[ $response_index ] = $theme;
							}
					
					} else {
						unset( $transient->response[ $response_index ] );
					}
				}
			}
		}
    return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'github2wp_update_check_themes', 10, 1);

//------------------------------------------------------------------------------
// Transform plugin info into the format used by the native WordPress.org API
function github2wp_toWpFormat( $data ) {
	$info = new StdClass;

	//The custom update API is built so that many fields have the same name and format
	//as those returned by the native WordPress.org API. These can be assigned directly. 
	$sameFormat = array(
		'name', 'slug', 'version', 'requires', 'tested', 'rating', 'upgrade_notice',
		'num_ratings', 'downloaded', 'homepage', 'last_updated',
	);

	foreach ( $sameFormat as $field ) {
		if ( isset( $data[ $field ] ) )
			$info->$field = $data[ $field ];
		else
			$info->$field = null;
	}

	//Other fields need to be renamed and/or transformed.
	$info->download_link = $data['download_url'];

	if ( ! empty( $data['author_homepage'] ) )
		$info->author = sprintf( '<a href="%s">%s</a>', $data['author_homepage'], $data['author'] );
	else
		$info->author = $data['author'];

	if ( is_object( $data['sections'] ) )
		$info->sections = get_object_vars( $data['sections'] );
	elseif ( is_array( $data['sections'] ) ) 
		$info->sections = $data['sections'];
	else
		$info->sections = array( 'description' => '' );

	return $info;
}


//------------------------------------------------------------------------------
function github2wp_inject_info( $result, $action = null, $args = null ) {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];

	if ( is_array( $resource_list ) and ! empty( $resource_list ) ) {
		foreach ( $resource_list as $resource ) {

			$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

			if ( 'plugin' == $repo_type ) {
				$response_index = $resource['repo_name'] . '/' . $resource['repo_name'] . '.php';
				$new_version = substr( $resource['head_commit'], 0, 7 );
				$homepage = github2wp_get_plugin_header( $plugin_file, 'AuthorURI' );
				$zipball = home_url() . '/?zipball=' . wp_hash( $resource['repo_name'] );

				$changelog = __( 'No changelog found', GITHUB2WP );

				$sections = array(
					'description' => github2wp_get_plugin_header( $response_index, 'Description' ),
					'changelog'   => $changelog,
				);
				$slug = dirname( $response_index );

				$relevant = ( 'plugin_information' == $action ) and isset( $args->slug ) and ( $args->slug == $slug );
				
				if ( ! $relevant )
					return $result;

				$plugin = array(
					'slug'            => $slug,
					'new_version'     => $new_version,
					'package'         => $zipball,
					'url'             => null,
					'name'            => github2wp_get_plugin_header( $response_index, 'Name' ),
					'version'         => $new_version,
					'homepage'        => null,
					'sections'        => $sections,
					'download_url'    => $zipball,
					'author'          => github2wp_get_plugin_header( $response_index, 'Author' ),
					'author_homepage' => github2wp_get_plugin_header( $response_index, 'AuthorURI' ),
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
			}
		}
	}
	return $result;
}
//Override requests for plugin information
add_filter( 'plugins_api', 'github2wp_inject_info', 20, 3 );

//------------------------------------------------------------------------------
function github2wp_update_check_plugins( $transient ) {	
    $options = get_option( 'github2wp_options' );
    $resource_list = $options['resource_list'];

    if ( count( $resource_list ) > 0 ) {
			foreach ( $resource_list as $resource ) {
				$repo_type = github2wp_get_repo_type($resource['resource_link']);
				
				if ( 'plugin' == $repo_type  ) {
					$response_index = $resource['repo_name'] . '/' . $resource['repo_name'] . '.php';
					$current_version = github2wp_get_plugin_version( $response_index );
					
					if ( $resource['head_commit'] ) {
						$new_version = substr( $resource['head_commit'], 0, 7 ); 
						$trans_new_version = $transient->response[ $response_index ]->new_version;

					if ( isset( $trans_new_version ) and ( 7 != strlen( $trans_new_version ) or false != strpos( $trans_new_version, '.') ) )
						unset( $transient->response[ $response_index ] );

					if ( '-' != $current_version and '' != $current_version
						and $current_version != $new_version and false != $new_version ) {
							$homepage = github2wp_get_plugin_header( $plugin_file, 'AuthorURI' );
							$zipball = GITHUB2WP_ZIPBALL_URL . '/' . wp_hash( $resource['repo_name'] ) . '.zip';

							$plugin = array(
								'slug'        => dirname( $response_index ),
								'new_version' => $new_version,
								'url'         => $homepage,
								'package'     => $zipball
							);
						$transient->response[ $response_index ] = (object) $plugin;
						}
					} else {
						unset( $transient->response[ $response_index ] );
					}
				}
			}
    }
    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'github2wp_update_check_plugins', 10, 1 );

//------------------------------------------------------------------------------
function github2wp_ajax_callback() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset( $_POST['github2wp_action'] ) and 'set_branch' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['id'] ) and isset( $_POST['branch'] ) ) {	
			$resource = &$resource_list[ $_POST['id'] ];

			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $resource['repo_branch'] 
				)
			);

			$branches = $git->fetch_branches();
			$branch_set = false;

			if ( count( $branches ) > 0) {
				foreach ( $branches as $br ) {
					if ( $br == $_POST['branch'] ) {
						$resource['repo_branch'] = $br;

						$sw = github2wp_update_options( 'github2wp_options', $options );

						if ( $sw ) {
							$branch_set = true;
							$response['success'] = true;
							break;
						}
					}
				}
			}

			if ( ! $branch_set )
				$response['error_messages'] = __( 'Branch not set', GITHUB2WP );  

			header( 'Content-type: application/json' );
			echo json_encode( $response );
			die();
		}
	}
	
	if ( isset( $_POST['github2wp_action'] ) and 'downgrade' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['commit_id'] ) and isset( $_POST['res_id'] ) ) {
			$resource = $resource_list[ $_POST['res_id'] ];
			$version = $_POST['commit_id'];

			if ( 0 != $_POST['res_id'] )
				$git = new Github_2_WP( array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'access_token' => $default['access_token'],
					'source'       => $version
					)
				);
			else
				$git = new Github_2_WP( array(
					'user'         => $resource['username'],
					'repo'         => $resource['repo_name'],
					'access_token' => $default['token_alt'],
					'source'       => $version		
					)
				);

			$version = substr( $version, 0, 7 );
			$type = github2wp_get_repo_type( $resource['resource_link'] );
			$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ) . '.zip';

			$sw = $git->store_git_archive();

			if ( $sw ) {
				if ( 'plugin' == $type )
					github2wp_uploadPlguinFile( $zipball_path, 'update' );
				else
					github2wp_uploadThemeFile( $zipball_path, 'update' );

				if ( file_exists( $zipball_path ) )
					unlink( $zipball_path );

				$response['success'] = true;
				$response['success_message'] = sprintf( __( 'The resource <b>%s<b> has been updated to %s .', GITHUB2WP ),
					$resource['repo_name'], $version );
			} else {
				$response['error_message'] = sprintf( __( 'The resource <b>%s<b> has FAILED to updated to %s .', GITHUB2WP ),
					$resource['repo_name'], $version );
			}

			header( 'Content-type: application/json' );
			echo json_encode( $response );
			die();
		}
	}

	if ( isset( $_POST['github2wp_action'] ) and 'fetch_history' == $_POST['github2wp_action'] ) {
		if ( isset ( $_POST['res_id'] ) ) {
			header( 'Content-Type: text/html' );

			$resource = $resource_list[ $_POST['res_id'] ];
			
			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $resource['repo_branch']
				)
			);

			$commit_history = $git->get_commits();

			github2wp_render_resource_history( $resource['repo_name'], $_POST['res_id'], $commit_history );

			die();
		}
	}
}
add_action( 'wp_ajax_github2wp_ajax', 'github2wp_ajax_callback' );

//-----------------------------------------------------------------------------
function github2wp_update_options( $where, $data ) {
	$data_array = array('option_value' => serialize( $data ) );
	$where_array = array( 'option_name' => $where );

	global $wpdb;
	$sw = $wpdb->update( $wpdb->prefix . 'options', $data_array, $where_array );

	if ( $sw ) {
	  $notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) && isset( $notoptions[ $where ] ) ) {
			unset( $notoptions[ $where ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		if ( ! defined( 'WP_INSTALLING' ) ) {
	  	$alloptions = wp_load_alloptions();
			
			if ( isset( $alloptions[ $where ] ) ) {
				$alloptions[ $where ] = $data_array['option_value'];
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			} else {
				wp_cache_set( $where, $data_array['option_value'], 'options' );
			}
		}
	}

	return $sw;
}

//------------------------------------------------------------------------------
function github2wp_add_javascript( $hook ) {
	if ( 'tools_page_github2wp/github2wp' != $hook )
		return;

	$script_file_name_url = plugins_url( 'github2wp.js', __FILE__ );
	$script_file_name_path = plugin_dir_path( __FILE__ ) . 'github2wp.js';
	wp_enqueue_script( 'github2wp_js', $script_file_name_url, array( 'jquery' ), filemtime( $script_file_name_path ) ); 
}
add_action( 'admin_enqueue_scripts', 'github2wp_add_javascript' ); 

//------------------------------------------------------------------------------
function github2wp_add_style( $hook ) {
	if ( 'tools_page_github2wp/github2wp' != $hook )
		return;

	$style_file_name_url = plugins_url( 'github2wp.css', __FILE__ );
	$style_file_name_path = plugin_dir_path( __FILE__ ) . 'github2wp.css';
	wp_enqueue_style( 'github2wp_css', $style_file_name_url, null, filemtime( $style_file_name_path ) );
}
add_action( 'admin_enqueue_scripts', 'github2wp_add_style' ); 

//------------------------------------------------------------------------------
function github2wp_options_page() {
	if ( ! current_user_can('manage_options') )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	
	$nav_bar_tabs = array(
		'resources',
		'settings',
		'history'
	);

	isset( $_GET['tab'] ) ? $tab = $_GET['tab'] : $tab = 'resources';

	if ( ! in_array( $tab, $nav_bar_tabs ) )
		$tab = 'resources';

	echo '<div class="wrap">';
		github2wp_render_plugin_icon();
		github2wp_render_tab_menu( $tab );

		if ( 'resources' == $tab ) {
		  github2wp_head_commit_cron();
			$options = get_option( 'github2wp_options' );
			github2wp_render_resource_form();
		}

		if ( 'settings' == $tab ) {
			github2wp_token_cron();
			$options = get_option( 'github2wp_options' );
			$default = &$options['default'];

			github2wp_render_settings_app_reset_message( $default );
			github2wp_render_settings_form( $default );
		}

		if ( 'history' == $tab )
			github2wp_render_history_page();
	echo '</div><!-- .wrap -->';
}

//------------------------------------------------------------------------------
function github2wp_needs_configuration() {
	$options = get_option( 'github2wp_options' );
	$default = $options['default'];

	return ( empty( $default['master_branch'] ) or empty( $default['client_id'] )
		or empty( $default['client_secret'] ) or empty( $default['access_token'] ) );
}

//------------------------------------------------------------------------------
function github2wp_admin_init() {
	register_setting( 'github2wp_options', 'github2wp_options', 'github2wp_options_validate' );	
	
	//
	// Resources tab
	//
	add_settings_section( 'github2wp_main_section', __( 'GitHub to WordPress - Resources', GITHUB2WP ),
		'github2wp_main_section_description', 'github2wp');
	add_settings_section( 'github2wp_resource_display_section', __( 'Your current GitHub resources', GITHUB2WP ),
		'github2wp_resource_display_section_description', 'github2wp_list' );
	
	//
	// Settings tab
	//
	add_settings_section( 'github2wp_second_section', __( 'GitHub to WordPress - Settings', GITHUB2WP ),
		'github2wp_second_section_description', 'github2wp_settings' );
	
	//
	// History tab
	//
	add_settings_section( 'github2wp_main_history_section', __( 'GitHub to WordPress - History', GITHUB2WP ),
		'github2wp_main_history_section_description', 'github2wp_history' );
	
	//
	// Add Settings notice
	//
	$plugin_page = plugin_basename( __FILE__ );
	$plugin_link = github2wp_return_settings_link( '&tab=settings' );

	$options = get_option( 'github2wp_options' );
	$default = $options['default'];

	if ( github2wp_needs_configuration() )
		add_action( 'admin_notices', create_function( '', 'echo \'<div class="error"><p>'
			. sprintf( __( 'GitHub2WP needs configuration information on its <a href="%s">Settings</a> page.', GITHUB2WP ), $plugin_link )
			. '</p></div>\';' )
		);

	if ( is_plugin_active( 'git2wp/git2wp.php' ) )
    add_action( 'admin_notices', create_function( '', 'echo \'<div class="error"><p>'
			. __( 'Git2WP is a further refined version of this plugin and is already installed on your server deactivate GitHub2WP.', GITHUB2WP )
			. '</p></div>\';' )
		);
}
add_action( 'admin_init', 'github2wp_admin_init' );

//------------------------------------------------------------------------------
function github2wp_second_section_description() {
	echo '<p>' . __( 'Enter here the default settings for the Github connexion.', GITHUB2WP ) . '</p>';
}

//------------------------------------------------------------------------------
function github2wp_main_history_section_description() {
	echo '<p>' . __( 'You can revert to an older version of a resource at any time.', GITHUB2WP ) . '</p>';
}

//------------------------------------------------------------------------------
function github2wp_resource_display_section_description() {
	echo '<p>' . __( 'Here you can manage your Github resources.', GITHUB2WP ) . '</p>';
}

//------------------------------------------------------------------------------
function github2wp_main_section_description() {
	echo '<p>' . __( 'Enter here the required data to set up a new GitHub endpoint.', GITHUB2WP ) . '</p>';
}

//------------------------------------------------------------------------------
function github2wp_str_between( $start, $end, $content ) {
	$r = explode( $start, $content );

	if ( isset( $r[1] ) ) {
		$r = explode( $end, $r[1] );
		return $r[0];
	}

	return '';
}

//------------------------------------------------------------------------------
function github2wp_get_repo_name_from_hash( $hash ) {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];
	foreach ( $resource_list as $res ) {
		$repo_name = $res['repo_name'];

		if ( $repo_name == $hash or wp_hash( $repo_name ) == $hash )
			return $repo_name;
	}

	return $repo_name;
}

//------------------------------------------------------------------------------
function github2wp_pluginFile_hashed( $hash ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	require_once( ABSPATH . '/wp-includes/pluggable.php' );

	$allPlugins = get_plugins();
	
	foreach( $allPlugins as $plugin_index => $plugin_value ) {
		$pluginFile = $plugin_index;
		$repo_name = substr( basename( $plugin_index ), 0, -4 );

		if ( $repo_name == $hash or $pluginFile == $hash or wp_hash( $repo_name ) == $hash )
			return $pluginFile;
	}

	return $hash;
}

//------------------------------------------------------------------------------
//
// Get the header of the plugin.
//
function github2wp_get_plugin_header( $pluginFile, $header = 'Version' ) {
	$pluginFile = github2wp_pluginFile_hashed( $pluginFile );

	if ( ! function_exists( 'get_plugins' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	$allPlugins = get_plugins();

	if ( 'ALL' == $header )
		return serialize( $allPlugins[ $pluginFile ] );

	if ( array_key_exists( $pluginFile, $allPlugins ) and array_key_exists( $header, $allPlugins[ $pluginFile ] ) )
		return $allPlugins[ $pluginFile ][ $header ];

	return '-';
}

//------------------------------------------------------------------------------
//
// Get the version of the plugin.
//
function github2wp_get_plugin_version( $pluginFile ) {
	return github2wp_get_plugin_header( $pluginFile );
}

//------------------------------------------------------------------------------
//
// Get the version of the theme.
//
function github2wp_get_theme_header( $theme_name, $header = 'Version' ) {
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme( $theme_name );

		if ( 'ALL' == $header )
			return serialize( $theme );

		return $theme->get( $header );
	}

	return '-';
}

//------------------------------------------------------------------------------
//
// Get the version of the theme.
//
function github2wp_get_theme_version( $theme_name ) {
	return github2wp_get_theme_header( $theme_name );
}

//------------------------------------------------------------------------------
//
// Returns the repo type: 'plugin' or 'theme'
//
function github2wp_get_repo_type( $resource_link ) {
	return github2wp_str_between( 'wp-content/', 's/', $resource_link );
}

//------------------------------------------------------------------------------
function github2wp_rmdir( $dir ) {
	if ( ! file_exists( $dir ) )
		return true;

	if ( ! is_dir( $dir ) or is_link( $dir ) )
		return unlink( $dir );

	foreach ( scandir( $dir ) as $item ) {
		if ( '.' == $item or '..' == $item )
			continue;

		if ( ! github2wp_rmdir( $dir . '/' . $item ) ) {
			chmod( $dir . '/' . $item, 0777 );
			if ( ! github2wp_rmdir( $dir . '/' . $item ) )
				return false;
		}
	}

	return rmdir( $dir );
}

//------------------------------------------------------------------------------
function github2wp_uploadThemeFile( $path, $mode = 'install' ) {
	//set destination dir
	$destDir = ABSPATH . 'wp-content/themes/';

	//set new file name
	$ftw = $destDir . basename( $path );
	$ftr = $path;	

	$theme_dirname = str_replace( '.zip', '', basename( $path ) );
	$theme_dirname = $destDir . github2wp_get_repo_name_from_hash( $theme_dirname ) . '/';

	if ( 'update' == $mode )
		github2wp_rmdir( $theme_dirname );

	$file = new Github_2_WP_File( $ftr, $ftw );

	if ( $file->checkFtr() ) {
		$file->writeToFile();
		github2wp_installTheme( $file->pathFtw() );
	}
}

//------------------------------------------------------------------------------
function github2wp_uploadPlguinFile( $path, $mode = 'install' ) {
	//set destination dir
	$destDir = ABSPATH . 'wp-content/plugins/';

	//set new file name
	$ftw = $destDir . basename( $path );
	$ftr = $path;

	$plugin_dirname = str_replace( '.zip', '', basename( $path ) );
	$plugin_dirname = $destDir . github2wp_get_repo_name_from_hash( $plugin_dirname ) . '/';
	
	if ( 'update' == $mode )
		github2wp_rmdir( $plugin_dirname );

	$file = new Github_2_WP_File( $ftr, $ftw );

	if ( $file->checkFtr() ) {
		$file->writeToFile();
		github2wp_installPlugin( $file->pathFtw() );
	}
}


//------------------------------------------------------------------------------
function github2wp_installTheme( $file ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	$upgrader = new Theme_Upgrader( new Theme_Installer_Skin( compact('type', 'title', 'nonce') ) );

	require_once( ABSPATH . 'wp-admin/admin-header.php' );
	$result = $upgrader->install( $file );
	require_once( ABSPATH . 'wp-admin/admin-footer.php' );

	github2wp_cleanup( $file );
}

//------------------------------------------------------------------------------
function github2wp_installPlugin( $file ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact('type', 'title', 'nonce') ) );

	require_once( ABSPATH . 'wp-admin/admin-header.php' );	
	$result = $upgrader->install( $file );
	require_once( ABSPATH . 'wp-admin/admin-footer.php' );

	github2wp_cleanup( $file );
}

//------------------------------------------------------------------------------
function github2wp_cleanup( $file ) {
	if ( file_exists( $file ) ) {
		return unlink( $file );
	}
}

//------------------------------------------------------------------------------
function github2wp_setting_resources_list() {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];

	if ( is_array( $resource_list )  and  ! empty( $resource_list ) ) {
	?>
		<br />
		<table id='the-list' class='wp-list-table widefat plugins' cellpadding='5' border='1' cellspacing='0' >
			<thead>
				<tr>
					<th></th>
					<th><?php _e( 'Resource', GITHUB2WP ); ?></th>
					<th><?php _e( 'Options', GITHUB2WP ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
				$new_transient = array();
				$transient = get_transient( 'github2wp_branches' );
				$default = $options['default'];

				if ( is_array( $resource_list ) and ! empty( $resource_list ) )		
					foreach ( $resource_list as $index => $resource ) {
						$k++;

						$git = new Github_2_WP( array(
							'user'         => $resource['username'],
							'repo'         => $resource['repo_name'],
							'access_token' => $default['access_token'],
							'source'       => $resource['repo_branch'] 
							)
						);
					
						if ( false === $transient ) {
							$branches = $git->fetch_branches();
							$new_transient[] = array(
								'repo_name' => $resource['repo_name'],
								'branches'  => $branches
							);
						} else {
							foreach ( $transient as $tran_res ) {
								if ( $tran_res['repo_name'] == $resource['repo_name'] ) {
									$branches = $tran_res['branches'];
									break;
								}
							}
						}

						$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

						$alternate = '';
						$my_data = '';
						
						if ( 0 == ($k % 2) )
							$alternate = 'class="inactive"';
						
						$resource_path = str_replace( home_url(), ABSPATH, $resource['resource_link'] );
						$dir_exists = is_dir( $resource_path );

						$action = github2wp_return_resource_dismiss( $resource, $k-1 );

						if ( ! $dir_exists ) {
							$my_data .= '<p><strong>' . __( 'The resource does not exist on your site!', GITHUB2WP ) . '</strong></p>';
							$action .= github2wp_return_resource_install( $resource, $k-1 );
						}

						if ( 'plugin' == $repo_type ) {
							$new_version = false;
							$plugin_file = $resource['repo_name'] . '/' . $resource['repo_name'] . '.php';
							$current_plugin_version = github2wp_get_plugin_version( $plugin_file );

							if ( $current_plugin_version > '-' and $current_plugin_version > '' ) {
								$my_data .= '<strong>' . github2wp_get_plugin_header( $plugin_file, 'Name' ) . '</strong>&nbsp;(';

								if ( $resource['is_on_wp_svn'] )
									$my_data .= '<div class="notification-warning" title="'
										. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP) . '"></div>';

								$author = github2wp_get_plugin_header( $plugin_file, 'Author' );
								$author_uri = github2wp_get_plugin_header( $plugin_file, 'AuthorURI' );
								$plugin_description = github2wp_get_plugin_header( $plugin_file, 'Description' );

								if ( '-' != $author_uri and '' != $author_uri )
									$author = "<a href='$author_uri' target='_blank'>$author</a>";

								$my_data .= __( 'Version ', GITHUB2WP ) . $current_plugin_version . '&nbsp;|&nbsp;';
								$my_data .= __( 'By ', GITHUB2WP ) . $author . ')&nbsp;';
								$my_data .= "<a id='need_help_$k' class='clicker' alt='res_details_$k'><strong>" . __( 'Details', GITHUB2WP ) . '</strong></a><br />';
								$my_data .= "<div id='res_details_$k' class='slider home-border-center'>";

								if ( '' != $plugin_description and '-' != $plugin_description )
									$my_data .= $plugin_description . '<br />';

								$new_version = substr( $resource['head_commit'], 0, 7 ); 
							}
	
							if ( new_version != $current_plugin_version and '-' != $current_plugin_version
								and '' != $current_plugin_version and false != $new_version ) {
									$my_data .= '<strong>' . __( 'New Version: ', GITHUB2WP ) . "</strong>$new_version<br /></div>";
									$action .= github2wp_return_resource_update( $resource, $k-1 );
							}
						} elseif ( 'theme' == $repo_type ) {
								$new_version = false;
								$theme_dirname = $resource['repo_name'];
								$current_theme_version = github2wp_get_theme_version( $theme_dirname );

								if ( $current_theme_version > '-' and $current_theme_version > '' ) {
									$my_data .= '<strong>' . github2wp_get_theme_header( $theme_dirname, 'Name' ) . '</strong>&nbsp;(';

									if ( $resource['is_on_wp_svn'] )
										$my_data .= '<div class="notification-warning" title="'
											. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP )
											. '"></div>';

									$author = github2wp_get_theme_header( $theme_file, 'Author');
									$author_uri = github2wp_get_theme_header( $theme_file, 'AuthorURI');
									$theme_description = github2wp_get_theme_header( $theme_dirname, 'Description');

									if ( '-' != $author_uri and '' != $author_uri )
										$author = "<a href='$author_uri' target='_blank'>$author</a>";

									$my_data .= __( 'Version ', GITHUB2WP) . $current_theme_version . '&nbsp;|&nbsp;';
									$my_data .= __( 'By ', GITHUB2WP) . $author . ')&nbsp;';
									$my_data .= "<a id='need_help_$k' class='clicker' alt='res_details_$k'><strong>" . __( 'Details', GITHUB2WP ) . '</strong></a><br />';
									$my_data .= "<div id='res_details_$k' class='slider home-border-center'>";

									if ( '' != $theme_description and '-' != $theme_description )
										$my_data .= $theme_description . '<br />';

									$new_version = substr( $resource['head_commit'], 0, 7) ;
								}

								if ( $new_version != $current_theme_version and false != $new_version
									and '-' != $current_theme_version and '' != $current_theme_version ) {
										$my_data .= '<strong>' . __( 'New Version:', GITHUB2WP ) . "</strong>$new_version<br /></div>";
										$action .= github2wp_return_resource_update( $resource, $k-1 );
							
									}
						}
	
						echo "<tr $alternate>"
										. "<td>$k</td>"
										. "<td>$my_data<br />"
												. github2wp_return_resource_git_link( $resource )
												. "<br />"
												. github2wp_return_wordpress_resource( $repo_type, $resource['repo_name'] )
												. "<br />"
												. github2wp_return_branch_dropdown( $index, $branches )
										. "</td>"
										. "<td>$action</td>"
										. "</tr>";				
					}
	
	if ( false === $transient)
		set_transient( 'github2wp_branches', $new_transient, 5*60 );

			?>
			</tbody>
		</table>
<?php
	}
}

///LEFT OFF HERE

//------------------------------------------------------------------------------
function github2wp_options_validate( $input ) {
	$options = get_option( 'github2wp_options' );

	if ( isset( $_POST['submit_resource'] ) and ! github2wp_needs_configuration() ) {
		$initial_options = $options;
		$resource_list = &$options['resource_list'];

		$repo_link = $_POST['resource_link'];
		$repo_branch = $_POST['master_branch'];

		if ( '' == $repo_branch )
			$repo_branch = $options['default']['master_branch'];

		if ( '' != $repo_link ) {
			$repo_link = trim( $repo_link );

			$data = Github_2_WP::get_data_from_git_clone_link( $repo_link );

			if ( isset( $data['user'] ) and isset( $data['repo'] ) ) {
				$resource_owner = $data['user'];
				$resource_repo_name = $data['repo'];

				$text_resource = '/' . $resource_repo_name;
				$text_resource = '/' . $_POST['resource_type_dropdown'] . $text_resource;
				$link = home_url() . '/wp-content' . $text_resource;
				$unique = true;

				if ( is_array( $resource_list ) and ! empty( $resource_list ) )
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

						add_settings_error( 'github2wp_settings_errors', 'repo_connected', __( 'Connection was established.', GITHUB2WP ), 'updated' );
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

	if ( is_array( $resource_list ) and ! empty( $resource_list ) ) {
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
					if ( 'plugin' == $repo_type )
						github2wp_uploadPlguinFile( $zipball_path );
					else
						github2wp_uploadThemeFile( $zipball_path );
					
					if ( file_exists( $zipball_path ) )
						unlink( $zipball_path );
				}
			}
		}
	}

	// update resources
	$resource_list = &$options['resource_list'];
	$k = 0;

	if ( is_array( $resource_list ) and ! empty( $resource_list ) ) {
		foreach ( $resource_list as $key => $resource ) {
			if ( isset( $_POST[ 'submit_update_resource_' . $k++ ] ) ) {
				$repo_type = github2wp_get_repo_type( $resource['resource_link'] );
				$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ) . '.zip';
				$default = $options['default'];

				if ( 0 != $key )
					$git = new Github_2_WP( array(
						'user'         => $resource['username'],
						'repo'         => $resource['repo_name'],
						'repo_type'    => $repo_type,
						'access_token' => $default['access_token'],
						'source'       => $resource['head_commit']
						)
					);
				else
					$git = new Github_2_WP( array(
						'user'         => $resource['username'],
						'repo'         => $resource['repo_name'],
						'repo_type'    => $repo_type,
						'access_token' => $default['token_alt'],
						'source'       => $resource['head_commit']
						)
					);
				$sw = $git->store_git_archive();

				if ( $sw ) {
					if ( 'plugin' == $repo_type )
						github2wp_uploadPlguinFile( $zipball_path, 'update' );
					else
						github2wp_uploadThemeFile( $zipball_path, 'update' );

					if ( file_exists( $zipball_path ) )
						unlink( $zipball_path );
				}
			}
		}
	}

	// delete resources
	$resource_list = &$options['resource_list'];
	$k = 0;

	if ( is_array( $resource_list ) and ! empty( $resource_list ) ) {
		foreach ( $resource_list as $key => $resource ) {
			if ( isset( $_POST[ 'submit_delete_resource_' . $k++ ] ) )
				if ( 0 != $key )
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

		if ( $master_branch and $client_id and $client_secret )
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

//------------------------------------------------------------------------------
function github2wp_init() {
	$options = get_option( 'github2wp_options' );

	$default = &$options['default'];

	// get token from GitHub
	if ( isset( $_GET['code'] ) and  isset( $_GET['github2wp_auth'] ) and 'true' == $_GET['github2wp_auth'] ) {
		$code = $_GET['code'];
		$options = get_option( 'github2wp_options' );
		$default = &$options['default'];
		$client_id = $default['client_id'];
		$client_secret = $default['client_secret'];
		$data = array(
			'code' => $code,
			'client_id' => $client_id,
			'client_secret' => $client_secret
		);

		$response = wp_remote_post( 'https://github.com/login/oauth/access_token', array( 'body' => $data ) );

		parse_str( $response['body'], $parsed_response_body );

		if ( null != $parsed_response_body['access_token'] ) {
			$default['access_token'] = $parsed_response_body['access_token'];
			$default['changed'] = 0;
			update_option( 'github2wp_options', $options );
		}
	}
}
add_action( 'init', 'github2wp_init' );


function github2wp_language_init() {
	load_plugin_textdomain( GITHUB2WP, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}
add_action( 'plugins_loaded', 'github2wp_language_init' );


function github2wp_admin_head() {
	if ( isset( $_GET['action'] )
		and ( 'update-selected' == $_GET['action'] or 'update-selected-themes' == $_GET['action'] ) ) {

			$options = get_option( 'github2wp_options' );
			$resource_list = $options['resource_list'];

			if ( isset( $_GET['plugins'] ) ) {
				$res = explode( ',', stripslashes( $_GET['plugins'] ) );

				foreach ( $res as &$r ) {
					$r = basename( $r, '.php' );
				}
			} elseif ( isset( $_GET['themes'] ) ) {
				$res = explode( ',', stripslashes( $_GET['themes'] ) );
			}

			if ( count( $res ) > 0 ) {
				foreach ( $resource_list as $key => $resource ) {
					if ( in_array( $resource['repo_name'], $res, true ) ) {
						$repo_type = github2wp_get_repo_type( $resource['resource_link'] );
						$default = $options['default'];

						if ( 0 != $key )
							$git = new Github_2_WP( array(
								'user'         => $resource['username'],
								'repo'         => $resource['repo_name'],
								'repo_type'    => $repo_type,
								'access_token' => $default['access_token'],
								'source'       => $resource['head_commit']
								)
							);
						else
							$git = new Github_2_WP(array(
								'user'         => $resource['username'],
								'repo'         => $resource['repo_name'],
								'repo_type'    => $repo_type,
								'access_token' => $default['token_alt'],
								'source'       => $resource['head_commit']
								)
							);
						$sw = $git->store_git_archive();
					}
				}
			}
		}
}
add_action( 'admin_head', 'github2wp_admin_head' );


function github2wp_admin_footer() {
	if ( defined( 'IFRAME_REQUEST' ) and isset( $_GET['action'] )
		and ( 'update-selected' == $_GET['action'] or 'update-selected-themes' == $_GET['action'] ) ) {
			
			$options = get_option('github2wp_options');
			$resource_list = $options['resource_list'];
			
			if ( isset( $_GET['plugins'] ) ) {
				$res = explode( ',', stripslashes( $_GET['plugins'] ) );

				foreach ( $res as &$r ) {
					$r = basename($r, '.php');
				}
			} elseif ( isset( $_GET['themes'] ) ) {
				$res = explode( ',', stripslashes($_GET['themes']) );
			}

			if ( count( $res ) > 0 ) {
				foreach ( $resource_list as $resource ) {
					if ( in_array( $resource['repo_name'], $res, true ) ) {
						$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ).'.zip';

						if ( file_exists( $zipball_path ) )
							unlink( $zipball_path );
					}
				}
			}
	}
}
add_action( 'admin_footer', 'github2wp_admin_footer' );
