# Progress log

Newest entry on top. Each entry: date, session number, what was done, what was verified, next step.
Status snapshot is kept up to date at the top.

## Status snapshot

| Phase | State |
| --- | --- |
| Analysis | ✅ done (session 1) |
| Phase 1 bug fixes (`BUGFIX-PLAN.md`) | ✅ 24 of 24 done, released as 4.1.0 |
| Working branch | `phase-2` (local commits, one per slice; merged into `master` at each release) |
| Phase 2 improvements (`IMPROVEMENT-PLAN.md`) | ✅ I1–I8, I11 done (I3 not needed); I9 waits for the user; I10 needs decisions |
| Current version | 5.1.0 (tag `v5.1.0`, commit `bfb2de3`, released 2026-09-25 by the Release workflow) |
| Next release target | 5.2.0 |

**Next step:** I10 (user: "yes do all I10"): I10a dashboard widget + notice, I10b words to leave
alone, I10c multisite network defaults. I9: user's submission steps remain. Open for the user: Dependabot
pull request #1 (actions/checkout 4 → 7, upload-artifact; all checks pass).
I9: technically done; the user must create/confirm the wordpress.org account, fix `Contributors:`,
submit, then add SVN secrets. I10 needs user decisions.

**Checks:** `composer check` (PHPCS + PHPStan level 8 + 343 unit tests incl. 159 Virastar.js
reference cases, 3 documented skips) clean on PHP 8.3 and 7.4; e2e + browser (settings page, editor
panel, bulk tool) pass on WP 7.1.2 and 5.8.3.
`tests/e2e/run.sh` → 13/13 on WordPress 7.1.2 / PHP 8.3.33 and on WordPress 5.8.3 / PHP 7.4.27.

---

## 2026-09-25 · Session 3 · I10b words to leave alone

Done: see IMPROVEMENT-PLAN I10b. First version split the text at the words (like tags), which
kept a space before "…" next to a listed word; switched to placeholder words with the split as a
safety net. 101 translated strings.
Verified: unit tests, PHPCS, PHPStan; e2e + browser on WP 7.1.2.

---

## 2026-09-25 · Session 3 · I10a dashboard widget and notice

Done: see IMPROVEMENT-PLAN I10a; 7 new translated strings.
Verified: unit tests, PHPCS (switched to `check_admin_referer()`), PHPStan; e2e on WP 7.1.2.

---

## 2026-09-25 · Session 3 · Release 5.1.0 + pull request #1

Release: version bump, checks all green locally; first CI run on GitHub failed the latest WordPress
job only: the browser test inserted its paragraph before the block editor had finished setting up
the new post (slow runner; not reproducible locally in 3 runs, even with the newer image). Test now
waits for the editor to be ready and for the paragraph to be present. CI green → tag `v5.1.0` →
published https://github.com/LordArma/negaresh/releases/tag/v5.1.0; 5.0.0 → 5.1.0 zip upgrade clean.
Pull requests (user: "check pull requests and merge them if they are ok"): only #1 (Dependabot:
actions/checkout and upload-artifact v4 → v7). Read the release notes: Node 24 and runner 2.327.1+
(GitHub hosted runners are current), upload-artifact v7 only adds an opt-in unzipped mode. Asked
Dependabot to rebase onto today's master, all 7 checks passed (each once: the duplicate run fix
works), squash merged as `d2659be`; CI on master green afterwards.

---

## 2026-09-25 · Session 3 · Phase 3 slice: P3-9 display cache

Done: `display_fix()` with the object cache for the four display filters; test bootstrap reads
the real `NEGARESH_VERSION`; default no-op cache stubs in `TestCase`.
Found by the B7 test: the cache key (rules hash) was computed outside the error guard; fixed.
Verified: 359 unit tests (PHP 8.3 and 7.4), e2e + browser on WP 7.1.2, e2e on 5.8.3.

---

## 2026-09-25 · Session 3 · Phase 3 slice: P3-8 accessibility

