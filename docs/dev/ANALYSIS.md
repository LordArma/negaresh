# Negaresh: codebase analysis

First written: 2026-09-25 (session 1). Analysed at commit `cd6f826` ("Feat download section"), tag `v4.0.0`.

## 1. Summary

A small plugin (about 300 lines of its own PHP plus a 1051 line vendored copy of Virastar). It
hooks `the_content`, builds a `Virastar` instance from 30 on/off options, runs `cleanup()` on the
rendered post HTML, then applies `nl2br()`.

The idea is sound, but the current release is **harmful on a live site**: the vendored Virastar
cannot restore anything it "preserves", so every HTML tag, link, URL, `&nbsp;` and entity in a
post is deleted at render time, shortcodes are mangled, and literal `\x{201a}` text appears.
Stored content is not modified (it is render time only), so disabling the plugin restores posts.

## 2. How a request flows today

1. `negaresh.php` requires `negaresh-scripts.php` and `negaresh-class.php`, then `new Negaresh()`.
2. `negaresh-class.php` does `include('Virastar.php')` and `require_once('negaresh-settings.php')`
   (relative paths).
3. Constructor hooks:
   * `admin_menu` → `negaresh_menu()` adds *Settings → Negaresh*.
   * `the_content` (priority 10) → `fix_farsi_typoes()`.
   * `admin_init` → global function `settings()` registers 30 options + fields.
   * `init` → `languages()` loads the text domain.
4. `fix_farsi_typoes($content)`: reads each option with `get_option($key) == '1'`, builds
   `new Virastar([...30 flags...])`, calls `cleanup()`, on exception **echoes** the message, returns
   `nl2br($content, true)`.
5. `negaresh-scripts.php` enqueues empty `css/style.css` and `js/main.js` on every front end page
   using hardcoded `plugins_url() . '/negaresh/...'` and handles prefixed `yts-` (copy/paste leftover).

## 3. Evidence: reproduction of the critical bug

Run from the repo root:

```php
<?php
require 'wp-content/plugins/negaresh/includes/Virastar.php';
$v = new Alirezasedghi\Virastar\Virastar(['decode_html_entities' => false]);
echo $v->cleanup('<p>سلام <a href="https://example.com/x">لینک</a> و example.com و A&amp;B&nbsp;ok &lt;b&gt; [gallery ids="1,2"] 123</p>');
```

Observed output (PHP 8.3.6):

```
سلام لینک و  و A\x{201a}Bok \x{201a}b\x{201a}[gallery ids= «1, 2»] ۱۲۳
```

