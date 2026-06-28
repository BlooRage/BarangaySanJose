<?php

require_once __DIR__ . '/appointmentSettings.php';

if (!function_exists('apos_schedule_table_name')) {
    function apos_schedule_table_name(): string
    {
        return 'appointmentofficialscheduletbl';
    }
}

if (!function_exists('apos_appointment_table_name')) {
    function apos_appointment_table_name(): string
    {
        return 'appointmentstbl';
    }
}

if (!function_exists('apos_schedule_ensure_storage')) {
    function apos_schedule_ensure_storage(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $table = apos_schedule_table_name();
        $created = $conn->query("
            CREATE TABLE IF NOT EXISTS {$table} (
                user_id VARCHAR(20) NOT NULL,
                weekday TINYINT UNSIGNED NOT NULL,
                is_available TINYINT(1) NOT NULL DEFAULT 0,
                start_time TIME DEFAULT NULL,
                end_time TIME DEFAULT NULL,
                meeting_location VARCHAR(255) DEFAULT NULL,
                updated_by_user_id VARCHAR(20) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, weekday),
                KEY idx_appointmentofficialschedule_updated_by (updated_by_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        if ($created === false) {
            throw new RuntimeException('Failed to prepare official appointment schedules.');
        }

        if ($conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string(apos_appointment_table_name()) . "'") instanceof mysqli_result) {
            @$conn->query("
                ALTER TABLE " . apos_appointment_table_name() . "
                ADD COLUMN IF NOT EXISTS meeting_location VARCHAR(255) NULL AFTER appointment_remarks
            ");
        }

        $done = true;
    }
}

if (!function_exists('apos_normalize_location')) {
    function apos_normalize_location($value): string
    {
        $location = trim((string)$value);
        if ($location === '') {
            return '';
        }

        $location = preg_replace('/\s+/', ' ', $location) ?: $location;
        if (function_exists('mb_substr')) {
            return trim((string)mb_substr($location, 0, 255));
        }

        return trim((string)substr($location, 0, 255));
    }
}

if (!function_exists('apos_default_daily_schedule')) {
    function apos_default_daily_schedule(array $globalSettings, int $weekday): array
    {
        $availableWeekdays = array_fill_keys(aps_normalize_weekdays($globalSettings['available_weekdays'] ?? []), true);

        return [
            'weekday' => $weekday,
            'enabled' => isset($availableWeekdays[$weekday]),
            'start_time' => aps_schedule_start_time(),
            'end_time' => aps_schedule_end_time(),
            'meeting_location' => '',
        ];
    }
}

if (!function_exists('apos_weekly_schedule_for_user')) {
    function apos_weekly_schedule_for_user(mysqli $conn, string $userId, array $globalSettings = []): array
    {
        apos_schedule_ensure_storage($conn);

        $normalizedUserId = trim($userId);
        $settings = $globalSettings !== [] ? aps_normalize_settings($globalSettings) : aps_settings_load($conn);
        $schedule = [];
        foreach (array_keys(aps_weekday_options()) as $weekday) {
            $schedule[(int)$weekday] = apos_default_daily_schedule($settings, (int)$weekday);
        }

        if ($normalizedUserId === '') {
            return $schedule;
        }

        $stmt = $conn->prepare("
            SELECT weekday, is_available, start_time, end_time, meeting_location
            FROM " . apos_schedule_table_name() . "
            WHERE user_id = ?
        ");
        if (!$stmt) {
            return $schedule;
        }

        $stmt->bind_param('s', $normalizedUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $weekday = isset($row['weekday']) ? (int)$row['weekday'] : -1;
            if (!array_key_exists($weekday, $schedule)) {
                continue;
            }

            $schedule[$weekday] = [
                'weekday' => $weekday,
                'enabled' => (int)($row['is_available'] ?? 0) === 1,
                'start_time' => aps_normalize_time_value($row['start_time'] ?? '', aps_schedule_start_time()),
                'end_time' => aps_normalize_time_value($row['end_time'] ?? '', aps_schedule_end_time()),
                'meeting_location' => apos_normalize_location($row['meeting_location'] ?? ''),
            ];
        }
        $stmt->close();

        ksort($schedule, SORT_NUMERIC);
        return $schedule;
    }
}

if (!function_exists('apos_fetch_schedule_map')) {
    function apos_fetch_schedule_map(mysqli $conn, array $userIds, array $globalSettings = []): array
    {
        $map = [];
        $settings = $globalSettings !== [] ? aps_normalize_settings($globalSettings) : aps_settings_load($conn);
        $normalizedUserIds = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $userIds), static function (string $value): bool {
            return $value !== '';
        })));

        foreach ($normalizedUserIds as $userId) {
            $map[$userId] = apos_weekly_schedule_for_user($conn, $userId, $settings);
        }

        return $map;
    }
}

