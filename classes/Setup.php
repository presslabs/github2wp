<?php

    namespace github2wp\classes;

    class Setup {
        private $initiator = null;


				public function __construct() {
					global $github2wp;

					$this->initiator = $github2wp;
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

            //the default options
            //TODO complete with already used opts
            $options = array ();

            if ( !get_option( $optionName ) ) {
                add_option( $optionName, $options );
            }
            else {
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
