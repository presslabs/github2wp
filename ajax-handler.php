<?php 
	require_once('./../../../wp-blog-header.php');
	require_once('./Git2WP.class.php');
	
	$options = get_option('git2wp_options');
	$resource_list = &$options['resource_list'];
	$default = $options['default'];

	if( isset($_POST['id']) and isset($_POST['branch']) and isset($_POST['git2wp_action'])){
		
		if( $_POST['git2wp_action'] == 'set_branch' ) {
			$resource = &$resource_list[$_POST['id']];
			error_log("req ok". print_r($resource, true));
			$response = '';
			
			$git = new Git2WP(array(
										"user" => $resource['username'],
										"repo" => $resource['repo_name'],
										"access_token" => $default['access_token'],
										"source" => $resource['repo_branch'] 
									));
					
			$branches = $git->fetch_branches();
			
			if(count($branches) > 0) {
				error_log("is array");
				foreach($branches as $br)
					if($br == $_POST['branch']) {
						error_log("branch $br");
						$resource['repo_branch'] = $br;
						update_option('git2wp_options', $options);
						break;
					}
			}
		}
	}
