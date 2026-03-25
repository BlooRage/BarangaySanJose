<?php

if (!function_exists('ot_transition_settings_definitions')) {
    function ot_transition_settings_definitions(): array
    {
        return [
            'it_admin_update_notice_days' => [
                'label' => 'IT administrator election-date reminder',
                'short_label' => 'IT Reminder',
                'description' => 'Days before the election to notify the IT Administrator to review and update the election date.',
                'min' => 1,
                'max' => 365,
                'default' => 90,
            ],
            'outgoing_notice_days' => [
                'label' => 'Outgoing officials notice',
                'short_label' => 'Outgoing Notice',
                'description' => 'Days before the election to notify outgoing officials that their access will be revoked.',
                'min' => 1,
                'max' => 365,
                'default' => 30,
            ],
            'access_revoke_days' => [
                'label' => 'Privilege revocation timing',
                'short_label' => 'Access Revocation',
                'description' => 'Days before the election when outgoing official privileges are revoked. Minimum is 1 day.',
                'min' => 1,
                'max' => 365,
                'default' => 7,
            ],
            'post_election_followup_days' => [
                'label' => 'Post-election follow-up reminder',
                'short_label' => 'Post-Election Follow-up',
                'description' => 'Days after the election to remind the IT Administrator to review unresolved transition records.',
                'min' => 0,
                'max' => 365,
                'default' => 3,
            ],
        ];
    }
}

if (!function_exists('ot_transition_settings_ensure_table')) {
    function ot_transition_settings_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialtransitionsettingstbl (
                setting_key VARCHAR(100) NOT NULL,
                setting_value INT NOT NULL,
                updated_by_user_id VARCHAR(20) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('ot_transition_settings_load')) {
    function ot_transition_settings_load(mysqli $conn): array
    {
        ot_transition_settings_ensure_table($conn);

        $definitions = ot_transition_settings_definitions();
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $settings[$key] = (int)($definition['default'] ?? 0);
        }

        $res = $conn->query("SELECT setting_key, setting_value FROM officialtransitionsettingstbl");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $key = (string)($row['setting_key'] ?? '');
                if (!array_key_exists($key, $settings)) {
                    continue;
                }
                $settings[$key] = (int)($row['setting_value'] ?? $settings[$key]);
            }
            $res->close();
        }

        return $settings;
    }
}

if (!function_exists('ot_transition_settings_upsert')) {
    function ot_transition_settings_upsert(mysqli $conn, array $settings, string $updatedByUserId = ''): void
    {
        ot_transition_settings_ensure_table($conn);

        $definitions = ot_transition_settings_definitions();
        $stmt = $conn->prepare("
            INSERT INTO officialtransitionsettingstbl (setting_key, setting_value, updated_by_user_id)
            VALUES (?, ?, NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save official transition settings.');
        }

        foreach ($definitions as $key => $definition) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }
            $value = (int)$settings[$key];
            $stmt->bind_param('sis', $key, $value, $updatedByUserId);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('ot_transition_settings_days_label')) {
    function ot_transition_settings_days_label(int $days, string $suffix): string
    {
        return $days . ' ' . ($days === 1 ? 'Day' : 'Days') . ' ' . $suffix;
    }
}
