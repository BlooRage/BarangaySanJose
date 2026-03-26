<?php
declare(strict_types=1);

if (!function_exists('ual_table_exists')) {
    function ual_table_exists(mysqli $conn, string $tableName): bool
    {
        $safeTable = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('ual_column_exists')) {
    function ual_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $safeTable = $conn->real_escape_string($tableName);
        $safeColumn = $conn->real_escape_string($columnName);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('ual_ensure_lock_columns')) {
    function ual_ensure_lock_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!ual_table_exists($conn, 'useraccountstbl')) {
            return;
        }

        $columnSql = [
            'lock_until' => "ALTER TABLE useraccountstbl ADD COLUMN lock_until DATETIME NULL DEFAULT NULL AFTER lock_start",
            'lock_type' => "ALTER TABLE useraccountstbl ADD COLUMN lock_type VARCHAR(20) NULL DEFAULT NULL AFTER lock_until",
            'lock_reason' => "ALTER TABLE useraccountstbl ADD COLUMN lock_reason VARCHAR(255) NULL DEFAULT NULL AFTER lock_type",
            'locked_by_user_id' => "ALTER TABLE useraccountstbl ADD COLUMN locked_by_user_id VARCHAR(12) NULL DEFAULT NULL AFTER lock_reason",
        ];

        foreach ($columnSql as $columnName => $sql) {
            if (!ual_column_exists($conn, 'useraccountstbl', $columnName)) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('ual_load_status_ids')) {
    function ual_load_status_ids(mysqli $conn): array
    {
        $statusIds = [];
        $stmt = $conn->prepare("
            SELECT status_id, status_name
            FROM statuslookuptbl
            WHERE status_type = 'UserAccount'
        ");
        if (!$stmt) {
            return $statusIds;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statusIds[strtolower(trim((string)($row['status_name'] ?? '')))] = (int)($row['status_id'] ?? 0);
        }
        $stmt->close();

        return $statusIds;
    }
}

if (!function_exists('ual_normalize_lock_type')) {
    function ual_normalize_lock_type(?string $lockType): string
    {
        $normalized = strtolower(trim((string)$lockType));
        return in_array($normalized, ['temporary', 'permanent'], true) ? $normalized : '';
    }
}

if (!function_exists('ual_get_lock_state')) {
    function ual_get_lock_state(array $row, int $defaultLockSeconds = 300): array
    {
        $lockType = ual_normalize_lock_type((string)($row['lock_type'] ?? ''));
        $lockUntilRaw = trim((string)($row['lock_until'] ?? ''));
        $lockStartRaw = trim((string)($row['lock_start'] ?? ''));

        $lockUntilTs = $lockUntilRaw !== '' ? strtotime($lockUntilRaw) : false;
        $lockStartTs = $lockStartRaw !== '' ? strtotime($lockStartRaw) : false;
        $fallbackUntilTs = ($lockStartTs !== false && $lockStartTs > 0) ? ($lockStartTs + $defaultLockSeconds) : false;

        $resolvedUntilRaw = $lockUntilRaw;
        $resolvedUntilTs = ($lockUntilTs !== false && $lockUntilTs > 0) ? $lockUntilTs : false;

        if ($resolvedUntilTs === false && $fallbackUntilTs !== false) {
            $resolvedUntilTs = $fallbackUntilTs;
            $resolvedUntilRaw = date('Y-m-d H:i:s', $fallbackUntilTs);
        }

        $isPermanent = $lockType === 'permanent' || ($lockType === '' && $resolvedUntilTs === false);
        $isExpired = !$isPermanent && $resolvedUntilTs !== false && time() >= $resolvedUntilTs;

        return [
            'type' => $isPermanent ? 'permanent' : 'temporary',
            'is_permanent' => $isPermanent,
            'is_expired' => $isExpired,
            'lock_until' => $resolvedUntilRaw,
            'lock_until_ts' => $resolvedUntilTs,
            'reason' => trim((string)($row['lock_reason'] ?? '')),
            'locked_by_user_id' => trim((string)($row['locked_by_user_id'] ?? '')),
        ];
    }
}

if (!function_exists('ual_lock_status_label')) {
    function ual_lock_status_label(array $lockState): string
    {
        if (!empty($lockState['is_expired'])) {
            return 'Lock expired';
        }
        if (!empty($lockState['is_permanent'])) {
            return 'Locked permanently';
        }
        $lockUntil = trim((string)($lockState['lock_until'] ?? ''));
        if ($lockUntil !== '') {
            $formatted = $lockUntil;
            try {
                $dt = new DateTimeImmutable($lockUntil, new DateTimeZone('Asia/Manila'));
                $formatted = $dt->format('M j, Y g:i A');
            } catch (Throwable) {
                $formatted = $lockUntil;
            }
            return 'Locked until ' . $formatted;
        }
        return 'Locked';
    }
}

if (!function_exists('ual_release_expired_locks')) {
    function ual_release_expired_locks(mysqli $conn, ?int $lockedStatusId, ?int $activeStatusId, int $defaultLockSeconds = 300): void
    {
        ual_ensure_lock_columns($conn);

        if ($lockedStatusId === null) {
            return;
        }

        $expiryCondition = "
            (
                (
                    (lock_type = 'temporary' OR lock_type IS NULL OR TRIM(lock_type) = '')
                    AND lock_until IS NOT NULL
                    AND lock_until <= NOW()
                )
                OR (
                    (lock_type = 'temporary' OR lock_type IS NULL OR TRIM(lock_type) = '')
                    AND lock_until IS NULL
                    AND lock_start IS NOT NULL
                    AND TIMESTAMPDIFF(SECOND, lock_start, NOW()) >= ?
                )
            )
        ";

        if ($activeStatusId !== null) {
            $stmt = $conn->prepare("
                UPDATE useraccountstbl
                SET status_id_account = ?,
                    failed_logins = 0,
                    lock_start = NULL,
                    lock_until = NULL,
                    lock_type = NULL,
                    lock_reason = NULL,
                    locked_by_user_id = NULL,
                    updated_at = NOW()
                WHERE status_id_account = ?
                  AND {$expiryCondition}
            ");
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('iii', $activeStatusId, $lockedStatusId, $defaultLockSeconds);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $stmt = $conn->prepare("
            UPDATE useraccountstbl
            SET failed_logins = 0,
                lock_start = NULL,
                lock_until = NULL,
                lock_type = NULL,
                lock_reason = NULL,
                locked_by_user_id = NULL,
                updated_at = NOW()
            WHERE status_id_account = ?
              AND {$expiryCondition}
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ii', $lockedStatusId, $defaultLockSeconds);
        $stmt->execute();
        $stmt->close();
    }
}
