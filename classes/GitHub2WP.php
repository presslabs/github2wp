<?php

class GitHub2WP {
    const NAME = 'github2wp';
    const WP_IDENTIFIER = 'github2wp/ini.php';
    const WP_BASE_IDENTIFIER = 'ini.php';
    const PREFIX = 'gh2w_';


    public function __construct() {
        $this->init();
    }


    private function init() {
        //TODO find what goes wrong here
        /*
        register_activation_hook( GitHub2WP::WP_IDENTIFIER, array( $this, 'install' ) );
        register_deactivation_hook( GitHub2WP::WP_IDENTIFIER, array( $this, 'deactivate' ) );
        register_uninstall_hook( GitHub2WP::WP_IDENTIFIER, array( $this, 'uninstall' ) );
        //add_action( 'uninstall_' . GitHub2WP::WP_BASE_IDENTIFIER, array( $this, 'uninstall' ) );
        add_option( GitHub2WP::PREFIX . '_options', array() );
        */
    }


    private function install() {
        wp_schedule_event( current_time ( 'timestamp' ), '6h', GitHub2WP::PREFIX . 'cron_hook' );
    }


    private function deactivate() {
        wp_clear_scheduled_hook( GitHub2WP::PREFIX . 'cron_hook' );
    }



    private function uninstall() {
        $this->delete_options();
        delete_transient( GitHub2WP::PREFIX . 'branches' );
    }


    private function delete_options() {
        delete_option( GitHub2WP::PREFIX . '_options' );
    }

} 