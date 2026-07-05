<?php
require_once __DIR__ . '/../General/connection.php';

function announcements_table_name(): string
{
  return 'contentstbl';
}

function announcements_ensure_schema(): void
{
  global $conn;

  static $done = false;
  if ($done || !isset($conn) || !($conn instanceof mysqli)) {
    return;
  }

  $table = announcements_table_name();
  $queries = [
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS content_type VARCHAR(30) NOT NULL DEFAULT 'page' AFTER title",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS audience_scope VARCHAR(20) NULL AFTER audience",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS area VARCHAR(100) NULL AFTER audience_scope",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS role_group VARCHAR(100) NULL AFTER area",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS news_headline_image_url TEXT NULL AFTER public_news_content_html",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS news_sections_json LONGTEXT NULL AFTER news_headline_image_url",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sms_message TEXT NULL AFTER public_content_html",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS email_subject VARCHAR(255) NULL AFTER sms_message",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS email_body_html LONGTEXT NULL AFTER email_subject",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS sms_sent_at DATETIME NULL AFTER reviewed_by",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS email_sent_at DATETIME NULL AFTER sms_sent_at",
    "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS faq_items_json LONGTEXT NULL AFTER email_sent_at"
  ];

  foreach ($queries as $sql) {
    @$conn->query($sql);
  }

  $statusColumn = $conn->query("
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{$table}'
      AND COLUMN_NAME = 'status'
    LIMIT 1
  ");
  $statusType = '';
  if ($statusColumn instanceof mysqli_result) {
    $statusRow = $statusColumn->fetch_assoc();
    $statusType = strtolower((string)($statusRow['COLUMN_TYPE'] ?? ''));
    $statusColumn->free();
  }
  if ($statusType !== '' && strpos($statusType, "'archived'") === false) {
    @$conn->query("ALTER TABLE {$table} MODIFY COLUMN status ENUM('draft','pending','approved','archived') NOT NULL DEFAULT 'draft'");
  }

  $done = true;
}

function announcements_normalize_datetime(?string $value): ?string
{
  $value = trim((string)$value);
  if ($value === '' || $value === '-') {
    return null;
  }

  $ts = strtotime($value);
  if ($ts === false) {
    return null;
  }

  return date('Y-m-d H:i:s', $ts);
}

function announcements_format_datetime(?string $value, string $fallback = '-'): string
{
  $value = trim((string)$value);
  if ($value === '') {
    return $fallback;
  }

  $ts = strtotime($value);
  if ($ts === false) {
    return $value;
  }

  return date('Y-m-d H:i:s', $ts);
}

function announcements_decode_channels(?string $json): array
{
  $decoded = json_decode((string)$json, true);
  if (!is_array($decoded)) {
    return [];
  }

  return array_values(array_filter($decoded, static function ($item): bool {
    return is_string($item) && $item !== '';
  }));
}

function announcements_prepare_row(array $row): array
{
  $contentType = (string)($row['content_type'] ?? 'page');
  if (!in_array($contentType, ['page', 'news', 'delivery', 'faq'], true)) {
    $contentType = 'page';
  }

  return [
    'id' => trim((string)($row['id'] ?? '')),
    'title' => (string)($row['title'] ?? ''),
    'content_type' => $contentType,
    'audience' => (string)($row['audience'] ?? 'All Residents'),
    'audience_scope' => (string)($row['audience_scope'] ?? 'all'),
    'area' => (string)($row['area'] ?? ''),
    'role_group' => (string)($row['role_group'] ?? ''),
    'channels' => array_values(array_unique(array_filter((array)($row['channels'] ?? []), static function ($item): bool {
      return is_string($item) && $item !== '';
    }))),
    'status' => in_array((string)($row['status'] ?? 'draft'), ['draft', 'pending', 'approved', 'archived'], true)
      ? (string)$row['status']
      : 'draft',
    'publish_date' => trim((string)($row['publish_date'] ?? '-')) ?: '-',
    'created_by' => (string)($row['created_by'] ?? 'Admin'),
    'created_by_user_id' => (string)($row['created_by_user_id'] ?? ''),
    'created_by_role' => (string)($row['created_by_role'] ?? 'Admin'),
    'content_html' => (string)($row['content_html'] ?? ''),
    'public_news_title' => (string)($row['public_news_title'] ?? ''),
    'public_news_content_html' => (string)($row['public_news_content_html'] ?? ''),
    'news_headline_image_url' => (string)($row['news_headline_image_url'] ?? ''),
    'news_sections_json' => (string)($row['news_sections_json'] ?? ''),
    'public_title' => (string)($row['public_title'] ?? ''),
    'public_content_html' => (string)($row['public_content_html'] ?? ''),
    'sms_message' => (string)($row['sms_message'] ?? ''),
    'email_subject' => (string)($row['email_subject'] ?? ''),
    'email_body_html' => (string)($row['email_body_html'] ?? ''),
    'review_result' => (string)($row['review_result'] ?? ''),
    'review_note' => (string)($row['review_note'] ?? ''),
    'created_at' => trim((string)($row['created_at'] ?? '')),
    'updated_at' => trim((string)($row['updated_at'] ?? '')),
    'updated_by' => (string)($row['updated_by'] ?? ''),
    'reviewed_at' => trim((string)($row['reviewed_at'] ?? '')),
    'reviewed_by' => (string)($row['reviewed_by'] ?? ''),
    'sms_sent_at' => trim((string)($row['sms_sent_at'] ?? '')),
    'email_sent_at' => trim((string)($row['email_sent_at'] ?? '')),
    'faq_items_json' => (string)($row['faq_items_json'] ?? ''),
  ];
}

function announcements_load_all(): array
{
  global $conn;
  announcements_ensure_schema();

  $table = announcements_table_name();
  $sql = "
    SELECT
      id,
      title,
      content_type,
      audience,
      audience_scope,
      area,
      role_group,
      channels_json,
      status,
      publish_date,
      created_by,
      created_by_user_id,
      created_by_role,
      content_html,
      public_news_title,
      public_news_content_html,
      news_headline_image_url,
      news_sections_json,
      public_title,
      public_content_html,
      sms_message,
      email_subject,
      email_body_html,
      review_result,
      review_note,
      created_at,
      updated_at,
      updated_by,
      reviewed_at,
      reviewed_by,
      sms_sent_at,
      email_sent_at,
      faq_items_json
    FROM {$table}
    ORDER BY COALESCE(publish_date, created_at) DESC, created_at DESC, id DESC
  ";

  $result = $conn->query($sql);
  if (!($result instanceof mysqli_result)) {
    return [];
  }

  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = [
      'id' => (string)($row['id'] ?? ''),
      'title' => (string)($row['title'] ?? ''),
      'content_type' => (string)($row['content_type'] ?? 'page'),
      'audience' => (string)($row['audience'] ?? 'All Residents'),
      'audience_scope' => (string)($row['audience_scope'] ?? 'all'),
      'area' => (string)($row['area'] ?? ''),
      'role_group' => (string)($row['role_group'] ?? ''),
      'channels' => announcements_decode_channels($row['channels_json'] ?? '[]'),
      'status' => (string)($row['status'] ?? 'draft'),
      'publish_date' => ($row['publish_date'] ?? null) ? announcements_format_datetime((string)$row['publish_date']) : '-',
      'created_by' => (string)($row['created_by'] ?? 'Admin'),
      'created_by_user_id' => (string)($row['created_by_user_id'] ?? ''),
      'created_by_role' => (string)($row['created_by_role'] ?? 'Admin'),
      'content_html' => (string)($row['content_html'] ?? ''),
      'public_news_title' => (string)($row['public_news_title'] ?? ''),
      'public_news_content_html' => (string)($row['public_news_content_html'] ?? ''),
      'news_headline_image_url' => (string)($row['news_headline_image_url'] ?? ''),
      'news_sections_json' => (string)($row['news_sections_json'] ?? ''),
      'public_title' => (string)($row['public_title'] ?? ''),
      'public_content_html' => (string)($row['public_content_html'] ?? ''),
      'sms_message' => (string)($row['sms_message'] ?? ''),
      'email_subject' => (string)($row['email_subject'] ?? ''),
      'email_body_html' => (string)($row['email_body_html'] ?? ''),
      'review_result' => (string)($row['review_result'] ?? ''),
      'review_note' => (string)($row['review_note'] ?? ''),
      'created_at' => ($row['created_at'] ?? null) ? announcements_format_datetime((string)$row['created_at'], '') : '',
      'updated_at' => ($row['updated_at'] ?? null) ? announcements_format_datetime((string)$row['updated_at'], '') : '',
      'updated_by' => (string)($row['updated_by'] ?? ''),
      'reviewed_at' => ($row['reviewed_at'] ?? null) ? announcements_format_datetime((string)$row['reviewed_at'], '') : '',
      'reviewed_by' => (string)($row['reviewed_by'] ?? ''),
      'sms_sent_at' => ($row['sms_sent_at'] ?? null) ? announcements_format_datetime((string)$row['sms_sent_at'], '') : '',
      'email_sent_at' => ($row['email_sent_at'] ?? null) ? announcements_format_datetime((string)$row['email_sent_at'], '') : '',
      'faq_items_json' => (string)($row['faq_items_json'] ?? ''),
    ];
  }
  $result->free();

  return $rows;
}

