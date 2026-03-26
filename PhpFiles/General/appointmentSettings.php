<?php

if (!function_exists('aps_settings_definitions')) {
    function aps_settings_definitions(): array
    {
        return [
            'slot_interval_minutes' => [
                'label' => 'Time allotment per appointment',
                'description' => 'Length of each appointment slot in minutes.',
                'min' => 10,
                'max' => 180,
                'step' => 5,
                'default' => 30,
            ],
            'booking_window_days' => [
                'label' => 'Maximum days ahead for booking',
                'description' => 'How many days ahead residents can schedule an appointment, still capped within the current year.',
                'min' => 1,
                'max' => 365,
                'step' => 1,
                'default' => 365,
            ],
            'available_weekdays' => [
                'label' => 'Available weekdays',
                'description' => 'Days when appointments may be scheduled.',
                'default' => '0,1,2,3,4,5,6',
            ],
            'lunch_break_enabled' => [
                'label' => 'Lunch break',
                'description' => 'Block appointments during lunch hours.',
                'default' => '0',
            ],
            'lunch_start_time' => [
                'label' => 'Lunch break start',
                'description' => 'Start time of the lunch break.',
                'default' => '12:00',
            ],
            'lunch_end_time' => [
                'label' => 'Lunch break end',
                'description' => 'End time of the lunch break.',
                'default' => '13:00',
            ],
            'unavailable_dates' => [
                'label' => 'Unavailable dates',
                'description' => 'Specific dates that cannot be booked.',
                'default' => '',
            ],
        ];
    }
}

if (!function_exists('aps_settings_ensure_table')) {
    function aps_settings_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $created = $conn->query("
            CREATE TABLE IF NOT EXISTS appointmentsettingstbl (
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NOT NULL,
                updated_by_user_id VARCHAR(20) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        if ($created === false) {
            throw new RuntimeException('Failed to prepare appointment settings storage.');
        }

        $altered = $conn->query("ALTER TABLE appointmentsettingstbl MODIFY setting_value TEXT NOT NULL");
        if ($altered === false) {
            throw new RuntimeException('Failed to update appointment settings storage.');
        }

        $done = true;
    }
}

if (!function_exists('aps_weekday_options')) {
    function aps_weekday_options(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }
}

if (!function_exists('aps_weekday_short_options')) {
    function aps_weekday_short_options(): array
    {
        return [
            0 => 'S',
            1 => 'M',
            2 => 'T',
            3 => 'W',
            4 => 'T',
            5 => 'F',
            6 => 'S',
        ];
    }
}

if (!function_exists('aps_normalize_weekdays')) {
    function aps_normalize_weekdays($value): array
    {
        $source = is_array($value) ? $value : explode(',', (string)$value);
        $normalized = [];
        foreach ($source as $item) {
            $weekday = filter_var(trim((string)$item), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => 6],
            ]);
            if ($weekday === false) {
                continue;
            }
            $normalized[(int)$weekday] = true;
        }

        if ($normalized === []) {
            foreach (array_keys(aps_weekday_options()) as $weekday) {
                $normalized[(int)$weekday] = true;
            }
        }

        $weekdays = array_keys($normalized);
        sort($weekdays, SORT_NUMERIC);
        return $weekdays;
    }
}

if (!function_exists('aps_normalize_time_value')) {
    function aps_normalize_time_value($value, string $default = ''): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return $default;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $matches)) {
            return $default;
        }

        $hour = (int)($matches[1] ?? 0);
        $minute = (int)($matches[2] ?? 0);
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return $default;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}

