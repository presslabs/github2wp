<?php

namespace github2wp;

use github2wp\git\API;
use github2wp\helper\Log;

class FileSystem {
	private static $instance;
	private $upload_dir;


	private function __construct() {
		$this->upload_dir = wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR.'git2wp'.DIRECTORY_SEPARATOR;

		if( !is_dir($this->upload_dir) )
			mkdir($this->upload_dir, 0755, true);	
	}


	public static function getInstance() {
		if ( !self::$instance )
				self::$instance = new FileSystem();

		return self::$instance;
	}


	public function downloadFile( $url, $fileName ) {
		try {
			$response_body = API::makeRequst($url);
		} catch (\Exception $e) {
			Log::writeException( $e );
			return false;
		}

		$bitCount = file_put_contents($this->upload_dir . $fileName . '.zip');

		return $bitCount ? true : false;
	}


	public function hashFileName( $filePath, $newName='' ) {
		if( is_dir($filePath) )
			return false;

		$dir = dirname($filePath);

		if ( !$newName )
			$newName = basename($filePath);

		$newName = wp_hash($newName);

		rename($filePath, $dir.DIRECTORY_SEPARATOR.$newName);
	}

}
