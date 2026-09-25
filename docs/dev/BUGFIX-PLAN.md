# Phase 1: bug fix plan

Work top to bottom. Each fix: write the failing test first, fix, tick the box, log it in
`PROGRESS.md`. Target release: **v4.1.0** (bug fix release) once every Critical and High item is done.

Severity: **Critical** = breaks sites or content. **High** = wrong behaviour users will hit.
**Medium** = robustness / hygiene. **Low** = cosmetic.

## 0. Prerequisite

- [x] **B0 Test harness.** *(done 2026-09-25, session 2)* Add `composer.json` (dev only) with PHPUnit and a `tests/` folder.
  Start with plain unit tests for Virastar (no WordPress needed) plus Brain Monkey for the
  WordPress hooks. Add a `tests/fixtures/` set of real Persian post HTML (paragraphs, links,
  shortcodes, `<pre><code>`, entities, `&nbsp;`, images, Gutenberg block comments).
  Keep `vendor/`, `tests/`, `composer.*` out of the shipped zip.
  *Result:* PHPUnit 9.6 + Brain Monkey 2.6, platform pinned to PHP 7.4, all dev files at the repo
  root (outside the zipped plugin folder). `composer test` runs the suite. Open bugs are kept as
  real assertions marked incomplete in a `...KnownBugsTest.php` file when a bug is found before
  it is fixed (none open right now).

## 1. Critical

- [x] **B1 Virastar deletes all preserved content.** *(done 2026-09-25, session 2; ⚠ ship together with B2)* Closures capture stores by value, push the
  whole match array, and entity restore reads the wrong table (ANALYSIS §3).
  *Fix:* check upstream first; otherwise patch: `use (&$html)`, store `$matched[0]`, restore
  entities from the captured list. Record the patch in ANALYSIS §4.
  *Test:* fixture HTML round trips with only text nodes changed.
  *Result:* upstream `master` is byte identical to our copy (bug is upstream, last change 2022), so
  patched locally; see ANALYSIS §4. Also fixed `fixPunctuationSpacing` backtracking past its
  "not before a preserver" guard (added a space before `</p>`). Plain text output verified
  identical to the original on 20 samples. Tests: `tests/Unit/VirastarPreservationTest.php`.
  **Do not release B1 without B2:** with `decode_html_entities` on (plugin default) every entity
  adds a fake HTML placeholder, so tags are restored in the wrong places (`A</p>B`).
- [x] **B2 `decode_html_entities` is destructive and unsafe.** *(done session 3: library `decodeHTMLEntities()` is a no-op; setting removed, always passed as `false`, migration drops the old row)* It replaces entities with an HTML
  placeholder instead of decoding; a real decode of `&lt;script&gt;` into `<script>` would be
  an XSS vector. *Fix:* force it off in the plugin, remove the setting (migration drops the
  option), or decode only a safe allow list (never `&lt; &gt; &amp; &quot;`).
  *Decision (session 3, user said "do what you know is best"):* remove the setting and force it off.
- [x] **B3 Shortcodes and code are rewritten.** *(done session 3, plugin refactor slice)* The filter runs before `do_shortcode` and
  brackets are not preserved, so `[gallery ids="1,2"]` becomes `[gallery ids= «1, 2»]`.
  Contents of `<pre>`, `<code>`, `<kbd>`, `<script>`, `<style>`, `<textarea>` are also edited.
  *Fix:* process text nodes only and skip those elements entirely (see I2 for the proper
  approach; minimum for v4.1 is to protect `[...]` and those element blocks before cleanup).
  *Result:* `Negaresh::fix()` swaps protected elements (with contents) and shortcode tags found
  between HTML tags for tag shaped placeholders that Virastar preserves, then puts them back.
  Shortcode names must start with a Latin letter, so Persian text in brackets is still fixed.
- [x] **B4 Fatal error with GDIC theme** *(done session 3: namespace `Negaresh\Vendor\Virastar`)* (or anything else shipping Virastar): both declare
  `Alirezasedghi\Virastar\Virastar`. *Fix:* move the copy into the plugin namespace
  (`Negaresh\Vendor\Virastar`) or guard with `class_exists()`; prefer the namespace, because
  another copy could be an older, differently behaving version.