Done: axe-core scan in `browser.mjs` for the three admin parts; nothing to fix (labels, contrast,
roles already right). Proven by a planted violation (reported as critical `label`).
Verified: e2e + browser on WP 7.1.2 and 5.8.3.

---

## 2026-09-25 · Session 3 · Release 5.0.0

Done: `master` pushed, CI green (7 jobs incl. Plugin Check on GitHub); tag `v5.0.0` → Release
workflow: `release` published https://github.com/LordArma/negaresh/releases/tag/v5.0.0, the
`wordpress-org` job skipped with its notice (no SVN secrets yet), as designed.
Verified: published 4.4.0 zip → published 5.0.0 zip: active, DB 3, mode kept, Persian date stored
right, `wp negaresh status` works, Plugin Check on the installed zip: no errors, empty debug.log.
Dependabot opened pull request #1 (actions bump, checks pass); left for the user. CI no longer runs
twice on pull request branches (push only on master).

---

## 2026-09-25 · Session 3 · wordpress.org readiness → 5.0.0

Done: see IMPROVEMENT-PLAN I9 and P3-7. Plugin Check clean; license files; listing assets;
deploy jobs (gated on secrets); readme screenshots/changelog/upgrade notice; version 5.0.0.
Note on method: the first "works without load_plugin_textdomain" test on 7.1 was invalid (the
call had already registered the path before `wp eval`); redone with a must-use plugin that
removes the call before init: 7.1 loads the bundled translation, 5.8 does not.

---

## 2026-09-25 · Session 3 · Phase 3 slice: P3-6 comments

Done: "Fix comments" option, save + display paths, comment markers, uninstall, translation.
Verified: 354 unit tests; e2e on WP 7.1.2 (+ browser) and 5.8.3: comment form, REST, older
comment on display. (A manual check looked like a 7.1 failure; it was fetched after the script had
switched the options, and the in-script check lacked `curl -L` for the `?p=` redirect.)
User then asked: make the plugin publishable on wordpress.org, call it version 5, release on GitHub.

---

## 2026-09-25 · Session 3 · Phase 3 slice: P3-1 to P3-5 (easy ones)

Done: `.gitattributes` (LF; every file already LF, nothing renormalized) + `.editorconfig`;
Dependabot (actions + composer, monthly, grouped); `composer audit` in CI; "Fix existing posts"
link on the Plugins screen; `wp negaresh status` (+ `Negaresh_Bulk::stats()`).
Verified: 345 unit tests; e2e on WP 7.1.2 incl. status checks.

---

## 2026-09-25 · Session 3 · Release 4.4.0

Done: version 4.4.0 (header, constant, readme.txt Stable tag + changelog + upgrade notice),
translations, CHANGELOG dated and its repeated headings merged; checks; `master` pushed, CI green;
tag → Release workflow published https://github.com/LordArma/negaresh/releases/tag/v4.4.0.
Verified: published 4.3.0 zip → published 4.4.0 zip on a fresh site: active, DB 3, mode kept.
A post saved under 4.3.0 kept the scrambled date `۱۳/۱/۳۵۵` (B29 stored in save mode); new saves are
correct. Not repairable automatically (the typed original was never stored); exposure is tiny:
save mode exists since 4.2.0, released the same day.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I11 Virastar re-port (+ B28, B29, B30)

Done: extracted Virastar.js's test suite (159 cases) and ran it against our PHP copy: 134 pass
(upstream PHP 116). The failures showed three port bugs: B28 (every multi step rule reversed),
B29 (missing `/u`: Persian digit dates scrambled with default rules, and the tokenizer splitting
`«»`), B30 (sprintf check). Re-ported all rule functions from JS 0.22.1 in JS order with `/u`;
added the JS `remove_spaces_before_ellipsis` as a plugin rule; removed two dead functions.
Result 156/159, 3 documented deviations. `VirastarReferenceTest` + B28/B29/B30 tests (proven to
fail on the old file: 8/11 and 22 reference failures).
Verified: 343 unit tests (PHP 8.3 and 7.4), PHPCS, PHPStan; plugin default rules change 8 of 162
corpus texts, all fixes; e2e + browser on WP 7.1.2 and 5.8.3.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I8 documentation

