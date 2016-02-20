<?php

/*
Plugin Name: Troll Trap
Plugin URI:
Description: Selectively filter and modify comments based on keywords found in them.
Version: 0.1.0
Author: Mahangu Weerasighe
Author URI: http://mahangu.wordpress.com
License: GPL v2
Text Domain: troll-trap
Domain Path: /languages
*/

require_once('includes/class-trolltrap.php');

function mahangu_Troll_Trap() {

    return mahangu_Troll_Trap::instance(__FILE__, '0.1.0');

}

add_action('plugins_loaded', 'mahangu_Troll_Trap');

?>