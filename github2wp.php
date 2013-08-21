<?php
/*
 * Plugin Name: github2wp
 * Plugin URI: http://wordpress.org/extend/plugins/git2wp/ 
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 1.3.5
 */

define('GIT2WP_MAX_COMMIT_HIST_COUNT', 100);
define('GIT2WP_ZIPBALL_DIR_PATH', ABSPATH . 'wp-content/uploads/' . basename(dirname(__FILE__)) . '/' );
define('GIT2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . basename(dirname(__FILE__)) );

require_once('Git2WP.class.php');
require_once('Git2WpFile.class.php');
require_once('git2wp_render.php');

/*
///REMOVE ONLY FOR TESTING PURPOSES
 add_filter( 'cron_schedules', 'cron_add_30s' );
 function cron_add_30s( $schedules ) {
    $schedules['30s'] = array(
        'interval' => 30,
        'display' => __( 'Once every 30 seconds' )
    );
    return $schedules;
 }

*/

//------------------------------------------------------------------------------
function git2wp_activate() {
    add_option('git2wp_options', array(
                      'resource_list' => array( 0 => array(
                                                'resource_link'  => home_url() . "/wp-content/plugins/" . basename(__FILE__, '.php'),
                                                'repo_name' => 'github2wp',
                                                'repo_branch' => 'master',
                                                'username' => 'PressLabs',
                                                'is_on_wp_svn' => false
                                              )
                                           ),
                      'default' => array( 'token_alt' => '15f16816a092c034995fcde4924dffe0f9216cb3')
                     ));
	///REMOVE TESTING ONLY
	//wp_schedule_event( current_time ( 'timestamp' ), '30s', 'git2wp_token_cron_hook' );
	//wp_schedule_event( current_time ( 'timestamp' ), '30s', 'git2wp_head_commit_cron_hook' );
	
	
    wp_schedule_event( current_time ( 'timestamp' ), 'twicedaily', 'git2wp_token_cron_hook' );
	wp_schedule_event( current_time ( 'timestamp' ), 'twicedaily', 'git2wp_head_commit_cron_hook' );
}
register_activation_hook(__FILE__,'git2wp_activate');

//------------------------------------------------------------------------------
function git2wp_deactivate() {
	git2wp_delete_options();
	delete_transient('git2wp_branches');
	wp_clear_scheduled_hook( 'git2wp_token_cron_hook' );
	wp_clear_scheduled_hook( 'git2wp_head_commit_cron_hook' );
}
register_deactivation_hook(__FILE__,'git2wp_deactivate');

//------------------------------------------------------------------------------
function git2wp_admin_notices_action() {
	settings_errors('git2wp_settings_errors');
}
add_action( 'admin_notices', 'git2wp_admin_notices_action' );

//------------------------------------------------------------------------------
function git2wp_delete_options() {
	delete_option('git2wp_options');
}

//------------------------------------------------------------------------------
// Add settings link on plugin page
function git2wp_settings_link($links) {
	$settings_link = "<a href='".git2wp_return_settings_link()."'>". __("Settings")."</a>";
	array_unshift($links, $settings_link);
	
	return $links;
}
add_filter("plugin_action_links_".plugin_basename(__FILE__), 'git2wp_settings_link' );

//------------------------------------------------------------------------------
function git2wp_return_settings_link($query_vars = '') {
	return admin_url('tools.php?page=' . plugin_basename(__FILE__) . $query_vars);
}

//----------------------------------------------------------------------------
function git2wp_head_commit_cron() {
    error_log('head commit cron');
	$options = get_option('git2wp_options');
	$default = &$options['default'];
	
	$resource_list = &$options['resource_list'];
	
	if(is_array($resource_list) && !empty($resource_list))
		foreach($resource_list as $index => &$resource) {
			if($index == 0)
				$args = array(
					'user' => $resource['username'],
					'repo' => $resource['repo_name'],
					'source' => $resource['repo_branch'],
					'access_token' => $default['token_alt']
				);
			else
				$args = array(
					'user' => $resource['username'],
					'repo' => $resource['repo_name'],
					'source' => $resource['repo_branch'],
					'access_token' => $default['access_token']
				);
			
			$git = new Git2WP($args);
			$head = $git->get_head_commit();
			
			if($head)
				$resource['head_commit'] = $head;
		}
	
	update_option('git2wp_options', $options);
}

//------------------------------------------------------------------------------
function git2wp_token_cron() {
    error_log('token cron');
	$options = get_option('git2wp_options');
	$default = &$options['default'];
	
	if(isset($default['access_token'])) {
		$args = array(
			access_token => $default['access_token']
		);

		$git = new Git2WP($args);

		if(!$git->check_user()) {
			$default['access_token'] = null;
			$default['client_id'] = null;
			$default['client_secret'] = null;
			$default['app_reset'] = 1;
			update_option("git2wp_options", $options);
		}	
	}
}
add_action( 'git2wp_head_commit_cron_hook', 'git2wp_head_commit_cron' );
add_action( 'git2wp_token_cron_hook', 'git2wp_token_cron' );


//------------------------------------------------------------------------------
// Dashboard integration
function git2wp_menu() {
	add_management_page('Git to WordPress Options Page', 'GitHub2WP', 
					   'manage_options', __FILE__, 'git2wp_options_page');
}
add_action('admin_menu', 'git2wp_menu');

//------------------------------------------------------------------------------
function git2wp_update_check_themes($transient) {
    $options = get_option('git2wp_options');
    $resource_list = $options['resource_list'];

    if ( count($resource_list) > 0 ) {
        foreach ($resource_list as $resource) {
            $repo_type = git2wp_get_repo_type($resource['resource_link']);

            if ( ($repo_type == 'theme') ) {
                $response_index = $resource['repo_name'];
                $current_version = git2wp_get_theme_version($response_index);
                if($resource['head_commit']) {
                    $new_version = substr($resource['head_commit'], 0, 7); //strval (strtotime($resource['head_commit']) );
                    $trans_new_version = $transient->response[ $response_index ]->new_version;
			
					if( isset($trans_new_version) && (strlen($trans_new_version) != 7 || strpos($trans_new_version, ".") != FALSE) )
					unset($transient->response[ $response_index ]);
						
						if ( ($current_version != '-') && ($current_version != '') && ($current_version != $new_version) && ($new_version != false) ) {
							$update_url = 'http://themes.svn.wordpress.org/responsive/1.9.3.2/readme.txt';
							//$zipball = GIT2WP_ZIPBALL_URL . '/' . $resource['repo_name'].'.zip';
							$zipball = home_url() . '/?zipball=' . wp_hash($resource['repo_name']);
							$theme = array(
									'new_version' => $new_version,
									"url" => $update_url,
									'package' => $zipball
							);
							$transient->response[ $response_index ] = $theme;
						}
                }else
                    unset($transient->response[ $response_index ]);
            }
        }
    }
    return $transient;
}
add_filter("pre_set_site_transient_update_themes","git2wp_update_check_themes", 10, 1); //WP 3.0+

//------------------------------------------------------------------------------
// Transform plugin info into the format used by the native WordPress.org API
function git2wp_toWpFormat($data){
	$info = new StdClass;
	
	//The custom update API is built so that many fields have the same name and format
	//as those returned by the native WordPress.org API. These can be assigned directly. 
	$sameFormat = array(
		'name', 'slug', 'version', 'requires', 'tested', 'rating', 'upgrade_notice',
		'num_ratings', 'downloaded', 'homepage', 'last_updated',
	);
	foreach ($sameFormat as $field) {
		if ( isset($data[$field]) ) {
			$info->$field = $data[$field];
		}
		else {
			$info->$field = null;
		}
	}

	//Other fields need to be renamed and/or transformed.
	$info->download_link = $data["download_url"];
	
	if ( !empty($data["author_homepage"]) ) {
		$info->author = sprintf('<a href="%s">%s</a>', $data["author_homepage"], $data["author"]);
	}
	else {
		$info->author = $data["author"];
	}
	
	if ( is_object($data["sections"]) ) {
		$info->sections = get_object_vars($data["sections"]);
	}
	elseif ( is_array($data["sections"]) ) {
		$info->sections = $data["sections"];
	}
	else {
		$info->sections = array('description' => '');
	}
	return $info;
}

//------------------------------------------------------------------------------
function git2wp_get_commits($payload) {
	$out = '';
	if ( $payload != null ) {
		$obj = json_decode($payload);
		$commits = $obj->{"commits"};
		$out .= '<ul>';
		foreach($commits as $commit)
			$out .= "<li>" . $commit->{"message"} . "</li>";
		$out .= '</ul>';
	}
	return $out;
}

//------------------------------------------------------------------------------
function git2wp_inject_info($result, $action = null, $args = null) {
	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];

	if ( is_array($resource_list)  and  !empty($resource_list)) {
		foreach($resource_list as $resource) {

			$repo_type = git2wp_get_repo_type($resource['resource_link']);

			if ( ($repo_type == 'plugin') ) {
				$response_index = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
				$new_version = substr($resource['head_commit'], 0, 7);
				$homepage = git2wp_get_plugin_header($plugin_file, "AuthorURI");
				$zipball = home_url() . '/?zipball=' . wp_hash($resource['repo_name']);

				$changelog = 'No changelog found';

				$sections = array(
					"description" => git2wp_get_plugin_header($response_index, "Description"),
					"changelog" => $changelog,
				);
				$slug = dirname( $response_index );
				
				$relevant = ($action == 'plugin_information') && isset($args->slug) && ($args->slug == $slug);
				if ( !$relevant ) {
					return $result;
				}
				
				$plugin = array(
					'slug' => $slug,
					'new_version' => $new_version,
					'package' => $zipball,
					"url" => null,
					"name" => git2wp_get_plugin_header($response_index, "Name"),
					"version" => $new_version,
					"homepage" => null,
					"sections" => $sections,
					"download_url" => $zipball,
					"author" => git2wp_get_plugin_header($response_index, "Author"),
					"author_homepage" => git2wp_get_plugin_header($response_index, "AuthorURI"),
					"requires" => null,
					"tested" => null,
					"upgrade_notice" => "Here's why you should upgrade...",
					"rating" => null,
					"num_ratings" => null,
					"downloaded" => null,
					"last_updated" => null
				);
				
				$pluginInfo = git2wp_toWpFormat($plugin);
				if ($pluginInfo){
					return $pluginInfo;
				}
			}
		}
	}
	return $result;
}
//Override requests for plugin information
add_filter('plugins_api', 'git2wp_inject_info', 20, 3);

