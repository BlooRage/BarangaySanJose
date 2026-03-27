<?php
declare(strict_types=1);

require_once __DIR__ . '/uniqueIDGenerate.php';

if (!function_exists('hmv_ensure_request_table')) {
    function hmv_ensure_request_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS householdmemberverificationtbl (
                request_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
                fam_head_id VARCHAR(100) NOT NULL,
                submitted_by_user_id VARCHAR(100) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                middle_name VARCHAR(255) DEFAULT NULL,
                suffix VARCHAR(255) DEFAULT NULL,
                birthdate DATE NOT NULL,
                status_id INT(11) DEFAULT NULL,
                attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
                approved_household_member_id BIGINT(20) UNSIGNED DEFAULT NULL,
                reviewed_by_user_id VARCHAR(100) DEFAULT NULL,
                review_remarks TEXT DEFAULT NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_hmv_head_status (fam_head_id, status_id),
                KEY idx_hmv_status_submitted (status_id, submitted_at),
                KEY idx_hmv_submitted_by (submitted_by_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->query($sql);
        idg_ensure_numeric_generated_key($conn, 'householdmemberverificationtbl', 'request_id', 'BIGINT(20) UNSIGNED NOT NULL');

        $columnDefinitions = [
            'submitted_by_user_id' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN submitted_by_user_id VARCHAR(100) NOT NULL AFTER fam_head_id",
            'middle_name' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN middle_name VARCHAR(255) DEFAULT NULL AFTER first_name",
            'suffix' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN suffix VARCHAR(255) DEFAULT NULL AFTER middle_name",
            'status_id' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN status_id INT(11) DEFAULT NULL AFTER birthdate",
            'attachment_id' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN attachment_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER status_id",
            'approved_household_member_id' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN approved_household_member_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER attachment_id",
            'reviewed_by_user_id' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN reviewed_by_user_id VARCHAR(100) DEFAULT NULL AFTER approved_household_member_id",
            'review_remarks' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN review_remarks TEXT DEFAULT NULL AFTER reviewed_by_user_id",
            'submitted_at' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER review_remarks",
            'reviewed_at' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER submitted_at",
            'updated_at' => "ALTER TABLE householdmemberverificationtbl ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER reviewed_at",
        ];

        foreach ($columnDefinitions as $columnName => $alterSql) {
            $safeColumn = $conn->real_escape_string($columnName);
            $res = $conn->query("SHOW COLUMNS FROM householdmemberverificationtbl LIKE '{$safeColumn}'");
            $exists = $res instanceof mysqli_result && $res->num_rows > 0;
            if ($res instanceof mysqli_result) {
                $res->free();
            }
            if (!$exists) {
                $conn->query($alterSql);
            }
        }

        $indexDefinitions = [
            'idx_hmv_head_status' => "ALTER TABLE householdmemberverificationtbl ADD KEY idx_hmv_head_status (fam_head_id, status_id)",
            'idx_hmv_status_submitted' => "ALTER TABLE householdmemberverificationtbl ADD KEY idx_hmv_status_submitted (status_id, submitted_at)",
            'idx_hmv_submitted_by' => "ALTER TABLE householdmemberverificationtbl ADD KEY idx_hmv_submitted_by (submitted_by_user_id)",
        ];

        foreach ($indexDefinitions as $indexName => $alterSql) {
            $safeIndex = $conn->real_escape_string($indexName);
            $res = $conn->query("SHOW INDEX FROM householdmemberverificationtbl WHERE Key_name = '{$safeIndex}'");
            $exists = $res instanceof mysqli_result && $res->num_rows > 0;
            if ($res instanceof mysqli_result) {
                $res->free();
            }
            if (!$exists) {
                $conn->query($alterSql);
            }
        }

        $legacyStatusExists = false;
        $legacyStatusRes = $conn->query("SHOW COLUMNS FROM householdmemberverificationtbl LIKE 'status'");
        if ($legacyStatusRes instanceof mysqli_result) {
            $legacyStatusExists = $legacyStatusRes->num_rows > 0;
            $legacyStatusRes->free();
        }

        $pendingStatusId = hmv_ensure_household_member_status_id($conn, 'PendingReview');
        $activeStatusId = hmv_ensure_household_member_status_id($conn, 'Active');
        $rejectedStatusId = hmv_ensure_household_member_status_id($conn, 'Rejected');

        if ($legacyStatusExists && $pendingStatusId !== null && $activeStatusId !== null && $rejectedStatusId !== null) {
            $stmt = $conn->prepare("
                UPDATE householdmemberverificationtbl
                SET status_id = CASE status
                    WHEN 'Approved' THEN ?
                    WHEN 'Rejected' THEN ?
                    ELSE ?
                END
                WHERE status_id IS NULL OR status_id = 0
            ");
            if ($stmt) {
                $stmt->bind_param('iii', $activeStatusId, $rejectedStatusId, $pendingStatusId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($pendingStatusId !== null) {
            $conn->query("
                UPDATE householdmemberverificationtbl
                SET status_id = {$pendingStatusId}
                WHERE (status_id IS NULL OR status_id = 0)
            ");
            $conn->query("
                ALTER TABLE householdmemberverificationtbl
                MODIFY COLUMN status_id INT(11) NOT NULL
            ");
        }
    }
}

if (!function_exists('hmv_generate_request_id')) {
    function hmv_generate_request_id(mysqli $conn): int
    {
        hmv_ensure_request_table($conn);
        $generated = GenerateHouseholdMemberVerificationRequestID($conn);
        return $generated !== false ? (int)$generated : 0;
    }
}

if (!function_exists('hmv_has_request_column')) {
    function hmv_has_request_column(mysqli $conn, string $columnName): bool
    {
        $safeColumn = $conn->real_escape_string($columnName);
        $res = $conn->query("SHOW COLUMNS FROM householdmemberverificationtbl LIKE '{$safeColumn}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $exists;
    }
}

if (!function_exists('hmv_request_uses_status_lookup')) {
    function hmv_request_uses_status_lookup(mysqli $conn): bool
    {
        return hmv_has_request_column($conn, 'status_id');
    }
}

if (!function_exists('hmv_get_status_id')) {
    function hmv_get_status_id(mysqli $conn, string $name, string $type): ?int
    {
        $stmt = $conn->prepare("
            SELECT status_id
            FROM statuslookuptbl
            WHERE status_name = ? AND status_type = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $name, $type);
        $stmt->execute();
        $stmt->bind_result($statusId);
        $statusId = $stmt->fetch() ? (int)$statusId : null;
        $stmt->close();
        return $statusId;
    }
}

if (!function_exists('hmv_ensure_status_id')) {
    function hmv_ensure_status_id(mysqli $conn, string $name, string $type): ?int
    {
        $resolved = hmv_get_status_id($conn, $name, $type);
        if ($resolved !== null) {
            return $resolved;
        }

        $stmt = $conn->prepare("
            INSERT INTO statuslookuptbl (status_name, status_type)
            VALUES (?, ?)
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $name, $type);
        if (!$stmt->execute()) {
            $stmt->close();
            return hmv_get_status_id($conn, $name, $type);
        }
        $statusId = (int)$stmt->insert_id;
        $stmt->close();
        return $statusId > 0 ? $statusId : hmv_get_status_id($conn, $name, $type);
    }
}

if (!function_exists('hmv_get_household_member_status_id')) {
    function hmv_get_household_member_status_id(mysqli $conn, string $name): ?int
    {
        return hmv_get_status_id($conn, $name, 'HouseholdMember');
    }
}

if (!function_exists('hmv_ensure_household_member_status_id')) {
    function hmv_ensure_household_member_status_id(mysqli $conn, string $name): ?int
    {
        return hmv_ensure_status_id($conn, $name, 'HouseholdMember');
    }
}

if (!function_exists('hmv_get_document_type_id')) {
    function hmv_get_document_type_id(mysqli $conn, string $name, string $category): int
    {
        $stmt = $conn->prepare("
            SELECT document_type_id
            FROM documenttypelookuptbl
            WHERE LOWER(document_type_name) = LOWER(?)
              AND document_category = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare household member document type lookup.');
        }
        $stmt->bind_param('ss', $name, $category);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['document_type_id'])) {
            return (int)$row['document_type_id'];
        }

        $insert = $conn->prepare("
            INSERT INTO documenttypelookuptbl (document_type_name, document_category)
            VALUES (?, ?)
        ");
        if (!$insert) {
            throw new RuntimeException('Failed to prepare household member document type create.');
        }
        $insert->bind_param('ss', $name, $category);
        if (!$insert->execute()) {
            $insert->close();
            throw new RuntimeException('Failed to create household member document type.');
        }
        $documentTypeId = (int)$insert->insert_id;
        $insert->close();

        if ($documentTypeId <= 0) {
            throw new RuntimeException('Failed to resolve household member document type.');
        }

        return $documentTypeId;
    }
}

if (!function_exists('hmv_to_db_web_path')) {
    function hmv_to_db_web_path(string $absolutePath): string
    {
        $absolutePath = str_replace("\\", "/", trim($absolutePath));
        $projectRoot = realpath(__DIR__ . '/../..');
        $marker = '/UnifiedFileAttachment/';
        $markerPos = strpos($absolutePath, $marker);
        if ($markerPos !== false) {
            return ltrim(substr($absolutePath, $markerPos), '/');
        }
        if ($projectRoot) {
            $rootNorm = str_replace("\\", "/", $projectRoot);
            if (strpos($absolutePath, $rootNorm) === 0) {
                return ltrim(substr($absolutePath, strlen($rootNorm)), '/');
            }
        }
        return ltrim($absolutePath, '/');
    }
}

if (!function_exists('hmv_to_public_path')) {
    function hmv_to_public_path(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $normalized = str_replace("\\", "/", $path);
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;

        $parts = explode('/', $normalized);
        $cleanParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($cleanParts);
                continue;
            }
            $cleanParts[] = $part;
        }
        $normalized = '/' . implode('/', $cleanParts);

        $marker = '/UnifiedFileAttachment/';
        $markerPos = stripos($normalized, $marker);
        if ($markerPos !== false) {
            return '..' . substr($normalized, $markerPos);
        }

        $webRoot = realpath(__DIR__ . '/../..');
        if ($webRoot) {
            $rootNorm = str_replace("\\", "/", $webRoot);
            if (strpos($normalized, $rootNorm) === 0) {
                $rel = substr($normalized, strlen($rootNorm));
                if ($rel !== '') {
                    if ($rel[0] !== '/') {
                        $rel = '/' . $rel;
                    }
                    return '../' . ltrim($rel, '/');
                }
            }
        }

        return '../' . ltrim($normalized, '/');
    }
}
