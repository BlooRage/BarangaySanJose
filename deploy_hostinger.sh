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

ensure_runtime_dirs() {
  local dirs=(
    "$ROOT_DIR/UnifiedFileAttachment/IssuedDocuments"
    "$ROOT_DIR/UnifiedFileAttachment/IssuedDocuments/Generated"
    "$ROOT_DIR/UnifiedFileAttachment/IssuedDocuments/QR"
    "$ROOT_DIR/UnifiedFileAttachment/IssuedDocuments/Tmp"
    "$ROOT_DIR/UnifiedFileAttachment/IssuedDocuments/Tmp/template_assets"
  )

  for dir in "${dirs[@]}"; do
    mkdir -p "$dir"
    chmod 0775 "$dir" 2>/dev/null || true
    echo "Prepared runtime directory: $dir"
  done
}

verify_issuance_templates() {
  local templates=(
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/Certificate of Good Moral.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/Certificate of Indigency.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/CertificateForJailVisitation.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/ClearanceForBusinessPermit.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/FirstTimeJobSeeker.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/GeneralCertification.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/GeneralClearance.pdf"
    "$ROOT_DIR/Resident-End/Certificates/DocumentIssuance/TricycleClearance.pdf"
  )

  local missing=0
  for template in "${templates[@]}"; do
    if [[ ! -f "$template" ]]; then
      echo "Missing issuance template: $template"
      missing=1
    fi
  done

  if [[ "$missing" -ne 0 ]]; then
    echo "Template verification failed."
    exit 1
  fi
}

install_deps "$ROOT_DIR/composer-email-handler"
install_deps "$ROOT_DIR/PhpFiles/PhpOffice"
ensure_runtime_dirs
verify_issuance_templates

echo "Dependency and runtime deployment finished."
