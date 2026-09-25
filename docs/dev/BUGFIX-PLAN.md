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
  real assertions marked incomplete in `tests/Unit/VirastarKnownBugsTest.php`.

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
- [ ] **B2 `decode_html_entities` is destructive and unsafe.** **Next.** Blocks releasing B1. It replaces entities with an HTML
  placeholder instead of decoding; a real decode of `&lt;script&gt;` into `<script>` would be
  an XSS vector. *Fix:* force it off in the plugin, remove the setting (migration drops the
  option), or decode only a safe allow list (never `&lt; &gt; &amp; &quot;`).
- [ ] **B3 Shortcodes and code are rewritten.** The filter runs before `do_shortcode` and
  brackets are not preserved, so `[gallery ids="1,2"]` becomes `[gallery ids= «1, 2»]`.
  Contents of `<pre>`, `<code>`, `<kbd>`, `<script>`, `<style>`, `<textarea>` are also edited.
  *Fix:* process text nodes only and skip those elements entirely (see I2 for the proper
  approach; minimum for v4.1 is to protect `[...]` and those element blocks before cleanup).
- [ ] **B4 Fatal error with GDIC theme** (or anything else shipping Virastar): both declare
  `Alirezasedghi\Virastar\Virastar`. *Fix:* move the copy into the plugin namespace
  (`Negaresh\Vendor\Virastar`) or guard with `class_exists()`; prefer the namespace, because
  another copy could be an older, differently behaving version.

## 2. High

- [ ] **B5 Defaults never apply on the front end.** `register_setting()` runs only on
  `admin_init`. *Fix:* one defaults array in code, read with `get_option($key, $default)` or via
  a single options array merged with defaults; seed on activation.
- [ ] **B6 `nl2br()` after `wpautop()`** doubles line breaks and injects `<br />` into `<pre>`,
  lists, tables. *Fix:* remove `nl2br()`; return Virastar output as is.
- [ ] **B7 Exception message echoed into the page** at an arbitrary position, unescaped.
  *Fix:* catch `\Throwable`, return the original `$content`, `error_log()` only when `WP_DEBUG`.
- [ ] **B8 Global functions `settings()` and `checkboxHTML()`.** Generic names cause
  "Cannot redeclare" fatals. *Fix:* make them methods of the settings class.
- [ ] **B9 Unprefixed option keys** (`fix_dashes`, `normalize_eol`, ... 30 rows in `wp_options`).
  *Fix:* store one `negaresh_options` array; migration on upgrade copies old keys then deletes
  them; bump an internal `negaresh_db_version`.

## 3. Medium

- [ ] **B10 Filter runs everywhere** `the_content` fires: admin list previews, REST
  `content.rendered`, feeds, and posts that are not Persian. *Fix:* skip `is_admin()` (except
  AJAX preview), make feeds/REST a setting, allow a per post type list.
- [ ] **B11 Empty assets enqueued on every page** with handles `yts-*` and a hardcoded
  `/negaresh/` path. *Fix:* delete `negaresh-scripts.php`, `css/`, `js/` until something needs them.
- [ ] **B12 Relative `include('Virastar.php')`** depends on `include_path`. *Fix:* `__DIR__ . '/...'`
  and a `NEGARESH_PATH` constant.
- [ ] **B13 Copy/paste leftovers**: settings group `wordcountplugin`, section `wcp_first_section`;
  checkbox `name` not escaped. *Fix:* rename to `negaresh`, `esc_attr()`.
- [ ] **B18 Hidden Virastar rules.** 14 Virastar options are never passed, so
  `cleanup_kashidas`, `cleanup_extra_marks`, `markdown_normalize_lists`, `markdown_normalize_braces`,
  `kashidas_as_parenthetic` run on HTML without the admin knowing. *Fix:* pass every option
  explicitly; markdown ones off for HTML.
- [ ] **B19 No uninstall cleanup.** *Fix:* `uninstall.php` deletes plugin options (old and new keys).

- [ ] **B20 Literal `\x{...}` in replacement strings** *(found session 2)*. PHP does not expand
  `\x{061F}` in a single quoted replacement, so the text `\x{061F}` is written into the post.
  Hits `fixQuestionMark` (every `?`), `cleanupZWNJ` (soft hyphen and repeated ZWNJ),
  `cleanupRLM`, `fixSuffixSpacingHamzeh` (Virastar.php ~590, 592, 702, 768, 801); line ~817 also
  uses `$2` with no second group. The rules are off by default in the plugin, so severity is
  High only for sites that enabled them. *Fix:* use the real characters or `"\u{061F}"`.
  Tests waiting in `VirastarKnownBugsTest`. Also check the `$entities` name table (same problem, B2).

## 4. Low

- [ ] **B21 Front matter never preserved** *(found session 2)*. The PHP port dropped the JS
  `' ' + text + ' '` padding, so `/^ ---/` never matches. Irrelevant for WordPress HTML; fix only
  if cheap (drop the leading space from the regex). Test waiting in `VirastarKnownBugsTest`.

- [ ] **B14 Untranslatable / meaningless labels.** 12 fields call `__($feild_title)` with the raw
  option key (`cleanup_rlm`, `fix_suffix_misc`, ...). *Fix:* literal, descriptive labels.
  Also "It's" → "Its", method `fix_farsi_typoes`, variable `$feild_name` spellings.
- [ ] **B15 Stale translations.** Regenerate `negaresh.pot` with `wp i18n make-pot`, update
  `fa_IR.po`, fill every `msgstr`, recompile `.mo`.
- [ ] **B16 Plugin header**: `Tested up to: 6.1.1`, `Requires PHP: 7.0` (tests will run on 7.4+),
  `Author URI` http. Update after the test matrix is decided.
- [ ] **B17 README mismatch**: says download from Releases, CI only produces a workflow artifact
  (fixed properly by I7; for now correct the README or attach the zip to the v4.1.0 release by hand).

## Suggested order

~~B0~~ → ~~B1~~ → B2 → B20 → B4 → B3 → B6 → B7 → B5 + B9 (same refactor) → B8 → B12 → B13 → B18 → B10 → B11 →
B19 → B14 → B15 → B16 → B17 → B21 → tag v4.1.0.
