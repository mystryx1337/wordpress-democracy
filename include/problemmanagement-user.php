<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Problemmanagement_User extends Democracy_Abstract_Controller {
	private $page_slug  = 'hackers-democracy-group';
	
	private $db;
	private $gui;
	
	function __construct(){
		$this->db = new Democracy_Problemmanagement_User_Database();
		$this->gui = new Democracy_Problemmanagement_User_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function ajax_invite_user(){
		if(
			$this->db->invitable_user_exists($_POST['username'])
			&& $_POST['username'] != ''
			&& Democracy_Singleton::get_service()->current_user_is_publisher()
		){
			if($_POST['democracy_reject_user_form_textarea'] != ''){
				$this->reject_user($_POST['username'],$_POST['democracy_reject_user_form_textarea']);
			}
			else{
				$this->invite_user($_POST['username']);
			}
		}
		
		elseif($_POST['username'] != ''){
			$this->suggest_user(
				$_POST['username'],
				$_POST['email'],
				[
					'first_name'=> $_POST['meta_first_name'],
					'last_name'	=> $_POST['meta_last_name'],
					'comment'	=> $_POST['meta_comment']
				]
			);
		}
		wp_die();
	}
	
	function user_invite_email ($content, $user_login, $user_email, $key) {
		$msg = "Hallo,
du wurdest eingeladen, '".get_option('blogname')."' auf
".get_option('home')."/de/ in der Rolle als Organisation beizutreten.
Wenn du dieser Website nicht beitreten willst, ignoriere bitte
diese E-Mail. Diese Einladung wird in wenigen Tagen verfallen.

Bitte klicke auf den folgenden Link, um dein Benutzerkonto zu aktivieren:
".get_option('home')."/wp-activate.php?key=".$key;
		return $msg;
	}
	
	function user_invite_email_subject ($subject) {
		return $subject;
	}

	private function invite_user($user_login){
		$invitable_user = $this->db->get_invitable_user($user_login);
		
		$user_id = email_exists($invitable_user['user_email']);
		is_user_member_of_blog( $user_id );
		
		if($user_id){
			add_user_to_blog(
				get_current_blog_id(),
				$user_id,
				$invitable_user['user_role']
			);
			echo esc_html__('Bestehender Benutzer wurde hinzugefügt.', $this->plugin_name);
		}
		else{
			wpmu_signup_user(
				$invitable_user['user_login'],
				$invitable_user['user_email'],
				[
					'add_to_blog'	=> get_current_blog_id(),
					'new_role'		=> $invitable_user['user_role'],
					'first_name'	=> $invitable_user['user_meta']['first_name'],
					'last_name'		=> $invitable_user['user_meta']['last_name']
				]
			);
			echo esc_html__('Benutzer wurde eingeladen.', $this->plugin_name);
		}
		$this->db->delete_invitable_user($user_login);
		echo ";--;";
		$this->gui->show_all_unchecked_invitations_widget();
		$this->gui->echo_output();
	}
	
	private function reject_user($user_login,$reason){
		$result = $this->db->reject_invitable_user($user_login,$reason);
		if( !is_wp_error( $result ) ) {
			echo esc_html__('Benutzer wurde erfolgreich abgelehnt.', $this->plugin_name);
			echo ";--;";
			$this->gui->show_all_unchecked_invitations_widget();
			$this->gui->echo_output();
			echo ";--;";
			$this->gui->show_all_rejected_invitations();
			
		}
		else { echo esc_html__('Es ist ein Fehler aufgetreten.', $this->plugin_name); }
	}
	
	private function suggest_user($user_login, $user_email, $user_meta){
		$standard_role = esc_attr( get_option($this->plugin_name.'_user_standard_role') );
		
		$user_exists = $this->db->user_exists($user_login, $user_email);
		if( $user_exists && $user_exists != 'wpmu' ){
			echo esc_html__('Benutzername oder eMail-Adresse ist bereits vorhanden.', $this->plugin_name);
		}
		else{
			$this->db->add_invitable_user($user_login, $user_email, $standard_role, $user_meta);
			echo esc_html__('Benutzer wurde erfolgreich vorgeschlagen. Der Vorschlag muss noch überprüft werden!', $this->plugin_name);
			echo ";--;";
			$this->gui->show_all_unchecked_invitations_widget();
			$this->gui->echo_output();
		}
	}
	
	private function notify_publishers(){
		$subject = get_option('blogname') . ": ".__( 'Neuer Benutzer vorgeschlagen', $this->plugin_name );
		$message = __( 'Besuchen Sie', $this->plugin_name )
			. " " . get_option('home')."/de/ "
			. __( 'um den neuen Benutzervorschlag zu überprüfen.', $this->plugin_name );
		Democracy_Singleton::get_notify()->mail_all_publisher($subject,$message);
	}
}

class Democracy_Problemmanagement_User_GUI extends Democracy_Abstract_GUI {
	private $page_title = 'Neuen Benutzer einladen';
	private $menu_title = 'Neuen Benutzer einladen';
	private $submenu_slug = 'democracy_problemmanagement_user';
	
	private $output = "";
	
	function add_menu_invite_user(){
		add_submenu_page(
			'users.php', //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'invite_user')
		);
		add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'invite_user')
		);
	}

	function invite_user(){
		$username = Democracy_Singleton::get_service()->base64url_decode(@$_GET['user']);
		if(
			$this->db->invitable_user_exists($username)
			&& $username != ''
			&& Democracy_Singleton::get_service()->current_user_is_publisher()
		){
			$this->check_user_by_publisher($username);
		}
		elseif($username == ''){
			$this->suggest_user_by_editor();
		}
		
		echo "<h3>".__( 'Ausstehende Benutzereinladungen', $this->plugin_name )."</h3>";
		echo "<div id='ajax_feedback_1'>";
			$this->show_all_unchecked_invitations_widget();
			if($this->output != ""){
				$this->echo_output();
			}
			else{
				echo __( 'keine ausstehenden Benutzereinladungen', $this->plugin_name );
			}
		echo "</div>";
		
		echo "<h3>".__( 'Abgelehnte Benutzer', $this->plugin_name )."</h3>";
		echo "<div id='ajax_feedback_2'>";
		$this->show_all_rejected_invitations();
		echo "</div>";
	}
	
	function show_all_unchecked_invitations(){
		$this->show_all_unchecked_invitations_widget();
		
		if($this->output != ""){
			wp_add_dashboard_widget(
				$this->plugin_name."_unchecked_invitations",
				__( 'Ausstehende Benutzereinladungen', $this->plugin_name ),
				[ $this, 'echo_output' ]
			);
		}
	}
	
	function echo_output(){
		echo $this->output;
	}
	
	function show_all_unchecked_invitations_widget(){
		$invitable_users = $this->db->get_invitable_users();
		
		foreach($invitable_users as $user){
			if(Democracy_Singleton::get_service()->current_user_is_publisher()){
				$this->output .= "<a href='./users.php?page=".$this->submenu_slug."&user=".Democracy_Singleton::get_service()->base64url_encode($user['user_login'])."'>";
			}
			$this->output .= $user['user_login'];
			if(Democracy_Singleton::get_service()->current_user_is_publisher()){ $this->output .= "</a>"; }
			$this->output .= "<br />";
		}
	}
	
	function show_all_rejected_invitations(){
		$rejected_users = $this->db->get_rejected_users();
		
		echo "<table class='democracy_rejected_user'>";
		echo "<tr class='b'>"
				."<td>".__( 'Benutzer', $this->plugin_name )."</td>"
				."<td>".__( 'Ablehnender', $this->plugin_name )."</td>"
				."<td>".__( 'Grund', $this->plugin_name )."</td></tr>";
		foreach($rejected_users as $user){
			echo "<tr><td>";
			
			if(Democracy_Singleton::get_service()->current_user_is_publisher()){
				echo "<a href='./users.php?page=".$this->plugin_name."&user=".Democracy_Singleton::get_service()->base64url_encode($user['user_login'])."'>";
			}
			else {
				echo "<a href='#'>";
			}
			
			$rejector = get_user_by('ID',$user['rejector'])->user_nicename;
			echo $user['user_login'] . "</a></td>"
				. "<td>" . $rejector . "</td><td>" . $user['reason'] . "</td>"
				. "</tr>";
		}
		echo "</table>";
	}

	private function suggest_user_by_editor(){
		$standard_role = esc_attr( get_option($this->plugin_name.'_user_standard_role') );
		$data = [
			'ajax_id'		=> 'democracy_invite_user',
			'page_title'	=> $this->page_title,
			'readonly'		=> '',
			'label_username'=> esc_html__('Neuer Benutzername', $this->plugin_name),
			'username'		=> '',
			'placeholder_username'=> 'Muster: mmustermann',
			'label_email'	=> esc_html__('eMail', $this->plugin_name),
			'email'			=> '',
			
			'label_meta_first_name'	=> esc_html__('Vorname', $this->plugin_name),
			'meta_first_name'		=> '',
			'label_meta_last_name'	=> esc_html__('Nachname', $this->plugin_name),
			'meta_last_name'		=> '',
			'label_meta_comment'	=> esc_html__('Kommentar', $this->plugin_name),
			'meta_comment'			=> '',
			
			'label_role'	=> esc_html__('Benutzerrolle', $this->plugin_name),
			'role'			=> Democracy_Singleton::get_service()->get_role_name( $standard_role ),
			'submit'		=> get_submit_button( esc_html__('Einladung vorschlagen', $this->plugin_name) ),
			'reject'		=> ''
		];
		
		echo Democracy_Singleton::get_service()->use_template('problemmanagement-users-invite-user',$data);
	}
	
	private function check_user_by_publisher($username){
		$standard_role = esc_attr( get_option($this->plugin_name.'_user_standard_role') );
		
		$user_arr = $this->db->get_invitable_user($username);
		
		$data = [
			'ajax_id'		=> 'democracy_invite_user',
			'page_title'	=> $this->page_title,
			'readonly'		=> 'readonly',
			'label_username'=> esc_html__('Neuer Benutzername', $this->plugin_name),
			'username'		=> $user_arr['user_login'],
			'placeholder_username'=> __('Muster: mmustermann', $this->plugin_name),
			'label_email'	=> esc_html__('eMail', $this->plugin_name),
			'email'			=> $user_arr['user_email'],
			
			'label_meta_first_name'	=> esc_html__('Vorname', $this->plugin_name),
			'meta_first_name'		=> $user_arr['user_meta']['first_name'],
			'label_meta_last_name'	=> esc_html__('Nachname', $this->plugin_name),
			'meta_last_name'		=> $user_arr['user_meta']['last_name'],
			'label_meta_comment'	=> esc_html__('Kommentar', $this->plugin_name),
			'meta_comment'			=> $user_arr['user_meta']['comment'],
			
			'label_role'	=> esc_html__('Benutzerrolle', $this->plugin_name),
			'role'			=> Democracy_Singleton::get_service()->get_role_name( $standard_role ),
			'submit'		=> get_submit_button( esc_html__('Einladung versenden', $this->plugin_name), 'primary large keep_disabled' ),
			'reject'		=> get_submit_button(
									esc_html__('Einladung ablehnen', $this->plugin_name),
									'primary large keep_disabled',
									'democracy_reject_user_form_send',
									true,
									[ 'id' => 'democracy_reject_user_form_send' ]
								),
			'reason_placeh'	=> esc_html__('Grund', $this->plugin_name)
		];
		
		echo Democracy_Singleton::get_service()->use_template('problemmanagement-users-invite-user',$data);
	}
}

