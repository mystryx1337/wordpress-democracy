<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Abstract_GUI {
	protected $plugin_name = 'hackers-democracy';
	protected $capability = 'edit_pages';
	protected $capability_priv = 'manage_options';
	protected $menu_slug  = 'hackers-democracy';
	protected $page_slug  = 'hackers-democracy-group';
	
	protected $db;
	protected $controller;
	
	function __construct($db = null, $controller = null){
		$this->db = $db;
		$this->controller = $controller;
	}
	
	protected function input_select($param, $data /* array( key => array( 0 => value, 1 => name ) )*/){
		$return = "<select name='".$param['name']."'";
		if(@$param['class'] != "") { $return .= " class='".$param['class']."'"; }
		$return .= ">";
		foreach($data as $key => $val){
			$return .= "<option value='".$val[0]."'";
			if($val[0] == $param['selected']){$return .= " selected"; }
			$return .= ">".$val[1]."</option>";
		}
		$return .= "</select>";
		
		return $return;
	}
	
	protected function input_int($param){
		$return = "<input name='".$param['name']."' type='number' step='1' min='0' max='100' value='".$param['value']."' />";
		
		return $return;
	}
	
	protected function info($s){
		return "<span class='democracy_info_box'><a href='#'>&#128712;</a><span>".$s."</span></span>";
	}
}

class Democracy_Abstract_GUI_Options extends Democracy_Abstract_GUI {
	protected $page_title = 'Einstellungen';
	
	protected function get_settings_fields( $option_group ) {
		ob_start();
		settings_fields( $option_group );
		$return = ob_get_contents();
		ob_end_clean();
		return $return;
	}
	
	protected function get_settings_sections( $page ) {
		ob_start();
		do_settings_sections( $page );
		$return = ob_get_contents();
		ob_end_clean();
		return $return;
	}
}
?>