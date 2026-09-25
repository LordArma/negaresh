# Negaresh

Negaresh is a WordPress plugin that helps fix Farsi (Persian) typos in WordPress.

![نگارش](https://github.com/LordArma/negaresh/raw/master/screenshot.png "نگارش")

## Download
Download `negaresh.zip` from the latest release on the [releases page](https://github.com/LordArma/negaresh/releases),
then upload it in *Plugins → Add New → Upload Plugin*.

## Requirements
* WordPress 5.8 or newer (tested up to 7.1)
* PHP 7.4 or newer

## Features
Built on [Virastar](https://github.com/AlirezaSedghi/Virastar) (a patched copy ships with the plugin).

* Fixes post content when it is displayed; what is stored in the database is never changed,
  so turning the plugin off shows the original text again.
* 32 rules you can switch on or off at *Settings → Negaresh*: Persian digits, half spaces
  (ZWNJ), Persian punctuation and quotes, spacing, Kashida cleanup and more, each with an example.
* Leaves HTML, links, entities, shortcodes and code (`pre`, `code`, `script`, `style`, ...) untouched.
* Choose the post types to fix, and whether feeds and the REST API are fixed too.
* Persian translation included.

## Development
Tooling lives at the repository root and is not part of the plugin zip.

```bash
composer install
composer test          # unit tests (PHPUnit + Brain Monkey)
tests/e2e/run.sh       # end to end checks in a real WordPress (needs Docker)
```

See [CHANGELOG.md](CHANGELOG.md) for what changed.
