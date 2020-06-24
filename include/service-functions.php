<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

//Democracy_Singleton::get_service()->get_setpoint($role);

class Democracy_Service_Functions extends Democracy_Abstract_Controller {
	
	function current_user_is_editor(){
		return (current_user_can( 'edit_posts' ) && !current_user_can( 'publish_pages' ) );
	}
	
	function get_setpoint_of($role){
		$num_allusers = sizeof( get_users() );
		$setpoint = esc_attr( get_option($this->plugin_name.'_role_'.$role.'_setpoint') );
		if(substr($setpoint,-1,1) == '%'){
			return ceil($num_allusers/100*substr($setpoint,0,-1));
		}
		if(is_numeric($setpoint)){
			return $setpoint;
		}
		return 0;
	}
	
	function translate($s){
		if($s == 'yes'){$s = __( 'Ja', $this->plugin_name);}
		if($s == 'no'){$s = __( 'Nein', $this->plugin_name);}
		if(substr($s,-3) == "day"){$s = substr($s,0,-3).__( 'Tag', $this->plugin_name);}
		if(substr($s,-4) == "days"){$s = substr($s,0,-4).__( 'Tage', $this->plugin_name);}
		if(substr($s,-4) == "week"){$s = substr($s,0,-4).__( 'Woche', $this->plugin_name);}
		if(substr($s,-5) == "weeks"){$s = substr($s,0,-5).__( 'Wochen', $this->plugin_name);}
		if(substr($s,-5) == "month"){$s = substr($s,0,-5).__( 'Monat', $this->plugin_name);}
		return $s;
	}
	
	function only_publish_if_community_is_initiated(){
		if(
			!is_admin()
			AND esc_attr( get_option($this->plugin_name.'_publish') ) == 'no'
		){
			wp_die(__( 'Diese Plattform wurde noch nicht durch die Community veröffentlicht.', $this->plugin_name));
		}
	}
	
	function get_num_of_users_by($role){
		$args = array(
			'role'    => $role
		);
		$users = get_users( $args );
		return sizeof($users);
	}
	
	function is_election_active($event_id){
		$end_date_ts = strtotime(Democracy_Singleton::get_database()->get_post_option('democracy_end_date',$event_id));
		if($end_date_ts <= time()){ return false; }
		return true;
	}
	
	function current_user_is_publisher(){
		return current_user_can( 'publish_pages' );
	}
	
	function use_template($template_name,$data){
		$template = file_get_contents( plugin_dir_path( __FILE__ ) . '../templates/'.$template_name.'.html');
		foreach ($data as $key => $value) {
			$template = str_replace("{{{{$key}}}}", $value, $template);
		}
		return $template;
	}
	
	function get_all_roles(){
		global $wp_roles;
		return $wp_roles->roles;
	}
	
	function get_role_name($role){
		global $wp_roles;
		return $wp_roles->roles[$role]['name'];
	}
	
	function choose_a_role($name='',$selected=''){
		$roles = $this->get_all_roles();
		$return = "<select name='".$name."'>";
		foreach($roles as $role => $role_details){
			$return .= "<option value='".$role."'";
			if($role == $selected){$return .= " selected";}
			$return .= ">".$role_details['name']."</option>";
		}
		$return .= "</select>";
		return $return;
	}
	
	function base64url_encode($data) { 
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
	}

	function base64url_decode($data) { 
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
	}
	
	function get_election_options_assoc(){
		return [
			'_election_duration'				=> ['4 days', __( 'Dauer einer Abstimmung', $this->plugin_name)],
			'_election_ratio'					=> ['absolute_qualified_66', __( 'erforderliche Mehrheit', $this->plugin_name)],
			'_election_changeable'				=> ['yes', __( 'Stimmen veränderbar', $this->plugin_name)],
			'_election_results_during_election'	=> ['yes', __( 'Ergebnis anzeigen während Wahl', $this->plugin_name)]
		];
	}
}
?>