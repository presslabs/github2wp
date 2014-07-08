<?php

namespace github2wp\classes\git;

use User;
use Repo;

abstract class API {
	protected $download_dir;
	protected $api_base;

	protected $user;


	public function __construct( User $user, $api_base ) {
		$this->user = $user;
		$this->api_base = $api_base;

		$this->download_dir = wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR;
	}

	public abstract function checkRepoVisibility( Repo $repo );
	public abstract function fetch( Repo $repo );

	public abstract function checkSubmodule( Repo $repo );
	public abstract function updateSubmodules( Repo $repo );
}
