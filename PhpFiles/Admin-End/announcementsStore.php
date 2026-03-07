<?php

function announcements_store_path(): string
{
  return __DIR__ . '/storage/announcements.json';
}

function announcements_load_all(): array
{
  $path = announcements_store_path();
  if (!is_file($path)) {
    return [];
  }

  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return [];
  }

  return array_values(array_filter($decoded, static fn($item) => is_array($item)));
}

function announcements_save_all(array $rows): bool
{
  $path = announcements_store_path();
  $dir = dirname($path);
  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    return false;
  }

  $encoded = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($encoded === false) {
    return false;
  }

  return file_put_contents($path, $encoded, LOCK_EX) !== false;
}

function announcement_generate_id(): string
{
  try {
    return 'ann_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
  } catch (Throwable $e) {
    return 'ann_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
  }
}
