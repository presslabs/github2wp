<?php

class GITHUB2WP_Setup {
	static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );

		if( !file_exists( GITHUB2WP_ZIPBALL_DIR_PATH ) )
			mkdir(GITHUB2WP_ZIPBALL_DIR_PATH, 0777, true);

		add_option( 'github2wp_options', array( 'default' => array(), 'resource_list' => array() ) );
		add_option( 'github2wp_reverts', array() );

		wp_schedule_event( current_time( 'timestamp' ), '6h', 'github2wp_cron_hook' );
	}



	static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );

		github2wp_rmdir(GITHUB2WP_ZIPBALL_DIR_PATH);
		wp_clear_scheduled_hook( 'github2wp_cron_hook' );
	}



	static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		check_admin_referer( 'bulk-plugins' );

		static::github2wp_delete_options();
		delete_transient( 'github2wp_branches' );
	}



	private static function github2wp_delete_options() {
		delete_option( 'github2wp_options' );
		delete_option( 'github2wp_reverts' );
	}
}
