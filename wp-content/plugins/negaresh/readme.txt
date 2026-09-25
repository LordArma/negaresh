=== Negaresh ===
Contributors: lordarma
Tags: persian, farsi, typography, rtl, virastar
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 5.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Fixes Persian (Farsi) typography in your posts: half spaces, Persian digits, punctuation, quotes and more.

== Description ==

Negaresh (نگارش) corrects common Persian typing mistakes in WordPress, using a patched copy of the [Virastar](https://github.com/AlirezaSedghi/Virastar) library.

* **Fix before saving or on display.** Correct the stored text when a post is saved, so the editor shows what readers get, or fix only what is displayed and never change the stored text.
* **32 rules you choose**, each with an example: Persian digits, half spaces (ZWNJ) for prefixes and suffixes, Persian comma, semicolon and question mark, «guillemets», ellipsis, dashes, Kashida cleanup, spacing and more.
* **Try it** on the settings page: paste text and see it fixed with the boxes you have checked, before saving.
* **Only text is changed.** HTML tags, attributes, links, shortcodes and code (`pre`, `code`, `script`, `style` …) are never touched, and neither is text written only in English.
* **Leave a post alone** from the editor sidebar, or part of a post with the CSS class `negaresh-skip`.
* **Fix this post now** button in the block editor; Undo reverts it.
* **Fix existing posts** in Tools → Negaresh or with `wp negaresh fix`: see the changed lines first, and every change is kept in the post's revisions.
* Titles, hand written excerpts and comments can be fixed too (off by default).
* Words to leave alone: brand names, quotations and other words Negaresh must never change.
* A dashboard widget shows how many posts are fixed or waiting.
* Multisite: set defaults for the whole network.
* Persian translation included.

== Installation ==

1. Upload the plugin in *Plugins → Add New → Upload Plugin*, or copy the `negaresh` folder to `wp-content/plugins`.
2. Activate it.
3. Choose the rules and when to fix in *Settings → Negaresh*.
4. To fix posts you already have, use *Tools → Negaresh* (or `wp negaresh fix --apply`).

== Frequently Asked Questions ==

= Does it change my posts? =

In "when a post is saved" mode (the default for new installs) the stored text is corrected when you save. In "when a post is displayed" mode (kept by sites upgrading from 4.x) nothing stored is ever changed. Posts you mark "Leave this post alone" are never changed.

= Can I undo a bulk fix? =

Yes. Before Negaresh changes an existing post it keeps the text as a revision, so each post can be restored from its revisions screen (when your site keeps revisions).

= Will it break my HTML, shortcodes or code samples? =

No. Only the text between tags is fixed; tags, attributes, shortcodes and the contents of `pre`, `code`, `script`, `style`, `textarea`, `svg` and `math` are left exactly as they are.

= How do I keep a quote or a paragraph as it is? =

In the block editor add `negaresh-skip` in *Advanced → Additional CSS class(es)*, or add `data-negaresh="off"` to an element in HTML.

== Screenshots ==

1. Settings → Negaresh: try the rules on your own text before saving, choose when to fix, pick the rules.
2. The Negaresh panel in the block editor: leave a post alone, or fix it now (Undo reverts it).
3. Tools → Negaresh: scan existing posts, see the changed lines, then fix them.

== Changelog ==

= 5.2.0 =
* Dashboard widget with how many posts are fixed, waiting or left alone, and a dismissible notice when posts are waiting.
* Words to leave alone: a list of words and phrases Negaresh never changes.
* Multisite: network defaults for sites without their own settings.

= 5.1.0 =
* When fixing on display, results are kept in the object cache (faster with Redis or Memcached).
* The admin screens are checked for accessibility (WCAG 2 A/AA) in the test suite.

= 5.0.0 =
* Ready for wordpress.org: passes the official Plugin Check, license files included.
* Optional fixing of comments (off by default).
* `wp negaresh status`, and a "Fix existing posts" link on the Plugins screen.

= 4.4.0 =
* Fix: dates written with Persian or Arabic digits were scrambled (۳/۱/۱۳۵۵ became ۱۳/۱/۳۵۵). Upgrade recommended.
* Fix: rules with several steps ran them in the wrong order (---, repeated !?, Kashida between numbers, times).
* Leave this post alone, Fix this post now, Tools → Negaresh and wp negaresh fix for existing posts.

= 4.3.0 =
* Try it box, reset rules to defaults, Settings link, optional titles and excerpts.

= 4.2.0 =
* Fix before saving. Only text between tags is fixed. Fixes for spaces around links and for attributes containing ">".

= 4.1.0 =
* Bug fix release: 4.0.0 removed the HTML of posts on display and prevented logging in. Upgrade strongly recommended.

The full changelog is at https://github.com/LordArma/negaresh/blob/master/CHANGELOG.md

== Upgrade Notice ==

= 5.0.0 =
Adds optional fixing of comments and `wp negaresh status`. Includes the 4.4.0 fix for dates written with Persian digits.

= 4.4.0 =
Fixes Persian and Arabic digit dates being scrambled. Please upgrade.

= 4.1.0 =
4.0.0 damaged how posts were displayed and blocked logins. Please upgrade.
