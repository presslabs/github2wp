<?php


//TODO check for nonce at each ajax request !!

add_action( 'wp_ajax_github2wp_set_branch', 'github2wp_ajax_set_branch' );
function github2wp_ajax_set_branch() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset( $_POST['id'] ) && isset( $_POST['branch'] ) ) {
		$resource = &$resource_list[ $_POST['id'] ];

		$git = new Github_2_WP( array(
			'user'         => $resource['username'],
			'repo'         => $resource['repo_name'],
			'access_token' => $default['access_token'],
			'source'       => $resource['repo_branch']
			)
		);

		$branches = $git->fetch_branches();
		$branch_set = false;

		if ( !empty($branches) ) {
			foreach ( $branches as $br ) {
				if ( $br !== $_POST['branch'] )
					continue;

				$resource['repo_branch'] = $br;

				$sw = github2wp_update_options( 'github2wp_options', $options );

				if ( $sw ) {
					$branch_set = true;
					$response['success'] = true;
					break;
				}
			}
		}

		if ( ! $branch_set )
			$response['error_messages'] = __( 'Branch not set', GITHUB2WP );

		header( 'Content-type: application/json' );
		echo json_encode( $response );
		die();
	}
}



add_action( 'wp_ajax_github2wp_downgrade', 'github2wp_ajax_downgrade' );
function github2wp_ajax_downgrade() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);


	if ( isset( $_POST['commit_id'] ) && isset( $_POST['res_id'] ) ) {
		$resource = $resource_list[ $_POST['res_id'] ];
		$type = github2wp_get_repo_type( $resource['resource_link'] );
		$version = $_POST['commit_id'];

		$args = array(
			'user'         => $resource['username'],
			'repo'         => $resource['repo_name'],
			'repo_type'    => $type,
			'access_token' => $default['access_token'],
			'source'       => $version
		);


		//Make sure wp knows there is an update even if it's a downgrade
		$version = substr( $version, 0, 7 );
		$res_slug = $resource['repo_name'] . (( 'plugin' === $type ) ? "/{$resource['repo_name']}.php" : '');
		$reverts = get_option('github2wp_reverts', array());
		$reverts[ $res_slug ]	= $version;
		update_option('github2wp_reverts', $reverts);


		$zipball_path = github2wp_generate_zipball_endpoint( $resource['repo_name'] );
		if ( github2wp_fetch_archive( $args ) ) {
			github2wp_update_resource( $zipball_path, $resource, 'update' );

			unset($reverts[ $res_slug ]);
			update_option('github2wp_reverts', $reverts );

			$response['success'] = true;
			$response['success_message'] = sprintf( __( 'The resource <b>%s<b> has been updated to %s .', GITHUB2WP ),
				$resource['repo_name'], $version );
		} else {
			$response['error_message'] = sprintf( __( 'The resource <b>%s<b> has FAILED to updated to %s .', GITHUB2WP ),
				$resource['repo_name'], $version );
		}

		header( 'Content-type: application/json' );
		echo json_encode( $response );
		die();
	}
}



add_action ( 'wp_ajax_github2wp_fetch_history', 'github2wp_ajax_fetch_history' );
function github2wp_ajax_fetch_history() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset ( $_POST['res_id'] ) ) {
		$resource = $resource_list[ $_POST['res_id'] ];

		$git = new Github_2_WP( array(
			'user'         => $resource['username'],
			'repo'         => $resource['repo_name'],
			'access_token' => $options['default']['access_token'],
			'source'       => $resource['repo_branch']
			)
		);

		$commit_history = $git->get_commits();

		header( 'Content-Type: text/html' );
		github2wp_render_resource_history( $resource['repo_name'], $_POST['res_id'], $commit_history );
		die();
	}
}
