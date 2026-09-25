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
| Namespace / class | `Alirezasedghi\Virastar\Virastar` |
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

## 6. Environment notes

* The working copy lives under a Syncthing folder (`../.stfolder`) on a Windows drive mounted in WSL:
  file modes show as 777, so ignore mode noise (`git config core.fileMode false` if it appears).
* PHP 8.3.6 CLI and Composer (`~/.local/bin/composer`) available. Dev dependencies install into
  `vendor/` (gitignored); Composer platform is pinned to PHP 7.4 so locked versions stay compatible.
