# Phase 2: improvement plan

Started 2026-09-25 (session 3) after 4.1.0 was released. Work happens on branch `phase-2`.
Each item lists the goal, the approach, and what "done" means.

**User decisions (2026-09-25):**
- I4: yes, the user prefers fixing text *before saving* (stored content is corrected).
- I9: submit to wordpress.org, **but not now**; keep the code ready for it (Plugin Check clean).

**Order:** I1 → I7 → I2 → I4 → I5 → I6 → I11 → I8 leftovers → I3 (only if needed) → I9 (when asked).
I10 needs user decisions first.

## I1 Tooling and quality gates ✅ *(done session 3; CI not yet run on GitHub, see PROGRESS)*
- Already there (phase 1): Composer, PHPUnit 9.6, Brain Monkey, 74 unit tests, `tests/e2e/run.sh`.
- To add: WPCS (PHPCS), PHPStan with `szepeviktor/phpstan-wordpress`.
- CI workflow: lint + PHPCS + PHPStan + tests on PHP 7.4, 8.1, 8.3; runs on push and pull requests;
  optionally `tests/e2e/run.sh` (GitHub runners have Docker).
- `.editorconfig`, `.gitattributes` with `export-ignore` for dev files.
- **Done when:** CI green, commands documented in CLAUDE.md §4.
- *Result:*
  - PHPCS (`phpcs.xml.dist`): PSR-12 formatting (the existing style; full WordPress formatting was
    rejected as churn with no gain) + WordPress Security, I18n, PrefixAllGlobals, DB, PHP rules +
    PHPCompatibilityWP for PHP 7.4+ (vendored Virastar: compatibility only). Clean.
  - PHPStan level 8 (`phpstan.neon.dist`) with `szepeviktor/phpstan-wordpress`; Virastar only
    scanned for symbols; `treatPhpDocTypesAsCertain: false` because Virastar's docblocks promise
    `string` where `preg_*` can return null. Clean.
  - Findings fixed: `fix()` now checks every `preg_*` result and throws (→ original content) instead
    of passing null to `implode()`. Not reachable with today's patterns (PCRE auto-possessifies
    them; a backtrack-limit repro did not trigger), so hardening, not a numbered bug.
    `Negaresh_Settings::get()` now normalizes stored values (flags → bool, `post_types` → list of
    strings), new `rules()` and `post_types()` accessors; a corrupt `post_types` string used to
    reach `in_array()`.
  - `composer lint | cs | cs:fix | stan | test | check`.
  - `.github/workflows/ci.yml`: cs + stan + tests on PHP 7.4, 8.1, 8.3, 8.4, then
    `tests/e2e/run.sh` on the latest WordPress and on 5.8 (minimum). The old
    `main.yml` (zip artifact) stays until I7.
  - Verified locally: all checks on PHP 7.4.33, 8.3.6, 8.4; e2e on WP 7.1.2 and 5.8.3.

## I2 HTML aware processing (the real fix behind B3) ✅ *(done session 3, fixes B24 and B25)*
- Walk the markup and run Virastar on **text nodes only**. Options: `WP_HTML_Tag_Processor`
  (WP 6.2+), `wp_html_split()` (older WP), or `DOMDocument` with UTF-8 handling.
- Skip subtrees: `pre, code, kbd, samp, script, style, textarea, svg, math`. (`.negaresh-skip` /
  `data-negaresh="off"` moved to I6.)
