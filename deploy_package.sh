#!/usr/bin/env bash
set -euo pipefail

# Build a deployment zip that excludes local/dev/repo-only files.
OUT_DIR="${1:-dist}"
OUT_FILE="${2:-barangaysanjose-deploy.zip}"

mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR/$OUT_FILE"

zip -r "$OUT_DIR/$OUT_FILE" . \
  -x ".git/*" \
  -x ".gitignore" \
  -x ".DS_Store" \
  -x "desktop.ini" \
  -x "dist/*"

echo "Created $OUT_DIR/$OUT_FILE"
