<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__ . '/../PhpFiles/General/audit.php';

$documentSettingsModuleKey = 'monitoring';
$documentSettingsModuleConfig = dms_get_module_config($documentSettingsModuleKey);
$documentSettingsActionUrl = appUrl('Admin-End/BusinessMonitoringSettings.php');
$documentSettingsBackUrl = appUrl((string)($documentSettingsModuleConfig['back_href'] ?? 'Admin-End/AdminDashboard.php'));
$documentSettingsSuccessMessage = trim((string)($_GET['success'] ?? ''));
$documentSettingsErrorMessage = trim((string)($_GET['error'] ?? ''));
$documentSettingsRows = dms_resolve_module_signatories($conn, $documentSettingsModuleKey);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_document_module_settings') {
    verifyCsrfToken(false);

    try {
        $saveResult = dms_save_module_signatories(
            $conn,
            $documentSettingsModuleKey,
            $_POST,
            $_FILES,
            trim((string)($_SESSION['user_id'] ?? ''))
        );

        insertUnifiedAuditLog(
            $conn,
            trim((string)($_SESSION['user_id'] ?? '')) ?: null,
            trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official',
            'document_settings',
            'module_signatories',
            $documentSettingsModuleKey,
            'update_signatories',
            'signatory_configuration',
            json_encode($saveResult['before'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($saveResult['after'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Updated Barangay Monitoring signatory settings.'
        );

        header('Location: ' . $documentSettingsActionUrl . '?success=' . rawurlencode('Barangay Monitoring signatory settings saved.'));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?error=' . rawurlencode($e->getMessage()));
        exit;
    }
}

include __DIR__ . '/includes/document_settings_page.php';
