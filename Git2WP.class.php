<?php
if ( !class_exists('Git2WP') ):
	if( !defined('GIT2WP_ZIPBALL_URL') )
		define('GIT2WP_ZIPBALL_URL', home_url() . '/wp-content/uploads/' . basename(dirname(__FILE__)) );
	if( !defined('GIT2WP_ZIPBALL_DIR_PATH') )
		define('GIT2WP_ZIPBALL_DIR_PATH', ABSPATH . '/wp-content/uploads/' . basename(dirname(__FILE__)) . '/' );
	if( !defined('GIT2WP_MAX_COMMIT_HIST_COUNT'))
		define('GIT2WP_MAX_COMMIT_HIST_COUNT', 100);
		

//------------------------------------------------------------------------------
/*
DONT FORGET TO SAVE THE INSTANCE OF THIS CLASS IN THE DATABASE
*/
class Git2WP {
	
	public $config = array(
		"user" => "",
		"repo" => "",
		"repo_type" => "",
		"client_secret" => "",
		"client_id" => "",
		"access_token" => "",
		"zip_url" => "",
		"git_api_base_url" => "https://api.github.com/",
		"git_endpoint" => "",
		"source" => ""
	);
	
	function __construct($array) {
		if(is_array($array))
			foreach ($array as $key => $value)
				if(array_key_exists($key, $this->config))
					if($this->config[$key] == '')
						$this->config[$key] = $array[$key];

		$this->create_zip_url();
	}


	public function get_config() {
		return $this->config;
	}


	public function create_zip_url() {
		if ($this->config['user'] != '' and $this->config['repo'] != '' and $this->config['access_token'] != NULL and $this->config['source'] != '') {
			$this->config['zip_url'] = $this->config['git_api_base_url'] 
			. sprintf("repos/%s/%s/zipball/%s?access_token=%s", $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token']);
		}
	}

	public function return_git_archive_url() {
		return $this->store_git_archive();
	}

	public function store_git_archive() {
		$url = $this->config['zip_url'];
		
		$upload_dir = GIT2WP_ZIPBALL_DIR_PATH;
		$upload_url = GIT2WP_ZIPBALL_URL;

		if (! is_dir($upload_dir)) 
		   mkdir( $upload_dir, 0777, true );

		$upload_dir_zip .= $upload_dir . wp_hash($this->config['repo']) . ".zip";	
		$upload_url_zip .= $upload_url . "/" . wp_hash($this->config['repo']) . ".zip";

		$args = array(
			'method'      =>    'GET',
			'timeout'     =>    50,
			'redirection' =>    5,
			'httpversion' =>    '1.0',
			'blocking'    =>    true,
			'headers'     =>    array(),
			'body'        =>    null,
			'cookies'     =>    array()
		);

		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'git2wp_settings_errors', 
						'repo_archive_error', 
						"An error has occured: $error_message", 
						'error' );

			return false;
		}

		$code = wp_remote_retrieve_response_code($response);

