<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Options_User extends Democracy_Abstract_Controller {
	private $db;
	private $gui;
	
	function __construct(){
		$this->db = new Democracy_Options_User_Database();
		$this->gui = new Democracy_Options_User_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function ajax_set_options (){
		$this->db->set_user_option($this->plugin_name.'_trusted_user',$_POST[$this->plugin_name.'_trusted_user']);
		$this->db->set_user_option($this->plugin_name.'_notify',$_POST[$this->plugin_name.'_notify']);
		echo "Änderungen gespeichert.";
		wp_die();
	}
}

class Democracy_Options_User_GUI extends Democracy_Abstract_GUI_Options {
	private $menu_title = 'Benutzereinstellungen';
	private $menu_slug_options  = 'hackers-democracy-options-user';
	private $submenu_slug = 'democracy_options_user';
	private $non_valid_users = [];
	
	function add_to_menu(){
		add_submenu_page(
			'users.php', //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'options')
		);
		add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'options')
		);
	}

	function options() {
		$data = [
			'page_title'		=> $this->page_title,
			'ajax_id'			=> 'democracy_options_user',
			
				'section1'						=> __( 'Wahlen', $this->plugin_name),
					'section1_trusted_user_label'		=> __( 'Vertrauensbenutzer', $this->plugin_name),
					'section1_trusted_user_option_name'	=> $this->plugin_name.'_trusted_user',
					'section1_trusted_user_option'		=> $this->input_select(
						[
							'name' => $this->plugin_name.'_trusted_user',
							'selected' => $this->db->get_user_option($this->plugin_name.'_trusted_user')
						],
						$this->get_all_valid_users()
					),
					'section1_notify_label'				=> __( 'Email Benachrichtigungen erhalten', $this->plugin_name),
					'section1_notify_option_name'		=> $this->plugin_name.'_notify',
					'section1_notify_option'			=> $this->input_select(
						[
							'name' => $this->plugin_name.'_notify',
							'selected' => $this->db->get_user_option($this->plugin_name.'_notify')
						],
						[
							1 => ['yes' , __( 'Ja', $this->plugin_name)],
							2 => ['no' , __( 'Nein', $this->plugin_name)]
						]
					),

			'submit_button'		=> get_submit_button()
		];
		
		echo Democracy_Singleton::get_service()->use_template('options-user',$data);
	}
	
	//delete all users, which have the current one in trust chain
	private function get_all_valid_users(){
		$users = $this->get_all_users();
		$this->non_valid_users[] = get_current_user_id();
		$change = true;
		
		while($change){
			$change = false;
			foreach($users as $key => $user){
				if( in_array($user[2],$this->non_valid_users) ){
					unset($users[$key]);
					$this->non_valid_users[] = $user[0];
					$change = true;
				}
			}
		}
		
		return $users;
	}
	
	private function get_all_users(){
		$users = [];
		$users[] = ['', '', ''];
		$wp_users = get_users();
		foreach($wp_users as $user){
			if($user->ID != get_current_user_id()){
				$trusted_user = $this->db->get_user_option($this->plugin_name.'_trusted_user', $user->ID);
				$users[] = [ $user->ID, $user->data->display_name, $trusted_user ];
			}
		}
		return $users;
	}
}

class Democracy_Options_User_Database extends Democracy_Database {
	
}

$democracy_options_user = new Democracy_Options_User();
add_action('admin_menu', array($democracy_options_user->get_gui(), 'add_to_menu') );
add_action( 'wp_ajax_democracy_options_user', array($democracy_options_user, 'ajax_set_options' ) );
?>