## 2. High

- [x] **B5 Defaults never apply on the front end.** `register_setting()` runs only on
  `admin_init`. *Fix:* one defaults array in code, read with `get_option($key, $default)` or via
  a single options array merged with defaults; seed on activation.
  *Result:* `Negaresh_Settings::get()` merges saved values over code defaults; no seeding needed.
  ⚠ Behaviour change: sites that never saved the 4.0 settings page ran with every rule off;
  after upgrading, the default rules apply. Mention in the changelog.
- [x] **B6 `nl2br()` after `wpautop()`** doubles line breaks and injects `<br />` into `<pre>`,
  lists, tables. *Fix:* remove `nl2br()`; return Virastar output as is.
- [x] **B7 Exception message echoed into the page** at an arbitrary position, unescaped.
  *Fix:* catch `\Throwable`, return the original `$content`, `error_log()` only when `WP_DEBUG`.
  *Result:* done, plus: invalid UTF-8 or content without Arabic script letters is returned untouched,
  and an empty result falls back to the original.
- [x] **B8 Global functions `settings()` and `checkboxHTML()`.** Generic names cause
  "Cannot redeclare" fatals. *Fix:* make them methods of the settings class.
- [x] **B9 Unprefixed option keys** *(done session 3, plugin refactor slice)* (`fix_dashes`, `normalize_eol`, ... 30 rows in `wp_options`).
  *Fix:* store one `negaresh_options` array; migration on upgrade copies old keys then deletes
  them; bump an internal `negaresh_db_version`.
  *Result:* runs on `plugins_loaded` when `negaresh_db_version` < 2. Legacy `'1'` → `true`, `''` → `false`.
  Never overwrites an existing `negaresh_options`.

## 3. Medium

- [x] **B10 Filter runs everywhere** *(done session 3, plugin refactor slice)* `the_content` fires: admin list previews, REST
  `content.rendered`, feeds, and posts that are not Persian. *Fix:* skip `is_admin()` (except
  AJAX preview), make feeds/REST a setting, allow a per post type list.
  *Result:* settings "Where to apply": post types (none checked = all), feeds (on), REST (on).
- [x] **B11 Empty assets enqueued on every page** with handles `yts-*` and a hardcoded
  `/negaresh/` path. *Fix:* delete `negaresh-scripts.php`, `css/`, `js/` until something needs them.
- [x] **B12 Relative `include('Virastar.php')`** depends on `include_path`. *Fix:* `__DIR__ . '/...'`
  and a `NEGARESH_PATH` constant. *Result:* `__DIR__` everywhere; constants `NEGARESH_VERSION`, `NEGARESH_FILE`.
- [x] **B13 Copy/paste leftovers**: settings group `wordcountplugin`, section `wcp_first_section`;
  checkbox `name` not escaped. *Fix:* rename to `negaresh`, `esc_attr()`.
- [x] **B18 Hidden Virastar rules.** *(done session 3, plugin refactor slice)* 14 Virastar options are never passed, so
  `cleanup_kashidas`, `cleanup_extra_marks`, `markdown_normalize_lists`, `markdown_normalize_braces`,
  `kashidas_as_parenthetic` run on HTML without the admin knowing. *Fix:* pass every option
  explicitly; markdown ones off for HTML.
  *Result:* `cleanup_kashidas`, `kashidas_as_parenthetic`, `cleanup_extra_marks` are now visible
  settings (default on, as before); the other 12 are fixed in `Negaresh::virastar_options()`
  (markdown off, preserve on, front matter off, brackets off because `fix()` protects shortcodes).
- [x] **B19 No uninstall cleanup.** *Fix:* `uninstall.php` deletes plugin options (old and new keys).
  *Result:* also on every site of a multisite network.

