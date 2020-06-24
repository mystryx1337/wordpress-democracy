<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Liquid extends Democracy_Abstract_Controller {
	private $db;
	private $gui;
	
	private $post_type = 'election';
	
	function __construct(){
		$this->db = new Democracy_Liquid_Database();
		$this->gui = new Democracy_Liquid_GUI($this->db,$this);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function post_voting(){
		$this->db->set_vote(
			get_current_user_id(), //user
			$_POST['event_id'], //event
			$_POST['choice'], //value
			'0' //direct
		);
		wp_redirect(admin_url('admin.php?page='.$this->gui->get_submenu_slug().'&election='.@$_POST['event_id']));
	}
	
	function create_election($postarr){
		if($postarr['post_content']['ratio'] == 'lottery'){
			$postarr['post_content']['duration'] = '0 days';
			$postarr['end_date'] = date( "Y-m-d H:i:s" );
		}
		else{
			$this->notify_users();
		}
		
		$election_id = $this->db->insert_event($postarr);
		
		return $election_id;
	}
	
	function get_election_event($post_id){
		return $this->db->get_election_event($post_id);
	}
	
	private function sort($options){
		shuffle($options);
		usort(
			$options,
			function ($a, $b) {
				return $a->votes < $b->votes;
			}
		);
		return $options;
	}
	
	function get_results($event_id){
		if($event_id != '' && $event_id != 0){
			if($this->is_active($event_id)){
				$return['active'] = true;
			}
			else{
				$return['active'] = false;
			}
			
			// get the result of an election (Array in absteigender Reihenfolge) oder ausstehend und false zurück
			$event = $this->db->get_election_event($event_id);
			if($event == null){
				return false;
			}
			
			//wenn noch nicht alle passiven stimmen hinzugefügt, dann hinzufügen
			if(
				(!$this->is_counted($event_id) && !$return['active'])
				&& $event['post_content']['ratio'] != 'lottery'
			){
				$this->fill_trusted_votes($event_id);
			}
			
			$return['abs_voters']	= (isset($event['post_content']['abs_voters']))? $event['post_content']['abs_voters'] : sizeof(get_users()) ;
			$return['all_votes']	= sizeof($this->db->get_all_voters_from($event_id));
			$config['absolute_user_count'] = $return['abs_voters'];
			$config['relative_user_count'] = $return['all_votes'];
			$config['per_option_user_count'] = $this->db->get_all_votes_per_option_from($event_id); #foreach(... as $o){$o->value, $o->votes}
				$config['per_option_user_count'] = $this->add_empty_options($event['post_content']['options'],$config['per_option_user_count']);
			
			$return['result'] = false;
			
			// Translate Aliases
			$event['post_content']['ratio'] = $this->translate_aliases($event['post_content']['ratio']);
			
			/* real methods */
			if( $config['per_option_user_count'][0]->votes > $config['per_option_user_count'][1]->votes ){
				$return['result'] = $this->evaluate($config,$config['per_option_user_count'][0],$event['post_content']['ratio']);
			}
			foreach($config['per_option_user_count'] as $key => $option){
				$config['per_option_user_count'][$key]->result = $this->evaluate($config,$option,$event['post_content']['ratio']);
			}
			$return['options'] = $this->sort($config['per_option_user_count']);
			$return['ratio'] = $event['post_content']['ratio'];
			
			return $return;
			/*
				$return = [
					'result' = true|false
					'options' = [
						i = {
							value
							votes
							result
						},
						...
					]
					'all_votes' = int
					'abs_voters' = int
				]
			*/
		}
		
		return false;
	}
	
	private function translate_aliases($ratio){
		if($ratio == ''){
			return 'simple_qualified_0';
		}
		if($ratio == 'lottery'){
			return 'simple_qualified_0';
		}
		if($ratio == 'relative'){
			return 'simple_qualified_0';
		}
		if($ratio == 'simple'){
			return 'simple_qualified_50';
		}
		if($ratio == 'absolute'){
			return 'absolute_qualified_50';
		}
	}
	
	private function add_empty_options($options,$per_option_user_count){
		if($options){
			foreach($per_option_user_count as $user_count){
				$assoc[$user_count->value] = $user_count->votes;
			}
			foreach($options as $option){
				if(!isset($assoc[$option])){
					$per_option_user_count[] = (object) ['value' => $option, 'votes' => 0];
				}
			}
		}
		return $per_option_user_count;
	}
	
	private function evaluate($config,$option,$ratio){
		// simple qualified
		if(substr($ratio,0,strlen('simple_qualified_')) == 'simple_qualified_'){
			$percentage = substr($ratio,strlen('simple_qualified_'));
			if( $option->votes > $config['relative_user_count']*($percentage/100) ){
				return true;
			}
		}
		// absolute qualified
		if(substr($ratio,0,strlen('absolute_qualified_')) == 'absolute_qualified_'){
			$percentage = substr($ratio,strlen('absolute_qualified_'));
			if( $option->votes > $config['absolute_user_count']*($percentage/100) ){
				return true;
			}
		}
	}
	
	function register_custom_post_type(){
		register_post_type($this->post_type,
			array(
				'labels'      => array(
					'name'          => __('Wahlen'),
					'singular_name' => __('Wahl'),
				),
				'public'      => true,
				'has_archive' => true,
			)
		);
	}
	
	private function is_counted($event_id){
		if( $this->db->get_post_option('democracy_counted',$event_id) == 'yes'){
			return true;
		}
		return false;
	}
	
	private function is_active($event_id){
		$end_date_ts = strtotime($this->db->get_post_option('democracy_end_date',$event_id));
		if($end_date_ts <= time()){ return false; }
		return true;
	}
	
	private function fill_trusted_votes($event_id){
		// für alle get_all_trusting_non_voters($event)
		$trusting_non_voters = $this->db->get_all_trusting_non_voters($event_id);
		
		//Mehrfach durchführen, bis keine Änderung mehr stattfindet
		$nobody_changed = false;
		for($i=1; !$nobody_changed; $i++){
			$nobody_changed = true;
			
			//Alle Wähler aus der Liste durchlaufen und prüfen, ob eine indirekte Stimme gsetzt werden kann
			foreach($trusting_non_voters as $key => $user){
				$trusted_user = $this->db->get_user_option($this->plugin_name.'_trusted_user',$user->ID);
				if($trusted_user != ''){
					$trusted_value = $this->db->get_vote($trusted_user,$event_id)->value;
					if($trusted_value){
						//Wahl übernehmen und indirekten Wähler aus iterierter Liste entfernen
						$nobody_changed = false;
						$this->db->set_vote($user->ID,$event_id,$trusted_value,$i);
						unset($trusting_non_voters[0]);
					}
				}
			}
		}
		
		$this->db->set_absolute_users($event_id);
		$this->db->set_post_option('democracy_counted','yes',$event_id);
	}
	
	//Benachrichtigung, wenn ein Ereignis eintritt
	private function notify_users(){
		$subject = __( "Integreat: Ein neues Wahlereignis ist eingetreten", $this->plugin_name );
		$message = __("Ihre Stimme ist gefragt! Bitte besuchen Sie", $this->plugin_name )
			." ".$this->url()
			." ".__("um daran teilzunehmen.", $this->plugin_name );
		Democracy_Singleton::get_notify()->mail_all_users($subject,$message);
	}
}

/*
election_event->content = [
	'description' = html(text)
	'duration' = 00d / 00w
	'ratio' = relative|simple|absolute|..._qualified_00
	'changeable' = yes|no
	'options' = [
		option1,
		option2,
		...
	]
	'type' = single
]
*/

class Democracy_Liquid_GUI extends Democracy_Abstract_GUI {
	private $post_type = 'election';
	
	private $menu_title = 'Wahlen';
	private $page_title = 'Wahlen';
	private $menu_slug_options  = 'hackers-democracy-options-user';
	private $submenu_slug = 'democracy_elections';
	
	private $output = "";
	
	function get_submenu_slug(){
		return $this->submenu_slug;
	}
	
	function add_to_menu(){
		add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability,
			$this->submenu_slug,
			array($this, 'show_elections')
		);
	}

	function show_elections(){
		$this->hint_elections_widget();
		
		if(isset($_GET['election'])){
			$election_id = $_GET['election'];
			$post = $this->db->get_election_event($election_id);
			$content = $post['post_content'];
			$is_changeable = $this->is_changable($election_id,$content);
			
			if( $is_changeable ){
				$submit = get_submit_button(__( 'Abstimmen', $this->plugin_name ));
			}
			else{
				$submit = '('.__( 'Stimme wurde bereits abgegeben', $this->plugin_name ).')';
			}
			
			$data = [
				'post_action'			=> esc_url( admin_url('admin-post.php') ),
				'post_id'				=> 'democracy_liquid',
			
				'event_id'				=> $election_id,
				'page_title'			=> $post['post_title'],
				'description'			=> $content['description'],
				'text_settings'			=> __( 'Wahlbedingungen', $this->plugin_name ),
				'text_end_date'			=> __( 'Endet am', $this->plugin_name ).':',
				'end_date'				=> $this->db->get_post_option('democracy_end_date',$post['ID']),
				'text_duration'			=> __( 'Gesamtdauer', $this->plugin_name ).':',
				'duration'				=> Democracy_Singleton::get_service()->translate($content['duration']),
				'text_ratio'			=> __( 'erforderliche Mehrheit', $this->plugin_name ).':',
				'ratio'					=> Democracy_Singleton::get_service()->translate($content['ratio']),
				'text_changeable'		=> __( 'Stimmen änderbar', $this->plugin_name ).':',
				'changeable'			=> Democracy_Singleton::get_service()->translate($content['changeable']),
				'text_own_choice'		=> __( 'Eigene Stimme', $this->plugin_name ).':',
				'options'				=> $this->radiobox_options(
					$content['options'],
					$this->db->get_vote(get_current_user_id(),$election_id)->value,
					$is_changeable
				),
				'text_hint_trusted_user'=>
					__( 'Möchten Sie einem anderen Benutzer ihre Stimme anvertrauen? Wählen Sie einen ', $this->plugin_name )
					."<a href='".get_site_url()."/wp-admin/admin.php?page=democracy_options_user'>"
					.__( 'Vertrauensbenutzer', $this->plugin_name )
					."</a>"
					.__( ', der mit ihrer Stimme wählt, wenn Sie sie nicht selbst abgeben möchten.', $this->plugin_name ),
				'submit'				=> $submit,
				
				'result_title'			=> __( 'Ergebnis', $this->plugin_name ),
				'result'				=> $this->show_result($election_id,$content)
			];
			echo Democracy_Singleton::get_service()->use_template('election',$data);
		}
	}
	
	private function show_result($election_id,$content){
		if(esc_attr( get_option($this->plugin_name.'_election_results_during_election') ) == 'no'){
			return '';
		}
		
		$results = $this->controller->get_results($election_id);
		/*'options' = [
			value
			votes
			result
		]*/
		
		if(!$results['active']){
			if($results['result']){
				$return = "<p>".__( 'Es gibt einen Sieger bei dieser Wahl', $this->plugin_name )."</p>";
			}
			else{
				$return = "<p>".__( 'Es gibt keinen Sieger bei dieser Wahl', $this->plugin_name )."</p>";
			}
		}
		else {
			$return = "<p>".__( 'Die Wahl ist noch nicht beendet.', $this->plugin_name )."</p>";
		}
		
		if(!$results['active'] || $content['results_during_election'] == 'yes'){
			$return .= "<table><tr class='b'><td>Option</td><td>Stimmen</td><td>Prozent</td></tr>";
			
			foreach($results['options'] as $option){
				$return .= "<tr><td>".$option->value."</td><td>".$option->votes."</td><td>".$this->graph($option->votes,$results)."</td></tr>";
			}
			$return .= "</table>";
		}
		return $return;
	}
	
	private function graph($votes,$results){
		$vote_volume = false;
		if(substr($results['ratio'],0,strlen('simple_')) == 'simple_'){
			$vote_volume = $results['all_votes'];
		}
		elseif(substr($results['ratio'],0,strlen('absolute_')) == 'absolute_'){
			$vote_volume = $results['abs_voters'];
		}
		if($vote_volume){
			if($vote_volume > 0){
				$percent = (100/$vote_volume)*$votes;
			}
			else{
				$percent = 0;
			}
			
			$data = [
				'percentage'	=> $percent
			];
			$bar = Democracy_Singleton::get_service()->use_template('percent-bar',$data);
			
			return $bar;
		}
	}
	
	private function radiobox_options($options,$current_choice,$is_changeable){
		$disabled = ($is_changeable)? '' : ' disabled' ;
		$return = "<fieldset class='table'>";
		foreach($options as $option){
			$return .= "<p><label>".$option."</label><input type='radio' name='choice' value='".$option."'";
			if($option == $current_choice){$return .= " checked";}
			else{ $return .= $disabled; }
			$return .= "></p>";
		}
		$return .= "</ul></fieldset>";
		return $return;
	}
	
	private function is_changable($election_id,$content){
		$vote = $this->db->get_vote(get_current_user_id(),$election_id);
		$value = $vote->value;
		
		if($content['changeable'] != 'yes' && $value != ''){
			return false;
		}
		if(strtotime($this->db->get_post_option('democracy_end_date',$election_id)) < time()){
			return false;
		}
		return true;
	}
	
	function hint_elections(){
		$this->hint_elections_widget();
		
		if($this->output != ""){
			wp_add_dashboard_widget(
				$this->plugin_name."_elections",
				esc_html__( 'Wahlen', $this->plugin_name ),
				array($this, 'echo_output')
			);
		}
	}
	
	function echo_output(){
		echo $this->output;
	}
	
	private function hint_elections_widget(){
		$html_elections = $this->list_elections('actual');
		if($html_elections != ""){
			$this->output .= "<h2>".__( 'Aktuelle', $this->plugin_name )."</h2>";
			$this->output .= "<table><tr class='b'><td>".__( 'Ende', $this->plugin_name )."</td><td></td></tr>";
			$this->output .= $html_elections;
			$this->output .= "</table>";
		}
		
		$html_elections = $this->list_elections('past');
		if($html_elections != ""){
			$this->output .= "<h2>".__( 'Beendete', $this->plugin_name )."</h2>";
			$this->output .= "<table><tr class='b'><td>".__( 'Ende', $this->plugin_name )."</td><td></td></tr>";
			$this->output .= $html_elections;
			$this->output .= "</table>";
		}
	}
	
	private function list_elections($mode){
		return $this->db->list_elections($mode);
	}
}

