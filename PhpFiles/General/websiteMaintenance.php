<?php

if (!function_exists('wms_flag_path')) {
    function wms_flag_path(): string
    {
        return dirname(__DIR__, 2) . '/.maintenance-mode.flag';
    }
}

if (!function_exists('wms_settings_path')) {
    function wms_settings_path(): string
    {
        return dirname(__DIR__, 2) . '/.maintenance-settings.json';
    }
}

if (!function_exists('wms_default_settings')) {
    function wms_default_settings(): array
    {
        return [
            'enabled' => false,
            'message' => 'Our developers are currently upgrading the system to deliver a smoother, faster, and better experience for everyone.',
            'subcopy' => 'The public pages will be available again once the improvements are complete.',
            'registration_enabled' => true,
            'registration_message' => 'Online resident registration is temporarily unavailable. Please contact the barangay office for assistance.',
            'resident_timeout_minutes' => 30,
            'admin_timeout_minutes' => 30,
            'admin_2fa_enabled' => false,
            'default_language' => 'en',
            'default_font_scale' => '100',
            'high_contrast' => false,
            'reduced_motion' => false,
            'updated_by_user_id' => '',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('wms_ensure_database_storage')) {
    function wms_ensure_database_storage(mysqli $conn): bool
    {
        return (bool)$conn->query("
            CREATE TABLE IF NOT EXISTS websitesettingstbl (
                setting_id TINYINT UNSIGNED NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                maintenance_message TEXT NOT NULL,
                maintenance_subcopy TEXT NOT NULL,
                registration_enabled TINYINT(1) NOT NULL DEFAULT 1,
                registration_message VARCHAR(400) NOT NULL DEFAULT '',
                resident_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
                admin_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
                admin_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
                default_language VARCHAR(5) NOT NULL DEFAULT 'en',
                default_font_scale VARCHAR(4) NOT NULL DEFAULT '100',
                high_contrast TINYINT(1) NOT NULL DEFAULT 0,
                reduced_motion TINYINT(1) NOT NULL DEFAULT 0,
                updated_by_user_id VARCHAR(20) NULL,
                updated_at VARCHAR(40) NULL,
                PRIMARY KEY (setting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('wms_ensure_extended_columns')) {
    function wms_ensure_extended_columns(mysqli $conn): void
    {
        $columns = [
            'registration_enabled' => "TINYINT(1) NOT NULL DEFAULT 1",
            'registration_message' => "VARCHAR(400) NOT NULL DEFAULT ''",
            'resident_timeout_minutes' => "SMALLINT UNSIGNED NOT NULL DEFAULT 30",
            'admin_timeout_minutes' => "SMALLINT UNSIGNED NOT NULL DEFAULT 30",
            'admin_2fa_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
            'default_language' => "VARCHAR(5) NOT NULL DEFAULT 'en'",
            'default_font_scale' => "VARCHAR(4) NOT NULL DEFAULT '100'",
            'high_contrast' => "TINYINT(1) NOT NULL DEFAULT 0",
            'reduced_motion' => "TINYINT(1) NOT NULL DEFAULT 0",
        ];
        $existing = [];
        $result = $conn->query("SHOW COLUMNS FROM websitesettingstbl");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existing[(string)($row['Field'] ?? '')] = true;
            }
            $result->free();
        }
        foreach ($columns as $name => $definition) {
            if (!isset($existing[$name])) {
                $conn->query("ALTER TABLE websitesettingstbl ADD COLUMN {$name} {$definition}");
            }
        }
    }
}

if (!function_exists('wms_load_database_settings')) {
    function wms_load_database_settings(mysqli $conn): ?array
    {
        if (!wms_ensure_database_storage($conn)) {
            return null;
        }
        wms_ensure_extended_columns($conn);

        $result = $conn->query("
            SELECT enabled, maintenance_message, maintenance_subcopy,
                   registration_enabled, registration_message, resident_timeout_minutes,
                   admin_timeout_minutes, admin_2fa_enabled, default_language,
                   default_font_scale, high_contrast, reduced_motion,
                   updated_by_user_id, updated_at
            FROM websitesettingstbl
            WHERE setting_id = 1
            LIMIT 1
        ");
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();
        if (!is_array($row)) {
            // One-time migration for installations that used the original
            // file-only implementation. The audit trail retains the last
            // explicit enable/disable choice even if its files were removed.
            $auditResult = $conn->query("
                SELECT new_value
                FROM unifiedauditlogstbl
                WHERE module_affected = 'website_settings'
                  AND target_type = 'maintenance_mode'
                  AND action_type IN ('enable_maintenance', 'disable_maintenance')
                ORDER BY action_timestamp DESC, audit_id DESC
                LIMIT 1
            ");
            if (!$auditResult) {
                return null;
            }

            $auditRow = $auditResult->fetch_assoc();
            $auditResult->free();
            $decoded = isset($auditRow['new_value'])
                ? json_decode((string)$auditRow['new_value'], true)
                : null;
            if (!is_array($decoded)) {
                return null;
            }

            $migrated = wms_normalize_settings($decoded);
            wms_write_database_settings($conn, $migrated);
            return $migrated;
        }

        return wms_normalize_settings([
            'enabled' => !empty($row['enabled']),
            'message' => (string)($row['maintenance_message'] ?? ''),
            'subcopy' => (string)($row['maintenance_subcopy'] ?? ''),
            'registration_enabled' => !isset($row['registration_enabled']) || !empty($row['registration_enabled']),
            'registration_message' => (string)($row['registration_message'] ?? ''),
            'resident_timeout_minutes' => (int)($row['resident_timeout_minutes'] ?? 30),
            'admin_timeout_minutes' => (int)($row['admin_timeout_minutes'] ?? 30),
            'admin_2fa_enabled' => !empty($row['admin_2fa_enabled']),
            'default_language' => (string)($row['default_language'] ?? 'en'),
            'default_font_scale' => (string)($row['default_font_scale'] ?? '100'),
            'high_contrast' => !empty($row['high_contrast']),
            'reduced_motion' => !empty($row['reduced_motion']),
            'updated_by_user_id' => (string)($row['updated_by_user_id'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ]);
    }
}

if (!function_exists('wms_write_database_settings')) {
    function wms_write_database_settings(mysqli $conn, array $settings): void
    {
        if (!wms_ensure_database_storage($conn)) {
            throw new RuntimeException('Unable to prepare durable website settings storage.');
        }
        wms_ensure_extended_columns($conn);

        $stmt = $conn->prepare("
            INSERT INTO websitesettingstbl
                (setting_id, enabled, maintenance_message, maintenance_subcopy,
                 registration_enabled, registration_message, resident_timeout_minutes,
                 admin_timeout_minutes, admin_2fa_enabled, default_language,
                 default_font_scale, high_contrast, reduced_motion,
                 updated_by_user_id, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                maintenance_message = VALUES(maintenance_message),
                maintenance_subcopy = VALUES(maintenance_subcopy),
                registration_enabled = VALUES(registration_enabled),
                registration_message = VALUES(registration_message),
                resident_timeout_minutes = VALUES(resident_timeout_minutes),
                admin_timeout_minutes = VALUES(admin_timeout_minutes),
                admin_2fa_enabled = VALUES(admin_2fa_enabled),
                default_language = VALUES(default_language),
                default_font_scale = VALUES(default_font_scale),
                high_contrast = VALUES(high_contrast),
                reduced_motion = VALUES(reduced_motion),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = VALUES(updated_at)
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare durable website settings update.');
        }

        $enabled = !empty($settings['enabled']) ? 1 : 0;
        $message = (string)($settings['message'] ?? '');
        $subcopy = (string)($settings['subcopy'] ?? '');
        $registrationEnabled = !empty($settings['registration_enabled']) ? 1 : 0;
        $registrationMessage = (string)($settings['registration_message'] ?? '');
        $residentTimeout = (int)($settings['resident_timeout_minutes'] ?? 30);
        $adminTimeout = (int)($settings['admin_timeout_minutes'] ?? 30);
        $admin2fa = !empty($settings['admin_2fa_enabled']) ? 1 : 0;
        $language = (string)($settings['default_language'] ?? 'en');
        $fontScale = (string)($settings['default_font_scale'] ?? '100');
        $highContrast = !empty($settings['high_contrast']) ? 1 : 0;
        $reducedMotion = !empty($settings['reduced_motion']) ? 1 : 0;
        $updatedBy = (string)($settings['updated_by_user_id'] ?? '');
        $updatedAt = (string)($settings['updated_at'] ?? '');
        $stmt->bind_param('issisiiissiiss', $enabled, $message, $subcopy, $registrationEnabled, $registrationMessage, $residentTimeout, $adminTimeout, $admin2fa, $language, $fontScale, $highContrast, $reducedMotion, $updatedBy, $updatedAt);
        $saved = $stmt->execute();
        $stmt->close();
        if (!$saved) {
            throw new RuntimeException('Unable to save durable website settings.');
        }
    }
}

if (!function_exists('wms_normalize_text')) {
    function wms_normalize_text($value, int $maxLen, string $fallback = ''): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            $text = $fallback;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (function_exists('mb_substr')) {
            return trim((string)mb_substr($text, 0, $maxLen));
        }

        return trim((string)substr($text, 0, $maxLen));
    }
}

if (!function_exists('wms_normalize_settings')) {
    function wms_normalize_settings(array $settings): array
    {
        $defaults = wms_default_settings();

        return [
            'enabled' => !empty($settings['enabled']),
            'message' => wms_normalize_text($settings['message'] ?? '', 600, (string)$defaults['message']),
            'subcopy' => wms_normalize_text($settings['subcopy'] ?? '', 400, (string)$defaults['subcopy']),
            'registration_enabled' => !array_key_exists('registration_enabled', $settings) || !empty($settings['registration_enabled']),
            'registration_message' => wms_normalize_text($settings['registration_message'] ?? '', 400, (string)$defaults['registration_message']),
            'resident_timeout_minutes' => max(5, min(240, (int)($settings['resident_timeout_minutes'] ?? 30))),
            'admin_timeout_minutes' => max(5, min(120, (int)($settings['admin_timeout_minutes'] ?? 30))),
            'admin_2fa_enabled' => !empty($settings['admin_2fa_enabled']),
            'default_language' => in_array((string)($settings['default_language'] ?? 'en'), ['en', 'fil'], true) ? (string)($settings['default_language'] ?? 'en') : 'en',
            'default_font_scale' => in_array((string)($settings['default_font_scale'] ?? '100'), ['90', '100', '110', '120'], true) ? (string)($settings['default_font_scale'] ?? '100') : '100',
            'high_contrast' => !empty($settings['high_contrast']),
            'reduced_motion' => !empty($settings['reduced_motion']),
            'updated_by_user_id' => wms_normalize_text($settings['updated_by_user_id'] ?? '', 20, ''),
            'updated_at' => wms_normalize_text($settings['updated_at'] ?? '', 40, ''),
        ];
    }
}

if (!function_exists('wms_load_settings')) {
    function wms_load_settings(?mysqli $conn = null): array
    {
        $path = wms_settings_path();
        $defaults = wms_default_settings();
        $localSettings = null;

        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $localSettings = wms_normalize_settings($decoded);
            }
        }

        if ($localSettings === null) {
            $defaults['enabled'] = is_file(wms_flag_path());
            $localSettings = $defaults;
        }

        $databaseSettings = $conn instanceof mysqli ? wms_load_database_settings($conn) : null;
        $settings = is_array($databaseSettings) ? $databaseSettings : $localSettings;

        // The database is authoritative. Repair disposable routing files when a
        // deployment or cleanup removed them instead of treating that as a disable.
        if (is_array($databaseSettings)) {
            try {
                wms_write_settings_file($settings);
                if (!empty($settings['enabled'])) {
                    if (!is_file(wms_flag_path())) {
                        wms_write_flag_file($settings);
                    }
                } elseif (is_file(wms_flag_path())) {
                    wms_disable_flag_file();
                }
            } catch (Throwable $e) {
                // Keep showing the durable state. A later settings save will report
                // any filesystem write failure directly to the administrator.
            }
        }

        return $settings;
    }
}

if (!function_exists('wms_is_enabled')) {
    function wms_is_enabled(?mysqli $conn = null): bool
    {
        return !empty(wms_load_settings($conn)['enabled']);
    }
}

if (!function_exists('wms_write_flag_file')) {
    function wms_write_flag_file(array $settings): void
    {
        $path = wms_flag_path();
        $tempPath = $path . '.tmp';
        $flagBody = 'enabled_at=' . (string)($settings['updated_at'] ?? date('c')) . PHP_EOL;
        $bytesWritten = @file_put_contents($tempPath, $flagBody, LOCK_EX);
        if ($bytesWritten === false) {
            throw new RuntimeException('Unable to write the maintenance mode flag file.');
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new RuntimeException('Unable to publish the maintenance mode flag file.');
        }
    }
}

if (!function_exists('wms_write_settings_file')) {
    function wms_write_settings_file(array $settings): void
    {
        $path = wms_settings_path();
        $payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || $payload === '') {
            throw new RuntimeException('Unable to prepare the website settings payload.');
        }

        $tempPath = $path . '.tmp';
        $bytesWritten = @file_put_contents($tempPath, $payload . PHP_EOL, LOCK_EX);
        if ($bytesWritten === false) {
            throw new RuntimeException('Unable to write the website settings file.');
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new RuntimeException('Unable to publish the website settings file.');
        }
    }
}

if (!function_exists('wms_disable_flag_file')) {
    function wms_disable_flag_file(): void
    {
        $path = wms_flag_path();
        if (!is_file($path)) {
            return;
        }

        if (!@unlink($path)) {
            throw new RuntimeException('Unable to remove the maintenance mode flag file.');
        }
    }
}

if (!function_exists('wms_save_settings')) {
    function wms_save_settings(array $settings, string $updatedByUserId = '', ?mysqli $conn = null): array
    {
        $normalized = wms_normalize_settings($settings);
        $normalized['updated_by_user_id'] = wms_normalize_text($updatedByUserId, 20, '');
        $normalized['updated_at'] = date('c');

        if ($conn instanceof mysqli) {
            wms_write_database_settings($conn, $normalized);
        }
        wms_write_settings_file($normalized);

        if (!empty($normalized['enabled'])) {
            wms_write_flag_file($normalized);
        } else {
            wms_disable_flag_file();
        }

        return $normalized;
    }
}
