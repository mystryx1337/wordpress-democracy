<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Users extends Democracy_Abstract_Controller {
	private $page_slug  = 'hackers-democracy-group';
	
	private $db;
	private $gui;
	
	function __construct(){
		$this->db = new Democracy_Users_Database();
		$this->gui = new Democracy_Users_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function post_exclude_users(){
		$get_mode = $this->get_mode();
		$mode = $get_mode[0];
		$user_id = @$get_mode[1];
		
		if($mode == 'suggest'){
			$this->suggest_exclude_users();
		}
		if($mode == 'exclude'){
			$this->db->exclude_user($user_id);
		}
		if($mode == 'include'){
			$this->db->include_user( $user_id, @$_POST['reason'.$user_id] );
		}
		
		wp_redirect(admin_url('users.php?page='.$this->gui->get_submenu_slug()));
	}
	
	private function get_mode(){
		foreach($_POST as $key => $val){
			$key_word = substr($key,0,7);
			$user_id = substr($key,7);
			if($key_word == 'include'){
				return array('include',$user_id);
			}
			if($key_word == 'exclude'){
				return array('exclude',$user_id);
			}
		}
		return array('suggest');
	}
	
	private function suggest_exclude_users(){
		print_r($_POST);
		foreach($_POST as $key => $val){
			if(substr($key,0,4) == 'user'){
				$user_id = substr($key,4);
				$reason = @$_POST['reason'.$user_id];
				if($val == 'on'){
					$this->db->suggest_exclude_user(
						$user_id,
						$reason,
						get_current_user_id()
					);
				}
			}
		}
	}
}

class Democracy_Users_GUI extends Democracy_Abstract_GUI {
	private $page_title = 'Benutzer anzeigen';
	private $menu_title = 'Benutzer anzeigen';
	private $submenu_slug = 'democracy_users';
	
	function get_submenu_slug(){
		return $this->submenu_slug;
	}
	
	function add_to_menu(){
		add_submenu_page(
			'users.php', //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'show_users')
		);
		add_menu_page(
			__( 'Benutzer', $this->plugin_name ),
			__( 'Benutzer', $this->plugin_name ),
			$this->capability,
			$this->menu_slug,
			array($this, 'show_users')
		);
		/*add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'show_users')
		);*/
	}

	function show_users(){
		$standard_role = esc_attr( get_option($this->plugin_name.'_user_standard_role') );
		$data = [
			'ajax_id_search'=> 'democracy_users_search',
			'post_action'	=> esc_url( admin_url('admin-post.php') ),
			'post_id'		=> 'democracy_exclude_users',
			
			'page_title'	=> $this->page_title,
			'filter'		=> esc_html__('Filter', $this->plugin_name),
			'list_title'	=> esc_html__('Benutzer', $this->plugin_name),
			'choose_all'	=> esc_html__('Alle auswählen', $this->plugin_name),
			'submit'		=> get_submit_button( esc_html__('Ausschluss vorschlagen', $this->plugin_name) ),
			
			'text_reason'	=> esc_html__('Begründung', $this->plugin_name),
			'text_exclude'	=> esc_html__('Benutzer löschen', $this->plugin_name),
			'text_include'	=> esc_html__('Benutzer wiederherstellen', $this->plugin_name),
			'text_without_role' => esc_html__('ohne Rolle', $this->plugin_name)
		];
		
		echo Democracy_Singleton::get_service()->use_template('users',$data);
	}
	
	function ajax_list_all_users(){
		global $wp_roles;
		$search = @$_POST['search'];
		$users = get_users( array('search' => '*'.$search.'*') );
		
		$return = "";
		foreach($users as $user){
			$exclude = $this->db->get_user_option('democracy_suggest_user_exclude', $user->ID);
			$exclude_reason = $this->db->get_user_option('democracy_suggest_user_exclude_reason', $user->ID);

			$user_roles = [];
			foreach($user->roles as $role){
				$setpoint = Democracy_Singleton::get_service()->get_setpoint_of($role);
				$actual_value = Democracy_Singleton::get_service()->get_num_of_users_by($role);
				if($setpoint == 0){$setpoint = '-';}
				$user_roles[] = $wp_roles->roles[$role]['name']." (".$actual_value."/".$setpoint.")";
			}
			$user_roles = implode('??',$user_roles);
			
			if(!$exclude){ $exclude = '0'; }
			$return .= $user->ID."::".$user->display_name."::".$exclude."::".$exclude_reason."::".$user_roles.";;";
		}
		echo $return;
		wp_die();
	}
}

class Democracy_Users_Database extends Democracy_Database {
	function exclude_user($user_id){
		if(
			Democracy_Singleton::get_service()->current_user_is_publisher()
			&& $this->get_user_option('democracy_suggest_user_exclude', $user_id) == '1'
		) {
			wp_delete_user($user_id);
		}
	}

	function include_user($user_id, $reason = ''){
		if(
			Democracy_Singleton::get_service()->current_user_is_publisher()
			&& $this->get_user_option('democracy_suggest_user_exclude', $user_id) == '1'
		) {
			$this->set_user_option('democracy_suggest_user_exclude', '0', $user_id);
			$this->set_user_option('democracy_suggest_user_exclude_reason', '', $user_id);
		}
	}

	function suggest_exclude_user($user_id,$reason,$suggestor){
		echo $user_id."::".$reason."::".$suggestor;
		if($reason != ''){
			$this->set_user_option('democracy_suggest_user_exclude', '1', $user_id);
			$this->set_user_option('democracy_suggest_user_exclude_reason', $reason, $user_id);
			$this->set_user_option('democracy_suggest_user_exclude_suggestor', $suggestor, $user_id);
		}
	}
}

$democracy_users = new Democracy_Users();

add_action( 'admin_menu', array($democracy_users->get_gui(), 'add_to_menu' ) );
add_action( 'wp_ajax_democracy_users_search', array($democracy_users->get_gui(), 'ajax_list_all_users' ) );
add_action( 'admin_post_democracy_exclude_users', array($democracy_users, 'post_exclude_users' ) );
?>