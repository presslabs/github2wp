<?php
if ( !class_exists('Git2WP') ):

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
	
	public function store_git_archive() {
		$url = $this->config['zip_url'];
		
		$upload = wp_upload_dir();
		
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/git2wp/';
		
		if (! is_dir($upload_dir)) 
		   mkdir( $upload_dir, 0777, true );

		$upload_dir_zip .= $upload_dir . wp_hash($this->config['repo']) . ".zip";	
		
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
			
			if($bit_count) {
				$zip = new ZipArchive;
				$res = $zip->open($upload_dir_zip);
				
				$folder_name = $this->config['user']."-".$this->config['repo']."-" ;
				
				if ($res === TRUE) {
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

    			if( is_dir($upload_dir.$name))
    				rename($upload_dir.$name, $upload_dir.$this->config['repo']);
    			
    			$created = $zip->open( $upload_dir_zip, ZIPARCHIVE::CREATE );
    			
    			if($created)
    				$this->addDirectoryToZip($zip, $upload_dir.$this->config['repo'], strlen($upload_dir),
					substr($upload_dir.$name, -7) );
   				
   				$zip->close();
   				git2wp_rmdir($upload_dir.$this->config['repo']);
    			
    			return true;
    		}
			}else {
				$error_message = wp_remote_retrieve_response_message($response);
				add_settings_error( 'git2wp_settings_errors', 
							'repo_archive_error', 
							"An error has occured: $code - $error_message", 
							'error' );
						
				return false;
			}
			return false;
		}
	}
		
	public function check_repo_availability() {
		$url = $this->config['git_api_base_url']."repos/".$this->config['user']
					 ."/".$this->config['repo']."/branches"."?access_token=".$this->config['access_token'];
		
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows;U;Windows NT 5.1;en-US;rv:1.8.1.13)"." Gecko/20080311 Firefox/2.0.0.13');		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
		
		curl_close($ch);
		
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
		
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows;U;Windows NT 5.1;en-US;rv:1.8.1.13)"." Gecko/20080311 Firefox/2.0.0.13');		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
		
		curl_close($ch);
		
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
}

endif;
