<?php
class Democracy_Singleton extends Democracy_Abstract_Controller {
	private static $log;
	private static $service;
	private static $scripts;
	
	private static $notify;
	private static $database;
	
	public static function init(){
		SELF::$log = new Democracy_Log();
			
		SELF::$service = new Democracy_Service_Functions();
			SELF::$service->only_publish_if_community_is_initiated();
		
		SELF::$scripts = new Democracy_Include_Scripts();
			add_action( 'admin_enqueue_scripts', array(SELF::$scripts, 'all' ) );
		
		SELF::$notify = new Democracy_Notify();
		
		SELF::$database = new Democracy_Database();
			SELF::$database->upgrade();
			
		
	}
	
	public static function get_service(){
		return SELF::$service;
	}
	
	public static function get_database(){
		return SELF::$database;
	}
	
	public static function get_notify(){
		return SELF::$notify;
	}
	
	public static function get_log(){
		return SELF::$log;
	}
}
Democracy_Singleton::init();

//Democracy_Singleton::get_service()
//Democracy_Singleton::get_database()
//Democracy_Singleton::get_notify()
//Democracy_Singleton::get_log()
?>