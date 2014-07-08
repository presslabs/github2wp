<?php

namespace github2wp\classes\git;

use API;
use Repo;
use User;

class GitHub_API extends API {
	//TODO fill the $api_base
	const API_BASE = '';

	public function __construct( User $user ) {
		parent::__construct( $user, self::API_BASE );
	}
}
