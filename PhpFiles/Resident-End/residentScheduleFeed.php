<?php
require_once __DIR__ . '/../Admin-End/contentStore.php';
require_once __DIR__ . '/../Admin-End/announcementAudience.php';

if (!function_exists('resident_schedule_table_exists')) {
    function resident_schedule_table_exists(mysqli $conn, string $tableName): bool
    {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return !empty($row);
    }
}

if (!function_exists('resident_schedule_table_columns')) {
    function resident_schedule_table_columns(mysqli $conn, string $tableName): array
    {
        $columns = [];
        $stmt = $conn->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        if (!$stmt) {
            return $columns;
        }

        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $column = strtolower(trim((string)($row['COLUMN_NAME'] ?? '')));
            if ($column !== '') {
                $columns[$column] = true;
            }
        }
        $stmt->close();

        return $columns;
    }
}

if (!function_exists('resident_schedule_status_bucket')) {
    function resident_schedule_status_bucket(string $statusName): string
    {
        $normalized = strtolower(trim($statusName));
        if ($normalized === '') {
            return 'pending';
        }
        if (str_contains($normalized, 'confirm') || str_contains($normalized, 'approve') || str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
            return 'approved';
        }
        if (str_contains($normalized, 'resched')) {
            return 'info';
        }
        if (str_contains($normalized, 'deny') || str_contains($normalized, 'reject') || str_contains($normalized, 'cancel')) {
            return 'archived';
        }
        return 'pending';
    }
}

if (!function_exists('resident_schedule_status_label')) {
    function resident_schedule_status_label(string $statusName): string
    {
        $normalized = strtolower(trim($statusName));
        if (str_contains($normalized, 'resched')) {
            return 'Rescheduled';
        }
        if (str_contains($normalized, 'confirm') || str_contains($normalized, 'approve') || str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
            return 'Confirmed';
        }
        if (str_contains($normalized, 'deny') || str_contains($normalized, 'reject') || str_contains($normalized, 'cancel')) {
            return 'Denied';
        }

        return 'Pending';
    }
}

if (!function_exists('resident_schedule_truncate')) {
    function resident_schedule_truncate(string $text, int $limit = 120): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '' || strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $limit - 1))) . '…';
    }
}

if (!function_exists('resident_schedule_parse_timestamp')) {
    function resident_schedule_parse_timestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $ts = strtotime($value);
        return $ts === false ? null : $ts;
    }
}

if (!function_exists('resident_schedule_contains_encrypted_marker')) {
    function resident_schedule_contains_encrypted_marker(string $value): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return false;
        }

        return str_contains($value, 'pii:v1:') || str_contains($value, 'pii:v2:');
    }
}

if (!function_exists('resident_schedule_format_person_name')) {
    function resident_schedule_format_person_name(string $firstName, string $middleName, string $lastName, string $suffix): string
    {
        $firstName = trim($firstName);
        $middleName = trim($middleName);
        $lastName = trim($lastName);
        $suffix = trim($suffix);

        foreach ([$firstName, $middleName, $lastName, $suffix] as $part) {
            if (resident_schedule_contains_encrypted_marker($part)) {
                return '';
            }
        }

        if ($middleName !== '') {
            $middleName = strtoupper(substr($middleName, 0, 1)) . '.';
        }

        return trim(implode(' ', array_values(array_filter([
            $firstName,
            $middleName,
            $lastName,
            $suffix,
        ], static function (string $part): bool {
            return $part !== '';
        }))));
    }
}

if (!function_exists('resident_schedule_extract_official_label')) {
    function resident_schedule_extract_official_label(array $row): string
    {
        if (function_exists('pii_decrypt_assoc')) {
            $row = pii_decrypt_assoc($row, [
                'official_firstname',
                'official_middlename',
                'official_lastname',
                'official_suffix',
            ]);
        }

        $seatName = trim((string)($row['official_seat_name'] ?? ''));
        if (resident_schedule_contains_encrypted_marker($seatName)) {
            $seatName = '';
        }

        $formattedName = resident_schedule_format_person_name(
            (string)($row['official_firstname'] ?? ''),
            (string)($row['official_middlename'] ?? ''),
            (string)($row['official_lastname'] ?? ''),
            (string)($row['official_suffix'] ?? '')
        );

        if ($formattedName !== '' && $seatName !== '') {
            return $formattedName . ' (' . $seatName . ')';
        }

        if ($formattedName !== '') {
            return $formattedName;
        }

        return $seatName;
    }
}

if (!function_exists('resident_schedule_resolved_status_name')) {
    function resident_schedule_resolved_status_name(string $statusName, string $confirmedScheduleTimestamp): string
    {
        $statusName = trim($statusName);
        if ($statusName !== '') {
            return $statusName;
        }

        return trim($confirmedScheduleTimestamp) !== '' ? 'Approved' : 'Pending';
    }
}

