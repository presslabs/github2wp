<?php

namespace git2wp;

use git2wp\helper\Log;
use git2wp\git\User;

class Loader {
	private $prefix = 'GH2WP_';

	public $plugin_FILE;

	public function __construct( $prefix='GH2WP_', $file ) {
		$this->plugin_FILE = $file;
		$this->prefix = $prefix;
	}

	public function load() {
		register_activation_hook( $this->file, array ( $this, 'activate' ) );
		register_deactivation_hook( $this->file, array ( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array ( $this, 'textDomain' ) );

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

	public function prefix( $data='' ) {
		//TODO validate and compose return of prefix + string;
		return '';	
	}
}
