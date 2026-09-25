#!/usr/bin/env bash
# End to end check of Negaresh in a real WordPress (Docker). Usage:
#   tests/e2e/run.sh            start a fresh site, run the checks, remove everything
#   KEEP=1 tests/e2e/run.sh     leave the site running at http://127.0.0.1:8089 (admin / admin)
#   BROWSER=1 tests/e2e/run.sh  also drive the settings page in headless Chromium (Playwright image)
# Needs Docker. Uses the latest official images unless WP_IMAGE / CLI_IMAGE are set.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PLUGIN="$ROOT/wp-content/plugins/negaresh"
WP_IMAGE="${WP_IMAGE:-wordpress:php8.3-apache}"
CLI_IMAGE="${CLI_IMAGE:-wordpress:cli-php8.3}"
NET=negaresh-e2e; DB=negaresh-e2e-db; WEB=negaresh-e2e-wp; URL=http://127.0.0.1:8089
FAIL=0

cleanup() { [ "${KEEP:-0}" = 1 ] && return; docker rm -f "$DB" "$WEB" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT
# set -e would end the run without a word; say where it stopped instead.
trap 'echo "FAIL  run.sh stopped at line $LINENO: $BASH_COMMAND"' ERR
wp() { docker run --rm -i --network "$NET" --volumes-from "$WEB" --user 33:33 -e HOME=/tmp \
  -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp "$CLI_IMAGE" wp "$@"; }
check() { if grep -qF -- "$2" <<<"$3"; then echo "PASS  $1"; else echo "FAIL  $1 (expected: $2)"; FAIL=1; fi; }

cleanup; KEEP_SAVED="${KEEP:-0}"; KEEP=0; cleanup; KEEP="$KEEP_SAVED"
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp \
  -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11 >/dev/null
docker run -d --name "$WEB" --network "$NET" -p 127.0.0.1:8089:80 -e WORDPRESS_DB_HOST="$DB" \
  -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp -e WORDPRESS_DEBUG=1 \
  -e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false); define('WP_ENVIRONMENT_TYPE', 'local');" \
  -v "$PLUGIN":/var/www/html/wp-content/plugins/negaresh:ro "$WP_IMAGE" >/dev/null
for _ in $(seq 1 60); do curl -s -o /dev/null "$URL/" && break; sleep 2; done
for _ in $(seq 1 30); do wp core install --url="$URL" --title=Negaresh --admin_user=admin --admin_password=admin \
  --admin_email=a@example.com --skip-email >/dev/null 2>&1 && break; sleep 2; done
echo "WordPress $(wp core version), PHP $(docker exec "$WEB" php -r 'echo PHP_VERSION;')"
wp rewrite structure '/%postname%/' >/dev/null 2>&1
wp plugin activate negaresh >/dev/null
# setup noise (for example the database not being up yet) is not ours
docker exec "$WEB" sh -c ': > /var/www/html/wp-content/debug.log'
printf '%s\n' '<?php' "add_shortcode('negaresh_test', function (\$a) { return '<span class=\"sc\">' . esc_html(\$a['label'] ?? '') . '</span>'; });" \
  | docker exec -i -u 33 "$WEB" sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cat > /var/www/html/wp-content/mu-plugins/e2e.php'

wp post create - --post_title=e2e --post_name=e2e --post_status=publish >/dev/null <<'HTML'
<!-- wp:paragraph --><p>سلام دوستان، به <a href="https://example.com/?a=1&amp;b=2">این صفحه</a> سر بزنید... عدد ٤٥٦ و A&amp;B و &lt;b&gt; متن</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[negaresh_test label="a,b"]<!-- /wp:shortcode -->
<!-- wp:code --><pre class="wp-block-code"><code>x ... y // 123</code></pre><!-- /wp:code -->
HTML
POST_ID="$(wp post list --name=e2e --post_type=post --field=ID)"
STORED="$(wp post get "$POST_ID" --field=post_content)"
check "fresh install fixes on save: stored text fixed (I4)" 'عدد ۴۵۶' "$STORED"
check "stored shortcode and code untouched (I4)" '[negaresh_test label="a,b"]' "$STORED"
check "post marked as fixed with the current rules (I4)" "$(wp eval 'echo (new Negaresh_Settings())->rules_hash();')" "$(wp post meta get "$POST_ID" _negaresh_fixed)"
PAGE="$(curl -s "$URL/e2e/")"
# themes differ in casing (Twenty Twenty-One writes <!doctype html>)
check "page starts with doctype (B23)" "<!doctype html>" "$(head -c 15 <<<"$PAGE" | tr 'A-Z' 'a-z')"
check "link kept (B1)" '<a href="https://example.com/?a=1&amp;b=2">این صفحه</a>' "$PAGE"
check "entities kept (B1, B2)" 'A&amp;B و &lt;b&gt; متن' "$PAGE"
check "defaults applied without saving (B5)" 'عدد ۴۵۶' "$PAGE"
check "shortcode ran with its attribute (B3)" '<span class="sc">a,b</span>' "$PAGE"
check "code block untouched (B3)" '<code>x ... y // 123</code>' "$PAGE"
check "RSS feed is valid XML (B23)" "ok" "$(curl -s "$URL/feed/" | python3 -c 'import sys,xml.dom.minidom as m; m.parseString(sys.stdin.buffer.read()); print("ok")' 2>&1)"
check "REST content fixed" 'عدد ۴۵۶' "$(curl -s "$URL/wp-json/wp/v2/posts?slug=e2e" | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["content"]["rendered"])')"

