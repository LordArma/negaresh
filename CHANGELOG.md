# Changelog

All notable changes to Negaresh. Format: [Keep a Changelog](https://keepachangelog.com/),
versions: [Semantic Versioning](https://semver.org/).

## [5.0.0] (2026-09-25)

Ready for wordpress.org: the plugin passes WordPress's official Plugin Check (including its
experimental checks), ships its license and the MIT notice of the bundled Virastar, and the
repository holds the wordpress.org listing assets and a deploy job that starts once the plugin is
approved there. No breaking changes; the major version marks the wordpress.org release.

### Added
- wordpress.org listing assets (icon, banners, screenshots) and deployment, `license.txt`,
  `includes/Virastar-LICENSE.txt`.
- **Fix existing posts** link on the Plugins screen (P3-3).
- Optional fixing of **comments** (off by default): on save, or on display for older ones (P3-6).
- **`wp negaresh status`**: how many posts are fixed with the current rules, waiting, or opted
  out (P3-5).

## [4.4.0] (2026-09-25)

### Fixed
- **Dates written with Persian or Arabic digits were scrambled** (`۳/۱/۱۳۵۵` became `۱۳/۱/۳۵۵`)
  with the default rules, and in "fix before saving" mode the scrambled date was stored (B29).
- Rules with several steps ran them in the wrong order: `---` became `–-`, repeated `!?` marks
  were not merged, a Kashida between numbers was deleted instead of becoming a dash, times got a
  space after the colon, stacked diacritics were removed (B28).
- `%1$s` style placeholders in text lost their digits (B30).

### Added
- **Leave this post alone**: a per post choice in the block editor sidebar and in the classic
  editor, so Negaresh never changes that post (I6).
- **Fix this post now** button in the block editor: fixes the text in the editor straight away;
  Undo reverts it (I6).
- **Tools → Negaresh**: check the posts you already have, see the changed lines, then fix them
  all; the text before each change is kept in the post's revisions (I6).
- **`wp negaresh fix`** (WP-CLI) fixes posts that already exist: a dry run with an optional diff
  unless `--apply` is given; the text before the change is kept as a revision. And
  `wp negaresh text` fixes a piece of text (I6).
- `readme.txt` for wordpress.org, a Persian README and a new screenshot (I8).
- Parts of a post can be left alone with the CSS class `negaresh-skip` (block editor: Advanced →
  Additional CSS class) or `data-negaresh="off"` (I6).

### Changed
- The rules now follow Virastar.js 0.22.1 and pass its own test suite (I11). New rule
  **Remove the space before an ellipsis** (on by default). Posts fixed with the old rules are
  checked again.

- The “Try it” boxes follow the direction of what is typed, so pasted HTML is readable (I8).

## [4.3.0] (2026-09-25)

### Added
- **Try it** box on the settings page: type or paste text and see it fixed straight away, with
  the boxes as they are checked, before saving (I5).
- **Reset rules to defaults** button (asks first; keeps the mode and where to apply) (I5).
- **Settings** link on the Plugins screen (I5).
- Optional fixing of **post titles** and of **excerpts written by hand**, off by default (I5).

### Changed
- The examples next to each rule read right to left with an arrow pointing the right way (I5).

## [4.2.0] (2026-09-25)

### Fixed
- A `>` inside an HTML attribute (for example `alt="a > b"`) could break the tag when a quote
  rule was on (B24).
- The space between an ellipsis and a following link or formatted word was removed, so the words
  ran together (B25).
- The ellipsis and quote rules never worked when fixing on display, because WordPress had already
  turned `...` and quotes into HTML entities (B27).
- No space is left after an ellipsis at the end of a line (B26).

### Added
- **Fix before saving** (I4): a new "When to fix" setting. *When a post is saved* corrects the
  stored text, so the editor shows what readers get; *when a post is displayed* never changes the
  stored text. New installs fix before saving; **sites upgrading keep fixing on display** until
  the setting is changed. Posts saved before the switch are still fixed on display until they are
  saved again.

### Changed
- Only the text between HTML tags is fixed now; tags, attributes and the spaces around tags are
  never changed. Text written only in English (no Persian letters) is left alone (I2).

## [4.1.0] (2026-09-25)

A bug fix release. Upgrading is strongly recommended: 4.0.0 damaged the displayed HTML of every post
and prevented logging in while it was active.

### Fixed
- HTML tags, links, URLs, HTML entities and `&nbsp;` were removed from every post when displayed,
  and text like `\x{201a}` appeared instead (B1, B2).
- While the plugin was active nobody could log in, redirects failed and RSS feeds were invalid,
  because a stray newline was sent before WordPress's headers (B23).
- Shortcodes were rewritten before they ran (`[gallery ids="1,2"]` lost its attributes), and the
  contents of `pre`, `code`, `script`, `style` and similar elements were changed (B3).
- Extra `<br />` tags were added throughout posts (B6).
- Error messages could be printed into the middle of a page; now the original text is shown (B7).
- The question mark, half space and RLM cleanup rules wrote text such as `\x{061F}` into posts (B20).
- Rules that need a space next to a word never fixed the first or last word of a text (B22).
- Fatal error when a theme or plugin also ships Virastar, such as the GDIC theme (B4).
- Possible fatal errors from generic global function names (B8).

### Changed
- **The default rules now apply before the settings page is ever saved.** In 4.0.0 a site that
  never saved the settings ran with every rule off (B5).
- Settings are stored in one `negaresh_options` option. Existing settings are moved over
  automatically on the first request after upgrading and the 30 old options are deleted (B9).
- The "decode HTML entities" setting is removed: it never worked and a working version would be
  unsafe (B2).
- New settings: which post types to fix, and whether feeds and the REST API are fixed (B10).
  Admin screens are never changed.
- Three rules that always ran silently are now visible settings: Kashida cleanup, Kashida as a
  dash, repeated marks (B18). They stay on by default, as before.
- Clearer setting labels grouped into sections, each with an example; full Persian translation
  (B14, B15).
- Requires PHP 7.4 and WordPress 5.8; tested up to WordPress 7.1 (B16).
- Content without Persian or Arabic letters, and content with invalid UTF-8, is left alone.

### Removed
- Empty stylesheet and script that were loaded on every page (B11).

### Added
- `uninstall.php` removes all plugin options, on every site of a network (B19).

## [4.0.0] (2025-03-30)
- Refactor and download section. See the git history for earlier changes.