//------------------------------------------------------------------------------
function git2wp_update_check_plugins($transient) {	
    $options = get_option('git2wp_options');
    $resource_list = $options['resource_list'];

    if ( count($resource_list) > 0 ) {
        foreach($resource_list as $resource) {
            $repo_type = git2wp_get_repo_type($resource['resource_link']);

            if ( ($repo_type == 'plugin') ) {
                $response_index = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
                $current_version = git2wp_get_plugin_version($response_index);
                if($resource['head_commit']) {
					$new_version = substr($resource['head_commit'], 0, 7); 
					$trans_new_version = $transient->response[ $response_index ]->new_version;
					
					if( isset($trans_new_version) && (strlen($trans_new_version) != 7 || strpos($trans_new_version, ".") != FALSE) )
						unset($transient->response[ $response_index ]);
					
					if ( ($current_version != '-') && ($current_version != '') && ($current_version != $new_version) && ($new_version != false) ) {
							$homepage = git2wp_get_plugin_header($plugin_file, "AuthorURI");
							//$zipball = GIT2WP_ZIPBALL_URL . '/' . wp_hash($resource['repo_name']) . '.zip';
							$zipball = home_url() . '/?zipball=' . wp_hash($resource['repo_name']);
							$plugin = array(
												'slug' => dirname( $response_index ),
												'new_version' => $new_version,
												"url" => $homepage,
												'package'    => $zipball
											);
						$transient->response[ $response_index ] = (object) $plugin;
					}
					
				}else
							unset($transient->response[ $response_index ]);
            }
        }
    }
    return $transient;
}
add_filter("pre_set_site_transient_update_plugins","git2wp_update_check_plugins", 10, 1); 

