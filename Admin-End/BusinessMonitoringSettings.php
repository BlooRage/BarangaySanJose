<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../PhpFiles/General/documentModuleSettings.php';

$documentSettingsModuleKey = 'monitoring';
$documentSettingsModuleConfig = dms_get_module_config($documentSettingsModuleKey);
$documentSettingsSuccessMessage = trim((string)($_GET['success'] ?? ''));
$documentSettingsErrorMessage = trim((string)($_GET['error'] ?? ''));
$documentSettingsRows = dms_resolve_module_signatories($conn, $documentSettingsModuleKey);
$documentSettingsFieldVisibility = dms_resolve_document_field_visibility($conn, $documentSettingsModuleKey);
$documentSettingsPrintHeaderEnabled = dms_resolve_module_print_header_setting($conn, $documentSettingsModuleKey);

include __DIR__ . '/includes/clearance_settings_landing.php';
