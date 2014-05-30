<?php

use github2wp\classes\helper\Log;

class WP_Test_Logger extends WP_UnitTestCase {
	private $log;
	private $log_path;

	public function setUp()	{
		$this->log = Log::getInstance();

		$this->log_path = __DIR__ . '/log';
		Log::setPath($this->log_path);
	}

	public function tearDown() {
		Log::setPath( null );
		$this->log = null;

		if ( file_exists($this->log_path) )
			unlink($this->log_path);
	}


	public function test_write_nonexistant_file_creation() {
		if ( file_exists($this->log_path) )
			unlink($this->log_path);


		$this->log->write('test');
		$this->assertTrue(file_exists( $this->log_path ));
	}

	public function test_write_file() {
		$this->log->write('test');
		$this->assertContains('test', file_get_contents( $this->log_path ));
	}
}