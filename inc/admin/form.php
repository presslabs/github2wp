<?php



function github2wp_submit_resource( array $options ) {
	$initial_options = $options;
	$resource_list = &$options['resource_list'];

	$repo_link = $_POST['resource_link'];
	$repo_branch = $_POST['master_branch'];

	if ( '' == $repo_branch )
		$repo_branch = $options['default']['master_branch'];


	$repo_link = trim( $repo_link );
	if ( !$repo_link ) {
		add_settings_error( 'github2wp_settings_errors', 'no_endpoint', 
			__( 'No enpoint provided', GITHUB2WP),
			'error' );

		return $initial_options;
	}


	$data = Github_2_WP::get_data_from_git_clone_link( $repo_link );
	if( !isset($data['user']) || !isset($data['repo']) ) {
		add_settings_error( 'github2wp_settings_errors', 'not_git_link', 
			__( 'This isn\'t a git link! eg: https://github.com/WordPress/WordPress.git', GITHUB2WP), 
			'error' );

		return $initial_options;
	}

	$resource_owner = $data['user'];
	$resource_repo_name = $data['repo'];


	$unique = true;
	if ( is_array( $resource_list ) && ! empty( $resource_list ) )
		foreach ( $resource_list as $resource ) {
			if ( $resource['repo_name'] === $resource_repo_name ) {
				$unique = false;
				break;
			}
	}

	if ( !$unique ) {
		add_settings_error( 'github2wp_settings_errors', 'duplicate_endpoint', 
			__( 'Duplicate resources! Repositories can\'t be both themes and plugins ', GITHUB2WP),
			'error' );

		return $initial_options;
	}


	$link = home_url() . "/wp-content/{$_POST['resource_type_dropdown']}/$resource_repo_name";
	$args = array(
		'username'      => $resource_owner,
		'repo_name'     => $resource_repo_name,
		'resource_link' => $link
	);

	$git = new Github_2_WP( $args, $repo_branch );
	$sw = $git->check_repo_availability();

	if ( !$sw )
		return $initial_options;


	$on_wp = Github_2_WP::check_svn_avail( $resource_repo_name, substr( $_POST['resource_type_dropdown'], 0, -1 ) );
	$head = $git->get_head_commit();

	$resource_list[] = array(
		'resource_link' => $link,
		'repo_name'     => $resource_repo_name,
		'repo_branch'   => $repo_branch,
		'username'      => $resource_owner,
		'is_on_wp_svn'  => $on_wp,
		'head_commit'   => $head
	);

	add_settings_error(	'github2wp_settings_errors', 'repo_connected',
		__( 'Connection was established.', GITHUB2WP ),	'updated'	);

	delete_transient( 'github2wp_branches' );

	return $options;
}



function github2wp_submit_settings( array $options ) {
	$default = &$options['default'];
	$client_id = $default['client_id'];
	$client_secret = $default['client_secret'];

	if ( isset($_POST['master_branch']) )
		$master_branch = trim( $_POST['master_branch'] );
	else
		$master_branch = 'master';

	if ( isset( $_POST['client_id'] ) ) {
		if ( $_POST['client_id'] != $default['client_id'] ) {
			$client_id = trim($_POST['client_id']);
			$changed = 1;
		}
	}

	if ( isset( $_POST['client_secret'] ) ) {
		if ( $_POST['client_secret'] != $default['client_secret'] ) {
			$client_secret = trim($_POST['client_secret']);
			$changed = 1;
		}
	}

	if ( $master_branch && $client_id && $client_secret )
		$default['app_reset']=0;

	$default['master_branch'] = $master_branch;
	$default['client_id'] = $client_id;
	$default['client_secret'] = $client_secret;

	if ( $changed ) {
		$default['access_token'] = NULL;
		$default['changed'] = $changed;
	}

	return $options;
}



function github2wp_process_resource_request( array $options ) {
	$resource_list = &$options['resource_list'];

	if ( !is_array( $resource_list ) || empty( $resource_list ) )
		return $options;


	$k = 0;
	foreach ( $resource_list as $key => $resource ) {
		$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash($resource['repo_name']).'.zip';

		if ( isset( $_POST[ 'submit_install_resource_' . $k ] ) ) {

			if ( github2wp_fetch_archive($resource) )
				github2wp_update_resource( $zipball_path, $resource );

		}	else if ( isset( $_POST[ 'submit_update_resource_' . $k ] ) ) {

			if ( github2wp_fetch_archive($resource, $resource['head_commit']) )
				github2wp_update_resource( $zipball_path, $resource, 'update' );

		} else if ( isset( $_POST[ 'submit_delete_resource_' . $k ] ) ) {
			unset( $resource_list[ $key ] );
		}

		$k++;
	}

	return $options;
}