- Handle text split across inline tags (`<strong>` inside a word) without breaking ZWNJ rules.
- **Done when:** fixtures show markup byte identical outside text nodes.
- *Result:* chose a small tokenizer over DOMDocument/`WP_HTML_Tag_Processor` (needs WP 6.2; the
  minimum is 5.8) and over `wp_html_split()` (same first-`>` weakness). `Negaresh::fix()` splits on
  `MARKUP_PATTERN` (comments, CDATA, declarations, tags with quoted attributes), skips protected
  elements with nesting (raw text elements `script/style/textarea` end at the first end tag; an
  unclosed one leaves the rest of the post untouched), splits text on shortcodes, and runs Virastar
  per text piece keeping edge whitespace (incl. ZWNJ, nbsp, direction marks).
  Text with Latin letters and no Arabic script is skipped (English sentences keep their quotes and
  digits); neutral text such as `123` is fixed.
  Decided against: rules working across inline tags (`کتاب <b>ها</b>` is not joined; the plugin
  should not restructure markup). The `.negaresh-skip` / `data-negaresh="off"` idea moves to I6.
  Cost: 156 KB post 34 ms → 55 ms (display mode only; I4 save mode removes display work).
  Tests: `HtmlProcessingTest` (21). All 13 e2e checks pass on WP 7.1.2 and 5.8.3.

## I3 Codebase structure ✅ *(not needed further, session 3)*
- Partly done in phase 1 (classes `Negaresh`, `Negaresh_Settings`, constants, own Virastar namespace).
- Remaining: namespaced classes under `src/` if the plugin grows; keep this optional.
- *Status:* one class per concern now (`Negaresh_Settings`, `Negaresh`, `Negaresh_Editor`,
  `Negaresh_Bulk`, `Negaresh_Bulk_Page`, `Negaresh_CLI`, Virastar fork); nothing more is needed now.
- `src/` with `Plugin`, `Settings` (single source of option definitions: key, label, description,
  default, group), `Processor` (wraps Virastar + I2), `Migration`.
- Composer autoload (classmap, shipped) or a tiny PSR-4 autoloader; Virastar scoped under
  `Negaresh\Vendor` (PHP-Scoper or a documented manual prefix).

## I4 Fix before saving (user wants this) + performance ✅ *(save mode done session 3; caching open)*
- ~~Build the Virastar instance once per request~~ (done in phase 1).
- Cache processed output: key = hash(content + options + plugin version), object cache / transient,
  invalidated on `save_post` and option update.
- **"Fix before saving" mode** (user decision 2026-09-25): correct title/content when a post is
  saved (`wp_insert_post_data`, covers the block editor through REST), so the database holds the
  fixed text and display time work disappears. Not reversible, so: a clear setting, revisions keep
  the original, skip autosaves where sensible, and the per post opt out from I6 applies.
  Display mode stays available; decide the default for new installs when building it.
- *Result:* setting "When to fix" (`mode`: `save` | `display`). **New installs: save** (user's
  preference). **Upgrades from 4.x keep display** (DB_VERSION 3 migration), so no existing site
  starts rewriting posts without choosing it. `wp_insert_post_data` fixes `post_content` (classic,
  block editor via REST, wp-cli, importers) for the chosen post types, else public types with an
  editor plus `wp_block`; never revisions, attachments, menus, templates, global styles, fonts,
  navigation, changesets. Titles/excerpts not included (I5 scope item). `save_post` stores the
  rules hash in `_negaresh_fixed`; display skips posts whose hash matches, so old posts (or posts
  fixed with other rules) are still fixed on display until saved again. Revisions hold the
  corrected text (the typed original is not kept; the setting says it cannot be undone).
  Found and fixed on the way: B26 (trailing space stored), B27 (display ran after wptexturize).
  Verified: 122 unit tests (`SaveModeTest` 18 new), e2e 21/21 on WP 7.1.2 and 5.8.3 (wp-cli save,
  REST save, display mode, markers removed on uninstall), 4.1.0 → new upgrade keeps display mode.
- Still open: caching display output (below), the bulk tool to fix existing posts (I6).

## I5 Settings page UX ✅ *(done session 3)*
- Group rules into sections (Characters, Numbers, Punctuation, Spacing, Cleanup) with a short
  Persian and English description and a before/after example per rule.
- Live preview box: paste text, see result with the current (unsaved) toggles (REST endpoint,
  nonce, `manage_options`).
