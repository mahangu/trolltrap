<?php
/*
Plugin Name:       Troll Trap
Plugin URI:        https://github.com/mahangu/troll-trap
Description:       Selectively filter and obfuscate comments based on keywords — a moderation tier between Approved and Unapproved.
Version:           1.0.0-alpha.5
Requires at least: 6.5
Requires PHP:      8.0
Author:            Mahangu Weerasinghe
Author URI:        https://mahangu.wordpress.com
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:       troll-trap
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TROLLTRAP_VERSION', '1.0.0-alpha.5' );

require_once 'includes/class-trolltrap.php';

function mahangu_troll_trap() {

	return Mahangu_Troll_Trap::instance();
}

add_action( 'plugins_loaded', 'mahangu_troll_trap' );

/**
 * Add a "Settings" shortcut to the plugin's row on the Plugins screen, since
 * Troll Trap lives under Settings > Discussion rather than its own page.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function mahangu_troll_trap_action_links( $links ) {

	$settings_link = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( admin_url( 'options-discussion.php#trolltrap' ) ),
		esc_html__( 'Settings', 'troll-trap' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mahangu_troll_trap_action_links' );
