<?php
declare(strict_types=1);

if (!function_exists('osig_ensure_schema')) {
    function osig_ensure_schema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS officialsignaturetbl (
                signature_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                official_id VARCHAR(20) NOT NULL,
                user_id VARCHAR(20) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                creation_method VARCHAR(20) NOT NULL DEFAULT 'draw',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deactivated_at DATETIME NULL,
                PRIMARY KEY (signature_id),
                KEY idx_osig_official_active (official_id, is_active),
                KEY idx_osig_user_active (user_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('osig_get_current')) {
    function osig_get_current(mysqli $conn, string $officialId = '', string $userId = ''): ?array
    {
        osig_ensure_schema($conn);
        $officialId = trim($officialId);
        $userId = trim($userId);
        if ($officialId === '' && $userId === '') return null;
        $stmt = $conn->prepare("
            SELECT signature_id, official_id, user_id, file_path, creation_method, created_at
            FROM officialsignaturetbl
            WHERE is_active = 1
              AND ((? <> '' AND official_id = ?) OR (? <> '' AND user_id = ?))
            ORDER BY signature_id DESC
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param('ssss', $officialId, $officialId, $userId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('osig_get_current_punong')) {
    function osig_get_current_punong(mysqli $conn): ?array
    {
        osig_ensure_schema($conn);
        $res = $conn->query("
            SELECT os.signature_id, os.official_id, os.user_id, os.file_path, os.creation_method, os.created_at
            FROM barangaycounciltbl bc
            INNER JOIN officialsignaturetbl os ON os.official_id = bc.current_official_id AND os.is_active = 1
            WHERE bc.is_active = 1
              AND (
                LOWER(bc.seat_name) LIKE '%punong barangay%'
                OR LOWER(bc.seat_name) LIKE '%barangay captain%'
                OR LOWER(bc.seat_name) = 'barangay chairman'
              )
            ORDER BY os.signature_id DESC
            LIMIT 1
        ");
        return $res instanceof mysqli_result ? ($res->fetch_assoc() ?: null) : null;
    }
}
