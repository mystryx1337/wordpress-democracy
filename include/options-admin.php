<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Options_Admin extends Democracy_Abstract_Controller {
	
	private $page_slug  = 'hackers-democracy-group';
	private $election_slug = 'options_admin';
	
	private $db;
	private $gui;
	
	private $kill_ajax_button = '<span>&nbsp;</span>';
	
	function __construct(){
		$this->db = new Democracy_Options_Admin_Database();
		$this->gui = new Democracy_Options_Admin_GUI($this->db);
	}
	
	function get_gui(){
		return $this->gui;
	}
	
	function register_options(){
		$options = $this->db->get_all_options();
		foreach($options as $option => $init_val){
			$this->init( $option, $init_val[0] );
		}
		
		/* hidden meta options */
		$this->init( $this->plugin_name.'_active_option_election', '-1' );
	}
	
	private function init($option,$value = ''){
		register_setting( $this->page_slug, $option );
		if($value != ''){
			add_option( $option, $value);
		}
	}
	
	function ajax_set_options (){
		$is_allowed_user = $this->db->is_allowed_user();
		$active_option_election = $this->db->get_active_option_election();
		
		$this->postprocess_options();
		
		if(
			$is_allowed_user
			&& $is_allowed_user == $_POST['submit_mode']
			&& $active_option_election === false
		){
			if($is_allowed_user == 'admin'){
				$this->db->update_options( $_POST );
				echo __( "Änderungen gespeichert.", $this->plugin_name);
			}
			elseif($is_allowed_user == 'publisher'){
				$election_id = $this->start_election( $_POST );
				$this->db->set_active_option_election( $election_id );
				echo $this->kill_ajax_button.__( "Die Änderung wurde der Community vorgeschlagen.", $this->plugin_name);
			}
		}
		else{
			echo $this->kill_ajax_button.__( "Derzeit können keine Änderungen durchgeführt werden.", $this->plugin_name);
		}
		
		wp_die();
	}
	
	private function postprocess_options(){
		foreach($_POST as $key => $val){
			if(
				substr($key,strlen('_ratio')*(-1)) == '_ratio'
				&& substr($val,-1) == '_'
			){
				$percent = $_POST[$key.'_percent'];
				if($percent == ""){$percent = '0';}
				$_POST[$key] = $val.$percent;
			}
		}
		foreach($_POST as $key => $val){
			if(substr($key,strlen('_ratio_percent')*(-1)) == '_ratio_percent'){
				unset($_POST[$key]);
			}
		}
	}
	
	function permit_election_results(){
		$active_option_election_id = $this->db->get_active_option_election();
		if($active_option_election_id){
			$election = new Democracy_Liquid();
			$election_post = $election->get_election_event($active_option_election_id);
			$result = $election->get_results($active_option_election_id);
			if($result['result'] && $result['options'][0] == 'Zustimmen'){
				$this->db->update_options( $election_post['post_content']['post'] );
				$this->db->set_active_option_election('-1');
			}
		}
	}
	
	private function get_changed_options($post){
		$options = $this->db->get_all_options();
		
		$filtered_options = [];
		
		foreach($options as $option => $arr){
			if(get_option($option) != $post[$option]){
				$filtered_options[$option] = $arr;
			}
		}
		
		print_r($filtered_options);
		
		return $filtered_options;
	}
	
	private function options_to_list($post,$mode){
		$options = $this->get_changed_options($post);
		
		$return = "<table class='options_to_list'>";
		foreach($options as $option => $arr){
			if($mode == 'current'){
				$val = get_option($option);
			}
			elseif($mode == 'new'){
				$val = $post[$option];
			}
			
			$val = Democracy_Singleton::get_service()->translate($val);
			
			$return .= "<tr><td>".$arr[1].":</td><td>".$val."</td></tr>";
		}
		$return .= "</table>";
		return $return;
	}
	
	private function start_election($post){
		$end_expression = 'now +'.get_option($this->plugin_name.'_'.$this->election_slug.'_election_duration');
		$end_date = date( "Y-m-d H:i:s",strtotime($end_expression));
		
		$current_version = $this->options_to_list($post,'current');
		$new_version = $this->options_to_list($post,'new');
		
		$event = [
			'post_title'		=> __( 'Einstellungsänderung', $this->plugin_name ).": ".$postarr['post_title'],
			'post_content'	=> [
				'description'				=>
					'<h2>'.__( 'Aktuelle Version', $this->plugin_name ).':</h2>'
					.$current_version
					.'<h2>'.__( 'Neue Version', $this->plugin_name ).':</h2>'
					.$new_version,
				'post'						=> $post,
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

class Democracy_Options_Admin_GUI extends Democracy_Abstract_GUI_Options {
	private $menu_title = 'Demokratie-Einstellungen';
	private $menu_slug_options  = 'hackers-democracy-options-admin';
	private $options;
	
	function after_activation_message(){
		if(esc_attr( get_option($this->plugin_name.'_publish') ) != 'yes'){
			$echo = 
				"<div class='notice notice-success is-dismissible'>"
					.__("Herzlichen Glückwunsch, das Demokratie-Plugin wurde deaktiviert! Gründen Sie eine Community unter demokratischen Gesichtspunkten. Vor der Veröffentlichung ihrer Plattform, ist eine initiale Abstimmung der ", $this->plugin_name)
					."<a href='./options-general.php?page=".$this->menu_slug_options."'>"
					.__("Einstellungen", $this->plugin_name)
					."</a>"
					.__(" durchzuführen. Vergessen Sie dabei nicht 'Inhalte veröffentlichen' einzuschalten.", $this->plugin_name)
				."</div>";
			echo $echo;
		}
	}
	
	function add_to_menu() {
		add_options_page(
			$this->page_title,
			$this->menu_title,
			$this->capability_priv,
			$this->menu_slug_options,
			array($this, 'show_options')
		);
		add_submenu_page(
			$this->menu_slug, //parent slug
			__( $this->page_title, $this->plugin_name ),
			__( $this->menu_title, $this->plugin_name ),
			$this->capability_priv,
			$this->menu_slug_options,
			array($this, 'show_options')
		);
	}
	
	function show_options() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		else {
			$is_allowed_user = $this->db->is_allowed_user();
			if($is_allowed_user == 'admin'){
				$submit_button['text'] = __( 'Änderungen speichern', $this->plugin_name);
			}
			elseif($is_allowed_user == 'publisher'){
				$submit_button['text'] = __( 'Der Community vorschlagen', $this->plugin_name);
			}
			
			if($this->db->get_active_option_election()){
				$err = __( 'Eine Wahl steht gerade aus. Derzeit können keine weiteren Änderungen durchgeführt werden.', $this->plugin_name);
			}
			
			$this->options = $this->db->get_all_options();
			
			$data = [
				'page_title'		=> $this->page_title,
				'ajax_id'			=> 'democracy_options_admin',
				'settings_fields'	=> $this->get_settings_fields( $this->page_slug ),
				'settings_sections'	=> $this->get_settings_sections( $this->page_slug ),
				'option_yes'		=> __( 'Ja', $this->plugin_name),
				'option_no'			=> __( 'Nein', $this->plugin_name),
				
					'section1'						=> __( 'Allgemeines', $this->plugin_name),
						//Admin ausschließen
						'section1_admin_exclude_label'	=> $this->options[$this->plugin_name.'_admin_exclude'][1]
															.$this->info(__("Der Administrator kann von den inhaltsrelevanten Funktionen ausgeschlossen werden.", $this->plugin_name)),
						'section1_admin_exclude_option'	=> $this->make_toggle_switch(
							$this->plugin_name.'_admin_exclude',
							esc_attr( get_option($this->plugin_name.'_admin_exclude') )
						),
						//Inhalte veröffentlichen
						'section1_publish_label'		=> $this->options[$this->plugin_name.'_publish'][1]
															.$this->info(__("Um sicherzustellen, dass sich die Community zur zur Gründung einig ist, muss diese Option zur ersten Wahl geändert werden.", $this->plugin_name)),
						'section1_publish_option'		=> $this->make_toggle_switch(
							$this->plugin_name.'_publish',
							esc_attr( get_option($this->plugin_name.'_publish') )
						),
						'section1_publish_hint'			=> __( 'Wichtig: Inhalte werden nicht veröffentlich, bevor diese Einstellung nicht durch die Community entschieden wurde.', $this->plugin_name),
						
					'section2'						=> __( 'Benutzereinladungen', $this->plugin_name),
						//Standardgruppe für neue Benutzer
						'section2_role_label'			=> $this->options[$this->plugin_name.'_user_standard_role'][1]
															.$this->info(__("Welche privilegierte Benutzergruppe soll benachrichtig werden, sobald ein neuer Benutzer von der Community vorgesclagen wurde?", $this->plugin_name)),
						'section2_role_option'			=> Democracy_Singleton::get_service()->choose_a_role(
							$this->plugin_name.'_user_standard_role',
							esc_attr( get_option($this->plugin_name.'_user_standard_role') )
						),
						
						//Zu benachrichtigende Benutzer
						'section2_notify_label'			=> $this->options[$this->plugin_name.'_user_notify_roles'][1],
						'section2_notify_option'		=> Democracy_Singleton::get_service()->choose_a_role(
							$this->plugin_name.'_user_notify_roles',
							esc_attr( get_option($this->plugin_name.'_user_notify_roles') )
						),
					
					'section3'						=> __( 'Regelbereich', $this->plugin_name)
														.$this->info(__("Im Menü finden Sie einen internen Regelbereich. Dieser kann mit den Mitgliedern der Community abgestimmt werden. Die Parameter zu dieser Abstimmung können im Folgenden für diese Funktion separat konfiguriert werden.", $this->plugin_name)),
						'section3_election'					=> $this->make_election_options('section3', 'rules'),
					
					'section4'						=> __( 'Benutzerrollen', $this->plugin_name)
														.$this->info(__("Benutzer können sich auf Berechtigngsrollen bewerben. Bewerbungen werden ausgelöst, wenn die unten stehenden Schwellwerte unterschritten werden. Darufhin folgt eine Wahl. Auch diese können hier für diese Funktion separat konfuriert werden.", $this->plugin_name)),
						//Benutzerrollen
						'section4_role_application_duration_label'	=> $this->options[$this->plugin_name.'_role_application_duration'][1],
						'section4_role_application_duration_option'	=> $this->input_select(
							array(
								'name'		=> $this->plugin_name.'_role_application_duration',
								'selected'	=> esc_attr( get_option($this->plugin_name.'_role_application_duration') )
							),
							$this->get_time_intervals()
						),
						'section4_election'			=> $this->make_election_options('section4', 'role_allocation'),
						'section4_roles'			=> $this->make_roles_options(),
					
					'section5'						=> __( 'Einstellungen', $this->plugin_name)
														.$this->info(__("Ja, auch Enstellungen können durch die Community gewählt werden, statt von einem Administrator gesetzt zu werden. Die Parameter zu dieser Abstimmung können im Folgenden für diese Funktion separat konfiguriert werden.", $this->plugin_name)),
						//Einstellungen
						'section5_election'			=> $this->make_election_options('section5', 'options_admin'),
				
				'submit_mode'		=> $is_allowed_user,
				'submit_button'		=> get_submit_button($submit_button['text']),
				'err'				=> @$err
			];
			
			echo Democracy_Singleton::get_service()->use_template('options-admin',$data);
		}
	}
	
	private function make_toggle_switch($name, $select /*no|yes*/){
		if($select == 'yes'){ $checked = 'checked'; }
		$return = "<div class='onoffswitch'>
			<input type='hidden' name='".$name."' class='onoffswitch-checkbox' value='no'>
			<input type='checkbox' name='".$name."' class='onoffswitch-checkbox' id='".$name."' value='yes' ".@$checked.">
			<label class='onoffswitch-label' for='".$name."'>
				<span class='onoffswitch-inner'></span>
				<span class='onoffswitch-switch'></span>
			</label>
		</div>";
		return $return;
	}
	
	private function make_roles_options(){
		$return = "";
		$roles = Democracy_Singleton::get_service()->get_all_roles();
		foreach($roles as $role => $role_details){
			register_setting( $this->page_slug, $this->plugin_name.'_role_'.$role.'_setpoint' );
			$return .= "<tr valign='top'>
				<th scope='row'>".$role_details['name'].":".$this->info(
							__("Hier kann ein Schwellwert angegeben werden. Das Plugin ruft zur Bewerbung "
								."auf unterbesetzte Rollen auf. Diese Schwellwerte können in absoluten Werten "
								."oder pronzentual in Abhängigkeit der Gesamtzahl der Benutzer angegeben werden.", $this->plugin_name)
						)."</th>
				<td>
					<input
						name='".$this->plugin_name.'_role_'.$role.'_setpoint'."'
						value='".get_option($this->plugin_name.'_role_'.$role.'_setpoint')."'
						type='text'
						placeholder='".__( 'absolut oder %', $this->plugin_name)."'
						pattern='^[0-9]{1,2}[%]{0,1}$' />
				</td>
			</tr>";
		}
		
		return $return;
	}

	private function make_election_options($section, $name){
		$options = [
			//Dauer einer Abstimmung
			$section.'_'.$name.'_election_duration_label'		=> $this->options[$this->plugin_name.'_'.$name.'_election_duration'][1],
			$section.'_'.$name.'_election_duration_option'		=> $this->input_select(
				[
					'name'		=> $this->plugin_name.'_'.$name.'_election_duration',
					'selected'	=> esc_attr( get_option($this->plugin_name.'_'.$name.'_election_duration') )
				],
				$this->get_time_intervals()
			),
			
			//Notwendiges Stimmverhältnis
			$section.'_'.$name.'_election_ratio_label'			=> $this->options[$this->plugin_name.'_'.$name.'_election_ratio'][1],
			$section.'_'.$name.'_election_ratio_option'			=> $this->input_select(
				[
					'name'		=> $this->plugin_name.'_'.$name.'_election_ratio',
					'selected'	=> esc_attr( get_option($this->plugin_name.'_'.$name.'_election_ratio') ),
					'class'		=> 'select_ratio'
				],
				$this->get_majorities()
			),
			$section.'_'.$name.'_election_ratio_option_percent'	=> $this->input_int(
				[
					'name'		=> $this->plugin_name.'_'.$name.'_election_ratio_percent',
					'value'		=> substr(
						esc_attr( get_option($this->plugin_name.'_'.$name.'_election_ratio') ),
						strrpos( esc_attr( get_option($this->plugin_name.'_'.$name.'_election_ratio') ), '_' ) +1
					)
				]
			),
			
			//Stimmen änderbar
			$section.'_'.$name.'_election_changeable_label'			=> $this->options[$this->plugin_name.'_'.$name.'_election_changeable'][1],
			$section.'_'.$name.'_election_changeable_option'			=> $this->make_toggle_switch(
				$this->plugin_name.'_'.$name.'_election_changeable',
				esc_attr( get_option($this->plugin_name.'_'.$name.'_election_changeable') )
			),
			
			//Ergebnis anzeigen während Wahl
			$section.'_'.$name.'_election_results_during_election_label'			=> $this->options[$this->plugin_name.'_'.$name.'_election_results_during_election'][1],
			$section.'_'.$name.'_election_results_during_election_option'			=> $this->make_toggle_switch(
				$this->plugin_name.'_'.$name.'_election_results_during_election',
				esc_attr( get_option($this->plugin_name.'_'.$name.'_election_results_during_election') )
			),
		];
		
		return "<tr valign='top'>
				<th scope='row'>".$options[$section.'_'.$name.'_election_duration_label'].":</th>
				<td>".$options[$section.'_'.$name.'_election_duration_option']."</td>
			</tr>
			<tr valign='top'>
				<th scope='row'>".$options[$section.'_'.$name.'_election_ratio_label'].":</th>
				<td>".$options[$section.'_'.$name.'_election_ratio_option']." <span>".$options[$section.'_'.$name.'_election_ratio_option_percent']." %</span></td>
			</tr>
			<tr valign='top'>
				<th scope='row'>".$options[$section.'_'.$name.'_election_changeable_label'].":</th>
				<td>".$options[$section.'_'.$name.'_election_changeable_option']."</td>
			</tr>
			<tr valign='top'>
				<th scope='row'>".$options[$section.'_'.$name.'_election_results_during_election_label'].":</th>
				<td>".$options[$section.'_'.$name.'_election_results_during_election_option']."</td>
			</tr>";
	}
	
	private function get_boolean(){
		return array(
			['yes',		__( 'Ja', $this->plugin_name)],
			['no',		__( 'Nein', $this->plugin_name)]
		);
	}
	
	private function get_majorities(){
		return array(
			['relative',		__( 'Relative Mehrheit', $this->plugin_name)],
			['simple',			__( 'Einfache Mehrheit', $this->plugin_name)],
			['absolute',		__( 'Absolute Mehrheit', $this->plugin_name)],
			['simple_qualified_66',		__( 'Qualifizierte relative Mehrheit (66%)', $this->plugin_name)],
			['absolute_qualified_66',	__( 'Qualifizierte absolute Mehrheit (66%)', $this->plugin_name)],
			['simple_qualified_',		__( 'Qualifizierte relative Mehrheit (XX %)', $this->plugin_name)],
			['absolute_qualified_',	__( 'Qualifizierte absolute Mehrheit (XX %)', $this->plugin_name)],
			['lottery',	__( 'Losverfahren', $this->plugin_name)]
		);
	}
	
	private function get_time_intervals(){
		return array(
			['1 day',	__( '1 Tag', $this->plugin_name)],
			['2 days',	__( '2 Tage', $this->plugin_name)],
			['3 days',	__( '3 Tage', $this->plugin_name)],
			['4 days',	__( '4 Tage', $this->plugin_name)],
			['5 days',	__( '5 Tage', $this->plugin_name)],
			['6 days',	__( '6 Tage', $this->plugin_name)],
			['1 week',	__( '1 Woche', $this->plugin_name)],
			['10 days',	__( '10 Tage', $this->plugin_name)],
			['2 weeks',	__( '2 Wochen', $this->plugin_name)],
			['3 weeks',	__( '3 Wochen', $this->plugin_name)],
			['4 weeks',	__( '4 Wochen', $this->plugin_name)]
		);
	}

}

class Democracy_Options_Admin_Database extends Democracy_Database {
	function update_options($post){
		$options = $this->get_all_options();
		foreach($options as $option => $init_val){
			update_option( $option, $post[$option] );
		}
	}

	function is_allowed_user(){
		$admin_exclude = esc_attr( get_option($this->plugin_name.'_admin_exclude') );
		
		if( ($admin_exclude == 'no' && is_admin()) ){
			return 'admin';
		}
		if(Democracy_Singleton::get_service()->current_user_is_publisher()){
			return 'publisher';
		}
		return false;
	}

	function get_active_option_election(){
		$active_option_election = get_option($this->plugin_name.'_active_option_election');
		if($active_option_election == '-1'){
			return false;
		}
		return $active_option_election;
	}
	
	function set_active_option_election($val){
		update_option( $this->plugin_name.'_active_option_election', $val );
	}
	
	function get_all_options(){
		$return = [
			/*name => [default, translation]*/
			$this->plugin_name.'_admin_exclude' => ['yes', __( 'Admin von Inhalt trennen', $this->plugin_name)],
			$this->plugin_name.'_publish' => ['no', __( 'Inhalte veröffentlichen', $this->plugin_name)],
			#$this->plugin_name.'_revision_types' => [''],
			$this->plugin_name.'_user_standard_role' => ['', __( 'Standardgruppe für neue Benutzer', $this->plugin_name)],
			$this->plugin_name.'_user_notify_roles' => ['', __( 'Zu benachrichtigende Benutzer', $this->plugin_name)],
			$this->plugin_name.'_role_application_duration' => ['4 days', __( 'Dauer der Bewerbungsphase', $this->plugin_name)]
		];
		
		$election_options = Democracy_Singleton::get_service()->get_election_options_assoc();
		foreach($election_options as $option => $val){
			$return[ $this->plugin_name.'_rules'.$option ] = $val;
			$val[2] = $option;
		}
		foreach($election_options as $option => $val){
			$return[ $this->plugin_name.'_role_allocation'.$option ] = $val;
			$val[2] = $option;
		}
		foreach($election_options as $option => $val){
			$return[ $this->plugin_name.'_options_admin'.$option ] = $val;
			$val[2] = $option;
		}
		
		$roles = Democracy_Singleton::get_service()->get_all_roles();
		foreach($roles as $role => $role_details){
			$return[ $this->plugin_name.'_role_'.$role.'_setpoint' ] = [
				0,
				Democracy_Singleton::get_service()->get_role_name($role)." ".__( 'Sollwert', $this->plugin_name)
			];
		}
		
		return $return;
	}
}

$democracy_options_admin = new Democracy_Options_Admin();
add_action( 'admin_menu', [$democracy_options_admin->get_gui(), 'add_to_menu'] );
add_action( 'admin_init', [$democracy_options_admin, 'register_options'] );
add_action( 'wp_ajax_democracy_options_admin', [$democracy_options_admin, 'ajax_set_options'] );
add_action( 'init', [$democracy_options_admin, 'permit_election_results'] );
add_action( 'admin_notices', [$democracy_options_admin->get_gui(), 'after_activation_message'] );
?>