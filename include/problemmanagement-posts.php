<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Problemmanagement_Posts extends Democracy_Abstract_Controller {
	private $db;
	private $gui;
	
	private $mail_sent_option = 'democracy_publisher_mail_sent';
	
	function __construct(){
		$this->db = new Democracy_Problemmanagement_Posts_Database();
		$this->gui = new Democracy_Problemmanagement_Posts_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}

	function ajax_reject_post(){
		$latest_revision_id = $this->db->get_latest_revision($_POST['post_id']);
		$post_obj = get_post($latest_revision_id);
		$user = get_user_by('id', $post_obj->post_author);
		
		$this->db->rejected_post($post_obj,wp_get_current_user(),$_POST['reason']);
		$this->msg_reject_post($user,$_POST['reason']);
		
		echo esc_html__( 'Der Beitrag wurde aus folgendem Grund abgelehnt: ', $this->plugin_name );
		echo $_POST['reason'];
		
		wp_die();
	}
	
	function notify_publishers(){
		global $post;
		$latest_revision_id = $this->db->get_latest_revision($post->ID);
		
		if(
			!Democracy_Singleton::get_service()->current_user_is_publisher()
			&& strpos($post->post_name,'autosave') === false
			&& $this->db->get_post_option($this->mail_sent_option,$latest_revision_id) != 'yes'
		){
			$subject = get_option('blogname') . ": ".__( 'Neue Beitragsänderung', $this->plugin_name );
			$message = __( 'Besuchen Sie', $this->plugin_name )
				. " " . $this->url()
				. __( 'um die neue Beitragsänderung zu überprüfen.', $this->plugin_name );
			Democracy_Singleton::get_notify()->mail_all_publisher($subject,$message);
			$this->db->set_post_option($this->mail_sent_option,'yes',$latest_revision_id);
		}
		elseif(Democracy_Singleton::get_service()->current_user_is_publisher()){
			$this->db->set_post_option($this->mail_sent_option,'no',$latest_revision_id);
		}
	}
	
	private function msg_reject_post($user_object, $message){
		Democracy_Singleton::get_notify()->mail(
			$user_object->data->user_email,
			esc_html__( 'Integreat: Ihr Beitrag wurde abgelehnt', $this->plugin_name ), //subject
			esc_html__( 'Ihr Beitrag wurde aus folgendem Grund abgelehnt: ', $this->plugin_name ).$message
		);
	}
}
$democracy_problemmanagement_posts = new Democracy_Problemmanagement_Posts();

class Democracy_Problemmanagement_Posts_GUI extends Democracy_Abstract_GUI {
	function reject_form(){
		global $post;

		$ig_revision_id = get_post_meta( $post->ID, 'ig_revision_id', true );
		
		if(
			current_user_can( 'publish_post', $post->ID )
			&& $ig_revision_id != '-1'
		){
			$latest_revision_id = $this->db->get_latest_revision($post->ID);
			$rejected_post = $this->db->get_rejected_post($latest_revision_id);

			$data = [
				'postId'		=> $post->ID,
				'button'		=> get_submit_button(esc_html__('Ablehnen', $this->plugin_name)),
				'message'		=> esc_html__('Grund', $this->plugin_name),
				
				'reason'		=> @$rejected_post['reason'],
				'rejected_title'=> esc_html__('Der Beitrag wurde bereits abgelehnt:', $this->plugin_name),
			];
			
			if($rejected_post){
				echo Democracy_Singleton::get_service()->use_template('problemmanagement-posts-rejected',$data);
			}
			else{
				echo Democracy_Singleton::get_service()->use_template('problemmanagement-posts-form',$data);
			}
		}
	}
	function change_publish_button( $translation, $text ) {
		if ( $text == 'Update' && !Democracy_Singleton::get_service()->current_user_is_publisher()){
			return __( 'Speichern und zur Überprüfung einreichen', $this->plugin_name );
		}
		return $translation;
	}
}

class Democracy_Problemmanagement_Posts_Database extends Democracy_Database {
	function rejected_post($post_obj,$rejector_obj,$reason_text){
		$this->wpdb->insert(
			$this->wpdb->prefix.'democracy_rejected_posts', //table
			array( //data
				'post'		=> $post_obj->ID,
				'rejector'	=> $rejector_obj->data->ID,
				'reason'	=> $reason_text
			),
			array( //format
				'%d',
				'%d',
				'%s'
			)
		);
	}
	
	function get_rejected_post($post_id){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix.'democracy_rejected_posts')." WHERE post = ".$post_id.";";
		$result = $this->wpdb->get_row( $qry, ARRAY_A );
		if(@sizeof($result) > 0){ return $result; }
		return false;
	}
}

add_action( 'post_submitbox_start', array($democracy_problemmanagement_posts->get_gui(), 'reject_form' ) );
add_action( 'wp_ajax_democracy_reject_post', array($democracy_problemmanagement_posts, 'ajax_reject_post' ) );
add_action( 'edit_post', array($democracy_problemmanagement_posts, 'notify_publishers' ) );

add_filter( 'gettext', array($democracy_problemmanagement_posts->get_gui(), 'change_publish_button' ), 10, 2 );
?>