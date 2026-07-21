<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__ . '/../../PhpFiles/General/audit.php';
$routes = ['general' => 'Admin-End/ClearanceGeneralSettings.php', 'types' => 'Admin-End/ClearanceTypeSettings.php', 'notifications' => 'Admin-End/ClearanceNotificationSettings.php'];
if (!isset($routes[$clearanceSection])) throw new RuntimeException('Unknown clearance settings section.');
$sectionActionUrl = appUrl($routes[$clearanceSection]);
$settingsOverviewUrl = appUrl('Admin-End/BusinessMonitoringSettings.php');
$clearanceSettings = dms_resolve_clearance_settings($conn);
$notificationRecipientOptions = $clearanceSection === 'notifications' ? dms_list_notification_recipient_options($conn) : [];
$successMessage = trim((string)($_GET['success'] ?? ''));
$errorMessage = trim((string)($_GET['error'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken(false);
    try {
        if ((string)($_POST['action'] ?? '') !== 'save_clearance_section') throw new RuntimeException('Unsupported settings action.');
        $_POST['settings_scope'] = $clearanceSection;
        $actor = trim((string)($_SESSION['user_id'] ?? ''));
        $result = dms_save_clearance_settings($conn, $_POST, $actor);
        $message = ucfirst($clearanceSection) . ' clearance settings saved.';
        insertUnifiedAuditLog($conn, $actor ?: null, trim((string)($_SESSION['role'] ?? 'Official')) ?: 'Official', 'document_settings', 'clearance_' . $clearanceSection, 'monitoring', 'update_settings', 'configuration', json_encode($result['before'] ?? []), json_encode($result['after'] ?? []), $message);
        header('Location: ' . $sectionActionUrl . '?success=' . rawurlencode($message)); exit;
    } catch (Throwable $e) { header('Location: ' . $sectionActionUrl . '?error=' . rawurlencode($e->getMessage())); exit; }
}
include __DIR__ . '/clearance_settings_section_page.php';
