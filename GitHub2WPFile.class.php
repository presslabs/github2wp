<?php
if ( ! class_exists('GitHub2WPFile') ):
class GitHub2WPFile {
	var $ftrHandle;
	var $ftwHandle;
	var $ftwPath;
	
	function __construct($ftr, $ftw) {
		$this->ftrHandle = new SplFileObject($ftr, "rb");
		$this->ftwHandle = new SplFileObject($ftw, "wb");
		$this->ftwPath = $ftw;
	}
	
	function checkFtr() {
		if($this->ftrHandle)
			return true;
	}
	
	function writeToFile() {
		while (!$this->ftrHandle->eof()):
			$this->ftwHandle->fwrite($this->ftrHandle->fgets());
		endwhile;
	}
	
	function pathFtw() {
		return $this->ftwPath;
	}
}
endif;
