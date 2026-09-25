#!/usr/bin/env bash
# Runs tests/e2e/browser.mjs in the official Playwright image (host network: the site redirects to
# its own address, http://127.0.0.1:8089) against the site started by
#   KEEP=1 tests/e2e/run.sh
# Optional: LANG_FA=1 switches the site to Persian first (RTL). Screenshots go to build/shots/.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.62.1-noble}"
VERSION="${IMAGE##*:v}"; VERSION="${VERSION%%-*}"
mkdir -p "$ROOT/build/shots"
if [ "${LANG_FA:-0}" = 1 ]; then
  docker run --rm -i --network negaresh-e2e --volumes-from negaresh-e2e-wp --user 33:33 -e HOME=/tmp \
    -e WORDPRESS_DB_HOST=negaresh-e2e-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
    wordpress:cli-php8.3 wp language core install fa_IR --activate >/dev/null
fi
# Save mode with the default rules, so "stored as typed" really tests the opt out.
docker run --rm -i --network negaresh-e2e --volumes-from negaresh-e2e-wp --user 33:33 -e HOME=/tmp \
  -e WORDPRESS_DB_HOST=negaresh-e2e-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
  wordpress:cli-php8.3 wp option update negaresh_options '{"mode":"save"}' --format=json >/dev/null
docker run --rm --network host -e WP_URL=http://127.0.0.1:8089 -e SHOTS=/shots \
  -e SHOT_NAME="${LANG_FA:+fa}" -v "$ROOT/tests/e2e":/e2e:ro -v "$ROOT/build/shots":/shots "$IMAGE" \
  bash -c "cd /tmp && npm init -y >/dev/null && npm i --silent playwright@$VERSION >/dev/null && cp /e2e/browser.mjs . && node browser.mjs"
