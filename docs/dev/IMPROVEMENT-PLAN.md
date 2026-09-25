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

## I3 Codebase structure
- Partly done in phase 1 (classes `Negaresh`, `Negaresh_Settings`, constants, own Virastar namespace).
- Remaining: namespaced classes under `src/` if the plugin grows; keep this optional.
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

## I5 Settings page UX
- Group rules into sections (Characters, Numbers, Punctuation, Spacing, Cleanup) with a short
  Persian and English description and a before/after example per rule.
- Live preview box: paste text, see result with the current (unsaved) toggles (REST endpoint,
  nonce, `manage_options`).
- "Reset to defaults" button, "Settings" link on the Plugins screen, RTL friendly layout.
- Scope settings: ~~post types, feeds, REST~~ (done in phase 1); still open: titles, excerpts,
  comments, widgets.
- ~~Sections and examples~~ (done in phase 1); still open: longer descriptions, live preview.

## I6 Editor integration
- Per post opt out (post meta + checkbox in Gutenberg sidebar and Classic editor meta box).
- Gutenberg: "Fix Persian typography" button that runs the processor on the selected block or
  the whole post via REST and shows a diff before applying.
- Bulk tool (Tools → Negaresh): dry run over posts, show changed count and diffs, apply in batches.
- Optional WP-CLI command `wp negaresh fix [--dry-run] [--post_type=post]`.

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

## I8 Documentation and i18n
- `readme.txt` in wordpress.org format (description, FAQ, screenshots, changelog).
- `README.fa.md` for Persian readers; update screenshot.
- ~~Complete `fa_IR` translation~~ (done in phase 1).

## I9 wordpress.org readiness (wanted later, not now: user 2026-09-25)
- Plugin Check (`wp plugin check`) clean, GPL compatible headers, no external calls, sanitisation
  and escaping audit, unique prefix audit. Submit.

## I10 Stretch ideas (need a user decision before starting)
- Admin notice / dashboard widget showing how many posts would change.
- Custom dictionary of words that must not be touched.
- Multisite: network wide defaults.

## I11 Replace or rewrite Virastar (decide after I2)
- The user allowed replacing or rewriting the vendored library (session 3).
- Why not now: ~1000 lines of Persian typography regexes with no upstream tests; a rewrite in the
  bug fix release would trade known bugs for unknown ones.
- When: after I2 (text node processing) lands, the preserve/restore placeholder machinery is no
  longer needed. Then either slim the vendored copy down to the text rules, or rewrite rule by rule
  with a test per rule (port the JS Virastar test suite as the reference).
- **Done when:** every rule has tests, and no `Negaresh patch` markers remain because the code is ours.
