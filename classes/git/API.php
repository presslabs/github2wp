<?php

namespace github2wp\git;

use User;
use Repo;

abstract class API {
	const API_BASE = '';

	protected $download_dir;
	protected $user;


	public function __construct( User $user ) {
		$this->user = $user;

		$this->download_dir = wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR;
	}

	public abstract function checkRepoVisibility( Repo $repo );
	public abstract function fetch( Repo $repo );

	public abstract function checkSubmodule( Repo $repo );
	public abstract function updateSubmodules( Repo $repo );
	
	public abstract function getApiUrl($endpoint, array $segments);

}
