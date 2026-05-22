<?php
/*
Plugin Name:       Troll Trap
Plugin URI:        https://github.com/mahangu/trolltrap
Description:       Selectively filter and obfuscate comments based on keywords — a moderation tier between Approved and Unapproved.
Version:           0.1.0
Requires at least: 6.5
Requires PHP:      8.0
Author:            Mahangu Weerasighe
Author URI:        https://mahangu.wordpress.com
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       troll-trap
Domain Path:       /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TROLLTRAP_VERSION', '0.1.0' );

require_once 'includes/class-trolltrap.php';

function mahangu_Troll_Trap() {

	return mahangu_Troll_Trap::instance( __FILE__, TROLLTRAP_VERSION );

}

add_action( 'plugins_loaded', 'mahangu_Troll_Trap' );
