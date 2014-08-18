<?php
/*
 * Plugin Name: github2wp
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 1.0.0
 */

require_once( 'loader.php' );


register_activation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'deactivate' ) ); 
register_uninstall_hook( __FILE__, array( 'GITHUB2WP_Setup', 'uninstall' ) );




function github2wp_change_transient_revert( $old_transient ) {
	/*$reverts = get_option('github2wp_reverts');

	$current_filter = current_filter();
	$resource_type = ( strpos( $current_filter, 'themes') !== false ) ? 'themes' : 'plugins';

	//TODO check if this function works and remember to add to reverts option and remove entries when necessary
	return $transient;*/

	return $old_transient;
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
							$current_plugin_version = github2wp_get_header( $plugin_file, 'Version' );

							if ( $current_plugin_version > '-' && $current_plugin_version > '' ) {
								$my_data .= '<strong>' . github2wp_get_header( $plugin_file, 'Name' ) . '</strong>&nbsp;(';

								if ( $resource['is_on_wp_svn'] )
									$my_data .= '<div class="notification-warning" title="'
										. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP) . '"></div>';

								$author = github2wp_get_header( $plugin_file, 'Author' );
								$author_uri = github2wp_get_header( $plugin_file, 'AuthorURI' );
								$plugin_description = github2wp_get_header( $plugin_file, 'Description' );

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
								$current_theme_version = github2wp_get_header( $theme_dirname, 'Version' );

								if ( $current_theme_version > '-' && $current_theme_version > '' ) {
									$my_data .= '<strong>' . github2wp_get_header( $theme_dirname, 'Name' ) . '</strong>&nbsp;(';

									if ( $resource['is_on_wp_svn'] )
										$my_data .= '<div class="notification-warning" title="'
											. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP )
											. '"></div>';

									$author = github2wp_get_header( $theme_file, 'Author');
									$author_uri = github2wp_get_header( $theme_file, 'AuthorURI');
									$theme_description = github2wp_get_header( $theme_dirname, 'Description');

									if ( '-' != $author_uri && '' != $author_uri )
										$author = "<a href='$author_uri' target='_blank'>$author</a>";

									$my_data .= __( 'Version ', GITHUB2WP) . $current_theme_version . '&nbsp;|&nbsp;';
									$my_data .= __( 'By ', GITHUB2WP) . $author . ')&nbsp;';
									$my_data .= "<a id='need_help_$k' class='clicker' alt='res_details_$k'><strong>" . __( 'Details', GITHUB2WP ) . '</strong></a><br />';
									$my_data .= "<div id='res_details_$k' class='slider home-border-center'>";

									if ( '' != $theme_description && '-' != $theme_description )
										$my_data .= $theme_description . '<br />';

									$new_version = substr( $resource['head_commit'], 0, 7 );
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




