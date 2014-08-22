<?php


add_filter( 'cron_schedules', 'github2wp_cron_add_6h' );
function github2wp_cron_add_6h( $schedules ) {
		$schedules['6h'] = array(
			'interval' => 21600,
			'display' => __( 'Once every 6 hours' , GITHUB2WP )
		);

	 return $schedules;
}



add_action( 'github2wp_cron_hook', 'github2wp_head_commit_cron' );
function github2wp_head_commit_cron() {
	$options = get_option( 'github2wp_options' );
	$default = &$options['default'];

	$resource_list = &$options['resource_list'];

	if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
		foreach ( $resource_list as $index => &$resource ) {
			$git = new Github_2_WP( $resource );
			$head = $git->get_head_commit();

			if ( $head )
				$resource['head_commit'] = $head;
		}
	}

	github2wp_update_options( 'github2wp_options', $options );
}



add_action( 'github2wp_cron_hook', 'github2wp_token_cron' );
function github2wp_token_cron() {
	$options = get_option( 'github2wp_options' );
	$default = &$options['default'];

	if ( isset( $default['access_token'] ) ) {
		if ( ! Github_2_WP::check_user( $default['access_token'] ) ) {
			$default['access_token'] = null;
			$default['client_id'] = null;
			$default['client_secret'] = null;
			$default['app_reset'] = 1;
			
			github2wp_update_options( 'github2wp_options', $options );
		}
	}
}
