<?php

    class Log {
        private static $instance = NULL;


        private function __construct() { }


        public static function getInstance() {
            if ( !self::$instance ) {
                self::$instance = new Log;
            }

            return self::$instance;
        }


        public function __set( $name, $value ) {
            switch ( $name ) {
                case 'logfile':
                    if ( !file_exists( $value ) || !is_writeable( $value ) ) {
                        throw new Exception( "$value is not a valid file path" );
                    }
                    $this->logfile = $value;
                    break;

                default:
                    throw new Exception( "$name cannot be set" );
            }
        }


        public function write( $message, $to_serialize = false, $file = null, $line = null ) {
            if ( $to_serialize )
                $message = serialize( $message );

            $message = time() . ' - ' . $message;
            $message .= is_null( $file ) ? '' : " in $file";
            $message .= is_null( $line ) ? '' : " on line $line";
            $message .= "\n";

            return file_put_contents( $this->logfile, $message, FILE_APPEND );
        }


        private function __clone() { }

    }