- "Reset to defaults" button, "Settings" link on the Plugins screen, RTL friendly layout.
- Scope settings: ~~post types, feeds, REST~~ (done in phase 1); still open: titles, excerpts,
  comments, widgets.
- ~~Sections and examples~~ (done in phase 1); still open: longer descriptions, live preview.
- *Result:*
  - Live preview ("Try it") at the top of the page: fixes as you type, with the boxes as they are
    checked on the page (unsaved); REST `POST negaresh/v1/preview` {text, rules}, admins only
    (`manage_options`), text up to 50 000 bytes; a missing rule counts as off, like an unchecked
    box. Script `assets/admin.js` (+ `admin.css`) loaded on this page only, `wp.apiFetch` handles
    the nonce, late answers are dropped.
  - "Reset rules to defaults": second submit button of the same form, handled in `sanitize()`
    (keeps mode and "where to apply"); asks for confirmation first.
  - "Settings" link first on the Plugins screen.
  - New scope options, off by default: "Fix post titles" (`the_title` at 9 and `post_title` on
    save) and "Fix excerpts written by hand" (`the_excerpt` at 9 and `post_excerpt` on save;
    automatic excerpts come from the fixed content). Both are part of the save marker hash.
  - Examples now show `←` (they are RTL, so "before" is on the right); found in the Persian
    screenshot.
  - Not done (not asked): comments and widgets; longer per rule descriptions.
  - Verified: 135 unit tests (`SettingsPageTest` 13 new); e2e 28/28 on WP 7.1.2 and 5.8.3; real
    browser (Playwright, headless Chromium): preview while typing, preview follows unsaved boxes,
    reset asks and dismissing does not submit, no JavaScript errors, in English and in fa_IR.

## I6 Editor integration ✅ *(done session 3)*
- Per post opt out (post meta + checkbox in Gutenberg sidebar and Classic editor meta box).
- Gutenberg: "Fix Persian typography" button that runs the processor on the selected block or
  the whole post via REST and shows a diff before applying.
- Bulk tool (Tools → Negaresh): dry run over posts, show changed count and diffs, apply in batches.
- Optional WP-CLI command `wp negaresh fix [--dry-run] [--post_type=post]`.
- *Result so far (slice I6a+d):*
  - Per post opt out: post meta `_negaresh_skip` (registered for REST, `edit_post` auth).
    Block editor: "Negaresh" panel in the document sidebar (`assets/editor.js`, works on WP 5.8
    via `wp.editPost` and on 6.6+ via `wp.editor`). Classic editor: side meta box
    (`__back_compat_meta_box`, nonce). Opted out posts are not fixed on save or display.
    The save that ticks or unticks the box obeys it: the block editor's choice is read in
    `rest_pre_insert_{type}`, the classic one from the form (nonce checked), because WordPress
    stores meta after `wp_insert_post_data`.
  - Markup skip: elements with class `negaresh-skip` or `data-negaresh="off"` are left alone
    (nesting aware, like protected elements); in the block editor: Advanced → Additional CSS class.
  - "Fix this post now" button in the panel: `POST negaresh/v1/fix` (`edit_posts`) returns
    content and title fixed with the saved rules; the editor applies it with
    `resetEditorBlocks`, so Undo reverts it. Disabled while the post is opted out.
  - Uninstall removes `_negaresh_skip` too.
  - Verified: 150 unit tests (`EditorTest` 15); e2e 32 checks; browser test drives the panel
    (fix, undo, opt out + save) on WP 7.1.2 and 5.8.3; CI now runs the browser test on both.
  - Found in the test harness (not the plugin): `grep -q` in a pipe under `pipefail` hid a FAIL
    and printed ALL PASSED; fixed by capturing the output first.