function announcements_save_all(array $rows): bool
{
  global $conn;
  announcements_ensure_schema();

  $table = announcements_table_name();
  $normalizedRows = array_values(array_filter(array_map('announcements_prepare_row', $rows), static function (array $row): bool {
    return $row['id'] !== '';
  }));

  $sql = "
    INSERT INTO {$table} (
      id,
      title,
      content_type,
      audience,
      audience_scope,
      area,
      role_group,
      channels_json,
      status,
      publish_date,
      created_by,
      created_by_user_id,
      created_by_role,
      content_html,
      public_news_title,
      public_news_content_html,
      news_headline_image_url,
      news_sections_json,
      public_title,
      public_content_html,
      sms_message,
      email_subject,
      email_body_html,
      review_result,
      review_note,
      created_at,
      updated_at,
      updated_by,
      reviewed_at,
      reviewed_by,
      sms_sent_at,
      email_sent_at,
      faq_items_json
	    ) VALUES (
	      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
	    )
    ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      content_type = VALUES(content_type),
      audience = VALUES(audience),
      audience_scope = VALUES(audience_scope),
      area = VALUES(area),
      role_group = VALUES(role_group),
      channels_json = VALUES(channels_json),
      status = VALUES(status),
      publish_date = VALUES(publish_date),
      created_by = VALUES(created_by),
      created_by_user_id = VALUES(created_by_user_id),
      created_by_role = VALUES(created_by_role),
      content_html = VALUES(content_html),
      public_news_title = VALUES(public_news_title),
      public_news_content_html = VALUES(public_news_content_html),
      news_headline_image_url = VALUES(news_headline_image_url),
      news_sections_json = VALUES(news_sections_json),
      public_title = VALUES(public_title),
      public_content_html = VALUES(public_content_html),
      sms_message = VALUES(sms_message),
      email_subject = VALUES(email_subject),
      email_body_html = VALUES(email_body_html),
      review_result = VALUES(review_result),
      review_note = VALUES(review_note),
      created_at = VALUES(created_at),
      updated_at = VALUES(updated_at),
      updated_by = VALUES(updated_by),
      reviewed_at = VALUES(reviewed_at),
      reviewed_by = VALUES(reviewed_by),
      sms_sent_at = VALUES(sms_sent_at),
      email_sent_at = VALUES(email_sent_at),
      faq_items_json = VALUES(faq_items_json)
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }

  $conn->begin_transaction();

  try {
    foreach ($normalizedRows as $row) {
      $id = $row['id'];
      $title = $row['title'];
      $contentType = $row['content_type'];
      $audience = $row['audience'];
      $audienceScope = $row['audience_scope'] !== '' ? $row['audience_scope'] : 'all';
      $area = $row['area'] !== '' ? $row['area'] : null;
      $roleGroup = $row['role_group'] !== '' ? $row['role_group'] : null;
      $channelsJson = json_encode($row['channels'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($channelsJson === false) {
        throw new RuntimeException('Failed to encode channels.');
      }
      $status = $row['status'];
      $publishDate = announcements_normalize_datetime($row['publish_date']);
      $createdBy = $row['created_by'];
      $createdByUserId = $row['created_by_user_id'];
      $createdByRole = $row['created_by_role'];
      $contentHtml = $row['content_html'];
      $publicNewsTitle = $row['public_news_title'];
      $publicNewsContentHtml = $row['public_news_content_html'];
      $newsHeadlineImageUrl = $row['news_headline_image_url'] !== '' ? $row['news_headline_image_url'] : null;
      $newsSectionsJson = $row['news_sections_json'] !== '' ? $row['news_sections_json'] : null;
      $publicTitle = $row['public_title'];
      $publicContentHtml = $row['public_content_html'];
      $smsMessage = $row['sms_message'] !== '' ? $row['sms_message'] : null;
      $emailSubject = $row['email_subject'] !== '' ? $row['email_subject'] : null;
      $emailBodyHtml = $row['email_body_html'] !== '' ? $row['email_body_html'] : null;
      $reviewResult = $row['review_result'];
      $reviewNote = $row['review_note'];
      $createdAt = announcements_normalize_datetime($row['created_at']) ?? date('Y-m-d H:i:s');
      $updatedAt = announcements_normalize_datetime($row['updated_at']);
      $updatedBy = $row['updated_by'] !== '' ? $row['updated_by'] : null;
      $reviewedAt = announcements_normalize_datetime($row['reviewed_at']);
      $reviewedBy = $row['reviewed_by'] !== '' ? $row['reviewed_by'] : null;
      $smsSentAt = announcements_normalize_datetime($row['sms_sent_at']);
      $emailSentAt = announcements_normalize_datetime($row['email_sent_at']);
      $faqItemsJson = $row['faq_items_json'] !== '' ? $row['faq_items_json'] : null;

      $stmt->bind_param(
        str_repeat('s', 33),
        $id,
        $title,
        $contentType,
        $audience,
        $audienceScope,
        $area,
        $roleGroup,
        $channelsJson,
        $status,
        $publishDate,
        $createdBy,
        $createdByUserId,
        $createdByRole,
        $contentHtml,
        $publicNewsTitle,
        $publicNewsContentHtml,
        $newsHeadlineImageUrl,
        $newsSectionsJson,
        $publicTitle,
        $publicContentHtml,
        $smsMessage,
        $emailSubject,
        $emailBodyHtml,
        $reviewResult,
        $reviewNote,
        $createdAt,
        $updatedAt,
        $updatedBy,
        $reviewedAt,
        $reviewedBy,
        $smsSentAt,
        $emailSentAt,
        $faqItemsJson
      );

      if (!$stmt->execute()) {
        throw new RuntimeException('Failed to save announcement row.');
      }
    }

    if ($normalizedRows) {
      $ids = array_column($normalizedRows, 'id');
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $deleteSql = "DELETE FROM {$table} WHERE id NOT IN ({$placeholders})";
      $deleteStmt = $conn->prepare($deleteSql);
      if (!$deleteStmt) {
        throw new RuntimeException('Failed to prepare cleanup statement.');
      }
      $types = str_repeat('s', count($ids));
      $deleteStmt->bind_param($types, ...$ids);
      if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        throw new RuntimeException('Failed to remove stale announcements.');
      }
      $deleteStmt->close();
    } else {
      if (!$conn->query("DELETE FROM {$table}")) {
        throw new RuntimeException('Failed to clear announcements table.');
      }
    }

    $conn->commit();
    $stmt->close();
    return true;
  } catch (Throwable $e) {
    $conn->rollback();
    if ($stmt instanceof mysqli_stmt) {
      $stmt->close();
    }
    error_log('announcements_save_all failed: ' . $e->getMessage());
    return false;
  }
}

function announcement_generate_id(): string
{
  try {
    return 'ann_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
  } catch (Throwable $e) {
    return 'ann_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
  }
}





