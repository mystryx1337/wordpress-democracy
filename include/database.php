<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


class Democracy_Database {
	protected $plugin_name = 'hackers-democracy';
	protected $democracy_db_version = '1.2.0';
	
	protected $wpdb;
	protected $charset_collate;
	
	protected $rejector_standard = '0';
	protected $reason_standard = '';
	
	protected $where;
	protected $order;
	
	private $sql_timestamp = '`last_changed` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ';
	
	function __construct(){
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->charset_collate = $wpdb->get_charset_collate();
		$this->upgrade();
	}
	
	function install(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$this->install_rejected_posts();
		$this->install_invitable_users();
		$this->install_liquid_democracy();
		
		add_option( 'democracy_db_version', $this->democracy_db_version );
	}
	
	function upgrade(){
		$installed_ver = get_option( 'democracy_db_version' );
		if ( $installed_ver != $this->democracy_db_version ) {
			$this->install();
			update_option( 'democracy_db_version', $this->democracy_db_version );
		}
	}
	
	private function install_rejected_posts(){
		$table_name = $this->wpdb->prefix . "democracy_rejected_posts";
		
		$qry = "CREATE TABLE ".$table_name." (
			`post` bigint(20) UNSIGNED NOT NULL,
			`rejector` bigint(20) UNSIGNED NOT NULL,
			`reason` text NOT NULL,
			".$this->sql_timestamp."
			PRIMARY KEY  (post)
		) ".$this->charset_collate.";";
		
		$return = dbDelta( $qry );
	}
	
	private function install_invitable_users(){
		$table_name = $this->wpdb->prefix . "democracy_invitable_users";
		
		$qry = "CREATE TABLE ".$table_name." (
			`user_login` VARCHAR(60) NOT NULL,
			`user_email` VARCHAR(100) NOT NULL,
			`user_role` LONGTEXT NOT NULL,
			`user_meta` LONGTEXT NOT NULL,
			`rejector` bigint(20) UNSIGNED NOT NULL,
			`reason` LONGTEXT NOT NULL,
			".$this->sql_timestamp."
			PRIMARY KEY  (`user_email`),
			UNIQUE (`user_login`)
		) ".$this->charset_collate.";";
		
		$return = dbDelta( $qry );
	}
	
	
	/* Added: Iteration 2 */
	protected function get_($table,$where){
		$qry =  "SELECT * FROM ".($this->wpdb->prefix . $table)." WHERE ".$where.";";
		$result = $this->wpdb->get_row( $qry );
		if(@sizeof($result) > 0){ return $result; }
		return false;
	}
	
	private function install_liquid_democracy(){
		$table_name = $this->wpdb->prefix . "democracy_liquid_user_votings";
		$qry =  "CREATE TABLE ".$table_name." (
			`user` bigint(20) UNSIGNED NOT NULL, "
			."`event` bigint(20) UNSIGNED NOT NULL, "
			."`value` VARCHAR(60) NOT NULL, "
			."`direct_distance` bigint(20) UNSIGNED NOT NULL, "
			.$this->sql_timestamp."
			PRIMARY KEY  (`user`, `event`)
		) ".$this->charset_collate.";";
		$return = dbDelta( $qry );
	}
	
	function set_user_option($option,$value,$user_id = null){
		if($user_id == null){ $user_id = get_current_user_id(); }
		
		update_user_meta(
			$user_id,
			$option,
			$value
		);
	}
	
	function get_user_option($option,$user_id = null){
		if($user_id == null){ $user_id = get_current_user_id(); }

		$value = get_user_meta(
			$user_id,
			$option,
			true
		);
		
		if($value == false){ $value = ''; }
		if(is_array($value)){ $value = $value[0]; }
		
		return $value;
	}
	
	function set_post_option($option,$value,$post_id){
		update_post_meta(
			$post_id,
			$option,
			$value
		);
	}
	
	function get_post_option($option,$post_id){
		$value = get_post_meta(
			$post_id,
			$option,
			true
		);
		
		if($value == false){ $value = ''; }
		if(is_array($value)){ $value = $value[0]; }
		
		return $value;
	}
	
	function get_latest_revision($post_id){
		$post = get_post($post_id);
		if($post->post_type != 'revision'){
			$args = array(
				'post_parent' => $post_id, // Current post's ID
				'post_type' => 'revision',
				'orderby' 	=> 'modified',
				'order' 	=> 'DESC',
				'post_status' => array('future', 'publish', 'pending', 'draft', 'auto-draft', 'inherit', 'trash')
			);
			$results = $this->wp_query( $args );
			
			if($results){
				$revision_id = $results[0]->ID;
				return $revision_id;
			}
		}
		return $post_id;
	}
	
	protected function wp_query($args){
		global $wpdb;
		
		$this->where = "";
		$this->where($args,'post_parent');
		$this->where($args,'post_type');
		$this->where($args,'post_status');
		
		$this->order = "";
		if(@$args['orderby']){
			if($args['orderby'] == 'modified'){ $args['orderby'] = 'post_modified'; }
			$this->order = "ORDER BY ".$args['orderby'];
			if(@$args['order']){
				$this->order .= " ".$args['order'];
			}
		}
		
		$ids = $wpdb->get_results( "SELECT ID FROM ".$wpdb->posts." ".$this->where." ".$this->order);
		return $ids;
	}
	
	private function where($args,$name){
		if(is_array(@$args[$name])){
			$where_or = "";
			foreach($args[$name] as $arg){
				if($where_or != ""){
					$where_or .= " OR";
				}
				$where_or .= " `".$name."`='".$arg."'";
			}
			$this->where .= "(".$where_or.")";
		}
		elseif(@$args[$name] != ""){
			if($this->where != ""){
				$this->where .= " AND";
			}
			else{
				$this->where .= " WHERE";
			}
			$this->where .= " `".$name."`='".$args[$name]."'";
		}
	}
}
$democracy_database = new Democracy_Database();

register_activation_hook( __FILE__, array($democracy_database, 'install' ) );
?>