<?php
/*
 * Plugin Name: Git2WP
 * Plugin URI: http://wordpress.org/extend/plugins/git2wp/ 
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 2.1.1
 */

define('GIT2WP_MAX_COMMIT_HIST_COUNT', 5);
define('GIT2WP_ZIPBALL_DIR_PATH', ABSPATH . '/wp-content/uploads/' . basename(dirname(__FILE__)) . '/' );
define('GIT2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . basename(dirname(__FILE__)) );

require_once('Git2WP.class.php');
require_once('Git2WpFile.class.php');

//------------------------------------------------------------------------------
function git2wp_activate() {
	add_option('git2wp_options', array());
}
register_activation_hook(__FILE__,'git2wp_activate');

//------------------------------------------------------------------------------
function git2wp_deactivate() {
	//git2wp_delete_options();
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
	return admin_url('index.php?page=' . plugin_basename(__FILE__) . $query_vars);
}

//------------------------------------------------------------------------------
// Dashboard integration
function git2wp_menu() {
	add_dashboard_page('Git to WordPress Options Page', 'Git2WP', 
					   'manage_options', __FILE__, 'git2wp_options_page');
}
add_action('admin_menu', 'git2wp_menu');

//------------------------------------------------------------------------------
function git2wp_update_check_themes($transient) {
	if ( empty( $transient->checked ) )
		return $transient;

	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];

	if ( is_array($resource_list)  and  !empty($resource_list)) {
		foreach ($resource_list as $resource) {
			$git_data = $resource['git_data'];

			$repo_type = git2wp_get_repo_type($resource['resource_link']);

			if ( ($repo_type == 'theme') ) {
				$response_index = $resource['repo_name'];
				$current_version = git2wp_get_theme_version($response_index);
				$new_version = strval( strtotime($git_data['head_commit']['timestamp']) );
				if ( ($current_version > '-') && ($current_version > '') && ($current_version < $new_version) ) {
					$update_url = 'http://themes.svn.wordpress.org/responsive/1.9.3.2/readme.txt';
					$zipball = GIT2WP_ZIPBALL_URL . '/' . $resource['repo_name'].'.zip';
					$theme = array(
						'new_version' => $new_version,
						"url" => $update_url,
						'package' => $zipball
					);
					$transient->response[ $response_index ] = $theme;
				}
			}
		}
	}
	return $transient;
}
add_filter("pre_set_site_transient_update_themes","git2wp_update_check_themes", 10, 1);

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
			$git_data = $resource['git_data'];

			$repo_type = git2wp_get_repo_type($resource['resource_link']);

			if ( ($repo_type == 'plugin') ) {
				$response_index = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
				//$current_version = git2wp_get_plugin_version($response_index);
				$new_version = strval (strtotime($git_data['head_commit']['timestamp']) );
				$homepage = git2wp_get_plugin_header($plugin_file, "AuthorURI");
				$zipball = GIT2WP_ZIPBALL_URL . '/' . $resource['repo_name'].'.zip';

				$changelog_head = '';
				if ( $new_version )
					$changelog_head = $new_version . " - " 
						. date("d/m/Y (h:m)", $new_version);

				$changelog = 'No changelog found';
				if ( $git_data['payload'] )
					$changelog = "<h4>".$changelog_head."</h4>"
						. git2wp_get_commits($git_data['payload']);

				$sections = array(
					"description" => git2wp_get_plugin_header($response_index, "Description"),
					//"installation" => "(Recommended) Installation instructions.",
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
					"url" => $homepage,
					
					"name" => git2wp_get_plugin_header($response_index, "Name"),
					"version" => $new_version,
					"homepage" => $homepage,
					"sections" => $sections,
					"download_url" => $zipball,
					"author" => git2wp_get_plugin_header($response_index, "Author"),
					"author_homepage" => git2wp_get_plugin_header($response_index, "AuthorURI"),
					"requires" => "3.0",
					"tested" => "3.5.1",
					"upgrade_notice" => "Here's why you should upgrade...",
					"rating" => 100,
					"num_ratings" => 123,
					"downloaded" => 100,
					"last_updated" => date("Y-m-d h:m:i", $new_version) //"2012-10-29 11:09:00"
				);
				
				$pluginInfo = git2wp_toWpFormat($plugin);
				if ($pluginInfo){
					return $pluginInfo;
				}
				return $result;
			}
		}
	}
}
//Override requests for plugin information
add_filter('plugins_api', 'git2wp_inject_info', 20, 3);