if (!function_exists('aps_normalize_unavailable_dates')) {
    function aps_normalize_unavailable_dates($value): array
    {
        $source = is_array($value) ? $value : explode(',', (string)$value);
        $normalized = [];

        foreach ($source as $item) {
            $isoDate = trim((string)$item);
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) {
                continue;
            }

            $year = (int)($matches[1] ?? 0);
            $month = (int)($matches[2] ?? 0);
            $day = (int)($matches[3] ?? 0);
            if (!checkdate($month, $day, $year)) {
                continue;
            }

            $normalized[$isoDate] = true;
        }

        $dates = array_keys($normalized);
        sort($dates, SORT_STRING);
        return $dates;
    }
}

if (!function_exists('aps_settings_defaults')) {
    function aps_settings_defaults(): array
    {
        $definitions = aps_settings_definitions();
        return [
            'slot_interval_minutes' => (int)($definitions['slot_interval_minutes']['default'] ?? 30),
            'booking_window_days' => (int)($definitions['booking_window_days']['default'] ?? 365),
            'available_weekdays' => aps_normalize_weekdays((string)($definitions['available_weekdays']['default'] ?? '0,1,2,3,4,5,6')),
            'lunch_break_enabled' => (int)((string)($definitions['lunch_break_enabled']['default'] ?? '0') === '1'),
            'lunch_start_time' => aps_normalize_time_value((string)($definitions['lunch_start_time']['default'] ?? '12:00'), '12:00'),
            'lunch_end_time' => aps_normalize_time_value((string)($definitions['lunch_end_time']['default'] ?? '13:00'), '13:00'),
            'unavailable_dates' => aps_normalize_unavailable_dates((string)($definitions['unavailable_dates']['default'] ?? '')),
        ];
    }
}

if (!function_exists('aps_normalize_settings')) {
    function aps_normalize_settings(array $settings): array
    {
        $definitions = aps_settings_definitions();
        $defaults = aps_settings_defaults();

        $slotInterval = (int)($settings['slot_interval_minutes'] ?? $defaults['slot_interval_minutes']);
        $slotInterval = max(
            (int)($definitions['slot_interval_minutes']['min'] ?? 10),
            min((int)($definitions['slot_interval_minutes']['max'] ?? 180), $slotInterval)
        );

        $bookingWindow = (int)($settings['booking_window_days'] ?? $defaults['booking_window_days']);
        $bookingWindow = max(
            (int)($definitions['booking_window_days']['min'] ?? 1),
            min((int)($definitions['booking_window_days']['max'] ?? 365), $bookingWindow)
        );

        $lunchEnabledRaw = strtolower(trim((string)($settings['lunch_break_enabled'] ?? $defaults['lunch_break_enabled'])));
        $lunchBreakEnabled = in_array($lunchEnabledRaw, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        $lunchStartTime = aps_normalize_time_value($settings['lunch_start_time'] ?? $defaults['lunch_start_time'], (string)$defaults['lunch_start_time']);
        $lunchEndTime = aps_normalize_time_value($settings['lunch_end_time'] ?? $defaults['lunch_end_time'], (string)$defaults['lunch_end_time']);
        if ($lunchStartTime === '' || $lunchEndTime === '' || $lunchStartTime >= $lunchEndTime) {
            $lunchBreakEnabled = 0;
            $lunchStartTime = (string)$defaults['lunch_start_time'];
            $lunchEndTime = (string)$defaults['lunch_end_time'];
        }

        return [
            'slot_interval_minutes' => $slotInterval,
            'booking_window_days' => $bookingWindow,
            'available_weekdays' => aps_normalize_weekdays($settings['available_weekdays'] ?? $defaults['available_weekdays']),
            'lunch_break_enabled' => $lunchBreakEnabled,
            'lunch_start_time' => $lunchStartTime,
            'lunch_end_time' => $lunchEndTime,
            'unavailable_dates' => aps_normalize_unavailable_dates($settings['unavailable_dates'] ?? $defaults['unavailable_dates']),
        ];
    }
}

if (!function_exists('aps_settings_load')) {
    function aps_settings_load(mysqli $conn): array
    {
        aps_settings_ensure_table($conn);

        $settings = aps_settings_defaults();
        $result = $conn->query("SELECT setting_key, setting_value FROM appointmentsettingstbl");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $key = (string)($row['setting_key'] ?? '');
                $value = (string)($row['setting_value'] ?? '');
                if (!array_key_exists($key, $settings)) {
                    continue;
                }
                $settings[$key] = $value;
            }
            $result->close();
        }

        return aps_normalize_settings($settings);
    }
}

