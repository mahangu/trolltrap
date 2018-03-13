<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// Text manipulation class.

class mahangu_Troll_Trap_convert {

    public function pig_latin($text) {

        $words = explode(" ", $text);

        $converted_text = "";

        foreach ($words as $word) {

            if (preg_match("/a|e|i|o|u/i", $word[0])) {

                $pig_latin_word = $word . "way";

            } else {

                $split_word = preg_split('/([^aeiouy])/i', $word, 2, PREG_SPLIT_DELIM_CAPTURE);

                $pig_latin_word = $split_word[2] . $split_word[1] . "ay";

            }

            $converted_text = $converted_text . " " . $pig_latin_word;
        }

        return $converted_text;

    }

    public function reverse($text) {

        $words = explode(" ", $text);

        $converted_text = "";

        foreach ($words as $word) {

            $reversed_word = strrev($word);

            $converted_text = $converted_text . " " . $reversed_word;
        }

        return $converted_text;

    }

	public function disemvowel($text) {

		$words = str_replace(array('a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'), '', $text);

		return $words;

	}
}

?>