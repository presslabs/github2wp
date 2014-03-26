<?php
    namespace classes;

    use classes\abstracts\ALoader;


    class Loader extends ALoader {

        public function __construct( $file, array $classes ) {
            parent::__construct( $file, $classes );

            self::$prefix = 'GH2WP_';
        }


        public function load() {
            parent::load();

            register_activation_hook( self::$abs_file, array ( $this, 'activate' ) );
            register_deactivation_hook( self::$abs_file, array ( $this, 'deactivate' ) );

            add_action( 'plugins_loaded', array ( $this, 'textDomain' ) );
            add_filter( 'cron_schedules', array ( $this, 'crons' ) );


            if ( is_admin() ) {

            } else {
                //TODO add enqueue methods classes, to be seen
                add_action( 'wp_enqueue_style', array ( $this, 'enqueueStyle' ) );
                add_action( 'wp_enqueue_script', array ( $this, 'enqueueScript' ) );
            }
        }


        public function activate() {
            //TODO pass down this reference to the other methods using observer pattern
            $setup = new Setup( $this );
            $setup->activate();
        }


        public function deactivate() {
            $setup = new Setup( $this );
            $setup->deactivate();
        }


        //TODO consider moving translation stuff in separate class
        public function textDomain() {
            load_plugin_textdomain( self::$prefix . 'textDomain', false,
                dirname( plugin_basename( self::$prefix ) ) . '/translations/' );
        }


        public function crons( $schedules ) {
            $schedules[ '6h' ] = array ( 'interval' => 21600, 'display' => __( 'Once every 6h' ),
                //TODO make translation compatible <===
            );

            return $schedules;
        }


        public function enqueueStyle() {
            // TODO: Implement enqueueStyle() method.
        }


        public function enqueueScript() {
            // TODO: Implement enqueueScript() method.
        }


        public function enqueueAdminStyle() {
            // TODO: Implement enqueueAdminStyle() method.
        }


        public function enqueueAdminScript() {
            // TODO: Implement enqueueAdminScript() method.
        }
    }