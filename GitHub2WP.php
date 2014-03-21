<?php

class GitHub2WP {
    const PLUGIN_NAME = 'github2wp';
    const PLUGIN_WP_IDENTIFIER = 'github2wp/ini.php';
    const PLUGIN_PREFIX = 'gh2w_';

    public function __construct() {
        $this->init();
    }

    private function init() {
        register_activation_hook( __FILE__, 'activate' );
        register_deactivation_hook( __FILE__, 'deactivate' );
        add_option( GitHub2WP::PLUGIN_PREFIX . '_options', array() );
        add_action( 'uninstall_' . plugin_basename( __FILE__ ), array( $this, 'uninstall' ) );
    }


    private function activate() {
        wp_schedule_event( current_time ( 'timestamp' ), '6h', GitHub2WP::PLUGIN_PREFIX . 'cron_hook' );
    }



    private function deactivate() {
        wp_clear_scheduled_hook( GitHub2WP::PLUGIN_PREFIX . 'cron_hook' );
    }



    private function uninstall() {
        $this->delete_options();
        //delete_transient( GitHub2WP::PLUGIN_PREFIX . 'branches' );
    }

    private function delete_options() {
    }

} 