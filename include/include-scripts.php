<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Include_Scripts extends Democracy_Abstract_Controller {
	function all(){
		wp_register_style( $this->plugin_name, plugin_dir_url( __DIR__ ) . 'css/style.css' );
		wp_enqueue_style( $this->plugin_name );

		wp_register_script( $this->plugin_name, plugin_dir_url(__DIR__) . 'js/functions.js', array('jquery'),'0.1', true);
		wp_enqueue_script( $this->plugin_name );
	}
}
?>