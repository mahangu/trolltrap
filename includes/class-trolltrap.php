<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'class-trolltrap-settings.php';
require 'class-trolltrap-convert.php';


class Mahangu_Troll_Trap {


	private static $instance = null;

	public $settings;

	public $convert;

	// Main array of comment filters that we use in all sub classes to generate
	// <select> boxes etc.

	public $filters = array(
		array(
			'slug' => 'piglatin',
			'name' => 'Piglatin',
		),
		array(
			'slug' => 'reverse',
			'name' => 'Reverse Words',
		),
		array(
			'slug' => 'disemvowel',
			'name' => 'Disemvowel',
		),
		array(
			'slug' => 'none',
			'name' => 'None',
		),
	);



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

		$this->settings = new Mahangu_Troll_Trap_Settings(); // Settings->Discussion and Comments panel setup class.

		$this->convert = new Mahangu_Troll_Trap_Convert(); // Text manipulation class.
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

		switch ( $comment_filter ) {

			case 'piglatin':
				return $this->convert->pig_latin( $content );

			case 'reverse':
				return $this->convert->reverse( $content );

			case 'disemvowel':
				return $this->convert->disemvowel( $content );

			default:
				return $content;

		}
	}
}