class Democracy_Liquid_Database extends Democracy_Database {
	private $post_type = 'election';
	private $table_user_votings = 'democracy_liquid_user_votings';
	
	function get_post_type(){
		return $this->post_type;
	}
	
	function insert_event($postarr){
		return $this->update_election_event('0',$postarr);
	}
	
	function set_absolute_users($post_id){
		$postarr['post_content']['abs_voters'] = sizeof(get_users());
		return $this->update_election_event($post_id,$postarr);
	}
	
	function get_election_event($post_id){
		$postarr = get_post($post_id, ARRAY_A);
		
		if($postarr != null){
			$postarr['post_content'] = maybe_unserialize($postarr['post_content']);
			if(substr($postarr['post_content']['description'],0,7) == 'BASE64:'){
				$postarr['post_content']['description'] = base64_decode(substr($postarr['post_content']['description'],7));
			}
		}
		
		return $postarr;
	}
	
	function update_election_event($post_id,$postarr){
		if(!$this->event_started($post_id) /*&& Democracy_Singleton::get_service()->current_user_is_publisher()*/){
			if($post_id != 0){
				$orig_post = $this->get_election_event($post_id);
				if($orig_post != null){
					$postarr = array_replace_recursive( $orig_post, $postarr );
				}
			}
			$meta_input = [];
			if(@$postarr['end_date'] != ''){
				$meta_input['democracy_end_date'] = $postarr['end_date'];
			}
			
			if(substr($postarr['post_content']['description'],0,7) != 'BASE64:'){
				$postarr['post_content']['description'] = 'BASE64:'.base64_encode($postarr['post_content']['description']);
			}
			
			$postarr = array(
				'ID'			=> $post_id,
				'post_title'	=> $postarr['post_title'],
				'post_content'	=> maybe_serialize($postarr['post_content']), //Beschreibung, wählbare optionen, datentyp der optionen
				'post_type'		=> $this->post_type,
				'post_status'	=> $postarr['post_status'],
				'post_author'	=> 0,
				'meta_input'	=> $meta_input
			);
			
			if($post_id != 0){
				return wp_update_post($postarr);
			}
			else{
				return wp_insert_post($postarr);
			}
		}
		else {
			return false;
		}
	}
	
