<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Role_Allocation extends Democracy_Abstract_Controller {
	private $page_slug  = 'hackers-democracy-group';
	private $election_slug = 'role_allocation';
	
	private $db;
	private $gui;
	
	function __construct(){
		$this->db = new Democracy_Role_Allocation_Database();
		$this->gui = new Democracy_Role_Allocation_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	private function find_vacancies(){
		$vacancies = array();
		$roles = Democracy_Singleton::get_service()->get_all_roles();
		foreach($roles as $role => $role_details){
			$setpoint = Democracy_Singleton::get_service()->get_setpoint_of($role);
			$actual_value = Democracy_Singleton::get_service()->get_num_of_users_by($role);
			$vacancies[$role] = [
				'actual_value' => $actual_value,
				'setpoint' => $setpoint
			];
		}
		return $vacancies;
	}
	
	function start_application_phases(){
		$vacancies = $this->find_vacancies();
		foreach($vacancies as $role => $val){
			if($val['actual_value'] < $val['setpoint']){
				$this->start_application_phase($role);
			}
		}
	}
	
	function start_election_phases(){
		//wenn offene abgelaufene Bewerbungsphasen existieren
		$events = $this->db->get_all_application_events();
		foreach($events as $postarr){
			if($postarr['post_content']['status'] == 'open' && strtotime($postarr['post_content']['end_date']) < time()){
				$this->start_election($postarr['post_content']['role'], $postarr['ID']);
			}
		}
	}
	
	private function start_election($role, $application_post_id){
		$end_expression = 'now +'.get_option($this->plugin_name.'_'.$this->election_slug.'_election_duration');
		$end_date = date( "Y-m-d H:i:s",strtotime($end_expression));
		
		$candidates = $this->translate_candidates($this->db->get_applications($application_post_id));
		
		if($candidates){
			$eventarr = [
				'post_title'	=> __( 'Rollenverteilung', $this->plugin_name ),
				'post_content'	=> [
					'description'				=> '<p>'.__( 'Bewerber für die Rolle', $this->plugin_name ).' '.__($role).'</p>',
					'options'					=> $candidates,
					'election_slug'				=> $this->election_slug,
					'type'						=> 'single', //single_choice or multiple_choice
					'duration'					=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_duration'),
					'ratio'						=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_ratio'),
					'changeable'				=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_changeable'),
					'results_during_election'	=> get_option($this->plugin_name.'_'.$this->election_slug.'_election_results_during_election')
				],
				'post_status'	=> 'private',
				'end_date'		=> $end_date
			];
			
			$election = new Democracy_Liquid();
			
			$election_post_id = $election->create_election($eventarr);
			
			$this->db->set_election_id($application_post_id, $election_post_id);
		}
		$this->db->set_application_event_status($application_post_id, 'election');
		
		return $election_post_id;
	}
	
	function evaluate_election(){
		//Prüfen, ob es nicht ausgewertete Bewerbungsphasen mit offenen Wahlen gibt
		$application_events = $this->db->get_all_application_events();
		
		//Prüfen, ob diese Wahlen zuende sind & Wahl auswerten
		foreach($application_events as $event){
			if($event['post_content']['status'] == 'election'){
				$election = new Democracy_Liquid();
				$result = $election->get_results($event['post_content']['election']);
				
				if($result['result']){
					$this->allocate_roles($result,$event['post_content']['role']);
					$this->db->set_application_event_status($event['ID'], 'closed');
				}
			}
		}
	}
	
	private function allocate_roles($result,$role){
		$vacancies = $this->find_vacancies();
		$delta = $vacancies[$role]['setpoint'] - $vacancies[$role]['setpoint']['actual_value'];

		$i = 0;
		foreach ($result['options'] as $user_str => $option){
			if($option->result && $i<$delta){
				$this->db->set_role($option->value,$role);
				$i++;
			}
		}
		/*
		'options' = [
			i = {
				value
				votes
				result
			},...
		*/
	}
	
	private function translate_candidates($candidates){
		if($candidates){
			foreach($candidates as $key => $user_id){
				$user = get_user_by('id',$user_id);
				$candidates[$key] = $user_id.':'.$user->data->display_name;
			}
			$candidates[] = __( 'keiner', $this->plugin_name );
		}
		return $candidates;
	}
	
	private function start_application_phase($role){
		//wenn keine laufende Bewerbungsphase zu der Rolle existiert, einschließlich wahlphase
		$application_events = $this->just_closed_application_events($role);
		if($application_events === false){
			return false;
		}
		
		//neue Bewerbungsphase anlegen
		$end_expression = 'now +'.get_option($this->plugin_name.'_role_application_duration');
		$end_date = date( "Y-m-d H:i:s",strtotime($end_expression));
		
		$postarr = [
			'post_title'	=> __('Bewerbungsphase', $this->plugin_name),
			'post_content'	=> [
				'role'			=> $role,
				'applications'	=> [],
				'duration'		=> $end_expression,
				'end_date'		=> $end_date,
				'election'		=> '0',
				'status'		=> 'open'
			]
		];
		$this->db->insert_application_event($postarr);
		$this->notify_users();
	}
	
	private function just_closed_application_events($role){
		$return = [];
		$events = $this->db->get_all_application_events();
		
		foreach($events as $event){
			if($event['post_content']['role'] == $role){
				if($event['post_content']['status'] != 'closed'){
					return false;
				}
				$return[] = $event;
			}
		}
		return $return;
	}
	
	private function notify_users(){
		$subject = __( "Integreat: Rollen sind verfügbar", $this->plugin_name );
		$message = __("Bewerben Sie sich auf eine vakante Rolle! Besuchen Sie", $this->plugin_name )
			." ".$this->url()
			." ".__("um daran teilzunehmen.", $this->plugin_name );
		Democracy_Singleton::get_notify()->mail_all_users($subject,$message);
	}
}

/*
application_event->post_content = [
	'role' => role,
	'applications' => [
		user_id,
		user_id,
		...
	],
	'duration' => time expression
	'end_date' => date
	'election' => post_id
	'status' => open|closed //closed: started election-phase
]
*/

class Democracy_Role_Allocation_GUI extends Democracy_Abstract_GUI {
	private $output = "";
	
	function ajax_role_application(){
		if($this->db->has_application($_POST['application_id'],get_current_user_id())){
			$this->db->remove_application($_POST['application_id'],get_current_user_id());
		}
		else{
			$this->db->add_application($_POST['application_id'],get_current_user_id());
		}
		$this->ajax_check_role_application();
	}

	function ajax_check_role_application(){
		if($this->db->has_application($_POST['application_id'],get_current_user_id())){
			echo 'yes';
		}
		else{
			echo 'no';
		}
		echo ";--;";
		echo $this->db->get_applications_count($_POST['application_id']);
		wp_die();
	}
	
	function applicate(){
		$this->applicate_widget();
		
		if($this->output != ""){
			wp_add_dashboard_widget(
				$this->plugin_name."_application",
				esc_html__( 'Freie Positionen', $this->plugin_name ),
				array($this, 'echo_output')
			);
		}
	}
	
	function echo_output(){
		echo $this->output;
	}
	
	private function applicate_widget(){
		$application_events = $this->db->get_all_application_events();
		foreach($application_events as $event){
			if( $this->application_possible($event) ){
				if($this->user_has_role($eventarr['post_content']['role'])){
					$submit_button = __('Du besitzt diese Rolle bereits.', $this->plugin_name);
				}
				else {
					$submit_button = get_submit_button();
				}
				
				$data = [
					'ajax_id'			=> 'democracy_role_application',
					'text_title'		=> Democracy_Singleton::get_service()->get_role_name($event['post_content']['role']) . " " . esc_html__('wird gesucht', $this->plugin_name),
					'text_applicate'	=> __('Bewerben', $this->plugin_name),
					'text_cancel_app'	=> __('Bewerbung zurückziehen', $this->plugin_name),
					'application_end'	=> __('Bewerbungsende', $this->plugin_name) . ": ".$event['post_content']['end_date'],
					'application_id'	=> $event['ID'],
					'submit_button'		=> $submit_button,
					'application_count'	=> "<span id='democracy_applications_count'>"
											.$this->db->get_applications_count($event['ID'])
											."</span> " . __('Bewerbungen vorhanden', $this->plugin_name)
				];
				
				$this->output .= Democracy_Singleton::get_service()->use_template('applicate',$data);
			}
		}
	}
	
	private function user_has_role($role){
		$user = wp_get_current_user();
		
		foreach ($user->roles as $user_role) {
			if (in_array($user_role, $role)) {
				return true;
			}
		}
		return false;
	}
	
	private function application_possible($eventarr){
		/* application possible at this moment? */
		if(strtotime($eventarr['post_content']['end_date']) < time()){ return false; }
		if($eventarr['post_content']['status'] == 'closed'){ return false; }
		return true;
	}
}

class Democracy_Role_Allocation_Database extends Democracy_Database {
	private $post_type = 'application_event';
	
	function add_application($post_id,$user_id){
		$postarr = $this->get_application_event($post_id);
		$postarr['post_content']['applications'][] = $user_id;
		$this->update_application_event($post_id,$postarr);
	}
	
	function set_election_id($application_post_id, $election_post_id){
		$postarr['post_content']['election'] = $election_post_id;
		$this->update_application_event($application_post_id,$postarr);
	}
	
	function set_application_event_status($post_id, $status){
		$postarr['post_content']['status'] = $status;
		return $this->update_application_event($post_id,$postarr);
	}
	
	function remove_application($post_id,$user_id){
		$postarr = $this->get_application_event($post_id);
		$postarr['post_content']['applications'] = array_diff( $postarr['post_content']['applications'], $user_id );
		$this->update_application_event($post_id,$postarr);
	}
	
	function has_application($post_id,$user_id){
		$postarr = $this->get_application_event($post_id);
		if(in_array($user_id,$postarr['post_content']['applications'])){
			return true;
		}
		return false;
	}
	
	function insert_application_event($post){
		return $this->update_application_event('0',$post);
	}
	
	private function get_application_event($post_id){
		$postarr = get_post($post_id, ARRAY_A);
		$postarr['post_content'] = maybe_unserialize($postarr['post_content']);
		return $postarr;
	}
	
	private function update_application_event($post_id,$postarr){
		if($post_id != 0){
			$orig_post = $this->get_application_event($post_id);
			if($orig_post != null){
				$postarr = array_replace_recursive( $orig_post, $postarr );
			}
		} 
		$postarr = array(
			'ID'			=> $post_id,
			'post_title'	=> $postarr['post_title'],
			'post_content'	=> maybe_serialize($postarr['post_content']), //Beschreibung, wählbare optionen, datentyp der optionen
			'post_type'		=> $this->post_type,
			'post_status'	=> 'publish',
			'post_author'	=> 0
		);
		
		if($post_id != 0){
			return wp_update_post($postarr);
		}
		else{
			return wp_insert_post($postarr);
		}
	}
	
	function get_applications($post_id){
		$postarr = $this->get_application_event($post_id);
		if(isset($postarr['post_content']['applications'])){
			return $postarr['post_content']['applications'];
		}
		return false;
	}
	
	function get_applications_count($post_id){
		$applications = $this->get_applications($post_id);
		if($applications){
			return sizeof($applications);
		}
		return 0;
	}
	
	function get_all_application_events(){
		$events = [];
		
		$args = array(
			'post_type' => $this->post_type,
			'orderby' 	=> 'modified',
			'order' 	=> 'DESC'
		);
		
		$results = $this->wp_query( $args );
		foreach($results as $id_obj){
			$post_id = $id_obj->ID;
			$events[] = $this->get_application_event($post_id);
		}
		return $events;
	}

	function set_role($user_str,$role){
		$user_id = explode(':',$user_str)[0];
		$user = new WP_user($user_id);
		$user->add_role($role);
	}
}

$democracy_role_allocation = new Democracy_Role_Allocation();


add_action( 'wp_dashboard_setup', array($democracy_role_allocation->get_gui(), 'applicate' ) );
add_action( 'wp_ajax_democracy_role_application', array($democracy_role_allocation->get_gui(), 'ajax_role_application' ) );
add_action( 'wp_ajax_democracy_check_role_application', array($democracy_role_allocation->get_gui(), 'ajax_check_role_application' ) );

#if(is_admin()){
	add_action('init', array($democracy_role_allocation, 'evaluate_election' ));
	add_action('init', array($democracy_role_allocation, 'start_election_phases' ));
	add_action('init', array($democracy_role_allocation, 'start_application_phases' ));
#}
?>