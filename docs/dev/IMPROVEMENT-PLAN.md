# Phase 2: improvement plan

Start only after BUGFIX-PLAN Critical and High items are closed (v4.1.0 tagged).
Each item lists the goal, the approach, and what "done" means. Order is the suggested order.

## I1 Tooling and quality gates
- Composer (dev): PHPUnit, Brain Monkey, WPCS (PHPCS), PHPStan with `szepeviktor/phpstan-wordpress`.
- CI workflow: lint + PHPCS + PHPStan + tests on PHP 7.4, 8.1, 8.3; runs on push and pull requests.
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
- `negaresh.php` defines `NEGARESH_VERSION`, `NEGARESH_FILE`, `NEGARESH_PATH`, then boots `Negaresh\Plugin`.
- `src/` with `Plugin`, `Settings` (single source of option definitions: key, label, description,
  default, group), `Processor` (wraps Virastar + I2), `Migration`.
- Composer autoload (classmap, shipped) or a tiny PSR-4 autoloader; Virastar scoped under
  `Negaresh\Vendor` (PHP-Scoper or a documented manual prefix).

## I4 Performance
- Build the Virastar instance once per request (options do not change mid request).
- Cache processed output: key = hash(content + options + plugin version), object cache / transient,
  invalidated on `save_post` and option update.
- Optional "fix on save" mode (`wp_insert_post_data` / `rest_pre_insert_post`) that writes the
  corrected text into the database, with a confirmation because it is not reversible;
  render mode remains the default.

## I5 Settings page UX
- Group rules into sections (Characters, Numbers, Punctuation, Spacing, Cleanup) with a short
  Persian and English description and a before/after example per rule.
- Live preview box: paste text, see result with the current (unsaved) toggles (REST endpoint,
  nonce, `manage_options`).
- "Reset to defaults" button, "Settings" link on the Plugins screen, RTL friendly layout.
- Scope settings: post types, apply to titles / excerpts / comments / widgets, feeds, REST.

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
- `CHANGELOG.md` (Keep a Changelog format), semantic versioning.

## I8 Documentation and i18n
- `readme.txt` in wordpress.org format (description, FAQ, screenshots, changelog).
- `README.fa.md` for Persian readers; update screenshot.
- Complete `fa_IR` translation; load via `load_plugin_textdomain` only if WP < 4.6 behaviour needed.

## I9 wordpress.org readiness (optional, decide with the user)
- Plugin Check (`wp plugin check`) clean, GPL compatible headers, no external calls, sanitisation
  and escaping audit, unique prefix audit. Submit.

## I10 Stretch ideas (need a user decision before starting)
- Admin notice / dashboard widget showing how many posts would change.
- Custom dictionary of words that must not be touched.
- Multisite: network wide defaults.
