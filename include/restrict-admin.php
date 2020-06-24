<?php
//Quelle: https://aristath.github.io/blog/restrict-access-wordpress-dashboard

class Democracy_Restrict_Admin_GUI extends Democracy_Abstract_GUI {
	private function is_restricted_admin(){
		if(
			current_user_can('administrator')
			&& !current_user_can( 'manage_network' )
			&& esc_attr( get_option($this->plugin_name.'_admin_exclude') ) == 'yes'
		){
			return true;
		}
		return false;
	}
	
	function remove_menus() {
		if( $this->is_restricted_admin() ) {
			echo 1111;
			global $menu;
			$restricted = array(
				__( 'Posts' ),
				__( 'Media' ),
				__( 'Links' ),
				__( 'Pages' ),
				#__( 'Tools' ),
				#__( 'Users' ),
				#__( 'Settings' ),
				__( 'Comments' ),
				#__( 'Plugins' ),
				__( 'QRcode'),
			);
			end ( $menu );

			while ( prev( $menu ) ) {
				$value = explode( ' ',$menu[key( $menu )][0] );
				if ( in_array( $value[0] != NULL ? $value[0]: '', $restricted ) ) {
					unset( $menu[key( $menu )] );
				}
			}

			remove_menu_page( 'edit-comments.php' );
			#remove_menu_page( 'themes.php' );
			#remove_menu_page( 'plugins.php' );
			#remove_menu_page( 'admin.php?page=mp_st' );
			#remove_menu_page( 'admin.php?page=cp_main' );
			remove_submenu_page( 'edit.php?post_type=product', 'edit-tags.php?taxonomy=product_category&amp;post_type=product' );
			remove_submenu_page( 'edit.php?post_type=product', 'edit-tags.php?taxonomy=brand&amp;post_type=product' );
			remove_submenu_page( 'edit.php?post_type=product', 'edit-tags.php?taxonomy=model&amp;post_type=product' );
			remove_submenu_page( 'edit.php?post_type=product', 'edit-tags.php?taxonomy=product_tag&amp;post_type=product' );
		}
	}

	function restrict_admin_with_redirect() {
		$restrictions = array(
			#'/wp-admin/widgets.php'
			#'/wp-admin/widgets.php'
			#'/wp-admin/user-new.php'
			#'/wp-admin/upgrade-functions.php'
			#'/wp-admin/upgrade.php'
			#'/wp-admin/themes.php'
			#'/wp-admin/theme-install.php'
			#'/wp-admin/theme-editor.php'
			#'/wp-admin/setup-config.php'
			#'/wp-admin/plugins.php'
			#'/wp-admin/plugin-install.php'
			#'/wp-admin/options-writing.php'
			#'/wp-admin/options-reading.php'
			#'/wp-admin/options-privacy.php'
			#'/wp-admin/options-permalink.php'
			#'/wp-admin/options-media.php'
			#'/wp-admin/options-head.php'
			#'/wp-admin/options-general.php.php'
			#'/wp-admin/options-discussion.php'
			#'/wp-admin/options.php'
			#'/wp-admin/network.php'
			#'/wp-admin/ms-users.php'
			#'/wp-admin/ms-upgrade-network.php'
			#'/wp-admin/ms-themes.php'
			#'/wp-admin/ms-sites.php'
			#'/wp-admin/ms-options.php'
			#'/wp-admin/ms-edit.php'
			#'/wp-admin/ms-delete-site.php'
			#'/wp-admin/ms-admin.php'
			#'/wp-admin/moderation.php'
			#'/wp-admin/menu-header.php'
			#'/wp-admin/menu.php'
			#'/wp-admin/edit-tags.php'
			#'/wp-admin/edit-tag-form.php'
			#'/wp-admin/edit-link-form.php'
			#'/wp-admin/edit-comments.php'
			#'/wp-admin/credits.php'
			#'/wp-admin/about.php'
		);

		foreach ( $restrictions as $restriction ) {
			if ( $this->is_restricted_admin() && $_SERVER['PHP_SELF'] == $restriction ) {
				wp_redirect( admin_url() );
				exit;
			}
		}
	}

}

$democracy_restrict_admin = new Democracy_Restrict_Admin_GUI();

add_action('admin_menu', array($democracy_restrict_admin, 'remove_menus' ) );
add_action('admin_init', array($democracy_restrict_admin, 'restrict_admin_with_redirect' ) );

?>