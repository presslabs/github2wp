<?php
if ( ! class_exists( 'Github_2_WP' ) ):

class Github_2_WP {
	private static $api_base = 'https://api.github.com/';
	private static $endpoints = array(
		'zip_url'    => 'repos/:user/:repo/zipball/:source?access_token=:access_token',
		'branches'   => 'repos/:user/:repo/branches?access_token=:access_token',
		'user_check' => 'user?access_token=:access_token',
		'contents'   => 'repos/:user/:repo/contents/:path?ref=:source&access_token=:access_token',
		'commits'    => 'repos/:user/:repo/commits?sha=:source&per_page=:limit&access_token=:access_token'
	);

	private $config = array();


	function __construct( array $resource=array(), $version='HEAD' ) {
		$access_token = get_option( 'github2wp_options' )['default']['access_token'];

		if( !empty($resource) ) {
			$this->config = array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'repo_type'    => github2wp_get_repo_type( $resource['resource_link'] ),
				'source'       => ( $version === 'HEAD' ) ? $resource['repo_branch'] : $version
			);
		}

		$this->config = wp_parse_args( array( 'access_token' => $access_token ), $this->config );
		$this->create_zip_url();
	}


	private function create_zip_url() {
		$this->config['zip_url'] = static::$api_base
			. sprintf( 'repos/%s/%s/zipball/%s?access_token=%s',
					$this->config['user'],
					$this->config['repo'],
					$this->config['source'],
					$this->config['access_token']
				);
	}

	public function getApiUrl( $endpoint, array $extra_segments=array() ) {
		$endpoint = static::$endpoints[ $endpoint ];

		$segments = wp_parse_args( $segments, $this->config );
		foreach( $segments as $seg => $value ) {
			$endpoint = str_replace( ':'.$seg, $value, $endpoint );
		}

		return static::$api_base . $endpoint;
	}


	public static function makeRequest( $url='' ) {
		if ( FALSE === filter_var($url, FILTER_VALIDATE_URL ) )
			throw new \InvalidArgumentException( "$url is not a valid url!" );

		set_time_limit(200);
		
		$args = array(
			'method'      => 'GET',
			'timeout'     => 150,
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

		if ( 200 !== $code )
			throw new \Exception( "Error code $code received at $url." );

		return $response;
	}


	private function validate_api_response( $url ) {
		try {
			$response = static::makeRequest( $url );
		} catch(\Exception $e) {
			add_settings_error( 'github2wp_settings_errors', 
				'repo_api_error', 
				__( 'An error has occured:', GITHUB2WP ) . $e->getMessage(), 
				'error'
			);

			return false;
		}

		return $response;
	}


	public function check_repo_availability() {
		$url = $this->getApiUrl( 'branches' );
		$response = $this->validate_api_response( $url );

		if ( !$response )
			return false;


		$result = wp_remote_retrieve_body( $response );
		$result = json_decode($result, true);

		if ( 'Not Found' == $result['message'] ) {
			add_settings_error( 'github2wp_settings_errors', 
				'repo_no_perm', 
				__( 'You have insufficient permissions or repo does not exist!', GITHUB2WP ), 
				'error' );

			return false;
		}

		return true;
	}


	public function fetch_branches() {
		$url = $this->getApiUrl( 'branches' );
		$response = $this->validate_api_response( $url );

		if ( !$response )
			return null;

		$result = wp_remote_retrieve_body( $response );
		$result = json_decode( $result, true );

		if ( !empty( $result['message'] ) )
			return null; 

		$branches = null;
		foreach ( $result as $branch ) {
			$branches[] = $branch['name'];
		}

		return $branches;
	}


	public function check_user( $access_token ) {
		$url = $this->getApiUrl( 'user' );
		$response = $this->validate_api_response( $url );

		if ( !$response )
			return false;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $result['message'] ) ) 
			return true;

		return false;
	}


	public static function check_svn_avail( $resource_name, $type ) {
		$url = "http://{$type}s.svn.wordpress.org/{$resource_name}/";

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

		if ( '200' == wp_remote_retrieve_response_code( $response ) )
			return true;

		return false;
	}


	public static function parse_git_submodule_file( $file_path ) {
		$submodules = parse_ini_file( $file_path, true );

		return $submodules;
	}


	public function get_submodule_data( $url, $target, $path ) {
		$response = $this->makeRequest( $url );
		
		if ( !$response )	
			return false;

		$bit_count = file_put_contents( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip',
			wp_remote_retrieve_body( $response ) );

		if ( ! $bit_count )
			return false;

		$zip = new ZipArchive();

		if ( true !== $zip->open(GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip') ) {
			unlink( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip' );
			return false;
		}

		if ( is_dir( $target ) )
			rmdir( $target );

		$zip->extractTo( dirname( $target ) );
		$folder_name = $zip->getNameIndex(0);

		rename( dirname($target)."/$folder_name", dirname($target).'/'.basename($path) );
		unlink( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip' );
		$zip->close();

		return true;
	}


	public function get_submodule_active_commit( $sub_user, $path, $ref ) {
		$args = array(
			'user' => $sub_user,
			'path' => $path,
		);
	
		$url = $this->getApiUrl( 'contents', $args );	
		$response = $this->makeRequest( $url );

		if ( !$response )
			return null;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $result['message'] )
			return null;

		if ( 'submodule' != $result['type'] )
			return null;


		return $result['sha'];
	}


	public static function get_data_from_git_clone_link( $url ) {
		if ( 0 === strpos( $url, 'https://github.com/' ) ) {
			$data = array(
				'repo' => basename( $url, '.git' ),
				'user' => basename( dirname( $url ) )
			);

			return $data;
		}

		if ( 0 === strpos( $url, 'git@github.com:' ) ) {
			$data = array(
				'repo' => basename( $url, '.git' ),
				'user'  =>  github2wp_str_between( 'git@github.com:', '/', $url )
			);
			
			return $data;
		}

		return null;
	}


	public function get_commits() {

		$args = array(
			'limit' => GITHUB2WP_MAX_COMMIT_HIST_COUNT
		);

		$url = $this->getApiUrl( 'commits' );
		$response = $this->makeRequest( $url );

		if ( !$response )
			return null;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );


		$commits = array();
		if ( !is_array( $result ) ) {
			if( $result )
				return $result;
			else
				return array();
		}


		foreach($result as $commit) {
			$commits[] = array(
				'sha'       => $commit['sha'],
				'message'   => $commit['commit']['message'],
				'git_url'   => $commit['html_url'],
				'timestamp' => $commit['commit']['author']['date']
			);
		}

		return $commits;
	}


	public function get_head_commit() {
		$result = $this->get_commits();

		if ( !$result )
			return null;

		if ( is_array($result) )
			return $result[0]['sha'];

		return $result['sha'];
	}


	public function return_git_archive_url() {
		return $this->store_git_archive();
	}


	public function addResourceDirectoryToZip( &$zip, $dir, $base = 0, $version ) {
		foreach ( glob( $dir . '/*' ) as $file ) {
		  if ( is_dir( $file ) ) {
				$this->addResourceDirectoryToZip($zip, $file, $base, $version);
				continue;
			}

			$file_name = substr( $file, $base );
			$repo_file_name = $this->config['repo'] . '.php';

			if ( 'theme' == $this->config['repo_type'] )
				$repo_file_name = 'style.css';


			if ( basename( $file_name ) != $repo_file_name ) {
				$zip->addFile( $file, $file_name );
				continue;
			}


			//Add a version tag + SHA in the first block comment of the main plugin/theme file
			$tag_version = 'Version';

			$f = fopen($file, 'rw+');
			$header_chunk = fread( $f, 8192 );

			//If the header is present simply replace its value
			$header_chunk = str_replace( "\r", "\n", $header_chunk );
			$new_header_chunk = preg_replace( '/^[ \t\/*#@]*'
					. preg_quote( $tag_version, '/' )
					. ':(.*)$/mi',
				' *Version: ' . $version,
				$header_chunk
			);


			//If the header was not present add it by force
			if ( $header_chunk !== $new_header_chunk ) {
				$new_file_content = $new_header_chunk;
			} else {
				$new_header_chunk = preg_replace(
					'/\/\*(.*?)\*\//s',
					"/*\n *Version: " . $version . '$1*/', $header_chunk,
					1
				);

				//file pointer is at 8193 or at the end of the file
				$new_file_content = $new_header_chunk . stream_get_contents($f);
			}

			$zip->addFromString( $file_name, $new_file_content );

			fclose($f);
		}
	}


	public function store_git_archive() {
		set_time_limit (300);

		$url = $this->getApiUrl('zip_url');
		$response = $this->makeRequest( $url );

		if ( !$response )
			return false;

		$upload_dir = GITHUB2WP_ZIPBALL_DIR_PATH;
		$upload_url = GITHUB2WP_ZIPBALL_URL;

		$upload_dir_zip .= $upload_dir . wp_hash( $this->config['repo'] ) . '.zip';	
		$upload_url_zip .= $upload_url . '/' . wp_hash( $this->config['repo'] ) . '.zip';

		$bit_count = file_put_contents( $upload_dir_zip, wp_remote_retrieve_body( $response ) );
		
		if ( !$bit_count ) {
			github2wp_cleanup($uploade_dir_zip);

			add_settings_error( 'github2wp_settings_errors', 
				'repo_archive_error', 
				__( 'Empty archive. ', GITHUB2WP ), 
				'error' );

			return false;
		}

		//Extract git archive and take info from it
		$zip = new ZipArchive;
		$zip->open( $upload_dir_zip ); 

		$folder_name = $this->config['user'] . '-' . $this->config['repo'] . '-' ;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {   
			$name = $zip->getNameIndex( $i );

			if ( 0 == strpos( $folder_name, $name ) ) {
				$name = substr( $name, 0, -1 );
				break;
			}
		}

		$zip->extractTo( $upload_dir );
		$zip->close();
		unlink( $upload_dir_zip );


		if( is_dir( $upload_dir . $name ) )
			rename( $upload_dir . $name, $upload_dir . $this->config['repo'] );

		$created = $zip->open( $upload_dir_zip, ZIPARCHIVE::CREATE );

		if ( $created ) {

			$error_free = true;

			//PROCESS first level submodules
			if ( file_exists( $upload_dir . $this->config['repo'] . '/.gitmodules' ) ) {
				$submodules = Github_2_WP::parse_git_submodule_file( $upload_dir.$this->config['repo'] . '/.gitmodules' );

				if ( is_array( $submodules ) && ! empty( $submodules ) )
					foreach ( $submodules as $module ) {
						if ( ! $error_free )
							break;

						$data = Github_2_WP::get_data_from_git_clone_link( $module['url'] );

						if ( $data ) {
							$sub_repo = $data['repo'];
							$sub_user = $data['user'];
							$sub_commit = $this->get_submodule_active_commit(
								$sub_user,
								$module['path'],
								$this->config['source']
							);
							
							if ( !$sub_commit ) {
								$error_free = false;
								add_settings_error( 'github2wp_settings_errors', 
									'repo_archive_submodule_error', 
									__( "At least one of the submodules included in the resource failed"
									. " to be retrieved! No permissions or repo does not exist. ", GITHUB2WP ), 
									'error' );
							}	else {
								$args = array(
									'user'   => $sub_user,
									'repo'   => $sub_repo,
									'source' => $sub_commit,
								);
								
								$sub_url = $this->getApiUrl( 'zip_url', $args );
								$sw = $this->get_submodule_data(
									$sub_url,
									$upload_dir . $this->config['repo'] . '/'.$module['path'],
									$module['path']
								);

								if ( ! $sw ) {
									$error_free = false;
									add_settings_error( 'github2wp_settings_errors', 
										'repo_archive_submodule_error', 
										__( "At least one of the submodules included in the resource failed"
										. " to be retrieved! No data retrieved. ", GITHUB2WP ), 
										'error' );
								}
							}
						} else {
							$error_free = false;
						}
					}
			}

			if ( $error_free )
				$this->addResourceDirectoryToZip(
					$zip, $upload_dir . $this->config['repo'],
					strlen( $upload_dir ),
					substr(
						strrchr( $upload_dir . $name, '-' ),
						1,
						7
					)
				);
				
			$zip->close();
		}

		github2wp_rmdir( $upload_dir . $this->config['repo'] );

		if ( $error_free )  {
			return $upload_url_zip;
		} else {
			github2wp_cleanup($uploade_dir_zip);

			return false;
		}

		return false;
	}
}
endif;
