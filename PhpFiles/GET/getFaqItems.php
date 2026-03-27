<?php
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/siteContent.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$page = cms_content_page($conn, 'faq');
$faqItems = [];

foreach ((array)($page['faq_items'] ?? []) as $faq) {
    if (!is_array($faq)) {
        continue;
    }

    $question = trim((string)($faq['question'] ?? ''));
    $answer = trim((string)($faq['answer'] ?? ''));
    if ($question === '' || $answer === '') {
        continue;
    }

    $faqItems[] = [
        'question' => $question,
        'answer' => $answer,
    ];
}

echo json_encode([
    'success' => true,
    'items' => $faqItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
