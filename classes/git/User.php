<?php

namespace github2wp\classes\git;

use Repo;
use github2wp\classes\Loader;

class User {
	private $authType ='token'; //can be token or ssh
	private $cron;
	private $loader;

	private $dbData;

	public function __construct( Loader $loader ) {
		$this->loader = $loader;
		$this->cron = new Cron( $this ); 

		//TODO make it read from the database all the repos info
		//TODO check if user needs authentification and if so use cron to check and update itself	
	}


	public function isAuthenticated() {
		$this->loadDB();
		$user = $this->dbData['user'];

		if ( $user && !$user['app_reset'] && !$user['needs_refresh'] && $user['token'] != ''	)
			return true;

		return false;
	}


	public function save() {
		update_option( $loader->getPrefix() . 'options', $this->dbData );
	}

	private function loadDB() {
		$this->dbData = get_option( $loader->getPrefix() . 'options', array() );
	}

	public function addRepo( $resource_url ) {
		$this->loadDB();


		//TODO change this to make it work
		$args = array(
			'type' => '',
			'link' => '',
			'name' => '',
			'branch'=>'',
			'username'=>'',
			'is_on_wp_svn'=>'',
			'head_commit'=>'',
		);

		$this->data['repos'][] = new Repo( $this,  );
	}
}
