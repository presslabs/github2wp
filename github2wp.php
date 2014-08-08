<?php
/*
 * Plugin Name: github2wp
 * Plugin URI: http://wordpress.org/extend/plugins/git2wp/ 
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 1.0.0
 */

require_once( 'loader.php' );


register_activation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'deactivate' ) ); 
register_uninstall_hook( __FILE__, array( 'GITHUB2WP_Setup', 'uninstall' ) );

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

						if ( isset( $trans_new_version ) && ( 7 != strlen( $trans_new_version ) || false != strpos( $trans_new_version, '.') ) )
							unset($transient->response[ $response_index ]);

						if ( '-' != $current_version && '' != $current_version
							&& $current_version != $new_version && false != $new_version ) {

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

	if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
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

				$relevant = ( 'plugin_information' == $action ) && isset( $args->slug ) && ( $args->slug == $slug );
				
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

					if ( isset( $trans_new_version ) && ( 7 != strlen( $trans_new_version ) || false != strpos( $trans_new_version, '.') ) )
						unset( $transient->response[ $response_index ] );

					if ( '-' != $current_version && '' != $current_version
						&& $current_version != $new_version && false != $new_version ) {
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

	if ( isset( $_POST['github2wp_action'] ) && 'set_branch' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['id'] ) && isset( $_POST['branch'] ) ) {	
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
	
	if ( isset( $_POST['github2wp_action'] ) && 'downgrade' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['commit_id'] ) && isset( $_POST['res_id'] ) ) {
			$resource = $resource_list[ $_POST['res_id'] ];
			$version = $_POST['commit_id'];

			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $version
				)
			);

			$version = substr( $version, 0, 7 );
			$type = github2wp_get_repo_type( $resource['resource_link'] );
			$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ) . '.zip';

			$sw = $git->store_git_archive();

			if ( $sw ) {
				github2wp_uploadFile( $zipball_path, $resource, 'update' );

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

	if ( isset( $_POST['github2wp_action'] ) && 'fetch_history' == $_POST['github2wp_action'] ) {
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



function github2wp_change_transient_revert( $old_transient ) {
	$reverts = get_option('github2wp_reverts');

	$current_filter = current_filter();
	$resource_type = ( strpos( $current_filter, 'themes') !== false ) ? 'themes' : 'plugins';

	if ( empty($reverts[ $resource_type ]) )
		return false;

	$response = array();
	foreach ( $reverts[ $resource_type ] as $res_slug ) {
		$repo_name = explode( '/', $res_slug )[0];

		$reponse[ $res_slug ] = (object) array(
			'slug'    => $repo_name,
			'version' => 'x#$!', //no need for it since we already have the right version downloaded
			'package' => GITHUB2WP_ZIPBALL_URL . '/' . wp_hash( $repo_name ) . '.zip'
		);
	}

	$transient = array(
		'lastchecked' => time(),
		'response' => $reponse
	);

	if ( false === $old_transient )
		return (object) $transient;

	$transient = wp_parse_args( $transient, (array) $old_transient );

	//TODO check if this function works and remember to add to reverts option and remove entries when necessary
	return (object) $transient;
}
add_filter( 'pre_site_transient_update_plugins', 'github2wp_change_transient_revert', 999 );
add_filter( 'pre_site_transient_update_themes', 'github2wp_change_transient_revert', 999 );


function github2wp_options_page() {
	if ( ! current_user_can('manage_options') )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	
	$nav_bar_tabs = array(
		'resources',
		'settings',
		'history',
		'faq'
	);

	isset( $_GET['tab'] ) ? $tab = $_GET['tab'] : $tab = 'resources';

	if ( ! in_array( $tab, $nav_bar_tabs ) )
		$tab = 'resources';

	echo '<div class="wrap">';
		github2wp_render_plugin_icon();
		github2wp_render_tab_menu( $tab );

		if ( 'resources' == $tab ) {
		  github2wp_head_commit_cron();

			wp_clean_plugins_cache(true);
			wp_clean_themes_cache(true);

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

		if ( 'faq' == $tab )
			github2wp_render_faq_page();

	echo '</div><!-- .wrap -->';
}



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

	if ( array_key_exists( $pluginFile, $allPlugins ) && array_key_exists( $header, $allPlugins[ $pluginFile ] ) )
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


function github2wp_get_theme_version( $theme_name ) {
	return github2wp_get_theme_header( $theme_name );
}


function github2wp_uploadFile( $path, array $resource, $mode='install' ) {
	$resource_type = github2wp_get_repo_type( $resource['resource_link'] );
	$resource_name = $resource['repo_name'];

	$destination_dir = WP_CONTENT_DIR;
	if ( 'plugin' == $resource_type )
		$destination_dir .= '/plugins/';
	elseif ( 'theme' == $resource_type )
		$destination_dir = '/themes/';
	else
		throw new InvalidArgumentException( 'Second parameter: must be a plugin resource db option!' );
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


function github2wp_setting_resources_list() {

	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];

	if ( is_array( $resource_list )  &&  ! empty( $resource_list ) ) {
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

				if ( is_array( $resource_list ) && ! empty( $resource_list ) )		
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

						$alternate = '';
						$my_data = '';
						$action = github2wp_return_resource_dismiss( $resource, $k-1 );

						$repo_type = github2wp_get_repo_type( $resource['resource_link'] );

						if ( 0 == ($k % 2) )
							$alternate = 'class="inactive"';

						$resource_path = WP_CONTENT_DIR;
						if ( 'plugin' == $repo_type )
							$resource_path .= '/plugins/';
						else
							$resource_path .= '/themes/';
						$resource_path .= basename($resource['resource_link']);

						if ( ! is_dir( $resource_path ) ) {
							$my_data .= '<p><strong>' . __( 'The resource does not exist on your site!', GITHUB2WP ) . '</strong></p>';
							$action .= github2wp_return_resource_install( $resource, $k-1 );
						}

						if ( 'plugin' == $repo_type ) {
							$new_version = false;
							$plugin_file = $resource['repo_name'] . '/' . $resource['repo_name'] . '.php';
							$current_plugin_version = github2wp_get_plugin_version( $plugin_file );

							if ( $current_plugin_version > '-' && $current_plugin_version > '' ) {
								$my_data .= '<strong>' . github2wp_get_plugin_header( $plugin_file, 'Name' ) . '</strong>&nbsp;(';

								if ( $resource['is_on_wp_svn'] )
									$my_data .= '<div class="notification-warning" title="'
										. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP) . '"></div>';

								$author = github2wp_get_plugin_header( $plugin_file, 'Author' );
								$author_uri = github2wp_get_plugin_header( $plugin_file, 'AuthorURI' );
								$plugin_description = github2wp_get_plugin_header( $plugin_file, 'Description' );

								if ( '-' != $author_uri && '' != $author_uri )
									$author = "<a href='$author_uri' target='_blank'>$author</a>";

								$my_data .= __( 'Version ', GITHUB2WP ) . $current_plugin_version . '&nbsp;|&nbsp;';
								$my_data .= __( 'By ', GITHUB2WP ) . $author . ')&nbsp;';
								$my_data .= "<a id='need_help_$k' class='clicker' alt='res_details_$k'><strong>" . __( 'Details', GITHUB2WP ) . '</strong></a><br />';
								$my_data .= "<div id='res_details_$k' class='slider home-border-center'>";

								if ( '' != $plugin_description && '-' != $plugin_description )
									$my_data .= $plugin_description . '<br />';

								$new_version = substr( $resource['head_commit'], 0, 7 ); 
							}
	
							if ( $new_version != $current_plugin_version && '-' != $current_plugin_version
								and '' != $current_plugin_version && false != $new_version ) {
									$my_data .= '<strong>' . __( 'New Version: ', GITHUB2WP ) . "</strong>$new_version<br /></div>";
									$action .= github2wp_return_resource_update( $resource, $k-1 );
							}
						} elseif ( 'theme' == $repo_type ) {
								$new_version = false;
								$theme_dirname = $resource['repo_name'];
								$current_theme_version = github2wp_get_theme_version( $theme_dirname );

								if ( $current_theme_version > '-' && $current_theme_version > '' ) {
									$my_data .= '<strong>' . github2wp_get_theme_header( $theme_dirname, 'Name' ) . '</strong>&nbsp;(';

									if ( $resource['is_on_wp_svn'] )
										$my_data .= '<div class="notification-warning" title="'
											. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP )
											. '"></div>';

									$author = github2wp_get_theme_header( $theme_file, 'Author');
									$author_uri = github2wp_get_theme_header( $theme_file, 'AuthorURI');
									$theme_description = github2wp_get_theme_header( $theme_dirname, 'Description');

									if ( '-' != $author_uri && '' != $author_uri )
										$author = "<a href='$author_uri' target='_blank'>$author</a>";

									$my_data .= __( 'Version ', GITHUB2WP) . $current_theme_version . '&nbsp;|&nbsp;';
									$my_data .= __( 'By ', GITHUB2WP) . $author . ')&nbsp;';
									$my_data .= "<a id='need_help_$k' class='clicker' alt='res_details_$k'><strong>" . __( 'Details', GITHUB2WP ) . '</strong></a><br />';
									$my_data .= "<div id='res_details_$k' class='slider home-border-center'>";

									if ( '' != $theme_description && '-' != $theme_description )
										$my_data .= $theme_description . '<br />';

									$new_version = substr( $resource['head_commit'], 0, 7) ;
								}

								if ( $new_version != $current_theme_version && false != $new_version
									and '-' != $current_theme_version && '' != $current_theme_version ) {
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


function github2wp_init() {
	$options = get_option( 'github2wp_options' );

	$default = &$options['default'];

	// get token from GitHub
	if ( isset( $_GET['code'] ) &&  isset( $_GET['github2wp_auth'] ) && 'true' == $_GET['github2wp_auth'] ) {
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
		and ( 'update-selected' == $_GET['action'] || 'update-selected-themes' == $_GET['action'] ) ) {

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
			}
		}
}
add_action( 'admin_head', 'github2wp_admin_head' );


function github2wp_admin_footer() {
	if ( defined( 'IFRAME_REQUEST' ) && isset( $_GET['action'] )
		and ( 'update-selected' == $_GET['action'] || 'update-selected-themes' == $_GET['action'] ) ) {
			
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
