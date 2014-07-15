<?php

namespace github2wp\git;

use User; 

class Repo implements \Serializable {
	private $owner;
	private $args;

	public function __construct( User $owner, array $db_args  ) {
		$this->owner = $owner;
		$this->args = $db_args;
		//TODO make type differentiation from the resource url after validation
	}


	public function update() {
		//TODO
	}

	public function install() {
		//TODO
	}


	public function isInstalled() {
		//TODO check wether it is installed or not at the correct location /plugins /themes
	}

	public function needsUpdate() {
		//TODO check wether this repo needs an update true/false , null->not installed
	}


	private function isValid() {
		//TODO see if the url is in a valid format SSH or HTTP
	}

	public static  function extractType() {
		//TODO after validation this should return SSH or HTTP or false on error
	}

	public function serialize() {
		//return serialize($this->data);
	}

	public function unserialize($data) {
		//$this->data = unserialize($data);
	}
}
