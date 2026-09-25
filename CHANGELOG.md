# Changelog

All notable changes to Negaresh. Format: [Keep a Changelog](https://keepachangelog.com/),
versions: [Semantic Versioning](https://semver.org/).

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