	function delete_event($id){
		if(!$this->event_started($id)){
			wp_delete_post( $id );
		}
	}
	
	private function event_started($id){
		$post_obj = get_post($id);
		
		$post_date_ts = strtotime( $post_obj->post_date );
		
		if($id == 0) { return false; } //new post?
		if($post_obj->post_status != 'publish') { return false; } //unpublished post?
		if($post_date_ts > time()){ return false; } //post in future?
		
		return true;
	}
	
	function get_event($id){
		$post_obj = get_post($id);
		return $post_obj;
	}

	function get_all_events(){
		$events = array();
		
		$args = array(
			'post_type' => $this->post_type,
			'orderby' 	=> 'modified',
			'order' 	=> 'DESC'
		);
		
		$results = $this->wp_query( $args );
		foreach($results as $id_obj){
			$post_id = $id_obj->ID;
			$events[] = $this->get_event($post_id);
		}
		
		return $events;
	}
	
	function set_vote($user,$event,$value,$direct){
		$data = [
			'user'				=> $user,
			'event'				=> $event,
			'value'				=> $value,
			'direct_distance'	=> $direct
		];
		$format = [
			'%d',
			'%d',
			'%s',
			'%d'
		];
		$result = $this->wpdb->replace(
			$this->wpdb->prefix.$this->table_user_votings, //table
			$data,
			$format
		);
	}
	
