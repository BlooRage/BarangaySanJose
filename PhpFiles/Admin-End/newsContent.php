<?php

function ann_news_node_has_class(DOMNode $node, string $className): bool
{
  if (!($node instanceof DOMElement)) {
    return false;
  }

  $classAttr = trim((string)$node->getAttribute('class'));
  if ($classAttr === '') {
    return false;
  }

  $classes = preg_split('/\s+/', $classAttr) ?: [];
  return in_array($className, $classes, true);
}

function ann_news_node_inner_html(DOMNode $node): string
{
  $html = '';
  foreach ($node->childNodes as $childNode) {
    $html .= $node->ownerDocument ? $node->ownerDocument->saveHTML($childNode) : '';
  }
  return trim($html);
}

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

function ann_news_decompose_html(string $html): array
{
  $result = [
    'headline_image_url' => '',
    'body_html' => trim($html),
    'sections' => [],
  ];

  $html = trim($html);
  if ($html === '') {
    return $result;
  }

  if (!class_exists('DOMDocument')) {
    return $result;
  }

  $previousUseErrors = libxml_use_internal_errors(true);
  $dom = new DOMDocument('1.0', 'UTF-8');
  $wrappedHtml = '<!DOCTYPE html><html><body><div id="news-decompose-root">' . $html . '</div></body></html>';
  $loaded = $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();
  libxml_use_internal_errors($previousUseErrors);

  if (!$loaded) {
    return $result;
  }

  $root = null;
  foreach ($dom->getElementsByTagName('div') as $div) {
    if ($div->getAttribute('id') === 'news-decompose-root') {
      $root = $div;
      break;
    }
  }

  if (!$root) {
    return $result;
  }

  $bodyParts = [];
  foreach ($root->childNodes as $childNode) {
    if ($childNode instanceof DOMElement && $childNode->tagName === 'figure' && ann_news_node_has_class($childNode, 'news-headline-figure')) {
      if ($result['headline_image_url'] === '') {
        $images = $childNode->getElementsByTagName('img');
        if ($images->length > 0) {
          $result['headline_image_url'] = trim((string)$images->item(0)?->getAttribute('src'));
        }
      }
      continue;
    }

    if ($childNode instanceof DOMElement && ann_news_node_has_class($childNode, 'news-extra-block')) {
      if (ann_news_node_has_class($childNode, 'news-extra-block--text')) {
        $sectionBodyHtml = ann_news_node_inner_html($childNode);
        if (trim(strip_tags($sectionBodyHtml)) !== '') {
          $result['sections'][] = [
            'type' => 'text',
            'body_html' => $sectionBodyHtml,
          ];
        }
        continue;
      }

      if (ann_news_node_has_class($childNode, 'news-extra-block--image')) {
        $imageUrl = '';
        $caption = '';
        $images = $childNode->getElementsByTagName('img');
        if ($images->length > 0) {
          $imageUrl = trim((string)$images->item(0)?->getAttribute('src'));
        }
        $captions = $childNode->getElementsByTagName('figcaption');
        if ($captions->length > 0) {
          $caption = trim((string)$captions->item(0)?->textContent);
        }
        if ($imageUrl !== '') {
          $result['sections'][] = [
            'type' => 'image',
            'image_url' => $imageUrl,
            'caption' => $caption,
          ];
        }
        continue;
      }
    }

    $bodyParts[] = $dom->saveHTML($childNode);
  }

  $bodyHtml = trim(implode('', $bodyParts));
  if ($bodyHtml !== '') {
    $result['body_html'] = $bodyHtml;
  }

  return $result;
}
