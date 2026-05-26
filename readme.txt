=== Troll Trap ===
Contributors: mahangu
Tags: comments, comment-moderation, moderation, anti-spam, troll
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0-alpha.4
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

Not by default. The eight built-in filters run entirely on your own server. The optional AI Rewrite feature is the one exception: when you enable it and supply an Anthropic API key, the content of a comment assigned the AI filter is sent to the Anthropic API (api.anthropic.com) to be rewritten, and usage is billed to your Anthropic account. AI Rewrite is off unless you turn it on.

= Who can apply or clear filters? =

Only users who can moderate comments (Editors and Administrators by default).

== Screenshots ==

1. The Troll Trap and Troll Trap AI settings under Settings &gt; Discussion.
2. The Troll Trap Filter column and bulk actions on the Comments admin screen.
3. Trapped comments as they appear to site visitors, beside untouched ones.

== Changelog ==

= 1.0.0-alpha.4 =
* Honor the Anthropic Retry-After header on AI rewrite retries so the backoff respects what the API asks for.
* Add wp trolltrap reevaluate --all for site-wide re-evaluation against the current graylist, keyset-paginated to survive concurrent deletes.
* Let the "Send a test rewrite" panel take a custom sample sentence so admins can test their own input through the configured style.
* Add wp trolltrap dry-run-graylist for read-only previewing of which existing comments a candidate keyword list would trap.
* Extract a pure Mahangu_Troll_Trap::match_keywords() helper so the production matcher and the dry-run path share one source of truth.
* Tighten WPCS compliance on $_SERVER reads in the admin-post POST handlers.

= 1.0.0-alpha.3 =
* Add a per-comment "Regenerate AI rewrite" button in the Troll Trap Filter column so a stuck or off-style rewrite can be redone without touching anything else.
* Add a matching wp trolltrap regenerate-ai &lt;comment-id&gt; WP-CLI command.
* Retry AI rewrites automatically on transient API failures (429, 408, 5xx, network errors) with bounded backoff (60s, 300s, 900s, max 3 attempts).
* Surface the new "AI rewrite failed after retries" state in the Comments column and in wp trolltrap status, with a clear hint to use Regenerate.
* Harden per-comment admin-post endpoints by requiring POST.

= 1.0.0-alpha.2 =
* Preview every registered filter, applied to a sample sentence, beneath the Default Filter selector on Settings &gt; Discussion.
* Surface the matched graylist keywords (not just the count) for each trapped comment on the Comments admin screen.
* Show whether an AI rewrite is ready or pending under the filter dropdown for comments assigned the AI filter.
* Add a "Re-evaluate against graylist" bulk action on the Comments screen, so updated graylist rules can be applied retroactively. Drops any cached AI rewrite for the re-evaluated comments.
* Add a "Settings" shortcut on the Plugins screen that jumps to the Troll Trap section under Settings &gt; Discussion.
* Add WP-CLI commands: wp trolltrap mark, untrap, reevaluate, status, filters.
* Fix the author name spelling in the plugin header.
* Expand CI coverage with end-to-end tests for the comment_text rendering pipeline, the admin column HTML, the plugin action link, the re-evaluate bulk action, and the WP-CLI class.

= 1.0.0-alpha.1 =
* First public alpha.
* Keyword graylist that automatically applies a filter to matching comments.
* Per-comment filter selection on the Comments admin screen.
* Mark as Troll and Untrap bulk actions.
* Eight built-in filters: Piglatin, Leetspeak, Mocking Case, uwu, Reverse Words, ROT13, Disemvowel and Zalgo — each multibyte- and whitespace-safe.
* A filter registry with a trolltrap_register_filters action for registering custom filters.
* Optional graduated severity: escalate the filter by how many graylist keywords a comment matches.
* Optional AI Rewrite filter: rewrites a trapped comment in a configurable style (Klingon, Shakespearean, ...) via the Anthropic API — opt-in, bring-your-own API key.
