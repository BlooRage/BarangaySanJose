<?php
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/sendSMS.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';
require_once __DIR__ . '/../General/runtimeConfig.php';

function ann_delivery_missing_mail_fields(array $emailConfig): array
{
  $missing = [];
  if (trim((string)($emailConfig['host'] ?? '')) === '') {
    $missing[] = 'mail.host';
  }
  if (trim((string)($emailConfig['username'] ?? '')) === '') {
    $missing[] = 'mail.username';
  }
  if ((string)($emailConfig['password'] ?? '') === '') {
    $missing[] = 'mail.password';
  }
  if (trim((string)($emailConfig['from_email'] ?? '')) === '') {
    $missing[] = 'mail.from_email';
  }
  return $missing;
}

function ann_delivery_missing_sms_fields(): array
{
  $missing = [];
  if (trim((string)runtime_env('SMS_SEMAPHORE_API_KEY', runtime_env('SMS_API_KEY', runtime_config('sms.semaphore_api_key', '')))) === '') {
    $missing[] = 'sms.semaphore_api_key';
  }
  if (trim((string)runtime_env('SMS_SENDER', runtime_config('sms.sender', 'BrgySanJose'))) === '') {
    $missing[] = 'sms.sender';
  }
  return $missing;
}

function ann_delivery_strip_html(string $html): string
{
  $html = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html);
  $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace("/\r\n|\r/", "\n", $text);
  $text = preg_replace("/\n{3,}/", "\n\n", $text);
  return trim((string)$text);
}

function ann_delivery_compose_fields(array $announcement, ?string $preferredEmailSubject = null, ?string $preferredSmsMessage = null): array
{
  $title = trim((string)($announcement['title'] ?? ''));
  $bodyHtml = trim((string)($announcement['content_html'] ?? ''));

  $announcementTitle = trim((string)($announcement['public_title'] ?? ''));
  $announcementBodyHtml = trim((string)($announcement['public_content_html'] ?? ''));
  $newsTitle = trim((string)($announcement['public_news_title'] ?? ''));
  $newsBodyHtml = trim((string)($announcement['public_news_content_html'] ?? ''));

  if ($announcementTitle !== '' || $announcementBodyHtml !== '') {
    $title = $announcementTitle !== '' ? $announcementTitle : $title;
    $bodyHtml = $announcementBodyHtml !== '' ? $announcementBodyHtml : $bodyHtml;
  } elseif ($newsTitle !== '' || $newsBodyHtml !== '') {
    $title = $newsTitle !== '' ? $newsTitle : $title;
    $bodyHtml = $newsBodyHtml !== '' ? $newsBodyHtml : $bodyHtml;
  }

  $plainText = ann_delivery_strip_html($bodyHtml);
  $smsMessage = trim((string)$preferredSmsMessage);
  if ($smsMessage === '') {
    $smsMessage = trim($title . ($plainText !== '' ? ("\n\n" . $plainText) : ''));
  }

  $emailSubject = trim((string)$preferredEmailSubject);
  if ($emailSubject === '') {
    $emailSubject = $title !== '' ? $title : 'Barangay Announcement';
  }

  return [
    'sms_message' => $smsMessage,
    'email_subject' => $emailSubject,
    'email_body_html' => $bodyHtml,
  ];
}

function ann_delivery_normalize_group(string $group): string
{
  $group = strtolower(trim($group));
  if ($group === 'officials' || $group === 'official') return 'official';
  if ($group === 'employees' || $group === 'employee' || $group === 'personnel' || $group === 'personnels') return 'employee';
  if ($group === 'residents' || $group === 'resident') return 'resident';
  return $group;
}

function ann_delivery_normalize_area(?string $area): string
{
  $area = strtolower(trim((string)$area));
  if ($area === '') {
    return '';
  }

  $area = preg_replace('/\s+/', ' ', $area);
  if ($area === 'barangay wide' || $area === 'barangaywide') {
    return 'barangaywide';
  }

  if (preg_match('/^area\s*0*(\d+)\s*([a-z]?)$/', $area, $matches)) {
    $number = (string)((int)$matches[1]);
    $suffix = strtolower((string)($matches[2] ?? ''));
    return 'area' . $number . $suffix;
  }

  return preg_replace('/[^a-z0-9]+/', '', $area);
}