Done: readme.txt (wordpress.org), README rewrite, README.fa.md, new screenshot, version test for
readme.txt, `dir="auto"` preview boxes.
Found in the harness: a crash in `browser.mjs` after some checks printed PASS looked like a pass
(now any crash prints FAIL and `run.sh` requires a final `DONE`); repeated runs on one KEEP site
saw each other's bulk test posts (unique title per run); the editor panel was found by its
English title (now the translated title).
Verified: 172 unit tests; browser test 14/14 in Persian and in English; e2e on WP 7.1.2.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I6c bulk tool page

Done: Tools → Negaresh page, bulk find/process REST routes, `bulk.js`, styles, 89 translated
strings (two sentences split for the line limit; typographic quotes in a msgid to avoid escaping;
counts reworded without plurals after the "1 posts fixed" screenshot), e2e + browser checks.
Verified: see status snapshot. I6 is complete.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I6b engine + WP-CLI

Done: `Negaresh_Bulk`, `Negaresh_CLI`, real globals, e2e +11 checks, ERR trap in `run.sh`.
Found and fixed while testing: (1) no restorable original after a bulk change (WordPress saves
only the new text as a revision) → snapshot first; (2) the first kses check proved nothing
(WP-CLI already disables kses) → now simulates kses and was seen failing without the guard;
(3) plugin globals were local under WP-CLI.
Verified: 165 unit tests; e2e + browser on WP 7.1.2 and 5.8.3.
Next: I6c bulk tool page.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I6a+d opt out, markup skip, Fix this post

Done: see IMPROVEMENT-PLAN I6 "Result so far". New `Negaresh_Editor` class and `assets/editor.js`;
69 translated strings; e2e +4 checks; browser test covers the editor panel on 7.1.2 and 5.8.3.
Fixed in the harness: a hidden browser FAIL (pipefail + grep -q), 5.8 welcome guide and panel
selectors, console errors from WordPress's own block validation no longer counted.
Next: I6b (engine + WP-CLI), I6c (bulk tool page).

---

## 2026-09-25 · Session 3 · Release 4.3.0

Done: version 4.3.0, translation headers, CHANGELOG dated; all local checks (135 unit, e2e on WP
7.1.2 with the browser check and on 5.8.3); `master` pushed, CI green including the Playwright
check on GitHub (first run there); tag `v4.3.0` → Release workflow published
https://github.com/LordArma/negaresh/releases/tag/v4.3.0.
Verified: published zip installed over the published 4.2.0 zip: active, DB 3, mode kept (save,
fresh 4.2 install), `assets/admin.js` served, empty debug.log.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I5 settings page

Done: live preview (REST route + admin script), reset rules button, Settings link, titles and
excerpts scope options, RTL example arrows, translations (63 strings), e2e +7 checks, new
Playwright browser check (`tests/e2e/browser.sh`, also in CI via `BROWSER=1` on the latest WP job).
Details in IMPROVEMENT-PLAN I5.
Verified: see status snapshot. Screenshots were checked by eye: the fa_IR page is laid out right
to left correctly; the example arrows were wrong (fixed).

---

## 2026-09-25 · Session 3 · Release 4.2.0

User: "release the new, merge it as a part of the release", then I5.
Done: version 4.2.0 (header, constant, translation headers), CHANGELOG dated; `phase-2`
fast forwarded into `master` and pushed; waited for CI before tagging. First CI run on GitHub
failed only on the Docker/zip jobs: scripts were not executable in git (`core.fileMode` is off in
this Windows checkout, so `chmod +x` was never recorded) → `git update-index --chmod=+x`, commit
`92c220b`, CI all green (PHP 7.4/8.1/8.3/8.4, zip, e2e latest + 5.8). Then tag `v4.2.0`: the
Release workflow ran every step and published https://github.com/LordArma/negaresh/releases/tag/v4.2.0.
Verified: downloaded the published zip, installed it over 4.1.0 in a fresh WordPress: active,
DB 3, mode display kept, empty debug.log.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I4 fix before saving (+ B26, B27)

