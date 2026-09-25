# Progress log

Newest entry on top. Each entry: date, session number, what was done, what was verified, next step.
Status snapshot is kept up to date at the top.

## Status snapshot

| Phase | State |
| --- | --- |
| Analysis | ✅ done (session 1) |
| Phase 1 bug fixes (`BUGFIX-PLAN.md`) | ✅ 24 of 24 done (B0 to B23); release not done |
| Working branch | `fix/v4.1.0` (local commits, one per slice, not pushed) |
| Phase 2 improvements (`IMPROVEMENT-PLAN.md`) | ⏳ ready to start; parts already done in phase 1 (marked there) |
| Current version | 4.0.0 (tag `v4.0.0`, commit `cd6f826`) |
| Next release target | 4.1.0, ready on `fix/v4.1.0`, waiting for the user to merge/tag/push |

**Next step:** user decisions: (1) release 4.1.0 (merge `fix/v4.1.0` → `master`, tag, push,
attach zip), (2) which phase 2 items to start; suggested order I1 → I7 → I2 → I5 → I6 (I11 after I2),
(3) open questions: I4 "fix on save" mode, I9 wordpress.org submission.

**Tests:** `composer test` → 74 tests, all pass (PHP 7.4.33 and 8.3.6).
`tests/e2e/run.sh` → 13/13 on WordPress 7.1.2 / PHP 8.3.33 and on WordPress 5.8.3 / PHP 7.4.27.

---

## 2026-09-25 · Session 3 · Slice: translations, header, README, changelog (B15, B16, B17)

Done:
- `negaresh.pot` regenerated with `wp i18n make-pot` (47 strings); `negaresh-fa_IR.po` fully
  translated; `.mo` compiled with `wp i18n make-mo`. `I18nTest` added (3 tests).
- Header: Tested up to 7.1. README rewritten. `CHANGELOG.md` added with the 4.1.0 entry.
- e2e script: doctype check made case insensitive (Twenty Twenty-One writes `<!doctype html>`).

Verified:
- e2e 13/13 on WP 5.8.3 / PHP 7.4.27 and WP 7.1.2 / PHP 8.3.33.
- Persian admin: settings page shows the translated title, sections and labels.
- 74 unit tests pass.

Phase 1 is complete. Nothing pushed, merged or tagged.

---

## 2026-09-25 · Session 3 · Slice: real WordPress end to end check (+ B23)

Done:
- Manual upgrade test in Docker: v4.0.0 with its saved settings → new code. Migration correct,
  output correct, `debug.log` empty. Details and the 4.0 "before" output: ANALYSIS §7.
- Found **B23** (Critical, 4.0 only): newline before `<?php` in `negaresh-class.php` broke
  logins, redirects and RSS feeds. Already fixed by the refactor; guarded by `PluginFilesTest`.
- Added `tests/e2e/run.sh` (13 checks, fresh site, cleans up) and ran it: all pass.
- Unit suite also passes on PHP 7.4.33 (`php:7.4-cli`).

---

## 2026-09-25 · Session 3 · Slice: plugin refactor (B2 rest, B3, B5 to B14, B18, B19, B22)

Done (commit "refactor: rewrite plugin code ..."):
- Rewrote the plugin's own code (library untouched except B22): `Negaresh_Settings` and
  `Negaresh` classes, `uninstall.php`; deleted `negaresh-scripts.php`, empty `css/` and `js/`.
  Version 4.1.0 in the header and `NEGARESH_VERSION`; `Requires PHP: 7.4`, `Requires at least: 5.8`.
- Settings page: 32 rules in 4 sections with examples + "Where to apply" section.
- Found and fixed **B22** (missing JS padding in the PHP port) while checking the examples.

Verified:
- 61 tests pass (new: `NegareshFilterTest` 20, `SettingsTest` 14, B22 tests).
- All 23 settings examples produce exactly the shown result with only that rule on.
- Original vs patched library on 32 plain text samples: only 6 differences, all end of text
  words now fixed (B22), as the JS original does.