function ann_delivery_parse_csv_values(?string $value): array
{
  $parts = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];
  return array_values(array_filter(array_map(static function ($item): string {
    return trim((string)$item);
  }, $parts), static function (string $item): bool {
    return $item !== '';
  }));
}

function ann_delivery_is_verified_resident_status(?string $statusName): bool
{
  $statusKey = strtolower(str_replace([' ', '_', '-'], '', (string)$statusName));
  return in_array($statusKey, ['verifiedresident', 'verified'], true);
}

function ann_delivery_target_groups(array $announcement): array
{
  $audienceScope = strtolower(trim((string)($announcement['audience_scope'] ?? 'all')));
  $roleGroups = array_values(array_filter(array_map(static function ($group): string {
    return ann_delivery_normalize_group($group);
  }, ann_delivery_parse_csv_values((string)($announcement['role_group'] ?? ''))), static function (string $group): bool {
    return $group !== '';
  }));

  if ($audienceScope === 'custom' && $roleGroups) {
    return array_values(array_unique($roleGroups));
  }

  $audience = strtolower((string)($announcement['audience'] ?? 'all residents'));
  if (strpos($audience, 'official') !== false) return ['official'];
  if (strpos($audience, 'employee') !== false || strpos($audience, 'personnel') !== false) return ['employee'];
  return ['resident'];
}

function ann_delivery_normalize_phone(?string $phone): string
{
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
    return '0' . $digits;
  }
  if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
    return $digits;
  }
  if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
    return '0' . substr($digits, 2);
  }
  return '';
}

function ann_delivery_has_usable_email(?string $email): bool
{
  $email = trim((string)$email);
  return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function ann_delivery_staff_group_matches(?string $accountRole, ?string $infoRole, string $targetGroup): bool
{
  $roles = [ann_delivery_normalize_group((string)$accountRole), ann_delivery_normalize_group((string)$infoRole)];

  foreach ($roles as $role) {
    if ($targetGroup === 'official' && in_array($role, ['official', 'admin', 'superadmin'], true)) {
      return true;
    }
    if ($targetGroup === 'employee' && in_array($role, ['employee'], true)) {
      return true;
    }
  }

  $rawRoles = [strtolower(trim((string)$accountRole)), strtolower(trim((string)$infoRole))];
  foreach ($rawRoles as $raw) {
    if ($targetGroup === 'official' && in_array($raw, ['official', 'officials', 'admin', 'superadmin'], true)) {
      return true;
    }
    if ($targetGroup === 'employee' && in_array($raw, ['employee', 'personnel', 'personnels'], true)) {
      return true;
    }
  }

  return false;
}

function ann_delivery_fetch_resident_recipients(mysqli $conn, string $areaFilter = ''): array
{
  $recipients = [];
  $stmt = $conn->prepare("\n    SELECT u.user_id, u.email, u.email_verify, u.phone_number, u.phoneNum_verify, s.status_name, a.area_number\n    FROM useraccountstbl u\n    INNER JOIN residentinformationtbl r ON r.user_id = u.user_id\n    LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id\n    LEFT JOIN residentaddresstbl a ON a.resident_id = r.resident_id\n      AND a.address_id = (\n        SELECT MAX(a2.address_id)\n        FROM residentaddresstbl a2\n        WHERE a2.resident_id = r.resident_id\n      )\n    WHERE u.role_access = 'Resident'\n  ");
  if (!$stmt) {
    return [];
  }
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    if (!ann_delivery_is_verified_resident_status($row['status_name'] ?? null)) {
      continue;
    }
    if ($areaFilter !== '' && ann_delivery_normalize_area($row['area_number'] ?? '') !== $areaFilter) {
      continue;
    }
    $recipients[] = [
      'user_id' => (string)($row['user_id'] ?? ''),
      'email' => trim((string)($row['email'] ?? '')),
      'phone' => ann_delivery_normalize_phone((string)($row['phone_number'] ?? '')),
    ];
  }
  $stmt->close();
  return $recipients;
}

