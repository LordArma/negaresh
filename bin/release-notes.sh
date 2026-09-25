#!/usr/bin/env bash
# Prints the CHANGELOG.md section of a version as GitHub release notes: internal bug and
# improvement IDs like "(B3)" or "(I4, B9)" are removed, install instructions are appended.
#   bin/release-notes.sh 4.1.0
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION="${1:?usage: bin/release-notes.sh VERSION}"
SECTION="$(awk -v v="$VERSION" '
  index($0, "## [" v "]") == 1 { on = 1; next }
  on && /^## \[/ { exit }
  on { print }
' CHANGELOG.md)"
if [ -z "$(tr -d '[:space:]' <<<"$SECTION")" ]; then
  echo "CHANGELOG.md has no section for $VERSION" >&2
  exit 1
fi
REQUIRES_WP="$(sed -n 's/^ \* Requires at least: //p' wp-content/plugins/negaresh/negaresh.php)"
REQUIRES_PHP="$(sed -n 's/^ \* Requires PHP: //p' wp-content/plugins/negaresh/negaresh.php)"
TESTED="$(sed -n 's/^ \* Tested up to: //p' wp-content/plugins/negaresh/negaresh.php)"
sed -E 's/ \(([BI][0-9]+(, )?)+\)//g' <<<"$SECTION" | sed -e '/./,$!d'
cat <<NOTES

### Install or upgrade
Download \`negaresh.zip\` below and upload it in *Plugins → Add New → Upload Plugin*. When WordPress
asks, choose **Replace current with uploaded**.

Requires WordPress $REQUIRES_WP+ and PHP $REQUIRES_PHP+, tested up to WordPress $TESTED.
NOTES
