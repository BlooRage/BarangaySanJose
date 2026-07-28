#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UPLOAD_LINK="$ROOT_DIR/UnifiedFileAttachment"
# Keep user files outside the Git checkout so a fresh deployment cannot remove
# them. Override this in Hostinger with APP_PERSISTENT_UPLOAD_DIR if desired.
PERSISTENT_UPLOAD_DIR="${APP_PERSISTENT_UPLOAD_DIR:-$(dirname "$ROOT_DIR")/.barangaysanjose-data/UnifiedFileAttachment}"

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

prepare_persistent_uploads() {
  if [[ "$PERSISTENT_UPLOAD_DIR" == "$UPLOAD_LINK" || "$PERSISTENT_UPLOAD_DIR" == "$UPLOAD_LINK/"* ]]; then
    echo "APP_PERSISTENT_UPLOAD_DIR must be outside the project checkout."
    exit 1
  fi

  mkdir -p "$PERSISTENT_UPLOAD_DIR"

  # First deployment: preserve files already stored inside the checkout.
  if [[ -d "$UPLOAD_LINK" && ! -L "$UPLOAD_LINK" ]]; then
    echo "Migrating existing runtime files to persistent storage..."
    cp -a "$UPLOAD_LINK/." "$PERSISTENT_UPLOAD_DIR/"
    rm -rf -- "$UPLOAD_LINK"
  elif [[ -e "$UPLOAD_LINK" && ! -L "$UPLOAD_LINK" ]]; then
    echo "$UPLOAD_LINK exists but is not a directory or symlink."
    exit 1
  fi

  if [[ -L "$UPLOAD_LINK" ]]; then
    current_target="$(readlink "$UPLOAD_LINK")"
    if [[ "$current_target" != "$PERSISTENT_UPLOAD_DIR" ]]; then
      rm -- "$UPLOAD_LINK"
      ln -s "$PERSISTENT_UPLOAD_DIR" "$UPLOAD_LINK"
    fi
  else
    ln -s "$PERSISTENT_UPLOAD_DIR" "$UPLOAD_LINK"
  fi

  local dirs=(
    "$PERSISTENT_UPLOAD_DIR/Documents"
    "$PERSISTENT_UPLOAD_DIR/IDPictures"
    "$PERSISTENT_UPLOAD_DIR/DocumentRequests"
    "$PERSISTENT_UPLOAD_DIR/Content/Announcements/EditorUploads"
    "$PERSISTENT_UPLOAD_DIR/Content/SiteContent"
    "$PERSISTENT_UPLOAD_DIR/IssuedDocuments"
    "$PERSISTENT_UPLOAD_DIR/IssuedDocuments/Generated"
    "$PERSISTENT_UPLOAD_DIR/IssuedDocuments/QR"
    "$PERSISTENT_UPLOAD_DIR/IssuedDocuments/Tmp"
    "$PERSISTENT_UPLOAD_DIR/IssuedDocuments/Tmp/template_assets"
  )

  for dir in "${dirs[@]}"; do
    mkdir -p "$dir"
    chmod 0775 "$dir" 2>/dev/null || true
    echo "Prepared runtime directory: $dir"
  done

  echo "Persistent uploads: $PERSISTENT_UPLOAD_DIR"
  echo "Application upload link: $UPLOAD_LINK"
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
prepare_persistent_uploads
verify_issuance_templates

echo "Dependency and runtime deployment finished."