function ann_delivery_fetch_staff_recipients(mysqli $conn, string $targetGroup, string $areaFilter = ''): array
{
  $recipients = [];
  $stmt = $conn->prepare("\n    SELECT u.user_id, u.email, u.email_verify, u.phone_number, u.phoneNum_verify, u.role_access AS account_role_access, oi.role_access AS info_role_access, oi.area_number\n    FROM useraccountstbl u\n    INNER JOIN officialinformationtbl oi ON oi.user_id = u.user_id\n  ");
  if (!$stmt) {
    return [];
  }
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    if (!ann_delivery_staff_group_matches($row['account_role_access'] ?? '', $row['info_role_access'] ?? '', $targetGroup)) {
      continue;
    }
    if ($areaFilter !== '' && ann_delivery_normalize_area($row['area_number'] ?? '') !== $areaFilter) {
      continue;
    }
    $recipients[] = [
      'user_id' => (string)($row['user_id'] ?? ''),
      'email' => trim((string)($row['email'] ?? '')),
      'phone' => ann_delivery_normalize_phone((string)($row['phone_number'] ?? '')),
    ];
  }
  $stmt->close();
  return $recipients;
}

function ann_delivery_fetch_recipients(mysqli $conn, array $announcement): array
{
  $groups = ann_delivery_target_groups($announcement);
  $audienceScope = strtolower(trim((string)($announcement['audience_scope'] ?? 'all')));
  $areaFilters = $audienceScope === 'custom'
    ? array_values(array_filter(array_map(static function ($area): string {
        return ann_delivery_normalize_area($area);
      }, ann_delivery_parse_csv_values((string)($announcement['area'] ?? ''))), static function (string $area): bool {
        return $area !== '';
      }))
    : [];
  $recipients = [];

  foreach ($groups as $group) {
    if ($group === 'resident') {
      if (!$areaFilters) {
        foreach (ann_delivery_fetch_resident_recipients($conn, '') as $recipient) {
          $recipients[$recipient['user_id']] = $recipient;
        }
        continue;
      }

      foreach ($areaFilters as $filter) {
        foreach (ann_delivery_fetch_resident_recipients($conn, $filter) as $recipient) {
          $recipients[$recipient['user_id']] = $recipient;
        }
      }
      continue;
    }

    if (in_array($group, ['official', 'employee'], true)) {
      if (!$areaFilters) {
        foreach (ann_delivery_fetch_staff_recipients($conn, $group, '') as $recipient) {
          $recipients[$recipient['user_id']] = $recipient;
        }
        continue;
      }

      foreach ($areaFilters as $filter) {
        foreach (ann_delivery_fetch_staff_recipients($conn, $group, $filter) as $recipient) {
          $recipients[$recipient['user_id']] = $recipient;
        }
      }
    }
  }

  return array_values($recipients);
}

