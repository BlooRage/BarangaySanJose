<?php
declare(strict_types=1);

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/residentTransaction.php';
require_once __DIR__ . '/sendSMS.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

const DR_STAGE_SUBMITTED = 'submitted';
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
            purpose VARCHAR(255) DEFAULT NULL,
            payload_json LONGTEXT DEFAULT NULL,
            stage VARCHAR(32) NOT NULL DEFAULT 'submitted',
            status_reason TEXT DEFAULT NULL,
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
            INDEX idx_docreq_stage (stage),
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
        "purpose VARCHAR(255) DEFAULT NULL AFTER document_type",
        "payload_json LONGTEXT DEFAULT NULL AFTER purpose",
        "stage VARCHAR(32) NOT NULL DEFAULT 'submitted' AFTER payload_json",
        "status_reason TEXT DEFAULT NULL AFTER stage",
        "payment_method VARCHAR(20) DEFAULT NULL AFTER status_reason",
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
    if (!in_array('idx_docreq_stage', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage (stage)");
    }
    if (!in_array('idx_docreq_submitted', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_submitted (submitted_at)");
    }
    if (!in_array('idx_docreq_doc_type', $indexNames, true)) {
        $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_doc_type (document_type)");
    }

    $done = true;
}

function dr_generate_request_id(mysqli $conn): string {
    $prefix = 'DR' . date('ym');
    $like = $prefix . '%';

    $stmt = $conn->prepare("SELECT request_id FROM documentrequesttbl WHERE request_id LIKE ? ORDER BY request_id DESC LIMIT 1");
    if (!$stmt) {
        return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    $stmt->bind_param('s', $like);
    $stmt->execute();
    $stmt->bind_result($lastId);
    $has = $stmt->fetch();
    $stmt->close();

    $next = 1;
    if ($has && is_string($lastId) && strlen($lastId) >= 4) {
        $tail = substr($lastId, -4);
        if (ctype_digit($tail)) {
            $next = ((int) $tail) + 1;
        }
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

function dr_stage_label(string $stage): string {
    $labels = [
        DR_STAGE_SUBMITTED => 'Submitted',
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

    if (in_array($stage, [DR_STAGE_REJECTED, DR_STAGE_PAYMENT_REJECTED], true)) {
        return $rejected;
    }
    if ($stage === DR_STAGE_COMPLETED) {
        return $verified;
    }
    return $pending;
}

function dr_sync_transaction(mysqli $conn, array $request): void {
    $requestId = (string)($request['request_id'] ?? '');
    $residentForeignId = (string)($request['resident_user_id'] ?? '');
    if ($requestId === '' || $residentForeignId === '') {
        return;
    }
    $accountUserId = dr_get_user_id_from_resident_id($conn, $residentForeignId);
    if ($accountUserId === null || $accountUserId === '') {
        return;
    }

    $docType = (string)($request['document_type'] ?? 'Document Request');
    $purpose = trim((string)($request['purpose'] ?? ''));
    $stage = (string)($request['stage'] ?? DR_STAGE_SUBMITTED);
    $reason = trim((string)($request['status_reason'] ?? ''));

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
    return $row ?: null;
}

function dr_update_stage(mysqli $conn, string $requestId, string $stage, array $patch = []): ?array {
    $allowedColumns = [
        'status_reason',
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

    $sets = ['stage = ?', 'updated_at = ?'];
    $types = 'ss';
    $vals = [$stage, dr_now()];

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
    $residentForeignId = (string)($request['resident_user_id'] ?? '');
    if ($residentForeignId === '') {
        return;
    }
    $residentUserId = dr_get_user_id_from_resident_id($conn, $residentForeignId);
    if ($residentUserId === null || $residentUserId === '') {
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
        CREATE TABLE IF NOT EXISTS certificaterequesttbl (
            certificate_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id {$requestIdType} NOT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            certificate_details LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (certificate_id),
            UNIQUE KEY uq_certreq_request (request_id),
            KEY idx_certreq_type (certificate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql);

    // Add FK if not present and types are compatible (best effort).
    $fkName = 'fk_certreq_request_id';
    $fkCheck = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'certificaterequesttbl'
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
                ALTER TABLE certificaterequesttbl
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
        INSERT INTO certificaterequesttbl (request_id, certificate_type, certificate_details)
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
        'First Time Job Seeker Certificate',
        'Certificate of Identity',
        'Certificate of Residency',
        'Certificate of Good Moral',
    ];
    $sel = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
    $upsert = $conn->prepare("
        INSERT INTO generalfeestbl (document_type_id, amount)
        VALUES (?, 50.00)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = CURRENT_TIMESTAMP
    ");
    if ($sel && $upsert) {
        foreach ($certificateDocNames as $name) {
            $sel->bind_param('s', $name);
            $sel->execute();
            $sel->bind_result($docTypeId);
            if ($sel->fetch()) {
                $docTypeIdParam = (int)$docTypeId;
                $upsert->bind_param('i', $docTypeIdParam);
                $upsert->execute();
            }
            $sel->free_result();
        }
        $sel->close();
        $upsert->close();
    }

    $done = true;
}

function dr_get_fee_amount_for_document_type(mysqli $conn, string $documentType): ?float {
    dr_ensure_general_fees_table($conn);

    $stmt = $conn->prepare("
        SELECT gf.amount
        FROM generalfeestbl gf
        INNER JOIN documenttypelookuptbl dt ON dt.document_type_id = gf.document_type_id
        WHERE dt.document_type_name = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $documentType);
    $stmt->execute();
    $stmt->bind_result($amount);
    $ok = $stmt->fetch();
    $stmt->close();
    return $ok ? (float)$amount : null;
}
