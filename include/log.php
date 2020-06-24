<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Log extends Democracy_Abstract_Controller {
	private $logfile = '../democracy.log';
	
	function __construct(){
	}
	
	function print_r($arr){
		$results = print_r($arr, true);
		$this->append_to_log($results);
	}
	
	function print($s){
		$this->append_to_log($s."\n");
	}
	
	function clear(){
		file_put_contents ( $this->logfile, "" );
	}
	
	private function append_to_log($data,$js_console = false){
		file_put_contents ( $this->logfile, date('Y-m-d H:i:s')." ".$data, FILE_APPEND );
		if($js_console){ echo "<script>console.log('".$data."');</script>"; }
	}
}
/*
Democracy_Singleton::get_log()->print_r();
*/
?>