Not verified yet: the plugin inside a real WordPress (planned next, Docker images pulled).

---

## 2026-09-25 · Session 3 · Slice: Virastar library fixes (B2 library half, B4, B20, B21)

User said: do all next items, "do what ever you know is the best way", the library may be replaced
or rewritten, and "save everything after each slice". Decisions taken:
- Keep the patched Virastar instead of a rewrite for v4.1 (a rewrite without reference tests is
  riskier; revisit in phase 2, see IMPROVEMENT-PLAN I11).
- B2: remove the setting (recommended option). B9: single `negaresh_options` array with migration.
- Each slice ends with docs updated and a local commit on branch `fix/v4.1.0`. Sessions 1 and 2
  were committed first as `c38b57e` (B0 + docs) and `688b622` (B1).

Done:
- Library moved to namespace `Negaresh\Vendor\Virastar` (B4); `decodeHTMLEntities()` is a no-op
  (B2); five `'\x{...}'` replacement strings fixed and `fixSuffixMisc` group fixed (B20); front
  matter regex fixed (B21). All listed in ANALYSIS §4.1.
- `VirastarKnownBugsTest` replaced by `VirastarFixesTest` (9 tests).

Verified: 7 of the new tests fail on the pre-slice library; full suite 23/23 passes.

---

## 2026-09-25 · Session 2 · B0 test harness, B1 preservation fix

Done:
- **B0:** `composer.json` (dev only, PHP 7.4 platform), PHPUnit 9.6, Brain Monkey 2.6,
  `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/Unit/TestCase.php` with HTML comparison
  helpers, fixtures for a Classic editor post and a block editor post. `/vendor/` and
  `.phpunit.result.cache` added to `.gitignore`.
- **B1:** upstream Virastar `master` is identical to our copy, so no upgrade path; patched locally
  (by reference closures, store `$matched[0]`, restore real entities, possessive quantifier in
  `fixPunctuationSpacing`). Every patch line marked `// Negaresh patch (B1)`, listed in ANALYSIS §4.1.

Verified:
- On the original file, 11 of 12 B1 tests fail. On the patched file, all pass.
- Original vs patched on 20 plain text samples (two option sets): 0 differences.
- The 3 incomplete tests really fail when their markers are removed.

Found:
- **B20** (new): literal `\x{061F}` / `\x{200c}` written into text by 4 rules (question mark,
  ZWNJ cleanup, RLM cleanup, hamzeh suffix).
- **B21** (new, low): front matter preservation never matches.
- **B1 alone is not releasable:** with `decode_html_entities` on (plugin default), tags are now
  put back in the wrong places instead of being dropped. B2 must land in the same release.

Nothing committed (user did not ask).

---

## 2026-09-25 · Session 1 · Analysis and planning

Done:
- Read the whole plugin and the relevant parts of the vendored Virastar.
- Reproduced the critical bug with PHP 8.3: all HTML tags, links, URLs, entities and `&nbsp;`
  are removed from rendered content; shortcodes mangled; literal `\x{201a}` inserted
  (ANALYSIS.md §3).
- Confirmed class collision with the sibling `../gdic` theme (same Virastar namespace).
- Wrote `CLAUDE.md` (gitignored, never committed), `docs/dev/ANALYSIS.md`,
  `docs/dev/BUGFIX-PLAN.md`, `docs/dev/IMPROVEMENT-PLAN.md`, this file.
- Added `CLAUDE.md` to `.gitignore`.

Not done / decisions pending for the user:
- B2: remove the `decode_html_entities` setting entirely, or keep a safe allow list?
- B9: OK to migrate the 30 option rows into one `negaresh_options` array?
- I4 "fix on save" mode and I9 wordpress.org submission: wanted or not?
- Nothing committed (user did not ask).
