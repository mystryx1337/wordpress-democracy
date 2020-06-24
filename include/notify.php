<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Notify extends Democracy_Abstract_Controller {
	private $headers = array();
	
	private $db;
	
	function __construct(){
		$headers[0] = 'From: Integreat <'.get_option( 'admin_email' ).'>';
		$this->db = new Democracy_Notify_Database();
		
		$this->db->get_all_publishers();
	}
	
	function mail_all_publisher($subject,$message){
		$publishers = $this->db->get_all_publishers();
		foreach($publishers as $publisher){
			$this->mail(
				$publisher->data->user_email,
				$subject,
				$message
			);
		}
	}
	
	function mail_all_users($subject,$message){
		$users = get_users();
		foreach($users as $user){
			if($this->db->get_user_option($this->plugin_name.'_notify',$user->ID) == 'yes'){
				/* TODO */
				/*$this->mail(
					$publisher->data->user_email,
					$subject,
					$message
				);*/
			}
		}
	}

	function mail($user_mail, $subject, $message){
		#return wp_mail( $user_mail, $subject, $message, $this->headers );
	}
}

class Democracy_Notify_Database extends Democracy_Database {
	function get_all_publishers(){
		$notify_roles = get_option( $this->plugin_name.'_user_notify_roles' );
		$qry = array(
			'role__in' => $notify_roles
		);
		
		return get_users ( $qry );
	}
}
?>