//------------------------------------------------------------------------------
function git2wp_ajax_callback() {
	$options = get_option('git2wp_options');
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array('success'=> false, 'error_messge'=>'', 'success_message'=>'');

	if( isset($_POST['git2wp_action']) and $_POST['git2wp_action'] == 'set_branch' ) {
		if( isset($_POST['id']) and isset($_POST['branch'])) {	
			$resource = &$resource_list[$_POST['id']];
			
			$git = new Git2WP( array(
				"user" => $resource['username'],
				"repo" => $resource['repo_name'],
				"access_token" => $default['access_token'],
				"source" => $resource['repo_branch'] 
			));
			
			$branches = $git->fetch_branches();
			$branch_set = false;

			if(count($branches) > 0) {
				foreach($branches as $br)
					if($br == $_POST['branch']) {
						$resource['repo_branch'] = $br;
						
						$sw = git2wp_update_options('git2wp_options', $options);
						
						if($sw) {
							$branch_set = true;
							$response['success'] = true;
							break;
						}
					}
			}
			
			if(!$branch_set)
				$response['error_messages'] = 'Branch not set';  
		
			header("Content-type: application/json");
			echo json_encode($response);
			die();
		}
	}
	
	if( isset($_POST['git2wp_action']) and  $_POST['git2wp_action'] == 'downgrade' ) {
		if( isset($_POST['commit_id']) and isset($_POST['res_id'])) {
			
			$resource = $resource_list[$_POST['res_id']];
			$version = $_POST['commit_id'];
			
			if($_POST['res_id'] != 0)
				$git = new Git2WP( array(
					"user" => $resource['username'],
					"repo" => $resource['repo_name'],
					"access_token" => $default['access_token'],
					"source" => $version
				));
			else
				$git = new Git2WP( array(
					"user" => $resource['username'],
					"repo" => $resource['repo_name'],
					"access_token" => $default['token_alt'],
					"source" => $version
				));
			
			$version = substr($version, 0, 7);
			
			$type = git2wp_get_repo_type($resource['resource_link']);
			$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
			
			$sw = $git->store_git_archive();
			
			if($sw) {
				
				if ( $type == 'plugin' ) 
					if( file_exists(ABSPATH . 'wp-content/plugins/' . $resource['repo_name']) ) 
						git2wp_uploadPlguinFile($zipball_path, 'update');
					else
						git2wp_uploadPlguinFile($zipball_path);
				else
					if( file_exists(ABSPATH . 'wp-content/themes/' . $resource['repo_name']) )
						git2wp_uploadThemeFile($zipball_path, 'update');
					else
						git2wp_uploadThemeFile($zipball_path);
				
				if ( file_exists($zipball_path) ) unlink($zipball_path);
				
				$response['success'] = true;
				$response['success_message'] = "The resource <b>{$resource['repo_name']}<b> has been updated to $version .";
			}else
				$response['error_message'] = "The resource <b>{$resource['repo_name']}<b> has FAILED to updated to $version .";
			
			header("Content-type: application/json");
			echo json_encode($response);
			die();
		}
	}
	
	if( isset($_POST['git2wp_action']) and $_POST['git2wp_action'] == 'fetch_history' ) {
		if(isset($_POST['res_id'])) {
			header("Content-Type: text/html");
			
			$resource = $resource_list[$_POST['res_id']];
			
			$git = new Git2WP( array(
				"user" => $resource['username'],
				"repo" => $resource['repo_name'],
				"access_token" => $default['access_token'],
				"source" => $resource['repo_branch']
			));
			
			$commit_history = $git->get_commits();
			
			git2wp_render_resource_history($resource['repo_name'], $_POST['res_id'], $commit_history);
			
			die();
		}
	}
}
add_action('wp_ajax_git2wp_ajax', 'git2wp_ajax_callback');

//-----------------------------------------------------------------------------
function git2wp_update_options($where,$data) {
	$data_array = array('option_value' => serialize($data) );
	$where_array = array('option_name' => $where);
	global $wpdb;
	$sw = $wpdb->update( $wpdb->prefix . 'options', $data_array, $where_array );
	
	return $sw;
}

//------------------------------------------------------------------------------
function git2wp_add_javascript($hook) {
	if('tools_page_github2wp/github2wp' != $hook) {
		return;
	}
	
	$script_file_name_url = plugins_url('git2wp.js', __FILE__);
	$script_file_name_path = plugin_dir_path(__FILE__) . 'git2wp.js';
	wp_enqueue_script('git2wp_js', $script_file_name_url, array('jquery'), filemtime($script_file_name_path) ); 
} 
add_action('admin_enqueue_scripts','git2wp_add_javascript'); 

//------------------------------------------------------------------------------
function git2wp_add_style($hook) {
	if('tools_page_github2wp/github2wp' != $hook) {
		return;
	}
	
	$style_file_name_url = plugins_url('git2wp.css', __FILE__);
	$style_file_name_path = plugin_dir_path(__FILE__) . 'git2wp.css';
	wp_enqueue_style('git2wp_css', $style_file_name_url, null, filemtime($style_file_name_path) );
}
add_action('admin_enqueue_scripts','git2wp_add_style'); 

//------------------------------------------------------------------------------
function git2wp_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	$nav_bar_tabs = array('resources', 'settings', 'history');
	isset($_GET['tab']) ? $tab = $_GET['tab'] : $tab = 'resources';
	
	if( ! in_array($tab, $nav_bar_tabs))
		$tab = 'resources';
?>

