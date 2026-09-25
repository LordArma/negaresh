#!/usr/bin/env bash
# Regenerates .wordpress-org/ (icon, banners, screenshots) from a site started with
#   KEEP=1 tests/e2e/run.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.62.1-noble}"
VERSION="${IMAGE##*:v}"; VERSION="${VERSION%%-*}"
wp() { docker run --rm -i --network negaresh-e2e --volumes-from negaresh-e2e-wp --user 33:33 -e HOME=/tmp \
  -e WORDPRESS_DB_HOST=negaresh-e2e-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
  wordpress:cli-php8.3 wp "$@"; }
# English admin, default rules, and one unfixed post for the bulk tool screenshot.
wp language core activate en_US >/dev/null 2>&1 || true
wp option update negaresh_options '{"mode":"display"}' --format=json >/dev/null
wp post create --post_title='نمونه' --post_status=publish --post_content='<p>متن قدیمی ... کتاب ها ٤٥٦</p>' >/dev/null
wp option update negaresh_options '{"mode":"save"}' --format=json >/dev/null
mkdir -p "$ROOT/.wordpress-org"
docker run --rm --network host -e WP_URL=http://127.0.0.1:8089 -v "$ROOT/tests/e2e":/e2e:ro -v "$ROOT/.wordpress-org":/out "$IMAGE" \
  bash -c "cd /tmp && npm init -y >/dev/null && npm i --silent playwright@$VERSION >/dev/null && cp /e2e/wporg-assets.mjs . && node wporg-assets.mjs"
