<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__ . '/../PhpFiles/General/audit.php';

$documentSettingsModuleKey = 'monitoring';
$documentSettingsModuleConfig = dms_get_module_config($documentSettingsModuleKey);
$documentSettingsActionUrl = appUrl('Admin-End/ClearanceDocumentSettings.php');
$documentSettingsBackUrl = appUrl('Admin-End/BusinessMonitoringSettings.php');
$documentSettingsSuccessMessage = trim((string)($_GET['success'] ?? ''));
$documentSettingsErrorMessage = trim((string)($_GET['error'] ?? ''));
$documentSettingsRows = dms_resolve_module_signatories($conn, $documentSettingsModuleKey);
$documentSettingsCopySignatureEnabled = dms_resolve_module_copy_signature_setting($conn, $documentSettingsModuleKey);
$documentSettingsShowCopySignatureToggle = false;
$documentSettingsFieldVisibility = dms_resolve_document_field_visibility($conn, $documentSettingsModuleKey);
$documentSettingsShowFieldVisibility = false;
$documentSettingsPrintHeaderEnabled = dms_resolve_module_print_header_setting($conn, $documentSettingsModuleKey);
$documentSettingsShowPrintHeaderToggle = false;

if (strtolower(trim((string)($_GET['action'] ?? ''))) === 'view_signature') {
    $requestedKey = trim((string)($_GET['signatory'] ?? ''));
    $signaturePath = trim((string)($documentSettingsRows[$requestedKey]['signature_path'] ?? ''));
    $projectRoot = realpath(__DIR__ . '/..');
    $absolutePath = ($projectRoot !== false && $signaturePath !== '')
        ? realpath($projectRoot . '/' . ltrim(str_replace('\\', '/', $signaturePath), '/'))
        : false;

    if ($projectRoot === false || !appIsUnifiedAttachmentFile($absolutePath, $projectRoot)) {
        http_response_code(404);
        exit('Signature image not found.');
    }

    $mime = (string)(mime_content_type($absolutePath) ?: 'application/octet-stream');
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        http_response_code(415);
        exit('Unsupported signature image.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('Cache-Control: private, max-age=300');
    readfile($absolutePath);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_document_module_settings') {
    verifyCsrfToken(false);
    try {
        $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
        $signatorySave = dms_save_module_signatories($conn, $documentSettingsModuleKey, $_POST, $_FILES, $actorUserId);
        $before = ['signatories' => $signatorySave['before'] ?? []];
        $after = ['signatories' => $signatorySave['after'] ?? []];

        insertUnifiedAuditLog($conn, $actorUserId ?: null, trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official', 'document_settings', 'module_signatories', $documentSettingsModuleKey, 'update_signatories', 'signatory_configuration', json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Updated Clearance Issuance document and signatory settings.');
        header('Location: ' . $documentSettingsActionUrl . '?success=' . rawurlencode('Clearance document and signatory settings saved.'));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?error=' . rawurlencode($e->getMessage()));
        exit;
    }
}

include __DIR__ . '/includes/document_settings_page.php';
