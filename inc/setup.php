<?php

function gihub2wp_activate() {
	if ( ! current_user_can( 'activate_plugins' ) )
		return;
	
	$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
	check_admin_referer( "activate-plugin_{$plugin}" );
	
	if( !file_exists( GITHUB2WP_ZIPBALL_DIR_PATH ) )
		mkdir(GITHUB2WP_ZIPBALL_DIR_PATH, 0777, true);

	add_option( 'github2wp_options', array() );
	add_option( 'github2wp_reverts', array( 'themes' => array(), 'plugins' => array()) );

	wp_schedule_event( current_time( 'timestamp' ), '6h', 'github2wp_cron_hook' );
}



function github2wp_deactivate() {
	if ( ! current_user_can( 'activate_plugins' ) )
		return;

	$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
	check_admin_referer( "deactivate-plugin_{$plugin}" );

	github2wp_rmdir(GITHUB2WP_ZIPBALL_DIR_PATH);
	wp_clear_scheduled_hook( 'github2wp_cron_hook' );
}



function github2wp_uninstall() {
	if ( ! current_user_can( 'activate_plugins' ) )
		return;

	check_admin_referer( 'bulk-plugins' );

	github2wp_delete_options();
	delete_transient( 'github2wp_branches' );
}



function github2wp_delete_options() {
	delete_option( 'github2wp_options' );
	delete_option( 'github2wp_revers' );
}