- [x] **B20 Literal `\x{...}` in replacement strings** *(done session 3)* *(found session 2)*. PHP does not expand
  `\x{061F}` in a single quoted replacement, so the text `\x{061F}` is written into the post.
  Hits `fixQuestionMark` (every `?`), `cleanupZWNJ` (soft hyphen and repeated ZWNJ),
  `cleanupRLM`, `fixSuffixSpacingHamzeh` (Virastar.php ~590, 592, 702, 768, 801); line ~817 also
  uses `$2` with no second group. The rules are off by default in the plugin, so severity is
  High only for sites that enabled them. *Fix:* use the real characters or `"\u{061F}"`.
  *Result:* all five replacements use `"\u{...}"`; `fixSuffixMisc` uses a lookahead instead of the
  missing `$2`. The `$entities` name table still holds `'\x{...}'` text but is only used by the
  disabled decoder (B2). Tests: `tests/Unit/VirastarFixesTest.php`.

## 4. Low

- [x] **B21 Front matter never preserved** *(done session 3)* *(found session 2)*. The PHP port dropped the JS
  `' ' + text + ' '` padding, so `/^ ---/` never matches. Irrelevant for WordPress HTML; fix only
  if cheap (drop the leading space from the regex). *Result:* fixed by B22 (padding restored,
  upstream regex kept), test in `VirastarFixesTest`.

- [x] **B29 Persian digit dates scrambled: `۳/۱/۱۳۵۵` → `۱۳/۱/۳۵۵`** *(Critical: data corruption with
  the default rules, stored in save mode; every release up to 4.3.0; found session 3 by the I11
  reference suite; fixed on `phase-2`)*. Patterns with Persian digits (dates, numeral symbols, time
  and number spacing) and the per word tokenizer had no `/u`, so PCRE matched bytes and split the
  two byte digits (and `«»`). *Fix:* `/u` on every pattern. Tests: `VirastarFixesTest::testB29...`.

- [x] **B28 Every multi step rule ran its steps in reverse** *(High; every release; found session 3
  by the I11 reference suite)*. The PHP port nested `preg_replace()` calls for the JS
  `.replace().replace()` chains, and the innermost runs first. Visible: `---` → `–-` (default rule),
  `!!!!?????` → `!?!?`, `۱۱ـ۲۳` → `۱۱۲۳`, times getting a space, suffixes half joined, stacked
  diacritics removed. *Fix:* rule functions re-ported step by step from Virastar.js 0.22.1 (I11).
  Tests: `VirastarFixesTest::testB28...`, `VirastarReferenceTest`.

- [x] **B30 sprintf directives lost digits (`%1$s` → `%۱$s`)** *(Low)*: the pattern sat in a PHP
  double quoted string, which ate the backslash of `\$`. Fixed with single quotes.

- [x] **B27 Display mode ran after `wptexturize`, so ellipsis and quote rules never worked**
  *(Medium; in 4.0 and 4.1.0; found session 3 by the I4 e2e check; fixed on `phase-2`)*.
  `the_content` priority 10 put Negaresh after `wptexturize`, which had already turned `...` into
  `&#8230;` and straight quotes into `&#8220;`/`&#8221;` entities, which Virastar preserves.
  *Fix:* priority 9 (after `do_blocks`, before `wptexturize`, `wpautop`, `do_shortcode`).
  Tests: `NegareshFilterTest::testConstructorRegistersHooks`, e2e "display mode fixes the page".

- [x] **B26 Trailing space after `…` at a line end** *(Low; found session 3 while building I4)*.
  `normalizeEllipsis` always put a space after `…`, also before a line break. Invisible on display,
  but save mode (I4) would store it in every classic editor line ending with `...`.
  *Fix:* library patch; test `VirastarFixesTest::testB26...`.

- [x] **B24 A `>` inside an attribute corrupts the tag** *(High; found session 3 while re-checking I2;
  in 4.1.0; fixed by I2 on `phase-2`)*. Virastar's tag regex `<\/?[a-z][^>]*?>` stops at the first
  `>`, so the rest of the tag (`b" src="x.png">`) was treated as text; with a quote rule on it became
  `«src=» x. png «>` and the HTML broke. *Fix:* the plugin tokenizes markup itself with a quote
  aware pattern and never hands tags to Virastar. Tests: `HtmlProcessingTest`.