<div class="wrap">
	<div id="icon-plugins" class="icon32">&nbsp;
	</div>
	
	<h2 class="nav-tab-wrapper">
		<a class="nav-tab<?php if($tab=='resources')
		echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=resources'); ?>">Resources</a>
		<a class="nav-tab<?php if($tab=='history')
		echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=history'); ?>">History</a>
		<a class="nav-tab<?php if($tab=='settings')
		echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=settings'); ?>">Settings</a>
	</h2>	

	<?php if ( $tab == 'resources' ) {
	    git2wp_head_commit_cron();
		$options = get_option('git2wp_options');
	?>
	
	<form action="options.php" method="post">
		<?php 
				$disable_resource_fields = '';
				if ( git2wp_needs_configuration() )
					$disable_resource_fields = 'disabled="disabled" ';

				settings_fields('git2wp_options');
				do_settings_sections('git2wp');				
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label>Resource Type:</label>
					</th>
					<td>
						<label for="resource_type_dropdown">
							<select name='resource_type_dropdown' <?php echo $disable_resource_fields; ?>id='resource_type_dropdown'>
								<option value='plugins'>Plugin</option>
								<option value='themes'>Theme</option>
							</select>
						</label>
						<p class="description">Is it a <strong>plugin</strong> or a <strong>theme</strong>?</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>GitHub clone url:</label>
					</th>
					<td>
						<label for="resource_link">
							<input name="resource_link" id="resource_link" value="" <?php echo $disable_resource_fields; ?>type="text" size='30'>
						</label>
						<p class="description">Github repo link.</p>
					</tr>
				<tr valign="top">
					<th scope="row">
						<label>Synching Branch:</label>
					</th>
					<td>
						<label for="master_branch">
							<input name="master_branch" id="master_branch" value="" <?php echo $disable_resource_fields; ?>type="text" size='30'>
						</label>
						<p class="description">This will override your account preference only for this resource.</p>
						<p class="description">Optional: This will set the branch that will dictate whether or not to synch.</p>
					</td>
				</tr>
				
				<tr valign="top">
					<td>
					</td>
				</tr>
			</tbody>
		</table>
		<input name="submit_resource" <?php echo $disable_resource_fields; ?>type="submit" class="button button-primary" value="<?php esc_attr_e('Add Resource'); ?>" />
		
		<br /><br /><br />
		<?php 
				do_settings_sections('git2wp_list');
				git2wp_setting_resources_list();
		?>
	</form>
	<?php } ?>

	
	
	<?php 
	if ( $tab == 'settings' ) {
	    git2wp_token_cron();
		$options = get_option("git2wp_options");
		$default = &$options['default'];
		
		if($default['app_reset'])
			if(git2wp_needs_configuration())
				echo "<div class='updated'><p>You've reset/deleted you're GitHub application settings reconfigure them here.</p></div>";
	?>
	
	<form action="options.php" method="post">
		
		<?php 
		settings_fields('git2wp_options');
		do_settings_sections('git2wp_settings');
		?>
		
		<a class="button-primary clicker" alt="#" >Need help?</a>		
		<div class="slider home-border-center" id="#">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label>Follow this link and <br />
											 fill as shown here:</label>
							</th>
							<td>
								<label><a href="https://github.com/settings/applications/new" target="_blank">Create a new git application</a></label>
								<p class="description"><strong>Application Name</strong> -> git2wp</p>
								<p class="description"><strong>Main URL </strong>-> <?php echo home_url();?></p>
								<p class="description"><strong>Callback URL</strong> -> <?php echo home_url() . '/?git2wp_auth=true';?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label>Go here and select the <br />
											 newly created application</label>
							</th>
							<td>
								<label><a href="https://github.com/settings/applications" target="_blank">Application list</a></label>
								<p class="description"><strong>Here you have all the information that you need to fill in the form.</strong></p>
							</td>
						</tr>
					</tbody>
				</table>
				<br /><br /><br />
		</div>
				
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label>Github master branch override:</label>
					</th>
					<td>
						<label for="master_branch">
							<input name='master_branch' id='master_branch'  type="text" size='40' value='<?php echo $default["master_branch"]  ? $default["master_branch"] : "master";
?>'>
						</label>
						<p class="description">In case you don't  want to synch your master branch, change this setting here.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>Github client id:</label>
					</th>
					<td>
						<label for="client_id">
							<input name='client_id' id='client_id'  type="text" size='40' value='<?php echo $default["client_id"]  ? $default["client_id"] : "";
?>'>
						</label>
						<p class="description">The git application client id, created for this plugin.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>Github client secret:</label>
					</th>
					<td>
						<label for="client_secret">
							<input name='client_secret' id='client_secret'  type="text" size='40' value='<?php echo $default["client_secret"]  ? $default["client_secret"] : "";
?>'>
						</label>
						<p class="description">The git application client secret, created for this plugin.</p>
						<p class="description">Notice: These two should be valid because they are used to authentificate us on behalf of yourself. </p>
					</td>
				</tr>
<?php	if($default['changed']) 
			echo "<tr valign='top' class='plugin-update-tr'>"
			. "<th scope='row'>"
			. "<label>Generate Token:</label>"
			. "</th>"
			. "<td>"
			. "<a onclick='setTimeout(function(){location.reload(true);}, 60*1000)' target='_blank' style='text-decoration: none; color: red; font-weight: bold;' href='https://github.com/login/oauth/authorize" 
			. "?client_id=" . $default['client_id']
			. "&client_secret" . $default['client_secret']
			. "&scope=repo'>" . "Generate!"
			. "</a>" 
			. "</td>"
			. "</tr>";
		else if($default['access_token'])
			echo  "<tr valign='top'>"
			. "<th scope='row'>"
			. "<label>GitHub Link Status: </lablel>"
			. "</th>"
			. "<td>"
			. "<span style='color: green'><strong>"
			. "OK"
			. "</strong></span>"
			. "</td>"
			. "</tr>";
				?>
			</tbody>
		</table>
		
		<input name="submit_settings" type="submit" class="button button-primary" value="<?php esc_attr_e('Save changes'); ?>" />
		<!--input name="submit_test" type="submit" class="button button-primary" value="<?php esc_attr_e('GET'); ?>" /-->
	</form>
	<?php } ?>
	
	<?php if ( $tab == 'history' ) { ?>
	<div id="git2wp_history_messages"></div>
	
	<form action="options.php" method="post">
		<?php
				settings_fields('git2wp_options');
				do_settings_sections('git2wp_history');
				$options = get_option('git2wp_options');
				$resource_list = $options['resource_list'];
		?>
		
		<?php
			$plugin_render = '';
			$theme_render = '';
			if(is_array($resource_list) && !empty($resource_list))
				foreach($resource_list as $key => $resource) {
					$type = git2wp_get_repo_type($resource['resource_link']);
					
					if($type == 'plugin') 
						$plugin_render .= "<tr valign='top'>
													<th scope='row'>
														<label><strong>{$resource['repo_name']}</strong></label>
													</th>
													<td>
														<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>Expand</center></span>
															<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
															</div>
														</span>
													</td>
												</tr>";
						
					if($type == 'theme') 
						$theme_render .= "<tr valign='top'>
													<th scope='row'>
														<label><strong>{$resource['repo_name']}</strong></label>
													</th>
													<td>
														<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>Expand</center></span>
															<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
															</div>
														</span>
													</td>
												</tr>";
				}
		?>
		<table class="form-table" >
			<tbody>
				<tr><th colspan='2'><h2>Plugins</h2><br /></th></tr> 
				<?php echo $plugin_render; ?>
			</tbody>
		</table>
		<br /><br /><br />
		<table class="form-table">
			<tbody>
				<tr><th colspan='2'><h2>Themes</h2><br /></th></tr> 
				<?php echo $theme_render; ?>
			</tbody>
		</table>
	</form>
	<?php } ?>
	
