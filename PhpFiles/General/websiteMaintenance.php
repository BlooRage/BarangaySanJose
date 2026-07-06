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
    function wms_load_settings(): array
    {
        $path = wms_settings_path();
        $defaults = wms_default_settings();
        if (!is_file($path)) {
            $defaults['enabled'] = is_file(wms_flag_path());
            return $defaults;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            $defaults['enabled'] = is_file(wms_flag_path());
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $defaults['enabled'] = is_file(wms_flag_path());
            return $defaults;
        }

        $normalized = wms_normalize_settings($decoded);
        $normalized['enabled'] = is_file(wms_flag_path());
        return $normalized;
    }
}

if (!function_exists('wms_is_enabled')) {
    function wms_is_enabled(): bool
    {
        return !empty(wms_load_settings()['enabled']);
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
    function wms_save_settings(array $settings, string $updatedByUserId = ''): array
    {
        $normalized = wms_normalize_settings($settings);
        $normalized['updated_by_user_id'] = wms_normalize_text($updatedByUserId, 20, '');
        $normalized['updated_at'] = date('c');
        wms_write_settings_file($normalized);

        if (!empty($normalized['enabled'])) {
            wms_write_flag_file($normalized);
        } else {
            wms_disable_flag_file();
        }

        return $normalized;
    }
}
