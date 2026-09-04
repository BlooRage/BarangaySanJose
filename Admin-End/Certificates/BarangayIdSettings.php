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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_barangay_id_operations') {
    verifyCsrfToken(false);
    try {
        $operationsSave = dms_save_barangay_id_operational_settings($conn, $_POST, trim((string)($_SESSION['user_id'] ?? '')));
        try {
            insertUnifiedAuditLog($conn, trim((string)($_SESSION['user_id'] ?? '')) ?: null, trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official', 'document_settings', 'barangay_id_operations', 'barangay_id', 'update_settings', 'configuration', json_encode($operationsSave['before']), json_encode($operationsSave['after']), 'Updated online applications, Digital ID access, capture protection, print/digital signatures, replacement handling, and default validity settings.');
        } catch (Throwable $auditError) {
            error_log('Barangay ID operational settings audit log failed: ' . $auditError->getMessage());
        }
        header('Location: ' . $documentSettingsActionUrl . '?success=' . rawurlencode('Barangay ID settings saved.'));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?error=' . rawurlencode($e->getMessage()));
        exit;
    }
}

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
$barangayIdOperationalSettings = dms_resolve_barangay_id_operational_settings($conn);
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
      overflow: hidden;
      background-clip: padding-box;
    }
    .bid-section__head {
      padding: 1.15rem 1.2rem;
      border-bottom: 1px solid rgba(140, 102, 64, 0.1);
      background: linear-gradient(180deg, #fffaf4 0%, #ffffff 100%);
      border-radius: 21px 21px 0 0;
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
      grid-template-columns: 1fr;
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
    .bid-upload-file-wrap[hidden] { display: none !important; }
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
      grid-template-columns: 1fr;
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
      overflow: visible;
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
      z-index: 1;
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
      pointer-events: none;
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
      overflow: hidden;
      border-radius: inherit;
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
    .bid-editor-field__delete {
      position: absolute;
      top: -12px;
      right: -12px;
      z-index: 20;
      display: grid;
      place-items: center;
      width: 22px;
      height: 22px;
      border: 1px solid #fff;
      border-radius: 50%;
      background: #dc3545;
      color: #fff;
      font: 800 15px/1 Arial, sans-serif;
      box-shadow: 0 3px 9px rgba(88, 20, 28, 0.3);
      opacity: 0;
      pointer-events: none;
    }
    .bid-editor-field:hover .bid-editor-field__delete,
    .bid-editor-field.is-selected .bid-editor-field__delete {
      opacity: 1;
      pointer-events: auto;
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
    .bid-process {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }
    .bid-process__step {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border: 1px solid #eadcca;
      border-radius: 16px;
      padding: 0.85rem 1rem;
      background: #fffaf4;
      color: #725238;
      font-weight: 800;
      text-align: left;
    }
    .bid-process__step-number {
      display: grid;
      place-items: center;
      width: 2rem;
      height: 2rem;
      flex: 0 0 2rem;
      border-radius: 50%;
      background: #f5e4cf;
      color: #8a4b00;
    }
    .bid-process__step.is-active {
      border-color: var(--bid-accent);
      background: #fff2df;
      color: var(--bid-accent-deep);
      box-shadow: 0 8px 20px rgba(222, 113, 12, 0.12);
    }
    .bid-process__step.is-active .bid-process__step-number,
    .bid-process__step.is-complete .bid-process__step-number {
      background: var(--bid-accent);
      color: #fff;
    }
    [data-bid-step-panel] { display: none; }
    #barangayIdSettingsForm[data-bid-active-step="1"] [data-bid-step-panel="1"],
    #barangayIdSettingsForm[data-bid-active-step="2"] [data-bid-step-panel="2"],
    #barangayIdSettingsForm[data-bid-active-step="3"] [data-bid-step-panel="3"] { display: block; }
    #barangayIdSettingsForm[data-bid-active-step="2"] .bid-layout,
    #barangayIdSettingsForm[data-bid-active-step="3"] .bid-layout { grid-template-columns: 1fr; }
    #barangayIdSettingsForm[data-bid-active-step="2"] .bid-layout > aside,
    #barangayIdSettingsForm[data-bid-active-step="3"] .bid-layout > aside { display: none !important; }
    .bid-view-switch {
      display: inline-flex;
      gap: 0.4rem;
      flex-wrap: wrap;
    }
    .bid-editor-controls {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      flex-wrap: wrap;
    }
    .bid-editor-control {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      min-height: 40px;
      padding: 0.35rem 0.65rem;
      border: 1px solid #eadcca;
      border-radius: 12px;
      background: #fff;
      color: #7a4b00;
      font-weight: 700;
    }
    .bid-editor-control:has(input:disabled),
    .bid-editor-control.is-disabled { opacity: 0.45; }
    .bid-editor-control input[type="color"] {
      width: 34px;
      height: 28px;
      padding: 2px;
      border: 0;
      background: transparent;
    }
    .bid-editor-control input[type="number"] { width: 64px; }
    .bid-align-tools { display: inline-flex; gap: 0.2rem; }
    .bid-align-tools button {
      width: 36px;
      height: 34px;
      border: 0;
      border-radius: 8px;
      background: transparent;
      color: #7a4b00;
    }
    .bid-align-tools button.is-active {
      background: var(--bid-accent);
      color: #fff;
    }
    [data-bid-editor-view-panel][hidden] { display: none !important; }
    #barangayIdSideSelector[hidden] { display: none !important; }
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
      .bid-process { grid-template-columns: 1fr; }
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
    .bid-btn-orange {
      --bs-btn-color: #fff;
      --bs-btn-bg: var(--bid-accent);
      --bs-btn-border-color: var(--bid-accent);
      --bs-btn-hover-color: #fff;
      --bs-btn-hover-bg: var(--bid-accent-deep);
      --bs-btn-hover-border-color: var(--bid-accent-deep);
      --bs-btn-focus-shadow-rgb: 222, 113, 12;
      --bs-btn-active-color: #fff;
      --bs-btn-active-bg: #874500;
      --bs-btn-active-border-color: #874500;
    }
    .bid-btn-outline-orange {
      --bs-btn-color: var(--bid-accent);
      --bs-btn-border-color: var(--bid-accent);
      --bs-btn-hover-color: #fff;
      --bs-btn-hover-bg: var(--bid-accent);
      --bs-btn-hover-border-color: var(--bid-accent);
      --bs-btn-focus-shadow-rgb: 222, 113, 12;
      --bs-btn-active-color: #fff;
      --bs-btn-active-bg: var(--bid-accent-deep);
      --bs-btn-active-border-color: var(--bid-accent-deep);
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
            <p class="mb-0 text-muted">Control Digital ID behavior, issuance defaults, and the card design.</p>
          </div>
        </div>
        <hr class="mt-0 mb-4">

        <?php if ($documentSettingsSuccessMessage !== ''): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($documentSettingsSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($documentSettingsErrorMessage !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($documentSettingsErrorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <form class="bid-landing-panel mb-4" method="post" action="<?= htmlspecialchars($documentSettingsActionUrl, ENT_QUOTES, 'UTF-8') ?>">
          <?= csrfTokenField() ?>
          <input type="hidden" name="action" value="save_barangay_id_operations">
          <div class="bid-landing-panel__head">
            <div><h3 class="bid-landing-panel__title">Digital ID Controls</h3><p class="bid-landing-panel__copy">Set the system defaults used when Barangay IDs are approved. Validity can still be changed during pre-approval.</p></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-file-signature"></i></span><span><label class="bid-function-item__title d-block" for="onlineApplicationEnabled">Online Applications</label><span class="bid-function-item__copy d-block">Allow residents to submit new, renewal, and replacement Barangay ID requests online.</span></span><div class="form-check form-switch"><input type="hidden" name="online_application_enabled" value="0"><input class="form-check-input" type="checkbox" role="switch" id="onlineApplicationEnabled" name="online_application_enabled" value="1" <?= $barangayIdOperationalSettings['online_application_enabled'] ? 'checked' : '' ?>></div></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-mobile-screen"></i></span><span><label class="bid-function-item__title d-block" for="digitalIdEnabled">Digital ID</label><span class="bid-function-item__copy d-block">Allow residents to access an issued digital card.</span></span><div class="form-check form-switch"><input type="hidden" name="digital_id_enabled" value="0"><input class="form-check-input" type="checkbox" role="switch" id="digitalIdEnabled" name="digital_id_enabled" value="1" <?= $barangayIdOperationalSettings['digital_id_enabled'] ? 'checked' : '' ?>></div></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-signature"></i></span><span><label class="bid-function-item__title d-block" for="digitalIdSignature">Digital ID Signature</label><span class="bid-function-item__copy d-block">Show the official signature on the resident's Digital ID.</span></span><div class="form-check form-switch"><input type="hidden" name="digital_id_has_signature" value="0"><input class="form-check-input" type="checkbox" role="switch" id="digitalIdSignature" name="digital_id_has_signature" value="1" <?= $barangayIdOperationalSettings['digital_id_has_signature'] ? 'checked' : '' ?>></div></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-calendar-check"></i></span><span class="flex-grow-1"><label class="bid-function-item__title d-block mb-2" for="defaultValidityMonths">Default Validity</label><select class="form-select" id="defaultValidityMonths" name="default_validity_months"><?php foreach ([3 => '3 months', 6 => '6 months', 12 => '1 year', 24 => '2 years', 36 => '3 years', 48 => '4 years', 60 => '5 years'] as $months => $label): ?><option value="<?= $months ?>" <?= (int)$barangayIdOperationalSettings['default_validity_months'] === $months ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><span class="bid-function-item__copy d-block">Preselected only; officers may override it.</span></span></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-print"></i></span><span><label class="bid-function-item__title d-block" for="printedIdSignature">Resident Printed Copy Signature</label><span class="bid-function-item__copy d-block">Show the official signature on the resident's printed Barangay ID copy.</span></span><div class="form-check form-switch"><input type="hidden" name="printed_id_has_signature" value="0"><input class="form-check-input" type="checkbox" role="switch" id="printedIdSignature" name="printed_id_has_signature" value="1" <?= $barangayIdOperationalSettings['printed_id_has_signature'] ? 'checked' : '' ?>></div></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-shield-halved"></i></span><span><label class="bid-function-item__title d-block" for="digitalIdCaptureDisabled">Disable Download / Capture</label><span class="bid-function-item__copy d-block">Blocks printing, right-click, dragging, and common capture shortcuts. OS screenshots cannot be fully prevented.</span></span><div class="form-check form-switch"><input type="hidden" name="digital_id_capture_disabled" value="0"><input class="form-check-input" type="checkbox" role="switch" id="digitalIdCaptureDisabled" name="digital_id_capture_disabled" value="1" <?= $barangayIdOperationalSettings['digital_id_capture_disabled'] ? 'checked' : '' ?>></div></div></div>
            <div class="col-lg-4"><div class="bid-function-item h-100"><span class="bid-function-item__icon"><i class="fa-solid fa-arrows-rotate"></i></span><span><label class="bid-function-item__title d-block" for="deactivatePreviousDigitalId">Deactivate Previous ID</label><span class="bid-function-item__copy d-block">After replacement, only the newest completed Digital ID remains active.</span></span><div class="form-check form-switch"><input type="hidden" name="deactivate_previous_digital_id" value="0"><input class="form-check-input" type="checkbox" role="switch" id="deactivatePreviousDigitalId" name="deactivate_previous_digital_id" value="1" <?= $barangayIdOperationalSettings['deactivate_previous_digital_id'] ? 'checked' : '' ?>></div></div></div>
          </div>
          <div class="d-flex justify-content-end mt-3"><button class="btn bid-btn-orange px-4" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save Settings</button></div>
        </form>

        <section class="bid-landing-panel">
          <div class="bid-landing-panel__head">
            <div>
              <h3 class="bid-landing-panel__title">ID Design</h3>
              <p class="bid-landing-panel__copy">Manage the visual template separately from day-to-day issuance defaults.</p>
            </div>
          </div>

          <div class="bid-function-list">
            <a class="bid-function-item" href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/BarangayIdSettings.php?section=template'), ENT_QUOTES, 'UTF-8') ?>">
              <span class="bid-function-item__icon"><i class="fa-solid fa-object-group"></i></span>
              <span>
                <span class="bid-function-item__title">Change ID</span>
                <span class="bid-function-item__copy d-block">Upload the front and back artwork, arrange fields, preview the card, and prepare the Punong Barangay signature.</span>
              </span>
              <span class="btn bid-btn-orange bid-function-item__action">
                Change ID <i class="fa-solid fa-arrow-right ms-1"></i>
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
        </div>
      </div>

      <?php if ($documentSettingsSuccessMessage !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($documentSettingsSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($documentSettingsErrorMessage !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($documentSettingsErrorMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form id="barangayIdSettingsForm" class="bid-shell p-3 p-md-4 p-xl-4" data-bid-active-step="1" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($documentSettingsActionUrl, ENT_QUOTES, 'UTF-8') ?>">
        <?= csrfTokenField() ?>
        <input type="hidden" name="action" value="save_barangay_id_settings">
        <input type="hidden" name="barangay_id_layout_json" id="barangayIdLayoutJson" value="<?= htmlspecialchars(dms_json_encode_pretty($barangayIdTemplateSettings['layout'] ?? dms_barangay_id_default_layout()), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="barangay_id_sample_json" id="barangayIdSampleJson" value="<?= htmlspecialchars(dms_json_encode_pretty($barangayIdTemplateSettings['sample_data'] ?? dms_barangay_id_default_sample_data()), ENT_QUOTES, 'UTF-8') ?>">

        <nav class="bid-process" aria-label="Barangay ID template setup steps">
          <button type="button" class="bid-process__step is-active" data-bid-step="1">
            <span class="bid-process__step-number">1</span><span>Upload Images</span>
          </button>
          <button type="button" class="bid-process__step" data-bid-step="2">
            <span class="bid-process__step-number">2</span><span>Layout Editor</span>
          </button>
          <button type="button" class="bid-process__step" data-bid-step="3">
            <span class="bid-process__step-number">3</span><span>Final ID Preview</span>
          </button>
        </nav>

        <div class="bid-layout">
          <section class="d-grid gap-3">
            <div class="bid-section" data-bid-step-panel="1">
              <div class="bid-section__head">
                <h3 class="bid-section__title">Upload Front and Back Images</h3>
              </div>
              <div class="bid-section__body">
                <div class="bid-upload-grid">
                  <article class="bid-upload-card">
                    <div>
                      <h4 class="h6 mb-1">Front ID Upload</h4>
                    </div>
                    <div class="bid-upload-preview">
                      <img src="<?= htmlspecialchars($frontTemplateUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Front template preview" id="frontTemplatePreview">
                    </div>
                    <div class="d-grid gap-2">
                      <label class="form-label fw-semibold mb-0" for="frontTemplateAction">Front template action</label>
                      <select class="form-select" id="frontTemplateAction" data-bid-upload-action="front">
                        <option value="keep">Keep current template</option>
                        <option value="upload">Upload new template</option>
                        <option value="default">Use default template</option>
                      </select>
                      <div class="bid-upload-file-wrap" data-bid-upload-file-wrap="front" hidden>
                        <label class="form-label fw-semibold mb-1" for="frontTemplateFile">Choose front PNG</label>
                        <input class="form-control" type="file" id="frontTemplateFile" name="front_template_file" accept="image/png">
                      </div>
                      <input type="checkbox" value="1" id="removeFrontTemplate" name="remove_front_template" hidden>
                    </div>
                  </article>

                  <article class="bid-upload-card">
                    <div>
                      <h4 class="h6 mb-1">Back ID Upload</h4>
                    </div>
                    <div class="bid-upload-preview">
                      <img src="<?= htmlspecialchars($backTemplateUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Back template preview" id="backTemplatePreview">
                    </div>
                    <div class="d-grid gap-2">
                      <label class="form-label fw-semibold mb-0" for="backTemplateAction">Back template action</label>
                      <select class="form-select" id="backTemplateAction" data-bid-upload-action="back">
                        <option value="keep">Keep current template</option>
                        <option value="upload">Upload new template</option>
                        <option value="default">Use default template</option>
                      </select>
                      <div class="bid-upload-file-wrap" data-bid-upload-file-wrap="back" hidden>
                        <label class="form-label fw-semibold mb-1" for="backTemplateFile">Choose back PNG</label>
                        <input class="form-control" type="file" id="backTemplateFile" name="back_template_file" accept="image/png">
                      </div>
                      <input type="checkbox" value="1" id="removeBackTemplate" name="remove_back_template" hidden>
                    </div>
                  </article>
                </div>
              </div>
            </div>

            <div class="bid-section" data-bid-step-panel="2">
              <div class="bid-section__head">
                <div class="bid-toolbar">
                  <div>
                    <h3 class="bid-section__title">Layout Editor</h3>
                  </div>
                  <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <div class="bid-segmented" role="tablist" aria-label="Editor view selector">
                      <button type="button" class="is-active" data-bid-editor-view-btn="editor"><i class="fa-solid fa-pen-ruler me-1"></i>Editor View</button>
                      <button type="button" data-bid-editor-view-btn="layout"><i class="fa-solid fa-eye me-1"></i>Layout View</button>
                    </div>
                    <div class="bid-segmented" id="barangayIdSideSelector" role="tablist" aria-label="Template side selector">
                      <button type="button" class="is-active" data-bid-side-btn="front">Front Editor</button>
                      <button type="button" data-bid-side-btn="back">Back Editor / Signature</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="bid-section__body d-grid gap-3">
                <div class="d-grid gap-3" data-bid-editor-view-panel="editor">
                <div class="bid-editor-controls" aria-label="Selected field controls">
                  <label class="bid-editor-control" for="barangayIdAddFieldSelect">
                    <i class="fa-solid fa-plus"></i>
                    <select class="form-select form-select-sm border-0" id="barangayIdAddFieldSelect">
                      <option value="">Add field</option>
                      <optgroup label="Resident and ID fields">
                        <option value="source:cardFullName">Full Name</option>
                        <option value="source:cardFullAddress">Full Address</option>
                        <option value="source:cardBirthdate">Birthdate</option>
                        <option value="source:cardBirthplace">Birthplace</option>
                        <option value="source:cardSex">Sex</option>
                        <option value="source:cardContactNumber">Contact Number</option>
                        <option value="source:cardNumber">Card Number</option>
                        <option value="source:validUntil">Valid Until</option>
                        <option value="source:photoUrl">Resident Photo</option>
                        <option value="source:qrUrl">Verification QR</option>
                        <option value="source:punongSignatorySignatureUrl">Official Signature</option>
                      </optgroup>
                      <optgroup label="Emergency fields">
                        <option value="source:cardEmergencyName">Emergency Contact Name</option>
                        <option value="source:cardEmergencyAddress">Emergency Address</option>
                        <option value="source:cardEmergencyContact">Emergency Contact Number</option>
                      </optgroup>
                    </select>
                  </label>

                  <label class="bid-editor-control" for="barangayIdQuickColor" title="Text color">
                    <i class="fa-solid fa-palette"></i>
                    <span>Text Color</span>
                    <input type="color" id="barangayIdQuickColor" value="#111111">
                  </label>

                  <label class="bid-editor-control" for="barangayIdQuickFontStyle" title="Font style and weight">
                    <i class="fa-solid fa-font"></i>
                    <select class="form-select form-select-sm border-0" id="barangayIdQuickFontStyle" aria-label="Font style and weight">
                      <option value="">Regular</option>
                      <option value="I">Italic</option>
                      <option value="B">Bold</option>
                      <option value="BI">Bold + Italic</option>
                    </select>
                  </label>

                  <div class="bid-editor-control" id="barangayIdQuickAlignment" title="Text alignment">
                    <i class="fa-solid fa-align-left"></i>
                    <div class="bid-align-tools">
                      <button type="button" data-bid-quick-align="left" aria-label="Align left"><i class="fa-solid fa-align-left"></i></button>
                      <button type="button" data-bid-quick-align="center" aria-label="Align center"><i class="fa-solid fa-align-center"></i></button>
                      <button type="button" data-bid-quick-align="right" aria-label="Align right"><i class="fa-solid fa-align-right"></i></button>
                    </div>
                  </div>

                  <label class="bid-editor-control" for="barangayIdQuickUppercase" title="Uppercase text">
                    <i class="fa-solid fa-a"></i>
                    <span>Upper Case</span>
                    <input class="form-check-input mt-0" type="checkbox" id="barangayIdQuickUppercase">
                  </label>

                  <label class="bid-editor-control" for="barangayIdQuickMaxLines" title="Multiline text">
                    <i class="fa-solid fa-align-justify"></i>
                    <span>Multiline</span>
                    <input class="form-check-input mt-0" type="checkbox" id="barangayIdQuickMultiline">
                    <input class="form-control form-control-sm" type="number" id="barangayIdQuickMaxLines" min="1" max="3" step="1" value="1">
                  </label>

                  <label class="bid-editor-control" for="barangayIdQuickCornerRadius" id="barangayIdQuickCornerRadiusWrap" title="Image corner rounding" hidden>
                    <i class="fa-solid fa-square"></i>
                    <span>Corner Rounding</span>
                    <input class="form-control form-control-sm" type="number" id="barangayIdQuickCornerRadius" min="0" max="50" step="1" value="0">
                    <span>%</span>
                  </label>
                </div>

                <div class="bid-editor-shell">
                  <div class="bid-editor-canvas-wrap">
                    <div class="bid-editor-canvas" id="barangayIdEditorCanvas" aria-label="Barangay ID template editor"></div>
                  </div>
                  <div class="bid-editor-sidebar" hidden>
                    <div class="bid-preview-card">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                          <h4 class="h6 mb-1">Fields on Active Side</h4>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="deleteSelectedField"><i class="fa-solid fa-trash me-1"></i>Delete</button>
                      </div>
                      <div class="bid-field-list" id="barangayIdFieldList"></div>
                    </div>

                    <div class="bid-preview-card">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                          <h4 class="h6 mb-1">Field Inspector</h4>
                        </div>
                      </div>
                      <div class="bid-inspector-grid" id="barangayIdFieldInspector"></div>
                    </div>
                  </div>
                </div>
                </div>

              </div>
            </div>

            <div class="bid-section" data-bid-step-panel="3">
              <div class="bid-section__head">
                <div class="bid-toolbar">
                  <h3 class="bid-section__title">Final ID Preview</h3>
                  <button type="button" class="btn bid-btn-outline-orange" data-bid-go-step="2"><i class="fa-solid fa-pen-ruler me-1"></i>Back to Editor</button>
                </div>
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

        </div>

        <div class="bid-actions mt-4">
          <a class="btn btn-light border" href="<?= htmlspecialchars($documentSettingsBackUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
          <button type="button" class="btn btn-outline-secondary" data-bid-step-prev hidden><i class="fa-solid fa-arrow-left me-2"></i>Previous</button>
          <button type="button" class="btn bid-btn-orange px-4" data-bid-step-next>Continue <i class="fa-solid fa-arrow-right ms-2"></i></button>
          <button type="submit" class="btn btn-success px-4" data-bid-step-save hidden><i class="fa-solid fa-floppy-disk me-2"></i>Save Barangay ID Settings</button>
        </div>
      </form>
    </main>
  </div>

  <script id="barangayIdSettingsPayload" type="application/json"><?= htmlspecialchars(json_encode($pagePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_NOQUOTES, 'UTF-8') ?></script>
  <script src="<?= htmlspecialchars(appUrl('JS-Script-Files/Shared/barangayIdDigital.js?v=20260812-signature-transparent-34'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(appUrl('JS-Script-Files/Admin-End/barangayIdSettingsEditor.js?v=20260718-27'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
