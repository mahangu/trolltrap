<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Text manipulation class.

class Mahangu_Troll_Trap_Convert {

	/**
	 * Apply a per-word transformation while preserving every whitespace run
	 * (spaces, tabs, newlines) exactly as it appeared in the input.
	 *
	 * @param string   $text      The text to transform.
	 * @param callable $transform Receives a single word, returns its replacement.
	 * @return string
	 */
	private function map_words( $text, callable $transform ) {

		$tokens = preg_split( '/(\s+)/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $tokens || null === $tokens ) {
			return (string) $text;
		}

		foreach ( $tokens as $i => $token ) {
			// Even indices are words; odd indices are the captured whitespace runs.
			if ( 0 === $i % 2 && '' !== $token ) {
				$tokens[ $i ] = $transform( $token );
			}
		}

		return implode( '', $tokens );
	}

	public function pig_latin( $text ) {

		return $this->map_words(
			$text,
			function ( $word ) {

				$first = mb_substr( $word, 0, 1 );

				if ( '' === $first ) {
					return $word;
				}

				// A word that begins with a vowel just takes a 'way' suffix.
				if ( preg_match( '/[aeiou]/iu', $first ) ) {
					return $word . 'way';
				}

				// Otherwise move the leading consonant cluster to the end.
				// 'y' counts as a consonant throughout (e.g. "myth" -> "hmytay").
				if ( preg_match( '/^([^aeiou]+)(.+)$/iu', $word, $matches ) ) {
					return $matches[2] . $matches[1] . 'ay';
				}

				// A token with no vowel at all: nothing to move.
				return $word . 'ay';
			}
		);
	}

	public function reverse( $text ) {

		return $this->map_words(
			$text,
			function ( $word ) {
				return implode( '', array_reverse( mb_str_split( $word ) ) );
			}
		);
	}

	public function disemvowel( $text ) {

		return str_replace(
			array( 'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U' ),
			'',
			(string) $text
		);
	}

	/**
	 * Alternate the case of each letter — "mOcKiNg cAsE".
	 *
	 * @param string $text The text to transform.
	 * @return string
	 */
	public function mocking( $text ) {

		$chars = mb_str_split( (string) $text );
		$out   = '';
		$upper = false;

		foreach ( $chars as $char ) {
			$lower = mb_strtolower( $char );
			$caps  = mb_strtoupper( $char );

			// A character with no case (digit, space, emoji) passes through
			// untouched and does not advance the alternation.
			if ( $lower === $caps ) {
				$out .= $char;
				continue;
			}

			$out  .= $upper ? $caps : $lower;
			$upper = ! $upper;
		}

		return $out;
	}

	/**
	 * Replace letters with their leetspeak equivalents — "l33t 5p34k".
	 *
	 * @param string $text The text to transform.
	 * @return string
	 */
	public function leetspeak( $text ) {

		$map = array(
			'a' => '4',
			'A' => '4',
			'e' => '3',
			'E' => '3',
			'i' => '1',
			'I' => '1',
			'o' => '0',
			'O' => '0',
			's' => '5',
			'S' => '5',
			't' => '7',
			'T' => '7',
			'b' => '8',
			'B' => '8',
			'g' => '9',
			'G' => '9',
		);

		return str_replace( array_keys( $map ), array_values( $map ), (string) $text );
	}

	/**
	 * Apply the ROT13 letter-substitution cipher.
	 *
	 * @param string $text The text to transform.
	 * @return string
	 */
	public function rot13( $text ) {

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_str_rot13 -- ROT13 is the user-facing comment transform itself, not code obfuscation.
		return str_rot13( (string) $text );
	}

	/**
	 * Soften the text into uwu-speak by turning r and l into w.
	 *
	 * @param string $text The text to transform.
	 * @return string
	 */
	public function uwu( $text ) {

		return str_replace(
			array( 'r', 'l', 'R', 'L' ),
			array( 'w', 'w', 'W', 'W' ),
			(string) $text
		);
	}

	/**
	 * Decorate each visible character with combining marks — "cursed" text.
	 *
	 * The marks are chosen deterministically, so the output is stable for a
	 * given input.
	 *
	 * @param string $text The text to transform.
	 * @return string
	 */
	public function zalgo( $text ) {

		$chars = mb_str_split( (string) $text );
		$out   = '';
		$index = 0;

		foreach ( $chars as $char ) {
			$out .= $char;

			// Leave whitespace undecorated.
			if ( '' === trim( $char ) ) {
				continue;
			}

			$count = ( $index % 4 ) + 2;
			for ( $mark = 0; $mark < $count; $mark++ ) {
				// Combining diacritical marks live in U+0300..U+036F.
				$out .= mb_chr( 0x0300 + ( ( $index * 7 + $mark * 13 ) % 0x70 ) );
			}

			++$index;
		}

		return $out;
	}
}
