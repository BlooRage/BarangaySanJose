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
const DR_STAGE_READY_FOR_CLAIM = 'ready_for_claim';
const DR_STAGE_COMPLETED = 'completed';

function dr_now(): string {
    return date('Y-m-d H:i:s');
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
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$colEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function dr_table_exists(mysqli $conn, string $table): bool {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
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
        "document_type_id INT(11) DEFAULT NULL AFTER document_type",
        "request_details LONGTEXT DEFAULT NULL AFTER purpose",
        "status_id INT(11) DEFAULT NULL AFTER request_details",
        "user_id_official_reviewed_by VARCHAR(12) DEFAULT NULL AFTER status_id",
        "user_id_official_released_by VARCHAR(12) DEFAULT NULL AFTER user_id_official_reviewed_by",
        "request_timestamp DATETIME DEFAULT NULL AFTER user_id_official_released_by",
        "review_timestamp DATETIME DEFAULT NULL AFTER request_timestamp",
        "release_timestamp DATETIME DEFAULT NULL AFTER review_timestamp",
        "document_validity DATETIME DEFAULT NULL AFTER release_timestamp",
        "qr_code_path VARCHAR(255) DEFAULT NULL AFTER document_validity",
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
            transaction_details LONGTEXT DEFAULT NULL,
            or_number VARCHAR(80) DEFAULT NULL,
            transaction_status_id INT(11) DEFAULT NULL,
            payment_deadline DATETIME DEFAULT NULL,
            payment_timestamp DATETIME DEFAULT NULL,
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
            resident_user_id VARCHAR(12) NOT NULL,
            resident_id VARCHAR(12) DEFAULT NULL,
            document_type VARCHAR(80) NOT NULL,
            document_type_id INT(11) DEFAULT NULL,
            purpose VARCHAR(255) DEFAULT NULL,
            request_details LONGTEXT DEFAULT NULL,
            status_id INT(11) DEFAULT NULL,
            status_remarks TEXT DEFAULT NULL,
            payment_method VARCHAR(20) DEFAULT NULL,
            payment_proof_path VARCHAR(255) DEFAULT NULL,
            payment_submitted_at DATETIME DEFAULT NULL,
            amount DECIMAL(12,2) DEFAULT NULL,
            or_number VARCHAR(40) DEFAULT NULL,
            certificate_number VARCHAR(64) DEFAULT NULL,
            verification_code VARCHAR(80) DEFAULT NULL,
            issued_file_path VARCHAR(255) DEFAULT NULL,
            submitted_at DATETIME NOT NULL,
            personnel_user_id VARCHAR(12) DEFAULT NULL,
            personnel_decision_at DATETIME DEFAULT NULL,
            finance_user_id VARCHAR(12) DEFAULT NULL,
            finance_decision_at DATETIME DEFAULT NULL,
            ready_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_docreq_resident_user (resident_user_id),
            INDEX idx_docreq_status_id (status_id),
            INDEX idx_docreq_document_type_id (document_type_id),
            INDEX idx_docreq_submitted (submitted_at),
            INDEX idx_docreq_doc_type (document_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    $conn->query($sql);

    // Backward-compatible schema upgrades for already-existing tables.
    $columnsToEnsure = [
        "resident_user_id VARCHAR(12) NOT NULL AFTER request_id",
        "resident_id VARCHAR(12) DEFAULT NULL AFTER resident_user_id",
        "document_type VARCHAR(80) NOT NULL AFTER resident_id",
        "document_type_id INT(11) DEFAULT NULL AFTER document_type",
        "purpose VARCHAR(255) DEFAULT NULL AFTER document_type",
        "request_details LONGTEXT DEFAULT NULL AFTER purpose",
        "status_id INT(11) DEFAULT NULL AFTER request_details",
        "status_remarks TEXT DEFAULT NULL AFTER status_id",
        "payment_method VARCHAR(20) DEFAULT NULL AFTER status_remarks",
        "payment_proof_path VARCHAR(255) DEFAULT NULL AFTER payment_method",
        "payment_submitted_at DATETIME DEFAULT NULL AFTER payment_proof_path",
        "amount DECIMAL(12,2) DEFAULT NULL AFTER payment_submitted_at",
        "or_number VARCHAR(40) DEFAULT NULL AFTER amount",
        "certificate_number VARCHAR(64) DEFAULT NULL AFTER or_number",
        "verification_code VARCHAR(80) DEFAULT NULL AFTER certificate_number",
        "issued_file_path VARCHAR(255) DEFAULT NULL AFTER verification_code",
        "submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER issued_file_path",
        "personnel_user_id VARCHAR(12) DEFAULT NULL AFTER submitted_at",
        "personnel_decision_at DATETIME DEFAULT NULL AFTER personnel_user_id",
        "finance_user_id VARCHAR(12) DEFAULT NULL AFTER personnel_decision_at",
        "finance_decision_at DATETIME DEFAULT NULL AFTER finance_user_id",
        "ready_at DATETIME DEFAULT NULL AFTER finance_decision_at",
        "completed_at DATETIME DEFAULT NULL AFTER ready_at",
        "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER completed_at",
        "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at",
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
    if (!in_array('idx_docreq_status_id', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_status_id (status_id)");
    }
    if (!in_array('idx_docreq_submitted', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_submitted (submitted_at)");
    }
    if (!in_array('idx_docreq_doc_type', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_doc_type (document_type)");
    }
    if (!in_array('idx_docreq_document_type_id', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_document_type_id (document_type_id)");
    }

    // Backward-compatible rename handling.
    if (!dr_column_exists($conn, 'documentrequesttbl', 'status_id') && dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
        $conn->query("ALTER TABLE documentrequesttbl CHANGE COLUMN status_id_request status_id INT(11) DEFAULT NULL");
    }
    if (!dr_column_exists($conn, 'documentrequesttbl', 'status_remarks') && dr_column_exists($conn, 'documentrequesttbl', 'status_reason')) {
        $conn->query("ALTER TABLE documentrequesttbl CHANGE COLUMN status_reason status_remarks TEXT NULL");
    }

    // Ensure FK for document type link.
    if (dr_column_exists($conn, 'documentrequesttbl', 'document_type_id') && dr_table_exists($conn, 'documenttypelookuptbl')) {
        $fkDocType = 'fk_docreq_document_type_id';
        $fkCheckDocType = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'documentrequesttbl'
              AND CONSTRAINT_NAME = ?
        ");
        if ($fkCheckDocType) {
            $fkCheckDocType->bind_param('s', $fkDocType);
            $fkCheckDocType->execute();
            $fkCheckDocType->bind_result($fkDocTypeCount);
            $fkCheckDocType->fetch();
            $fkCheckDocType->close();
            if ((int)$fkDocTypeCount === 0) {
                $conn->query("
                    ALTER TABLE documentrequesttbl
                    ADD CONSTRAINT {$fkDocType}
                    FOREIGN KEY (document_type_id) REFERENCES documenttypelookuptbl(document_type_id)
                    ON DELETE SET NULL ON UPDATE CASCADE
                ");
            }
        }
    }

    dr_ensure_document_request_extensions($conn);

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
        'indigency' => 'Certificate of Indigency',
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
    $variants = dr_document_type_name_variants($documentType);
    if (!$variants) return null;

    foreach ($variants as $name) {
        $sel = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
        if (!$sel) continue;
        $sel->bind_param('s', $name);
        $sel->execute();
        $sel->bind_result($docTypeId);
        $ok = $sel->fetch();
        $sel->close();
        if ($ok && $docTypeId) {
            return (int)$docTypeId;
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
        return $insertId;
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
            return (int)$docTypeId;
        }
    }
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
        DR_STAGE_READY_FOR_CLAIM => 'Ready for Claim',
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

function dr_map_stage_to_transaction_status_id(mysqli $conn, string $stage): int {
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

    if (in_array($stage, [DR_STAGE_REJECTED, DR_STAGE_PAYMENT_REJECTED, DR_STAGE_INSPECTION_FAILED], true)) {
        return $rejected;
    }
    if ($stage === DR_STAGE_COMPLETED) {
        return $verified;
    }
    return $pending;
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

function dr_sync_stage_from_status_lookup(mysqli $conn, array &$row): void {
    if (!isset($row['status_id'])) {
        return;
    }
    $statusId = (int)$row['status_id'];
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

function dr_sync_transaction(mysqli $conn, array $request): void {
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
    $txOr = trim((string)($request['or_number'] ?? ''));
    if ($txOr === '') {
        $txOr = null;
    }
    $txPaymentMethod = trim((string)($request['payment_method'] ?? ''));
    if ($txPaymentMethod === '') {
        $txPaymentMethod = null;
    }
    $txPaymentAt = trim((string)($request['payment_submitted_at'] ?? ''));
    if ($txPaymentAt === '') {
        $txPaymentAt = null;
    }

    $transactionDetails = $docType;
    if ($purpose !== '') {
        $transactionDetails .= ' | Purpose: ' . $purpose;
    }
    if ($reason !== '') {
        $transactionDetails .= ' | Reason: ' . $reason;
    }

    $statusId = dr_map_stage_to_transaction_status_id($conn, $stage);
    $sql = "
        INSERT INTO financetransactiontbl (
            transaction_id, request_id, transaction_amount, applicant_lastname, applicant_firstname, applicant_middleInitial,
            payment_method, transaction_details, or_number, transaction_status_id, payment_deadline, payment_timestamp, user_id_employee_process
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
        ON DUPLICATE KEY UPDATE
            transaction_amount = VALUES(transaction_amount),
            applicant_lastname = VALUES(applicant_lastname),
            applicant_firstname = VALUES(applicant_firstname),
            applicant_middleInitial = VALUES(applicant_middleInitial),
            payment_method = VALUES(payment_method),
            transaction_details = VALUES(transaction_details),
            or_number = VALUES(or_number),
            transaction_status_id = VALUES(transaction_status_id),
            payment_timestamp = VALUES(payment_timestamp),
            user_id_employee_process = VALUES(user_id_employee_process),
            updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            'ssdsssssisss',
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
            $txPaymentAt,
            $employee
        );
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
        dr_sync_stage_from_status_lookup($conn, $row);
    }
    return $row ?: null;
}

function dr_update_stage(mysqli $conn, string $requestId, string $stage, array $patch = []): ?array {
    $allowedColumns = [
        'status_remarks',
        'payment_method',
        'payment_proof_path',
        'payment_submitted_at',
        'amount',
        'or_number',
        'certificate_number',
        'verification_code',
        'qr_code_path',
        'issued_file_path',
        'personnel_user_id',
        'personnel_decision_at',
        'finance_user_id',
        'finance_decision_at',
        'ready_at',
        'completed_at',
    ];

    $sets = ['updated_at = ?'];
    $types = 's';
    $vals = [dr_now()];

    foreach ($patch as $k => $v) {
        if (!in_array($k, $allowedColumns, true)) {
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

    $requestStatusId = dr_find_request_status_id_by_stage($conn, $stage);
    if ($requestStatusId === null) {
        $requestStatusId = dr_map_stage_to_transaction_status_id($conn, $stage);
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        $sets[] = 'status_id = ?';
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
        && in_array($stage, [DR_STAGE_FOR_INTERVIEW, DR_STAGE_FOR_INSPECTION, DR_STAGE_INSPECTION_FAILED, DR_STAGE_REJECTED, DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
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
        dr_sync_transaction($conn, $row);
    }
    return $row;
}

function dr_make_certificate_number(string $orNumber): string {
    $cleanOr = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($orNumber));
    if ($cleanOr === '') {
        $cleanOr = 'NA';
    }
    return 'BSJ-' . date('Ymd') . '-' . $cleanOr;
}

function dr_send_notification(mysqli $conn, array $request, string $subject, string $message): void {
    $residentUserId = (string)($request['resident_user_id'] ?? '');
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
    $emailVerified = (int)($acct['email_verify'] ?? 0) === 1;
    if ($emailVerified && filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                'transaction_no' => (string)($request['request_id'] ?? ''),
                'status' => dr_stage_label((string)($request['stage'] ?? '')),
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (certificate_id),
            UNIQUE KEY uq_issuancereq_request (request_id),
            KEY idx_issuancereq_type (certificate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql);

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

    $done = true;
}

function dr_upsert_certificate_request(mysqli $conn, $requestId, string $certificateType, string $certificateDetails): void {
    dr_ensure_certificate_request_table($conn);

    $stmt = $conn->prepare("
        INSERT INTO issuancerequesttbl (request_id, certificate_type, certificate_details)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            certificate_type = VALUES(certificate_type),
            certificate_details = VALUES(certificate_details),
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        return;
    }

    $requestIdParam = (string)$requestId;
    $stmt->bind_param('sss', $requestIdParam, $certificateType, $certificateDetails);
    $stmt->execute();
    $stmt->close();
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

    $done = true;
}

function dr_get_fee_amount_for_document_type(mysqli $conn, string $documentType): ?float {
    dr_ensure_general_fees_table($conn);
    $docTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest');
    if (!$docTypeId) return null;
    $stmt = $conn->prepare("SELECT amount FROM generalfeestbl WHERE document_type_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $docTypeId);
    $stmt->execute();
    $stmt->bind_result($amount);
    $ok = $stmt->fetch();
    $stmt->close();
    return $ok ? (float)$amount : null;
}
