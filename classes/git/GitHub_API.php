<?php

namespace github2wp\git;

use github2wp\git\API;
use github2wp\git\Repo;
use github2wp\git\User;
use github2wp\helper\Log;

class GitHub_API extends API {
	//TODO fill the $api_base
	const API_BASE = 'https://api.github.com/';

	private $endpoints = array(
		'availability_ping' => 'repos/:user/:repo/branches?access_token=:access_token',
		'repo_download'     => 'repos/:user/:repo/zipball/:version?access_token=:access_token'
	); 

	public function __construct( User $user ) {
		parent::__construct( $user );
	}

	public function fetch( Repo $repo, FileSystem $fs ) {
		$segments = array(
			'repo'         => $repo->getName(),
			'user'         => $user->getName(),
			'access_token' => $user->getToken()
		);

		$download_url = $this->getApiUrl( $this->endpoints['repo_download'], $segments );
		$fs->downloadFile( $download_url );

		//TODO make the transition to wp format
	}

	public function checkRepoVisibility( Repo $repo ) {
		//TODO edit this after a more clearer repo structure
		$segments = array(
			'repo' => 'dotfiles',
			'user' => 'krodyrobi',
			'access_token' => 'aaaa'
		);

		$ping_url = $this->getApiUrl( $this->endpoints['availability_ping'], $segments );

		try {
			$response_body = static::makeRequest( $ping_url ); 
		} catch ( \Exception $e ) {
			Log::writeException( $e );
			return false;
		}

		$result = json_decode($result, true);

		if ( 'Not Found' == $result['message'] ) {
			Log::write("Repo {$repo->getName()} not available");
			return false;
		}

		return true;
	}

	public function checkSubmodule( Repo $repo ) {}
	public function updateSubmodules( Repo $repo ) {}

	public function getBranches( Repo $repo ) {}
	public function getHeadCommit( Repo $repo, $branch ) {}
}
