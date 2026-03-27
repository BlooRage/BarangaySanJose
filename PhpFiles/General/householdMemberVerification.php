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
                status ENUM('PendingReview', 'Approved', 'Rejected') NOT NULL DEFAULT 'PendingReview',
                attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
                approved_household_member_id BIGINT(20) UNSIGNED DEFAULT NULL,
                reviewed_by_user_id VARCHAR(100) DEFAULT NULL,
                review_remarks TEXT DEFAULT NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_hmv_head_status (fam_head_id, status),
                KEY idx_hmv_status_submitted (status, submitted_at),
                KEY idx_hmv_submitted_by (submitted_by_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->query($sql);
        idg_ensure_numeric_generated_key($conn, 'householdmemberverificationtbl', 'request_id', 'BIGINT(20) UNSIGNED NOT NULL');
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
