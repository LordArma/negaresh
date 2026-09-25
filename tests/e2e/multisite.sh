#!/usr/bin/env bash
# Multisite end to end check (I10c): network defaults set in Network Admin reach sites without
# their own settings, a site's own settings win, uninstall cleans the network.
#   tests/e2e/multisite.sh            (WP_IMAGE picks the WordPress image, as for run.sh)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PLUGIN="$ROOT/wp-content/plugins/negaresh"
WP_IMAGE="${WP_IMAGE:-wordpress:php8.3-apache}"
CLI_IMAGE="${CLI_IMAGE:-wordpress:cli-php8.3}"
NET=negaresh-ms; DB=negaresh-ms-db; WEB=negaresh-ms-wp; URL=http://127.0.0.1:8092
FAIL=0
# The multisite constants; wp-config.php reads them from this variable, so the web container and
# wp-cli must both get it (wp-cli otherwise sees a single site).
EXTRA="define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false); define('MULTISITE', true); define('SUBDOMAIN_INSTALL', false); define('DOMAIN_CURRENT_SITE', '127.0.0.1:8092'); define('PATH_CURRENT_SITE', '/'); define('SITE_ID_CURRENT_SITE', 1); define('BLOG_ID_CURRENT_SITE', 1);"

cleanup() { docker rm -f "$DB" "$WEB" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT
trap 'echo "FAIL  multisite.sh stopped at line $LINENO: $BASH_COMMAND"' ERR
wp() { docker run --rm -i --network "$NET" --volumes-from "$WEB" --user 33:33 -e HOME=/tmp \
  -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
  -e WORDPRESS_CONFIG_EXTRA="$EXTRA" "$CLI_IMAGE" wp "$@"; }
check() { if grep -qF -- "$2" <<<"$3"; then echo "PASS  $1"; else echo "FAIL  $1 (expected: $2)"; echo "      got: ${3:0:300}"; FAIL=1; fi; }

cleanup
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp \
  -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11 >/dev/null
docker run -d --name "$WEB" --network "$NET" -p 127.0.0.1:8092:80 -e WORDPRESS_DB_HOST="$DB" \
  -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp -e WORDPRESS_DEBUG=1 \
  -e WORDPRESS_CONFIG_EXTRA="$EXTRA" \
  -v "$PLUGIN":/var/www/html/wp-content/plugins/negaresh:ro "$WP_IMAGE" >/dev/null
for _ in $(seq 1 60); do curl -s -o /dev/null "$URL/wp-login.php" && break; sleep 2; done
for _ in $(seq 1 30); do wp core multisite-install --url="$URL" --title=Network --admin_user=admin --admin_password=admin \
  --admin_email=a@example.com --skip-email --skip-config >/dev/null 2>&1 && break; sleep 2; done
echo "WordPress $(wp core version) multisite, PHP $(docker exec "$WEB" php -r 'echo PHP_VERSION;')"
wp plugin activate negaresh --network >/dev/null
docker exec "$WEB" sh -c ': > /var/www/html/wp-content/debug.log'

# Network Admin → Settings → Negaresh, as the super admin.
JAR="$(mktemp)"; curl -s -c "$JAR" -b "$JAR" -o /dev/null "$URL/wp-login.php"
curl -s -c "$JAR" -b "$JAR" -o /dev/null -d 'log=admin&pwd=admin&testcookie=1' "$URL/wp-login.php"
PAGE="$(curl -s -b "$JAR" "$URL/wp-admin/network/settings.php?page=negaresh-network")"
check "network settings page renders (I10c)" 'name="negaresh_options[fix_english_numbers]"' "$PAGE"
NONCE="$(grep -oP "name=['\"]_wpnonce['\"] value=['\"]\K[^'\"]+" <<<"$PAGE" | head -1)"
check "forged nonce refused (I10c)" '403' "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' --data-urlencode '_wpnonce=forged' \
  --data-urlencode 'negaresh_options[mode]=display' "$URL/wp-admin/network/edit.php?action=negaresh_network")"
SAVED="$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' --data-urlencode "_wpnonce=$NONCE" \
  --data-urlencode '_wp_http_referer=/wp-admin/network/settings.php?page=negaresh-network' \
  --data-urlencode 'negaresh_options[mode]=display' --data-urlencode 'negaresh_options[fix_english_numbers]=1' \
  --data-urlencode 'negaresh_options[fix_three_dots]=1' --data-urlencode 'negaresh_options[remove_spaces_before_ellipsis]=1' \
  "$URL/wp-admin/network/edit.php?action=negaresh_network")"
check "network defaults saved (I10c)" 'updated=true' "$SAVED"
check "stored as network option (I10c)" '"mode":"display","post_types":[]' "$(wp site option get negaresh_network_options --format=json)"
rm -f "$JAR"

# A new site inherits the network defaults.
wp site create --slug=two --title=Two >/dev/null
TWO="$URL/two/"
GOT="$(wp --url="$TWO" eval 'echo wp_json_encode((new Negaresh_Settings())->get());')"
check "new site uses the network mode (I10c)" '"mode":"display"' "$GOT"
check "new site uses the network rules (I10c)" '"fix_english_numbers":true' "$GOT"
ID="$(wp --url="$TWO" post create --post_title=t --post_status=publish --post_content='<p>عدد 123 ...</p>' --porcelain)"
check "display mode from the network: stored as typed (I10c)" '<p>عدد 123 ...</p>' "$(wp --url="$TWO" post get "$ID" --field=post_content)"
check "network rules on the page (I10c)" 'عدد ۱۲۳…' "$(curl -sL "${TWO}?p=$ID")"

# A site's own settings win.
wp --url="$TWO" option update negaresh_options '{"mode":"save","fix_english_numbers":false}' --format=json >/dev/null
GOT="$(wp --url="$TWO" eval 'echo wp_json_encode((new Negaresh_Settings())->get());')"
check "site setting wins (I10c)" '"mode":"save"' "$GOT"
check "site rule wins (I10c)" '"fix_english_numbers":false' "$GOT"

# WordPress's own update check cannot reach wordpress.org from old images (TLS); that core
# warning is not ours. Everything else counts.
LOG="$(docker exec "$WEB" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null' | grep -v 'wp-includes/update.php' || true)"
if [ -z "$LOG" ]; then echo "PASS  debug.log is empty"; else echo "FAIL  debug.log:"; echo "$LOG" | head -20; FAIL=1; fi

# Network uninstall removes the network defaults and every site's settings.
wp plugin deactivate negaresh --network >/dev/null
wp plugin uninstall negaresh --skip-delete >/dev/null
check "uninstall removed network defaults (I10c)" 'none' "$(wp site option get negaresh_network_options >/dev/null 2>&1 && echo present || echo none)"
check "uninstall removed site settings (I10c)" 'none' "$(wp --url="$TWO" option get negaresh_options >/dev/null 2>&1 && echo present || echo none)"

[ "$FAIL" = 0 ] && echo "ALL PASSED" || { echo "SOME CHECKS FAILED"; exit 1; }