</div><!-- .wrap -->

<?php
}
//------------------------------------------------------------------------------
function git2wp_needs_configuration() {
	$options = get_option('git2wp_options');
	$default = $options['default'];

	return (empty($default['master_branch']) || empty($default['client_id']) 
		|| empty($default['client_secret']) || empty($default['access_token']));
}

//------------------------------------------------------------------------------
function git2wp_admin_init() {
	register_setting( 'git2wp_options', 'git2wp_options', 'git2wp_options_validate' );	
	//
	// Resources tab
	//
	add_settings_section('git2wp_main_section', 'Git to WordPress - Resources',
						 'git2wp_main_section_description', 'git2wp');
	add_settings_section('git2wp_resource_display_section', 'Your current Git resources', 
						 'git2wp_resource_display_section_description', 'git2wp_list');
	//
	// Settings tab
	//
	add_settings_section('git2wp_second_section', 'Git to WordPress - Settings', 
						 'git2wp_second_section_description', 'git2wp_settings');
	//
	// History tab
	//
	add_settings_section('git2wp_main_history_section', 'Git to WordPress - History', 
						 'git2wp_main_history_section_description', 'git2wp_history');
	//
	// Add Settings notice
	//
	$plugin_page = plugin_basename(__FILE__);
	$plugin_link = git2wp_return_settings_link('&tab=settings');

	$options = get_option('git2wp_options');
	$default = $options['default'];

	if ( git2wp_needs_configuration() )
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>"
			.sprintf(__('Git2WP needs configuration information on its <a href="%s">'.__('Settings').'</a> page.', $plugin_page), 
  					 $plugin_link)."</p></div>';" ) );
}
add_action('admin_init', 'git2wp_admin_init');

//------------------------------------------------------------------------------
function git2wp_second_section_description() {
	echo '<p>Enter here the default settings for the Github connexion.</p>';
}

//------------------------------------------------------------------------------
function git2wp_main_history_section_description() {
	echo '<p>You can revert to an older version of a resource at any time.</p>';
}

//------------------------------------------------------------------------------
function git2wp_resource_display_section_description() {
	echo '<p>Here you can manage your Github resources.</p>';
}

//------------------------------------------------------------------------------
function git2wp_main_section_description() {
	echo '<p>Enter here the required data to set up a new Git endpoint.</p>';
}

//------------------------------------------------------------------------------
function git2wp_str_between( $start, $end, $content ) {
	$r = explode($start, $content);
	
	if ( isset($r[1]) ) {
		$r = explode($end, $r[1]);
		return $r[0];
	}
	return '';
}

//------------------------------------------------------------------------------
function git2wp_get_repo_name_from_hash( $hash ) {
	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];
	foreach( $resource_list as $res ) {
		$repo_name = $res['repo_name'];
		if ( ($repo_name == $hash) || (wp_hash($repo_name) == $hash) )
			return $repo_name;
	}
	return $repo_name;
}

//------------------------------------------------------------------------------
function git2wp_pluginFile_hashed( $hash ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	require_once( ABSPATH . '/wp-includes/pluggable.php' );
	$allPlugins = get_plugins();
	foreach($allPlugins as $plugin_index => $plugin_value) {
		$pluginFile = $plugin_index;
		$repo_name = substr(basename($plugin_index), 0, -4);
		if ( ($repo_name == $hash) || ($pluginFile == $hash) || (wp_hash($repo_name) == $hash) )
			return $pluginFile;
	}
	return $hash;
}

