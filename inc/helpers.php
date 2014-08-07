<?php



function github2wp_return_settings_link( $query_vars = '' ) {
	return admin_url( 'tools.php?page=' . GITHUB2WP_PLUGIN_BASENAME . $query_vars );
}



function github2wp_update_options( $where, $data ) {
	$data_array = array('option_value' => serialize( $data ) );
	$where_array = array( 'option_name' => $where );

	global $wpdb;
	$sw = $wpdb->update( $wpdb->prefix . 'options', $data_array, $where_array );

	if ( $sw ) {
	  $notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) && isset( $notoptions[ $where ] ) ) {
			unset( $notoptions[ $where ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		if ( ! defined( 'WP_INSTALLING' ) ) {
	  	$alloptions = wp_load_alloptions();
			
			if ( isset( $alloptions[ $where ] ) ) {
				$alloptions[ $where ] = $data_array['option_value'];
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			} else {
				wp_cache_set( $where, $data_array['option_value'], 'options' );
			}
		}
	}

	return $sw;
}



function github2wp_check_page_hook( $hook='', $prefix='', $string='' ) {
	if( $hook === $prefix.$string )
		return true;

	return false;
}



function github2wp_check_toolpage_hook( $hook='' ) {
	$plugin_parts = pathinfo( GITHUB2WP_PLUGIN_BASENAME );
	$plugin_hook_base = $plugin_parts['dirname'].'/'.$plugin_parts['filename'];

	if ( github2wp_check_page_hook( $hook, 'tools_page_', $plugin_hook_base ) )
		return true;

	return false;
}



function github2wp_enqueue_resource( $resource='', array $deps=array(), $activation ) {
	if ( '' === $resource )
		return;

	$path = GITHUB2WP_INC_PATH.$resource;
	$rel_path = basename(GITHUB2WP_INC_PATH).'/'.$resource;
	$url = plugins_url( $rel_path, GITHUB2WP_MAIN_PLUGIN_FILE );

	$resource_parts = pathinfo($resource);

	if ( !isset( $resource_parts['extension'] ) )
		return;

	switch( $resource_parts['extension'] ) {
		case 'css':
			wp_enqueue_style( esc_attr($resource), $url, $deps, filemtime( $path ), $activation );
			break;
		case 'js':
			wp_enqueue_script( esc_attr($resource), $url, $deps, filemtime( $path ), $activation );
			break;
		default:
			return;
	}
}



function github2wp_str_between( $start, $end, $content ) {
	$r = explode( $start, $content );

	if ( isset( $r[1] ) ) {
		$r = explode( $end, $r[1] );
		return $r[0];
	}

	return '';
}



function github2wp_get_repo_name_from_hash( $hash ) {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];
	foreach ( $resource_list as $res ) {
		$repo_name = $res['repo_name'];

		if ( $repo_name == $hash || wp_hash( $repo_name ) == $hash )
			return $repo_name;
	}

	return $repo_name;
}



function github2wp_pluginFile_hashed( $hash ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	require_once( ABSPATH . '/wp-includes/pluggable.php' );

	$allPlugins = get_plugins();
	
	foreach( $allPlugins as $plugin_index => $plugin_value ) {
		$pluginFile = $plugin_index;
		$repo_name = substr( basename( $plugin_index ), 0, -4 );

		if ( $repo_name == $hash || $pluginFile == $hash || wp_hash( $repo_name ) == $hash )
			return $pluginFile;
	}

	return $hash;
}



function github2wp_get_repo_type( $resource_link ) {
	return github2wp_str_between( 'wp-content/', 's/', $resource_link );
}



function github2wp_rmdir( $dir ) {
	if ( ! file_exists( $dir ) )
		return true;

	if ( ! is_dir( $dir ) || is_link( $dir ) )
		return unlink( $dir );

	foreach ( scandir( $dir ) as $item ) {
		if ( '.' == $item || '..' == $item )
			continue;

		if ( ! github2wp_rmdir( $dir . '/' . $item ) ) {
			chmod( $dir . '/' . $item, 0777 );
			if ( ! github2wp_rmdir( $dir . '/' . $item ) )
				return false;
		}
	}

	return rmdir( $dir );
}



function github2wp_cleanup( $file ) {
	if ( file_exists( $file ) ) {
		return unlink( $file );
	}
}