- *Result, slice I6b (engine + WP-CLI):*
  - `Negaresh_Bulk` (`includes/negaresh-bulk.php`): `find()` returns every candidate ID at once
    (oldest first; save mode post types incl. `wp_block`; publish/future/draft/pending/private;
    never opted out; posts already fixed with the current rules only with `all`), so a run that
    marks posts cannot shift a page. `process($id, $apply)` fixes content, and titles/excerpts when
    enabled, reports before/after per field; applying snapshots the current text with
    `wp_save_post_revision()` first (found by the e2e check: WordPress only stores the NEW text on
    update, so without it the original was not restorable), updates with kses switched off (kses
    is on for users without unfiltered_html, e.g. multisite site admins, and would strip embeds;
    WP-CLI turns it off itself), and marks the post. `diff()` gives changed lines (LCS).
  - WP-CLI (`includes/negaresh-cli.php`, loaded only under WP-CLI): `wp negaresh fix [<id>...]
    [--post_type=] [--all] [--limit=] [--apply] [--diff] [--format=table|csv|json|count]` is a dry
    run unless `--apply`; `wp negaresh text [<text>]` (or stdin).
  - Plugin instances are real globals now (`$GLOBALS['negaresh']`, `negaresh_settings`,
    `negaresh_editor`, `negaresh_bulk`): WP-CLI includes plugin files inside a function, so the
    old top level variables were local there.
  - Verified: 165 unit tests (`BulkTest` 11); e2e 43 checks on WP 7.1.2 and 5.8.3 (+ browser):
    dry run saves nothing, apply fixes, the original is in the revisions, marker set, embeds kept
    with kses on (proven: the check fails with the kses switch removed), opted out untouched,
    `wp negaresh text`. `run.sh` now reports the line where it stops instead of ending silently.
- *Result, slice I6c (bulk tool page):* Tools → Negaresh (`includes/negaresh-bulk-page.php`,
  `assets/bulk.js`): choose post types (and "also posts already fixed"), **Scan** (nothing saved;
  batches of 10 through `POST negaresh/v1/bulk/process` with `apply: false`) lists posts that
  would change with their changed lines, **Fix all listed posts** (confirmation) fixes exactly
  those in batches and ticks each row. Routes are `manage_options` only, and each post is also
  checked with `edit_post`; only changed lines travel to the browser. Count messages are worded
  without plurals ("Posts fixed: 3"), which reads right in English and Persian.
  Verified: 172 unit tests (`BulkPageTest` 5); e2e 4 REST checks; browser: scan lists the post,
  shows the diff, scanning saves nothing, fixing stores the fixed text, on WP 7.1.2 and 5.8.3.

## I7 Release pipeline ✅ *(done session 3; workflows not yet run on GitHub)*
- On tag `v*`: build zip with only the plugin folder (respecting `export-ignore`), attach to a
  GitHub Release, generate release notes from `CHANGELOG.md`.
- Keep the existing push artifact for testing builds.
- ~~`CHANGELOG.md`~~ (added in phase 1); keep it updated with every change.
- *Result:* `bin/build-zip.sh` (git archive of the plugin folder under `negaresh/`; identical
  layout to the hand built 4.1.0 zip), `bin/release-notes.sh` (CHANGELOG section without
  internal IDs + install text from the header), `.github/workflows/release.yml` (on `v*` tag:
  tag = header Version = `NEGARESH_VERSION` and a dated CHANGELOG section, `composer check`,
  e2e, build, `gh release create`). `main.yml` replaced by a `package` job in `ci.yml` that
  uploads the same layout as an artifact. `PluginFilesTest` checks the versions agree.
  Found while testing locally: `[ a ] && [ b ]` does not stop a `bash -e` step, so a wrong tag
  could have passed; rewritten as an explicit `if`.

