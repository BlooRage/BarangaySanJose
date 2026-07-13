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
                updated_by_user_id VARCHAR(20) NULL,
                updated_at VARCHAR(40) NULL,
                PRIMARY KEY (setting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('wms_load_database_settings')) {
    function wms_load_database_settings(mysqli $conn): ?array
    {
        if (!wms_ensure_database_storage($conn)) {
            return null;
        }

        $result = $conn->query("
            SELECT enabled, maintenance_message, maintenance_subcopy,
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

        $stmt = $conn->prepare("
            INSERT INTO websitesettingstbl
                (setting_id, enabled, maintenance_message, maintenance_subcopy,
                 updated_by_user_id, updated_at)
            VALUES (1, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                maintenance_message = VALUES(maintenance_message),
                maintenance_subcopy = VALUES(maintenance_subcopy),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = VALUES(updated_at)
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare durable website settings update.');
        }

        $enabled = !empty($settings['enabled']) ? 1 : 0;
        $message = (string)($settings['message'] ?? '');
        $subcopy = (string)($settings['subcopy'] ?? '');
        $updatedBy = (string)($settings['updated_by_user_id'] ?? '');
        $updatedAt = (string)($settings['updated_at'] ?? '');
        $stmt->bind_param('issss', $enabled, $message, $subcopy, $updatedBy, $updatedAt);
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
