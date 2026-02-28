#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer is not installed or not in PATH."
  exit 1
fi

install_deps() {
  local dir="$1"
  local lock_file="$dir/composer.lock"
  local json_file="$dir/composer.json"

  if [[ ! -f "$json_file" ]]; then
    echo "Skipping $dir (composer.json not found)."
    return
  fi

  echo "Installing Composer dependencies in: $dir"
  if [[ -f "$lock_file" ]]; then
    composer install \
      --working-dir="$dir" \
      --no-dev \
      --prefer-dist \
      --no-interaction \
      --optimize-autoloader
  else
    composer update \
      --working-dir="$dir" \
      --no-dev \
      --prefer-dist \
      --no-interaction \
      --optimize-autoloader
  fi
}

install_deps "$ROOT_DIR/composer-email-handler"
install_deps "$ROOT_DIR/PhpFiles/PhpOffice"

echo "Dependency deployment finished."
