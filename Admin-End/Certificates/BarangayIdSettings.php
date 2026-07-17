<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__ . '/../../PhpFiles/General/audit.php';

$documentSettingsModuleKey = 'barangay_id';
$documentSettingsModuleConfig = dms_get_module_config($documentSettingsModuleKey);
$documentSettingsActionUrl = appUrl('Admin-End/Certificates/BarangayIdSettings.php');
$documentSettingsBackUrl = appUrl((string)($documentSettingsModuleConfig['back_href'] ?? 'Admin-End/AdminDashboard.php'));
$barangayIdSettingsSection = strtolower(trim((string)($_GET['section'] ?? '')));
$isBarangayIdTemplateSection = $barangayIdSettingsSection === 'template';
$documentSettingsSuccessMessage = trim((string)($_GET['success'] ?? ''));
$documentSettingsErrorMessage = trim((string)($_GET['error'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_barangay_id_settings') {
    verifyCsrfToken(false);

    try {
        $templateSave = dms_save_barangay_id_template_settings(
            $conn,
            $_POST,
            $_FILES,
            trim((string)($_SESSION['user_id'] ?? ''))
        );
        $signatorySave = dms_save_module_signatories(
            $conn,
            $documentSettingsModuleKey,
            $_POST,
            $_FILES,
            trim((string)($_SESSION['user_id'] ?? ''))
        );

        try {
            insertUnifiedAuditLog(
                $conn,
                trim((string)($_SESSION['user_id'] ?? '')) ?: null,
                trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official',
                'document_settings',
                'barangay_id_template',
                $documentSettingsModuleKey,
                'update_template',
                'template_configuration',
                json_encode([
                    'template' => $templateSave['before'] ?? [],
                    'signatory' => $signatorySave['before'] ?? [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode([
                    'template' => $templateSave['after'] ?? [],
                    'signatory' => $signatorySave['after'] ?? [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Updated Barangay ID template, preview layout, and signature settings.'
            );
        } catch (Throwable $auditError) {
            error_log('Barangay ID settings audit log failed: ' . $auditError->getMessage());
        }

        header('Location: ' . $documentSettingsActionUrl . '?section=template&success=' . rawurlencode('Barangay ID template settings saved.') . '#barangay-id-settings-top');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?section=template&error=' . rawurlencode($e->getMessage()) . '#barangay-id-settings-top');
        exit;
    }
}

$documentSettingsRows = dms_resolve_module_signatories($conn, $documentSettingsModuleKey);
$barangayIdTemplateSettings = dms_resolve_barangay_id_template_settings($conn);
$punongRow = $documentSettingsRows['punong'] ?? [
    'label' => 'Punong Barangay',
    'source' => 'seat_punong',
    'name' => 'HON. GLENN S. EVANGELISTA',
    'title' => 'Punong Barangay',
    'signature_path' => '',
    'signature_help' => 'Shown on the back of the Barangay ID card.',
];

$templateUpdatedAt = trim((string)($barangayIdTemplateSettings['updated_at'] ?? ''));
$templateUpdatedBy = trim((string)($barangayIdTemplateSettings['updated_by_user_id'] ?? ''));
$signatoryMeta = dms_max_updated_meta((array)$documentSettingsRows);
$lastUpdatedAt = $templateUpdatedAt;
$lastUpdatedBy = $templateUpdatedBy;
if ($lastUpdatedAt === '' || (($signatoryMeta['updated_at'] ?? '') !== '' && strcmp((string)$signatoryMeta['updated_at'], $lastUpdatedAt) > 0)) {
    $lastUpdatedAt = trim((string)($signatoryMeta['updated_at'] ?? ''));
    $lastUpdatedBy = trim((string)($signatoryMeta['updated_by_user_id'] ?? ''));
}
$lastUpdatedLabel = 'No Barangay ID settings saved yet.';
if ($lastUpdatedAt !== '') {
    $timestamp = strtotime($lastUpdatedAt);
    $lastUpdatedLabel = $timestamp !== false ? date('M j, Y g:i A', $timestamp) : $lastUpdatedAt;
}
$lastUpdatedBy = $lastUpdatedBy !== '' ? $lastUpdatedBy : 'Not recorded yet';

$appBase = rtrim(appUrl(''), '/');
$frontTemplatePublicPath = (string)($barangayIdTemplateSettings['front_template_path'] ?? dms_barangay_id_default_template_paths()['front']);
$backTemplatePublicPath = (string)($barangayIdTemplateSettings['back_template_path'] ?? dms_barangay_id_default_template_paths()['back']);
$frontTemplateDiskPath = dms_module_asset_public_path_to_disk($frontTemplatePublicPath);
$backTemplateDiskPath = dms_module_asset_public_path_to_disk($backTemplatePublicPath);
$frontTemplateVersion = $frontTemplateDiskPath !== '' && is_file($frontTemplateDiskPath) ? (string)@filemtime($frontTemplateDiskPath) : '';
$backTemplateVersion = $backTemplateDiskPath !== '' && is_file($backTemplateDiskPath) ? (string)@filemtime($backTemplateDiskPath) : '';
$frontTemplateUrl = $appBase . $frontTemplatePublicPath . ($frontTemplateVersion !== '' ? '?v=' . rawurlencode($frontTemplateVersion) : '');
$backTemplateUrl = $appBase . $backTemplatePublicPath . ($backTemplateVersion !== '' ? '?v=' . rawurlencode($backTemplateVersion) : '');

$pagePayload = [
    'appBase' => $appBase,
    'frontTemplateUrl' => $frontTemplateUrl,
    'backTemplateUrl' => $backTemplateUrl,
    'layoutConfig' => $barangayIdTemplateSettings['layout'] ?? dms_barangay_id_default_layout(),
    'sampleData' => $barangayIdTemplateSettings['sample_data'] ?? dms_barangay_id_default_sample_data(),
    'fieldLibrary' => [
        ['type' => 'text', 'label' => 'Text Field'],
        ['type' => 'image', 'label' => 'Image Field'],
        ['type' => 'qr', 'label' => 'QR Field'],
        ['type' => 'image', 'label' => 'Signature', 'source' => 'punongSignatorySignatureUrl'],
        ['type' => 'cover', 'label' => 'Cover Block'],
    ],
    'signatory' => [
        'name' => (string)($punongRow['name'] ?? 'HON. GLENN S. EVANGELISTA'),
        'title' => (string)($punongRow['title'] ?? 'Punong Barangay'),
        'signatureUrl' => trim((string)($punongRow['signature_path'] ?? '')) !== ''
            ? $appBase . trim((string)($punongRow['signature_path'] ?? ''))
            : '',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars(appUrl('Images/favicon_sanjose.png?v=20260211'), ENT_QUOTES, 'UTF-8') ?>">
  <title>Barangay ID Settings</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    :root {
      --bid-accent: #de710c;
      --bid-accent-deep: #9e5000;
      --bid-ink: #1f2937;
      --bid-muted: #667085;
      --bid-border: #eadcca;
      --bid-panel: #ffffff;
      --bid-panel-soft: #fff9f3;
      --bid-bg: #fff5ea;
    }
    body {
      background:
        radial-gradient(circle at top left, rgba(255, 208, 153, 0.32), transparent 32%),
        linear-gradient(180deg, #fff9f2 0%, #f7f2ea 100%);
    }
    .bid-page-title {
      font-family: 'Charis SIL Bold', serif;
      color: var(--bid-accent);
      margin-bottom: 0.4rem;
    }
    .bid-shell {
      border: 1px solid var(--bid-border);
      border-radius: 28px;
      background: rgba(255, 255, 255, 0.94);
      box-shadow: 0 18px 45px rgba(86, 54, 19, 0.08);
      overflow: hidden;
    }
    .bid-section {
      border: 1px solid rgba(140, 102, 64, 0.12);
      border-radius: 22px;
      background: var(--bid-panel);
      box-shadow: 0 8px 24px rgba(37, 25, 12, 0.04);
    }
    .bid-section__head {
      padding: 1.15rem 1.2rem;
      border-bottom: 1px solid rgba(140, 102, 64, 0.1);
      background: linear-gradient(180deg, #fffaf4 0%, #ffffff 100%);
    }
    .bid-section__body {
      padding: 1.2rem;
    }
    .bid-section__title {
      margin: 0;
      font-size: 1rem;
      color: var(--bid-ink);
      font-weight: 800;
    }
    .bid-section__copy {
      margin: 0.35rem 0 0;
      color: var(--bid-muted);
      font-size: 0.92rem;
    }
    .bid-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.58fr) minmax(320px, 0.92fr);
      gap: 1.25rem;
      align-items: start;
    }
    .bid-upload-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
    }
    .bid-upload-card {
      border: 1px solid rgba(140, 102, 64, 0.14);
      border-radius: 18px;
      padding: 1rem;
      background: var(--bid-panel-soft);
      display: grid;
      gap: 0.8rem;
    }
    .bid-upload-preview {
      aspect-ratio: 856 / 541;
      border-radius: 18px;
      overflow: hidden;
      border: 1px solid rgba(140, 102, 64, 0.12);
      background: #f9efe1;
    }
    .bid-upload-preview img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
    }
    .bid-toolbar {
      display: flex;
      justify-content: space-between;
      gap: 0.85rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .bid-segmented {
      display: inline-flex;
      padding: 0.28rem;
      border-radius: 999px;
      background: #fff2df;
      border: 1px solid #f2cf9e;
      gap: 0.28rem;
    }
    .bid-segmented button {
      border: 0;
      background: transparent;
      color: #7a4b00;
      border-radius: 999px;
      padding: 0.52rem 0.95rem;
      font-weight: 800;
      font-size: 0.88rem;
    }
    .bid-segmented button.is-active {
      background: var(--bid-accent);
      color: #fff;
      box-shadow: 0 8px 18px rgba(222, 113, 12, 0.25);
    }
    .bid-add-tools {
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }
    .bid-add-tools button {
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
    }
    .bid-editor-shell {
      display: grid;
      grid-template-columns: minmax(0, 1.55fr) minmax(260px, 0.72fr);
      gap: 1rem;
      align-items: start;
    }
    .bid-editor-canvas-wrap {
      border: 1px solid rgba(140, 102, 64, 0.12);
      border-radius: 22px;
      background: linear-gradient(180deg, #fffdfb 0%, #fff6ee 100%);
      padding: 0.95rem;
    }
    .bid-editor-canvas {
      position: relative;
      width: 100%;
      max-width: 980px;
      margin: 0 auto;
      aspect-ratio: 856 / 541;
      border-radius: 18px;
      overflow: hidden;
      background: #f8ece0 center center / cover no-repeat;
      box-shadow: inset 0 0 0 1px rgba(140, 102, 64, 0.12);
      user-select: none;
      touch-action: none;
    }
    .bid-editor-field {
      position: absolute;
      border: 1px dashed rgba(222, 113, 12, 0.48);
      background: rgba(255, 255, 255, 0.08);
      border-radius: 2px;
      box-shadow: none;
      cursor: move;
      overflow: hidden;
      padding: 0;
      font: inherit;
      text-align: left;
    }
    .bid-editor-field.is-selected {
      border-color: #0f766e;
      background: rgba(240, 253, 250, 0.16);
      box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.2);
    }
    .bid-editor-field.is-media {
      border-style: solid;
      background: rgba(255, 255, 255, 0.18);
    }
    .bid-editor-field__tag {
      position: absolute;
      top: 2px;
      left: 2px;
      right: auto;
      max-width: calc(100% - 22px);
      padding: 0.08rem 0.3rem;
      border-radius: 4px;
      background: rgba(255, 248, 238, 0.96);
      color: #7a4b00;
      border: 1px solid rgba(222, 113, 12, 0.45);
      font-size: var(--bid-editor-label-font-size, 0.62rem);
      font-weight: 800;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      opacity: 0.86;
      transition: opacity 140ms ease;
    }
    .bid-editor-field:not(.is-media) .bid-editor-field__tag {
      inset: 0;
      max-width: none;
      padding: 0 0.55rem 0.18rem;
      border: 0;
      border-radius: 0;
      background: transparent;
      display: flex;
      align-items: flex-end;
      color: #7a4b00;
      text-overflow: ellipsis;
    }
    .bid-editor-field:hover .bid-editor-field__tag,
    .bid-editor-field.is-selected .bid-editor-field__tag {
      opacity: 1;
    }
    .bid-editor-field__sample {
      position: absolute;
      inset: 0;
      padding: 0 2px;
      font-family: Arial, Helvetica, sans-serif;
      font-size: var(--bid-editor-font-size, 10px);
      line-height: var(--bid-editor-line-height, 1.05);
      color: var(--bid-editor-color, #111111);
      font-weight: var(--bid-editor-font-weight, 800);
      font-style: var(--bid-editor-font-style, normal);
      overflow: hidden;
      display: flex;
      align-items: center;
      white-space: nowrap;
      text-overflow: clip;
      text-transform: var(--bid-editor-text-transform, none);
    }
    .bid-editor-field__sample.is-multiline {
      align-items: flex-start;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .bid-editor-signatory {
      position: absolute;
      inset: 0;
      display: grid;
      grid-template-rows: minmax(0, 1fr) auto auto auto;
      justify-items: center;
      color: #111;
      font-family: Arial, Helvetica, sans-serif;
      pointer-events: none;
    }
    .bid-editor-signatory__ink {
      width: 100%;
      min-height: 0;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .bid-editor-signatory__ink img {
      display: block;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .bid-editor-signatory__line {
      width: 100%;
      border-top: 1px solid currentColor;
    }
    .bid-editor-signatory__name,
    .bid-editor-signatory__title {
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: clip;
      text-align: center;
      line-height: 1.05;
    }
    .bid-editor-signatory__name { font-size: 9px; font-weight: 800; text-transform: uppercase; }
    .bid-editor-signatory__title { font-size: 8px; }
    .bid-editor-field__sample.is-center {
      justify-content: center;
      text-align: center;
    }
    .bid-editor-field__sample.is-right {
      justify-content: flex-end;
      text-align: right;
    }
    .bid-editor-field__media {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      display: block;
      object-fit: var(--bid-editor-object-fit, cover);
    }
    .bid-editor-field__media.is-signature {
      object-position: center bottom;
      background: transparent;
    }
    .bid-editor-field__placeholder.is-signature {
      align-content: end;
      background: transparent;
    }
    .bid-editor-field__placeholder {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      text-align: center;
      color: rgba(122, 74, 0, 0.82);
      background: rgba(255, 255, 255, 0.42);
      font: 700 0.72rem/1.1 Arial, Helvetica, sans-serif;
    }
    .bid-editor-field__resize {
      position: absolute;
      right: 0;
      bottom: 0;
      width: 18px;
      height: 18px;
      background: linear-gradient(135deg, transparent 46%, var(--bid-accent) 46%);
      cursor: nwse-resize;
      opacity: 0;
      transition: opacity 140ms ease;
    }
    .bid-editor-field:hover .bid-editor-field__resize,
    .bid-editor-field.is-selected .bid-editor-field__resize {
      opacity: 1;
    }
    .bid-editor-sidebar {
      display: grid;
      gap: 1rem;
    }
    .bid-field-list {
      display: grid;
      gap: 0.55rem;
      max-height: 300px;
      overflow: auto;
    }
    .bid-field-item {
      border: 1px solid rgba(140, 102, 64, 0.12);
      border-radius: 16px;
      padding: 0.72rem 0.78rem;
      background: #fff;
      text-align: left;
      width: 100%;
    }
    .bid-field-item.is-active {
      border-color: rgba(15, 118, 110, 0.5);
      background: #f0fdfa;
    }
    .bid-field-item__meta {
      display: flex;
      justify-content: space-between;
      gap: 0.6rem;
      align-items: center;
      margin-bottom: 0.3rem;
      font-size: 0.82rem;
      color: var(--bid-muted);
    }
    .bid-field-type {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.18rem 0.48rem;
      border-radius: 999px;
      background: #fff2df;
      color: #8a4b00;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.68rem;
      letter-spacing: 0.04em;
    }
    .bid-guide-list {
      display: grid;
      gap: 0.6rem;
    }
    .bid-guide-item {
      border: 1px solid rgba(140, 102, 64, 0.12);
      border-radius: 14px;
      padding: 0.75rem 0.85rem;
      background: #fffdf9;
    }
    .bid-guide-item strong {
      display: block;
      color: var(--bid-ink);
      margin-bottom: 0.15rem;
    }
    .bid-inspector-grid,
    .bid-sample-grid,
    .bid-signature-grid {
      display: grid;
      gap: 0.8rem;
    }
    .bid-inspector-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .bid-sample-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .bid-signature-preview {
      border: 1px dashed rgba(140, 102, 64, 0.22);
      border-radius: 18px;
      min-height: 140px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fffdf9;
      overflow: hidden;
    }
    .bid-signature-preview img {
      max-width: 100%;
      max-height: 100%;
      display: block;
      object-fit: contain;
    }
    .bid-preview-card {
      border: 1px solid rgba(140, 102, 64, 0.12);
      border-radius: 22px;
      background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
      padding: 1rem;
    }
    .bid-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.78rem;
      border-radius: 999px;
      border: 1px solid #f0d2ae;
      background: #fff6ea;
      color: #8a4b00;
      font-weight: 800;
      font-size: 0.8rem;
    }
    .bid-quick-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 0.7rem;
      margin-top: 0.95rem;
    }
    .bid-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    .bid-help {
      margin: 0;
      padding-left: 1.15rem;
      display: grid;
      gap: 0.45rem;
      color: #475467;
    }
    .bid-muted {
      color: var(--bid-muted);
    }
    @media (max-width: 1599.98px) {
      .bid-editor-shell {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 1199.98px) {
      .bid-layout,
      .bid-editor-shell {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 767.98px) {
      .bid-upload-grid,
      .bid-inspector-grid,
      .bid-sample-grid {
        grid-template-columns: 1fr;
      }
      .bid-actions .btn,
      .bid-toolbar .btn {
        width: 100%;
      }
    }
    .bid-landing-shell {
      max-width: var(--admin-table-shell-max-width, 1180px);
      margin: 0 auto;
    }
    .bid-landing-panel {
      border: 1px solid #dee2e6;
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
      padding: 1.5rem;
    }
    .bid-landing-panel__head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      padding-bottom: 1rem;
      margin-bottom: 1rem;
      border-bottom: 1px solid #edf0f3;
    }
    .bid-landing-panel__title {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      color: #212529;
    }
    .bid-landing-panel__copy {
      margin: .35rem 0 0;
      color: #6c757d;
      max-width: 720px;
    }
    .bid-function-list {
      display: grid;
      gap: .75rem;
    }
    .bid-function-item {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr) auto;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid #eceff3;
      border-radius: .75rem;
      color: #212529;
      text-decoration: none;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .bid-function-item:hover,
    .bid-function-item:focus-visible {
      color: #212529;
      border-color: #f1b56d;
      background: #fffaf4;
      box-shadow: 0 .25rem .75rem rgba(222, 113, 12, .08);
    }
    .bid-function-item__icon {
      display: inline-grid;
      width: 2.75rem;
      height: 2.75rem;
      place-items: center;
      border-radius: .75rem;
      color: #de710c;
      background: #fff4e8;
      font-size: 1.1rem;
    }
    .bid-function-item__title {
      margin: 0;
      font-size: 1rem;
      font-weight: 700;
    }
    .bid-function-item__copy {
      margin: .25rem 0 0;
      color: #6c757d;
      line-height: 1.45;
    }
    .bid-function-item__action {
      white-space: nowrap;
    }
    @media (max-width: 767.98px) {
      .bid-landing-panel,
      .bid-function-item {
        padding: 1rem;
      }
      .bid-landing-panel__head,
      .bid-function-item {
        align-items: stretch;
      }
      .bid-function-item {
        grid-template-columns: 1fr;
      }
      .bid-function-item__action {
        width: 100%;
      }
    }
  </style>
</head>
<?php if (!$isBarangayIdTemplateSection): ?>
<body id="barangay-id-settings-top">
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <div class="bid-landing-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 class="bid-page-title">Barangay ID Settings</h2>
            <p class="mb-0 text-muted">Choose a Barangay ID function before opening its settings.</p>
          </div>
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($documentSettingsBackUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to Module
          </a>
        </div>
        <hr class="mt-0 mb-4">

        <section class="bid-landing-panel">
          <div class="bid-landing-panel__head">
            <div>
              <h3 class="bid-landing-panel__title">Available Functions</h3>
              <p class="bid-landing-panel__copy">Open the setup area you want to manage. More Barangay ID settings can be added here later.</p>
            </div>
          </div>

          <div class="bid-function-list">
            <a class="bid-function-item" href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/BarangayIdSettings.php?section=template'), ENT_QUOTES, 'UTF-8') ?>">
              <span class="bid-function-item__icon"><i class="fa-solid fa-object-group"></i></span>
              <span>
                <span class="bid-function-item__title">Change Barangay ID Template</span>
                <span class="bid-function-item__copy d-block">Upload the front and back artwork, arrange fields, preview the card, and prepare the Punong Barangay signature.</span>
              </span>
              <span class="btn btn-warning text-white bid-function-item__action">
                Open Function <i class="fa-solid fa-arrow-right ms-1"></i>
              </span>
            </a>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
<?php exit; endif; ?>
<body id="barangay-id-settings-top">
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5" id="main-display">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h2 class="bid-page-title">Barangay ID Settings</h2>
          <p class="mb-0 text-muted" style="max-width: 860px;">Prepare the Barangay ID front and back template, drag every field into place, preview the finished card, and keep the Punong Barangay signature ready for generated IDs.</p>
          <div class="bid-quick-stats">
            <span class="bid-chip"><i class="fa-solid fa-layer-group"></i><?= count((array)($barangayIdTemplateSettings['layout']['fields'] ?? [])) ?> layout fields</span>
            <span class="bid-chip"><i class="fa-solid fa-clock-rotate-left"></i><?= htmlspecialchars($lastUpdatedLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="bid-chip"><i class="fa-solid fa-user-pen"></i><?= htmlspecialchars($lastUpdatedBy, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($documentSettingsBackUrl, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-arrow-left me-2"></i>Back to Module
        </a>
      </div>

      <?php if ($documentSettingsSuccessMessage !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($documentSettingsSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($documentSettingsErrorMessage !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($documentSettingsErrorMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form id="barangayIdSettingsForm" class="bid-shell p-3 p-md-4 p-xl-4" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($documentSettingsActionUrl, ENT_QUOTES, 'UTF-8') ?>">
        <?= csrfTokenField() ?>
        <input type="hidden" name="action" value="save_barangay_id_settings">
        <input type="hidden" name="barangay_id_layout_json" id="barangayIdLayoutJson" value="<?= htmlspecialchars(dms_json_encode_pretty($barangayIdTemplateSettings['layout'] ?? dms_barangay_id_default_layout()), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="barangay_id_sample_json" id="barangayIdSampleJson" value="<?= htmlspecialchars(dms_json_encode_pretty($barangayIdTemplateSettings['sample_data'] ?? dms_barangay_id_default_sample_data()), ENT_QUOTES, 'UTF-8') ?>">

        <div class="bid-layout">
          <section class="d-grid gap-3">
            <div class="bid-section">
              <div class="bid-section__head">
                <h3 class="bid-section__title">Template Uploads</h3>
                <p class="bid-section__copy">Upload PNG artwork for the front and back of the Barangay ID. The editor below uses the same assets while you position fields.</p>
              </div>
              <div class="bid-section__body">
                <div class="bid-upload-grid">
                  <article class="bid-upload-card">
                    <div>
                      <h4 class="h6 mb-1">Front ID Upload</h4>
                      <p class="bid-muted small mb-0">PNG only. Keep the full card dimensions and final background design here.</p>
                    </div>
                    <div class="bid-upload-preview">
                      <img src="<?= htmlspecialchars($frontTemplateUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Front template preview" id="frontTemplatePreview">
                    </div>
                    <div class="d-grid gap-2">
                      <label class="form-label fw-semibold mb-0" for="frontTemplateFile">Replace front template</label>
                      <input class="form-control" type="file" id="frontTemplateFile" name="front_template_file" accept="image/png">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="removeFrontTemplate" name="remove_front_template">
                        <label class="form-check-label" for="removeFrontTemplate">Remove custom front upload and use the default template</label>
                      </div>
                    </div>
                  </article>

                  <article class="bid-upload-card">
                    <div>
                      <h4 class="h6 mb-1">Back ID Upload</h4>
                      <p class="bid-muted small mb-0">PNG only. This should include the final background for emergency details, signatory space, and QR area.</p>
                    </div>
                    <div class="bid-upload-preview">
                      <img src="<?= htmlspecialchars($backTemplateUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Back template preview" id="backTemplatePreview">
                    </div>
                    <div class="d-grid gap-2">
                      <label class="form-label fw-semibold mb-0" for="backTemplateFile">Replace back template</label>
                      <input class="form-control" type="file" id="backTemplateFile" name="back_template_file" accept="image/png">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="removeBackTemplate" name="remove_back_template">
                        <label class="form-check-label" for="removeBackTemplate">Remove custom back upload and use the default template</label>
                      </div>
                    </div>
                  </article>
                </div>
              </div>
            </div>

            <div class="bid-section">
              <div class="bid-section__head">
                <div class="bid-toolbar">
                  <div>
                    <h3 class="bid-section__title">Drag and Drop Field Layout</h3>
                    <p class="bid-section__copy">Move every text, image, QR, and signature directly on top of the selected card side. Resize from the corner handle and fine-tune values in the inspector.</p>
                  </div>
                  <div class="bid-segmented" role="tablist" aria-label="Template side selector">
                    <button type="button" class="is-active" data-bid-side-btn="front">Front Editor</button>
                    <button type="button" data-bid-side-btn="back">Back Editor / Signature</button>
                  </div>
                </div>
              </div>
              <div class="bid-section__body d-grid gap-3">
                <div class="bid-toolbar">
                  <div class="bid-add-tools">
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bid-add-type="text"><i class="fa-solid fa-font me-1"></i>Add Text</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bid-add-type="image"><i class="fa-solid fa-image me-1"></i>Add Image</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bid-add-type="qr"><i class="fa-solid fa-qrcode me-1"></i>Add QR</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bid-add-type="signature"><i class="fa-solid fa-signature me-1"></i>Add Signature</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bid-add-type="cover"><i class="fa-solid fa-vector-square me-1"></i>Add Cover</button>
                  </div>
                  <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-light border" id="restoreBarangayIdDefaults"><i class="fa-solid fa-rotate-left me-1"></i>Restore Default Layout</button>
                  </div>
                </div>
                <p class="bid-muted small mb-0">Signature placement is edited on the Back Editor. The Signature field contains only the uploaded signature image.</p>

                <div class="bid-editor-shell">
                  <div class="bid-editor-canvas-wrap">
                    <div class="bid-editor-canvas" id="barangayIdEditorCanvas" aria-label="Barangay ID template editor"></div>
                  </div>
                  <div class="bid-editor-sidebar">
                    <div class="bid-preview-card">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                          <h4 class="h6 mb-1">Fields on Active Side</h4>
                          <p class="small text-muted mb-0">Each item below is the real field name. Select one to edit its position, size, and content.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="deleteSelectedField"><i class="fa-solid fa-trash me-1"></i>Delete</button>
                      </div>
                      <div class="bid-field-list" id="barangayIdFieldList"></div>
                    </div>

                    <div class="bid-preview-card">
                      <div class="mb-3">
                        <h4 class="h6 mb-1">Field Name Guide</h4>
                        <p class="small text-muted mb-0">The template already contains the printed labels, so the editor only keeps the real data fields you need to position.</p>
                      </div>
                      <div class="bid-guide-list">
                        <div class="bid-guide-item">
                          <strong>Value Field</strong>
                          The actual resident data shown on the ID like `Resident Name`, `Birthdate`, `Address`, or `Card Number`.
                        </div>
                        <div class="bid-guide-item">
                          <strong>Resident Photo / QR Code / Signatory</strong>
                          Image-based fields for the photo, verification QR, and Punong Barangay signature block.
                        </div>
                      </div>
                    </div>

                    <div class="bid-preview-card">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                          <h4 class="h6 mb-1">Field Inspector</h4>
                          <p class="small text-muted mb-0">Fine adjustments here stay in sync with the drag editor.</p>
                        </div>
                      </div>
                      <div class="bid-inspector-grid" id="barangayIdFieldInspector"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="bid-section">
              <div class="bid-section__head">
                <h3 class="bid-section__title">Sample Preview</h3>
                <p class="bid-section__copy">Use sample values to test the layout. The preview below uses the current templates, auto-font fitting, and signatory state.</p>
              </div>
              <div class="bid-section__body">
                <div class="bid-sample-grid mb-3">
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Name</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardFullName" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardFullName'] ?? '') ?: dms_barangay_id_default_sample_data()['cardFullName']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Address</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardFullAddress" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardFullAddress'] ?? '') ?: dms_barangay_id_default_sample_data()['cardFullAddress']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Birthdate</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardBirthdate" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardBirthdate'] ?? '') ?: dms_barangay_id_default_sample_data()['cardBirthdate']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Birthplace</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardBirthplace" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardBirthplace'] ?? '') ?: dms_barangay_id_default_sample_data()['cardBirthplace']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Sex</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardSex" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardSex'] ?? '') ?: dms_barangay_id_default_sample_data()['cardSex']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Card Number</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardNumber" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardNumber'] ?? '') ?: dms_barangay_id_default_sample_data()['cardNumber']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Valid Until</span>
                    <input type="text" class="form-control" data-bid-sample-input="validUntil" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['validUntil'] ?? '') ?: dms_barangay_id_default_sample_data()['validUntil']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Emergency Name</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardEmergencyName" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardEmergencyName'] ?? '') ?: dms_barangay_id_default_sample_data()['cardEmergencyName']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Emergency Address</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardEmergencyAddress" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardEmergencyAddress'] ?? '') ?: dms_barangay_id_default_sample_data()['cardEmergencyAddress']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Emergency Contact</span>
                    <input type="text" class="form-control" data-bid-sample-input="cardEmergencyContact" value="<?= htmlspecialchars((string)(($barangayIdTemplateSettings['sample_data']['cardEmergencyContact'] ?? '') ?: dms_barangay_id_default_sample_data()['cardEmergencyContact']), ENT_QUOTES, 'UTF-8') ?>">
                  </label>
                </div>
                <div id="barangayIdSamplePreview"></div>
              </div>
            </div>
          </section>

          <aside class="d-grid gap-3">
            <div class="bid-section">
              <div class="bid-section__head">
                <h3 class="bid-section__title">Punong Barangay Signature</h3>
                <p class="bid-section__copy">The Barangay Secretary does not sign the Barangay ID. Only the Punong Barangay signature is prepared here.</p>
              </div>
              <div class="bid-section__body d-grid gap-3">
                <div class="bid-signature-grid">
                  <div>
                    <div class="small text-uppercase fw-bold text-muted mb-1">Signatory Source</div>
                    <span class="bid-chip"><i class="fa-solid fa-chair"></i>Current Seat Assignment</span>
                  </div>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Signatory Name</span>
                    <input type="text" class="form-control" value="<?= htmlspecialchars((string)($punongRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </label>
                  <label class="form-label mb-0">
                    <span class="fw-semibold d-block mb-1">Signatory Title</span>
                    <input type="text" class="form-control" value="<?= htmlspecialchars((string)($punongRow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </label>
                  <div>
                    <label class="form-label fw-semibold" for="signatureFilePunong">Upload Signature</label>
                    <input class="form-control" type="file" id="signatureFilePunong" name="signature_file_punong" accept="image/png">
                    <div class="form-text"><?= htmlspecialchars((string)($punongRow['signature_help'] ?? 'Shown on the back of the Barangay ID card.'), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="removeSignaturePunong" name="remove_signature_punong">
                    <label class="form-check-label" for="removeSignaturePunong">Remove the saved signature image</label>
                  </div>
                  <div class="bid-signature-preview" id="punongSignaturePreview">
                    <?php if (!empty($punongRow['signature_path'])): ?>
                      <img src="<?= htmlspecialchars($appBase . (string)$punongRow['signature_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Punong Barangay signature preview">
                    <?php else: ?>
                      <div class="text-center text-muted px-3">No signature uploaded yet.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="bid-section">
              <div class="bid-section__head">
                <h3 class="bid-section__title">Editor Notes</h3>
                <p class="bid-section__copy">This page is intentionally focused on the template system first so more Barangay ID settings can be added later without changing the route.</p>
              </div>
              <div class="bid-section__body">
                <ul class="bid-help">
                  <li>Drag inside a field box to move it. Use the corner handle to resize.</li>
                  <li>Text fields use auto-font fitting in the live preview so long names and addresses stay inside the card.</li>
                  <li>The saved layout is reused by the generated Barangay ID output, not just the admin settings preview.</li>
                  <li>Use cover blocks only if you need to hide something already printed on the uploaded template.</li>
                </ul>
              </div>
            </div>
          </aside>
        </div>

        <div class="bid-actions mt-4">
          <a class="btn btn-light border" href="<?= htmlspecialchars($documentSettingsBackUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
          <button type="submit" class="btn btn-warning text-white px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Barangay ID Settings</button>
        </div>
      </form>
    </main>
  </div>

  <script id="barangayIdSettingsPayload" type="application/json"><?= htmlspecialchars(json_encode($pagePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_NOQUOTES, 'UTF-8') ?></script>
  <script src="<?= htmlspecialchars(appUrl('JS-Script-Files/Shared/barangayIdDigital.js?v=20260718-26'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(appUrl('JS-Script-Files/Admin-End/barangayIdSettingsEditor.js?v=20260718-11'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