JAR="$(mktemp)"; curl -s -c "$JAR" -b "$JAR" -o /dev/null "$URL/wp-login.php"
LOGIN="$(curl -s -c "$JAR" -b "$JAR" -o /dev/null -w '%{http_code}' -d 'log=admin&pwd=admin&testcookie=1' "$URL/wp-login.php")"
check "admin login works (B23)" "302" "$LOGIN"
SETTINGS="$(curl -s -b "$JAR" "$URL/wp-admin/options-general.php?page=negaresh-options")"
check "settings page renders" 'name="negaresh_options[fix_dashes]"' "$SETTINGS"
NONCE="$(grep -oP "name=['\"]_wpnonce['\"] value=['\"]\K[^'\"]+" <<<"$SETTINGS")"
curl -s -b "$JAR" -o /dev/null --data-urlencode option_page=negaresh --data-urlencode action=update \
  --data-urlencode "_wpnonce=$NONCE" --data-urlencode "negaresh_options[fix_english_numbers]=1" \
  --data-urlencode "negaresh_options[post_types][]=bogus" "$URL/wp-admin/options.php"
check "settings saved and sanitized" '"fix_english_numbers":true,"fix_numeral_symbols":false' "$(wp option get negaresh_options --format=json)"

# I5: the settings page, the Plugins screen link, reset, and the preview endpoint as the page uses it
SETTINGS="$(curl -s -b "$JAR" "$URL/wp-admin/options-general.php?page=negaresh-options")"
check "preview box on the settings page (I5)" 'id="negaresh-preview-input"' "$SETTINGS"
check "admin script loaded on the settings page (I5)" 'negaresh/assets/admin.js' "$SETTINGS"
check "Settings link on the Plugins screen (I5)" 'options-general.php?page=negaresh-options">Settings</a>' "$(curl -s -b "$JAR" "$URL/wp-admin/plugins.php")"
NONCE="$(grep -oP "name=['\"]_wpnonce['\"] value=['\"]\K[^'\"]+" <<<"$SETTINGS")"
curl -s -b "$JAR" -o /dev/null --data-urlencode option_page=negaresh --data-urlencode action=update \
  --data-urlencode "_wpnonce=$NONCE" --data-urlencode "negaresh_options[reset_rules]=Reset" "$URL/wp-admin/options.php"
