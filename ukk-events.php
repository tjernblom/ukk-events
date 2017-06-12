<?php
/*
Plugin Name: UKK Events & Tickster API Integration
Plugin URI: http://github.com/aventyret/ukk
Description: Implements events for ukk.se and handles the integration with Tickster
Author: Anders Tjernblom, Äventyret
Author URI: http://aventyret.com
Version: 0.0.1
*/

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)){
    die('Invalid URL');
}

if (defined('UKK_EVENTS_PLUGIN'))
{
    die('Invalid plugin access');    
}

define('UKK_EVENTS_PLUGIN',  __FILE__ );
define('UKK_EVENTS_DIR', plugin_dir_path( __FILE__ ));
define('UKK_EVENTS_VER', "0.0.1");

require_once(UKK_EVENTS_DIR . 'includes/ukk_events_class.php'); 
require_once(UKK_EVENTS_DIR . 'includes/ukk_tickster_import_class.php'); 

$ukk_events = new ukk_events();

?>