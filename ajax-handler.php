<?php 
	require_once('./../../../wp-blog-header.php');
	require_once('./Git2WP.class.php');
	
	$options = get_option('git2wp_options');
	$resource_list = $options['resource_list'];
	$default = $options['default'];

	if( isset($_GET['id']) and isset($_GET['git2wp_action']))
		if( $_GET['git2wp_action'] == 'fetch_branches' ) {
			$resource = $resource_list[$_GET['id']];
			
			$response = '';
			
			$git = new Git2WP(array(
										"user" => $resource['username'],
										"repo" => $resource['repo_name'],
										"access_token" => $default['access_token'],
										"source" => $resource['repo_branch'] 
									));
					
			$branches = $git->fetch_branches();
			error_log(print_r($branches, true));
			
			if(count($branches) > 0) {
				foreach($branches as $d)
					$response .= "<option value='$d'>$d</option>";

				echo $response;
			}
		}
