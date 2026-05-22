=== Troll Trap ===
Contributors: mahangu
Tags: comments, comment-moderation, moderation, anti-spam, troll
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0-alpha.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Selectively filter and obfuscate comments based on keywords — a moderation tier between Approved and Unapproved.

== Description ==

Troll Trap adds a moderation level that sits between "Approved" and "Unapproved": it keeps a comment **visible** on your site while making its content **inaccessible** to readers.

Instead of deleting or hiding a troll's comment, Troll Trap transforms its text — so the comment stays on the public record, but the sting is gone.

= Two ways to use it =

1. **Keyword graylist (automatic).** Maintain a list of keywords under Settings &gt; Discussion &gt; Troll Trap. When an incoming comment matches a keyword in its content, author name, URL, email, IP address, or user agent, the default Troll Trap filter is applied to it automatically. The graylist works like WordPress' built-in disallowed-comment list, but obfuscates instead of blocks.

2. **Manual filters (per comment).** From the Comments admin screen you can apply a filter to any individual comment, or use the **Mark as Troll** and **Untrap** bulk actions to filter or clear many comments at once.

= Built-in filters =

* **Piglatin** — converts the comment to pig latin.
* **Leetspeak** — swaps letters for numerals (l33t 5p34k).
* **Mocking Case** — aLtErNaTeS tHe CaSe of every letter.
* **uwu** — softens the text by turning r and l into w.
* **Reverse Words** — reverses each word.
* **ROT13** — applies the ROT13 letter cipher.
* **Disemvowel** — strips the vowels, leaving text that is readable, but only slowly.
* **Zalgo** — decorates the text with combining marks into "cursed" text.

Developers can register custom filters on the `trolltrap_register_filters` action.

== Installation ==

1. Upload the `troll-trap` folder to `/wp-content/plugins/`, or install the plugin through the Plugins screen in WordPress.
2. Activate the plugin through the Plugins screen.
3. Go to Settings &gt; Discussion and scroll to the Troll Trap section to set your Comment Graylist and Default Filter.

== Frequently Asked Questions ==

= Does Troll Trap delete comments? =

No. Troll Trap never deletes or hides comments. It transforms the displayed text of a comment while leaving the comment itself published and intact. Clearing the filter (via the per-comment dropdown or the Untrap bulk action) restores the original text.

= Does it send my comments anywhere? =

No. All filtering happens on your own server. Troll Trap makes no external network requests.

= Who can apply or clear filters? =

Only users who can moderate comments (Editors and Administrators by default).

== Screenshots ==

1. The Comment Graylist and Default Filter settings under Settings &gt; Discussion.
2. The Troll Trap Filter column and bulk actions on the Comments admin screen.
3. A filtered comment as it appears to site visitors.

== Changelog ==

= 1.0.0-alpha.1 =
* First public alpha.
* Keyword graylist that automatically applies a filter to matching comments.
* Per-comment filter selection on the Comments admin screen.
* Mark as Troll and Untrap bulk actions.
* Eight built-in filters: Piglatin, Leetspeak, Mocking Case, uwu, Reverse Words, ROT13, Disemvowel and Zalgo — each multibyte- and whitespace-safe.
* A filter registry with a trolltrap_register_filters action for registering custom filters.
