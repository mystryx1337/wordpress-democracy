<?php
/**
 * Plugin Name: Hackers' democracy
 * Plugin URI: 
 * Description: Democracy for Wordpress-Communitys
 * Version: 0.1
 * Author: Alexander Hacker
 * Author URI: 
 * License: MIT
 */

//security-feature to not access this file directly
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


require( plugin_dir_path( __FILE__ ) . 'include/abstract-controller.php');
require( plugin_dir_path( __FILE__ ) . 'include/abstract-gui.php');
require( plugin_dir_path( __FILE__ ) . 'include/log.php');
require( plugin_dir_path( __FILE__ ) . 'include/service-functions.php');
require( plugin_dir_path( __FILE__ ) . 'include/include-scripts.php');

require( plugin_dir_path( __FILE__ ) . 'include/database.php');
require( plugin_dir_path( __FILE__ ) . 'include/notify.php');
require( plugin_dir_path( __FILE__ ) . 'include/singleton.php');

include( plugin_dir_path( __FILE__ ) . 'include/show-users.php');
include( plugin_dir_path( __FILE__ ) . 'include/liquid-democracy.php');

include( plugin_dir_path( __FILE__ ) . 'include/options-admin.php');
include( plugin_dir_path( __FILE__ ) . 'include/problemmanagement-posts.php');
include( plugin_dir_path( __FILE__ ) . 'include/problemmanagement-user.php');
include( plugin_dir_path( __FILE__ ) . 'include/unfinnished-posts.php');
include( plugin_dir_path( __FILE__ ) . 'include/community-rules.php');
include( plugin_dir_path( __FILE__ ) . 'include/options-user.php');
include( plugin_dir_path( __FILE__ ) . 'include/restrict-admin.php');
include( plugin_dir_path( __FILE__ ) . 'include/role-allocation.php');

?>