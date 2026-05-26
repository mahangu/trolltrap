<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'class-trolltrap-filters.php';
require 'class-trolltrap-convert.php';
require 'class-trolltrap-ai.php';
require 'class-trolltrap-settings.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require 'class-trolltrap-cli.php';
	WP_CLI::add_command( 'trolltrap', 'Mahangu_Troll_Trap_CLI' );
}


class Mahangu_Troll_Trap {


	private static $instance = null;

	public $settings;

	public $convert;

	/**
	 * The filter registry.
	 *
	 * @var Mahangu_Troll_Trap_Filters
	 */
	public $filters;

	/**
	 * The optional AI rewriting feature.
	 *
	 * @var Mahangu_Troll_Trap_AI
	 */
	public $ai;


	public function __construct() {

		add_action( 'comment_post', array( $this, 'comments_tag' ), 10, 1 );

		add_filter( 'comment_text', array( $this, 'comments_render' ), 10, 2 );
	}


	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->initialize_global_objects();
		}

		return self::$instance;
	}


	public function initialize_global_objects() {

		$this->convert = new Mahangu_Troll_Trap_Convert(); // Text manipulation class.

		$this->filters = new Mahangu_Troll_Trap_Filters(); // Filter registry.
		$this->register_default_filters();

		$this->ai = new Mahangu_Troll_Trap_AI( $this->filters ); // Optional AI rewriting.
		$this->ai->register_filter( $this->filters );

		/**
		 * Fires after the built-in filters are registered, so third-party code
		 * can add its own.
		 *
		 * @since 1.0.0
		 * @param Mahangu_Troll_Trap_Filters $filters The filter registry.
		 */
		do_action( 'trolltrap_register_filters', $this->filters );

		$this->settings = new Mahangu_Troll_Trap_Settings( $this->filters ); // Settings + Comments panel.
	}


	/**
	 * Register the filters that ship with the plugin.
	 */
	private function register_default_filters() {

		$this->filters->register( 'piglatin', __( 'Piglatin', 'troll-trap' ), array( $this->convert, 'pig_latin' ), 1 );
		$this->filters->register( 'leetspeak', __( 'Leetspeak', 'troll-trap' ), array( $this->convert, 'leetspeak' ), 1 );
		$this->filters->register( 'mocking', __( 'Mocking Case', 'troll-trap' ), array( $this->convert, 'mocking' ), 1 );
		$this->filters->register( 'uwu', __( 'uwu', 'troll-trap' ), array( $this->convert, 'uwu' ), 1 );
		$this->filters->register( 'reverse', __( 'Reverse Words', 'troll-trap' ), array( $this->convert, 'reverse' ), 2 );
		$this->filters->register( 'rot13', __( 'ROT13', 'troll-trap' ), array( $this->convert, 'rot13' ), 2 );
		$this->filters->register( 'disemvowel', __( 'Disemvowel', 'troll-trap' ), array( $this->convert, 'disemvowel' ), 3 );
		$this->filters->register( 'zalgo', __( 'Zalgo', 'troll-trap' ), array( $this->convert, 'zalgo' ), 3 );
		$this->filters->register( 'none', __( 'None', 'troll-trap' ), null, 0 );
	}


	/**
	 * The default graduated-severity ladder: keyword-match count to filter slug.
	 *
	 * @return array<int,string>
	 */
	public static function default_severity_ladder() {

		return array(
			1 => 'piglatin',
			2 => 'reverse',
			3 => 'disemvowel',
		);
	}


	/**
	 * Tag a newly posted comment if it matches the Comment Graylist.
	 *
	 * Hooks into 'comment_post' and fires whenever a new comment is posted.
	 *
	 * @since 0.1.0
	 * @param int $comment_id The ID of the newly posted comment.
	 */
	public function comments_tag( $comment_id ) {

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		// An allowlist match short-circuits graylist matching: trusted
		// commenters can mention a graylisted keyword without being trapped.
		$allow_hit = self::match_keywords( $comment, self::allowed_from_option() );

		if ( ! empty( $allow_hit ) ) {
			update_comment_meta( $comment_id, '_trolltrap_filter', 'none' );
			update_comment_meta( $comment_id, '_trolltrap_match_count', 0 );
			update_comment_meta( $comment_id, '_trolltrap_matched_keywords', array() );
			update_comment_meta( $comment_id, '_trolltrap_allowed', $allow_hit );
			return;
		}

		delete_comment_meta( $comment_id, '_trolltrap_allowed' );

		$matched     = self::match_keywords( $comment, self::words_from_option() );
		$match_count = count( $matched );

		update_comment_meta( $comment_id, '_trolltrap_filter', $this->resolve_filter( $match_count ) );
		update_comment_meta( $comment_id, '_trolltrap_match_count', $match_count );
		update_comment_meta( $comment_id, '_trolltrap_matched_keywords', $matched );
	}


	/**
	 * Pure matcher: return the keywords from $words that the given comment
	 * matches, in the order they were supplied. No writes, no side effects.
	 * Mirrors WordPress' own wp_check_comment_disallowed_list() shape.
	 *
	 * Centralised so the production comments_tag path and the read-only CLI
	 * dry-run share one source of truth.
	 *
	 * @since 1.0.0
	 * @param WP_Comment $comment Comment to test.
	 * @param string[]   $words   Candidate keywords (already trimmed of empty entries).
	 * @return string[] The subset of $words that matched the comment.
	 */
	public static function match_keywords( $comment, $words ) {

		$matched = array();

		foreach ( (array) $words as $word ) {
			$word = trim( (string) $word );

			if ( '' === $word ) {
				continue;
			}

			// Escape so a '#' in the keyword cannot break the pattern delimiter.
			$pattern = '#' . preg_quote( $word, '#' ) . '#i';

			if (
				preg_match( $pattern, $comment->comment_author )
				|| preg_match( $pattern, $comment->comment_author_email )
				|| preg_match( $pattern, $comment->comment_author_url )
				|| preg_match( $pattern, $comment->comment_content )
				|| preg_match( $pattern, $comment->comment_author_IP )
				|| preg_match( $pattern, $comment->comment_agent )
			) {
				$matched[] = $word;
			}
		}

		return $matched;
	}


	/**
	 * Parse the stored graylist option into a clean array of keywords, dropping
	 * empty lines and surrounding whitespace.
	 *
	 * @return string[]
	 */
	public static function words_from_option() {

		return self::lines_from_option( 'trolltrap_words' );
	}


	/**
	 * Parse the stored allowlist option (one keyword/email/IP per line) into a
	 * clean array, dropping empty lines and whitespace.
	 *
	 * @return string[]
	 */
	public static function allowed_from_option() {

		return self::lines_from_option( 'trolltrap_allowed' );
	}


	/**
	 * Generic helper to read a newline-separated option as a clean string array.
	 *
	 * @param string $option_name Option key.
	 * @return string[]
	 */
	private static function lines_from_option( $option_name ) {

		$raw = (string) get_option( $option_name, '' );

		return array_values(
			array_filter(
				array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ),
				static function ( $w ) {
					return '' !== $w;
				}
			)
		);
	}


	/**
	 * Decide which filter a comment receives, given how many distinct graylist
	 * keywords it matched.
	 *
	 * With graduated severity disabled, any match applies the single default
	 * filter. With it enabled, the configured ladder maps the match count to a
	 * filter (the third rung covers "3 or more").
	 *
	 * @since 1.0.0
	 * @param int $match_count Number of graylist keywords the comment matched.
	 * @return string The filter slug to apply.
	 */
	private function resolve_filter( $match_count ) {

		if ( $match_count < 1 ) {
			return 'none';
		}

		if ( '1' === (string) get_option( 'trolltrap_graduated_enabled', '0' ) ) {

			$ladder = get_option( 'trolltrap_severity_ladder', self::default_severity_ladder() );
			$rung   = min( $match_count, 3 );

			if ( is_array( $ladder ) && isset( $ladder[ $rung ] ) && $this->filters->has( $ladder[ $rung ] ) ) {
				return $ladder[ $rung ];
			}
		}

		return get_option( 'trolltrap_default_filter', 'piglatin' );
	}


	/**
	 * Filter a comment's displayed text according to its stored filter.
	 *
	 * Hooks into 'comment_text', which fires wherever a comment is rendered.
	 *
	 * @since 0.1.0
	 * @param string          $content The comment text.
	 * @param WP_Comment|null $comment The comment object, when supplied by the caller.
	 * @return string
	 */
	public function comments_render( $content, $comment = null ) {

		// Leave the comment untouched in the admin (e.g. edit-comments.php).
		if ( is_admin() ) {
			return $content;
		}

		// The Comments block and the REST API pass the comment as an argument
		// but do not set the global $comment that get_comment_ID() relies on,
		// so prefer the passed object and only fall back to the global.
		$comment_id = $comment instanceof WP_Comment ? $comment->comment_ID : get_comment_ID();

		$comment_filter = get_comment_meta( $comment_id, '_trolltrap_filter', true );

		// The AI filter serves a cached rewrite; while that is still pending
		// (or if the request failed) it falls back to an algorithmic filter.
		if ( 'llm' === $comment_filter ) {
			$rewritten = $this->ai->cached_text( $comment_id );
			if ( null !== $rewritten ) {
				return $rewritten;
			}
			return $this->filters->apply( $this->ai->fallback_slug(), $content );
		}

		return $this->filters->apply( $comment_filter, $content );
	}
}
