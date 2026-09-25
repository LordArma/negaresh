# Phase 2: improvement plan

Started 2026-09-25 (session 3) after 4.1.0 was released. Work happens on branch `phase-2`.
Each item lists the goal, the approach, and what "done" means.

**User decisions (2026-09-25):**
- I4: yes, the user prefers fixing text *before saving* (stored content is corrected).
- I9: submit to wordpress.org, **but not now**; keep the code ready for it (Plugin Check clean).

**Order:** I1 → I7 → I2 → I4 → I5 → I6 → I11 → I8 leftovers → I3 (only if needed) → I9 (when asked).
I10 needs user decisions first.

## I1 Tooling and quality gates
- Already there (phase 1): Composer, PHPUnit 9.6, Brain Monkey, 74 unit tests, `tests/e2e/run.sh`.
- To add: WPCS (PHPCS), PHPStan with `szepeviktor/phpstan-wordpress`.
- CI workflow: lint + PHPCS + PHPStan + tests on PHP 7.4, 8.1, 8.3; runs on push and pull requests;
  optionally `tests/e2e/run.sh` (GitHub runners have Docker).
- `.editorconfig`, `.gitattributes` with `export-ignore` for dev files.
- **Done when:** CI green, commands documented in CLAUDE.md §4.

## I2 HTML aware processing (the real fix behind B3)
- Walk the markup and run Virastar on **text nodes only**. Options: `WP_HTML_Tag_Processor`
  (WP 6.2+), `wp_html_split()` (older WP), or `DOMDocument` with UTF-8 handling.
- Skip subtrees: `pre, code, kbd, samp, script, style, textarea, svg, math` and elements with a
  `.negaresh-skip` class or `data-negaresh="off"`.
- Handle text split across inline tags (`<strong>` inside a word) without breaking ZWNJ rules.
- **Done when:** fixtures show markup byte identical outside text nodes.

## I3 Codebase structure
- Partly done in phase 1 (classes `Negaresh`, `Negaresh_Settings`, constants, own Virastar namespace).
- Remaining: namespaced classes under `src/` if the plugin grows; keep this optional.
- `src/` with `Plugin`, `Settings` (single source of option definitions: key, label, description,
  default, group), `Processor` (wraps Virastar + I2), `Migration`.
- Composer autoload (classmap, shipped) or a tiny PSR-4 autoloader; Virastar scoped under
  `Negaresh\Vendor` (PHP-Scoper or a documented manual prefix).

## I4 Fix before saving (user wants this) + performance
- ~~Build the Virastar instance once per request~~ (done in phase 1).
- Cache processed output: key = hash(content + options + plugin version), object cache / transient,
  invalidated on `save_post` and option update.
- **"Fix before saving" mode** (user decision 2026-09-25): correct title/content when a post is
  saved (`wp_insert_post_data`, covers the block editor through REST), so the database holds the
  fixed text and display time work disappears. Not reversible, so: a clear setting, revisions keep
  the original, skip autosaves where sensible, and the per post opt out from I6 applies.
  Display mode stays available; decide the default for new installs when building it.

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

## I7 Release pipeline
- On tag `v*`: build zip with only the plugin folder (respecting `export-ignore`), attach to a
  GitHub Release, generate release notes from `CHANGELOG.md`.
- Keep the existing push artifact for testing builds.
- ~~`CHANGELOG.md`~~ (added in phase 1); keep it updated with every change.

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
