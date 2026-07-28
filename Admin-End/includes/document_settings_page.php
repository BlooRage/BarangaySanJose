<?php
declare(strict_types=1);

if (!isset($documentSettingsModuleConfig, $documentSettingsRows, $documentSettingsActionUrl, $documentSettingsBackUrl)) {
    throw new RuntimeException('Document settings page context is incomplete.');
}

$documentSettingsTitle = (string)($documentSettingsModuleConfig['label'] ?? 'Document Settings');
$documentSettingsDescription = (string)($documentSettingsModuleConfig['description'] ?? '');
$documentSettingsAppliesTo = (string)($documentSettingsModuleConfig['applies_to'] ?? '');
$documentSettingsMeta = dms_max_updated_meta((array)$documentSettingsRows);
$documentSettingsUpdatedAt = trim((string)($documentSettingsMeta['updated_at'] ?? ''));
$documentSettingsUpdatedAtLabel = 'No signatory uploads saved yet.';
if ($documentSettingsUpdatedAt !== '') {
    $updatedTimestamp = strtotime($documentSettingsUpdatedAt);
    $documentSettingsUpdatedAtLabel = $updatedTimestamp !== false
        ? date('M j, Y g:i A', $updatedTimestamp)
        : $documentSettingsUpdatedAt;
}
$documentSettingsUpdatedByLabel = trim((string)($documentSettingsMeta['updated_by_user_id'] ?? ''));
$documentSettingsUpdatedByLabel = $documentSettingsUpdatedByLabel !== '' ? $documentSettingsUpdatedByLabel : 'Not recorded yet';
$documentSettingsShowCopySignatureToggle = !empty($documentSettingsShowCopySignatureToggle);
$documentSettingsCopySignatureEnabled = !isset($documentSettingsCopySignatureEnabled) || !empty($documentSettingsCopySignatureEnabled);
$documentSettingsShowFieldVisibility = !empty($documentSettingsShowFieldVisibility);
$documentSettingsFieldVisibility = isset($documentSettingsFieldVisibility) && is_array($documentSettingsFieldVisibility) ? $documentSettingsFieldVisibility : [];
$documentSettingsShowPrintHeaderToggle = !empty($documentSettingsShowPrintHeaderToggle);
$documentSettingsPrintHeaderEnabled = !isset($documentSettingsPrintHeaderEnabled) || !empty($documentSettingsPrintHeaderEnabled);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="<?= htmlspecialchars(appUrl('Images/favicon_sanjose.png?v=20260211'), ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($documentSettingsTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    .document-settings-shell {
      border-color: #f1e1cf !important;
    }
    .document-settings-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      margin-bottom: 0.4rem;
    }
    .document-settings-lead {
      color: #5b6470;
      max-width: 820px;
    }
    .document-settings-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.8fr);
      gap: 1.25rem;
      align-items: start;
    }
    .document-settings-card,
    .document-settings-panel {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.04);
    }
    .document-settings-card {
      padding: 1.2rem;
    }
    .document-settings-panel {
      overflow: hidden;
    }
    .document-settings-panel-head {
      padding: 1.1rem 1.2rem;
      border-bottom: 1px solid #edf1f5;
      background: linear-gradient(180deg, #fffaf4 0%, #ffffff 100%);
    }
    .document-settings-panel-body {
      padding: 1.1rem 1.2rem 1.2rem;
      display: grid;
      gap: 1rem;
    }
    .document-settings-stat {
      border: 1px solid #edf1f5;
      border-radius: 14px;
      padding: 0.95rem 1rem;
      background: #fbfcfe;
    }
    .document-settings-stat-label {
      margin-bottom: 0.25rem;
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #667085;
    }
    .document-settings-stat-value {
      color: #1f2937;
      font-size: 0.96rem;
      word-break: break-word;
    }
    .document-settings-callout {
      border: 1px solid #f4d8b8;
      border-radius: 16px;
      background: #fff7ef;
      padding: 1rem 1.05rem;
      color: #7a4b00;
    }
    .document-settings-callout p:last-child {
      margin-bottom: 0;
    }
    .document-settings-signatory {
      border: 1px solid #eceff3;
      border-radius: 18px;
      background: #fff;
      padding: 1rem;
      display: grid;
      gap: 1rem;
    }
    .document-settings-signatory-top {
      display: flex;
      justify-content: space-between;
      gap: 0.9rem;
      align-items: flex-start;
      flex-wrap: wrap;
    }
    .document-settings-signatory-title {
      margin: 0;
      color: #1f2937;
      font-size: 1rem;
    }
    .document-settings-signatory-subcopy {
      color: #667085;
      margin: 0.3rem 0 0;
      font-size: 0.92rem;
    }
    .document-settings-source-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.75rem;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      border: 1px solid transparent;
      white-space: nowrap;
    }
    .document-settings-source-badge.is-seat {
      color: #0f4c81;
      background: #e8f2fd;
      border-color: #c8def9;
    }
    .document-settings-source-badge.is-manual {
      color: #7a2e0b;
      background: #fff0e7;
      border-color: #f7d1bb;
    }
    .document-settings-signatory-grid {
      display: grid;
      grid-template-columns: minmax(300px, 1fr) minmax(300px, 0.75fr);
      gap: 1.25rem;
      align-items: start;
    }
    .document-settings-preview {
      border: 1px dashed #d6dbe3;
      border-radius: 16px;
      background: #f8fafc;
      padding: 0.85rem;
      display: grid;
      gap: 0.75rem;
    }
    .document-settings-preview-frame {
      min-height: 190px;
      aspect-ratio: 16 / 9;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .document-settings-preview-frame img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
      padding: 0.75rem;
    }
    .document-settings-preview-frame img.is-unavailable {
      display: none;
    }
    .document-settings-preview-empty {
      color: #94a3b8;
      font-size: 0.9rem;
      text-align: center;
      padding: 0 1rem;
    }
    .document-settings-preview-note {
      color: #667085;
      font-size: 0.86rem;
      margin: 0;
    }
    .document-settings-text-preview {
      border-top: 1px solid #e2e8f0;
      padding-top: 0.8rem;
      text-align: center;
      color: #111827;
    }
    .document-settings-text-preview-name {
      display: block;
      font-size: 0.92rem;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.2;
      border-bottom: 1px solid #1f2937;
      padding: 0 0.4rem 0.25rem;
    }
    .document-settings-text-preview-title {
      display: block;
      margin-top: 0.25rem;
      font-size: 0.82rem;
      font-style: italic;
      line-height: 1.25;
    }
    .document-settings-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    .document-settings-help-list {
      padding-left: 1.1rem;
      margin: 0;
      display: grid;
      gap: 0.45rem;
      color: #475467;
    }
    @media (max-width: 991.98px) {
      .document-settings-layout,
      .document-settings-signatory-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 767.98px) {
      .document-settings-card,
      .document-settings-panel-body,
      .document-settings-panel-head {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      .document-settings-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4 bg-light" id="main-display">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h2 class="document-settings-title"><?= htmlspecialchars($documentSettingsTitle, ENT_QUOTES, 'UTF-8') ?></h2>
          <?php if ($documentSettingsDescription !== ''): ?>
            <p class="document-settings-lead mb-0"><?= htmlspecialchars($documentSettingsDescription, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
      </div>
      <hr class="mt-0 mb-4">

      <?php if (!empty($documentSettingsSuccessMessage)): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars((string)$documentSettingsSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if (!empty($documentSettingsErrorMessage)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string)$documentSettingsErrorMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="bg-white rounded-4 shadow-sm border p-4 document-settings-shell">
        <div class="document-settings-layout">
          <section class="document-settings-panel">
            <div class="document-settings-panel-head">
              <h3 class="h5 mb-2">Signatories</h3>
              <p class="text-muted mb-0">Upload the signature images that should appear on the generated documents for this module. More settings can be added to this page later without changing the route.</p>
            </div>

            <form class="document-settings-panel-body" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($documentSettingsActionUrl, ENT_QUOTES, 'UTF-8') ?>">
              <?= csrfTokenField() ?>
              <input type="hidden" name="action" value="save_document_module_settings">

              <?php if ($documentSettingsShowCopySignatureToggle): ?>
                <section class="document-settings-signatory" id="clearance-general-settings">
                  <div class="document-settings-signatory-top">
                    <div>
                      <h4 class="document-settings-signatory-title">Resident Copy Signature</h4>
                      <p class="document-settings-signatory-subcopy">Choose whether the uploaded signatures should appear on the resident or released copy for this module.</p>
                    </div>
                  </div>
                  <label class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="copy_has_signature" value="1" <?= $documentSettingsCopySignatureEnabled ? 'checked' : '' ?>>
                    <span class="ms-2 fw-semibold">Show signatures on the released copy</span>
                  </label>
                </section>
              <?php endif; ?>

              <?php if ($documentSettingsShowFieldVisibility): ?>
                <section class="document-settings-signatory">
                  <div><h4 class="document-settings-signatory-title">Generated Document Fields</h4><p class="document-settings-signatory-subcopy">Turn each field on or off on generated documents and PDFs.</p></div>
                  <div class="row g-3">
                    <?php foreach (dms_document_field_catalog((string)($documentSettingsModuleConfig['key'] ?? '')) as $fieldKey => $fieldLabel): ?>
                      <div class="col-sm-6"><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="document_field_visible[<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>]" value="1" <?= !empty($documentSettingsFieldVisibility[$fieldKey]) ? 'checked' : '' ?>><span class="ms-2 fw-semibold">Show <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></span></label></div>
                    <?php endforeach; ?>
                  </div>
                </section>
              <?php endif; ?>

              <?php if ($documentSettingsShowPrintHeaderToggle): ?>
                <section class="document-settings-signatory">
                  <div><h4 class="document-settings-signatory-title">Printing Header</h4><p class="document-settings-signatory-subcopy">Turn this off when printing on bond paper that already has the barangay letterhead.</p></div>
                  <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="print_header_enabled" value="1" <?= $documentSettingsPrintHeaderEnabled ? 'checked' : '' ?>><span class="ms-2 fw-semibold">Add barangay header when printed</span></label>
                </section>
              <?php endif; ?>

              <div id="clearance-signatories"></div>
              <?php foreach ((array)$documentSettingsRows as $signatoryRow): ?>
                <?php
                  $signatoryKey = (string)($signatoryRow['signatory_key'] ?? '');
                  $moduleKey = (string)($documentSettingsModuleConfig['key'] ?? '');
                  if ($moduleKey === 'monitoring' && $signatoryKey === 'punong') {
                      continue;
                  }
                  $source = (string)($signatoryRow['source'] ?? 'manual');
                  $isMonitoringHeadEditor = $signatoryKey === 'monitoring_head';
                  $signaturePath = trim((string)($signatoryRow['signature_path'] ?? ''));
                  $signatureUrl = $signaturePath !== ''
                      ? $documentSettingsActionUrl
                          . '?action=view_signature&signatory=' . rawurlencode($signatoryKey)
                          . '&v=' . rawurlencode((string)($signatoryRow['updated_at'] ?? ''))
                      : '';
                ?>
                <section class="document-settings-signatory">
                  <div class="document-settings-signatory-top">
                    <div>
                      <h4 class="document-settings-signatory-title"><?= htmlspecialchars((string)($signatoryRow['label'] ?? $signatoryKey), ENT_QUOTES, 'UTF-8') ?></h4>
                      <?php if (!empty($signatoryRow['signature_help'])): ?>
                        <p class="document-settings-signatory-subcopy"><?= htmlspecialchars((string)$signatoryRow['signature_help'], ENT_QUOTES, 'UTF-8') ?></p>
                      <?php endif; ?>
                      <?php if ($isMonitoringHeadEditor): ?>
                        <p class="document-settings-signatory-subcopy">Edit the name and position exactly as they should appear in clearance previews and generated documents.</p>
                      <?php endif; ?>
                    </div>
                    <span class="document-settings-source-badge <?= $source === 'manual' ? 'is-manual' : 'is-seat' ?>">
                      <i class="fa-solid <?= $source === 'manual' ? 'fa-pen' : 'fa-users-viewfinder' ?>"></i>
                      <?= $source === 'manual' ? 'Manual identity' : 'Current seated official' ?>
                    </span>
                  </div>

                  <div class="document-settings-signatory-grid">
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label fw-semibold" for="signatory_name_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"><?= $isMonitoringHeadEditor ? 'Displayed Name' : 'Signatory Name' ?></label>
                        <input
                          type="text"
                          class="form-control"
                          id="signatory_name_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          name="signatory_name_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          <?= $isMonitoringHeadEditor ? 'data-monitoring-head-name' : '' ?>
                          value="<?= htmlspecialchars((string)($signatoryRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                          <?= $source === 'manual' ? '' : 'readonly' ?>>
                      </div>
                      <div class="col-12">
                        <label class="form-label fw-semibold" for="signatory_title_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"><?= $isMonitoringHeadEditor ? 'Position / Title' : 'Title' ?></label>
                        <input
                          type="text"
                          class="form-control"
                          id="signatory_title_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          name="signatory_title_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          <?= $isMonitoringHeadEditor ? 'data-monitoring-head-title' : '' ?>
                          value="<?= htmlspecialchars((string)($signatoryRow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                          <?= $source === 'manual' ? '' : 'readonly' ?>>
                      </div>
                      <div class="col-12">
                        <label class="form-label fw-semibold" for="signature_file_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>">Signature Image</label>
                        <input
                          type="file"
                          class="form-control"
                          id="signature_file_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          name="signature_file_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>"
                          accept="image/png,image/jpeg">
                        <div class="form-text">Transparent PNG works best, but JPG is also supported.</div>
                      </div>
                      <?php if ($signaturePath !== ''): ?>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="remove_signature_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>" name="remove_signature_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>">
                            <label class="form-check-label" for="remove_signature_<?= htmlspecialchars($signatoryKey, ENT_QUOTES, 'UTF-8') ?>">
                              Remove the current signature image
                            </label>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>

                    <aside class="document-settings-preview">
                      <div class="document-settings-preview-frame">
                        <?php if ($signatureUrl !== ''): ?>
                          <img
                            src="<?= htmlspecialchars($signatureUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string)($signatoryRow['label'] ?? $signatoryKey), ENT_QUOTES, 'UTF-8') ?> signature preview"
                            data-signature-preview-image>
                          <div class="document-settings-preview-empty d-none" data-signature-preview-error>Unable to load the current signature. Upload a replacement and save.</div>
                        <?php else: ?>
                          <div class="document-settings-preview-empty">No signature uploaded yet for this signatory.</div>
                        <?php endif; ?>
                      </div>
                      <p class="document-settings-preview-note">
                        Current file:
                        <strong><?= $signaturePath !== '' ? htmlspecialchars(basename($signaturePath), ENT_QUOTES, 'UTF-8') : 'None' ?></strong>
                      </p>
                      <?php if ($isMonitoringHeadEditor): ?>
                        <div class="document-settings-text-preview" aria-live="polite">
                          <span class="document-settings-text-preview-name" data-monitoring-head-preview-name><?= htmlspecialchars((string)($signatoryRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="document-settings-text-preview-title" data-monitoring-head-preview-title><?= htmlspecialchars((string)($signatoryRow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                      <?php endif; ?>
                    </aside>
                  </div>
                </section>
              <?php endforeach; ?>

              <div class="document-settings-actions">
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($documentSettingsBackUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
                <button type="submit" class="btn btn-primary">
                  <i class="fa-solid fa-floppy-disk me-2"></i>Save Signatory Settings
                </button>
              </div>
            </form>
          </section>

          <aside class="d-grid gap-3">
            <?php if (!empty($documentSettingsFeeRequestsUrl)): ?>
              <section class="document-settings-card">
                <div class="d-flex align-items-start gap-3">
                  <span class="document-settings-source-badge is-manual" aria-hidden="true">
                    <i class="fa-solid fa-tags"></i>
                  </span>
                  <div class="flex-grow-1">
                    <h3 class="h6 mb-2"><?= htmlspecialchars((string)($documentSettingsFeeRequestsTitle ?? 'Fee Change Requests'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-muted small mb-3"><?= htmlspecialchars((string)($documentSettingsFeeRequestsDescription ?? 'Manage fee change requests for this module.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="btn btn-outline-primary w-100" href="<?= htmlspecialchars((string)$documentSettingsFeeRequestsUrl, ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Open Fee Requests
                    </a>
                  </div>
                </div>
              </section>
            <?php endif; ?>

            <section class="document-settings-card">
              <h3 class="h6 mb-3">Module Summary</h3>
              <div class="document-settings-stat mb-3">
                <div class="document-settings-stat-label">Applies To</div>
                <div class="document-settings-stat-value"><?= htmlspecialchars($documentSettingsAppliesTo !== '' ? $documentSettingsAppliesTo : 'Generated documents for this module.', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="document-settings-stat mb-3">
                <div class="document-settings-stat-label">Last Updated</div>
                <div class="document-settings-stat-value"><?= htmlspecialchars($documentSettingsUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="document-settings-stat">
                <div class="document-settings-stat-label">Updated By</div>
                <div class="document-settings-stat-value"><?= htmlspecialchars($documentSettingsUpdatedByLabel, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </section>

            <section class="document-settings-card">
              <h3 class="h6 mb-3">Upload Tips</h3>
              <ul class="document-settings-help-list">
                <li>Use a clean signature image with a plain background for the best result on the document layout.</li>
                <li>When the seated official changes, upload the new signature here so previews and generated files stay aligned.</li>
                <li>Save on this page first before checking a document preview or regenerating a released file.</li>
              </ul>
            </section>

            <section class="document-settings-callout">
              <p class="fw-semibold mb-2">Ready for future settings</p>
              <p class="mb-0">This page currently focuses on signatories only, but the route and storage are already set up so more document-request settings can be added later without moving staff to a new page.</p>
            </section>
          </aside>
        </div>
      </div>
    </main>
  </div>
  <script>
    (() => {
      const nameInput = document.querySelector('[data-monitoring-head-name]');
      const titleInput = document.querySelector('[data-monitoring-head-title]');
      const namePreview = document.querySelector('[data-monitoring-head-preview-name]');
      const titlePreview = document.querySelector('[data-monitoring-head-preview-title]');
      if (nameInput && titleInput && namePreview && titlePreview) {
        const updatePreview = () => {
          namePreview.textContent = nameInput.value.trim() || 'SIGNATORY NAME';
          titlePreview.textContent = titleInput.value.trim() || 'Head, Monitoring & Collection Dept.';
        };
        nameInput.addEventListener('input', updatePreview);
        titleInput.addEventListener('input', updatePreview);
        updatePreview();
      }

      document.querySelectorAll('[data-signature-preview-image]').forEach((image) => {
        image.addEventListener('error', () => {
          image.classList.add('is-unavailable');
          image.parentElement?.querySelector('[data-signature-preview-error]')?.classList.remove('d-none');
        });
      });

      document.querySelectorAll('input[type="file"][name^="signature_file_"]').forEach((input) => {
        input.addEventListener('change', () => {
          const card = input.closest('.document-settings-signatory');
          const image = card?.querySelector('[data-signature-preview-image]');
          const file = input.files?.[0];
          if (!image || !file || !file.type.startsWith('image/')) return;
          image.src = URL.createObjectURL(file);
          image.classList.remove('is-unavailable');
          image.parentElement?.querySelector('[data-signature-preview-error]')?.classList.add('d-none');
        });
      });
    })();
  </script>
</body>
</html>
