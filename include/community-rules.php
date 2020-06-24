<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Community_Rules extends Democracy_Abstract_Controller {
	private $db;
	private $gui;
	private $election_slug = 'rules';
	
	function __construct(){
		$this->db = new Democracy_Community_Rules_Database();
		$this->gui = new Democracy_Community_Rules_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function get_db(){
		return $this->db;
	}
	
	function on_edit($data, $postarr){
		if($this->db->is_community_rule($postarr['ID'])){
			$data = $this->assure_private_mode($data, $postarr);
			if(
				@$_POST['democracy_rules_start_election_send'] != ''
				&&	(
					$this->db->get_post_option('democracy_election_id',$postarr['ID']) == '0'
					|| $this->db->get_post_option('democracy_election_id',$postarr['ID']) == ''
				)
			){
				$new_election_id = $this->start_election($postarr);
				$this->db->set_post_option('democracy_election_id',$new_election_id,$postarr['ID']);
			}
			
			//if changed, and actual version is not permitted, save current revision for common use
			if($this->db->is_post_permitted($postarr['ID'])){
				$this->db->unpermit_post($postarr['ID']);
			}
		}
		return $data;
	}
	
	private function assure_private_mode($data, $postarr){
		$data['post_status'] = 'private';
		return $data;
	}
	
	private function start_election($postarr){
		$end_expression = 'now +'.get_option($this->plugin_name.'_'.$this->election_slug.'_election_duration');
		$end_date = date( "Y-m-d H:i:s",strtotime($end_expression));
		
		$current_version = $this->db->get_current_version($postarr->ID)->post_content;
		$new_version = stripslashes ($postarr['post_content']);
		
		$event = [
			'post_title'		=> __( 'Regelwerk', $this->plugin_name ).": ".$postarr['post_title'],
			'post_content'	=> [
				'description'				=> 
					'<h2>'.__( 'Aktuelle Version', $this->plugin_name ).':</h2>'
					.$current_version
					.'<h2>'.__( 'Neue Version', $this->plugin_name ).':</h2>'
					.$new_version,
				'options'					=> [
					'Zustimmen',
					'Ablehnen'
				],
				'election_slug'				=> $this->election_slug,
				'type'						=> 'single', //single_choice or multiple_choice
				'duration'					=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_duration'),
				'ratio'						=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_ratio'),
				'changeable'				=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_changeable'),
				'results_during_election'	=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_results_during_election')
			],
			'post_status'	=> 'private',
			'end_date'	=> $end_date
		];
		
		$election = new Democracy_Liquid();
		$election_id = $election->create_election($event);
		return $election_id;
	}
}

class Democracy_Community_Rules_GUI extends Democracy_Abstract_GUI {
	private $menu_title = 'Regeln und Richtlinien';
	private $page_title = 'Regeln und Richtlinien';
	private $menu_slug_options = 'hackers-democracy-options-user';
	private $submenu_slug = 'democracy_rules';
	
	private $post_type = 'democracy_rules';
	
	function add_to_menu(){
		add_submenu_page(
			'users.php', //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'show_rules')
		);
		add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'show_rules')
		);
	}
	
	function make_extra_menu(){
		register_post_type(
			$this->post_type,
			[
				'labels' => [
					'name'			=> __( $this->menu_title, $this->plugin_name ),
					'singular_name'	=> __( 'Regel/Richtlinie' )
				],
				'public'				=> false,
				'hierarchical'			=> true,
				'exclude_from_search'	=> true,
				'capability_type'		=> 'page',
				'has_archive'			=> true,
				'show_ui'				=> true,
				'menu_icon'				=> 'dashicons-info'
			]
		);
	}
	
	function show_rules(){
		echo "<div class='wrap'>";
		$this->hint_rules_widget();

		if(@$_GET['post'] != ''){
			$post = $this->db->get_current_version($_GET['post']);
			
			echo "<h2>".$post->post_title."</h2>";
			echo "<p>".$post->post_modified."</p>";
			echo $post->post_content;
		}
		echo "</div>";
	}
	
	function hint_rules(){
		wp_add_dashboard_widget(
			$this->plugin_name."_cummunity_rules",
			esc_html__( 'Regeln und Richtlinien', $this->plugin_name ),
			array($this, 'hint_rules_widget')
		);
	}
	
	function hint_rules_widget(){
		echo esc_html__( "Die Regeln und Richtlinien des Projekts können Sie mitgestalten.", $this->plugin_name );
		
		$rules_posts = $this->db->get_all_rules();
		echo "<ul>";
		foreach($rules_posts as $post){
			echo "<li><a href='".get_site_url()."/wp-admin/admin.php?page=democracy_rules&amp;post=".$post->ID."'>".$post->post_title."</a></li>";
		}
		echo "</ul>";
	}
	
	function publish_form(){
		if(
			!$this->has_active_election($_GET['post'])
			&& Democracy_Singleton::get_service()->current_user_is_publisher()
		){
			global $post;
			$data = [
					'postId'		=> $post->ID,
					'button_value'	=> esc_html__('Aktualisieren &amp; Community vorschlagen', $this->plugin_name)
				];
				
			echo Democracy_Singleton::get_service()->use_template('rules-publish',$data);
		}
	}
	
	private function has_active_election($post_id){
		$election_id = $this->db->get_post_option('democracy_election_id',$post_id);
		if( $election_id == '0' || $election_id == '' ){
			return false;
		}
		return true;
	}
	
	function hide_windows() {
		remove_post_type_support('page','page-attributes');
	}
}

