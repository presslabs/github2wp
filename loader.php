<?php

define( 'GITHUB2WP_MAIN_FILE_NAME', 'github2wp');
define( 'GITHUB2WP_MAIN_PLUGIN_FILE', dirname(__DIR__) . '/' . GITHUB2WP_MAIN_FILE_NAME . '.php' );
define( 'GITHUB2WP_PLUGIN_BASENAME', plugin_basename( GITHUB2WP_MAIN_PLUGIN_FILE ) );
define( 'GITHUB2WP_INC_PATH', __DIR__ . '/' );

define( 'GITHUB2WP_MAX_COMMIT_HIST_COUNT', 100 );
define( 'GITHUB2WP_ZIPBALL_DIR_PATH', WP_CONTENT_DIR . '/uploads/' . GITHUB2WP_MAIN_FILE_NAME . '/' );
define( 'GITHUB2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . GITHUB2WP_MAIN_FILE_NAME . '/' );
define( 'GITHUB2WP', GITHUB2WP_MAIN_FILE_NAME );



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
