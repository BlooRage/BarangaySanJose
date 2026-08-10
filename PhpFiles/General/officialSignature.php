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
                signature_blob LONGBLOB NULL,
                scale_percent SMALLINT UNSIGNED NOT NULL DEFAULT 100,
                offset_x_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                offset_y_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                creation_method VARCHAR(20) NOT NULL DEFAULT 'draw',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deactivated_at DATETIME NULL,
                PRIMARY KEY (signature_id),
                KEY idx_osig_official_active (official_id, is_active),
                KEY idx_osig_user_active (user_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $column = $conn->query("SHOW COLUMNS FROM officialsignaturetbl LIKE 'signature_blob'");
        if (!($column instanceof mysqli_result) || $column->num_rows === 0) {
            $conn->query("ALTER TABLE officialsignaturetbl ADD COLUMN signature_blob LONGBLOB NULL AFTER file_path");
        }
        $scaleColumn = $conn->query("SHOW COLUMNS FROM officialsignaturetbl LIKE 'scale_percent'");
        if (!($scaleColumn instanceof mysqli_result) || $scaleColumn->num_rows === 0) {
            $conn->query("ALTER TABLE officialsignaturetbl ADD COLUMN scale_percent SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER signature_blob");
        }
        $offsetXColumn = $conn->query("SHOW COLUMNS FROM officialsignaturetbl LIKE 'offset_x_percent'");
        if (!($offsetXColumn instanceof mysqli_result) || $offsetXColumn->num_rows === 0) {
            $conn->query("ALTER TABLE officialsignaturetbl ADD COLUMN offset_x_percent DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER scale_percent");
        }
        $offsetYColumn = $conn->query("SHOW COLUMNS FROM officialsignaturetbl LIKE 'offset_y_percent'");
        if (!($offsetYColumn instanceof mysqli_result) || $offsetYColumn->num_rows === 0) {
            $conn->query("ALTER TABLE officialsignaturetbl ADD COLUMN offset_y_percent DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER offset_x_percent");
        }
    }
}

if (!function_exists('osig_restore_file')) {
    function osig_restore_file(mysqli $conn, array $row): array
    {
        $publicPath = trim((string)($row['file_path'] ?? ''));
        $blob = (string)($row['signature_blob'] ?? '');
        if ($publicPath === '' || strpos(str_replace('\\', '/', $publicPath), '/UnifiedFileAttachment/') !== 0) {
            return $row;
        }
        $projectRoot = realpath(__DIR__ . '/../../');
        if ($projectRoot === false) return $row;
        $diskPath = $projectRoot . str_replace('\\', '/', $publicPath);
        if (is_file($diskPath) && $blob === '') {
            $contents = @file_get_contents($diskPath);
            $signatureId = (int)($row['signature_id'] ?? 0);
            if (is_string($contents) && $contents !== '' && $signatureId > 0) {
                $update = $conn->prepare('UPDATE officialsignaturetbl SET signature_blob = ? WHERE signature_id = ? LIMIT 1');
                if ($update) {
                    $nullBlob = null;
                    $update->bind_param('bi', $nullBlob, $signatureId);
                    $update->send_long_data(0, $contents);
                    $update->execute();
                    $update->close();
                    $row['signature_blob'] = $contents;
                }
            }
        }
        if (!is_file($diskPath) && $blob !== '') {
            $directory = dirname($diskPath);
            if ((is_dir($directory) || @mkdir($directory, 0775, true)) && @file_put_contents($diskPath, $blob, LOCK_EX) !== false) {
                @chmod($diskPath, 0664);
            }
        }
        return $row;
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
            SELECT signature_id, official_id, user_id, file_path, signature_blob, scale_percent, offset_x_percent, offset_y_percent, creation_method, created_at
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
        return $row ? osig_restore_file($conn, $row) : null;
    }
}

if (!function_exists('osig_get_current_punong')) {
    function osig_get_current_punong(mysqli $conn): ?array
    {
        osig_ensure_schema($conn);
        $res = $conn->query("
            SELECT os.signature_id, os.official_id, os.user_id, os.file_path, os.signature_blob, os.scale_percent, os.offset_x_percent, os.offset_y_percent, os.creation_method, os.created_at
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
        $row = $res instanceof mysqli_result ? ($res->fetch_assoc() ?: null) : null;
        return $row ? osig_restore_file($conn, $row) : null;
    }
}
