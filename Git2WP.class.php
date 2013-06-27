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
		"client_secret" => "",
		"client_id" => "",
		"access_token" => "",
		"zip_url" => "",
		"git_api_base_url" => "https://api.github.com",
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
		if($this->config['user'] != '' and $this->config['repo'] != '' and $this->config['access_token'] != NULL and $this->config['source'] != '') {
			$this->config['zip_url'] = $this->config['git_api_base_url'] 
			. sprintf("/repos/%s/%s/zipball/%s?access_token=%s", $this->config['user'], $this->config['repo'], $this->config['source'], $this->config['access_token']);
		}
	}
	
	
	public function store_git_archive() {
		
		$url = $this->config['zip_url'];
		
		$upload = wp_upload_dir();
		
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/git2wp';
		
		if (! is_dir($upload_dir)) 
		   mkdir( $upload_dir, 0777, true );

		error_log($url);

		$upload_dir .= '/' . $this->config['repo'] . ".zip";	
		
		$fp = fopen ($upload_dir, 'wb+');
	
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows;U;Windows NT 5.1;en-US;rv:1.8.1.13)"." Gecko/20080311 Firefox/2.0.0.13');		

		curl_exec($ch);
		
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
		
		curl_close($ch);
		fclose($fp);
		
		if($httpCode == 404)
			return false;
		
		return filesize($upload_dir) > 0;
	}
}

endif;
