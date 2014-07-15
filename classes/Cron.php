<?php

namespace github2wp;

class Cron {
	//TODO Make the loader have access to the Cron OBJECT
	public function __construct() {
		add_filter( 'cron_schedules', array($this, 'cron_add_6h') );
		add_action( 'cron_hook', array($this, 'head_commit_cron') );
		add_action( 'cron_hook', array($this, 'token_cron') );
	}

	//TODO figure out what was needed for the cron to function

	public function cron_add_6h( $schedules ) {
		$schedules['6h'] = array(
			'interval' => 21600,
			'display'  => 'Once every 6 hours'
		);

		return $schedules;
	}


	public function token_cron() {
		
	}

	public function head_commits_cron() {
	
	}
}