Done: "When to fix" setting (save / display), save hooks, rules hash marker, display skip for
marked posts, DB_VERSION 3 migration (upgrades keep display), uninstall removes markers,
translations for the new strings (52), e2e extended to 21 checks (wp-cli save, REST save with an
application password, display mode, markers), migration split into numbered steps.
Found: B26 (trailing space after `…` before a line break, would be stored in save mode) and B27
(display mode ran after wptexturize since 4.0, so ellipsis/quote rules never applied); both fixed.
Verified: see status snapshot; plus a real 4.1.0 zip → new code upgrade: DB 2 → 3, mode display,
stored text untouched, displayed text fixed (`…`), empty debug.log.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I2 HTML aware processing (+ B24, B25)

Re-checked I2's premise before building it: probing 4.1.0 with tricky HTML found two real bugs,
B24 (a `>` inside an attribute breaks the tag when quote rules are on) and B25 (space lost between
`…` and an inline tag, default rules). Both are in the released 4.1.0.
Done: `fix()` rewritten around a quote aware tokenizer and per text node processing (details in
IMPROVEMENT-PLAN I2); `HtmlProcessingTest` (21 tests, 9 failed before the change).
Verified: 98 unit tests, PHPCS, PHPStan clean; probes all correct; e2e 13/13 on WP 7.1.2 and
5.8.3; timing 34 → 55 ms on a 156 KB post.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I7 release pipeline

Done: `bin/build-zip.sh`, `bin/release-notes.sh`, `release.yml`, `package` job in `ci.yml`
(replaces `main.yml`), version agreement test, release steps in CLAUDE.md §5.
Verified locally: zip from `v4.1.0` has the same file list as the released asset; notes for 4.1.0
have no internal IDs; the tag check passes for v4.1.0 and fails for v4.0.0 and v4.2.0 under
`bash -e` (after fixing an `&&` chain that did not fail the step); `composer check` 77 tests.
Not verified: the workflows on GitHub.

---

## 2026-09-25 · Session 3 · Phase 2 slice: I1 quality gates

Done: PHPCS ruleset, PHPStan level 8, composer scripts, CI workflow (details in IMPROVEMENT-PLAN
I1). PHPStan findings fixed: regex results checked in `fix()` (hardening; a repro attempt showed it
is not reachable today, so it is not logged as a bug), settings normalized with typed accessors
(+2 tests), test helpers typed.

Verified: `composer check` clean on PHP 7.4.33, 8.3.6 and 8.4; e2e 13/13 on WP 7.1.2 and 5.8.3.
Not verified: the workflow on GitHub itself (branch not pushed).

---

## 2026-09-25 · Session 3 · Release 4.1.0

User: "release 4.1.0 and start phase 2"; fix before saving is preferred (I4); wordpress.org
submission later, not now (I9).

Done:
- CHANGELOG dated, `master` fast forwarded to `fix/v4.1.0`, annotated tag `v4.1.0`, pushed.
- Zip built with `git archive --prefix=negaresh/ v4.1.0:wp-content/plugins/negaresh` (the 4.0.0
  zip had no top folder; a `negaresh/` folder is the standard and required by wordpress.org).
- GitHub release "Negaresh 4.1.0" with `negaresh.zip` and the changelog as notes (bug IDs removed).

Verified:
- Before pushing: installed the published 4.0.0 zip in a fresh WordPress 7.1.2, saved settings,
  then `wp plugin install negaresh.zip --force`: stays active, old files gone, settings migrated,
  links back, debug.log empty.
- After pushing: the existing "Create ZIP Plugin" workflow passed on `master`.

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
