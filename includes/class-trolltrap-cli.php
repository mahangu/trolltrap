<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for Troll Trap.
 *
 * Registered only when WP_CLI is available. Mirrors the operations the admin
 * UI offers (mark, untrap, re-evaluate, status) plus a few read-only helpers.
 *
 * Examples:
 *
 *     wp trolltrap mark 42
 *     wp trolltrap mark 42 --filter=zalgo
 *     wp trolltrap untrap 42
 *     wp trolltrap reevaluate 42
 *     wp trolltrap status 42
 *     wp trolltrap filters
 */
class Mahangu_Troll_Trap_CLI {

	/**
	 * Apply a Troll Trap filter to a comment.
	 *
	 * ## OPTIONS
	 *
	 * <comment-id>
	 * : The ID of the comment to mark.
	 *
	 * [--filter=<slug>]
	 * : Filter slug to apply. Defaults to the configured trolltrap_default_filter.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap mark 42
	 *     wp trolltrap mark 42 --filter=zalgo
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments.
	 */
	public function mark( $args, $assoc_args ) {

		$comment_id = $this->require_comment( $args );

		$tt      = mahangu_troll_trap();
		$slug    = isset( $assoc_args['filter'] ) ? sanitize_key( $assoc_args['filter'] ) : '';
		$allowed = array_keys( $tt->filters->transforming() );

		if ( '' === $slug ) {
			$slug = (string) get_option( 'trolltrap_default_filter', 'piglatin' );
		}

		if ( ! in_array( $slug, $allowed, true ) ) {
			WP_CLI::error( sprintf( "Unknown or non-transforming filter '%s'. Run `wp trolltrap filters` to list available slugs.", $slug ) );
		}

		update_comment_meta( $comment_id, '_trolltrap_filter', $slug );

		WP_CLI::success( sprintf( 'Comment %d marked with filter: %s', $comment_id, $slug ) );
	}

	/**
	 * Clear the Troll Trap filter on a comment.
	 *
	 * ## OPTIONS
	 *
	 * <comment-id>
	 * : The ID of the comment to untrap.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap untrap 42
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments (unused).
	 */
	public function untrap( $args, $assoc_args ) {

		unset( $assoc_args );

		$comment_id = $this->require_comment( $args );

		update_comment_meta( $comment_id, '_trolltrap_filter', 'none' );

		WP_CLI::success( sprintf( 'Comment %d untrapped.', $comment_id ) );
	}

	/**
	 * Re-evaluate a comment against the current Comment Graylist.
	 *
	 * Runs the same matcher that fires on new comments, so the resulting filter
	 * reflects the current graylist, default filter, and graduated-severity
	 * settings. Any cached AI rewrite is dropped so it can be regenerated.
	 *
	 * ## OPTIONS
	 *
	 * <comment-id>
	 * : The ID of the comment to re-evaluate.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap reevaluate 42
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments (unused).
	 */
	public function reevaluate( $args, $assoc_args ) {

		unset( $assoc_args );

		$comment_id = $this->require_comment( $args );

		delete_comment_meta( $comment_id, '_trolltrap_llm_text' );

		mahangu_troll_trap()->comments_tag( $comment_id );

		$slug  = (string) get_comment_meta( $comment_id, '_trolltrap_filter', true );
		$count = (int) get_comment_meta( $comment_id, '_trolltrap_match_count', true );

		WP_CLI::success( sprintf( 'Comment %d re-evaluated: filter=%s, matches=%d.', $comment_id, $slug, $count ) );
	}

	/**
	 * Show the Troll Trap status of a single comment.
	 *
	 * ## OPTIONS
	 *
	 * <comment-id>
	 * : The ID of the comment to inspect.
	 *
	 * [--format=<format>]
	 * : Render format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap status 42
	 *     wp trolltrap status 42 --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments.
	 */
	public function status( $args, $assoc_args ) {

		$comment_id = $this->require_comment( $args );

		$tt    = mahangu_troll_trap();
		$slug  = (string) get_comment_meta( $comment_id, '_trolltrap_filter', true );
		$count = (int) get_comment_meta( $comment_id, '_trolltrap_match_count', true );
		$kws   = get_comment_meta( $comment_id, '_trolltrap_matched_keywords', true );
		if ( 'llm' !== $slug ) {
			$ai = 'n/a';
		} else {
			$ai = ( null !== $tt->ai->cached_text( $comment_id ) ) ? 'ready' : 'pending';
		}

		$row = array(
			'comment_id'        => $comment_id,
			'filter'            => '' !== $slug ? $slug : '(none)',
			'match_count'       => $count,
			'matched_keywords'  => is_array( $kws ) ? implode( ', ', $kws ) : '',
			'ai_rewrite_status' => $ai,
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, array( $row ), array_keys( $row ) );
	}

	/**
	 * List every registered Troll Trap filter.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap filters
	 *     wp trolltrap filters --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments.
	 */
	public function filters( $args, $assoc_args ) {

		$registry = mahangu_troll_trap()->filters;
		$rows     = array();

		foreach ( $registry->all() as $filter ) {
			$rows[] = array(
				'slug'        => $filter['slug'],
				'name'        => $filter['name'],
				'severity'    => $filter['severity'],
				'transforms'  => ( null === $filter['callback'] ) ? 'no' : 'yes',
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, $rows, array( 'slug', 'name', 'severity', 'transforms' ) );
	}

	/**
	 * Resolve the first positional argument to an absint comment ID, erroring
	 * out cleanly when the comment is missing or the ID is malformed.
	 *
	 * @param array $args Positional arguments.
	 * @return int
	 */
	private function require_comment( $args ) {

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Missing comment ID.' );
		}

		$comment_id = absint( $args[0] );

		if ( ! $comment_id || ! get_comment( $comment_id ) ) {
			WP_CLI::error( sprintf( 'No comment with ID %s.', $args[0] ) );
		}

		return $comment_id;
	}
}
