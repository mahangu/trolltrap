<?php

if ( ! defined( 'ABSPATH' ) ) exit;

require 'class-trolltrap-settings.php';
require 'class-trolltrap-convert.php';


class mahangu_Troll_Trap
{

	private static $_instance = null;

	// Main array of comment filters that we use in all sub classes to generate
	// <select> boxes etc.

	public $filters = array(
		array (
			slug => "piglatin",
			name => "Piglatin"
		),
		array (
			slug => "reverse",
			name => "Reverse Words"
		),
		array (
			slug => "disemvowel",
			name => "Disemvowel"
		),
		array (
			slug => "none",
			name => "None"
		),

	);



	public function __construct($file, $version = '1.0.1') {

		add_filter('comment_post', array($this, 'comments_tag'), 10, 2);

		add_filter('comment_text', array($this, 'comments_render'), 10, 2);

	}


	public static function instance($file, $version = '0.1.0') {


		if (is_null(self::$_instance)) {
			self::$_instance = new self($file, $version);
		}

		self::$_instance->initialize_global_objects();

		return self::$_instance;
	}


	public function initialize_global_objects() {

		$this->settings = new mahangu_TT_settings(); // Settings->Discussion and Comments panel setup class.

		$this->convert = new mahangu_TT_convert(); // Text manipulation class.

	}

	/**
	 * function comments tag()
	 *
	 * Checks if each new comment contains any words found on the Graylist.
	 * Hooks into 'comment_post' and fires whenever a new comment is posted.
	 *
	 * @access   public
	 * @since    0.1.0
	 * @param    $comment_id int The ID of the newly posted comment
	 * @return   Null
	 */

	public function comments_tag($comment_id) {

		$default_filter = esc_attr(get_option('trolltrap_default_filter'));

		$comment = get_comment($comment_id);

		// This bit grabbed directly from wp-includes/comment.php/wp_blacklist_check()

		$mod_keys = trim( get_option('trolltrap_words') );

		$words = explode("\n", $mod_keys );

		foreach ( (array) $words as $word ) {
			$word = trim($word);

			// Skip empty lines
			if (empty($word)) {
				continue;
			}

			// Do some escaping magic so that '#' chars in the
			// spam words don't break things:
			$word = preg_quote($word, '#');

			$pattern = "#$word#i";
			if (
				preg_match($pattern, $comment->comment_author)
				|| preg_match($pattern, $comment->comment_author_email)
				|| preg_match($pattern, $comment->comment_author_url)
				|| preg_match($pattern, $comment->comment_content)
				|| preg_match($pattern, $comment->comment_author_IP)
				|| preg_match($pattern, $comment->comment_agent)
			) {

				update_comment_meta($comment_id, '_trolltrap_filter', $default_filter, true);

			} else {

				update_comment_meta($comment_id, '_trolltrap_filter', "none", true);
			}

		}

	} // End comments_tag()


	/**
	 * function comments_render()
	 *
	 * Checks if each comment has '_trolltrap_filter' meta data set for it
	 * and runs manipulates the $content of the comment based on this.
	 *
	 * If '_trolltrap_filter' is 'piglatin', for example, we convert the
	 * $content to piglatin before passing it back to WordPress.
	 *
	 * Hooks into 'comment_text' and fires whenever a comment is rendered,
	 * anywhere on the site.
	 *
	 * @access   public
	 * @since    0.1.0
	 * @param    $content str
	 * @return   str
	 */

	public function comments_render($content) {

		// If we're in WP-Admin (typically edit-comments.php), return the original comment.

		if (is_admin()) {

			return $content;
		}

		$string = $content;

		$comment_id = get_comment_ID();

		$comment_filter = get_comment_meta($comment_id, '_trolltrap_filter', true);

		switch ($comment_filter) {

			case "piglatin":
				$content = $this->convert->pig_latin($string);
				return $content;
				break;

			case "reverse":
				$content = $this->convert->reverse($string);
				return $content;
				break;

			case "disemvowel":
				$content = $this->convert->disemvowel($string);
				return $content;
				break;

			default:
				return $content;

		}


	} // End comments_render()

}

?>