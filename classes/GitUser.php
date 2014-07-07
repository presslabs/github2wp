<?php

namespace github2wp\classes;

use github2wp\classes\Cron;
use github2wp\classes\GitRepo;

class GitUser {
	private $repos = array();
	private $authType ='token'; //can be token or ssh
	private $cron;
	private $loader;

	private $dbData;

	public function __construct( Loader $loader ) {
		$this->loader = $loader;
		$this->cron = new Cron( $this ); 


		if ( $this->isAuthenticated()  )
			$this->repos = $this->getUserRepos();


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


	private function loadDB() {
		$this->dbData = get_option( $loader->getPrefix() . 'options', array() );
	}

	private function getUserRepos() {
		$this->loadDB();

		$dbReps = $this->dbData['repos'];

		$this->repos = array();
		foreach( $dbReps as $repo ) {
				$this->repos[] = new GitRepo( $this, $repo );
		}
	}
}
