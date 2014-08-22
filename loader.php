<?php

require_once( 'inc/constants.php' );
require_once( GITHUB2WP_INC_PATH . 'helpers.php' );
require_once( GITHUB2WP_INC_PATH . 'notices.php' );


require_once( GITHUB2WP_INC_PATH . 'GitHub2WP_Setup.class.php' );
require_once( GITHUB2WP_INC_PATH . 'cron.php' );
require_once( GITHUB2WP_INC_PATH . 'ajax.php' );

require_once( GITHUB2WP_INC_PATH . '/admin/admin.php' );
require_once( GITHUB2WP_INC_PATH . '/admin/ui.php' );

require_once( GITHUB2WP_INC_PATH . 'GitHub2WP.class.php' );


add_action( 'plugins_loaded', 'github2wp_language_init' );
function github2wp_language_init() {
	load_plugin_textdomain( GITHUB2WP, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}