//------------------------------------------------------------------------------
//
// Get the header of the plugin.
//
function git2wp_get_plugin_header($pluginFile, $header = 'Version') {
	$pluginFile = git2wp_pluginFile_hashed($pluginFile);

	if ( !function_exists('get_plugins') ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	$allPlugins = get_plugins();
	
	if ( $header == 'ALL' )
		return serialize($allPlugins[$pluginFile]);
	
	if ( array_key_exists($pluginFile, $allPlugins) && array_key_exists($header, $allPlugins[$pluginFile]) ){
		return $allPlugins[$pluginFile][$header];
	}
	return "-";
}

//------------------------------------------------------------------------------
//
// Get the version of the plugin.
//
function git2wp_get_plugin_version($pluginFile) {
	return git2wp_get_plugin_header($pluginFile);
}

//------------------------------------------------------------------------------
//
// Get the version of the theme.
//
function git2wp_get_theme_header($theme_name, $header = 'Version') {
	if ( function_exists('wp_get_theme') ) {
		$theme = wp_get_theme($theme_name);

		if ( $header == 'ALL' )
			return serialize($theme);

		return $theme->get($header);
	}
	return "-";
}

//------------------------------------------------------------------------------
//
// Get the version of the theme.
//
function git2wp_get_theme_version($theme_name) {
	return git2wp_get_theme_header($theme_name);
}

//------------------------------------------------------------------------------
//
// Returns the repo type: 'plugin' or 'theme'
//
function git2wp_get_repo_type($resource_link) {
	return git2wp_str_between("wp-content/", "s/", $resource_link);
}

//------------------------------------------------------------------------------
function git2wp_rmdir($dir) {
	if ( ! file_exists($dir) ) return true;
	
	if ( ! is_dir($dir) || is_link($dir) ) return unlink($dir);

	foreach ( scandir($dir) as $item ) {
		if ($item == '.' || $item == '..') continue;

		if ( ! git2wp_rmdir($dir . "/" . $item) ) {
			chmod($dir . "/" . $item, 0777);
			if ( ! git2wp_rmdir($dir . "/" . $item) ) return false;
		}
	}
	return rmdir($dir);
}

//------------------------------------------------------------------------------
function git2wp_uploadThemeFile($path, $mode = 'install') {
	//set destination dir
	$destDir = ABSPATH.'wp-content/themes/';
	
	//set new file name
	$ftw = $destDir . basename($path);
	$ftr = $path;	
	
	$theme_dirname = str_replace('.zip', '', basename($path));
	$theme_dirname = $destDir . git2wp_get_repo_name_from_hash($theme_dirname) . '/';
	
	if ( $mode == 'update' ) // remove old files
		git2wp_rmdir($theme_dirname);

	$file = new Git2WpFile($ftr, $ftw);
	
	if($file->checkFtr()):
		$file->writeToFile();
		git2wp_installTheme($file->pathFtw());
	endif;
}

//------------------------------------------------------------------------------
function git2wp_uploadPlguinFile($path, $mode = 'install') {
	//set destination dir
	$destDir = ABSPATH.'wp-content/plugins/';
	
	//set new file name
	$ftw = $destDir . basename($path);
	$ftr = $path;

	$plugin_dirname = str_replace('.zip', '', basename($path));
	$plugin_dirname = $destDir . git2wp_get_repo_name_from_hash($plugin_dirname) . '/';
	if ( $mode == 'update' ) // remove old files
		git2wp_rmdir($plugin_dirname);
	
	$file = new Git2WpFile($ftr, $ftw);
	
	if($file->checkFtr()):
		$file->writeToFile();
		git2wp_installPlugin($file->pathFtw());
	endif;
	
}


//------------------------------------------------------------------------------
function git2wp_installTheme($file) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	
	$upgrader = new Theme_Upgrader( new Theme_Installer_Skin( compact('type', 'title', 'nonce') ) );

	require_once(ABSPATH . 'wp-admin/admin-header.php');
	$result = $upgrader->install( $file );
	require_once(ABSPATH . 'wp-admin/admin-footer.php');
	
	if ( $result )
		git2wp_cleanup($file);
}

//------------------------------------------------------------------------------
function git2wp_installPlugin($file) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	
	$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact('type', 'title', 'nonce') ) );
	
	require_once(ABSPATH . 'wp-admin/admin-header.php');	
	$result = $upgrader->install( $file );
	require_once(ABSPATH . 'wp-admin/admin-footer.php');
	
	if ( $result ) 
		git2wp_cleanup($file);
} 

//------------------------------------------------------------------------------
function git2wp_cleanup($file) {
	if ( file_exists($file) ):
		return unlink( $file );
	endif;
}

