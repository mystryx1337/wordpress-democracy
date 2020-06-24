<?php
//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Democracy_Unfinnished_Posts extends Democracy_Abstract_GUI {
	private $output;
	
	function show_all_unfinnished_posts(){
		$this->show_all_unfinnished_posts_widget();
		
		if($this->output != ""){
			wp_add_dashboard_widget(
				$this->plugin_name."_unfinnished_posts",
				esc_html__( 'Unvollendete Seiten', $this->plugin_name ),
				array($this, 'echo_output')
			);
		}
	}
	
	function echo_output(){
		echo $this->output;
	}
	
	function show_all_unfinnished_posts_widget($post_type = 'page'){
		$post_type = 'page';
		$args = array(
			'post_type' => $post_type,
			'orderby' 	=> 'post_modified',
			'order' 	=> 'DESC',
			'post_status' => array('future', 'publish', 'pending', 'draft', 'auto-draft', 'inherit'),
			'posts_per_page' => 9999,
		);
		$wp_query = new WP_Query( $args );
		if ($wp_query->have_posts()){
			$unfinished = "";
			$unfinished_count = 0;
			$finished = "<hr><table class='democracy_unfinnished_posts_table'><tr><td></td><td></td><td class='b'>".__( 'Fertiggestellte', $this->plugin_name )."</td></tr>";
			$finished_count = 0;
			
			while($wp_query->have_posts()){
				$wp_query->the_post();
				$post = get_post(get_the_ID());
				$ig_revision_id = @get_post_meta( get_the_ID(), 'ig_revision_id' )[0];
				$post_link = "<a href='".get_site_url()."/wp-admin/post.php?post=".get_the_ID()."&action=edit&lang=de"."'>".the_title('','',false)."</a><br />";
				$post_modified = date('d.m.y',strtotime($post->post_modified));
				if($post->post_status != 'publish' && $post->post_status != 'future'){
					$err = __( $post->post_status, 'cms-tree-page-view' );
					$unfinished .= "<tr><td>".$post_modified."</td><td>".$err."</td><td>".$post_link."</td></tr>";
					$unfinished_count++;
				}
				elseif( strlen($post->post_content) < 40 && $post->post_parent != "0"){ //weniger als 40 Zeichen und nicht 1. Ebene
					$err = __( 'leer', $this->plugin_name );
					$unfinished .= "<tr><td>".$post_modified."</td><td>".$err."</td><td>".$post_link."</td></tr>";
					$unfinished_count++;
				}
				elseif( $ig_revision_id != '-1'){ //Integreat-Revision
					$err = __( 'ungeprüft', $this->plugin_name );
					$unfinished .= "<tr><td>".$post_modified."</td><td>".$err."</td><td>".$post_link."</td></tr>";
					$unfinished_count++;
				}
				else { // zuletzt fertiggestellte
					$err = __( 'fertig', $this->plugin_name );
					$finished_count++;
					if($finished_count <= 10){
						$finished .= "<tr><td>".$post_modified."</td><td>".$err."</td><td>".$post_link."</td></tr>";
					}
				}
			}
			$unfinished = "<table class='democracy_unfinnished_posts_table'>
								<tr><td></td><td></td><td>".__( 'insgesamt', $this->plugin_name )." ".$unfinished_count."</td></tr>"
								.$unfinished
							."</table>";
			$finished .= "<tr><td></td><td></td><td>".__( 'und weitere', $this->plugin_name )." ".max(0,$finished_count-10)."</td></tr></table>";
		}
		$this->output = $unfinished.$finished;
	}
}
$democracy_unfinnished_posts = new Democracy_Unfinnished_Posts();

add_action( 'wp_dashboard_setup', array($democracy_unfinnished_posts, 'show_all_unfinnished_posts' ) );
?>