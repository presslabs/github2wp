<?php
/*
 * Plugin Name: github2wp
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/ 
 * Version: 1.0.0
 */

if ( is_admin() )
	require_once( 'loader.php' );


register_activation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GITHUB2WP_Setup', 'deactivate' ) ); 
register_uninstall_hook( __FILE__, array( 'GITHUB2WP_Setup', 'uninstall' ) );



add_action( 'init', 'github2wp_init' );
function github2wp_init() {
	$options = get_option( 'github2wp_options' );

	$default = &$options['default'];

	if ( isset( $_GET['code'] ) &&  isset( $_GET['github2wp_auth'] ) && 'true' == $_GET['github2wp_auth'] ) {
		if ( isset($_GET['state']) && $_GET['state'] == $default['oauth_state'] ) {

			$data = array(
				'code'          => $_GET['code'],
				'client_id'     => $default['client_id'],
				'client_secret' => $default['client_secret'],
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
}
