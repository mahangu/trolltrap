<?php
/**
 * Uninstall handler for Troll Trap.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Removes the
 * options and the per-comment filter meta the plugin created — on every site
 * of a multisite network.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove every option and comment meta value Troll Trap stored on the current site.
 */
function trolltrap_uninstall_site() {

	delete_option( 'trolltrap_words' );
	delete_option( 'trolltrap_default_filter' );
	delete_option( 'trolltrap_graduated_enabled' );
	delete_option( 'trolltrap_severity_ladder' );
	delete_option( 'trolltrap_ai_enabled' );
	delete_option( 'trolltrap_ai_key' );
	delete_option( 'trolltrap_ai_model' );
	delete_option( 'trolltrap_ai_style' );
	delete_option( 'trolltrap_ai_fallback' );

	// Remove the plugin's comment meta from every comment ($delete_all = true).
	delete_metadata( 'comment', 0, '_trolltrap_filter', '', true );
	delete_metadata( 'comment', 0, '_trolltrap_match_count', '', true );
	delete_metadata( 'comment', 0, '_trolltrap_llm_text', '', true );

	// Drop any queued AI rewrite jobs.
	wp_clear_scheduled_hook( 'trolltrap_ai_transform' );
}

/**
 * Remove Troll Trap data across the whole install, covering every site on a
 * multisite network.
 */
function trolltrap_uninstall() {

	if ( ! is_multisite() ) {
		trolltrap_uninstall_site();
		return;
	}

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		trolltrap_uninstall_site();
		restore_current_blog();
	}
}

trolltrap_uninstall();