//------------------------------------------------------------------------------
function git2wp_update_check_plugins($transient) {
	if ( empty( $transient->checked ) )
		return $transient;

	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];

	if ( is_array($resource_list)  and  !empty($resource_list)) {
		foreach($resource_list as $resource) {
			$git_data = $resource['git_data'];			
			$repo_type = git2wp_get_repo_type($resource['resource_link']);
			
			if ( ($repo_type == 'plugin') ) {
				$response_index = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
				$current_version = git2wp_get_plugin_version($response_index);
				$new_version = strval (strtotime($git_data['head_commit']['timestamp']) );
				if ( ($current_version > '-') && ($current_version > '') && ($current_version < $new_version) ) {
					$homepage = git2wp_get_plugin_header($plugin_file, "AuthorURI");
					$zipball = GIT2WP_ZIPBALL_URL . '/' . $resource['repo_name'].'.zip';
					$plugin = array(
						'slug' => dirname( $response_index ),
						'new_version' => $new_version,
						"url" => $homepage,
						'package'    => $zipball
					);
					$transient->response[ $response_index ] = (object) $plugin;
				}
			}
		}
	}
	return $transient;
}
//Insert our update info into the update array maintained by WP
add_filter("pre_set_site_transient_update_plugins","git2wp_update_check_plugins", 10, 1); //WP 3.0+
//add_filter("site_transient_update_plugins","git2wp_update_check_plugins", 10, 1); //WP 3.0+
//add_filter('transient_update_plugins', 'git2wp_update_check_plugins'); //WP 2.8+

//-----------------------------------------------------------------------------
function git2wp_add_script() { 
	wp_enqueue_script('git2wp', plugins_url('/git2wp/git2wp.js', __FILLE__)); 
}
add_action('admin_enqueue_scripts','git2wp_add_script'); 

//------------------------------------------------------------------------------
function git2wp_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	$options = get_option('git2wp_options');
	isset($_GET['tab']) ? $tab = $_GET['tab'] : $tab = 'resources';
?>

<div class="wrap">
	<div id="icon-plugins" class="icon32">&nbsp;
	</div>
	
	<h2 class="nav-tab-wrapper">
		<a class="nav-tab<?php if($tab=='resources')
		echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=resources'); ?>">Github resources</a>
		<a class="nav-tab<?php if($tab=='settings')
		echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=settings'); ?>">Github settings</a>
	</h2>	

	<?php if ( $tab == 'resources' ) { ?>
	
	<form action="options.php" method="post">
		<?php 
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
							<select name='resource_type_dropdown' id='resource_type_dropdown'>
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
							<input name="resource_link" id="resource_link" value="" type="text" size='30'>
						</label>
						<p class="description">Github repo link.</p>
					</tr>
				<tr valign="top">
					<th scope="row">
						<label>Synching Branch:</label>
					</th>
					<td>
						<label for="master_branch">
							<input name="master_branch" id="master_branch" value="" type="text" size='30'>
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
		<input name="submit_resource" type="submit" class="button button-primary" value="<?php esc_attr_e('Add Resource'); ?>" />
		
		<br /><br /><br />
		<?php 
				do_settings_sections('git2wp_list');
				git2wp_setting_resources_list();
		?>
	</form>
	<?php } ?>
	
	
	
	<?php 
	if ( $tab == 'settings' ) {
		$default = $options['default'];
		
	?>
	
	<form action="options.php" method="post">
		
		<?php 
		settings_fields('git2wp_options');
		do_settings_sections('git2wp_settings');
		?>
		
		<a class="button-primary clicker" href="#" >Need help?</a>		
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
							<input name='master_branch' id='master_branch'  type="text" size='40' value='<?php echo $default["master_branch"]  ? $default["master_branch"] : "";
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
			. "<a onclick='setTimeout(function(){location.reload(true);}, 5000)' target='_blank' style='text-decoration: none; color: red; font-weight: bold;' href='https://github.com/login/oauth/authorize" 
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
		<input name="submit_test" type="submit" class="button button-primary" value="<?php esc_attr_e('GET'); ?>" />
	</form>
	<?php } ?>
	
