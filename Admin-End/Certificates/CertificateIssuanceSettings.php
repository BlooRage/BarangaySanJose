<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__ . '/../../PhpFiles/General/audit.php';

$documentSettingsModuleKey = 'issuance';
$documentSettingsModuleConfig = dms_get_module_config($documentSettingsModuleKey);
$documentSettingsActionUrl = appUrl('Admin-End/Certificates/CertificateIssuanceSettings.php');
$documentSettingsBackUrl = appUrl((string)($documentSettingsModuleConfig['back_href'] ?? 'Admin-End/AdminDashboard.php'));
$documentSettingsSuccessMessage = trim((string)($_GET['success'] ?? ''));
$documentSettingsErrorMessage = trim((string)($_GET['error'] ?? ''));
$documentSettingsRows = dms_resolve_module_signatories($conn, $documentSettingsModuleKey);
$issuanceSettings = dms_resolve_issuance_settings($conn);
$documentSettingsFeeRequestsUrl = appUrl('Admin-End/Certificates/CertificateTracker.php?tab=fees&fee_scope=issuance&filter_document=__certificates__');
$documentSettingsFeeRequestsTitle = 'Certificate Fee Change Requests';
$documentSettingsFeeRequestsDescription = 'Request an update to the fee assigned to a certificate type and review submitted requests.';
$governmentOfficialRows = dms_list_government_official_dropdown($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array((string)($_POST['action'] ?? ''), ['save_indigency_government_official', 'delete_indigency_government_official'], true)) {
    verifyCsrfToken(false);
    $officialAction = (string)$_POST['action'];
    try {
        if ($officialAction === 'delete_indigency_government_official') {
            $officialId = (int)($_POST['government_official_id'] ?? 0);
            if (!dms_delete_government_official_dropdown($conn, $officialId)) throw new RuntimeException('Government official was not found or could not be deleted.');
            $auditAfter = null;
            $message = 'Government official deleted.';
            $auditAction = 'delete_dropdown_official';
        } else {
            $auditAfter = dms_save_government_official_dropdown($conn, $_POST);
            $officialId = (int)($auditAfter['id'] ?? 0);
            $message = (int)($_POST['government_official_id'] ?? 0) > 0 ? 'Government official updated.' : 'Government official added.';
            $auditAction = (int)($_POST['government_official_id'] ?? 0) > 0 ? 'update_dropdown_official' : 'add_dropdown_official';
        }
        insertUnifiedAuditLog($conn, trim((string)($_SESSION['user_id'] ?? '')) ?: null, trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official', 'document_settings', 'indigency_government_official', (string)$officialId, $auditAction, 'dropdown_configuration', null, $auditAfter === null ? null : json_encode($auditAfter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $message);
        header('Location: ' . $documentSettingsActionUrl . '?success=' . rawurlencode($message) . '#indigency-officials');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?error=' . rawurlencode($e->getMessage()) . '#indigency-officials');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array((string)($_POST['action'] ?? ''), ['save_document_module_settings', 'save_issuance_settings'], true)) {
    verifyCsrfToken(false);

    try {
        $isOperationalSave = (string)($_POST['action'] ?? '') === 'save_issuance_settings';
        $saveResult = $isOperationalSave
            ? dms_save_issuance_settings($conn, $_POST, trim((string)($_SESSION['user_id'] ?? '')))
            : dms_save_module_signatories($conn, $documentSettingsModuleKey, $_POST, $_FILES, trim((string)($_SESSION['user_id'] ?? '')));

        insertUnifiedAuditLog(
            $conn,
            trim((string)($_SESSION['user_id'] ?? '')) ?: null,
            trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official',
            'document_settings',
            $isOperationalSave ? 'issuance_operations' : 'module_signatories',
            $documentSettingsModuleKey,
            $isOperationalSave ? 'update_settings' : 'update_signatories',
            $isOperationalSave ? 'issuance_configuration' : 'signatory_configuration',
            json_encode($saveResult['before'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($saveResult['after'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $isOperationalSave ? 'Updated Barangay Issuance operational, certificate, notification, and dropdown settings.' : 'Updated Barangay Issuance signatory settings.'
        );

        header('Location: ' . $documentSettingsActionUrl . '?success=' . rawurlencode($isOperationalSave ? 'Barangay Issuance settings saved.' : 'Barangay Issuance signatory settings saved.'));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $documentSettingsActionUrl . '?error=' . rawurlencode($e->getMessage()));
        exit;
    }
}

include __DIR__ . '/../includes/issuance_settings_page.php';
