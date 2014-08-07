<?php


add_action( 'admin_notices', 'github2wp_admin_notices_action' );
function github2wp_admin_notices_action() {
	settings_errors( 'github2wp_settings_errors' );
}



function github2wp_notice_needs_configuration() {
	$plugin_link = github2wp_return_settings_link( '&tab=settings' );

	if ( github2wp_needs_configuration() )
		add_action( 'admin_notices',
			create_function( '',
				'echo \'<div class="error"><p>'
				. sprintf( __( 'GitHub2WP needs configuration information on its <a href="%s">Settings</a> page.', GITHUB2WP ), $plugin_link )
				. '</p></div>\';'
			)
		);
}




function github2wp_notice_git2wp_active() {
	if ( is_plugin_active( 'git2wp/git2wp.php' ) )
		add_action( 'admin_notices',
			create_function( '',
			'echo \'<div class="error"><p>'
				. __( 'Git2WP is a further refined version of this plugin and is already installed on your server deactivate GitHub2WP.', GITHUB2WP )
				. '</p></div>\';'
			)
		);
}
