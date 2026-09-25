#!/usr/bin/env bash
# Builds the installable plugin zip from a git ref: only wp-content/plugins/negaresh, inside a
# top level negaresh/ folder (the layout WordPress and wordpress.org expect).
#   bin/build-zip.sh [ref=HEAD] [output=build/negaresh.zip]
set -euo pipefail
cd "$(dirname "$0")/.."
REF="${1:-HEAD}"
OUT="${2:-build/negaresh.zip}"
mkdir -p "$(dirname "$OUT")"
git archive --format=zip --prefix=negaresh/ -o "$OUT" "$REF:wp-content/plugins/negaresh"
echo "$OUT"
