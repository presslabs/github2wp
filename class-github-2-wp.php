<?php
if ( ! class_exists( 'Github_2_WP' ) ):

class Github_2_WP {

	public $config = array(
		'user' => '',
		'repo' => '',
		'repo_type' => '',
		'access_token' => '',
		'zip_url' => '',
		'git_api_base_url' => 'https://api.github.com/',
		'source' => ''
	);

	function __construct( $array ) {
		if ( is_array( $array ) )
			foreach ( $array as $key => $value ) {
				if ( array_key_exists( $key, $this->config ) )
					if ( '' == $this->config[ $key ] )
						$this->config[ $key ] = $array[ $key ];
			}

		$this->create_zip_url();
	}


	public function get_config() {
		return $this->config;
	}


	public function create_zip_url() {
		if ( '' != $this->config['user'] && '' != $this->config['repo'] && null != $this->config['access_token'] && '' != $this->config['source'] ) {
			$this->config['zip_url'] = $this->config['git_api_base_url'] 
			. sprintf( 'repos/%s/%s/zipball/%s?access_token=%s', $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token'] );
		}
	}

	public function return_git_archive_url() {
		return $this->store_git_archive();
	}

	public function store_git_archive() {
		set_time_limit (300);
		$url = $this->config['zip_url'];

		$upload_dir = GITHUB2WP_ZIPBALL_DIR_PATH;
		$upload_url = GITHUB2WP_ZIPBALL_URL;

		$upload_dir_zip .= $upload_dir . wp_hash( $this->config['repo'] ) . '.zip';	
		$upload_url_zip .= $upload_url . '/' . wp_hash( $this->config['repo'] ) . '.zip';

		$args = array(
			'method'      => 'GET',
			'timeout'     => 150,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(),
			'body'        => null,
			'cookies'     => array()
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'github2wp_settings_errors', 
						'repo_archive_error', 
						__( 'An error has occured:', GITHUB2WP ) . $error_message, 
						'error' );

			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 == $code ) {
			$bit_count = file_put_contents( $upload_dir_zip, wp_remote_retrieve_body( $response ) );

			if ( $bit_count ) {
				$zip = new ZipArchive;
				$res = $zip->open( $upload_dir_zip );

				$folder_name = $this->config['user'] . '-' . $this->config['repo'] . '-' ;

				if ( true === $res ) {
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
						if ( file_exists( $upload_dir . $this->config['repo'] . '/.gitmodules' ) ) {
							$submodules = Github_2_WP::parse_git_submodule_file( $upload_dir.$this->config['repo'] . '/.gitmodules' );

							if ( is_array( $submodules ) && ! empty( $submodules ) )
								foreach ( $submodules as $module ) {
									if ( ! $error_free )
										break;
								}

							$data = Github_2_WP::get_data_from_git_clone_link( $module['url'] );

							if ( $data ) {
								$sub_repo = $data['repo'];
								$sub_user = $data['user'];
								$sub_commit = $this->get_submodule_active_commit( $sub_user, $module['path'], $this->config['source'] ); 
								
								if ( ! $sub_commit ) {
									$error_free = false;
									add_settings_error( 'github2wp_settings_errors', 
										'repo_archive_submodule_error', 
										__( "At least one of the submodules included in the resource failed to be retrieved! No permissions or repo does not exist. ", GITHUB2WP ), 
										'error' );
								}	else {
									$sub_url = $this->config['git_api_base_url']
										. sprintf( 'repos/%s/%s/zipball/%s?access_token=%s', $sub_user, $sub_repo, $sub_commit, $this->config['access_token'] );
									
									$sw = Github_2_WP::get_submodule_data( $sub_url, $upload_dir . $this->config['repo'] . '/'.$module['path'], $module['path'] );

									if ( ! $sw ) {
										$error_free = false;
										add_settings_error( 'github2wp_settings_errors', 
											'repo_archive_submodule_error', 
											__( "At least one of the submodules included in the resource failed to be retrieved! No data retrieved. ", GITHUB2WP ), 
											'error' );
									}
								}
							} else {
										$error_free = false;
							}	
						}
					}

					if ( $error_free )
						$this->addDirectoryToZip( $zip, $upload_dir . $this->config['repo'], strlen( $upload_dir ), substr( strrchr( $upload_dir . $name, '-' ), 1, 7 ) );
					$zip->close();
				}
				
				github2wp_rmdir( $upload_dir . $this->config['repo'] );
			
				if ( $error_free )  {
					return $upload_url_zip;
				} else {
					if( file_exists( $upload_dir_zip ) )
						unlink( $upload_dir_zip );

					return false;
				}
			} else {
				if( file_exists( $upload_dir_zip ) )
					unlink( $upload_dir_zip );	

				add_settings_error( 'github2wp_settings_errors', 
					'repo_archive_error', 
					__( 'Empty archive. ', GITHUB2WP ), 
					'error' );

				return false;
			}
		} else {
		$error_message = wp_remote_retrieve_response_message( $response );
		add_settings_error( 'github2wp_settings_errors', 
			'repo_archive_error', 
			__('An error has occured:', GITHUB2WP) . "$code-$error_message", 
			'error' );

		return false;
	}
	return false;
}



	public function check_repo_availability() {
		$url = $this->config['git_api_base_url'] . "repos/{$this->config['user']}/{$this->config['repo']}/branches?access_token={$this->config['access_token']}";

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

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'github2wp_settings_errors', 
				'repo_archive_error', 
				__( 'An error has occured:', GITHUB2WP ) . $error_message, 
				'error' );

			return false;
		}

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
		$url = $this->config['git_api_base_url']."repos/{$this->config['user']}/{$this->config['repo']}/branches?access_token={$this->config['access_token']}";

		$branches = null;
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

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'github2wp_settings_errors', 
				'repo_archive_error', 
				__( 'An error has occured:', GITHUB2WP ) . $error_message, 
				'error' );

			return null;
		}

		$result = wp_remote_retrieve_body( $response );
		$result = json_decode( $result, true );

		if ( empty( $result['message'] ) ) {
			foreach ( $result as $branch ) {
				$branches[] = $branch['name'];
			}
		}

		return $branches;
	}


	public function addDirectoryToZip( &$zip, $dir, $base = 0, $version ) {
		foreach ( glob( $dir . '/*' ) as $file ) {
		  if ( is_dir( $file ) ) {
				$this->addDirectoryToZip($zip, $file, $base, $version);
			} else {
				$file_name = substr( $file, $base );
				$repo_file_name = $this->config['repo'] . '.php';

				if ( 'theme' == $this->config['repo_type'] )
					$repo_file_name = 'style.css';

				if ( basename( $file_name ) == $repo_file_name ) {
					$tag_version = 'Version';

					$f = fopen($file, 'rw+');
					$header_chunk = fread( $f, 8192 );

					$header_chunk = str_replace( "\r", "\n", $header_chunk );
					$new_header_chunk = preg_replace( '/^[ \t\/*#@]*' . preg_quote( $tag_version, '/' )	. ':(.*)$/mi', ' *Version: ' . $version, $header_chunk );

					if ( $header_chunk !== $new_header_chunk ) {
						$new_file_content = $new_header_chunk;
					} else {
						$new_header_chunk = preg_replace( '/\/\*(.*?)\*\//s', "/*\n *Version: " . $version . '$1*/', $header_chunk, 1 );

						//file pointer is at 8193 or at the end of the file
						$new_file_content = $new_header_chunk . stream_get_contents($f);
					}
	
					$zip->addFromString( $file_name, $new_file_content );

					fclose($f);
				} else {
					$zip->addFile( $file, $file_name );
				}
			}
		}
	}

	public function check_user() {
		$url = "https://api.github.com/user?access_token={$this->config['access_token']}";

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

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( '200' == wp_remote_retrieve_response_code( $response ) )
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

	public static function get_submodule_data( $url, $target, $path ) {
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

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) )
			return false;

		$bit_count = file_put_contents( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip', wp_remote_retrieve_body( $response ) );

		if ( ! $bit_count ) {
			return false;
		} else {
			$zip = new ZipArchive();

			if ( true === $zip->open(GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip') ) {
				if ( is_dir( $target ) )
					rmdir( $target );

				$zip->extractTo( dirname( $target ) );
				$folder_name = $zip->getNameIndex(0);

				rename( dirname( $target ) . "/$folder_name", dirname( $target ) . '/' . basename( $path ) );
				unlink( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip' );
				$zip->close();

				return true;
			} else {
				unlink( GITHUB2WP_ZIPBALL_DIR_PATH . 'submodule.zip' );

				return false;
			}
		}
	}
	
	public function get_submodule_active_commit( $sub_user, $path, $ref ) {
		$url = sprintf( 'https://api.github.com/repos/%s/%s/contents/%s?access_token=%s&ref=%s', $sub_user, $this->config['repo'], $path, $this->config['access_token'], $ref );
		$commit = null;

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

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) )
			return null;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $result['message'] )
			return null;

		if ( 'submodule' == $result['type'] )
			$commit = $result['sha'];
		else
			return null;

		return $commit;
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
		$commits = null;

		$url = sprintf( 'https://api.github.com/repos/%s/%s/commits?sha=%s&access_token=%s&per_page=%s',
			$this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token'],
			GITHUB2WP_MAX_COMMIT_HIST_COUNT );

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

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) )
			return null;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $result ) )
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
		$url = sprintf( 'https://api.github.com/repos/%s/%s/commits/%s?access_token=%s',
					   $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token'] );

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

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) )
			return null;

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $result )
			return $result['sha'];

		return null;
	}
}
endif;