//------------------------------------------------------------------------------
function git2wp_setting_resources_list() {
	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];

	if ( is_array($resource_list)  and  !empty($resource_list)) {
?>  
<br />
<table id="the-list" class="wp-list-table widefat plugins" cellpadding='5' border='1' cellspacing='0' >
	<thead>
		<tr><th></th><th>Resource</th><th>Options</th></tr>
	</thead>
	<tbody>
<?php 

		$new_transient = array();
		$transient = get_transient('git2wp_branches');
		$default = $options['default'];

		if(is_array($resource_list) && !empty($resource_list))		
			foreach($resource_list as $index => $resource) {
				$k++;
				
				$git = new Git2WP(array(
					"user" => $resource['username'],
					"repo" => $resource['repo_name'],
					"access_token" => $default['access_token'],
					"source" => $resource['repo_branch'] 
				));
				
										
				if(false === $transient){
					$branches = $git->fetch_branches();
					$new_transient[] = array('repo_name' => $resource['repo_name'],
							'branches' => $branches);
				}else
					foreach($transient as $tran_res)
						if($tran_res['repo_name'] == $resource['repo_name']){
							$branches = $tran_res['branches'];
							break;
						}
	
				$repo_type = git2wp_get_repo_type($resource['resource_link']);
				
				$alternate = '';
				$my_data = "";
				
				if ( ($k % 2) == 0 )
					$alternate = ' class="inactive"';
				
				$resource_path = str_replace( home_url(), ABSPATH, $resource['resource_link'] );
				$dir_exists = is_dir($resource_path);
				
				$action = git2wp_return_resource_dismiss($resource, $k-1); // CHANGE HERE AFTER UPDATE INSTALL DELETE USES INDEX
				
				if ( ! $dir_exists ) {
					$my_data .= "<p><strong>The resource does not exist on your site!</strong></p>";
					$action .= git2wp_return_resource_install($resource, $k-1); // CHANGE HERE AFTER UPDATE INSTALL DELETE USES INDEX
				}
				
				if ( $repo_type == 'plugin' ) {
					$new_version = false;
					
					$plugin_file = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
					$current_plugin_version = git2wp_get_plugin_version($plugin_file);
					
					if ($current_plugin_version > '-' && $current_plugin_version > '') {
						$my_data .= "<strong>" . git2wp_get_plugin_header($plugin_file, "Name") . "</strong>&nbsp;(";
					
						if($resource['is_on_wp_svn'])
							$my_data .= "<div class='notification-warning' title='Wordpress has a resource with the same name.\nWe will override its update notifications! ' ></div>";
							
						$author = git2wp_get_plugin_header($plugin_file, "Author");
						$author_uri = git2wp_get_plugin_header($plugin_file, "AuthorURI");
						$plugin_description = git2wp_get_plugin_header($plugin_file, "Description");
						
						if ( $author_uri != '-' && $author_uri != '' )
							$author = '<a href="' . $author_uri . '" target="_blank">' . $author . '</a>';
						
						$my_data .= "Version " . $current_plugin_version . "&nbsp;|&nbsp;";
						$my_data .= "By " . $author . ")&nbsp;";
						$my_data .= '<a id="need_help_'.$k.'" class="clicker" alt="res_details_'.$k.'"><strong>Details</strong></a><br />';
						$my_data .= "<div id='res_details_".$k."' class='slider home-border-center'>";
	
						if ( ($plugin_description != '') && ($plugin_description != '-') )
							$my_data .= $plugin_description . "<br />";
							
						$new_version = substr($resource['head_commit'], 0, 7); 
					}
					
					if ( ($new_version != $current_plugin_version) && ('-' != $current_plugin_version) && ('' != $current_plugin_version)  && ($new_version != false) ) {
						$my_data .= "<strong>New Version: </strong>" . $new_version . "<br /></div>";
						$action .= git2wp_return_resource_update($resource, $k-1); // CHANGE HERE AFTER UPDATE INSTALL DELETE USES INDEX
					}
				}
				else
					if ( $repo_type == 'theme' ) {							
						$new_version = false;
							
						$theme_dirname = $resource['repo_name'];
						$current_theme_version = git2wp_get_theme_version($theme_dirname);
						
						if ($current_theme_version > '-' && $current_theme_version > '') {
							$my_data .= "<strong>" . git2wp_get_theme_header($theme_dirname, "Name") . "</strong>&nbsp;(";
							
							if($resource['is_on_wp_svn'])
								$my_data .= "<div class='notification-warning' title='Wordpress has a resource with the same name.\nWe will override its update notifications! ' ></div>";
							
							$author = git2wp_get_theme_header($theme_file, "Author");
							$author_uri = git2wp_get_theme_header($theme_file, "AuthorURI");
							$theme_description = git2wp_get_theme_header($theme_dirname, "Description");
							
							if ( $author_uri != '-' && $author_uri != '' )
								$author = '<a href="' . $author_uri . '" target="_blank">' . $author . '</a>';
														
							$my_data .= "Version " . $current_theme_version . "&nbsp;|&nbsp;";
							$my_data .= "By " . $author . ")&nbsp;";
							$my_data .= '<a id="need_help_'.$k.'" class="clicker" alt="res_details_'.$k.'"><strong>Details</strong></a><br />';
							$my_data .= "<div id='res_details_".$k."' class='slider home-border-center'>";
							
							if ( ($theme_description != '') && ($theme_description != '-') )
								$my_data .= $theme_description . "<br />";
			
							$new_version = substr($resource['head_commit'], 0, 7);
						}
						
						if ( ($new_version != $current_theme_version) && ($new_version != false) && ($current_theme_version != '-') && ($current_theme_version != '') ) {
							$my_data .= "<strong>New Version: </strong>" . $new_version . "<br /></div>";
							$action .= git2wp_return_resource_update($resource, $k-1); // CHANGE HERE AFTER UPDATE INSTALL DELETE USES INDEX
						}
					}
					
				echo "<tr".$alternate.">"
							."<td>".$k."</td>"
							."<td>".$my_data
								."<br />"
								.git2wp_return_resource_git_link($resource)
								."<br />"
								.git2wp_return_wordpress_resource($repo_type, $resource['repo_name'])
								."<br />"
								.git2wp_return_branch_dropdown($index, $branches)
							."</td>"
							."<td>".$action."</td>"
						."</tr>";
			}
		
		if($transient === false)
			set_transient('git2wp_branches', $new_transient, 5*60);
		
		?></tbody></table>
<?php
	}
}