- [x] **B25 Space lost between `…` and a following inline tag** *(High, default rules; found session 3;
  in 4.1.0; fixed by I2 on `phase-2`)*. `متن ... <em>` became `متن…<em>` (words run together):
  `normalizeEllipsis` collapsed the real space and Virastar's space padded placeholder, then the
  restore step ate the remaining one. *Fix:* text nodes are fixed one by one and their leading and
  trailing whitespace is kept byte for byte. Tests: `HtmlProcessingTest`.
  **B24, B25 and B27 are in the released 4.1.0: ship 4.2.0 soon.** (done: 4.2.0)
  **B28, B29 and B30 are in every release up to 4.3.0: ship 4.4.0 soon (B29 corrupts dates).**

- [x] **B23 Output before `<?php` breaks logins, redirects and feeds** *(Critical; found in the
  Docker end to end run and already fixed by the refactor, session 3)*. 4.0's `negaresh-class.php`
  starts with a newline before `<?php`. It is sent on every request: `wp-login.php` fails with
  "headers already sent" (nobody can log in while 4.0 is active), redirects after saving settings
  fail, and every page and RSS feed starts with a stray newline (a feed with anything before
  `<?xml` is invalid XML). *Test:* `PluginFilesTest` checks every PHP file starts with `<?php` and
  has no closing `?>`; `tests/e2e/run.sh` checks login, doctype and feed XML.

- [x] **B22 Missing space padding in the PHP port** *(found and done session 3)*. The JS Virastar
  pads the text with one space each side before the rules and strips it after; the PHP port kept
  only the stripping. Rules that need a neighbouring space (suffixes ها/تر, hamzeh, Arabic hamzeh)
  missed the first/last word of every text. Padding restored; only end of text results change.

- [x] **B14 Untranslatable / meaningless labels.** 12 fields call `__($feild_title)` with the raw
  option key (`cleanup_rlm`, `fix_suffix_misc`, ...). *Fix:* literal, descriptive labels.
  Also "It's" → "Its", method `fix_farsi_typoes`, variable `$feild_name` spellings.
  *Result:* new descriptive labels in five sections, each with a before → after example; every
  example was checked against the real rule (23/23).
- [x] **B15 Stale translations.** *(done session 3)* Regenerate `negaresh.pot` with `wp i18n make-pot`, update
  `fa_IR.po`, fill every `msgstr`, recompile `.mo`.
  *Result:* 47 strings, all translated; `I18nTest` keeps source, `.pot`, `.po` and `.mo` in sync;
  verified in wp-admin with the site language set to fa_IR.
- [x] **B16 Plugin header** *(done session 3)*: `Tested up to: 6.1.1`, `Requires PHP: 7.0` (tests will run on 7.4+),
  `Author URI` http. Update after the test matrix is decided.
  *Result:* Requires at least 5.8, Requires PHP 7.4, Tested up to 7.1, https Author URI, License
  lines. Backed by `tests/e2e/run.sh` passing on WP 5.8.3/PHP 7.4.27 and WP 7.1.2/PHP 8.3.33,
  and the unit suite on PHP 7.4.33 and 8.3.6.
- [x] **B17 README mismatch** *(done session 3)*: says download from Releases, CI only produces a workflow artifact
  (fixed properly by I7; for now correct the README or attach the zip to the v4.1.0 release by hand).
  *Result:* the v4.0.0 GitHub release does have `negaresh.zip` attached, so the README link is right;
  README rewritten (requirements, features, development). Until I7, attach the zip by hand when
  releasing. `CHANGELOG.md` added.

## Suggested order

**Phase 1 complete and released as 4.1.0 on 2026-09-25**
(https://github.com/LordArma/negaresh/releases/tag/v4.1.0). Still worth doing some day: send
the Virastar patches upstream.

From session 3 the plugin's own code (B2 plugin half, B3, B5 to B19) is done as one refactor
slice, since every item touches the same three files.
