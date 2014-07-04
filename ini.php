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

use github2wp\classes\Loader;

spl_autoload_register( function ( $className ) {

	if ( substr( $className, 0, strlen(__NAMESPACE__) ) === __NAMESPACE__ )
    	$className = substr($className, strlen(__NAMESPACE__));

    $className = trim($className);
    $className = __DIR__ . str_replace( '\\', DIRECTORY_SEPARATOR, $className );
    $className .= '.php';

    if ( file_exists( $className ) )
        include $className;
} );

global $github2wp;

$github2Wp = new Loader( __FILE__ );
$github2Wp->load();