if (!function_exists('resident_schedule_collect_announcements')) {
    function resident_schedule_collect_announcements(array $viewerContext): array
    {
        $items = [];
        $announcements = announcements_load_all();

        foreach ($announcements as $item) {
            $channels = array_values(array_filter((array)($item['channels'] ?? []), static function ($channel): bool {
                return in_array((string)$channel, ['website', 'public', 'public_news', 'sms', 'email'], true);
            }));
            $status = strtolower(trim((string)($item['status'] ?? 'draft')));
            if ($status !== 'approved' || !in_array('website', $channels, true)) {
                continue;
            }
            if (!ann_audience_matches_viewer($item, $viewerContext)) {
                continue;
            }

            $rawDate = (string)($item['publish_date'] ?? '');
            if ($rawDate === '' || $rawDate === '-') {
                $rawDate = (string)($item['created_at'] ?? '');
            }
            $ts = resident_schedule_parse_timestamp($rawDate);
            if ($ts === null) {
                continue;
            }

            $title = trim((string)(($item['public_title'] ?? '') !== '' ? $item['public_title'] : ($item['title'] ?? '')));
            if ($title === '') {
                $title = 'Announcement';
            }

            $items[] = [
                'id' => 'announcement:' . (string)($item['id'] ?? md5($title . $rawDate)),
                'kind' => 'announcement',
                'kind_label' => 'Announcement',
                'date_iso' => date('Y-m-d', $ts),
                'datetime_iso' => date('Y-m-d H:i:s', $ts),
                'timestamp' => $ts,
                'is_upcoming' => $ts >= strtotime('today'),
                'title' => $title,
                'summary' => 'Posted on the resident announcement page',
                'meta' => 'Posted ' . date('M d, Y h:i A', $ts),
                'status_label' => 'Posted',
                'status_bucket' => 'info',
                'href' => 'Announcements/AnnouncementsLandingPage',
            ];
        }

        return $items;
    }
}