if (!function_exists('aps_settings_upsert')) {
    function aps_settings_upsert(mysqli $conn, array $settings, string $updatedByUserId = ''): void
    {
        aps_settings_ensure_table($conn);

        $normalized = aps_normalize_settings($settings);
        $savePayload = [
            'slot_interval_minutes' => (string)$normalized['slot_interval_minutes'],
            'booking_window_days' => (string)$normalized['booking_window_days'],
            'available_weekdays' => implode(',', $normalized['available_weekdays']),
            'lunch_break_enabled' => (string)(int)($normalized['lunch_break_enabled'] ?? 0),
            'lunch_start_time' => (string)($normalized['lunch_start_time'] ?? ''),
            'lunch_end_time' => (string)($normalized['lunch_end_time'] ?? ''),
            'unavailable_dates' => implode(',', $normalized['unavailable_dates'] ?? []),
        ];

        $stmt = $conn->prepare("
            INSERT INTO appointmentsettingstbl (setting_key, setting_value, updated_by_user_id)
            VALUES (?, ?, NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save appointment settings.');
        }

        foreach ($savePayload as $key => $value) {
            $stmt->bind_param('sss', $key, $value, $updatedByUserId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to save appointment settings.');
            }
        }
        $stmt->close();
    }
}

if (!function_exists('aps_schedule_start_time')) {
    function aps_schedule_start_time(): string
    {
        return '09:00';
    }
}

if (!function_exists('aps_schedule_end_time')) {
    function aps_schedule_end_time(): string
    {
        return '16:30';
    }
}

if (!function_exists('aps_weekdays_label')) {
    function aps_weekdays_label(array $weekdays): string
    {
        $options = aps_weekday_options();
        $labels = [];
        foreach (aps_normalize_weekdays($weekdays) as $weekday) {
            if (isset($options[$weekday])) {
                $labels[] = $options[$weekday];
            }
        }

        return $labels !== [] ? implode(', ', $labels) : 'No available days selected';
    }
}

if (!function_exists('aps_has_lunch_break')) {
    function aps_has_lunch_break(array $settings): bool
    {
        $normalized = aps_normalize_settings($settings);
        return (int)($normalized['lunch_break_enabled'] ?? 0) === 1
            && trim((string)($normalized['lunch_start_time'] ?? '')) !== ''
            && trim((string)($normalized['lunch_end_time'] ?? '')) !== ''
            && (string)($normalized['lunch_start_time'] ?? '') < (string)($normalized['lunch_end_time'] ?? '');
    }
}

if (!function_exists('aps_lunch_break_window')) {
    function aps_lunch_break_window(array $settings): ?array
    {
        $normalized = aps_normalize_settings($settings);
        if (!aps_has_lunch_break($normalized)) {
            return null;
        }

        return [
            'start' => (string)($normalized['lunch_start_time'] ?? ''),
            'end' => (string)($normalized['lunch_end_time'] ?? ''),
        ];
    }
}

if (!function_exists('aps_lunch_break_label')) {
    function aps_lunch_break_label(array $settings): string
    {
        $window = aps_lunch_break_window($settings);
        if ($window === null) {
            return 'No lunch break configured';
        }

        $startLabel = date('h:i A', strtotime((string)$window['start']));
        $endLabel = date('h:i A', strtotime((string)$window['end']));
        return $startLabel . ' to ' . $endLabel;
    }
}

if (!function_exists('aps_format_date_label')) {
    function aps_format_date_label(string $isoDate, string $fallback = ''): string
    {
        $timestamp = strtotime($isoDate);
        if ($timestamp === false) {
            return $fallback !== '' ? $fallback : $isoDate;
        }

        return date('M d, Y', $timestamp);
    }
}

if (!function_exists('aps_unavailable_dates_label')) {
    function aps_unavailable_dates_label(array $dates, int $limit = 6): string
    {
        $normalizedDates = aps_normalize_unavailable_dates($dates);
        if ($normalizedDates === []) {
            return 'None';
        }

        $labels = array_map(
            static fn(string $isoDate): string => aps_format_date_label($isoDate),
            array_slice($normalizedDates, 0, max(1, $limit))
        );

        if (count($normalizedDates) > $limit) {
            $remaining = count($normalizedDates) - $limit;
            $labels[] = '+' . $remaining . ' more';
        }

        return implode(', ', $labels);
    }
}

if (!function_exists('aps_disabled_weekdays')) {
    function aps_disabled_weekdays(array $settings): array
    {
        $allowed = array_fill_keys(aps_normalize_weekdays($settings['available_weekdays'] ?? []), true);
        $disabled = [];
        foreach (array_keys(aps_weekday_options()) as $weekday) {
            if (!isset($allowed[$weekday])) {
                $disabled[] = (int)$weekday;
            }
        }
        return $disabled;
    }
}

if (!function_exists('aps_is_date_unavailable')) {
    function aps_is_date_unavailable(array $settings, string $isoDate): bool
    {
        return in_array($isoDate, aps_normalize_unavailable_dates($settings['unavailable_dates'] ?? []), true);
    }
}

if (!function_exists('aps_is_weekday_available')) {
    function aps_is_weekday_available(array $settings, string $isoDate): bool
    {
        $timestamp = strtotime($isoDate);
        if ($timestamp === false) {
            return false;
        }

        $weekday = (int)date('w', $timestamp);
        return in_array($weekday, aps_normalize_weekdays($settings['available_weekdays'] ?? []), true);
    }
}

if (!function_exists('aps_is_date_available')) {
    function aps_is_date_available(array $settings, string $isoDate): bool
    {
        return aps_is_weekday_available($settings, $isoDate)
            && !aps_is_date_unavailable($settings, $isoDate);
    }
}

if (!function_exists('aps_booking_date_limits')) {
    function aps_booking_date_limits(array $settings, ?DateTimeImmutable $now = null): array
    {
        $timezone = $now?->getTimezone() ?? new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
        $baseNow = $now ?? new DateTimeImmutable('now', $timezone);
        $normalized = aps_normalize_settings($settings);

        $minDate = $baseNow->modify('+1 day');
        $windowMax = $baseNow->modify('+' . (int)$normalized['booking_window_days'] . ' days');
        $yearEnd = new DateTimeImmutable($baseNow->format('Y-12-31'), $timezone);
        $maxDate = $windowMax < $yearEnd ? $windowMax : $yearEnd;

        return [
            'min_date' => $minDate->format('Y-m-d'),
            'max_date' => $maxDate->format('Y-m-d'),
            'has_window' => $maxDate >= $minDate,
        ];
    }
}

if (!function_exists('aps_first_available_booking_date')) {
    function aps_first_available_booking_date(array $settings, ?DateTimeImmutable $now = null): ?string
    {
        $limits = aps_booking_date_limits($settings, $now);
        if (empty($limits['has_window'])) {
            return null;
        }

        $timezone = $now?->getTimezone() ?? new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
        $cursor = new DateTimeImmutable((string)$limits['min_date'], $timezone);
        $end = new DateTimeImmutable((string)$limits['max_date'], $timezone);

        while ($cursor <= $end) {
            $isoDate = $cursor->format('Y-m-d');
            if (aps_is_date_available($settings, $isoDate)) {
                return $isoDate;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return null;
    }
}
