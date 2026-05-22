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
}
