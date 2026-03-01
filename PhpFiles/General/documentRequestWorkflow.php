<?php
declare(strict_types=1);

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/residentTransaction.php';
require_once __DIR__ . '/sendSMS.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

const DR_STAGE_SUBMITTED = 'submitted';
const DR_STAGE_FOR_INTERVIEW = 'for_interview';
const DR_STAGE_FOR_INSPECTION = 'for_inspection';
const DR_STAGE_INSPECTION_FAILED = 'inspection_failed';
const DR_STAGE_REJECTED = 'rejected';
const DR_STAGE_FOR_PAYMENT = 'for_payment';
const DR_STAGE_PAYMENT_SUBMITTED = 'payment_submitted';
const DR_STAGE_PAYMENT_REJECTED = 'payment_rejected';
const DR_STAGE_PAYMENT_VERIFIED = 'payment_verified';
const DR_STAGE_CANCELLED = 'cancelled';
const DR_STAGE_READY_FOR_CLAIM = 'ready_for_claim';
const DR_STAGE_COMPLETED = 'completed';

function dr_now(): string {
    return date('Y-m-d H:i:s');
}

function dr_add_working_days(string $fromDateTime, int $workingDays): string {
    try {
        $date = new DateTime($fromDateTime);
    } catch (Throwable $e) {
        $date = new DateTime();
    }
    if ($workingDays <= 0) {
        return $date->format('Y-m-d H:i:s');
    }

    $added = 0;
    while ($added < $workingDays) {
        $date->modify('+1 day');
        $dayOfWeek = (int)$date->format('N');
        if ($dayOfWeek <= 5) {
            $added++;
        }
    }
    return $date->format('Y-m-d H:i:s');
}

function dr_safe_json(array $v): string {
    $j = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $j === false ? '{}' : $j;
}

function dr_respond_json(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo dr_safe_json($payload);
    exit;
}

function dr_column_exists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }
    $cacheKey = strtolower($tableSafe . '|' . $column);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$colEsc}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}

function dr_table_exists(mysqli $conn, string $table): bool {
    static $cache = [];
    $cacheKey = strtolower($table);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}

function dr_get_column_type(mysqli $conn, string $table, string $column, string $fallback): string {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return $fallback;
    }
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$colEsc}'");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && !empty($row['Type'])) {
            return (string)$row['Type'];
        }
    }
    return $fallback;
}

function dr_ensure_document_request_extensions(mysqli $conn): void {
    $columnsToEnsure = [
        "resident_name VARCHAR(191) DEFAULT NULL AFTER resident_id",
        "attachment_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER resident_name",
        "request_details LONGTEXT DEFAULT NULL AFTER attachment_id",
        "status_id_request INT(11) DEFAULT NULL AFTER request_details",
        "user_id_official_reviewed_by VARCHAR(12) DEFAULT NULL AFTER status_id_request",
        "user_id_official_released_by VARCHAR(12) DEFAULT NULL AFTER user_id_official_reviewed_by",
        "request_timestamp DATETIME DEFAULT NULL AFTER user_id_official_released_by",
        "review_timestamp DATETIME DEFAULT NULL AFTER request_timestamp",
        "release_timestamp DATETIME DEFAULT NULL AFTER review_timestamp",
        "document_validity DATETIME DEFAULT NULL AFTER release_timestamp",
        "qr_code_path VARCHAR(255) DEFAULT NULL AFTER document_validity",
        "issued_file_path VARCHAR(255) DEFAULT NULL AFTER qr_code_path",
    ];

    foreach ($columnsToEnsure as $definition) {
        if (!preg_match('/^([a-zA-Z0-9_]+)/', $definition, $m)) {
            continue;
        }
        $col = $m[1];
        if (!dr_column_exists($conn, 'documentrequesttbl', $col)) {
            $conn->query("ALTER TABLE documentrequesttbl ADD COLUMN $definition");
        }
    }
}

function dr_drop_documentrequest_indexes_for_column(mysqli $conn, string $column): void {
    $columnEsc = $conn->real_escape_string($column);
    $indexes = [];
    $res = $conn->query("SHOW INDEX FROM documentrequesttbl");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $idx = (string)($row['Key_name'] ?? '');
            $col = (string)($row['Column_name'] ?? '');
            if ($idx === '' || strtoupper($idx) === 'PRIMARY') {
                continue;
            }
            if (strcasecmp($col, $columnEsc) === 0) {
                $indexes[$idx] = true;
            }
        }
    }
    foreach (array_keys($indexes) as $idxName) {
        $safeIdx = preg_replace('/[^a-zA-Z0-9_]/', '', $idxName);
        if ($safeIdx === '') {
            continue;
        }
        $conn->query("ALTER TABLE documentrequesttbl DROP INDEX {$safeIdx}");
    }
}

function dr_remove_legacy_payment_columns_from_document_request(mysqli $conn): void {
    if (!dr_table_exists($conn, 'documentrequesttbl')) {
        return;
    }

    // Payment data should only live in financetransactiontbl.
    $legacyPaymentColumns = [
        'payment_method',
        'payment_proof_path',
        'payment_submitted_at',
        'payment_reference',
        'payment_deadline',
        'or_number',
        'amount',
        'finance_user_id',
        'finance_decision_at',
    ];

    foreach ($legacyPaymentColumns as $column) {
        if (!dr_column_exists($conn, 'documentrequesttbl', $column)) {
            continue;
        }
        dr_drop_documentrequest_indexes_for_column($conn, $column);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeColumn === '') {
            continue;
        }
        $conn->query("ALTER TABLE documentrequesttbl DROP COLUMN {$safeColumn}");
    }
}