## I8 Documentation and i18n ✅ *(done session 3)*
- `readme.txt` in wordpress.org format (description, FAQ, screenshots, changelog).
- `README.fa.md` for Persian readers; update screenshot.
- ~~Complete `fa_IR` translation~~ (done in phase 1).
- *Result:* `wp-content/plugins/negaresh/readme.txt` in wordpress.org format (ships in the zip;
  `PluginFilesTest` keeps Stable tag / Requires / Tested up to equal to the plugin header and
  requires a changelog entry for the version); README rewritten (features, WP-CLI, development);
  `README.fa.md` added; `screenshot.png` replaced with the real Persian settings page
  (made by `tests/e2e/browser.mjs` as `build/shots/readme-fa.png`, run with `LANG_FA=1`).
  Preview boxes are `dir="auto"` now (HTML pasted in RTL showed mirrored tags).
  **Check before I9:** `Contributors: lordarma` in readme.txt must be the real wordpress.org
  username.

## I9 wordpress.org readiness ⏳ *(technically ready in 5.0.0; submission is the user's step)*
- Plugin Check (`wp plugin check`) clean, GPL compatible headers, no external calls, sanitisation
  and escaping audit, unique prefix audit. Submit.
- *Done for 5.0.0 (user: "make the plugin structure able to publish on wordpress.org, call it
  version 5"):* Plugin Check 2.1.0 finds nothing, experimental checks included (was: 1 error, the
  unescaped `gettype()` in Virastar's exception; 1 warning, `load_plugin_textdomain`, kept with a
  justified ignore: WP 5.8.3 does not load the bundled translation without it, WP 7.1.2 does;
  both verified on real sites). `license.txt` and `includes/Virastar-LICENSE.txt` (MIT notices of
  the PHP port and Virastar.js) ship in the zip. `.wordpress-org/` holds icon 128/256, banners
  772×250/1544×500 and 3 screenshots, made by `tests/e2e/wporg-assets.sh`. The Release workflow
  has a `wordpress-org` job (10up deploy action) and `wordpress-org-assets.yml` updates the
  listing; both skip until `SVN_USERNAME`/`SVN_PASSWORD` secrets exist. The plugin folder layout
  already matches what wordpress.org expects (main file, readme.txt, uninstall.php, languages/).
- **Left for the user:** a wordpress.org account; set `Contributors:` in readme.txt to that
  username; submit the zip at https://wordpress.org/plugins/developers/add/ (slug `negaresh`);
  after approval add the two SVN secrets to the GitHub repository. The next tag then deploys.

## I10 Stretch ideas ✅ *(user 2026-09-25: "yes do all I10"; done session 3)*
- [x] **I10a** Dashboard widget with the counts (fixed / waiting / opted out) and a link to the bulk
  tool; a dismissible notice when existing posts are waiting. Counts cached (1 hour).
  *Result:* `Negaresh_Dashboard` (`includes/negaresh-dashboard.php`): widget for `manage_options`;
  notice on Dashboard and Plugins only, dismissed per user (user meta, `check_admin_referer`);
  counts in the `negaresh_stats` transient, cleared on `save_post` and settings changes; uninstall
  removes both. Tests: `DashboardTest` (6), e2e (widget, notice, forged dismiss 403, dismissal).
- [x] **I10b** Words to leave alone: a list on the settings page; matches are never changed
  (treated as boundaries, like shortcodes); the preview uses the list from the page.
  *Result:* option `protected_words` (one per line; trimmed, unique, ≤500 × ≤100 chars), part of
  the rules hash. Matches are whole words only (no letter, combining mark or ZWNJ on either side).
  Each match becomes a Latin placeholder word while Virastar runs (so spacing and punctuation
  rules still work around it; Virastar leaves Latin words alone), then is restored; if a
  placeholder does not come back, or the text already contains the placeholder spelling, the
  text is split at the words instead. Tests: `ProtectedWordsTest` (9), e2e (stored post), browser
  (preview with the typed list).
- [x] **I10c** Multisite: network defaults set in Network Admin; sites that never saved their own
  settings (and new sites) use them.
  *Result:* network option `negaresh_network_options`; `get()` layers site values over network
  values over code defaults, per key. Network Admin → Settings → Negaresh (`manage_network_options`)
  renders the same fields from the network values and saves through `network_admin_edit_negaresh_network`
  (nonce with `check_admin_referer`, then the same `sanitize()`). Uninstall deletes it on multisite.
  Tests: `NetworkTest` (6); `tests/e2e/multisite.sh` (13 checks: page, forged nonce 403, save,
  new site inherits mode and rules, stored as typed, page fixed, site setting wins, debug.log,
  network uninstall) on WP 7.1.2 and 5.8.3, in CI after run.sh.

