<?php

define( 'GITHUB2WP_MAIN_FILE_NAME', 'github2wp');
define( 'GITHUB2WP_MAIN_PLUGIN_FILE', dirname(__DIR__) . '/' . GITHUB2WP_MAIN_FILE_NAME . '.php' );
define( 'GITHUB2WP_PLUGIN_BASENAME', plugin_basename( GITHUB2WP_MAIN_PLUGIN_FILE ) );
define( 'GITHUB2WP_INC_PATH', __DIR__ . '/' );

define( 'GITHUB2WP_MAX_COMMIT_HIST_COUNT', 100 );
define( 'GITHUB2WP_ZIPBALL_DIR_PATH', WP_CONTENT_DIR . '/uploads/' . GITHUB2WP_MAIN_FILE_NAME . '/' );
define( 'GITHUB2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . GITHUB2WP_MAIN_FILE_NAME . '/' );
define( 'GITHUB2WP', GITHUB2WP_MAIN_FILE_NAME );