function dr_ensure_request_child_tables(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $requestIdType = dr_get_column_type($conn, 'documentrequesttbl', 'request_id', 'VARCHAR(16)');
    $residentUserType = dr_get_column_type($conn, 'documentrequesttbl', 'resident_user_id', 'VARCHAR(12)');

    $conn->query("
        CREATE TABLE IF NOT EXISTS barangayidrequesttbl (
            barangay_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id {$requestIdType} NOT NULL,
            id_details LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (barangay_id),
            UNIQUE KEY uq_barangayid_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS issuancerequesttbl (
            certificate_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id {$requestIdType} NOT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            certificate_details LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (certificate_id),
            UNIQUE KEY uq_issuancereq_request (request_id),
            KEY idx_issuancereq_type (certificate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS clearancerequesttbl (
            clearance_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id {$requestIdType} NOT NULL,
            clearance_type VARCHAR(120) DEFAULT NULL,
            application_type VARCHAR(120) DEFAULT NULL,
            clearance_details LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (clearance_id),
            UNIQUE KEY uq_clearancereq_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS clearanceinspectiontbl (
            inspection_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            clearance_id BIGINT(20) UNSIGNED NOT NULL,
            inspector_name VARCHAR(191) DEFAULT NULL,
            date_inspected DATETIME DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            inspector_signature_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (inspection_id),
            KEY idx_inspection_clearance (clearance_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS clearancefeestbl (
            clearance_fee_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            clearance_id BIGINT(20) UNSIGNED NOT NULL,
            fee_type VARCHAR(120) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (clearance_fee_id),
            KEY idx_clearancefee_clearance (clearance_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS financetransactiontbl (
            transaction_id VARCHAR(10) NOT NULL,
            request_id {$requestIdType} NOT NULL,
            transaction_amount DECIMAL(12,2) DEFAULT NULL,
            applicant_lastname VARCHAR(120) DEFAULT NULL,
            applicant_firstname VARCHAR(120) DEFAULT NULL,
            applicant_middleInitial VARCHAR(10) DEFAULT NULL,
            payment_method VARCHAR(40) DEFAULT NULL,
            payment_proof_path VARCHAR(255) DEFAULT NULL,
            transaction_details LONGTEXT DEFAULT NULL,
            or_number VARCHAR(80) DEFAULT NULL,
            transaction_status_id INT(11) DEFAULT NULL,
            payment_deadline DATETIME DEFAULT NULL,
            payment_timestamp DATETIME DEFAULT NULL,
            finance_decision_at DATETIME DEFAULT NULL,
            user_id_employee_process {$residentUserType} DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (transaction_id),
            UNIQUE KEY uq_transaction_request (request_id),
            UNIQUE KEY uq_transaction_or_number (or_number),
            KEY idx_transaction_status (transaction_status_id),
            KEY idx_transaction_employee (user_id_employee_process)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (dr_table_exists($conn, 'financetransactiontbl') && !dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path')) {
        $conn->query("ALTER TABLE financetransactiontbl ADD COLUMN payment_proof_path VARCHAR(255) DEFAULT NULL AFTER payment_method");
    }
    if (dr_table_exists($conn, 'financetransactiontbl') && !dr_column_exists($conn, 'financetransactiontbl', 'finance_decision_at')) {
        $conn->query("ALTER TABLE financetransactiontbl ADD COLUMN finance_decision_at DATETIME DEFAULT NULL AFTER payment_timestamp");
    }

    $done = true;
}

function dr_ensure_table(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS documentrequesttbl (
            request_id VARCHAR(16) NOT NULL PRIMARY KEY,
            resident_user_id VARCHAR(12) DEFAULT NULL,
            resident_id VARCHAR(12) DEFAULT NULL,
            resident_name VARCHAR(191) DEFAULT NULL,
            attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            request_details LONGTEXT DEFAULT NULL,
            status_id_request INT(11) DEFAULT NULL,
            user_id_official_reviewed_by VARCHAR(12) DEFAULT NULL,
            user_id_official_released_by VARCHAR(12) DEFAULT NULL,
            request_timestamp DATETIME NOT NULL,
            review_timestamp DATETIME DEFAULT NULL,
            release_timestamp DATETIME DEFAULT NULL,
            document_validity DATETIME DEFAULT NULL,
            qr_code_path VARCHAR(255) DEFAULT NULL,
            issued_file_path VARCHAR(255) DEFAULT NULL,
            INDEX idx_docreq_resident_user (resident_user_id),
            INDEX idx_docreq_attachment_id (attachment_id),
            INDEX idx_docreq_status_request (status_id_request)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    $conn->query($sql);

    // Backward-compatible schema upgrades for already-existing tables.
    $columnsToEnsure = [
        "resident_user_id VARCHAR(12) DEFAULT NULL AFTER request_id",
        "resident_id VARCHAR(12) DEFAULT NULL AFTER resident_user_id",
        "resident_name VARCHAR(191) DEFAULT NULL AFTER resident_id",
        "attachment_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER resident_name",
        "request_details LONGTEXT DEFAULT NULL AFTER attachment_id",
        "status_id_request INT(11) DEFAULT NULL AFTER request_details",
        "user_id_official_reviewed_by VARCHAR(12) DEFAULT NULL AFTER status_id_request",
        "user_id_official_released_by VARCHAR(12) DEFAULT NULL AFTER user_id_official_reviewed_by",
        "request_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER user_id_official_released_by",
        "review_timestamp DATETIME DEFAULT NULL AFTER request_timestamp",
        "release_timestamp DATETIME DEFAULT NULL AFTER review_timestamp",
        "document_validity DATETIME DEFAULT NULL AFTER release_timestamp",
        "qr_code_path VARCHAR(255) DEFAULT NULL AFTER document_validity",
        "issued_file_path VARCHAR(255) DEFAULT NULL AFTER qr_code_path",
    ];

    foreach ($columnsToEnsure as $definition) {
        if (!preg_match('/^([a-zA-Z0-9_]+)/', $definition, $m)) {
            continue;
        }
        $col = $m[1];
        $colEsc = $conn->real_escape_string($col);
        $existsRes = $conn->query("SHOW COLUMNS FROM documentrequesttbl LIKE '{$colEsc}'");
        $exists = $existsRes instanceof mysqli_result && $existsRes->num_rows > 0;
        if (!$exists) {
            $conn->query("ALTER TABLE documentrequesttbl ADD COLUMN $definition");
        }
    }

    // Indexes for older tables.
    $indexNames = [];
    $idxRes = $conn->query("SHOW INDEX FROM documentrequesttbl");
    if ($idxRes instanceof mysqli_result) {
        while ($idx = $idxRes->fetch_assoc()) {
            $indexNames[] = (string)($idx['Key_name'] ?? '');
        }
    }
    if (!in_array('idx_docreq_resident_user', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_resident_user (resident_user_id)");
    }
    if (!in_array('idx_docreq_status_request', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_status_request (status_id_request)");
    }
    if (!in_array('idx_docreq_attachment_id', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_attachment_id (attachment_id)");
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp') && !in_array('idx_docreq_request_timestamp', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_request_timestamp (request_timestamp)");
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'stage') && !in_array('idx_docreq_stage_request_timestamp', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_request_timestamp (stage, request_timestamp)");
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') && !in_array('idx_docreq_submitted_at', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_submitted_at (submitted_at)");
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'stage') && dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') && !in_array('idx_docreq_stage_submitted_at', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_submitted_at (stage, submitted_at)");
    }

    // Backward-compatible rename handling.
    if (!dr_column_exists($conn, 'documentrequesttbl', 'status_id_request') && dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        $conn->query("ALTER TABLE documentrequesttbl CHANGE COLUMN status_id status_id_request INT(11) DEFAULT NULL");
    }

    dr_ensure_document_request_extensions($conn);
    dr_remove_legacy_payment_columns_from_document_request($conn);
    dr_ensure_payment_transaction_statuses($conn);
    dr_backfill_finance_transaction_statuses($conn, 5000);

    $fkName = 'fk_docreq_resident_user';
    $fkRefTable = null;
    $fkCheck = $conn->prepare("
        SELECT REFERENCED_TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentrequesttbl'
          AND COLUMN_NAME = 'resident_user_id'
          AND CONSTRAINT_NAME = ?
        LIMIT 1
    ");
    if ($fkCheck) {
        $fkCheck->bind_param('s', $fkName);
        $fkCheck->execute();
        $fkCheck->bind_result($fkRefTable);
        $fkCheck->fetch();
        $fkCheck->close();
    }

    if ($fkRefTable !== 'useraccountstbl') {
        // Convert legacy values where resident_user_id previously stored resident_id.
        $conn->query("
            UPDATE documentrequesttbl dr
            INNER JOIN residentinformationtbl r ON r.resident_id = dr.resident_user_id
            SET
                dr.resident_id = COALESCE(NULLIF(dr.resident_id, ''), r.resident_id),
                dr.resident_user_id = r.user_id
        ");
        $conn->query("
            UPDATE documentrequesttbl dr
            INNER JOIN residentinformationtbl r ON r.resident_id = dr.resident_id
            LEFT JOIN useraccountstbl u ON u.user_id = dr.resident_user_id
            SET dr.resident_user_id = r.user_id
            WHERE u.user_id IS NULL
        ");
        $conn->query("ALTER TABLE documentrequesttbl DROP FOREIGN KEY {$fkName}");
        $conn->query("
            ALTER TABLE documentrequesttbl
            ADD CONSTRAINT {$fkName}
            FOREIGN KEY (resident_user_id)
            REFERENCES useraccountstbl(user_id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ");
    }

    dr_ensure_request_child_tables($conn);

    $done = true;
}

function dr_generate_request_id(mysqli $conn): string {
    $prefix = 'DR' . date('ym');
    $like = $prefix . '%';
    $seqKey = 'DRID:' . $prefix;

    $conn->query("
        CREATE TABLE IF NOT EXISTS idsequencetbl (
            seq_key VARCHAR(64) NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $conn->query("ALTER TABLE idsequencetbl MODIFY seq_key VARCHAR(64) NOT NULL");

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(request_id, 4) AS UNSIGNED)), 0) + 1)
        FROM documentrequesttbl
        WHERE request_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    $stmt->bind_param('ss', $seqKey, $like);
    if (!$stmt->execute()) {
        $stmt->close();
        return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    $stmt->close();

    $next = (int)$conn->insert_id;
    if ($next <= 0 || $next > 9999) {
        return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function dr_normalize_document_type(string $value): string {
    $key = strtolower(trim($value));
    $map = [
        'cohabitation' => 'Certificate of Cohabitation',
        'indigency' => 'CertificateOfIndigency',
        'certificate of indigency' => 'CertificateOfIndigency',
        'certificateofindigency' => 'CertificateOfIndigency',
        'firsttimejobseeker' => 'First Time Job Seeker Certificate',
        'identity' => 'Certificate of Identity',
        'residency' => 'Certificate of Residency',
        'goodmoral' => 'Certificate of Good Moral',
    ];
    return $map[$key] ?? trim($value);
}

function dr_is_issuance_document_type(string $documentType): bool {
    $doc = strtolower(trim($documentType));
    if ($doc === '') {
        return false;
    }
    // Issuance department currently handles certificate documents.
    return strpos($doc, 'certificate') !== false;
}

function dr_canonical_document_type_key(string $value): string {
    $raw = strtolower(trim($value));
    return preg_replace('/[^a-z0-9]+/', '', $raw);
}

function dr_document_type_name_variants(string $documentType): array {
    $doc = trim($documentType);
    if ($doc === '') return [];
    $canonical = dr_canonical_document_type_key($doc);
    $variants = [$doc];
    if ($canonical === 'certificateofindigency') {
        $variants[] = 'Certificate of Indigency';
        $variants[] = 'CertificateOfIndigency';
    }
    return array_values(array_unique(array_filter(array_map('trim', $variants), static fn($v) => $v !== '')));
}

function dr_get_or_create_document_type_id(mysqli $conn, string $documentType, string $category = 'DocumentRequest'): ?int {
    static $cache = [];
    $variants = dr_document_type_name_variants($documentType);
    if (!$variants) return null;
    $cacheKey = strtolower(trim($category)) . '|' . dr_canonical_document_type_key((string)$variants[0]);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    foreach ($variants as $name) {
        $sel = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
        if (!$sel) continue;
        $sel->bind_param('s', $name);
        $sel->execute();
        $sel->bind_result($docTypeId);
        $ok = $sel->fetch();
        $sel->close();
        if ($ok && $docTypeId) {
            $cache[$cacheKey] = (int)$docTypeId;
            return $cache[$cacheKey];
        }
    }

    $createName = $variants[0];
    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, ?)");
    if (!$ins) return null;
    $ins->bind_param('ss', $createName, $category);
    $okIns = $ins->execute();
    $insertId = (int)$ins->insert_id;
    $ins->close();
    if ($okIns && $insertId > 0) {
        $cache[$cacheKey] = $insertId;
        return $cache[$cacheKey];
    }

    // Race-safe retry.
    foreach ($variants as $name) {
        $sel = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
        if (!$sel) continue;
        $sel->bind_param('s', $name);
        $sel->execute();
        $sel->bind_result($docTypeId);
        $ok = $sel->fetch();
        $sel->close();
        if ($ok && $docTypeId) {
            $cache[$cacheKey] = (int)$docTypeId;
            return $cache[$cacheKey];
        }
    }
    $cache[$cacheKey] = null;
    return null;
}

function dr_stage_label(string $stage): string {
    $labels = [
        DR_STAGE_SUBMITTED => 'Pending Verification',
        DR_STAGE_FOR_INTERVIEW => 'For Interview',
        DR_STAGE_FOR_INSPECTION => 'For Inspection',
        DR_STAGE_INSPECTION_FAILED => 'Inspection Failed',
        DR_STAGE_REJECTED => 'Rejected',
        DR_STAGE_FOR_PAYMENT => 'For Payment',
        DR_STAGE_PAYMENT_SUBMITTED => 'Pending Payment Verification',
        DR_STAGE_PAYMENT_REJECTED => 'Payment Rejected',
        DR_STAGE_PAYMENT_VERIFIED => 'Payment Verified',
        DR_STAGE_CANCELLED => 'Cancelled',
        DR_STAGE_READY_FOR_CLAIM => 'For Release',
        DR_STAGE_COMPLETED => 'Completed',
    ];
    return $labels[$stage] ?? $stage;
}

function dr_stage_to_request_status_names(string $stage): array {
    $map = [
        DR_STAGE_SUBMITTED => ['PendingVerification', 'PendingReview'],
        DR_STAGE_FOR_INTERVIEW => ['ForInterview'],
        DR_STAGE_FOR_INSPECTION => ['ForInspection'],
        DR_STAGE_INSPECTION_FAILED => ['InspectionFailed'],
        DR_STAGE_REJECTED => ['Rejected'],
        DR_STAGE_FOR_PAYMENT => ['ForPayment'],
        DR_STAGE_PAYMENT_SUBMITTED => ['PaymentSubmitted'],
        DR_STAGE_PAYMENT_REJECTED => ['PaymentRejected'],
        DR_STAGE_PAYMENT_VERIFIED => ['PaymentVerified'],
        DR_STAGE_CANCELLED => ['Cancelled', 'AutoCancelled', 'Rejected'],
        DR_STAGE_READY_FOR_CLAIM => ['ForRelease', 'ReadyForClaim'],
        DR_STAGE_COMPLETED => ['Completed'],
    ];
    return $map[$stage] ?? [];
}

function dr_status_name_to_stage(string $statusName): ?string {
    $key = strtolower(trim($statusName));
    $map = [
        'pendingverification' => DR_STAGE_SUBMITTED,
        'pendingreview' => DR_STAGE_SUBMITTED,
        'forinterview' => DR_STAGE_FOR_INTERVIEW,
        'forinspection' => DR_STAGE_FOR_INSPECTION,
        'inspectionfailed' => DR_STAGE_INSPECTION_FAILED,
        'rejected' => DR_STAGE_REJECTED,
        'forpayment' => DR_STAGE_FOR_PAYMENT,
        'paymentsubmitted' => DR_STAGE_PAYMENT_SUBMITTED,
        'paymentrejected' => DR_STAGE_PAYMENT_REJECTED,
        'paymentverified' => DR_STAGE_PAYMENT_VERIFIED,
        'cancelled' => DR_STAGE_CANCELLED,
        'autocancelled' => DR_STAGE_CANCELLED,
        'expired' => DR_STAGE_CANCELLED,
        'forrelease' => DR_STAGE_READY_FOR_CLAIM,
        'readyforclaim' => DR_STAGE_READY_FOR_CLAIM,
        'completed' => DR_STAGE_COMPLETED,
    ];
    return $map[$key] ?? null;
}

function dr_find_status_id(mysqli $conn, string $statusName, array $preferredTypes = []): ?int {
    $statusName = trim($statusName);
    if ($statusName === '') {
        return null;
    }

    if ($preferredTypes) {
        $in = implode(',', array_fill(0, count($preferredTypes), '?'));
        $sql = "SELECT status_id FROM statuslookuptbl WHERE status_name = ? AND status_type IN ($in) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $types = 's' . str_repeat('s', count($preferredTypes));
            $params = array_merge([$statusName], $preferredTypes);
            $refs = [];
            foreach ($params as $k => $v) {
                $refs[$k] = &$params[$k];
            }
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $stmt->bind_result($sid);
            if ($stmt->fetch()) {
                $stmt->close();
                return (int) $sid;
            }
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $statusName);
    $stmt->execute();
    $stmt->bind_result($sid);
    $ok = $stmt->fetch();
    $stmt->close();

    return $ok ? (int) $sid : null;
}

function dr_find_or_create_status_id(mysqli $conn, string $statusName, string $statusType): ?int {
    $statusName = trim($statusName);
    $statusType = trim($statusType);
    if ($statusName === '' || $statusType === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = ? AND status_type = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $statusName, $statusType);
        $stmt->execute();
        $stmt->bind_result($sid);
        if ($stmt->fetch()) {
            $stmt->close();
            return (int)$sid;
        }
        $stmt->close();
    }

    $ins = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if ($ins) {
        $ins->bind_param('ss', $statusName, $statusType);
        if ($ins->execute()) {
            $newId = (int)$ins->insert_id;
            $ins->close();
            if ($newId > 0) {
                return $newId;
            }
        } else {
            $ins->close();
        }
    }

    // Race-safe re-read fallback.
    $stmt2 = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = ? AND status_type = ? ORDER BY status_id ASC LIMIT 1");
    if (!$stmt2) {
        return null;
    }
    $stmt2->bind_param('ss', $statusName, $statusType);
    $stmt2->execute();
    $stmt2->bind_result($sid2);
    $ok = $stmt2->fetch();
    $stmt2->close();
    return $ok ? (int)$sid2 : null;
}

function dr_find_status_id_exact_type(mysqli $conn, string $statusName, string $statusType): ?int {
    $statusName = trim($statusName);
    $statusType = trim($statusType);
    if ($statusName === '' || $statusType === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = ? AND status_type = ? ORDER BY status_id ASC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $statusName, $statusType);
    $stmt->execute();
    $stmt->bind_result($sid);
    $ok = $stmt->fetch();
    $stmt->close();
    return $ok ? (int)$sid : null;
}

function dr_ensure_payment_transaction_statuses(mysqli $conn): array {
    $type = 'TransactionPayment';
    $map = [
        // Canonical unpaid state for transactions with no completed payment yet.
        'pending' => ['Unpaid', 'Pending', 'PendingReview'],
        'pending_verification' => ['PendingVerification', 'PaymentSubmitted', 'Pending Payment Verification'],
        'verified' => ['Verified', 'Approved'],
        'rejected' => ['Rejected', 'Denied'],
        'cancelled' => ['Cancelled', 'AutoCancelled'],
    ];

    $ids = [];
    foreach ($map as $key => $candidates) {
        $resolved = null;
        foreach ($candidates as $name) {
            $resolved = dr_find_status_id_exact_type($conn, $name, $type);
            if ($resolved !== null) {
                break;
            }
        }
        if ($resolved === null) {
            // Create canonical first candidate under TransactionPayment type.
            $resolved = dr_find_or_create_status_id($conn, $candidates[0], $type);
        }
        if ($resolved !== null) {
            $ids[$key] = $resolved;
        }
    }
    return $ids;
}

function dr_map_stage_to_transaction_status_id(mysqli $conn, string $stage): int {
    $paymentStatus = dr_ensure_payment_transaction_statuses($conn);
    $pendingPayment = $paymentStatus['pending'] ?? null;
    $pendingVerificationPayment = $paymentStatus['pending_verification'] ?? null;
    $verifiedPayment = $paymentStatus['verified'] ?? null;
    $rejectedPayment = $paymentStatus['rejected'] ?? null;
    $cancelledPayment = $paymentStatus['cancelled'] ?? null;

    if (in_array($stage, [DR_STAGE_SUBMITTED, DR_STAGE_FOR_INTERVIEW, DR_STAGE_FOR_INSPECTION, DR_STAGE_FOR_PAYMENT], true) && $pendingPayment !== null) {
        return $pendingPayment;
    }
    if ($stage === DR_STAGE_PAYMENT_SUBMITTED && $pendingVerificationPayment !== null) {
        return $pendingVerificationPayment;
    }
    if (in_array($stage, [DR_STAGE_PAYMENT_REJECTED], true) && $rejectedPayment !== null) {
        return $rejectedPayment;
    }
    if ($stage === DR_STAGE_CANCELLED && $cancelledPayment !== null) {
        return $cancelledPayment;
    }
    if (in_array($stage, [DR_STAGE_PAYMENT_VERIFIED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true) && $verifiedPayment !== null) {
        return $verifiedPayment;
    }

    $pending = dr_find_status_id($conn, 'PendingReview', ['Transaction', 'DocumentVerification', 'VerificationStatus'])
        ?? dr_find_status_id($conn, 'PendingReview', [])
        ?? 1;
    $verified = dr_find_status_id($conn, 'Verified', ['Transaction', 'DocumentVerification', 'VerificationStatus'])
        ?? dr_find_status_id($conn, 'Approved', ['Transaction', 'DocumentVerification', 'VerificationStatus'])
        ?? dr_find_status_id($conn, 'Verified', [])
        ?? $pending;
    $rejected = dr_find_status_id($conn, 'Rejected', ['Transaction', 'DocumentVerification', 'VerificationStatus'])
        ?? dr_find_status_id($conn, 'Denied', ['Transaction', 'DocumentVerification', 'VerificationStatus'])
        ?? dr_find_status_id($conn, 'Rejected', [])
        ?? $pending;

    if (in_array($stage, [DR_STAGE_REJECTED, DR_STAGE_PAYMENT_REJECTED, DR_STAGE_INSPECTION_FAILED, DR_STAGE_CANCELLED], true)) {
        return $rejected;
    }
    if ($stage === DR_STAGE_COMPLETED) {
        return $verified;
    }
    return $pending;
}

function dr_backfill_finance_transaction_statuses(mysqli $conn, int $limit = 3000): int {
    if (!dr_table_exists($conn, 'financetransactiontbl') || !dr_table_exists($conn, 'documentrequesttbl')) {
        return 0;
    }

    $limit = max(1, min(10000, $limit));
    $sql = "
        SELECT d.request_id, d.stage
        FROM documentrequesttbl d
        INNER JOIN financetransactiontbl f ON f.request_id = d.request_id
        ORDER BY f.updated_at DESC, f.request_id DESC
        LIMIT {$limit}
    ";
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        return 0;
    }

    $updated = 0;
    $upd = $conn->prepare("UPDATE financetransactiontbl SET transaction_status_id = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ? LIMIT 1");
    if (!$upd) {
        return 0;
    }

    while ($row = $res->fetch_assoc()) {
        $rid = trim((string)($row['request_id'] ?? ''));
        $stage = trim((string)($row['stage'] ?? ''));
        if ($rid === '') {
            continue;
        }
        $sid = dr_map_stage_to_transaction_status_id($conn, $stage);
        $upd->bind_param('is', $sid, $rid);
        if ($upd->execute()) {
            $updated++;
        }
    }
    $upd->close();
    return $updated;
}

function dr_find_request_status_id_by_stage(mysqli $conn, string $stage): ?int {
    $candidates = dr_stage_to_request_status_names($stage);
    foreach ($candidates as $statusName) {
        $sid = dr_find_status_id($conn, $statusName, ['DocumentVerification']);
        if ($sid !== null) return $sid;
        $sid = dr_find_status_id($conn, $statusName, []);
        if ($sid !== null) return $sid;
    }
    return null;
}

function dr_status_name_by_id(mysqli $conn, ?int $statusId): string {
    if (!$statusId || $statusId <= 0) return '';
    static $cache = [];
    if (isset($cache[$statusId])) {
        return $cache[$statusId];
    }
    $name = '';
    $stmt = $conn->prepare("SELECT status_name FROM statuslookuptbl WHERE status_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $statusId);
        $stmt->execute();
        $stmt->bind_result($nameRes);
        if ($stmt->fetch()) {
            $name = trim((string)$nameRes);
        }
        $stmt->close();
    }
    $cache[$statusId] = $name;
    return $name;
}

function dr_request_status_column(mysqli $conn): ?string {
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
        return 'status_id_request';
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        return 'status_id';
    }
    return null;
}

function dr_hydrate_request_derived_fields(mysqli $conn, array &$row, bool $includeIssuanceMeta = true): void {
    $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $requestId = trim((string)($row['request_id'] ?? ''));
    $issuanceMeta = ['certificate_type' => '', 'certificate_number' => '', 'verification_code' => ''];
    $currentDocType = trim((string)($row['document_type'] ?? ''));
    $payloadDocType = trim((string)($payload['document_type'] ?? ''));
    $needsIssuanceMeta = (
        (trim((string)($row['certificate_number'] ?? '')) === '')
        || (trim((string)($row['verification_code'] ?? '')) === '')
        || ($currentDocType === '' && $payloadDocType === '')
    );
    if ($includeIssuanceMeta && $needsIssuanceMeta && $requestId !== '' && dr_table_exists($conn, 'issuancerequesttbl')) {
        $issuanceMeta = dr_get_issuance_request_meta($conn, $requestId);
    }

    if ($currentDocType === '') {
        $docType = $payloadDocType;
        if ($docType === '') {
            $docType = $issuanceMeta['certificate_type'];
        }
        $row['document_type'] = $docType !== '' ? $docType : 'Certificate Request';
    }

    if (trim((string)($row['purpose'] ?? '')) === '') {
        $row['purpose'] = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
    }
    if (trim((string)($row['resident_name'] ?? '')) === '') {
        $row['resident_name'] = trim((string)($payload['resident_name'] ?? ''));
    }
    if (trim((string)($row['submitted_at'] ?? '')) === '') {
        $row['submitted_at'] = (string)($row['request_timestamp'] ?? '');
    }
    if (trim((string)($row['certificate_number'] ?? '')) === '') {
        $row['certificate_number'] = $issuanceMeta['certificate_number'];
    }
    if (trim((string)($row['verification_code'] ?? '')) === '') {
        $row['verification_code'] = $issuanceMeta['verification_code'];
    }
}

function dr_sync_stage_from_status_lookup(mysqli $conn, array &$row): void {
    $statusCol = isset($row['status_id_request']) ? 'status_id_request' : (isset($row['status_id']) ? 'status_id' : null);
    if ($statusCol === null) {
        return;
    }
    $statusId = (int)$row[$statusCol];
    if ($statusId <= 0) {
        return;
    }
    $statusName = dr_status_name_by_id($conn, $statusId);
    if ($statusName === '') {
        return;
    }
    $mappedStage = dr_status_name_to_stage($statusName);
    if ($mappedStage !== null) {
        $row['stage'] = $mappedStage;
    }
}

function dr_merge_finance_transaction_into_request(mysqli $conn, array &$row): void {
    $requestId = trim((string)($row['request_id'] ?? ''));
    if ($requestId === '' || !dr_table_exists($conn, 'financetransactiontbl')) {
        return;
    }

    $proofColumn = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
    $sql = $proofColumn
        ? "SELECT transaction_amount, payment_method, payment_proof_path, transaction_details, or_number, transaction_status_id, payment_deadline, payment_timestamp, finance_decision_at, user_id_employee_process FROM financetransactiontbl WHERE request_id = ? LIMIT 1"
        : "SELECT transaction_amount, payment_method, transaction_details, or_number, transaction_status_id, payment_deadline, payment_timestamp, finance_decision_at, user_id_employee_process FROM financetransactiontbl WHERE request_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $requestId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tx) {
        return;
    }

    $row['amount'] = isset($tx['transaction_amount']) ? (float)$tx['transaction_amount'] : null;
    $row['payment_method'] = (string)($tx['payment_method'] ?? '');
    if ($proofColumn) {
        $row['payment_proof_path'] = (string)($tx['payment_proof_path'] ?? '');
    }
    $row['or_number'] = (string)($tx['or_number'] ?? '');
    $txStatusId = isset($tx['transaction_status_id']) ? (int)$tx['transaction_status_id'] : 0;
    $row['payment_status_id'] = $txStatusId;
    $row['payment_status_name'] = $txStatusId > 0 ? dr_status_name_by_id($conn, $txStatusId) : '';
    $row['payment_deadline'] = (string)($tx['payment_deadline'] ?? '');
    $row['payment_submitted_at'] = (string)($tx['payment_timestamp'] ?? '');
    $row['finance_decision_at'] = (string)($tx['finance_decision_at'] ?? '');
    $row['finance_user_id'] = (string)($tx['user_id_employee_process'] ?? '');
    $txDetails = (string)($tx['transaction_details'] ?? '');
    if ($txDetails !== '') {
        $decoded = json_decode($txDetails, true);
        if (is_array($decoded)) {
            $ref = trim((string)($decoded['reference'] ?? ''));
            if ($ref !== '') {
                $row['payment_reference'] = $ref;
            }
        } elseif (preg_match('/\bReference:\s*(.+)$/mi', $txDetails, $m)) {
            $row['payment_reference'] = trim((string)($m[1] ?? ''));
        }
    }
}

function dr_sync_transaction(mysqli $conn, array $request): void {
    dr_hydrate_request_derived_fields($conn, $request);
    dr_sync_stage_from_status_lookup($conn, $request);
    $requestId = (string)($request['request_id'] ?? '');
    $accountUserId = (string)($request['resident_user_id'] ?? '');
    if ($requestId === '' || $accountUserId === '') {
        return;
    }

    $docType = (string)($request['document_type'] ?? 'Document Request');
    $purpose = trim((string)($request['purpose'] ?? ''));
    $stage = (string)($request['stage'] ?? DR_STAGE_SUBMITTED);
    $reason = trim((string)($request['status_remarks'] ?? ''));

    $description = 'Stage: ' . dr_stage_label($stage);
    if ($purpose !== '') {
        $description .= "\nPurpose: " . $purpose;
    }
    if ($reason !== '') {
        $description .= "\nReason: " . $reason;
    }

    $metadata = [
        'document_request_id' => $requestId,
        'stage' => $stage,
        'stage_label' => dr_stage_label($stage),
        'document_type' => $docType,
        'purpose' => $purpose,
        'or_number' => (string)($request['or_number'] ?? ''),
        'certificate_number' => (string)($request['certificate_number'] ?? ''),
    ];

    $statusId = dr_map_stage_to_transaction_status_id($conn, $stage);
    upsertResidentTransaction(
        $conn,
        $accountUserId,
        $accountUserId,
        'DOCUMENT_REQUEST',
        $requestId,
        'DOCUMENT_REQUEST',
        $docType,
        $statusId,
        $description,
        $metadata,
        null,
        null,
        null,
        null
    );

    dr_ensure_request_child_tables($conn);
    if (!dr_table_exists($conn, 'financetransactiontbl')) {
        return;
    }

    // Free documents must not exist in finance transactions.
    $configuredFee = dr_get_fee_amount_for_document_type($conn, $docType);
    $isFreeDocument = ($configuredFee !== null && (float)$configuredFee <= 0.0);
    if ($isFreeDocument) {
        $del = $conn->prepare("DELETE FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($del) {
            $del->bind_param('s', $requestId);
            $del->execute();
            $del->close();
        }
        return;
    }

    $existingId = '';
    $sel = $conn->prepare("SELECT transaction_id FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
    if ($sel) {
        $sel->bind_param('s', $requestId);
        $sel->execute();
        $sel->bind_result($existingId);
        $sel->fetch();
        $sel->close();
    }
    $transactionId = trim($existingId);
    if ($transactionId === '') {
        $transactionId = GenerateTransactionID($conn, 'financetransactiontbl', 'transaction_id');
    }

    $payload = json_decode((string)($request['request_details'] ?? $request['payload_json'] ?? '{}'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $lastName = trim((string)($payload['last_name'] ?? $payload['lastname'] ?? ''));
    $firstName = trim((string)($payload['first_name'] ?? $payload['firstname'] ?? ''));
    $middle = trim((string)($payload['middle_name'] ?? $payload['middlename'] ?? ''));
    $middleInitial = $middle !== '' ? strtoupper(substr($middle, 0, 1)) : '';
    $employee = trim((string)($request['finance_user_id'] ?? $request['personnel_user_id'] ?? ''));
    if ($employee === '') {
        $employee = null;
    }

    $txAmount = isset($request['amount']) ? (float)$request['amount'] : null;
    if (($txAmount === null || $txAmount <= 0.0) && $configuredFee !== null && (float)$configuredFee > 0.0) {
        $txAmount = (float)$configuredFee;
    }
    $txOr = trim((string)($request['or_number'] ?? ''));
    if ($txOr === '') {
        $txOr = null;
    }
    $txPaymentMethod = trim((string)($request['payment_method'] ?? ''));
    if ($txPaymentMethod === '') {
        $txPaymentMethod = null;
    }
    $txProofPath = trim((string)($request['payment_proof_path'] ?? ''));
    if ($txProofPath === '') {
        $txProofPath = null;
    }
    $txPaymentAt = trim((string)($request['payment_submitted_at'] ?? ''));
    if ($txPaymentAt === '') {
        $txPaymentAt = null;
    }
    $txFinanceDecisionAt = trim((string)($request['finance_decision_at'] ?? ''));
    if ($txFinanceDecisionAt === '') {
        $stmtDecisionAt = $conn->prepare("SELECT finance_decision_at FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($stmtDecisionAt) {
            $stmtDecisionAt->bind_param('s', $requestId);
            $stmtDecisionAt->execute();
            $decisionRow = $stmtDecisionAt->get_result()->fetch_assoc();
            $stmtDecisionAt->close();
            $txFinanceDecisionAt = trim((string)($decisionRow['finance_decision_at'] ?? ''));
        }
    }
    if ($txFinanceDecisionAt === '') {
        $txFinanceDecisionAt = null;
    }
    $txDeadline = trim((string)($request['payment_deadline'] ?? ''));
    if ($txDeadline === '') {
        $stmtDeadline = $conn->prepare("SELECT payment_deadline FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($stmtDeadline) {
            $stmtDeadline->bind_param('s', $requestId);
            $stmtDeadline->execute();
            $deadlineRow = $stmtDeadline->get_result()->fetch_assoc();
            $stmtDeadline->close();
            $txDeadline = trim((string)($deadlineRow['payment_deadline'] ?? ''));
        }
    }
    if ($txDeadline === '') {
        $txDeadline = null;
    }
    $txReference = trim((string)($request['payment_reference'] ?? ''));
    if ($txReference === '') {
        $stmtRef = $conn->prepare("SELECT transaction_details FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($stmtRef) {
            $stmtRef->bind_param('s', $requestId);
            $stmtRef->execute();
            $refRow = $stmtRef->get_result()->fetch_assoc();
            $stmtRef->close();
            $rawDetails = (string)($refRow['transaction_details'] ?? '');
            if ($rawDetails !== '') {
                $decodedRef = json_decode($rawDetails, true);
                if (is_array($decodedRef)) {
                    $txReference = trim((string)($decodedRef['reference'] ?? ''));
                } elseif (preg_match('/\bReference:\s*(.+)$/mi', $rawDetails, $m)) {
                    $txReference = trim((string)($m[1] ?? ''));
                }
            }
        }
    }

    $transactionDetailsPayload = [
        'document_type' => $docType,
        'purpose' => $purpose,
        'reason' => $reason,
        'reference' => $txReference,
        'stage' => $stage,
        'stage_label' => dr_stage_label($stage),
    ];
    $transactionDetailsPayload = array_filter(
        $transactionDetailsPayload,
        static fn($v) => !($v === null || $v === '')
    );
    $transactionDetails = dr_safe_json($transactionDetailsPayload);

    $statusId = dr_map_stage_to_transaction_status_id($conn, $stage);
    $proofColumn = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
    $sql = "
        INSERT INTO financetransactiontbl (
            transaction_id, request_id, transaction_amount, applicant_lastname, applicant_firstname, applicant_middleInitial,
            payment_method" . ($proofColumn ? ", payment_proof_path" : "") . ", transaction_details, or_number, transaction_status_id, payment_deadline, payment_timestamp, finance_decision_at, user_id_employee_process
        ) VALUES (?, ?, ?, ?, ?, ?, ?, " . ($proofColumn ? "?, " : "") . "?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            transaction_amount = VALUES(transaction_amount),
            applicant_lastname = VALUES(applicant_lastname),
            applicant_firstname = VALUES(applicant_firstname),
            applicant_middleInitial = VALUES(applicant_middleInitial),
            payment_method = VALUES(payment_method),
            " . ($proofColumn ? "payment_proof_path = VALUES(payment_proof_path)," : "") . "
            transaction_details = VALUES(transaction_details),
            or_number = VALUES(or_number),
            transaction_status_id = VALUES(transaction_status_id),
            payment_deadline = VALUES(payment_deadline),
            payment_timestamp = VALUES(payment_timestamp),
            finance_decision_at = VALUES(finance_decision_at),
            user_id_employee_process = VALUES(user_id_employee_process),
            updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($proofColumn) {
            $stmt->bind_param(
                'ssdssssssisssss',
                $transactionId,
                $requestId,
                $txAmount,
                $lastName,
                $firstName,
                $middleInitial,
                $txPaymentMethod,
                $txProofPath,
                $transactionDetails,
                $txOr,
                $statusId,
                $txDeadline,
                $txPaymentAt,
                $txFinanceDecisionAt,
                $employee
            );
        } else {
            $stmt->bind_param(
                'ssdsssssisssss',
                $transactionId,
                $requestId,
                $txAmount,
                $lastName,
                $firstName,
                $middleInitial,
                $txPaymentMethod,
                $transactionDetails,
                $txOr,
                $statusId,
                $txDeadline,
                $txPaymentAt,
                $txFinanceDecisionAt,
                $employee
            );
        }
        $stmt->execute();
        $stmt->close();
    }
}

function dr_fetch_request(mysqli $conn, string $requestId): ?array {
    $stmt = $conn->prepare("SELECT * FROM documentrequesttbl WHERE request_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($row)) {
        dr_ensure_issuance_row_for_request($conn, $row);
        dr_hydrate_request_derived_fields($conn, $row);
        dr_merge_finance_transaction_into_request($conn, $row);
        dr_sync_stage_from_status_lookup($conn, $row);
    }
    return $row ?: null;
}

function dr_update_stage(mysqli $conn, string $requestId, string $stage, array $patch = []): ?array {
    $currentRow = dr_fetch_request($conn, $requestId);
    $currentStage = (string)($currentRow['stage'] ?? '');
    $currentPaymentDeadline = trim((string)($currentRow['payment_deadline'] ?? ''));

    $certNumberPatch = array_key_exists('certificate_number', $patch) ? (string)$patch['certificate_number'] : null;
    $verificationPatch = array_key_exists('verification_code', $patch) ? (string)$patch['verification_code'] : null;
    if ($certNumberPatch !== null || $verificationPatch !== null) {
        dr_upsert_issuance_identifiers($conn, $requestId, $certNumberPatch, $verificationPatch);
    }

    $allowedColumns = [
        'status_remarks',
        'issued_file_path',
        'qr_code_path',
        'personnel_user_id',
        'personnel_decision_at',
        'ready_at',
        'completed_at',
    ];

    // Backward compatibility: old callers may still send status_reason.
    if (!array_key_exists('status_remarks', $patch) && array_key_exists('status_reason', $patch)) {
        $patch['status_remarks'] = $patch['status_reason'];
    }

    $sets = [];
    $types = '';
    $vals = [];
    if (dr_column_exists($conn, 'documentrequesttbl', 'updated_at')) {
        $sets[] = 'updated_at = ?';
        $types .= 's';
        $vals[] = dr_now();
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'stage')) {
        $sets[] = 'stage = ?';
        $types .= 's';
        $vals[] = $stage;
    }
    $paymentPatchKeys = [
        'payment_method',
        'payment_proof_path',
        'payment_submitted_at',
        'payment_reference',
        'amount',
        'or_number',
        'payment_deadline',
        'finance_user_id',
        'finance_decision_at',
    ];
    $paymentPatch = [];

    foreach ($patch as $k => $v) {
        if (in_array($k, $paymentPatchKeys, true)) {
            $paymentPatch[$k] = $v;
        }
        if (!in_array($k, $allowedColumns, true)) {
            continue;
        }
        if (!dr_column_exists($conn, 'documentrequesttbl', $k)) {
            continue;
        }
        $sets[] = "$k = ?";
        if (in_array($k, ['amount'], true)) {
            $types .= 'd';
            $vals[] = (float) $v;
        } else {
            $types .= 's';
            $vals[] = $v === null ? null : (string) $v;
        }
    }

    if ($stage === DR_STAGE_FOR_PAYMENT && !array_key_exists('payment_deadline', $paymentPatch)) {
        if ($currentPaymentDeadline === '' || $currentStage !== DR_STAGE_FOR_PAYMENT) {
            $paymentPatch['payment_deadline'] = dr_add_working_days(dr_now(), 5);
        } else {
            $paymentPatch['payment_deadline'] = $currentPaymentDeadline;
        }
    }

    $requestStatusId = dr_find_request_status_id_by_stage($conn, $stage);
    if ($requestStatusId === null) {
        $requestStatusId = dr_map_stage_to_transaction_status_id($conn, $stage);
    }
    $statusCol = dr_request_status_column($conn);
    if ($statusCol !== null) {
        $sets[] = $statusCol . ' = ?';
        $types .= 'i';
        $vals[] = $requestStatusId;
    }

    $actorUserId = null;
    if (!empty($patch['finance_user_id'])) {
        $actorUserId = (string)$patch['finance_user_id'];
    } elseif (!empty($patch['personnel_user_id'])) {
        $actorUserId = (string)$patch['personnel_user_id'];
    }

    if (dr_column_exists($conn, 'documentrequesttbl', 'review_timestamp')
        && in_array($stage, [DR_STAGE_FOR_INTERVIEW, DR_STAGE_FOR_INSPECTION, DR_STAGE_INSPECTION_FAILED, DR_STAGE_REJECTED, DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED, DR_STAGE_CANCELLED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        $sets[] = 'review_timestamp = ?';
        $types .= 's';
        $vals[] = dr_now();
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'release_timestamp')
        && in_array($stage, [DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        $sets[] = 'release_timestamp = ?';
        $types .= 's';
        $vals[] = dr_now();
    }
    if ($actorUserId !== null && dr_column_exists($conn, 'documentrequesttbl', 'user_id_official_reviewed_by')) {
        $sets[] = 'user_id_official_reviewed_by = ?';
        $types .= 's';
        $vals[] = $actorUserId;
    }
    if ($actorUserId !== null
        && dr_column_exists($conn, 'documentrequesttbl', 'user_id_official_released_by')
        && in_array($stage, [DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        $sets[] = 'user_id_official_released_by = ?';
        $types .= 's';
        $vals[] = $actorUserId;
    }

    $types .= 's';
    $vals[] = $requestId;

    if (empty($sets)) {
        // No writable columns available; return current row instead of hard-failing.
        return dr_fetch_request($conn, $requestId);
    }

    $sql = 'UPDATE documentrequesttbl SET ' . implode(', ', $sets) . ' WHERE request_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $refs = [];
    foreach ($vals as $i => $value) {
        $refs[$i] = &$vals[$i];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();

    $row = dr_fetch_request($conn, $requestId);
    if ($row) {
        dr_ensure_issuance_row_for_request($conn, $row);
        foreach ($paymentPatch as $k => $v) {
            $row[$k] = $v;
        }
        dr_sync_transaction($conn, $row);
        dr_merge_finance_transaction_into_request($conn, $row);
    }
    return $row;
}

function dr_cancel_overdue_payment_requests(mysqli $conn, ?string $onlyRequestId = null): int {
    dr_ensure_request_child_tables($conn);
    if (!dr_table_exists($conn, 'financetransactiontbl')) {
        return 0;
    }

    $sql = "
        SELECT request_id
        FROM financetransactiontbl
        WHERE payment_deadline IS NOT NULL
          AND payment_deadline <> '0000-00-00 00:00:00'
          AND payment_deadline < NOW()
          AND (payment_timestamp IS NULL OR payment_timestamp = '0000-00-00 00:00:00')
    ";
    $params = [];
    $types = '';
    if ($onlyRequestId !== null && trim($onlyRequestId) !== '') {
        $sql .= " AND request_id = ?";
        $types = 's';
        $params[] = trim($onlyRequestId);
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
    $requestIds = [];
    while ($row = $rs->fetch_assoc()) {
        $rid = trim((string)($row['request_id'] ?? ''));
        if ($rid !== '') {
            $requestIds[] = $rid;
        }
    }
    $stmt->close();

    $cancelled = 0;
    foreach (array_values(array_unique($requestIds)) as $rid) {
        $req = dr_fetch_request($conn, $rid);
        if (!$req) {
            continue;
        }
        $stage = trim((string)($req['stage'] ?? ''));
        if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
            continue;
        }
        $updated = dr_update_stage($conn, $rid, DR_STAGE_CANCELLED, [
            'status_remarks' => 'Automatically cancelled: payment was not completed within 5 working days.',
        ]);
        if ($updated) {
            $cancelled++;
        }
    }

    return $cancelled;
}

function dr_backfill_missing_finance_transactions(mysqli $conn, int $limit = 500): int {
    dr_ensure_request_child_tables($conn);
    if (!dr_table_exists($conn, 'documentrequesttbl') || !dr_table_exists($conn, 'financetransactiontbl')) {
        return 0;
    }

    $limit = max(1, min(5000, $limit));
    $sql = "
        SELECT d.request_id
        FROM documentrequesttbl d
        LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
        WHERE f.request_id IS NULL
        ORDER BY d.request_timestamp DESC, d.request_id DESC
        LIMIT {$limit}
    ";
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        return 0;
    }

    $synced = 0;
    while ($row = $res->fetch_assoc()) {
        $rid = trim((string)($row['request_id'] ?? ''));
        if ($rid === '') {
            continue;
        }
        $requestRow = dr_fetch_request($conn, $rid);
        if (!$requestRow) {
            continue;
        }
        dr_sync_transaction($conn, $requestRow);
        $synced++;
    }

    return $synced;
}

function dr_prune_free_document_finance_transactions(mysqli $conn, int $limit = 3000): int {
    dr_ensure_request_child_tables($conn);
    if (!dr_table_exists($conn, 'documentrequesttbl') || !dr_table_exists($conn, 'financetransactiontbl')) {
        return 0;
    }

    $limit = max(1, min(10000, $limit));
    $sql = "
        SELECT d.request_id, d.document_type
        FROM financetransactiontbl f
        INNER JOIN documentrequesttbl d ON d.request_id = f.request_id
        ORDER BY f.updated_at DESC, f.request_id DESC
        LIMIT {$limit}
    ";
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        return 0;
    }

    $deleted = 0;
    $del = $conn->prepare("DELETE FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
    if (!$del) {
        return 0;
    }

    while ($row = $res->fetch_assoc()) {
        $requestId = trim((string)($row['request_id'] ?? ''));
        $docType = trim((string)($row['document_type'] ?? ''));
        if ($requestId === '' || $docType === '') {
            continue;
        }
        $fee = dr_get_fee_amount_for_document_type($conn, $docType);
        if ($fee === null || (float)$fee > 0.0) {
            continue;
        }
        $del->bind_param('s', $requestId);
        if ($del->execute()) {
            $deleted++;
        }
    }

    $del->close();
    return $deleted;
}

function dr_make_certificate_number(string $orNumber): string {
    $cleanOr = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($orNumber));
    if ($cleanOr === '') {
        $cleanOr = 'NA';
    }
    return 'BSJ-' . date('Ymd') . '-' . $cleanOr;
}

function dr_send_notification(mysqli $conn, array $request, string $subject, string $message): void {
    $residentUserId = trim((string)($request['resident_user_id'] ?? ''));
    if ($residentUserId === '') {
        $residentUserId = trim((string)($request['user_id'] ?? ''));
    }
    if ($residentUserId === '') {
        $residentId = trim((string)($request['resident_id'] ?? ''));
        if ($residentId !== '') {
            $fallbackUserId = dr_get_user_id_from_resident_id($conn, $residentId);
            if ($fallbackUserId !== null) {
                $residentUserId = trim($fallbackUserId);
            }
        }
    }
    if ($residentUserId === '') {
        return;
    }

    $stmt = $conn->prepare("SELECT email, phone_number, email_verify FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $residentUserId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$acct) {
        return;
    }

    $phone = preg_replace('/[^0-9]/', '', (string)($acct['phone_number'] ?? ''));
    if ($phone !== '') {
        @sendSMS($phone, $message);
    }

    $email = trim((string)($acct['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $requestId = trim((string)($request['request_id'] ?? ''));
        $documentType = trim((string)($request['document_type'] ?? ''));
        if ($documentType === '') {
            $documentType = 'Document Request';
        }
        $purpose = trim((string)($request['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = '-';
        }
        $statusLabel = dr_stage_label((string)($request['stage'] ?? ''));
        if ($statusLabel === '') {
            $statusLabel = '-';
        }
        $rejectionReason = trim((string)($request['status_remarks'] ?? $request['status_reason'] ?? ''));
        $amountValue = $request['amount'] ?? null;
        $amount = '';
        if ($amountValue !== null && $amountValue !== '' && is_numeric((string)$amountValue)) {
            $amount = 'PHP ' . number_format((float)$amountValue, 2);
        }

        $mailConfig = require __DIR__ . '/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        $sender->send([
            'type' => 'transaction',
            'to' => $email,
            'subject' => $subject,
            'data' => [
                'title' => $subject,
                'headline' => $subject,
                'message' => $message,
                'transaction_no' => $requestId,
                'transactionId' => $requestId,
                'document_type' => $documentType,
                'documentType' => $documentType,
                'purpose' => $purpose,
                'amount' => $amount,
                'status' => $statusLabel,
                'rejection_reason' => $rejectionReason,
                'updated_at' => dr_now(),
            ],
            'template' => 'emails/transactionNotification.php',
        ]);
    }
}

function dr_get_resident_id(mysqli $conn, string $userId): ?string {
    $stmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $stmt->bind_result($residentId);
    $ok = $stmt->fetch();
    $stmt->close();
    return $ok ? (string)$residentId : null;
}

function dr_get_user_id_from_resident_id(mysqli $conn, string $residentId): ?string {
    $residentId = trim($residentId);
    if ($residentId === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT user_id FROM residentinformationtbl WHERE resident_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $residentId);
    $stmt->execute();
    $stmt->bind_result($userId);
    $ok = $stmt->fetch();
    $stmt->close();
    return $ok ? (string)$userId : null;
}

function dr_ensure_certificate_request_table(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $requestIdType = 'BIGINT(20)';
    $colRes = $conn->query("SHOW COLUMNS FROM documentrequesttbl LIKE 'request_id'");
    if ($colRes instanceof mysqli_result) {
        $row = $colRes->fetch_assoc();
        if ($row && !empty($row['Type'])) {
            $requestIdType = (string)$row['Type'];
        }
    }

    // Build table with request_id type matching documentrequesttbl.request_id.
    $sql = "
        CREATE TABLE IF NOT EXISTS issuancerequesttbl (
            certificate_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id {$requestIdType} NOT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            certificate_details LONGTEXT DEFAULT NULL,
            certificate_number VARCHAR(64) DEFAULT NULL,
            verification_code VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (certificate_id),
            UNIQUE KEY uq_issuancereq_request (request_id),
            KEY idx_issuancereq_type (certificate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql);

    // Align request_id column type with documentrequesttbl when table already exists.
    $issuanceRequestType = null;
    $issuanceColRes = $conn->query("SHOW COLUMNS FROM issuancerequesttbl LIKE 'request_id'");
    if ($issuanceColRes instanceof mysqli_result) {
        $issuanceCol = $issuanceColRes->fetch_assoc();
        if ($issuanceCol && !empty($issuanceCol['Type'])) {
            $issuanceRequestType = (string)$issuanceCol['Type'];
        }
    }
    if ($issuanceRequestType !== null && strtolower($issuanceRequestType) !== strtolower($requestIdType)) {
        // Best effort only; if conversion cannot be applied, keep current type and continue.
        $conn->query("ALTER TABLE issuancerequesttbl MODIFY COLUMN request_id {$requestIdType} NOT NULL");
    }

    // Add FK if not present and types are compatible (best effort).
    $fkName = 'fk_issuancereq_request_id';
    $fkCheck = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'issuancerequesttbl'
          AND CONSTRAINT_NAME = ?
    ");
    if ($fkCheck) {
        $fkCheck->bind_param('s', $fkName);
        $fkCheck->execute();
        $fkCheck->bind_result($fkCount);
        $fkCheck->fetch();
        $fkCheck->close();

        if ((int)$fkCount === 0) {
            // Try to add FK; ignore failure on incompatible existing schema.
            $conn->query("
                ALTER TABLE issuancerequesttbl
                ADD CONSTRAINT {$fkName}
                FOREIGN KEY (request_id) REFERENCES documentrequesttbl(request_id)
                ON DELETE CASCADE ON UPDATE CASCADE
            ");
        }
    }

    if (!dr_column_exists($conn, 'issuancerequesttbl', 'verification_code')) {
        $conn->query("ALTER TABLE issuancerequesttbl ADD COLUMN verification_code VARCHAR(80) DEFAULT NULL AFTER certificate_details");
    }

    $done = true;
}

function dr_issuance_table_candidates(mysqli $conn): array {
    $tables = [];
    if (dr_table_exists($conn, 'issuancerequesttbl')) {
        $tables[] = 'issuancerequesttbl';
    }
    if (dr_table_exists($conn, 'issuancerequeststbl')) {
        $tables[] = 'issuancerequeststbl';
    }
    return $tables;
}

function dr_upsert_certificate_request_into_table(mysqli $conn, string $table, string $requestId, string $certificateType, string $certificateDetails): void {
    if (!dr_table_exists($conn, $table)) {
        return;
    }
    if (!dr_column_exists($conn, $table, 'request_id')
        || !dr_column_exists($conn, $table, 'certificate_type')
        || !dr_column_exists($conn, $table, 'certificate_details')) {
        return;
    }

    $duplicateUpdates = [
        'certificate_type = VALUES(certificate_type)',
        'certificate_details = VALUES(certificate_details)',
    ];
    if (dr_column_exists($conn, $table, 'updated_at')) {
        $duplicateUpdates[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $stmt = $conn->prepare("
        INSERT INTO {$table} (request_id, certificate_type, certificate_details)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            " . implode(",\n            ", $duplicateUpdates) . "
    ");
    if (!$stmt) {
        error_log("[documentRequestWorkflow][issuance] prepare failed for {$table}: " . $conn->error);
        return;
    }
    $stmt->bind_param('sss', $requestId, $certificateType, $certificateDetails);
    if (!$stmt->execute()) {
        error_log("[documentRequestWorkflow][issuance] upsert failed in {$table}: " . $stmt->error . ' | request_id=' . $requestId);
    }
    $stmt->close();
}

function dr_upsert_certificate_request(mysqli $conn, $requestId, string $certificateType, string $certificateDetails): void {
    dr_ensure_certificate_request_table($conn);
    $requestIdParam = (string)$requestId;
    foreach (dr_issuance_table_candidates($conn) as $table) {
        dr_upsert_certificate_request_into_table($conn, $table, $requestIdParam, $certificateType, $certificateDetails);
    }
}

function dr_ensure_issuance_row_for_request(mysqli $conn, array $requestRow): void {
    $requestId = trim((string)($requestRow['request_id'] ?? ''));
    if ($requestId === '') {
        return;
    }

    $detailsRaw = (string)($requestRow['request_details'] ?? $requestRow['payload_json'] ?? '{}');
    $payload = json_decode($detailsRaw, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $docType = trim((string)($requestRow['document_type'] ?? ''));
    if ($docType === '') {
        $docType = trim((string)($payload['document_type'] ?? ''));
    }
    $docType = dr_normalize_document_type($docType);
    if (!dr_is_issuance_document_type($docType)) {
        return;
    }

    $purpose = trim((string)($requestRow['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
    }

    $certificateDetails = dr_safe_json([
        'source' => 'issuance_request_enforcement',
        'purpose' => $purpose,
        'submitted_payload' => $payload,
        'resident_id' => trim((string)($requestRow['resident_id'] ?? '')),
        'resident_user_id' => trim((string)($requestRow['resident_user_id'] ?? $requestRow['user_id'] ?? '')),
    ]);
    dr_upsert_certificate_request($conn, $requestId, $docType, $certificateDetails);
}

function dr_backfill_missing_issuance_requests(mysqli $conn, int $limit = 1000): int {
    if (!dr_table_exists($conn, 'documentrequesttbl')) {
        return 0;
    }
    dr_ensure_certificate_request_table($conn);
    if (!dr_table_exists($conn, 'issuancerequesttbl')) {
        return 0;
    }

    $limit = max(1, min($limit, 10000));
    $hasDocType = dr_column_exists($conn, 'documentrequesttbl', 'document_type');
    $hasPurpose = dr_column_exists($conn, 'documentrequesttbl', 'purpose');
    $hasRequestDetails = dr_column_exists($conn, 'documentrequesttbl', 'request_details');
    $hasResidentId = dr_column_exists($conn, 'documentrequesttbl', 'resident_id');
    $hasResidentUserId = dr_column_exists($conn, 'documentrequesttbl', 'resident_user_id');

    $selectCols = ['d.request_id'];
    if ($hasDocType) $selectCols[] = 'd.document_type';
    if ($hasPurpose) $selectCols[] = 'd.purpose';
    if ($hasRequestDetails) $selectCols[] = 'd.request_details';
    if ($hasResidentId) $selectCols[] = 'd.resident_id';
    if ($hasResidentUserId) $selectCols[] = 'd.resident_user_id';

    $sql = "
        SELECT " . implode(', ', $selectCols) . "
        FROM documentrequesttbl d
        LEFT JOIN issuancerequesttbl i ON i.request_id = d.request_id
        WHERE i.request_id IS NULL
        ORDER BY d.request_id ASC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    $created = 0;
    foreach ($rows as $row) {
        $requestId = trim((string)($row['request_id'] ?? ''));
        if ($requestId === '') {
            continue;
        }

        $payload = [];
        if ($hasRequestDetails) {
            $decoded = json_decode((string)($row['request_details'] ?? '{}'), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $docType = trim((string)($row['document_type'] ?? ''));
        if ($docType === '') {
            $docType = trim((string)($payload['document_type'] ?? ''));
        }
        $docType = dr_normalize_document_type($docType);
        if (!dr_is_issuance_document_type($docType)) {
            continue;
        }

        $purpose = trim((string)($row['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
        }

        $certificateDetails = dr_safe_json([
            'source' => 'issuance_backfill',
            'purpose' => $purpose,
            'submitted_payload' => $payload,
            'resident_id' => trim((string)($row['resident_id'] ?? '')),
            'resident_user_id' => trim((string)($row['resident_user_id'] ?? '')),
        ]);

        dr_upsert_certificate_request($conn, $requestId, $docType, $certificateDetails);
        $created++;
    }

    return $created;
}

function dr_get_issuance_request_meta(mysqli $conn, string $requestId): array {
    static $cache = [];
    dr_ensure_certificate_request_table($conn);
    $rid = trim($requestId);
    if ($rid === '') {
        return [
            'certificate_type' => '',
            'certificate_number' => '',
            'verification_code' => '',
        ];
    }
    if (array_key_exists($rid, $cache)) {
        return $cache[$rid];
    }
    $meta = [
        'certificate_type' => '',
        'certificate_number' => '',
        'verification_code' => '',
    ];
    foreach (dr_issuance_table_candidates($conn) as $table) {
        if (!dr_column_exists($conn, $table, 'request_id') || !dr_column_exists($conn, $table, 'certificate_type')) {
            continue;
        }
        $selectCols = ['certificate_type'];
        if (dr_column_exists($conn, $table, 'certificate_number')) {
            $selectCols[] = 'certificate_number';
        }
        if (dr_column_exists($conn, $table, 'verification_code')) {
            $selectCols[] = 'verification_code';
        }
        $stmt = $conn->prepare("
            SELECT " . implode(', ', $selectCols) . "
            FROM {$table}
            WHERE request_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $rid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            continue;
        }
        $meta['certificate_type'] = trim((string)($row['certificate_type'] ?? ''));
        $meta['certificate_number'] = trim((string)($row['certificate_number'] ?? ''));
        $meta['verification_code'] = trim((string)($row['verification_code'] ?? ''));
        $cache[$rid] = $meta;
        return $cache[$rid];
    }

    $cache[$rid] = $meta;
    return $cache[$rid];
}

function dr_upsert_issuance_identifiers(mysqli $conn, string $requestId, ?string $certificateNumber, ?string $verificationCode): void {
    dr_ensure_certificate_request_table($conn);
    $requestId = trim($requestId);
    if ($requestId === '') {
        return;
    }

    $certNo = $certificateNumber !== null ? trim($certificateNumber) : '';
    $vc = $verificationCode !== null ? trim($verificationCode) : '';
    $existing = dr_get_issuance_request_meta($conn, $requestId);
    $certType = $existing['certificate_type'] !== '' ? $existing['certificate_type'] : 'Certificate Request';

    foreach (dr_issuance_table_candidates($conn) as $table) {
        if (!dr_column_exists($conn, $table, 'request_id')
            || !dr_column_exists($conn, $table, 'certificate_type')
            || !dr_column_exists($conn, $table, 'certificate_details')) {
            continue;
        }

        $hasCertNumber = dr_column_exists($conn, $table, 'certificate_number');
        $hasVerificationCode = dr_column_exists($conn, $table, 'verification_code');
        if (!$hasCertNumber && !$hasVerificationCode) {
            continue;
        }

        $insertCols = ['request_id', 'certificate_type', 'certificate_details'];
        $placeholders = ['?', '?', "JSON_OBJECT('source','issuance_identifiers_upsert')"];
        $types = 'ss';
        $params = [$requestId, $certType];
        $duplicateUpdates = [];

        if ($hasCertNumber) {
            $insertCols[] = 'certificate_number';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $certNo;
            $duplicateUpdates[] = 'certificate_number = VALUES(certificate_number)';
        }
        if ($hasVerificationCode) {
            $insertCols[] = 'verification_code';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $vc;
            $duplicateUpdates[] = 'verification_code = VALUES(verification_code)';
        }
        if (dr_column_exists($conn, $table, 'updated_at')) {
            $duplicateUpdates[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        $stmt = $conn->prepare("
            INSERT INTO {$table} (" . implode(', ', $insertCols) . ")
            VALUES (" . implode(', ', $placeholders) . ")
            ON DUPLICATE KEY UPDATE
                " . implode(",\n                ", $duplicateUpdates) . "
        ");
        if (!$stmt) {
            error_log("[documentRequestWorkflow][issuance] identifier upsert prepare failed for {$table}: " . $conn->error);
            continue;
        }
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) {
            error_log("[documentRequestWorkflow][issuance] identifier upsert failed in {$table}: " . $stmt->error . ' | request_id=' . $requestId);
        }
        $stmt->close();
    }
}

function dr_ensure_general_fees_table(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS generalfeestbl (
            fee_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_type_id INT(11) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (fee_id),
            UNIQUE KEY uq_generalfees_document_type (document_type_id),
            KEY idx_generalfees_amount (amount)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql);

    $existingCount = 0;
    $countRes = $conn->query("SELECT COUNT(*) AS c FROM generalfeestbl");
    if ($countRes instanceof mysqli_result) {
        $countRow = $countRes->fetch_assoc();
        $existingCount = (int)($countRow['c'] ?? 0);
    }

    // Seed defaults only when table is empty to keep runtime requests fast.
    if ($existingCount <= 0) {
    // Seed default certificate fees to 50.00 (idempotent via unique index).
    $certificateDocNames = [
        'Certificate of Cohabitation',
        'Certificate of Indigency',
        'CertificateOfIndigency',
        'First Time Job Seeker Certificate',
        'Certificate of Identity',
        'Certificate of Residency',
        'Certificate of Good Moral',
    ];
    $upsert = $conn->prepare("
        INSERT INTO generalfeestbl (document_type_id, amount)
        VALUES (?, 50.00)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = CURRENT_TIMESTAMP
    ");
    if ($upsert) {
        foreach ($certificateDocNames as $name) {
            $docTypeIdParam = dr_get_or_create_document_type_id($conn, $name, 'DocumentRequest');
            if ($docTypeIdParam) {
                $upsert->bind_param('i', $docTypeIdParam);
                $upsert->execute();
            }
        }
        $upsert->close();
    }

    // CertificateOfIndigency is free.
    $indigencyId = dr_get_or_create_document_type_id($conn, 'CertificateOfIndigency', 'DocumentRequest');
    if ($indigencyId) {
        $free = $conn->prepare("
            INSERT INTO generalfeestbl (document_type_id, amount)
            VALUES (?, 0.00)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = CURRENT_TIMESTAMP
        ");
        if ($free) {
            $free->bind_param('i', $indigencyId);
            $free->execute();
            $free->close();
        }
    }
    }

    $done = true;
}

function dr_get_fee_amount_for_document_type(mysqli $conn, string $documentType): ?float {
    static $cache = [];
    // Rule: certificate requests use generalfeestbl as the fee source.
    if (!dr_is_issuance_document_type($documentType)) {
        return null;
    }
    $cacheKey = dr_canonical_document_type_key($documentType);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    dr_ensure_general_fees_table($conn);
    $docTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest');
    if (!$docTypeId) {
        $cache[$cacheKey] = null;
        return null;
    }
    $stmt = $conn->prepare("SELECT amount FROM generalfeestbl WHERE document_type_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $docTypeId);
    $stmt->execute();
    $stmt->bind_result($amount);
    $ok = $stmt->fetch();
    $stmt->close();
    $cache[$cacheKey] = $ok ? (float)$amount : null;
    return $cache[$cacheKey];
}
