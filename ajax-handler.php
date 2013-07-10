<?php 
	require_once('./../../../wp-blog-header.php');
	require_once('./Git2WP.class.php');
	
	$options = get_option('git2wp_options');
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array('success'=> false, 'error_messges'=>array(), 'success_messages'=>array());

	if( isset($_POST['id']) and isset($_POST['branch']) and isset($_POST['git2wp_action'])){
		if( $_POST['git2wp_action'] == 'set_branch' ) {
			$resource = &$resource_list[$_POST['id']];
			
			$git = new Git2WP(array(
										"user" => $resource['username'],
										"repo" => $resource['repo_name'],
										"access_token" => $default['access_token'],
										"source" => $resource['repo_branch'] 
									));
					
			$branches = $git->fetch_branches();
		
			$branch_set = false;
			
			if(count($branches) > 0) {
				foreach($branches as $br)
					if($br == $_POST['branch']) {
						$resource['repo_branch'] = $br;
						update_option('git2wp_options', $options);
						$branch_set = true;
						$response['success'] = true;
						break;
					}
			}
			
			if(!$branch_set)
				$response['error_messages'][] = 'Branch not set';  
		
			$response = json_encode($response);
			echo $response;
		}
	}