if (!function_exists('apos_validate_weekly_schedule_entries')) {
    function apos_validate_weekly_schedule_entries(array $entries, array $globalSettings = []): array
    {
        $settings = aps_normalize_settings($globalSettings);
        $normalized = [];
        $minStart = aps_schedule_start_time();
        $maxEnd = aps_schedule_end_time();
        $allowedLocations = aps_normalize_location_options($settings['meeting_locations'] ?? []);

        foreach (array_keys(aps_weekday_options()) as $weekday) {
            $weekdayKey = (int)$weekday;
            $entry = isset($entries[$weekdayKey]) && is_array($entries[$weekdayKey]) ? $entries[$weekdayKey] : [];
            $enabled = isset($entry['enabled']) && in_array(strtolower(trim((string)$entry['enabled'])), ['1', 'true', 'yes', 'on'], true);
            $startTime = aps_normalize_time_value($entry['start_time'] ?? '', '');
            $endTime = aps_normalize_time_value($entry['end_time'] ?? '', '');
            $location = apos_normalize_location($entry['meeting_location'] ?? '');

            if ($enabled) {
                if ($startTime === '' || $endTime === '') {
                    throw new RuntimeException('Start and end times are required for every available appointment day.');
                }
                if ($startTime >= $endTime) {
                    throw new RuntimeException('Appointment day end time must be after the start time.');
                }
                if ($startTime < $minStart || $endTime > $maxEnd) {
                    throw new RuntimeException('Official schedules must stay within the appointment office coverage.');
                }
                if ($location === '') {
                    throw new RuntimeException('Meeting location is required for every available appointment day.');
                }
                if ($allowedLocations !== [] && !in_array($location, $allowedLocations, true)) {
                    throw new RuntimeException('Meeting location must be selected from the saved appointment settings list.');
                }
            } else {
                $startTime = aps_schedule_start_time();
                $endTime = aps_schedule_end_time();
                $location = '';
            }

            $normalized[$weekdayKey] = [
                'weekday' => $weekdayKey,
                'enabled' => $enabled,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'meeting_location' => $location,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('apos_save_weekly_schedule')) {
    function apos_save_weekly_schedule(mysqli $conn, string $userId, array $entries, string $updatedByUserId = '', array $globalSettings = []): array
    {
        apos_schedule_ensure_storage($conn);

        $normalizedUserId = trim($userId);
        if ($normalizedUserId === '') {
            throw new RuntimeException('Official user ID is required for appointment schedules.');
        }

        $normalizedEntries = apos_validate_weekly_schedule_entries($entries, $globalSettings !== [] ? $globalSettings : aps_settings_load($conn));

        $stmt = $conn->prepare("
            INSERT INTO " . apos_schedule_table_name() . " (
                user_id,
                weekday,
                is_available,
                start_time,
                end_time,
                meeting_location,
                updated_by_user_id
            ) VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                is_available = VALUES(is_available),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                meeting_location = VALUES(meeting_location),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save official appointment schedules.');
        }

        foreach ($normalizedEntries as $entry) {
            $weekday = (int)$entry['weekday'];
            $isAvailable = $entry['enabled'] ? 1 : 0;
            $startTime = (string)$entry['start_time'];
            $endTime = (string)$entry['end_time'];
            $meetingLocation = (string)$entry['meeting_location'];
            $stmt->bind_param(
                'siissss',
                $normalizedUserId,
                $weekday,
                $isAvailable,
                $startTime,
                $endTime,
                $meetingLocation,
                $updatedByUserId
            );
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to save official appointment schedules.');
            }
        }
        $stmt->close();

        return $normalizedEntries;
    }
}

if (!function_exists('apos_generate_slots_for_window')) {
    function apos_generate_slots_for_window(string $startTime, string $endTime, array $globalSettings): array
    {
        $normalizedSettings = aps_normalize_settings($globalSettings);
        $intervalMinutes = (int)($normalizedSettings['slot_interval_minutes'] ?? 30);
        $lunchBreak = aps_lunch_break_window($normalizedSettings);

        $slots = [];
        $current = new DateTimeImmutable($startTime);
        $last = new DateTimeImmutable($endTime);

        while ($current <= $last) {
            $value = $current->format('H:i');
            $slotEnd = $current->modify('+' . $intervalMinutes . ' minutes');
            if (
                $lunchBreak !== null
                && $current < new DateTimeImmutable((string)$lunchBreak['end'])
                && $slotEnd > new DateTimeImmutable((string)$lunchBreak['start'])
            ) {
                $current = $slotEnd;
                continue;
            }

            $slots[$value] = $current->format('h:i A');
            $current = $slotEnd;
        }

        return $slots;
    }
}

if (!function_exists('apos_effective_schedule_for_user_date')) {
    function apos_effective_schedule_for_user_date(mysqli $conn, string $userId, string $isoDate, array $globalSettings = []): ?array
    {
        apos_schedule_ensure_storage($conn);

        $normalizedUserId = trim($userId);
        if ($normalizedUserId === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
            return null;
        }

        $settings = $globalSettings !== [] ? aps_normalize_settings($globalSettings) : aps_settings_load($conn);
        if (!aps_is_date_available($settings, $isoDate)) {
            return null;
        }

        $timestamp = strtotime($isoDate);
        if ($timestamp === false) {
            return null;
        }

        $weekday = (int)date('w', $timestamp);
        $weeklySchedule = apos_weekly_schedule_for_user($conn, $normalizedUserId, $settings);
        $daySchedule = $weeklySchedule[$weekday] ?? null;
        if (!$daySchedule || empty($daySchedule['enabled'])) {
            return null;
        }

        $startTime = max((string)$daySchedule['start_time'], aps_schedule_start_time());
        $endTime = min((string)$daySchedule['end_time'], aps_schedule_end_time());
        if ($startTime >= $endTime) {
            return null;
        }

        $slots = apos_generate_slots_for_window($startTime, $endTime, $settings);
        if ($slots === []) {
            return null;
        }

        return [
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'meeting_location' => apos_normalize_location($daySchedule['meeting_location'] ?? ''),
            'slots' => $slots,
        ];
    }
}

if (!function_exists('apos_time_is_available_for_user_date')) {
    function apos_time_is_available_for_user_date(mysqli $conn, string $userId, string $isoDate, string $time, array $globalSettings = []): bool
    {
        $time = aps_normalize_time_value($time, '');
        if ($time === '') {
            return false;
        }

        $effective = apos_effective_schedule_for_user_date($conn, $userId, $isoDate, $globalSettings);
        if ($effective === null) {
            return false;
        }

        return array_key_exists($time, (array)($effective['slots'] ?? []));
    }
}
