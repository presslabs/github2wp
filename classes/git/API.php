<?php

namespace github2wp\classes;

use github2wp\classes\GitUser;
use github2wp\classes\GitRepo;

abstract class Git {
	protected $api_base;
	protected GitUser $user;

	public function __construct( GitUser $user, $api_base ) {
		$this->user = $user;
		$this->api_base = $api_base;
	}

	public abstract function checkRepoConnection( GitRepo $repo );
	public abstract function fetch( GitRepo $repo );
	public abstract function checkSubmodule( GitRepo $repo );
	public abstract function updateSubmodules( GitRepo $repo );
}
