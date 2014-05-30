<?php

use github2wp\classes\Loader;

class WP_Test_Loader extends WP_UnitTestCase {
	private $loader;

	public function setUp() {
		$this->loader = new Loader( __FILE__ );
	}

	public function teadDown() {
		$this->loader = null;
	}


	public function test_getFile() {
		$this->assertEquals(__FILE__, $this->loader->getFile());
	}

	public function test_getAbsPath() {
		$this->assertEquals(dirname(__FILE__), $this->loader->getAbsPath());
	}


	public function test_log_created() {
		$this->assertNotNull(Loader::$logger);
	}

	public function test_log_has_path() {
		$prefix = $this->loader->getPrefix();

		$path = wp_upload_dir()[ 'basedir' ] . '/' . $prefix . 'log';

		$logger = Loader::$logger;
		$this->assertEquals($path, $logger->getPath());
	}

	public function test_cron_setup() {
		$crons = $this->loader->crons(array());

		$this->assertArrayHasKey('6h', $crons);
		$this->assertEquals( array( 'interval' => 21600, 'display' => __( 'Once every 6h' )), $crons['6h']);
	}

}
