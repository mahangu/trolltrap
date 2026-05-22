<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'class-trolltrap-filters.php';
require 'class-trolltrap-convert.php';
require 'class-trolltrap-settings.php';


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
		$this->filters->register( 'reverse', __( 'Reverse Words', 'troll-trap' ), array( $this->convert, 'reverse' ), 2 );
		$this->filters->register( 'disemvowel', __( 'Disemvowel', 'troll-trap' ), array( $this->convert, 'disemvowel' ), 3 );
		$this->filters->register( 'none', __( 'None', 'troll-trap' ), null, 0 );
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

		$default_filter = get_option( 'trolltrap_default_filter', 'piglatin' );
		$applied        = 'none';

		$comment = get_comment( $comment_id );

		// Keyword matching mirrors WordPress' own wp_check_comment_disallowed_list().

		$mod_keys = trim( get_option( 'trolltrap_words', '' ) );

		$words = explode( "\n", $mod_keys );

		foreach ( (array) $words as $word ) {
			$word = trim( $word );

			// Skip empty lines
			if ( empty( $word ) ) {
				continue;
			}

			// Do some escaping magic so that '#' chars in the
			// spam words don't break things:
			$word = preg_quote( $word, '#' );

			$pattern = "#$word#i";
			if (
				preg_match( $pattern, $comment->comment_author )
				|| preg_match( $pattern, $comment->comment_author_email )
				|| preg_match( $pattern, $comment->comment_author_url )
				|| preg_match( $pattern, $comment->comment_content )
				|| preg_match( $pattern, $comment->comment_author_IP )
				|| preg_match( $pattern, $comment->comment_agent )
			) {

				$applied = $default_filter;
				break;

			}
		}

		update_comment_meta( $comment_id, '_trolltrap_filter', $applied );
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

		return $this->filters->apply( $comment_filter, $content );
	}
}
