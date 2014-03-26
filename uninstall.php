<?php
    namespace github2wp;

    if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
        exit();

    //TODO change this whenever the LOADER prefix changes
    //TODO find a more elegant solution to the prefix in uninstall.php
    $prefix = 'GH2WP_';

    $option_name = $prefix . 'options';

    // For Single site
    if ( !is_multisite() ) {
        delete_option( $option_name );
    } else {
        // For regular options.
        global $wpdb;
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        $original_blog_id = get_current_blog_id();
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            delete_option( $option_name );
        }
        switch_to_blog( $original_blog_id );

        // For site options.
        delete_site_option( $option_name );
    }

    $logPath = wp_upload_dir()['basedir'] . '/' . $prefix . 'log';
    if ( file_exists( $logPath ) )
        unlink( $logPath );

    delete_transient( $prefix . 'branches' );