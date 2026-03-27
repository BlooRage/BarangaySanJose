<?php

if (!function_exists('apcm_table_exists')) {
    function apcm_table_exists(mysqli $conn, string $tableName): bool
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

if (!function_exists('apcm_column_exists')) {
    function apcm_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $tableName, $columnName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return !empty($row);
    }
}

if (!function_exists('apcm_account_status_allows_appointments')) {
    function apcm_account_status_allows_appointments(string $statusName): bool
    {
        $normalized = strtolower(trim($statusName));
        if ($normalized === '') {
            return true;
        }

        return !preg_match('/inactive|revoked|suspended|disabled/', $normalized);
    }
}

if (!function_exists('apcm_full_name')) {
    function apcm_full_name(array $row): string
    {
        $parts = array_values(array_filter([
            trim((string)($row['firstname'] ?? '')),
            trim((string)($row['middlename'] ?? '')),
            trim((string)($row['lastname'] ?? '')),
            trim((string)($row['suffix'] ?? '')),
        ], static fn($value): bool => $value !== ''));

        return implode(' ', $parts);
    }
}

if (!function_exists('apcm_option_label')) {
    function apcm_option_label(array $row): string
    {
        $fullName = trim((string)($row['full_name'] ?? apcm_full_name($row)));
        $seatName = trim((string)($row['seat_name'] ?? ''));
        $positionAccess = trim((string)($row['position_access'] ?? ''));
        $labelParts = [];

        if ($fullName !== '') {
            $labelParts[] = $fullName;
        }

        if ($seatName !== '' && strcasecmp($seatName, $positionAccess) !== 0) {
            $labelParts[] = $seatName;
        } elseif ($positionAccess !== '') {
            $labelParts[] = $positionAccess;
        }

        return $labelParts !== [] ? implode(' - ', $labelParts) : trim((string)($row['user_id'] ?? ''));
    }
}

if (!function_exists('apcm_fetch_council_members')) {
    function apcm_fetch_council_members(mysqli $conn): array
    {
        if (
            !apcm_table_exists($conn, 'barangaycounciltbl')
            || !apcm_table_exists($conn, 'officialinformationtbl')
            || !apcm_table_exists($conn, 'useraccountstbl')
        ) {
            return [];
        }

        $positionSelect = apcm_column_exists($conn, 'officialinformationtbl', 'position_access')
            ? 'COALESCE(oi.position_access, oi.role_access)'
            : 'oi.role_access';
        $statusJoin = '';
        $accountStatusSelect = "''";

        if (apcm_table_exists($conn, 'statuslookuptbl')) {
            $statusJoin = "
                LEFT JOIN statuslookuptbl sa
                    ON sa.status_id = ua.status_id_account
            ";
            $accountStatusSelect = "COALESCE(sa.status_name, '')";
        }

        $sql = "
            SELECT
                bc.council_id,
                bc.seat_name,
                bc.seat_group,
                bc.selection_method,
                bc.sort_order,
                bc.current_official_id,
                oi.official_id,
                oi.user_id,
                oi.firstname,
                oi.lastname,
                oi.middlename,
                oi.suffix,
                {$positionSelect} AS position_access,
                oi.department,
                {$accountStatusSelect} AS account_status
            FROM barangaycounciltbl bc
            LEFT JOIN officialinformationtbl oi
                ON oi.official_id = bc.current_official_id
            LEFT JOIN useraccountstbl ua
                ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            {$statusJoin}
            WHERE bc.is_active = 1
              AND bc.selection_method = 'Elected'
            ORDER BY bc.sort_order, bc.council_id
        ";

        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $members = [];
        while ($row = $result->fetch_assoc()) {
            $officialId = trim((string)($row['official_id'] ?? ''));
            $userId = trim((string)($row['user_id'] ?? ''));
            if ($officialId === '' || $userId === '') {
                continue;
            }

            if (!apcm_account_status_allows_appointments((string)($row['account_status'] ?? ''))) {
                continue;
            }

            $row['official_id'] = $officialId;
            $row['user_id'] = $userId;
            $row['full_name'] = apcm_full_name($row);
            if ($row['full_name'] === '') {
                $row['full_name'] = trim((string)($row['seat_name'] ?? ''));
            }
            $row['option_label'] = apcm_option_label($row);
            $members[] = $row;
        }
        $result->free();

        return $members;
    }
}

