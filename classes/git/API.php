<?php

namespace git2wp\git;

use git2wp\git\User;
use git2wp\git\Repo;

abstract class API {
	const API_BASE = '';

	protected $download_dir;
	protected $user;


	public function __construct( User $user ) {
		$this->user = $user;

		$this->download_dir = wp_upload_dir()['basedir'].DIRECTORY_SEPARATOR;
	}

	public abstract function checkRepoVisibility( Repo $repo );
	public abstract function fetch( Repo $repo, FileSystem $fs );

	public abstract function checkSubmodule( Repo $repo );
	public abstract function updateSubmodules( Repo $repo );

	public abstract function getBranches( Repo $repo );
	public abstract function getHeadCommit( Repo $repo, $branch );


	public function getApiUrl($endpoint, array $segments) {
		foreach( $segments as $seg => $value ) {
			$endpoint = str_replace( ':'.$seg, $value, $endpoint );
		}

		return static::API_BASE . $endpoint;
	}


	public static function makeRequest( $url='' ) {
		if ( FALSE === filter_var( $url, FILTER_VALIDATE_URL ) )
			throw new \InvalidArgumentException( "$url is not a valid url!" );

		$args = array(
			'method'      => 'GET',
			'timeout'     => 50,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(),
			'body'        => null,
			'cookies'     => array()
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) )
			throw new \Exception( "Request at $url returned an error: " . $response->get_error_message() );

		$code = wp_remote_retrieve_response_code( $response );

		if ( '200' !== $code )
			throw new \Exception( "Error code $code received at $url." );

		$body = wp_remote_retrieve_body( $response );

		return $body;
	}

}
