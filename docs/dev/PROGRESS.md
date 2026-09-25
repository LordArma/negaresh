# Progress log

Newest entry on top. Each entry: date, session number, what was done, what was verified, next step.
Status snapshot is kept up to date at the top.

## Status snapshot

| Phase | State |
| --- | --- |
| Analysis | ✅ done (session 1) |
| Phase 1 bug fixes (`BUGFIX-PLAN.md`) | ⏳ in progress, 2 of 22 done (B0, B1) |
| Phase 2 improvements (`IMPROVEMENT-PLAN.md`) | ⛔ blocked on phase 1 Critical + High |
| Current version | 4.0.0 (tag `v4.0.0`, commit `cd6f826`) |
| Next release target | 4.1.0 (bug fixes) |

**Next step:** B2 (needs the user's decision: remove the setting, or safe allow list). B1 must not
be released without B2. Then B20.

**Tests:** `composer test` → 17 tests, 14 pass, 3 incomplete (B20 ×2, B21).

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
