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
 *     wp trolltrap regenerate-ai 42
 *     wp trolltrap status 42
 *     wp trolltrap filters
 *     wp trolltrap dry-run-graylist --keywords="badger,mushroom"
 *     wp trolltrap dry-run-allowlist --keywords="trusted@example.com"
 *     wp trolltrap stats
 *     wp trolltrap export-settings
 *     wp trolltrap import-settings --file=trolltrap.json
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
		$allowed = array_keys( $tt->filters->enabled() );

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
	 * Re-evaluate one or every comment against the current Comment Graylist.
	 *
	 * Runs the same matcher that fires on new comments, so the resulting filter
	 * reflects the current graylist, default filter, and graduated-severity
	 * settings. Any cached AI rewrite is dropped so it can be regenerated.
	 *
	 * ## OPTIONS
	 *
	 * [<comment-id>]
	 * : The ID of the comment to re-evaluate. Required unless --all is set.
	 *
	 * [--all]
	 * : Re-evaluate every comment on the site, in batches. Mutually exclusive
	 *   with a positional comment ID.
	 *
	 * [--batch-size=<n>]
	 * : With --all, the number of comments to fetch per batch (default 200).
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap reevaluate 42
	 *     wp trolltrap reevaluate --all
	 *     wp trolltrap reevaluate --all --batch-size=500
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments.
	 */
	public function reevaluate( $args, $assoc_args ) {

		$all = ! empty( $assoc_args['all'] );

		if ( $all && ! empty( $args ) ) {
			WP_CLI::error( '--all cannot be combined with a positional comment ID.' );
		}

		if ( ! $all ) {
			$comment_id = $this->require_comment( $args );

			delete_comment_meta( $comment_id, '_trolltrap_llm_text' );

			mahangu_troll_trap()->comments_tag( $comment_id );

			$slug  = (string) get_comment_meta( $comment_id, '_trolltrap_filter', true );
			$count = (int) get_comment_meta( $comment_id, '_trolltrap_match_count', true );

			WP_CLI::success( sprintf( 'Comment %d re-evaluated: filter=%s, matches=%d.', $comment_id, $slug, $count ) );
			return;
		}

		$batch_size = isset( $assoc_args['batch-size'] ) ? max( 1, (int) $assoc_args['batch-size'] ) : 200;

		$tt    = mahangu_troll_trap();
		$total = (int) get_comments( array( 'count' => true ) );

		if ( 0 === $total ) {
			WP_CLI::success( 'No comments to re-evaluate.' );
			return;
		}

		$progress = WP_CLI\Utils\make_progress_bar( sprintf( 'Re-evaluating %d comments', $total ), $total );

		// Keyset-paginate by comment_ID so concurrent deletions during a long
		// run cannot cause us to skip comments at a batch boundary the way
		// offset/limit pagination would.
		global $wpdb;

		$last_id   = 0;
		$processed = 0;

		while ( true ) {

			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID > %d ORDER BY comment_ID ASC LIMIT %d",
					$last_id,
					$batch_size
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $cid ) {
				$cid = (int) $cid;
				delete_comment_meta( $cid, '_trolltrap_llm_text' );
				$tt->comments_tag( $cid );
				++$processed;
				$progress->tick();
				$last_id = $cid;
			}
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Re-evaluated %d comment(s) against the current graylist.', $processed ) );
	}

	/**
	 * Regenerate the AI rewrite for a comment.
	 *
	 * Drops the cached rewrite and re-queues the wp-cron job so a fresh
	 * rewrite is requested. Unlike `reevaluate`, this does not re-run the
	 * graylist matcher; the assigned filter is left alone. Intended for
	 * comments already on the 'llm' filter that got an off-style result.
	 *
	 * ## OPTIONS
	 *
	 * <comment-id>
	 * : The ID of the comment to regenerate.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap regenerate-ai 42
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flag arguments (unused).
	 */
	public function regenerate_ai( $args, $assoc_args ) {

		unset( $assoc_args );

		$comment_id = $this->require_comment( $args );

		mahangu_troll_trap()->ai->regenerate( $comment_id );

		WP_CLI::success( sprintf( 'Comment %d AI rewrite cache cleared and refresh queued.', $comment_id ) );
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
		} elseif ( null !== $tt->ai->cached_text( $comment_id ) ) {
			$ai = 'ready';
		} elseif ( $tt->ai->has_failed( $comment_id ) ) {
			$ai = 'failed';
		} else {
			$ai = 'pending';
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
	 * Dry-run a candidate Comment Graylist against existing comments, without
	 * writing anything. Useful for sanity-checking a new keyword list before
	 * pasting it into the live Settings > Discussion > Comment Graylist box.
	 *
	 * Prints a table of matched comments with their ID, author, an excerpt of
	 * the content, and the keywords each one hit.
	 *
	 * ## OPTIONS
	 *
	 * --keywords=<list>
	 * : Comma-separated keyword list to test. Required.
	 *
	 * [--limit=<n>]
	 * : Maximum number of matching comments to show (default 20). Pass 0 for
	 *   no limit.
	 *
	 * [--format=<format>]
	 * : Render format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap dry-run-graylist --keywords="badger,mushroom"
	 *     wp trolltrap dry-run-graylist --keywords="badger" --limit=0 --format=json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flag arguments.
	 */
	public function dry_run_graylist( $args, $assoc_args ) {

		unset( $args );
		$this->run_dry_run( $assoc_args, null, 'graylist' );
	}

	/**
	 * Dry-run a candidate Comment Allowlist against existing comments, without
	 * writing anything. Useful for sanity-checking a trusted-author list
	 * before pasting it into Settings > Discussion > Comment Allowlist.
	 *
	 * Like the production allowlist, matching is restricted to author
	 * identity fields (name, email, URL, IP, user agent) and deliberately
	 * excludes the comment body, so the dry run reflects what the live
	 * allowlist would actually do.
	 *
	 * ## OPTIONS
	 *
	 * --keywords=<list>
	 * : Comma-separated keyword list to test. Required.
	 *
	 * [--limit=<n>]
	 * : Maximum number of matching comments to show (default 20). Pass 0 for
	 *   no limit.
	 *
	 * [--format=<format>]
	 * : Render format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap dry-run-allowlist --keywords="trusted@example.com"
	 *     wp trolltrap dry-run-allowlist --keywords="trusted@example.com" --format=json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flag arguments.
	 */
	public function dry_run_allowlist( $args, $assoc_args ) {

		unset( $args );
		$this->run_dry_run( $assoc_args, Mahangu_Troll_Trap::allowlist_match_fields(), 'allowlist' );
	}

	/**
	 * Shared implementation for dry-run-graylist and dry-run-allowlist. Reads
	 * --keywords, --limit, --format, walks comments newest-first via keyset
	 * pagination, and prints the matches. $fields=null delegates to the
	 * everything-scanned match_keywords; a non-null array narrows via
	 * match_keywords_in.
	 *
	 * @param array       $assoc_args Flag arguments from the public command.
	 * @param string[]|null $fields    Optional restricted field set.
	 * @param string      $label      'graylist' or 'allowlist' for the summary line.
	 */
	private function run_dry_run( $assoc_args, $fields, $label ) {

		if ( empty( $assoc_args['keywords'] ) ) {
			WP_CLI::error( 'Missing required --keywords=<comma-separated list>.' );
		}

		$words = array_values(
			array_filter(
				array_map( 'trim', explode( ',', (string) $assoc_args['keywords'] ) )
			)
		);

		if ( empty( $words ) ) {
			WP_CLI::error( '--keywords resolved to an empty list after trimming.' );
		}

		$limit  = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 20;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		global $wpdb;

		$rows          = array();
		$matched_count = 0;
		$last_id       = PHP_INT_MAX;
		$batch_size    = 200;

		while ( true ) {

			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID < %d ORDER BY comment_ID DESC LIMIT %d",
					$last_id,
					$batch_size
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $cid ) {

				$cid     = (int) $cid;
				$last_id = $cid;
				$comment = get_comment( $cid );

				if ( ! $comment ) {
					continue;
				}

				$hit = null === $fields
					? Mahangu_Troll_Trap::match_keywords( $comment, $words )
					: Mahangu_Troll_Trap::match_keywords_in( $comment, $words, $fields );

				if ( empty( $hit ) ) {
					continue;
				}

				++$matched_count;

				$rows[] = array(
					'comment_id'       => $cid,
					'author'           => $comment->comment_author,
					'content_excerpt'  => wp_html_excerpt( (string) $comment->comment_content, 60, '...' ),
					'matched_keywords' => implode( ', ', $hit ),
				);

				if ( $limit > 0 && count( $rows ) >= $limit ) {
					break 2;
				}
			}
		}

		if ( empty( $rows ) ) {
			WP_CLI::success( sprintf( 'No existing comments match the supplied %s.', $label ) );
			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'comment_id', 'author', 'content_excerpt', 'matched_keywords' ) );

		WP_CLI::log(
			$limit > 0 && $matched_count >= $limit
				? sprintf( 'Showing first %d match(es). Run with --limit=0 for the full list.', $limit )
				: sprintf( '%d comment(s) would match this %s.', $matched_count, $label )
		);
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
				'slug'       => $filter['slug'],
				'name'       => $filter['name'],
				'severity'   => $filter['severity'],
				'transforms' => ( null === $filter['callback'] ) ? 'no' : 'yes',
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, $rows, array( 'slug', 'name', 'severity', 'transforms' ) );
	}

	/**
	 * Show how many comments are currently on each Troll Trap filter, plus
	 * the count of allowlisted comments. Quick operational snapshot.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap stats
	 *     wp trolltrap stats --format=json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flag arguments.
	 */
	public function stats( $args, $assoc_args ) {

		unset( $args );

		global $wpdb;

		// One COUNT per filter slug currently in commentmeta.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS filter_slug, COUNT(*) AS comment_count FROM {$wpdb->commentmeta} WHERE meta_key = %s GROUP BY meta_value ORDER BY comment_count DESC",
				'_trolltrap_filter'
			),
			ARRAY_A
		);

		$untagged = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} c WHERE NOT EXISTS ( SELECT 1 FROM {$wpdb->commentmeta} m WHERE m.comment_id = c.comment_ID AND m.meta_key = %s )",
				'_trolltrap_filter'
			)
		);

		$allowlisted = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = %s",
				'_trolltrap_allowed'
			)
		);

		// Normalize the meta-grouped rows and append the synthetic ones.
		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'filter' => '' === $row['filter_slug'] ? '(empty)' : $row['filter_slug'],
				'count'  => (int) $row['comment_count'],
			);
		}

		if ( $untagged > 0 ) {
			$out[] = array(
				'filter' => '(never evaluated)',
				'count'  => $untagged,
			);
		}

		$out[] = array(
			'filter' => 'allowlisted',
			'count'  => $allowlisted,
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, $out, array( 'filter', 'count' ) );
	}

	/**
	 * The Troll Trap options that are safe to export and import. Deliberately
	 * excludes 'trolltrap_ai_key' so a secret never leaves the source site
	 * through this command.
	 *
	 * @return string[]
	 */
	private function exportable_options() {

		return array(
			'trolltrap_words',
			'trolltrap_allowed',
			'trolltrap_default_filter',
			'trolltrap_disabled_filters',
			'trolltrap_graduated_enabled',
			'trolltrap_severity_ladder',
			'trolltrap_ai_enabled',
			'trolltrap_ai_model',
			'trolltrap_ai_style',
			'trolltrap_ai_fallback',
		);
	}

	/**
	 * Dump every Troll Trap option (except the API key) to JSON on stdout
	 * or a file. Pairs with import-settings for moving config between sites.
	 *
	 * The Anthropic API key is deliberately excluded: secrets shouldn't be
	 * round-tripped through file artifacts. Set the key on the target site
	 * via wp-config.php (TROLLTRAP_ANTHROPIC_KEY) or the settings screen.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write the JSON dump to this path. Default: stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap export-settings > trolltrap.json
	 *     wp trolltrap export-settings --file=trolltrap.json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flag arguments.
	 */
	public function export_settings( $args, $assoc_args ) {

		unset( $args );

		$payload = array(
			'version' => defined( 'TROLLTRAP_VERSION' ) ? (string) TROLLTRAP_VERSION : '',
			'options' => array(),
		);

		foreach ( $this->exportable_options() as $name ) {
			$payload['options'][ $name ] = get_option( $name );
		}

		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT );

		if ( false === $json ) {
			WP_CLI::error( 'Failed to JSON-encode the settings payload.' );
		}

		if ( ! empty( $assoc_args['file'] ) ) {
			$path = (string) $assoc_args['file'];
			if ( false === file_put_contents( $path, $json ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI tool writing to a user-supplied path.
				WP_CLI::error( sprintf( 'Could not write to %s.', $path ) );
			}
			WP_CLI::success( sprintf( 'Wrote %d option(s) to %s.', count( $payload['options'] ), $path ) );
			return;
		}

		WP_CLI::line( $json );
	}

	/**
	 * Restore Troll Trap settings from a JSON file produced by export-settings.
	 *
	 * Existing options are overwritten with the values from the file; options
	 * not present in the file are left untouched. Unknown option names in the
	 * file are silently skipped.
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : Path to a JSON file produced by export-settings. Required.
	 *
	 * ## EXAMPLES
	 *
	 *     wp trolltrap import-settings --file=trolltrap.json
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flag arguments.
	 */
	public function import_settings( $args, $assoc_args ) {

		unset( $args );

		if ( empty( $assoc_args['file'] ) ) {
			WP_CLI::error( 'Missing required --file=<path>.' );
		}

		$path = (string) $assoc_args['file'];

		if ( ! is_readable( $path ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $path ) );
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI tool reading a user-supplied path.

		if ( false === $raw ) {
			WP_CLI::error( sprintf( 'Failed to read %s.', $path ) );
		}

		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) || ! isset( $payload['options'] ) || ! is_array( $payload['options'] ) ) {
			WP_CLI::error( 'File is not a valid trolltrap settings export (missing options array).' );
		}

		$known   = array_flip( $this->exportable_options() );
		$applied = 0;
		$skipped = 0;
		$unknown = array();

		foreach ( $payload['options'] as $name => $value ) {
			if ( ! isset( $known[ $name ] ) ) {
				$unknown[] = (string) $name;
				++$skipped;
				continue;
			}
			update_option( $name, $value );
			++$applied;
		}

		WP_CLI::success( sprintf( 'Imported %d option(s); skipped %d unknown key(s).', $applied, $skipped ) );

		if ( ! empty( $unknown ) ) {
			WP_CLI::log( 'Skipped: ' . implode( ', ', $unknown ) );
		}
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
