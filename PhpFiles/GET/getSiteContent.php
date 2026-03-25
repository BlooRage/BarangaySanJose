<?php
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/siteContent.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pageKey = cms_content_normalize_page_key((string)($_GET['page'] ?? ''));
if ($pageKey === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid content page.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = $pageKey === 'home'
    ? cms_content_page_with_context($conn, $pageKey)
    : cms_content_page($conn, $pageKey);

echo json_encode([
    'success' => true,
    'page_key' => $pageKey,
    'page_label' => cms_content_page_label($pageKey),
    'payload' => $payload,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
