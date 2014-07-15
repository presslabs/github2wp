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

use github2wp\Loader;


require 'vendor/autoload.php';


global $github2wp;

$github2Wp = new Loader( __FILE__ );
$github2Wp->load();