if (!function_exists('apcm_fetch_council_members_by_user_id')) {
    function apcm_fetch_council_members_by_user_id(mysqli $conn): array
    {
        $members = [];
        foreach (apcm_fetch_council_members($conn) as $row) {
            $userId = trim((string)($row['user_id'] ?? ''));
            if ($userId !== '') {
                $members[$userId] = $row;
            }
        }

        return $members;
    }
}

if (!function_exists('apcm_normalize_session_role')) {
    function apcm_normalize_session_role(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === 'superadmin') {
            return 'superadmin';
        }
        if ($normalized === 'personnel' || $normalized === 'personnels') {
            return 'personnel';
        }
        if ($normalized === 'secretary') {
            return 'secretary';
        }
        return 'official';
    }
}

if (!function_exists('apcm_personnel_position_labels')) {
    function apcm_personnel_position_labels(): array
    {
        return [
            'department public assistance desk',
            'department secretary',
            'department oic (officer in charge)',
            'barangay police',
            'desk officer',
            'area oic',
            'barangay treasurer',
        ];
    }
}

if (!function_exists('apcm_is_personnel_position')) {
    function apcm_is_personnel_position(string $positionAccess): bool
    {
        $normalized = strtolower(trim($positionAccess));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, apcm_personnel_position_labels(), true);
    }
}

if (!function_exists('apcm_is_barangay_secretary_position')) {
    function apcm_is_barangay_secretary_position(string $positionAccess): bool
    {
        $normalized = strtolower(trim($positionAccess));
        return in_array($normalized, ['barangay secretary', 'secretary'], true);
    }
}

if (!function_exists('apcm_current_official_position')) {
    function apcm_current_official_position(mysqli $conn, string $userId): string
    {
        $userId = trim($userId);
        if ($userId === '' || !apcm_table_exists($conn, 'officialinformationtbl')) {
            return '';
        }

        $selectPosition = apcm_column_exists($conn, 'officialinformationtbl', 'position_access')
            ? 'COALESCE(position_access, role_access)'
            : 'role_access';
        $stmt = $conn->prepare("
            SELECT {$selectPosition} AS position_access
            FROM officialinformationtbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return '';
        }

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return trim((string)($row['position_access'] ?? ''));
    }
}

if (!function_exists('apcm_get_appointment_admin_scope')) {
    function apcm_get_appointment_admin_scope(mysqli $conn, string $userId, string $sessionRole = ''): array
    {
        $userId = trim($userId);
        $normalizedRole = apcm_normalize_session_role($sessionRole);
        $positionAccess = apcm_current_official_position($conn, $userId);

        $isSuperAdmin = $normalizedRole === 'superadmin';
        $isBarangaySecretary = !$isSuperAdmin
            && (
                $normalizedRole === 'secretary'
                || apcm_is_barangay_secretary_position($positionAccess)
            );
        $isPersonnel = !$isSuperAdmin
            && !$isBarangaySecretary
            && (
                $normalizedRole === 'personnel'
                || apcm_is_personnel_position($positionAccess)
            );
        $canAccessTracker = !$isPersonnel;
        $canViewAllTracker = $isSuperAdmin || $isBarangaySecretary;

        return [
            'user_id' => $userId,
            'position_access' => $positionAccess,
            'is_superadmin' => $isSuperAdmin,
            'is_barangay_secretary' => $isBarangaySecretary,
            'is_personnel' => $isPersonnel,
            'can_access_tracker' => $canAccessTracker,
            'can_view_all_tracker' => $canViewAllTracker,
            'can_manage_all_tracker' => $canViewAllTracker,
            'can_access_settings' => $canViewAllTracker,
            'scoped_official_user_id' => $canViewAllTracker ? '' : $userId,
        ];
    }
}
