<?php
    namespace github2wp\helper;

    final class Log {
        private static $instance = null;
        private static $path = null;


        private function __construct() { }


        public static function getInstance() {
            if ( !self::$instance )
                self::$instance = new Log();


            return self::$instance;
        }


        public static function write( $message, $to_serialize = false, $file = null, $line = null ) {
            if ( $to_serialize )
                $message = serialize( $message );

            $message = time() . ' - ' . $message;
            $message .= is_null( $file ) ? '' : " in $file";
            $message .= is_null( $line ) ? '' : " on line $line";
            $message .= "\n";

            return file_put_contents( self::$path, $message, FILE_APPEND );
        }


        public static function getPath() {
            return self::$path;
        }


        public static function setPath( $path ) { 
            self::$path = $path;
        }


        private function __clone() { }

    }