if (!function_exists('resident_schedule_collect_appointments')) {
    function resident_schedule_collect_appointments(mysqli $conn, string $residentUserId): array
    {
        if ($residentUserId === '' || !resident_schedule_table_exists($conn, 'appointmentstbl')) {
            return [];
        }

        $columns = resident_schedule_table_columns($conn, 'appointmentstbl');
        if (!isset($columns['user_id_resident'])) {
            return [];
        }

        $subjectSelect = isset($columns['subject']) ? 'a.subject' : "'Appointment'";
        $subjectOtherSelect = isset($columns['subject_other']) ? 'a.subject_other' : "''";
        $purposeSelect = isset($columns['purpose']) ? 'a.purpose' : "''";
        $preferredScheduleSelect = isset($columns['preferred_schedule_timestamp'])
            ? 'a.preferred_schedule_timestamp'
            : (isset($columns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
        $confirmedScheduleSelect = isset($columns['confirmed_schedule_timestamp'])
            ? 'a.confirmed_schedule_timestamp'
            : 'NULL';
        $requestTimestampSelect = isset($columns['request_timestamp']) ? 'a.request_timestamp' : 'NULL';
        $statusJoin = resident_schedule_table_exists($conn, 'statuslookuptbl')
            ? "LEFT JOIN statuslookuptbl s ON a.appointment_status_id = s.status_id"
            : '';
        $statusSelect = resident_schedule_table_exists($conn, 'statuslookuptbl')
            ? "NULLIF(TRIM(s.status_name), '') AS status_name"
            : "NULL AS status_name";

        $officialJoin = '';
        $officialFirstNameSelect = "'' AS official_firstname";
        $officialMiddleNameSelect = "'' AS official_middlename";
        $officialLastNameSelect = "'' AS official_lastname";
        $officialSuffixSelect = "'' AS official_suffix";
        $officialSeatNameSelect = "'' AS official_seat_name";
        if (isset($columns['user_id_official_assigned']) && resident_schedule_table_exists($conn, 'officialinformationtbl')) {
            $officialJoin = "
                LEFT JOIN officialinformationtbl oi
                    ON a.user_id_official_assigned COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            ";
            $officialFirstNameSelect = "oi.firstname AS official_firstname";
            $officialMiddleNameSelect = "oi.middlename AS official_middlename";
            $officialLastNameSelect = "oi.lastname AS official_lastname";
            $officialSuffixSelect = "oi.suffix AS official_suffix";

            if (resident_schedule_table_exists($conn, 'barangaycounciltbl')) {
                $officialJoin .= "
                    LEFT JOIN barangaycounciltbl bc
                        ON bc.current_official_id = oi.official_id
                       AND bc.is_active = 1
                ";
                $officialSeatNameSelect = "NULLIF(bc.seat_name, '') AS official_seat_name";
            }
        }

        $sql = "
            SELECT
                a.appointment_id,
                {$subjectSelect} AS subject,
                {$subjectOtherSelect} AS subject_other,
                {$purposeSelect} AS purpose,
                {$preferredScheduleSelect} AS preferred_schedule_timestamp,
                {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
                {$requestTimestampSelect} AS request_timestamp,
                {$statusSelect},
                {$officialFirstNameSelect},
                {$officialMiddleNameSelect},
                {$officialLastNameSelect},
                {$officialSuffixSelect},
                {$officialSeatNameSelect}
            FROM appointmentstbl a
            {$statusJoin}
            {$officialJoin}
            WHERE a.user_id_resident = ?
            ORDER BY COALESCE({$confirmedScheduleSelect}, {$preferredScheduleSelect}, {$requestTimestampSelect}) ASC, a.appointment_id DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $residentUserId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $scheduleRaw = trim((string)($row['confirmed_schedule_timestamp'] ?? ''));
            if ($scheduleRaw === '') {
                $scheduleRaw = trim((string)($row['preferred_schedule_timestamp'] ?? ''));
            }
            $ts = resident_schedule_parse_timestamp($scheduleRaw);
            if ($ts === null) {
                continue;
            }

            $subject = trim((string)($row['subject'] ?? 'Appointment'));
            $subjectOther = trim((string)($row['subject_other'] ?? ''));
            if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
                $subject = 'Other: ' . $subjectOther;
            }
            if ($subject === '') {
                $subject = 'Barangay appointment';
            }

            $purpose = trim((string)($row['purpose'] ?? ''));
            $officialName = resident_schedule_extract_official_label($row);
            $statusName = resident_schedule_resolved_status_name(
                (string)($row['status_name'] ?? ''),
                (string)($row['confirmed_schedule_timestamp'] ?? '')
            );
            $statusLabel = resident_schedule_status_label($statusName);
            $scheduleContext = trim((string)($row['confirmed_schedule_timestamp'] ?? '')) !== '' ? 'Confirmed' : 'Requested';

            $title = $subject;
            $summaryParts = [];
            if ($officialName !== '') {
                $summaryParts[] = 'With ' . $officialName;
            }
            if ($purpose !== '') {
                $summaryParts[] = resident_schedule_truncate($purpose, 82);
            }
            $summary = implode(' · ', $summaryParts);
            if ($summary === '') {
                $summary = 'Resident appointment record';
            }

            $items[] = [
                'id' => 'appointment:' . (string)($row['appointment_id'] ?? md5($title . $scheduleRaw)),
                'kind' => 'appointment',
                'kind_label' => 'Appointment',
                'date_iso' => date('Y-m-d', $ts),
                'datetime_iso' => date('Y-m-d H:i:s', $ts),
                'timestamp' => $ts,
                'is_upcoming' => $ts >= strtotime('today'),
                'title' => $title,
                'summary' => $summary,
                'meta' => $scheduleContext . ' schedule · ' . date('M d, Y h:i A', $ts),
                'status_label' => $statusLabel,
                'status_bucket' => resident_schedule_status_bucket($statusName),
                'href' => 'appointment_tracker',
            ];
        }
        $stmt->close();

        return $items;
    }
}

if (!function_exists('resident_schedule_collect_all')) {
    function resident_schedule_collect_all(mysqli $conn, string $residentUserId, array $viewerContext): array
    {
        $items = array_merge(
            resident_schedule_collect_appointments($conn, $residentUserId),
            resident_schedule_collect_announcements($viewerContext)
        );

        usort($items, static function (array $a, array $b): int {
            $aTs = (int)($a['timestamp'] ?? 0);
            $bTs = (int)($b['timestamp'] ?? 0);
            if ($aTs === $bTs) {
                return strcmp((string)($a['kind'] ?? ''), (string)($b['kind'] ?? ''));
            }
            return $aTs <=> $bTs;
        });

        return $items;
    }
}

if (!function_exists('resident_schedule_build_dashboard_items')) {
    function resident_schedule_build_dashboard_items(array $items, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $upcoming = array_values(array_filter($items, static function (array $item): bool {
            return !empty($item['is_upcoming']);
        }));

        usort($upcoming, static function (array $a, array $b): int {
            return ((int)($a['timestamp'] ?? 0)) <=> ((int)($b['timestamp'] ?? 0));
        });

        $selected = array_slice($upcoming, 0, $limit);
        if (count($selected) >= $limit) {
            return $selected;
        }

        $selectedIds = array_fill_keys(array_map(static function (array $item): string {
            return (string)($item['id'] ?? '');
        }, $selected), true);

        $recentFallback = array_values(array_filter($items, static function (array $item) use ($selectedIds): bool {
            $id = (string)($item['id'] ?? '');
            return $id !== '' && !isset($selectedIds[$id]);
        }));

        usort($recentFallback, static function (array $a, array $b): int {
            return ((int)($b['timestamp'] ?? 0)) <=> ((int)($a['timestamp'] ?? 0));
        });

        return array_slice(array_merge($selected, $recentFallback), 0, $limit);
    }
}
