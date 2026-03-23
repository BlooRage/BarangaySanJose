<?php
require_once __DIR__ . '/../Admin-End/contentStore.php';

header('Content-Type: application/json; charset=UTF-8');

$items = announcements_load_all();
$faqItems = [];

foreach ($items as $item) {
    $status = strtolower((string)($item['status'] ?? 'draft'));
    $contentType = strtolower((string)($item['content_type'] ?? 'page'));
    if ($status !== 'approved' || $contentType !== 'faq') {
        continue;
    }

    $decoded = json_decode((string)($item['faq_items_json'] ?? ''), true);
    if (!is_array($decoded) || $decoded === []) {
        $decoded = [[
            'question' => (string)($item['title'] ?? ''),
            'answer' => trim(strip_tags((string)($item['content_html'] ?? ''))),
        ]];
    }

    foreach ($decoded as $faq) {
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
}

echo json_encode([
    'success' => true,
    'items' => $faqItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