With `decode_html_entities => true` (the plugin's default): `سلام لینک و  و ABok b[gallery ids= «1, 2»] ۱۲۳`

Lost: `<p>`, `<a href>`, the bare URL, `&amp;`, `&nbsp;`, `&lt;b&gt;`. Mangled: the shortcode.

### Root cause

In `Virastar::cleanup()` every preserver closure captures its store **by value**:

```php
$html = [];
$text = preg_replace_callback('/<\/?[a-z][^>]*?>/i', function ($matched) use ($html) {
    $html[] = $matched;          // writes to the closure's copy; outer $html stays []
    return ' __HTML__PRESERVER__ ';
}, $text);
...
$text = preg_replace_callback('/[ ]?__HTML__PRESERVER__[ ]?/', function () use ($html) {
    return array_shift($html);   // always null → placeholder replaced with ''
}, $text);
```

Same pattern for front matter, comments, brackets, braces, URIs, markdown links, nbsp and entities.
Additional defects in the same file:

* Stores push the whole `$matched` array, not `$matched[0]`, so even with `&` references the
  restore would return an array.
* Entity restore returns `array_shift($this->entities)`, i.e. entries of the *entity name table*
  (`'\x{201a}'`, a literal escape string), instead of the captured entities.
* `decodeHTMLEntities()` does not decode anything: it replaces every entity with
  `' __HTML__PRESERVER__ '` without storing it, which would also desynchronise the HTML queue.

## 4. Vendored library status

| Item | Value |
| --- | --- |
| File | `wp-content/plugins/negaresh/includes/Virastar.php` |
| Namespace / class | upstream `Alirezasedghi\Virastar\Virastar`; ours `Negaresh\Vendor\Virastar\Virastar` (B4) |
| Upstream | https://github.com/AlirezaSedghi/Virastar (PHP port of JS Virastar) |
| Upstream version | identical to upstream `master` `ccc38a3` (= v1.0.2 + README change), checked 2026-09-25 |
| Local patches | see §4.1 (record every patch here; each is marked `// Negaresh patch (Bn)` in the code) |
| Same class elsewhere | `../gdic/Virastar.php` (GDIC theme) → fatal "Cannot declare class" if both load |

Upstream has no fix (checked 2026-09-25), so the file is patched locally. Consider sending the
patches upstream as a PR.

### 4.1 Local patches to Virastar.php

| Bug | Where | Change |
| --- | --- | --- |
| B1 | `cleanup()`, every preserver closure (front matter, HTML, comments, brackets, braces, markdown links, URIs, nbsp, entities) | `use ($x)` → `use (&$x)`; store `$matched[0]` instead of the match array |
| B1 | `cleanup()`, every restore closure | `use (&$x)` and `(string) array_shift($x)` |
| B1 | `cleanup()`, entity restore | restore from the captured `$entities`, not `$this->entities` (the name table) |
| B1 | `fixPunctuationSpacing()` | `[ \t\x{200c}]*` → `[ \t\x{200c}]*+` so `(?!\n\|_{2})` actually protects placeholders |
| B2 | `decodeHTMLEntities()` | returns the text unchanged (upstream injected unstored HTML placeholders; a real decode of `&lt;` is unsafe) |
| B4 | namespace | `Alirezasedghi\Virastar` → `Negaresh\Vendor\Virastar` |
| B20 | `cleanupZWNJ()`, `cleanupRLM()`, `fixQuestionMark()`, `fixSuffixSpacingHamzeh()` | replacement strings use `"\u{...}"` instead of the literal text `'\x{...}'` |
| B20 | `fixSuffixMisc()` | `$2` pointed at a missing group; trailing check is now a lookahead |
| B22 | `cleanup()` start | `$text = ' ' . $text . ' ';` restored from the JS original (the end of `cleanup()` already strips it) |
| B21 | front matter preserver | upstream regex kept; it matches again because of B22 (a session 3 interim change to `/^---/` was reverted) |

To re-apply after an upstream upgrade: `grep -n "Negaresh patch" includes/Virastar.php`, and run
`composer test`; `VirastarPreservationTest` fails without these patches.

## 5. Other findings (details live in BUGFIX-PLAN.md)

* Option defaults exist only when `register_setting()` ran, which happens only on `admin_init`,
  so on a fresh install the front end treats every rule as off until an admin presses Save (B5).
* Options passed to Virastar are only 30 of its 44; the rest silently use Virastar defaults
  (`cleanup_kashidas`, `cleanup_extra_marks`, `markdown_normalize_lists`, ... all `true`) (B18).
* `nl2br()` runs after `wpautop()`, adding `<br />` between block elements and inside `<pre>` (B6).
* Global functions `settings()` and `checkboxHTML()`; unprefixed option keys like `fix_dashes` (B8, B9).
* 12 settings are labelled with their raw option key through `__($variable)` (B14).
* Translations are stale: `.po` references `negaresh.php:104` etc. from an older layout, several
  `msgstr` are empty (B15).
* No tests, no linting, no static analysis; CI only uploads a workflow artifact, while the README
  points users at GitHub Releases (I7).

## 6. Architecture after the 4.1 refactor (session 3)

* `negaresh.php`: header, `NEGARESH_VERSION`, `NEGARESH_FILE`, requires the three includes with
  `__DIR__`, creates `$negaresh = new Negaresh(new Negaresh_Settings())`.
* `includes/negaresh-settings.php` → `class Negaresh_Settings`: `RULE_DEFAULTS` (32 Virastar rules),
  `SCOPE_DEFAULTS` (post types, feeds, REST), `LEGACY_OPTIONS` (4.0 rows), `get()`, `sanitize()`,
  `maybe_migrate()`, `delete_all()`, settings page rendering, `rule_labels()` with examples.
* `includes/negaresh-class.php` → `class Negaresh`: hooks, `filter_content()` (guards + error
  handling), `fix()` (protect code elements and shortcodes, run Virastar, restore),
  `should_filter()` (scope), `virastar_options()` (all 44 options explicitly), one Virastar per request.
* `uninstall.php`: `Negaresh_Settings::delete_all()` per site.
* Stored data: `negaresh_options` (array), `negaresh_db_version` (int, 2).

## 7. End to end check in a real WordPress

`tests/e2e/run.sh` (Docker: MariaDB 11 + official WordPress image + wp-cli) installs a fresh site,
mounts the plugin read only, and checks 13 things: doctype and feed XML (B23), link and entities
(B1, B2), defaults without saving (B5), shortcode and code block (B3), REST, admin login, the
settings page render and save/sanitize, an empty `debug.log`, and uninstall (B19).
`KEEP=1` leaves the site at http://127.0.0.1:8089 (admin/admin). `WP_IMAGE=...` picks another
WordPress/PHP combination.

Session 3 also ran a manual upgrade test: v4.0.0 with its 30 settings rows (as its form stores
them), then the new code on the same database. Migration mapped every value, removed all legacy
rows, set `negaresh_db_version` = 2, logged nothing. 4.0 output on the same post, for the record:
all tags and the link gone, `\x{061F}` text, shortcode mangled and not executed, code block
rewritten, runs of `<br />`, and a newline before `<!DOCTYPE`.

## 8. Environment notes

* The working copy lives under a Syncthing folder (`../.stfolder`) on a Windows drive mounted in WSL:
  file modes show as 777, so ignore mode noise (`git config core.fileMode false` if it appears).
* Docker works in this WSL setup (used by `tests/e2e/run.sh`; images: `mariadb:11`,
  `wordpress:php8.3-apache`, `wordpress:cli-php8.3`, `php:7.4-cli`, `wordpress:5.8-php7.4-apache`).
* PHP 8.3.6 CLI and Composer (`~/.local/bin/composer`) available. Dev dependencies install into
  `vendor/` (gitignored); Composer platform is pinned to PHP 7.4 so locked versions stay compatible.