## I11 Replace or rewrite Virastar (decide after I2) ✅ *(done session 3)*
- The user allowed replacing or rewriting the vendored library (session 3).
- Why not now: ~1000 lines of Persian typography regexes with no upstream tests; a rewrite in the
  bug fix release would trade known bugs for unknown ones.
- When: after I2 (text node processing) lands, the preserve/restore placeholder machinery is no
  longer needed. Then either slim the vendored copy down to the text rules, or rewrite rule by rule
  with a test per rule (port the JS Virastar test suite as the reference).
- **Done when:** every rule has tests, and no `Negaresh patch` markers remain because the code is ours.
- *Result (definition adjusted):* ported the JS test suite first (159 cases, extractor in
  `tests/tools/`), which showed the PHP port's defects: reversed rule steps (B28), missing `/u`
  (B29, scrambled Persian dates), sprintf check (B30). Re-ported all rule functions from
  Virastar.js 0.22.1 instead of rewriting from scratch: 156/159 reference cases pass (was 134;
  upstream PHP 116), 3 documented deviations. The `Negaresh patch` markers stay on purpose: they
  document where we differ from upstream (security, HTML handling), and the file is now our
  maintained fork. With the plugin's default rules 8 of 162 corpus texts change, all fixes.
  New rule "Remove the space before an ellipsis" (JS option, default on) keeps `متن ...` → `متن…`.
  Note: the rules hash changes (new rule), so posts fixed on save by 4.3.0 are checked again on
  display and listed again by the bulk tool; that is wanted, the rules improved.

---

# Phase 3: further improvements (session 3, after 4.4.0)

User: "do anything if exist; if not think about what improvements we can have and then do them;
start with easy ones". Easy first. Each item: done when tests (unit and/or e2e) cover it.

- [x] **P3-1** `.gitattributes` (`eol=lf`) and `.editorconfig`: the checkout lives on a Windows drive.
- [x] **P3-2** Dependabot for GitHub Actions and Composer (monthly, grouped).
- [x] **P3-3** "Fix existing posts" link (Tools → Negaresh) next to "Settings" on the Plugins screen.
- [x] **P3-4** `composer audit` in CI (PHP 8.3 job; clean today).
- [x] **P3-5** `wp negaresh status`: posts fixed with the current rules, waiting, opted out; mode.
- [x] **P3-6** Fix comments too (opt-in scope option "Fix comments"). Save: `pre_comment_content`
      at 20 (after kses; covers the comment form, REST and admin edits; slashed value), marked with
      comment meta `_negaresh_fixed`; any later change that did not go through the filter drops
      the mark. Display: `comment_text` at 9 (before wptexturize), skips marked comments.
      Uninstall removes the comment markers. Tests: `CommentsTest` (9), e2e (form, REST, older).
- [x] **P3-7** WordPress Plugin Check (`wp plugin check --include-experimental`) in the e2e run (WP 6.3+;
      skipped on 5.8 with a note); findings fixed (see I9).
- [x] **P3-8** Accessibility check (axe-core 4, WCAG 2.0/2.1 A and AA) of Negaresh's parts of the settings
      page, the tools page and the editor panel, in the browser test: no violations on WP 7.1.2 and
      5.8.3. Seen failing on a planted unlabelled field before being trusted.
- [x] **P3-9** Display results cached in the object cache (group `negaresh`, one day; key = text md5 +
      rules hash + plugin version, so no invalidation is needed). Content, titles, excerpts, comments;
      save paths never cached; no database transients. Key computation is inside the B7 guard
      (the B7 test caught an exception escaping from it). Tests: `CacheTest` (5).
