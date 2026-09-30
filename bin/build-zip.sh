#!/usr/bin/env bash
# Builds the installable plugin zip from a git ref: only wp-content/plugins/negaresh, inside a
# top level negaresh/ folder (the layout WordPress and wordpress.org expect). Without the .po/.mo:
# wordpress.org delivers translations as language packs (review of 2026-09-27); the .pot stays.
#   bin/build-zip.sh [ref=HEAD] [output=build/negaresh.zip]
set -euo pipefail
cd "$(dirname "$0")/.."
REF="${1:-HEAD}"
OUT="${2:-build/negaresh.zip}"
mkdir -p "$(dirname "$OUT")"
git archive --format=zip --prefix=negaresh/ -o "$OUT" "$REF:wp-content/plugins/negaresh" \
  -- . ':(exclude)languages/*.po' ':(exclude)languages/*.mo'
echo "$OUT"