</div><!-- .wrap -->


<?php
}

//------------------------------------------------------------------------------
function git2wp_admin_init() {
	register_setting( 'git2wp_options', 'git2wp_options', 'git2wp_options_validate' );	
	//
	// Resources tab
	//
	add_settings_section('git2wp_main_section', 'Git to WordPress - Resource',
						 'git2wp_main_section_description', 'git2wp');
	add_settings_section('git2wp_resource_display_section', 'Your current Git resources', 
						 'git2wp_resource_display_section_description', 'git2wp_list');
	//
	// Settings tab
	//
	add_settings_section('git2wp_second_section', 'Git to WordPress - Settings', 
						 'git2wp_second_section_description', 'git2wp_settings');
	//
	// Add Settings notice
	//
	$plugin_page = plugin_basename(__FILE__);
	$plugin_link = git2wp_return_settings_link('&tab=settings');

	$options = get_option('git2wp_options');
	$default = $options['default'];

	if ( empty($default['master_branch']) || empty($default['client_id']) || empty($default['client_secret']) )
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
function git2wp_resource_display_section_description() {
	echo '<p>Here you can retreive the endpoints that need to be set in GitHub\'s repo settings->service hooks->web hook->url.</p>';
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
function git2wp_get_plugin_repo_name_from_hash( $hash ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	require_once( ABSPATH . '/wp-includes/pluggable.php' );
	$allPlugins = get_plugins();
	foreach($allPlugins as $plugin_index => $plugin_value) {
		$pluginFile = $plugin_index;
		$repo_name = substr( basename($plugin_index), 0, -4 );
		if ( ($repo_name == $hash) || ($pluginFile == $hash) || (wp_hash($repo_name) == $hash) )
			return $repo_name;
	}
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
	require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	
	//set destination dir
	$destDir = ABSPATH.'wp-content/themes/';
	
	//set new file name
	$ftw = $destDir . basename($path);
	$ftr = $path;
	
	$theme_dirname = $destDir . str_replace('.zip', '', basename($path)) . '/';
	if ( $mode == 'update' ) // remove old files
		git2wp_rmdir($theme_dirname);

	$file = new Git2WpFile($ftr, $ftw);
	
	if($file->checkFtr()):
		$file->writeToFile();
	endif;
	
	git2wp_installTheme($file->pathFtw());
}

//------------------------------------------------------------------------------
function git2wp_uploadPlguinFile($path, $mode = 'install') {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	
	//set destination dir
	$destDir = ABSPATH.'wp-content/plugins/';
	
	//set new file name
	$ftw = $destDir . basename($path);
	$ftr = $path;

	$plugin_dirname = str_replace('.zip', '', basename($path));
	$plugin_dirname = $destDir . git2wp_get_plugin_repo_name_from_hash($plugin_dirname) . '/';
	if ( $mode == 'update' ) // remove old files
		git2wp_rmdir($plugin_dirname);
	
	$file = new Git2WpFile($ftr, $ftw);
	
	if($file->checkFtr()):
		$file->writeToFile();
	endif;
	
	git2wp_installPlugin($file->pathFtw());
}


//------------------------------------------------------------------------------
function git2wp_installTheme($file) {
	$title = __('Upload Theme');
	$parent_file = 'themes.php';
	$submenu_file = 'theme-install.php';
	add_thickbox();
	wp_enqueue_script('theme-preview');
	require_once(ABSPATH . 'wp-admin/admin-header.php');
	
	$filename = str_replace('.zip', '', basename( $file ));
	$filename = git2wp_get_theme_repo_name_from_hash($filename) . '.zip';
	$title = sprintf( __('Installing Theme from file: %s'), basename( $file ) );
	$nonce = 'theme-upload';
	//path$url = add_query_arg(array('package' => $file_upload->id), 'update.php?action=upload-theme');
	$type = 'upload';
	
	$upgrader = new Theme_Upgrader( new Theme_Installer_Skin( compact('type', 'title', 'nonce') ) );
	$result = $upgrader->install( $file );
	
	if ( $result )
		git2wp_cleanup($file);
	
	include(ABSPATH . 'wp-admin/admin-footer.php');
}

//------------------------------------------------------------------------------
function git2wp_installPlugin($file) {
	$title = __('Upload Plugin');
	$parent_file = 'plugins.php';
	$submenu_file = 'plugin-install.php';
	require_once(ABSPATH . 'wp-admin/admin-header.php');
	
	$filename = str_replace('.zip', '', basename( $file ));
	$repo_name = git2wp_get_plugin_repo_name_from_hash($filename);
	$new_filename = $repo_name . '.zip';
	//error_log(time().': INSTALL >>>>> '.$repo_name.' - ' . $filename);
	error_log(time().': UPDATE >>>>> '.$repo_name.' - ' . $filename);
	$title = sprintf( __('Installing Plugin from file: %s'), $new_filename );
	$nonce = 'plugin-upload';
	//$url = add_query_arg(array('package' => $file_upload->id), 'update.php?action=upload-plugin');
	$type = 'upload'; //Install plugin type, From Web or an Upload.
	
	$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact('type', 'title', 'nonce') ) );
	$result = $upgrader->install( $file );
	
	if ( $result )
		git2wp_cleanup($file);
	
	include(ABSPATH . 'wp-admin/admin-footer.php');
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
		<tr><th></th><th>Resource</th><th>Endpoint</th><th>Options</th></tr>
	</thead>
	<tbody>
<?php 
		foreach($resource_list as $resource) {
			$k++;
			
			$endpoint = home_url() . '/' . '?git2wp=' . md5( str_replace(home_url(), '', $resource['resource_link']) );
			$repo_type = git2wp_get_repo_type($resource['resource_link']);
			
			$alternate = '';
			if ( ($k % 2) == 0 )
				$alternate = ' class="inactive"';
			
			$selected_resource_checkbox = "<input type='checkbox' name='selected_resource_".$k
				."' id='selected_resource_".$k
				."' value=''>";
			
			$github_resource_url = "https://github.com/".$resource['username']."/".$resource['repo_name'].".git";
			$github_resource = "<strong>Github:</strong> "
				."<a target='_blank' href='".$github_resource_url."'>".$github_resource_url."</a>";
			
			$resource_path = str_replace( home_url(), ABSPATH, $resource['resource_link'] );
			$dir_exists = is_dir($resource_path);
			$wordpress_resource = "<strong>WP:</strong> /wp-content/" . $repo_type . "s/" . $resource['repo_name'];
			//
			// Delete resource button
			//
			$action = '<p><input name="submit_delete_resource_'.($k-1)
				.'" type="submit" class="button" value="'.esc_attr('Delete')
				.'" onclick="return confirm(\'Do you really want to delete: '
				.$github_resource_url . '?\');"/></p>';
			
			$git_data = $resource['git_data'];
			$my_data = "";
			
			if ( ! $dir_exists ) {
				$zipball_url = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']) .'.zip';
				$my_data .= "<p><strong>The resource does not exist on WordPress!</strong></p>";
				if ( file_exists($zipball_url) ) {
					//
					// Install resource button
					//
					//$alternate = ' style="background-color:#EDC5C0;"';
					$action .= '<p><input name="submit_install_resource_'.($k-1)
						.'" type="submit" class="button button-primary" value="'
						.esc_attr('Install') . '" /></p>';
				}
			} else {
				//
				// Update resource button
				//
				$action .= '<p><input name="submit_update_resource_'.($k-1)
					.'" type="submit" class="button" value="'
					.esc_attr('Update') . '" /></p>';
			}
			
			echo "<tr".$alternate."><td>".$k."</td>"
				."<td>".$github_resource."<br />".$wordpress_resource."</td>"
				."<td>".$endpoint."</td>"
				."<td>".$action."</td></tr>";
			
			//$my_data .= $repo_type . "<br />"; // for debug
			
			if ( ($repo_type == 'plugin') ) {
				$plugin_file = $resource['repo_name'] . "/" . $resource['repo_name'] . ".php";
				if ( (git2wp_get_plugin_version($plugin_file) > '-') 
					&& (git2wp_get_plugin_version($plugin_file) > '') ) {
					$my_data .= "<strong>Name: </strong>" . git2wp_get_plugin_header($plugin_file, "Name") . "<br />";
					$my_data .= "<strong>Description: </strong>" . git2wp_get_plugin_header($plugin_file, "Description") . "<br />";

					$author = git2wp_get_plugin_header($plugin_file, "Author");
					$author_uri = git2wp_get_plugin_header($plugin_file, "AuthorURI");
					if ( $author_uri != '-' && $author_uri != '' )
						$author = '<a href="' . $author_uri . '" target="_blank">' . $author . '</a>';
					$my_data .= "<strong>Author: </strong>" . $author . "<br />";

					$my_data .= "<strong>Version: </strong>" . git2wp_get_plugin_version($plugin_file) . "<br />";
					//$zipball = home_url() . '/wp-content/uploads/' . basename(dirname(__FILE__)) . '/' . $resource['repo_name'].'.zip';
					//$my_data .= "<strong>zipball: </strong>" . $zipball . "<br />";
					
					$new_version = strtotime($resource['git_data']['head_commit']['timestamp']);
				}
				if ( $new_version > '' )
					$my_data .= "<strong>New Version: </strong>" . $new_version . "<br />";
				
				if ( ! ((git2wp_get_plugin_version($plugin_file) > '-') 
						&& (git2wp_get_plugin_version($plugin_file) > '')) )
					$alternate = ' style="background-color:#EDC5C0;"';
			}
			else if ( ($repo_type == 'theme') ) {
				$theme_dirname = $resource['repo_name'];
				$my_data .= "<strong>Name: </strong>" . git2wp_get_theme_header($theme_dirname, "Name") . "<br />";
				$my_data .= "<strong>Description: </strong>" . git2wp_get_theme_header($theme_dirname, "Description") . "<br />";

				$author = git2wp_get_theme_header($theme_file, "Author");
				$author_uri = git2wp_get_theme_header($theme_file, "AuthorURI");
				if ( $author_uri != '-' && $author_uri != '' )
					$author = '<a href="' . $author_uri . '" target="_blank">' . $author . '</a>';
				$my_data .= "<strong>Author: </strong>" . $author . "<br />";

				$my_data .= "<strong>Version: </strong>" . git2wp_get_theme_version($theme_dirname) . "<br />";
				
				$new_version = strtotime($resource['git_data']['head_commit']['timestamp']);
				if ( $new_version > '' )
					$my_data .= "<strong>New Version: </strong>" . $new_version . "<br />";
				
				if ( ! ((git2wp_get_theme_version($theme_dirname) > '-') 
						&& (git2wp_get_theme_version($theme_dirname) > '')) ) {
					$my_data = "<strong>The resource may not be a WordPress " . ucfirst($repo_type) . ".</strong>";
					//$alternate = ' style="background-color:#EDC5C0;"';
				}
			}

			echo "<tr".$alternate."><td></td><td colspan='3'><div class='update-message'>" . $my_data . "</div></td></tr>";
		}
		?></tbody></table>
<?php
	}
}

