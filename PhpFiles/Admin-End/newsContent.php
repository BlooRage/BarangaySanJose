<?php

function ann_news_extract_first_image_url(string $html): string
{
  if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
    return trim((string)($matches[1] ?? ''));
  }

  return '';
}

function ann_news_decode_sections_json(?string $json): array
{
  $decoded = json_decode((string)$json, true);
  if (!is_array($decoded)) {
    return [];
  }

  $sections = [];
  foreach ($decoded as $section) {
    if (!is_array($section)) {
      continue;
    }

    $type = strtolower(trim((string)($section['type'] ?? '')));
    if (!in_array($type, ['text', 'image'], true)) {
      continue;
    }

    if ($type === 'text') {
      $bodyHtml = trim((string)($section['body_html'] ?? ''));
      if (trim(strip_tags($bodyHtml)) === '') {
        continue;
      }
      $sections[] = [
        'type' => 'text',
        'body_html' => $bodyHtml,
      ];
      continue;
    }

    $imageUrl = trim((string)($section['image_url'] ?? ''));
    $caption = trim((string)($section['caption'] ?? ''));
    if ($imageUrl === '') {
      continue;
    }
    $sections[] = [
      'type' => 'image',
      'image_url' => $imageUrl,
      'caption' => $caption,
    ];
  }

  return $sections;
}

function ann_news_compose_html(string $headlineImageUrl, string $bodyHtml, array $sections, string $title = 'News image'): string
{
  $parts = [];
  $headlineImageUrl = trim($headlineImageUrl);
  $safeTitle = htmlspecialchars(trim($title) !== '' ? $title : 'News image', ENT_QUOTES, 'UTF-8');

  if ($headlineImageUrl !== '') {
    $parts[] = '<figure class="news-headline-figure"><img src="' . htmlspecialchars($headlineImageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeTitle . '"></figure>';
  }

  $bodyHtml = trim($bodyHtml);
  if ($bodyHtml !== '') {
    $parts[] = $bodyHtml;
  }

  foreach ($sections as $section) {
    $type = strtolower(trim((string)($section['type'] ?? '')));
    if ($type === 'text') {
      $sectionBody = trim((string)($section['body_html'] ?? ''));
      if ($sectionBody !== '') {
        $parts[] = '<section class="news-extra-block news-extra-block--text">' . $sectionBody . '</section>';
      }
      continue;
    }

    if ($type === 'image') {
      $imageUrl = trim((string)($section['image_url'] ?? ''));
      if ($imageUrl === '') {
        continue;
      }

      $caption = trim((string)($section['caption'] ?? ''));
      $imageHtml = '<figure class="news-extra-block news-extra-block--image"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeTitle . '">';
      if ($caption !== '') {
        $imageHtml .= '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>';
      }
      $imageHtml .= '</figure>';
      $parts[] = $imageHtml;
    }
  }

  return implode("\n", array_filter($parts, static function ($part): bool {
    return trim((string)$part) !== '';
  }));
}