check "reset restores the default rules (I5)" '"fix_english_numbers":false,"fix_numeral_symbols":false,"fix_misc_non_persian_chars":false,"fix_hamzeh":true' "$(wp option get negaresh_options --format=json)"
REST_NONCE="$(grep -oP 'createNonceMiddleware\(\s*"\K[^"]+' <<<"$SETTINGS")"
PREVIEW="$(curl -s -b "$JAR" -H "X-WP-Nonce: $REST_NONCE" -H 'Content-Type: application/json' \
  -d '{"text":"<p>عدد 123 ...</p>","rules":{"fix_english_numbers":true,"fix_three_dots":true,"remove_spaces_before_ellipsis":true}}' "$URL/wp-json/negaresh/v1/preview")"
check "preview uses the unsaved boxes (I5)" '<p>عدد ۱۲۳…</p>' "$(python3 -c 'import json,sys; print(json.load(sys.stdin)["text"])' <<<"$PREVIEW" 2>&1)"
check "preview refused without login (I5)" '401' "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"text":"x"}' "$URL/wp-json/negaresh/v1/preview")"
rm -f "$JAR"

# Back to the defaults (the form above left only one rule on): save mode, default rules.
wp option delete negaresh_options >/dev/null

# The block editor saves through the REST API.
APP_PASS="$(wp user application-password create admin e2e --porcelain)"
REST_ID="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' \
  -d '{"title":"rest","status":"publish","content":"<!-- wp:paragraph --><p>از ویرایشگر بلوک ... عدد ٧٨٩</p><!-- /wp:paragraph -->"}' \
  "$URL/wp-json/wp/v2/posts" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("id",""))')"
check "block editor (REST) save is fixed (I4)" '<p>از ویرایشگر بلوک… عدد ۷۸۹</p>' "$(wp post get "$REST_ID" --field=post_content)"

# I6: opting out in the block editor applies to the save that ticks the box, and back again.
SKIP_ID="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' \
  -d '{"title":"skip","status":"publish","content":"<p>دست نزن ...</p>","meta":{"_negaresh_skip":true}}' \
  "$URL/wp-json/wp/v2/posts" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("id",""))')"
check "opted out post is stored as typed (I6)" '<p>دست نزن ...</p>' "$(wp post get "$SKIP_ID" --field=post_content)"
curl -s -o /dev/null -u "admin:$APP_PASS" -H 'Content-Type: application/json' \
  -d '{"content":"<p>حالا اصلاح شود ...</p>","meta":{"_negaresh_skip":false}}' "$URL/wp-json/wp/v2/posts/$SKIP_ID"
check "unticking fixes that same save (I6)" '<p>حالا اصلاح شود…</p>' "$(wp post get "$SKIP_ID" --field=post_content)"
MARKED_ID="$(wp post create --post_title=m --post_status=publish --porcelain \
  --post_content='<div class="negaresh-skip"><p>نقل قول ...</p></div><p>بقیه ...</p>')"
check "negaresh-skip markup left alone, the rest fixed (I6)" '<div class="negaresh-skip"><p>نقل قول ...</p></div><p>بقیه…</p>' "$(wp post get "$MARKED_ID" --field=post_content)"
FIXED="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' -d '{"content":"<p>متن ...</p>","title":"t"}' "$URL/wp-json/negaresh/v1/fix")"
check "fix this post endpoint (I6)" '<p>متن…</p>' "$(python3 -c 'import json,sys; print(json.load(sys.stdin)["content"])' <<<"$FIXED" 2>&1)"

# I5: titles, when enabled, are fixed on save too.
wp option update negaresh_options '{"fix_titles":true}' --format=json >/dev/null
TITLE_ID="$(wp post create --post_title='عنوان ...' --post_status=publish --post_content='<p>متن</p>' --porcelain)"
check "title fixed on save when enabled (I5)" 'عنوان…' "$(wp post get "$TITLE_ID" --field=post_title)"

# Display mode never changes what is stored.
wp option update negaresh_options '{"mode":"display"}' --format=json >/dev/null
DISPLAY_ID="$(wp post create --post_title=d --post_name=display-mode --post_status=publish --post_content='<p>حالت نمایش ...</p>' --porcelain)"
check "display mode leaves stored text alone (I4)" '<p>حالت نمایش ...</p>' "$(wp post get "$DISPLAY_ID" --field=post_content)"
# "..." checks B27 too: the fix must run before wptexturize turns "..." into &#8230;
check "display mode fixes the page, before wptexturize (I4, B27)" 'حالت نمایش…' "$(curl -s "$URL/display-mode/")"

# I6: the bulk tool's endpoints (admin only) see the stored, unfixed display mode post.
BULK_FIND="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' -d '{}' "$URL/wp-json/negaresh/v1/bulk/find")"
check "bulk find lists unfixed posts (I6)" "$DISPLAY_ID" "$BULK_FIND"
BULK_DRY="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' -d "{\"ids\":[$DISPLAY_ID]}" "$URL/wp-json/negaresh/v1/bulk/process")"
check "bulk scan reports the change (I6)" '"changed":true' "$BULK_DRY"
check "bulk scan saved nothing (I6)" '<p>حالت نمایش ...</p>' "$(wp post get "$DISPLAY_ID" --field=post_content)"
check "bulk endpoints refuse anonymous calls (I6)" '401' "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{}' "$URL/wp-json/negaresh/v1/bulk/find")"

# I6: WP-CLI fixes existing posts (the site is in display mode here, so stored posts are unfixed).
DRY="$(wp negaresh fix "$DISPLAY_ID" 2>&1)"
check "wp negaresh fix is a dry run by default (I6)" 'would change' "$DRY"
check "dry run saved nothing (I6)" '<p>حالت نمایش ...</p>' "$(wp post get "$DISPLAY_ID" --field=post_content)"
wp negaresh fix "$DISPLAY_ID" --apply >/dev/null 2>&1
check "wp negaresh fix --apply fixes the post (I6)" '<p>حالت نمایش…</p>' "$(wp post get "$DISPLAY_ID" --field=post_content)"
check "the change left a revision to undo it (I6)" '<p>حالت نمایش ...</p>' "$(wp post list --post_type=revision --post_parent="$DISPLAY_ID" --post_status=inherit --field=post_content)"
check "fixed post is marked (I6)" "$(wp eval 'echo (new Negaresh_Settings())->rules_hash();')" "$(wp post meta get "$DISPLAY_ID" _negaresh_fixed)"
EMBED_ID="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' \
  -d '{"title":"embed","status":"publish","content":"<p>ویدیو ...</p><iframe src=\"https://example.com/embed\" width=\"300\"></iframe>"}' \
  "$URL/wp-json/wp/v2/posts" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("id",""))')"
