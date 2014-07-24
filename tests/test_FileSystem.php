<?php

namespace github2wp\tests;

use github2wp\FileSystem;

class WP_TEST_FileSystem extends \WP_UnitTestCase {
	
	public function test_FileSystemUnicity() {
		$fs1 = FileSystem::getInstance();
		$fs2 = FileSystem::getInstance();

		$this->assertTrue($fs1 == $fs2);
	}

}
