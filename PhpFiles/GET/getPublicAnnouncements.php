<?php
require_once __DIR__ . '/../Admin-End/announcementsStore.php';

header('Content-Type: application/json; charset=UTF-8');

$items = announcements_load_all();
$publicAnnouncements = [];

foreach ($items as $item) {
    $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
        return in_array((string)$ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
    }));

    $status = strtolower((string)($item['status'] ?? 'draft'));
    if (!in_array('public', $channels, true) || $status !== 'approved') {
        continue;
    }

    $rawPosted = (string)($item['publish_date'] ?? '');
    if ($rawPosted === '' || $rawPosted === '-') {
        $rawPosted = (string)($item['created_at'] ?? '');
    }

    $postedDate = '-';
    $ts = strtotime($rawPosted);
    if ($ts !== false) {
        $postedDate = date('F d, Y', $ts);
    }

    $publicAnnouncements[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => (string)($item['title'] ?? ''),
        'content_html' => (string)($item['content_html'] ?? ''),
        'posted_date' => $postedDate
    ];
}

echo json_encode([
    'success' => true,
    'items' => array_slice($publicAnnouncements, 0, 6)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
