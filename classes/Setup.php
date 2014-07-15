<?php

namespace github2wp;


class Setup {
	private $initiator = null;


	public function __construct( Loader $loader ) {
		$this->initiator = $loader;
	}


	public function deactivate() {
		wp_clear_scheduled_hook( $this->initiator->getPrefix() . 'cron_hook' );
	}


	public function activate() {
		$this->checkOptions();
		wp_schedule_event( current_time( 'timestamp' ), '6h', $this->initiator->getPrefix() . 'cron_hook' );
	}


	private function checkOptions() {
		$optionName = $this->initiator->getPrefix() . 'options';

		$options = array(
			'user' => array(
				'connected_username' => '',
				'connection_type'    => 'token',
				'client_id'          => '',
				'client_secret'      => '',
				'master_branch'      => 'master',
				'token'              => '',
				'needs_refresh'      => false,
				'app_reset'          => false
			),
			'repos' => array()
		);

		if ( !get_option( $optionName ) ) {
			add_option( $optionName, $options );
		}	else {
			$old_op = get_option( $optionName );
			$options = wp_parse_args( $old_op, $options );

			update_option( $optionName, $options );
		}
	}


	public function optionReset() {
		$optionName = $this->initiator->getPrefix() . 'options';

		delete_option( $optionName );
		$this->checkOptions();
	}
}
