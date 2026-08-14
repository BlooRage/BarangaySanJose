<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

define('BARANGAY_DOCUMENT_WORKFLOW_CLI', true);

require_once __DIR__ . '/../PhpFiles/General/security.php';

$maintenanceUserId = trim((string)(getenv('BARANGAY_MAINT_USER_ID') ?: 'CLI_MAINT'));
$maintenanceRole = trim((string)(getenv('BARANGAY_MAINT_ROLE') ?: 'Official'));
$_SESSION['user_id'] = (string)($_SESSION['user_id'] ?? $maintenanceUserId);
$_SESSION['role'] = (string)($_SESSION['role'] ?? $maintenanceRole);
$_SESSION['last_activity'] = time();

$limit = isset($argv[1]) ? max(1, min(1000, (int)$argv[1])) : 500;
$requestId = isset($argv[2]) ? trim((string)$argv[2]) : '';

$_REQUEST['action'] = 'bulk_regenerate_issued';
$_GET['action'] = 'bulk_regenerate_issued';
$_REQUEST['limit'] = (string)$limit;
$_GET['limit'] = (string)$limit;

if ($requestId !== '') {
    $_REQUEST['request_id'] = $requestId;
    $_GET['request_id'] = $requestId;
}

require __DIR__ . '/../PhpFiles/Admin-End/documentRequestWorkflow.php';