function ann_delivery_send(mysqli $conn, array &$announcement): array
{
  $channels = array_values(array_filter((array)($announcement['channels'] ?? []), static function ($ch): bool {
    return is_string($ch) && $ch !== '';
  }));

  $fields = ann_delivery_compose_fields(
    $announcement,
    (string)($announcement['email_subject'] ?? ''),
    (string)($announcement['sms_message'] ?? '')
  );
  $announcement['sms_message'] = $fields['sms_message'];
  $announcement['email_subject'] = $fields['email_subject'];
  $announcement['email_body_html'] = $fields['email_body_html'];

  $recipients = ann_delivery_fetch_recipients($conn, $announcement);
  $smsCount = 0;
  $emailCount = 0;
  $smsEligible = 0;
  $emailEligible = 0;
  $smsMissing = ann_delivery_missing_sms_fields();
  $smsConfigured = $smsMissing === [];
  $smsLastError = '';
  $emailConfig = require __DIR__ . '/../General/mailConfigurations.php';
  $emailMissing = ann_delivery_missing_mail_fields($emailConfig);
  $emailConfigured = $emailMissing === [];
  $emailLastError = '';

  if (in_array('sms', $channels, true) && $announcement['sms_message'] !== '') {
    foreach ($recipients as $recipient) {
      if ($recipient['phone'] === '') {
        continue;
      }
      $smsEligible++;
      if (sendSMS($recipient['phone'], $announcement['sms_message'])) {
        $smsCount++;
      } else {
        $smsLastError = getLastSmsError();
      }
    }
    if ($smsCount > 0) {
      $announcement['sms_sent_at'] = date('Y-m-d H:i:s');
    }
  }

  if (in_array('email', $channels, true) && $announcement['email_subject'] !== '') {
    $emailSender = new EmailSender($emailConfig);
    $plainMessage = ann_delivery_strip_html((string)$announcement['email_body_html']);
    $emailTitle = trim((string)(($announcement['public_title'] ?? '') ?: ($announcement['public_news_title'] ?? '') ?: ($announcement['title'] ?? 'Announcement')));

    foreach ($recipients as $recipient) {
      if (!ann_delivery_has_usable_email($recipient['email'] ?? '')) {
        continue;
      }
      $emailEligible++;
      $sent = $emailSender->send([
        'type' => 'announcement',
        'to' => $recipient['email'],
        'subject' => $announcement['email_subject'],
        'template' => 'emails/announcement.php',
        'data' => [
          'title' => $emailTitle !== '' ? $emailTitle : 'Announcement',
          'message' => $plainMessage,
        ],
      ]);
      if ($sent) {
        $emailCount++;
      } else {
        $emailLastError = $emailSender->getLastError();
      }
    }

    if ($emailCount > 0) {
      $announcement['email_sent_at'] = date('Y-m-d H:i:s');
    }
  }

  return [
    'recipient_count' => count($recipients),
    'sms_eligible' => $smsEligible,
    'email_eligible' => $emailEligible,
    'sms_count' => $smsCount,
    'email_count' => $emailCount,
    'sms_configured' => $smsConfigured,
    'sms_missing' => $smsMissing,
    'sms_last_error' => $smsLastError,
    'email_configured' => $emailConfigured,
    'email_missing' => $emailMissing,
    'email_last_error' => $emailLastError,
  ];
}

function ann_delivery_message_suffix(array $result): string
{
  $parts = [];
  $smsCount = (int)($result['sms_count'] ?? 0);
  $emailCount = (int)($result['email_count'] ?? 0);
  $smsEligible = (int)($result['sms_eligible'] ?? 0);
  $emailEligible = (int)($result['email_eligible'] ?? 0);
  $recipientCount = (int)($result['recipient_count'] ?? 0);
  $smsConfigured = (bool)($result['sms_configured'] ?? true);
  $emailConfigured = (bool)($result['email_configured'] ?? true);
  $smsMissing = array_values(array_filter((array)($result['sms_missing'] ?? []), 'is_string'));
  $emailMissing = array_values(array_filter((array)($result['email_missing'] ?? []), 'is_string'));
  $smsLastError = trim((string)($result['sms_last_error'] ?? ''));
  $emailLastError = trim((string)($result['email_last_error'] ?? ''));

  if ($smsCount > 0) {
    $parts[] = 'SMS sent: ' . $smsCount;
  } elseif (!$smsConfigured) {
    $parts[] = 'SMS not configured' . ($smsMissing ? ' (' . implode(', ', $smsMissing) . ')' : '');
  } elseif ($smsEligible > 0) {
    $parts[] = 'SMS delivery failed' . ($smsLastError !== '' ? ' (' . $smsLastError . ')' : '');
  } elseif ($smsEligible === 0 && $recipientCount > 0) {
    $parts[] = 'No SMS recipients with mobile numbers found';
  }

  if ($emailCount > 0) {
    $parts[] = 'Email sent: ' . $emailCount;
  } elseif (!$emailConfigured) {
    $parts[] = 'Email not configured' . ($emailMissing ? ' (' . implode(', ', $emailMissing) . ')' : '');
  } elseif ($emailEligible > 0) {
    $parts[] = 'Email delivery failed' . ($emailLastError !== '' ? ' (' . $emailLastError . ')' : '');
  } elseif ($emailEligible === 0 && $recipientCount > 0) {
    $parts[] = 'No recipients with valid email addresses found';
  }

  if ($recipientCount === 0) {
    $parts[] = 'No eligible recipients found';
  }

  return $parts ? (' ' . implode(' | ', $parts) . '.') : '';
}
?>
