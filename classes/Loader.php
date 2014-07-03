<?php
namespace github2wp\classes;

use github2wp\classes\helper\Log;
use github2wp\classes\Cron;

class Loader {

    private $prefix = 'GH2WP_';
    private $abs_path;
    private $file;

    public static $logger = null;
    public Cron $cron = null;

    public function __construct( $file ) {
        $this->abs_path = dirname( $file );
        $this->file = $file;

        self::$logger = Log::getInstance();

        $logPath = wp_upload_dir()[ 'basedir' ] . '/' . $this->prefix . 'log';
	Log::setPath( $logPath );
    }

    public function load() {
        register_activation_hook( $this->file, array ( $this, 'activate' ) );
        register_deactivation_hook( $this->file, array ( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array ( $this, 'textDomain' ) );

        if ( is_admin() ) {
		//TODO null should be a GITUSER
		$this->cron = new Cron($this, null);
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
        load_plugin_textdomain( $this->prefix . 'textDomain', false,
            dirname( plugin_basename( $this->prefix ) ) . '/translations/' );
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

    public function getPrefix() {
        return $this->prefix;
    }

    public function setPrefix( $newPrefix ) {
        $this->prefix = $newPrefix;
    }

    public function getAbsPath() {
        return $this->abs_path;
    }

    public function getFile() {
        return $this->file;
    }
}
