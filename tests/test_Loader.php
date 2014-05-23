<?php

use github2wp\classes\Loader;

class WP_Test_Loader extends WP_UnitTestCase {

	public function test_getFile() {
		$loader = new Loader( __FILE__ );

		$this->assertEquals(__FILE__, $loader->getFile());
	}

	public function test_getAbsPath() {
		$loader = new Loader( __FILE__ );

		$this->assertEquals(dirname(__FILE__), $loader->getAbsPath());
	}

}
