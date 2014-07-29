<?php
/*
 * Plugin Name: github2wp
 * Plugin URI: http://wordpress.org/extend/plugins/github2wp/
 * Description: Managing themes and plugins from github.
 * Author: PressLabs
 * Author URI: http://www.presslabs.com/
 * Version: 1.0.0
 */

namespace git2wp;

use git2wp\Loader;


require 'vendor/autoload.php';


global $git2wp;

$git2wp = new Loader( __FILE__ );
$git2wp->load();
