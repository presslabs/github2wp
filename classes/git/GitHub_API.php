<?php

namespace github2wp\classes\git;

use API;
use Repo;
use User;

class GitHub_API extends API {
	//TODO fill the $api_base
	const API_BASE = 'https://api.github.com/';

	private $endpoints = array(
		'availability_ping' => 'repos/:user/:repo/branches?access_token=:access_token'
	); 

	public function __construct( User $user ) {
		parent::__construct( $user );
	}

	public function checkRepoVisibility( Repo $repo ) {
		//TODO edit this after a more clearer repo structure
		$segments = array(
			'repo' => 'dotfiles',
			'user' => 'krodyrobi',
			'access_token' => 'aaaa'
		);

		$ping_url = $this->getApiUrl( $this->endpoints['availability_ping'], $segments );

		$args = array(
			'method'      => 'GET',
			'timeout'     => 50,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(),
			'body'        => null,
			'cookies'     => array()
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) )
			return false;

		$result = wp_remote_retrieve_body( $response );
		$result = json_decode($result, true);

		if ( 'Not Found' == $result['message'] )
			return false;

		return true;
	}


	public function getApiUrl($endpoint, array $segments) {
		foreach( $segments as $seg => $value ) {
			$endpoint = str_replace( ':'.$seg, $value, $endpoint );
		}

		return self::API_BASE . $endpoint;
	}

}
