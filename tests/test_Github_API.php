<?php

namespace github2wp\tests;

use github2wp\git\GitHub_API;

class WP_Test_Github_API extends \WP_UnitTestCase {
	private $gitApi = null;

	public function setUp() {
		$this->gitApi = new GitHub_API(new User());
	}	

	public function tearDown() {
		$this->gitApi = null;
	}

	public function test_repoVisibility_wrong_parameter_type() {
		$this->gitApi->checkRepoVisibility( 'random' );
	}
}
