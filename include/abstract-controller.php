<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Abstract_Controller {
	protected $plugin_name = 'hackers-democracy';
	
	function plugin_name(){
		return $this->plugin_name;
	}
	
	protected function url(){
		return get_option('home')."/de/ ";
	} 
}
?>