class Democracy_Problemmanagement_User_Database extends Democracy_Database {
	private $table = 'democracy_invitable_users';
	
	function add_invitable_user($user_login, $user_email, $user_role, $user_meta){
		$result = $this->wpdb->insert(
			$this->wpdb->prefix.$this->table, //table
			[ //data
				'user_login'	=> $user_login,
				'user_email'	=> $user_email,
				'user_role'		=> $user_role,
				'user_meta'		=> maybe_serialize($user_meta)
			]
		);
		return $result;
	}
	
	function delete_invitable_user($user_login){
		$result = $this->wpdb->delete(
			$this->wpdb->prefix.$this->table, //table
			[ //where
				'user_login'	=> $user_login
			]
		);
		return $result;
	}
	
	function reject_invitable_user($user_login, $reason){
		$result = $this->wpdb->update(
			$this->wpdb->prefix.$this->table, //table
			[ //data
				'rejector'		=> get_current_user_id(),
				'reason'		=> $reason
			],
			[ //where
				'user_login'	=> $user_login
			]
		);
		return $result;
	}
	
	function get_invitable_users(){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix.$this->table)." WHERE rejector = '".$this->rejector_standard."' AND reason = '".$this->reason_standard."';";
		$result = $this->wpdb->get_results( $qry, ARRAY_A );
		return $result;
	}
	
	function get_invitable_user($user_login){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix.$this->table)." WHERE user_login = '".$user_login."';";
		$invitable_user = $this->wpdb->get_row( $qry, ARRAY_A );
		if(sizeof($invitable_user) > 0){
			$invitable_user['user_meta'] = maybe_unserialize($invitable_user['user_meta']);
			return $invitable_user;
		}
		return false;
	}
	
	function get_rejected_users(){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix.$this->table)." WHERE rejector <> '".$this->rejector_standard."' OR reason <> '".$this->reason_standard."';";
		$result = $this->wpdb->get_results( $qry, ARRAY_A );
		return $result;
	}
	
	function user_exists($username,$email){
		$registered_user_exists = $this->registered_user_exists($username,$email);
		if( $registered_user_exists ){ return $registered_user_exists; }
		
		if( $this->invitable_user_exists($username,$email) ){ return true; }
		
		return false;
	}
	
	function registered_user_exists($username,$email){
		$user_id = username_exists($username);
		if($user_id === false){
			$user_id = email_exists($email);
		}
		if($user_id === false){
			return false;
		}
		
		if( is_user_member_of_blog( $user_id ) ){
			return true;
		}
		return 'wpmu';
	}
	
	function invitable_user_exists($username,$email=''){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix.$this->table)." WHERE user_login = '".$username."' OR user_email = '".$email."';";
		$result = $this->wpdb->get_results( $qry );
		if(sizeof($result) > 0){ return true; }
		return false;
	}
}

$democracy_problemmanagement_user = new Democracy_Problemmanagement_User();

add_filter( 'wpmu_signup_user_notification_email', [ $democracy_problemmanagement_user, 'user_invite_email' ], 11, 4 );
add_filter( 'wpmu_signup_user_notification_subject', [ $democracy_problemmanagement_user, 'user_invite_email_subject' ] );
add_action( 'admin_menu', [ $democracy_problemmanagement_user->get_gui(), 'add_menu_invite_user' ] );
add_action( 'wp_dashboard_setup', [ $democracy_problemmanagement_user->get_gui(), 'show_all_unchecked_invitations' ] );
add_action( 'wp_ajax_democracy_invite_user', [ $democracy_problemmanagement_user, 'ajax_invite_user' ] );
?>