<?php
require_once __DIR__ . '/../Admin-End/contentStore.php';

header('Content-Type: application/json; charset=UTF-8');

$items = announcements_load_all();
$publicNewsItems = [];

foreach ($items as $item) {
    $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
        return in_array((string)$ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
    }));

    $status = strtolower((string)($item['status'] ?? 'draft'));
    if (!in_array('public_news', $channels, true) || $status !== 'approved') {
        continue;
    }

    $rawPosted = (string)($item['publish_date'] ?? '');
    if ($rawPosted === '' || $rawPosted === '-') {
        $rawPosted = (string)($item['created_at'] ?? '');
    }

    $postedDate = '-';
    $sortTimestamp = 0;
    $ts = strtotime($rawPosted);
    if ($ts !== false) {
        $postedDate = date('F d, Y', $ts);
        $sortTimestamp = $ts;
    }

    $title = (string)($item['public_news_title'] ?? '');
    if ($title === '') {
        $title = (string)($item['title'] ?? '');
    }

    $contentHtml = (string)($item['public_news_content_html'] ?? '');
    if ($contentHtml === '') {
        $contentHtml = (string)($item['content_html'] ?? '');
    }

    $imageUrl = '';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentHtml, $matches)) {
        $imageUrl = (string)($matches[1] ?? '');
    }

    $publicNewsItems[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => $title,
        'content_html' => $contentHtml,
        'posted_date' => $postedDate,
        'image_url' => $imageUrl,
        'sort_ts' => $sortTimestamp
    ];
}

usort($publicNewsItems, static function (array $a, array $b): int {
    return ((int)($b['sort_ts'] ?? 0)) <=> ((int)($a['sort_ts'] ?? 0));
});

$latestItem = $publicNewsItems[0] ?? null;
if (is_array($latestItem)) {
    unset($latestItem['sort_ts']);
}

$newsItems = array_map(static function (array $item): array {
    unset($item['sort_ts']);
    return $item;
}, $publicNewsItems);

echo json_encode([
    'success' => true,
    'item' => $latestItem,
    'items' => $newsItems
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