class Democracy_Community_Rules_Database extends Democracy_Database {
	private $post_type = 'democracy_rules';
	
	function get_all_rules(){
		$args = array(
			'post_type' => $this->post_type,
			'orderby' 	=> 'post_title',
			'order' 	=> 'ASC'
		);
		
		$results = $this->wp_query( $args );
		foreach($results as $id_obj){
			$post_id = $id_obj->ID;
			$posts[] = get_post($post_id);
		}
		return $posts;
	}
	
	function get_latest_version($post_id){
		$args = [
			'post_parent' => $post_id,
			'post_type' => 'page',
			'orderby' 	=> 'post_date',
			'order' 	=> 'DESC',
			'post_status' => ['private']
		];
		
		$results = $this->wp_query( $args );
		foreach($results as $id_obj){
			$post_id = $id_obj->ID;
			$post = get_post($post_id);
			echo "<li><a href='".get_site_url()."/wp-admin/admin.php?page=democracy_rules&amp;post=".$post->ID."'>".$post->post_title."</a></li>";
		}
	}
	
	function get_current_version($post_id){
		$current_post_id = $this->get_post_option('democracy_current_post',$post_id);
		if($current_post_id == '' || $current_post_id == '0'){ $current_post_id = $post_id; }
		$post = get_post($current_post_id);
		return $post;
	}
	
	function is_post_permitted($post_id){
		$current_post_id = $this->get_post_option('democracy_current_post',$post_id);
		if($current_post_id == '' || $current_post_id == '0'){ return true; }
		return false;
	}
	
	function unpermit_post($post_id){
		$this->set_post_option('democracy_current_post',$this->get_latest_revision($post_id),$post_id);
	}
	
	private function permit_post($post_id){
		$this->set_post_option('democracy_current_post','0',$post_id);
		$this->set_post_option('democracy_election_id','0',$post_id);
	}
	
	function is_community_rule($post_id){
		if($post_id == ''){
			return false;
		}
		
		$post = get_post($post_id);
		if($post->post_type == $this->post_type){
			return true;
		}
		return false;
	}
	
	function permit_election_results(){
		$post_id = $_GET['post'];
		$election = new Democracy_Liquid();
		$election_id = $this->get_post_option('democracy_election_id',$post_id);
		
		if(!$this->is_post_permitted($post_id)){
			$result = $election->get_results($election_id);
			if(@$result['result'] && @$result['options'][0] == 'Zustimmen'){
				$this->permit_post($post_id);
			}
		}
	}
}

$democracy_community_rules = new Democracy_Community_Rules();

if ( $democracy_community_rules->get_db()->is_community_rule(@$_GET['post']) ){
	add_action( 'post_submitbox_start', [ $democracy_community_rules->get_gui(), 'publish_form' ] );
	add_action( 'init', [ $democracy_community_rules->get_gui(), 'hide_windows' ] );
	add_action( 'init', [ $democracy_community_rules->get_db(), 'permit_election_results' ] );
}

add_filter( 'wp_insert_post_data', [ $democracy_community_rules, 'on_edit' ], 10, 2 );
add_action( 'wp_dashboard_setup', [ $democracy_community_rules->get_gui(), 'hint_rules' ] );
add_action( 'admin_menu', [ $democracy_community_rules->get_gui(), 'add_to_menu' ] );
add_action( 'init', [ $democracy_community_rules->get_gui(), 'make_extra_menu' ] );

?>