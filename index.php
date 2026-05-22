<?php
/*
Plugin Name:       Troll Trap
Plugin URI:        https://github.com/mahangu/troll-trap
Description:       Selectively filter and obfuscate comments based on keywords — a moderation tier between Approved and Unapproved.
Version:           1.0.0-alpha.1
Requires at least: 6.5
Requires PHP:      8.0
Author:            Mahangu Weerasighe
Author URI:        https://mahangu.wordpress.com
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       troll-trap
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TROLLTRAP_VERSION', '1.0.0-alpha.1' );

require_once 'includes/class-trolltrap.php';

function mahangu_troll_trap() {

	return Mahangu_Troll_Trap::instance();
}

add_action( 'plugins_loaded', 'mahangu_troll_trap' );
