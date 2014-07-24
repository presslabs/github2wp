<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array('github2wp/ini.php')
);

require_once $_tests_dir . '/includes/bootstrap.php';


require 'vendor/autoload.php';
