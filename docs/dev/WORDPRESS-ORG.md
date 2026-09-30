# Publishing Negaresh on wordpress.org

Written 2026-09-25 (session 3). The plugin is technically ready since 5.0.0: WordPress's Plugin
Check finds nothing (experimental checks included), `readme.txt` is in wordpress.org format, the
listing assets are in `.wordpress-org/`, and the Release workflow can deploy. What is left needs
the owner's wordpress.org account, so only the owner can do it.

## 1. Account (on wordpress.org)

1. Log in or register at https://login.wordpress.org/ .
2. Turn on two factor authentication (Profile → Account & Security). wordpress.org requires it for
   plugin committers.
3. Note your **username** (not the email, not the display name). It is shown in your profile URL:
   `https://profiles.wordpress.org/<username>/`.

## 2. Put your username in the plugin (in this repository)

| File | Line | Change |
| --- | --- | --- |
| `wp-content/plugins/negaresh/readme.txt` | `Contributors: lordarma` | replace `lordarma` with your wordpress.org username (several: comma separated) |
| `wp-content/plugins/negaresh/negaresh.php` | `Author:` / `Author URI:` | optional: your name and site as you want them shown (now `Lord Arma`, `https://LordArma.com/`) |
| `wp-content/plugins/negaresh/readme.txt` | (no line yet) | optional: `Donate link: https://...` under `Tags:` |

`Contributors:` must be real wordpress.org usernames, or the plugin page shows no author and the
review may ask for it. The other header lines (`Requires at least`, `Tested up to`, `Stable tag`,
`License`) are kept correct by the release process and a unit test. `Tested up to` is only in
`readme.txt` (the review asked for it; a unit test keeps it out of the plugin header).

## 3. Submit

1. Build the zip of the current release: download `negaresh.zip` from the latest GitHub release
   (https://github.com/LordArma/negaresh/releases/latest), or run `bin/build-zip.sh HEAD`.
   (After changing `Contributors:`, make a release first so the zip has the right name.)
2. Upload it at https://wordpress.org/plugins/developers/add/ . The slug comes from the plugin name:
   **negaresh**. If it is taken, wordpress.org offers another; then the `SLUG:` lines in
   `.github/workflows/release.yml` and `.github/workflows/wordpress-org-assets.yml` must change.
3. Wait for the review email (usually some days to a few weeks). Answer it from the same account.

## 4. After approval: let GitHub deploy (repository settings on GitHub)

wordpress.org hosts plugins in SVN. The Release workflow pushes each release there once it has
your SVN credentials.

1. On wordpress.org: Profile → Account & Security → **SVN password**: generate one (it is not your
   login password).
2. On GitHub: https://github.com/LordArma/negaresh/settings/secrets/actions → **New repository
   secret**, twice:
   - `SVN_USERNAME` = your wordpress.org username
   - `SVN_PASSWORD` = the SVN password from step 1
3. That's all. The next release (push a `vX.Y.Z` tag, as always) deploys the plugin, and any change
   to `readme.txt` or `.wordpress-org/` on `master` updates the listing
   (`.github/workflows/wordpress-org-assets.yml`). Until the secrets exist both jobs only leave a
   notice ("wordpress.org deploy skipped").

To publish the current version right after approval without a code change, make a patch release
(for example 5.2.1 with the `Contributors:` fix), or ask Claude to do it.

## 5. What the listing shows (already prepared)

| wordpress.org shows | Comes from |
| --- | --- |
| Name, short description, tags, requirements | `readme.txt` header and first line |
| Description, Installation, FAQ, Changelog | `readme.txt` sections |
| Screenshots 1 to 3 and their captions | `.wordpress-org/screenshot-*.png` + `== Screenshots ==` |
| Icon, banner | `.wordpress-org/icon-*.png`, `banner-*.png` |
| Translations | translate.wordpress.org language packs only: the zip has no `.po`/`.mo` (review of 2026-09-27) |

Regenerate the images with `KEEP=1 tests/e2e/run.sh && tests/e2e/wporg-assets.sh`.

## 6. Review of 2026-09-27 (first submission)

The automatic pre-review asked for three things. Fixed in the code: `Tested up to` removed from the
plugin header; `load_plugin_textdomain()` removed (WordPress loads language packs by itself since
4.6); `.po`/`.mo` left out of the zip by `bin/build-zip.sh` (still in the repository).

Owner's steps:
1. **Ownership**: the account email is `@jangal.co`, the plugin names `LordArma.com`. Either add
   the DNS TXT record `wordpressorg-lordarma-verification` at the root (`@`) of `lordarma.com` and
   check it is visible (`dig +short TXT lordarma.com`), or reply that Lord Arma is you and both
   domains are yours, or change the account email to one `@lordarma.com`.
2. Upload the new zip (`bin/build-zip.sh HEAD`) at https://wordpress.org/plugins/developers/add/
   while logged in as `lordarma`, then reply **to the review email thread** (short, no change list).
3. After approval: import `languages/negaresh-fa_IR.po` at
   https://translate.wordpress.org/projects/wp-plugins/negaresh/ (Persian); ask the Persian
   translation editors, or request PTE rights for your plugin, so it is approved and shipped as a
   language pack.