	function get_vote($user,$event){
		$vote = $this->get_($this->table_user_votings,"user = ".$user." AND event = ".$event);
		if($vote){ return $vote; }
		else{ return (object) ['value' => ''];}
	}

	function get_all_trusting_non_voters($event_id){
		//Alle Benutzer
		$users = get_users();
		$users_a = [];
		foreach($users as $user){
			$users_a[$user->ID] = $user;
		}
		
		//Alle Benutzer, die an diesem event nicht gewählt haben
		$all_voters = $this->get_all_voters_from($event_id);
		foreach( $all_voters as $user_obj ) {
			unset($users_a[$user_obj->user]);
		}
		
		//Alle Benutzer, die keine Vertrauensperson gewählt haben entfernen
		$trusting_non_voting_users = [];
		foreach($users_a as $user_id => $user){
			$trusted_user = $this->get_user_option($this->plugin_name.'_trusted_user',$user_id);
			if($trusted_user && $trusted_user != ''){
				$trusting_non_voting_users[] = $user;
			}
		}
		
		return $trusting_non_voting_users;
	}

	function get_all_voters_from($event_id){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix . $this->table_user_votings)." WHERE event = ".$event_id.";";
		$results = $this->wpdb->get_results( $qry );
		return $results;
	}
	
	function get_all_votes_per_option_from($event_id){
		$qry =  "SELECT COUNT(user) AS votes, value
				FROM ".($this->wpdb->prefix . $this->table_user_votings)."
				WHERE event = ".$event_id."
				GROUP BY value
				ORDER BY votes DESC;";
		$results = $this->wpdb->get_results( $qry );
		return $results;
	}
	
	function list_elections($mode){
		$args = array(
			'post_type' => $this->post_type,
			'orderby' 	=> 'post_date',
			'order' 	=> 'ASC'
		);
		
		$return = "";
		
		$results = $this->wp_query( $args );
		foreach($results as $id_obj){
			$post = get_post($id_obj->ID);
			$end_date_ts = strtotime($this->get_post_option('democracy_end_date',$post->ID));
			$end_date = date('d.m.Y',$end_date_ts);
			$url = get_site_url()."/wp-admin/admin.php?page=democracy_elections&amp;election=".$post->ID;
			$html_line = "<tr><td>".$end_date."</td><td><a href='".$url."'>".$post->post_title."</a></td></tr>";
			if($end_date_ts < time() && $mode == 'past'){
				$return .= $html_line;
			}
			elseif($end_date_ts > time() && $mode == 'actual'){
				$return .= $html_line;
			}
		}
		return $return;
	}
}

$democracy_liquid = new Democracy_Liquid();
add_action( 'wp_dashboard_setup', array($democracy_liquid->get_gui(), 'hint_elections' ) );
add_action( 'admin_menu', array($democracy_liquid->get_gui(), 'add_to_menu') );
add_action( 'admin_post_democracy_liquid', array($democracy_liquid, 'post_voting' ) );

?>