<?php

//TODO find a way to limit the requires only to where they are needed

if ( is_admin() ):
	require_once( 'inc/constants.php' );
	require_once( GITHUB2WP_INC_PATH . 'helpers.php' );
	require_once( GITHUB2WP_INC_PATH . 'notices.php' );


	require_once( GITHUB2WP_INC_PATH . 'GITHUB2WP_Setup.class.php' );
	require_once( GITHUB2WP_INC_PATH . 'cron.php' );
	require_once( GITHUB2WP_INC_PATH . 'ajax.php' );

	require_once( GITHUB2WP_INC_PATH . '/admin/admin.php' );
	require_once( GITHUB2WP_INC_PATH . '/admin/ui.php' );

	//TODO rename, relocate these files, maybe split them
	require_once( 'class-github-2-wp.php' );



	function github2wp_language_init() {
		load_plugin_textdomain( GITHUB2WP, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
	}
	add_action( 'plugins_loaded', 'github2wp_language_init' );
endif;
