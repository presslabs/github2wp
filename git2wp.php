<?php
/*
 * Plugin Name: Git2WP
 * Plugin URI: http://wordpress.org/extend/plugins/git2wp/ 
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 5df3382dfdc6853d8d160a9c0bab8bb4d4960521
 */

//------------------------------------------------------------------------------
function git2wp_activate() {
	add_option('git2wp_options', array());
}
register_activation_hook(__FILE__,'git2wp_activate');

//------------------------------------------------------------------------------
function git2wp_deactivate() {
	git2wp_delete_options();
}
register_deactivation_hook(__FILE__,'git2wp_deactivate');

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
function git2wp_return_settings_link() {
	return admin_url('plugins.php?page='.plugin_basename(__FILE__));
}

//------------------------------------------------------------------------------
// Dashboard integration (Tools)
function git2wp_menu() {
	add_plugins_page('Git to WordPress Options Page', 'Git2WP', 
		'manage_options', __FILE__, 'git2wp_options_page');
}
add_action('admin_menu', 'git2wp_menu');

//------------------------------------------------------------------------------
function git2wp_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$options = get_option('toplytics_options');
?>
<div class="wrap">
<div id="icon-plugins" class="icon32">&nbsp;</div>

<h2>Git to WordPress</h2>
Please configure your Themes/Plugins endpoints for Github connextion:
<form action="options.php" method="post">
  <p>
	<?php settings_fields('git2wp_options'); ?>
	<?php do_settings_sections('git2wp'); ?>
  </p>
	<input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
</form></div><?php
}

//------------------------------------------------------------------------------
function git2wp_admin_init(){
	register_setting( 'git2wp_options', 'git2wp_options', 'git2wp_options_validate' );

	add_settings_section('git2wp_main_section', 'Git to WordPress Endpoints', 'git2wp_main_section_description', 'git2wp');
	add_settings_field('git2wp_text_endpoint', 'Endpoint:', 'git2wp_setting_endpoint', 'git2wp', 'git2wp_main_section');
}
add_action('admin_init', 'git2wp_admin_init');

//------------------------------------------------------------------------------
function git2wp_main_section_description() {
	echo '<p>Enter here the theme endpoint for Git connection:</p>';
}

//------------------------------------------------------------------------------
function git2wp_setting_endpoint() {
	$options = get_option('git2wp_options');
	echo "http://".site_url()."/wp-content/themes/<input id='git2wp_text_endpoint' name='git2wp_options[text_endpoint]' size='40' type='text' value='{$options['text_endpoint']}' />";
}

//------------------------------------------------------------------------------
function git2wp_options_validate($input) {
	$options = get_option('git2wp_options');
	$options['text_endpoint'] = trim($input['text_endpoint']);

	if(!preg_match('/^[a-zA-Z0-9\.@-]{2,}$/i', $options['text_endpoint'])) {
		$options['text_endpoint'] = '';
	}

	return $options;
}

