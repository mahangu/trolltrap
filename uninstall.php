<?php
/**
 * Uninstall handler for Troll Trap.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Removes the
 * options and the per-comment filter meta the plugin created.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'trolltrap_words' );
delete_option( 'trolltrap_default_filter' );

// Remove '_trolltrap_filter' meta from every comment ($delete_all = true).
delete_metadata( 'comment', 0, '_trolltrap_filter', '', true );