		if($code == 200) {
			$bit_count = file_put_contents($upload_dir_zip, wp_remote_retrieve_body( $response ));
			
			error_log("200");
				
			if($bit_count) {
				$zip = new ZipArchive;
				$res = $zip->open($upload_dir_zip);
				
				error_log("bitcount ok");
					
				$folder_name = $this->config['user']."-".$this->config['repo']."-" ;

				if ($res === TRUE) {
					error_log("res opened OK");
					for($i = 0; $i < $zip->numFiles; $i++) {   
						$name = $zip->getNameIndex($i);

						if(strpos($folder_name, $name) == 0) {
							$name = substr($name, 0, -1);
							break;
						}
					}

					$zip->extractTo($upload_dir);
					$zip->close();
					unlink($upload_dir_zip);
	
					if( is_dir($upload_dir.$name) )
						rename($upload_dir.$name, $upload_dir.$this->config['repo']);

					$created = $zip->open( $upload_dir_zip, ZIPARCHIVE::CREATE );
					
					if($created) {
						$error_free = true;
						error_log("created new zip pointer");
						if(file_exists($upload_dir.$this->config['repo']."/.gitmodules")) {
							error_log("submodules detected");
							$submodules = Git2WP::parse_git_submodule_file($upload_dir.$this->config['repo']."/.gitmodules");
							error_log("parsed module file" . print_r($submodules, true));
							
							if(is_array($submodules) && !empty($submodules))
								foreach($submodules as $module) {
									error_log("Entered for each loop");
									if(!$error_free) {
										error_log("foreach exit by force error");
										break;
									}
									
									$data = Git2WP::get_data_from_git_clone_link($module['url']) ;
									error_log("data from link ". print_r($data, true));
									if($data) {
										error_log("submodule data received" . print_r($data,true));
										$sub_repo = $data['repo'];
										$sub_user = $data['user'];
										
										
										$sub_commit = $this->get_submodule_active_commit($sub_user, $module['path'], $this->config['source']); 
										
										if(!$sub_commit) {
											error_log("no commit found for submodule");
											$error_free = false;
											add_settings_error( 'git2wp_settings_errors', 
																			'repo_archive_submodule_error', 
																			"At least one of the submodules included in the resource failed to be retrieved! No permissions or repo does not exist. ", 
																			'error' );
										}
										else {
											error_log("commit found");
											$sub_url = $this->config['git_api_base_url'].sprintf("repos/%s/%s/zipball/%s?access_token=%s", $sub_user, $sub_repo, $sub_commit, $this->config['access_token']);
											$sw = Git2WP::get_submodule_data($sub_url, $upload_dir.$this->config['repo']."/".$module['path'], $module['path']);
											
											if(!$sw) {
												error_log("no data");
												$error_free = false;
												
												add_settings_error( 'git2wp_settings_errors', 
																				'repo_archive_submodule_error', 
																				"At least one of the submodules included in the resource failed to be retrieved! No data retrieved. ", 
																				'error' );
											}
										}
									}else
										$error_free = false;
								}
						}
					
						if($error_free) {
							error_log("added to new archive");
							$this->addDirectoryToZip($zip, $upload_dir.$this->config['repo'], strlen($upload_dir), substr(strrchr($upload_dir.$name, '-'), 1, 7) );
						
						}
						$zip->close();
					}
					git2wp_rmdir($upload_dir.$this->config['repo']);
					error_log("removed" . serialize($upload_dir.$this->config['repo']));
				}
				
				if($error_free)  {
						error_log("returned zip link all ok $upload_url_zip");
						return $upload_url_zip;
				}else {
					error_log("cleaned zip dir error 1");
					if(file_exists($upload_dir_zip))
						unlink($upload_dir_zip);
							
					return false;
				}
			} else {
				error_log("cleaned zip dir error 2");
				if(file_exists($upload_dir_zip))
						unlink($upload_dir_zip);
						
				add_settings_error( 'git2wp_settings_errors', 
												'repo_archive_error', 
												"Empty archive. ", 
												'error' );
				return false;
			}
		}else {
			error_log("remote error");
			$error_message = wp_remote_retrieve_response_message($response);
			add_settings_error( 'git2wp_settings_errors', 
						'repo_archive_error', 
						"An error has occured: $code - $error_message", 
						'error' );

			return false;
		}
		return false;
	}
	


	public function check_repo_availability() {
		$url = $this->config['git_api_base_url']."repos/".$this->config['user']
					 ."/".$this->config['repo']."/branches"."?access_token=".$this->config['access_token'];

		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
				);

		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'git2wp_settings_errors', 
						'repo_archive_error', 
						"An error has occured: $error_message", 
						'error' );

			return false;
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode($result, true);

		if($result['message'] == 'Not Found'){
			add_settings_error( 'git2wp_settings_errors', 
							'repo_no_perm', 
							'You have insufficient permissions or repo does not exist!', 
							'error' );

			return false;
		}

		return true;
	}

	public function fetch_branches() {
		$url = $this->config['git_api_base_url']."repos/".$this->config['user']
					 ."/".$this->config['repo']."/branches"."?access_token=".$this->config['access_token'];

		$branches = null;
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
				);


		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'git2wp_settings_errors', 
						'repo_archive_error', 
						"An error has occured: $error_message", 
						'error' );

			return null;
		}

		$branches = null;
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
				);


		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			add_settings_error( 'git2wp_settings_errors', 
						'repo_archive_error', 
						"An error has occured: $error_message", 
						'error' );

			return null;
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode($result, true);

		if(empty($result['message'])){
			foreach($result as $branch)
				$branches[] = $branch['name'];
		}

		return $branches;
	}


	public function addDirectoryToZip(&$zip, $dir, $base = 0, $version) {
		foreach(glob($dir . '/*') as $file) {
		  if(is_dir($file))
			$this->addDirectoryToZip($zip, $file, $base, $version);
		  else {
				$file_name = substr($file, $base);

				$repo_file_name = $this->config['repo'].'.php';
				if ( $this->config['repo_type'] == 'theme' )
						$repo_file_name = 'style.css';

				if ( basename($file_name) == $repo_file_name ) {
						$tag_version = "Version: ";
						$zip_filename = basename($file_name);

						$file_content = file_get_contents($file);
						$old_version = $tag_version . git2wp_str_between($tag_version, "\n", $file_content);
						$new_version = $tag_version . $version;

						$new_file_content = str_replace($old_version, $new_version, $file_content);
						$zip->addFromString($file_name, $new_file_content);
				} else
					$zip->addFile($file, $file_name);
			}
		}
	}

	public function check_user() {
		$url = "https://api.github.com/user?access_token=".$this->config['access_token'];

		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
				);


		$response = wp_remote_get( $url, $args );

		if( is_wp_error( $response ) )
			return false;

		$result = json_decode(wp_remote_retrieve_body( $response ), true);
		
		if(wp_remote_retrieve_response_code( $response ) == '200')
			if(empty($result['message'])) 
				return true;
		
		return false;
	}
		
	public static function check_svn_avail( $resource_name, $type ) {
			$url = "http://".$type."s.svn.wordpress.org/".$resource_name."/";
			
			$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
			);
			
			$response = wp_remote_get( $url, $args );

			if(wp_remote_retrieve_response_code( $response ) == '200')
				return true;
			return false;
	}
	
	public static function parse_git_submodule_file($file_path) {
		$submodules = parse_ini_file($file_path, true);

		return $submodules;
		
	}
	
	public static function get_submodule_data($url, $target, $path) {
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
			);
			
			$response = wp_remote_get( $url, $args );
			
			if (is_wp_error( $response ) || wp_remote_retrieve_response_code($response) != 200)
				return false;
			
			$bit_count = file_put_contents(GIT2WP_ZIPBALL_DIR_PATH."submodule.zip", wp_remote_retrieve_body($response));
			
			if(!$bit_count) {
				
				return false;
			}
			else {
				$zip = new ZipArchive();
		
				if ($zip->open(GIT2WP_ZIPBALL_DIR_PATH."submodule.zip") === TRUE) {
					if(is_dir($target))
						rmdir($target);
						
					$zip->extractTo(dirname($target));
					
					$folder_name = $zip->getNameIndex(0);

					
					rename(dirname($target)."/".$folder_name, dirname($target)."/".basename($path));
					unlink(GIT2WP_ZIPBALL_DIR_PATH."submodule.zip");
					$zip->close();
					
					return true;
				}else {
					unlink(GIT2WP_ZIPBALL_DIR_PATH."submodule.zip");
					
					return false;
				}
			}
	}
	
	public function get_submodule_active_commit($sub_user, $path, $ref) {
		$url = sprintf('https://api.github.com/repos/%s/%s/contents/%s?access_token=%s&ref=%s', $sub_user, $this->config['repo'], $path, $this->config['access_token'], $ref);
		$commit = null;
		
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
			);
			
		$response = wp_remote_get( $url, $args );
		
		if( is_wp_error( $response ) || wp_remote_retrieve_response_code($response) != 200)
			return null;
		
		$result = json_decode(wp_remote_retrieve_body($response), true);
		
		if($result['message'])
			return null;
		
		if($result['type'] == 'submodule')
			$commit = $result['sha'];
		else
			return null;
	
		return $commit;
	}
	
	public static function get_data_from_git_clone_link($url) {
		if(strpos($url, "https://github.com/") === 0) {
			$data = array ('repo' => basename($url, '.git'),
									'user'  => basename(dirname($url))
									);
			return $data;
		}
		
		if(strpos($url, "git@github.com:") === 0) {
			$data = array ('repo' => basename($url, '.git'),
									'user'  =>  git2wp_str_between("git@github.com:", "/", $url)
									);
			return $data;
		}
		
		return null;
	}
	
	public function get_commits() {
		$commits = null;
		
		$url = sprintf('https://api.github.com/repos/%s/%s/commits?sha=%s&access_token=%s&per_page=%s',
					   $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token'], GIT2WP_MAX_COMMIT_HIST_COUNT);
		
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
			);
			
		$response = wp_remote_get( $url, $args );
		
		if( is_wp_error( $response ) || wp_remote_retrieve_response_code($response) != 200)
			return null;
		
		$result = json_decode(wp_remote_retrieve_body($response), true);
		
		if(is_array($result))
			foreach($result as $commit)
				$commits[] = array(
													'sha' => $commit['sha'],
													'message' => $commit['commit']['message'],
													'git_url' => $commit['html_url'],
													'timestamp' => $commit['commit']['author']['date']
												);
		
		return $commits;
	}
	
	public function get_head_commit() {
		$url = sprintf('https://api.github.com/repos/%s/%s/commits/%s?access_token=%s',
					   $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token']);
		
		$args = array(
				'method'      =>    'GET',
				'timeout'     =>    50,
				'redirection' =>    5,
				'httpversion' =>    '1.0',
				'blocking'    =>    true,
				'headers'     =>    array(),
				'body'        =>    null,
				'cookies'     =>    array()
			);
			
		$response = wp_remote_get( $url, $args );
		
		if( is_wp_error( $response ) || wp_remote_retrieve_response_code($response) != 200)
			return null;
		
		$result = json_decode(wp_remote_retrieve_body($response), true);
		
		if($result)
			return $result['sha'];
		
		return null;
	}
}

endif;