//------------------------------------------------------------------------------
function git2wp_options_validate($input) {
	$options = get_option('git2wp_options');
	
	if( isset($_POST['submit_resource']) && !git2wp_needs_configuration() ) {
	    $initial_options = $options;
		$resource_list = &$options['resource_list'];
		
		$repo_link = $_POST['resource_link'];
		$repo_branch = $_POST['master_branch'];
		
		if($repo_branch == '')
			$repo_branch = $options['default']['master_branch'];
		
		if ($repo_link != '') {
			$repo_link = trim($repo_link);
			
			$data = Git2WP::get_data_from_git_clone_link($repo_link);

			if(isset($data['user']) && isset($data['repo'])) {
				$resource_owner = $data['user'];
				$resource_repo_name = $data['repo'];
				
				$text_resource = "/" . $resource_repo_name;
				
				$text_resource = "/" . $_POST['resource_type_dropdown'] . $text_resource;
				$link = home_url() . "/wp-content" . $text_resource;
				
				$unique = true;
				
				if(is_array($resource_list) && !empty($resource_list))
					foreach($resource_list as $resource) {
						if($resource['repo_name'] === $resource_repo_name) {
							$unique = false;
							break;
						}
				}
				
				if($unique) {
					$default = $options['default'];
					
					$args = array(
						"user" => $resource_owner,
						"repo" => $resource_repo_name,
						"access_token" => $default['access_token'],
						"source" => $repo_branch 
					);

					$git = new Git2WP($args);
					
					$sw = $git->check_repo_availability();
					
					if ($sw) {
						$on_wp = Git2WP::check_svn_avail($resource_repo_name, substr($_POST['resource_type_dropdown'], 0, -1));
						$head = $git->get_head_commit();
						
						$resource_list[] = array(
												'resource_link' => $link,
												'repo_name' => $resource_repo_name,
												'repo_branch' => $repo_branch,
												'username' => $resource_owner,
												'is_on_wp_svn' => $on_wp,
												'head_commit' => $head
											);
						
						add_settings_error( 'git2wp_settings_errors', 'repo_connected', "Connection was established.", "updated" );
						delete_transient('git2wp_branches');
					}else
						return $initial_options;	
				} else {
					add_settings_error( 'git2wp_settings_errors', 'duplicate_endpoint', 
									   "Duplicate resources! Repositories can't be both themes and plugins ", 
									   "error" );
					return $initial_options;
				}
			}
			else {
				add_settings_error( 'git2wp_settings_errors', 'not_git_link', 
								   "This isn't a git link! eg: https://github.com/dragospl/pressignio.git", 
								   "error" );
				return $initial_options;
			}
		}
	}
	
	// install resources
	$resource_list = &$options['resource_list'];
	$k = 0;
	
	if(is_array($resource_list) && !empty($resource_list))
		foreach($resource_list as $key => $resource)
			if ( isset($_POST['submit_install_resource_'.$k++]) ) {
				$repo_type = git2wp_get_repo_type($resource['resource_link']);
				$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
	
				$default = $options['default'];
				$git = new Git2WP(array(
					"user" => $resource['username'],
					"repo" => $resource['repo_name'],
					"repo_type" => $repo_type,
					"access_token" => $default['access_token'],
					"source" => $resource['repo_branch']
				));
				$sw = $git->store_git_archive();
				
				if($sw) {
					if ( $repo_type == 'plugin' )
						git2wp_uploadPlguinFile($zipball_path);
					else
						git2wp_uploadThemeFile($zipball_path);
					if ( file_exists($zipball_path) ) unlink($zipball_path);
				}
			}

	// update resources
	$resource_list = &$options['resource_list'];
	$k = 0;
	if(is_array($resource_list) && !empty($resource_list))
		foreach($resource_list as $key => $resource)
			if ( isset($_POST['submit_update_resource_'.$k++]) ) {
				$repo_type = git2wp_get_repo_type($resource['resource_link']);
				$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
				$default = $options['default'];
				
				if($key != 0)
					$git = new Git2WP(array(
						"user" => $resource['username'],
						"repo" => $resource['repo_name'],
						"repo_type" => $repo_type,
						"access_token" => $default['access_token'],
						"source" => $resource['head_commit']
					));
				else
					$git = new Git2WP(array(
						"user" => $resource['username'],
						"repo" => $resource['repo_name'],
						"repo_type" => $repo_type,
						"access_token" => $default['token_alt'],
						"source" => $resource['head_commit']
					));
				$sw = $git->store_git_archive();
				
				if($sw) {
					if ( $repo_type == 'plugin' )
						git2wp_uploadPlguinFile($zipball_path, 'update');
					else
						git2wp_uploadThemeFile($zipball_path, 'update');
	
					if ( file_exists($zipball_path) ) unlink($zipball_path);
				}
			}

	// delete resources
	$resource_list = &$options['resource_list'];
	$k = 0;
	
	if(is_array($resource_list) && !empty($resource_list))
		foreach($resource_list as $key => $resource)
			if ( isset($_POST['submit_delete_resource_'.$k++]) )
				if($key != 0)
					unset($resource_list[$key]);
		
	// settings
	if(isset($_POST['submit_settings'])) {
		$default = &$options['default'];
		
		$client_id = $default['client_id'];
		$client_secret = $default['client_secret'];
		
		if(isset($_POST['master_branch'])) {
			if($_POST['master_branch'])
				$master_branch = trim($_POST['master_branch']);
			else
				$master_branch = 'master';
		}
		
		if(isset($_POST['client_id']))
			if($_POST['client_id'] != $default['client_id']) {
				$client_id = trim($_POST['client_id']);
				$changed = 1;
			}
		
		if(isset($_POST['client_secret']))
			if($_POST['client_secret'] != $default['client_secret']) {
				$client_secret = trim($_POST['client_secret']);
				$changed = 1;
			}
		
		if($master_branch and $client_id and $client_secret)
			$default['app_reset']=0;
		
		$default["master_branch"] = $master_branch;
		$default["client_id"] = $client_id;
		$default["client_secret"] = $client_secret;
		
		if($changed) {
			$default["access_token"] = NULL;
			$default["changed"] =  $changed;
		}
	}
	
	return $options;
}

//------------------------------------------------------------------------------
function git2wp_init() {
	$options = get_option('git2wp_options');
	$resource_list = &$options['resource_list'];
	
	$default = &$options['default'];

	// get token from GitHub
	if ( isset($_GET['code']) and  isset($_GET['git2wp_auth']) and $_GET['git2wp_auth'] == 'true' ) {
		
		$code = $_GET['code'];
		$options = get_option("git2wp_options");
		$default = &$options['default'];
		
		$client_id = $default['client_id'];
		$client_secret = $default['client_secret'];
		$data = array("code"=>$code, "client_id"=>$client_id, "client_secret"=>$client_secret);
		
		$response = wp_remote_post( "https://github.com/login/oauth/access_token", array("body" =>$data));
		
		parse_str($response['body'], $parsed_response_body);
		
		if($parsed_response_body['access_token'] != NULL) {
			$default['access_token'] = $parsed_response_body['access_token'];
			$default['changed'] = 0;
			update_option("git2wp_options", $options);
		}
	}

	if ( isset( $_GET['zipball'] ) )
		git2wp_install_from_wp_hash($_GET['zipball']);
}
add_action('init', 'git2wp_init');

function git2wp_install_from_wp_hash($hash) {
	$options = get_option("git2wp_options");
	$default = &$options['default'];

	$resource = null;
	$resource_list = $options['resource_list'];
	if(is_array($resource_list) && !empty($resource_list))
		foreach( $resource_list as $resource_index => $resource_value )
			if ( wp_hash($resource_value['repo_name']) == $hash ) {
				$resource = $resource_value;
				break;
			}

	if ( $resource != null ) {
		$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
		$default = $options['default'];
		
		if($resource_index != 0)
			$git = new Git2WP(array(
				"user" => $resource['username'],
				"repo" => $resource['repo_name'],
				"access_token" => $default['access_token'],
				"source" => $resource['repo_branch']
			));
		else
			$git = new Git2WP(array(
				"user" => $resource['username'],
				"repo" => $resource['repo_name'],
				"access_token" => $default['token_alt'],
				"source" => $resource['repo_branch']
			));
			
		$zip_url = $git->store_git_archive();
		
		$upload_dir = GIT2WP_ZIPBALL_DIR_PATH;
		$upload_dir_zip .= $upload_dir . wp_hash($git->config['repo']) . ".zip";
	
		if($zip_url) {			
			$filename = basename($upload_dir_zip);

			// http headers for zip downloads
			header("Pragma: GIT2WP");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: public");
			header("Content-Description: File Transfer");
			header("Content-type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"".$filename."\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($upload_dir_zip));
			ob_end_flush();
			
			@readfile($upload_dir_zip);
			unlink($upload_dir_zip);
		} else
			header('HTTP/1.0 404 Not Found');
	}
}


