<?php

    namespace classes\abstracts;

    use classes\helper\Log;

    abstract class ALoader {
        protected static $prefix = 'presslabs_default_prefix_';
        protected static $abs_path;
        protected static $abs_file;
        protected static $init_classes = array ();

        protected $logger = null;


        //TODO minimize stuff in this class move in a parent called LOADER
        public function __construct( $file, array $classes ) {
            //TODO add functionality to auto load the classes ^^^
            self::$abs_path = dirname( $file );
            self::$abs_file = $file;
            self::$init_classes = $classes;
        }


        public static function getPrefix() {
            return self::$prefix;
        }


        public static function setPrefix( $prefix ) {
            self::$prefix = $prefix;
        }


        public static function getAbsPath() {
            return self::$abs_path;
        }


        public static function setAbsPath( $abs_path ) {
            self::$abs_path = $abs_path;
        }


        public static function getAbsFile() {
            return self::$abs_file;
        }


        public static function setAbsFile( $abs_file ) {
            self::$abs_file = $abs_file;
        }


        public static function getInitClasses() {
            return self::$init_classes;
        }


        //TODO split Styles into a separate class


        public static function setInitClasses( $init_classes ) {
            self::$init_classes = $init_classes;
        }


        public function load() {
            $logPath = wp_upload_dir()[ 'basedir' ] . '/' . self::$prefix . 'log';

            Log::setPath( $logPath );
        }


        public abstract function activate();


        public abstract function deactivate();


        public abstract function textDomain();


        public abstract function crons( $schedules );


        public abstract function enqueueStyle();


        public abstract function enqueueScript();


        public abstract function enqueueAdminStyle();


        public abstract function enqueueAdminScript();
    }