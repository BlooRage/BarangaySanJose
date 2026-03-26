<?php

require_once __DIR__ . '/appointmentSettings.php';

if (!function_exists('ats_allotted_times')) {
    function ats_allotted_times(array $settings = []): array
    {
        static $cache = [];

        $normalized = aps_normalize_settings($settings);
        $startTime = aps_schedule_start_time();
        $endTime = aps_schedule_end_time();
        $intervalMinutes = (int)($normalized['slot_interval_minutes'] ?? 30);
        $lunchBreak = aps_lunch_break_window($normalized);
        $cacheKey = implode('|', [
            $startTime,
            $endTime,
            $intervalMinutes,
            (string)($lunchBreak['start'] ?? ''),
            (string)($lunchBreak['end'] ?? ''),
        ]);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

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

        $cache[$cacheKey] = $slots;
        return $slots;
    }
}

if (!function_exists('ats_is_valid_time')) {
    function ats_is_valid_time(string $time, array $settings = []): bool
    {
        $time = trim($time);
        if ($time === '') {
            return false;
        }

        return array_key_exists($time, ats_allotted_times($settings));
    }
}