# WP-CLI turns kses off by itself; switch WordPress's kses on to act like a user without
# unfiltered_html (multisite site admin), who may use the bulk tool page.
wp eval "kses_init_filters(); \$GLOBALS['negaresh_bulk']->process($EMBED_ID, true);" >/dev/null 2>&1
check "fixing keeps embeds even where kses is on (I6)" '<p>ویدیو…</p><iframe src="https://example.com/embed" width="300"></iframe>' "$(wp post get "$EMBED_ID" --field=post_content)"
OPT_ID="$(curl -s -u "admin:$APP_PASS" -H 'Content-Type: application/json' \
  -d '{"title":"opt","status":"publish","content":"<p>نه ...</p>","meta":{"_negaresh_skip":true}}' \
  "$URL/wp-json/wp/v2/posts" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("id",""))')"
wp negaresh fix --all --apply >/dev/null 2>&1
check "opted out post untouched by a full run (I6)" '<p>نه ...</p>' "$(wp post get "$OPT_ID" --field=post_content)"
STATUS="$(wp negaresh status --format=json 2>&1)"
check "wp negaresh status (P3-5)" '"mode":"display"' "$STATUS"
check "wp negaresh status counts opted out posts (P3-5)" '"opted_out":1' "$STATUS"
check "wp negaresh text (I6)" 'عدد ۴۵۶…' "$(wp negaresh text 'عدد ٤٥٦ ...' 2>&1)"

# BROWSER=1: drive the settings page in headless Chromium too (tests/e2e/browser.sh, I5)
if [ "${BROWSER:-0}" = 1 ]; then
  # capture first: "grep -q" in a pipe exits early, tee dies of SIGPIPE and pipefail hides the FAIL
  BROWSER_OUT="$("$ROOT/tests/e2e/browser.sh" 2>&1 || true)"
  grep -E '^(PASS|FAIL)' <<<"$BROWSER_OUT" || { echo "FAIL  browser check produced no results"; echo "$BROWSER_OUT" | tail -20; }
  # A pass needs results, no FAIL, and the DONE line printed after the last check.
  if ! grep -q '^PASS' <<<"$BROWSER_OUT" || grep -q '^FAIL' <<<"$BROWSER_OUT" || ! grep -q '^DONE' <<<"$BROWSER_OUT"; then
    FAIL=1
    grep -q '^DONE' <<<"$BROWSER_OUT" || echo "FAIL  browser check did not finish"
  fi
fi

LOG="$(docker exec "$WEB" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null' || true)"
if [ -z "$LOG" ]; then echo "PASS  debug.log is empty"; else echo "FAIL  debug.log:"; echo "$LOG" | head -20; FAIL=1; fi

if [ "${KEEP:-0}" != 1 ]; then
  wp plugin deactivate negaresh >/dev/null && wp plugin uninstall negaresh --skip-delete >/dev/null
  check "uninstall removed options (B19)" "none" "$(wp option list --search='negaresh*' --format=count | sed 's/^0$/none/')"
  check "uninstall removed post markers (I4)" "none" "$(wp post meta list "$POST_ID" --keys=_negaresh_fixed --format=count | sed 's/^0$/none/')"
fi

[ "$FAIL" = 0 ] && echo "ALL PASSED" || { echo "SOME CHECKS FAILED"; exit 1; }