//------------------------------------------------------------------------------
function git2wp_options_validate($input) {
	$options = get_option('git2wp_options');
	$initial_options = $options;
	
	if(isset($_POST['submit_resource'])) {
		$resource_list = &$options['resource_list'];
		$git_base = 'https://github.com/';
		
		$repo_link = $_POST['resource_link'];
		$repo_branch = $_POST['master_branch'];
		
		if($repo_branch == '')
			$repo_branch = $options['default']['master_branch'];
		
		if ($repo_link != '') {
			$repo_link = trim($repo_link);

			if(strpos($repo_link, $git_base) === 0) {

				$repo_link = trim($repo_link);
				$repo_link = substr($repo_link, strlen($git_base));
				
				$resource_details = explode("/", $repo_link);
				
				$resource_owner = $resource_details[0];
				$resource_repo_name = substr($resource_details[1], 0, -4);
				
				$text_resource = "/" . $resource_repo_name;
				
				$text_resource = "/" . $_POST['resource_type_dropdown'] . $text_resource;
				$link = home_url() . "/wp-content" . $text_resource;
				
				$unique = true;
				
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

					error_log('args: ' . print_r($args,true));

					$git = new Git2WP($args);
					
					$sw = $git->store_git_archive(false);
					
					if ($sw)
						add_settings_error( 'git2wp_settings_errors', 'repo_connected', "Connection was established.", "updated" );
					else
						return $initial_options;	

					
					
					$resource_list[] = array(
												'resource_link' => $link,
												'repo_name' => $resource_repo_name,
												'repo_branch' => $repo_branch,
												'username' => $resource_owner,
												'current_version' => 0
											);
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
	foreach($resource_list as $key => $resource)
		if ( isset($_POST['submit_install_resource_'.$k++]) ) {
			$repo_type = git2wp_get_repo_type($resource['resource_link']);
			$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
			if ( $repo_type == 'plugin' )
				git2wp_uploadPlguinFile($zipball_path);
			else
				git2wp_uploadThemeFile($zipball_path);
		}

	// update resources
	$resource_list = &$options['resource_list'];
	$k = 0;
	foreach($resource_list as $key => $resource)
		if ( isset($_POST['submit_update_resource_'.$k++]) ) {
			$repo_type = git2wp_get_repo_type($resource['resource_link']);
			$zipball_path = GIT2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';
			if ( $repo_type == 'plugin' )
				git2wp_uploadPlguinFile($zipball_path, 'update');
			else
				git2wp_uploadThemeFile($zipball_path, 'update');
		}

	// delete resources
	$resource_list = &$options['resource_list'];
	$k = 0;
	foreach($resource_list as $key => $resource)
		if ( isset($_POST['submit_delete_resource_'.$k++]) ) 
			unset($resource_list[$key]);
		
		// settings
		if(isset($_POST['submit_settings'])) {
			$default = &$options['default'];
			
			$master_branch = '';
			$client_id = $default['client_id'];
			$client_secret = $default['client_secret'];
			
			if(isset($_POST['master_branch']))
				$master_branch = trim($_POST['master_branch']);
			
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
			
			$default["master_branch"] = $master_branch;
			$default["client_id"] = $client_id;
			$default["client_secret"] = $client_secret;
			
			if($changed) {
				$default["access_token"] = NULL;
				$default["changed"] =  $changed;
			}
		}
	
	// TEST ARCHIVE
	if(isset($_POST['submit_test'])) { 
		$default = $options['default'];
		
		$git = new Git2WP(array(
			"user" => "krodyrobi",
			"repo" => "pl-templates",
			"client_id" => $default['client_id'],
			"client_secret" => $default['client_secret'],
			"access_token" => $default['access_token'],
			"git_endpoint" => md5(str_replace(home_url(), "", $resource['resource_link'])),
			"source" => $default['master_branch']
		));
		
		$sw = $git->store_git_archive();
	}
	
	return $options;
}

//------------------------------------------------------------------------------
function git2wp_init() {
	$options = get_option('git2wp_options');
	$resource_list = &$options['resource_list'];
	
	$default = &$options['default'];
	
	if ( isset($_GET['git2wp']) ) {
		foreach($resource_list as &$resource) {
			if ( $_GET['git2wp'] == md5(str_replace(home_url(), "", $resource['resource_link'])) ) {
				
				if ( isset($_POST['payload']) ) {
					$git_data = &$resource['git_data'];
					
					$raw = stripslashes($_POST['payload']);
					$obj = json_decode($raw, true);
					
					if($resource['repo_branch'] == substr ($obj['ref'], strlen("refs/heads/"))){
						$git_data['head_commit'] = $obj["head_commit"];
						
						$commits = $obj['commits'];
						
						if(count($commits) > GIT2WP_MAX_COMMIT_HIST_COUNT)
							$commits = array_slice($obj['commits'], -GIT2WP_MAX_COMMIT_HIST_COUNT);
						
						foreach($commits as $key => $data)	{
							$unique = true;
							foreach($git_data['commit_history'] as $key2 => $data2)
								if($data['id'] === $data2['sha']) {
									$unique = false;
									break;
								}
								
								if($unique)
									$git_data['commit_history'][] = array('sha' => $data['id'],
																												'message' => $data['message'],
																												'timestamp' => $data['timestamp'],
																												'git_url' => $data['url']
																											 );
						}
							
						if(count($git_data['commit_history']) > GIT2WP_MAX_COMMIT_HIST_COUNT)
							$git_data['commit_history'] = array_slice($git_data['commit_history'], -GIT2WP_MAX_COMMIT_HIST_COUNT);

						
						$git_data['payload'] = $raw;
					}
				}
				break;
			}
		}
		update_option("git2wp_options", $options);
	}
	
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
}
add_action('init', 'git2wp_init');

