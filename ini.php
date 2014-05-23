<?php
/*
 * Plugin Name: github2wp
 * Plugin URI: http://wordpress.org/extend/plugins/github2wp/
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/
 * Version: 1.0.0
 */

namespace github2wp;

use classes\Loader;

spl_autoload_register( function ( $className ) {
    $className = __DIR__ . '/' . str_replace( '\\', DIRECTORY_SEPARATOR, $className );
    $className .= '.php';

    if ( file_exists( $className ) )
        include $className;
} );


$github2Wp = new Loader( __FILE__, array () );
$github2Wp->load();