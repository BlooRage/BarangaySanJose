<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['Resident'], true);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

$drSubmitTiming = null;
if ($action === 'submit_request') {
    $drSubmitTiming = [
        'started_at' => microtime(true),
        'request_id' => '',
        'resident_user_id' => (string)($_SESSION['user_id'] ?? ''),
        'bootstrap_ran' => false,
    ];
    register_shutdown_function(static function () use (&$drSubmitTiming): void {
        if (!is_array($drSubmitTiming) || !isset($drSubmitTiming['started_at'])) {
            return;
        }
        $elapsedMs = (microtime(true) - (float)$drSubmitTiming['started_at']) * 1000;
        $status = http_response_code();
        if (!is_int($status) || $status <= 0) {
            $status = 200;
        }
        error_log(sprintf(
            '[documentRequestWorkflow][submit_request][timing] status=%d elapsed_ms=%.2f request_id=%s resident_user_id=%s bootstrap=%s',
            $status,
            $elapsedMs,
            trim((string)($drSubmitTiming['request_id'] ?? '')) !== '' ? (string)$drSubmitTiming['request_id'] : '-',
            trim((string)($drSubmitTiming['resident_user_id'] ?? '')) !== '' ? (string)$drSubmitTiming['resident_user_id'] : '-',
            !empty($drSubmitTiming['bootstrap_ran']) ? '1' : '0'
        ));
    });
}

function dr_ensure_request_support_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS documenttypelookuptbl (
            document_type_id INT(11) NOT NULL AUTO_INCREMENT,
            document_type_name VARCHAR(100) NOT NULL,
            document_category VARCHAR(100) NOT NULL,
            PRIMARY KEY (document_type_id),
            UNIQUE KEY uq_document_type_name (document_type_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS unifiedfileattachmenttbl (
            attachment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(50) NOT NULL,
            source_id VARCHAR(12) NOT NULL,
            document_type_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            upload_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id_uploaded_by VARCHAR(12) NOT NULL,
            status_id_verify INT(11) NOT NULL,
            remarks TEXT DEFAULT NULL,
            id_number VARCHAR(100) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            delete_reason VARCHAR(100) DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (attachment_id),
            KEY idx_source (source_type, source_id),
            KEY idx_doc_type (document_type_id),
            KEY idx_uploaded_by (user_id_uploaded_by),
            KEY idx_verify_status (status_id_verify),
            CONSTRAINT fk_ufa_document_type FOREIGN KEY (document_type_id) REFERENCES documenttypelookuptbl (document_type_id),
            CONSTRAINT fk_ufa_uploaded_by FOREIGN KEY (user_id_uploaded_by) REFERENCES useraccountstbl (user_id),
            CONSTRAINT fk_ufa_verify_status FOREIGN KEY (status_id_verify) REFERENCES statuslookuptbl (status_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function dr_submit_bootstrap_needed(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Fast guard: only run heavy migration bootstrap when core schema pieces are missing.
    $requiredTables = [
        'documentrequesttbl',
        'documenttypelookuptbl',
        'unifiedfileattachmenttbl',
        'generalfeestbl',
    ];
    foreach ($requiredTables as $table) {
        if (!dr_table_exists($conn, $table)) {
            $cached = true;
            return true;
        }
    }

    $requiredColumns = [
        'resident_user_id',
        'resident_id',
        'request_details',
        'status_id_request',
        'request_timestamp',
    ];
    foreach ($requiredColumns as $column) {
        if (!dr_column_exists($conn, 'documentrequesttbl', $column)) {
            $cached = true;
            return true;
        }
    }

    $cached = false;
    return false;
}

$bootstrapActions = ['submit_request'];
if (in_array($action, $bootstrapActions, true)) {
    if (dr_submit_bootstrap_needed($conn)) {
        if (is_array($drSubmitTiming)) {
            $drSubmitTiming['bootstrap_ran'] = true;
        }
        // Run migration/bootstrap only for incomplete installs, not every request submission.
        dr_ensure_request_support_tables($conn);
        dr_ensure_table($conn);
        dr_ensure_general_fees_table($conn);
    }
}

$userId = (string)($_SESSION['user_id'] ?? '');
$residentId = dr_get_resident_id($conn, $userId) ?? '';
$residentForeignId = $userId;

if ($residentId === '') {
    dr_respond_json(422, ['success' => false, 'message' => 'Resident profile is incomplete.']);
}

function dr_app_base_path(): string {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptName, '/PhpFiles/');
    $base = $pos !== false ? substr($scriptName, 0, $pos) : dirname($scriptName);
    $base = rtrim((string)$base, '/');
    if ($base === '.' || $base === '/') {
        return '';
    }
    return $base;
}

function dr_strip_legacy_base(string $publicPath): string {
    $publicPath = trim($publicPath);
    $base = dr_app_base_path();
    if ($base !== '' && strpos($publicPath, $base) === 0) {
        return substr($publicPath, strlen($base));
    }
    $projectRoot = realpath(__DIR__ . '/../../');
    $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
    if ($projectBase !== '' && strpos($publicPath, '/' . $projectBase) === 0) {
        return substr($publicPath, strlen('/' . $projectBase));
    }
    return $publicPath;
}

function dr_allowed_extension(string $name): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true);
}

function dr_save_upload(array $file, string $folder, ?array $allowedExtensions = null): array {
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Please attach your GCash payment proof.'];
        }
        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            return ['path' => null, 'error' => 'Uploaded file is too large. Please choose a smaller file.'];
        }
        if ($errorCode === UPLOAD_ERR_PARTIAL) {
            return ['path' => null, 'error' => 'Upload was interrupted. Please try again.'];
        }
        return ['path' => null, 'error' => 'Unable to read uploaded file. Please try again.'];
    }

    $orig = (string)($file['name'] ?? '');
    if ($orig === '') {
        return ['path' => null, 'error' => 'Please attach your GCash payment proof.'];
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($allowedExtensions !== null) {
        $allowed = array_values(array_unique(array_map(static fn($v) => strtolower(trim((string)$v)), $allowedExtensions)));
        if (!in_array($ext, $allowed, true)) {
            return ['path' => null, 'error' => 'Unsupported file type. Allowed: JPG, JPEG, PNG, WEBP.'];
        }
    } elseif (!dr_allowed_extension($orig)) {
        return ['path' => null, 'error' => 'Unsupported file type. Allowed: JPG, JPEG, PNG, WEBP, PDF.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return ['path' => null, 'error' => 'Upload validation failed. Please reselect the file and submit again.'];
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return ['path' => null, 'error' => 'Server path resolution failed. Please try again later.'];
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['path' => null, 'error' => 'Server storage is unavailable. Please try again later.'];
    }
    @chmod($targetDir, 0775);
    // Probe writeability by creating a tiny temp file; this is more reliable than is_writable() alone.
    $probe = $targetDir . '/.__w_' . bin2hex(random_bytes(4)) . '.tmp';
    $probeOk = @file_put_contents($probe, 'ok');
    if ($probeOk === false) {
        error_log('[documentRequestWorkflow][upload] target dir not writable: ' . $targetDir);
        return ['path' => null, 'error' => 'Upload folder is not writable on server. Please contact admin.'];
    }
    @unlink($probe);

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $name;

    if (!@move_uploaded_file($tmp, $targetPath)) {
        // Fallbacks for environments where move_uploaded_file intermittently fails.
        $copied = @copy($tmp, $targetPath);
        if (!$copied) {
            $renamed = @rename($tmp, $targetPath);
            if (!$renamed) {
                $lastError = error_get_last();
                error_log('[documentRequestWorkflow][upload] save failed tmp=' . $tmp . ' target=' . $targetPath . ' err=' . ($lastError['message'] ?? 'unknown'));
                return ['path' => null, 'error' => 'Failed to save uploaded file. Please try again.'];
            }
        }
    }
    @chmod($targetPath, 0664);
    if (!is_file($targetPath) || filesize($targetPath) <= 0) {
        @unlink($targetPath);
        error_log('[documentRequestWorkflow][upload] saved file invalid/empty: ' . $targetPath);
        return ['path' => null, 'error' => 'Uploaded file appears empty. Please re-upload a valid proof file.'];
    }

    return ['path' => '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $name, 'error' => null];
}

function dr_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableEsc === '') {
        return false;
    }
    $key = strtolower($tableEsc . '|' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableEsc} LIKE '{$colEsc}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$key] = $exists;
    return $exists;
}

function dr_column_type(mysqli $conn, string $table, string $column): string {
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableEsc} LIKE '{$colEsc}'");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['Type'])) {
            return strtolower((string)$row['Type']);
        }
    }
    return '';
}

function dr_get_fee_map_for_document_types(mysqli $conn, array $documentTypes): array {
    $out = [];
    $clean = [];
    foreach ($documentTypes as $docType) {
        $doc = trim((string)$docType);
        if ($doc === '' || !dr_is_issuance_document_type($doc)) {
            continue;
        }
        $clean[$doc] = true;
    }
    if (!$clean) {
        return $out;
    }
    if (!dr_table_exists($conn, 'documenttypelookuptbl') || !dr_table_exists($conn, 'generalfeestbl')) {
        return $out;
    }

    $names = array_keys($clean);
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $sql = "
        SELECT dt.document_type_name, gf.amount
        FROM documenttypelookuptbl dt
        LEFT JOIN generalfeestbl gf ON gf.document_type_id = dt.document_type_id
        WHERE dt.document_type_name IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $types = str_repeat('s', count($names));
    $bindParams = [$types];
    foreach ($names as $k => $v) {
        $bindParams[] = &$names[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['document_type_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $amount = $row['amount'];
        $out[$name] = ($amount === null ? null : (float)$amount);
    }
    $stmt->close();
    return $out;
}

function dr_request_details_requires_json(mysqli $conn): bool {
    $colType = dr_column_type($conn, 'documentrequesttbl', 'request_details');
    if (strpos($colType, 'json') !== false) {
        return true;
    }

    $res = $conn->query("
        SELECT cc.CHECK_CLAUSE
        FROM information_schema.CHECK_CONSTRAINTS cc
        JOIN information_schema.TABLE_CONSTRAINTS tc
          ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
         AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE tc.TABLE_SCHEMA = DATABASE()
          AND tc.TABLE_NAME = 'documentrequesttbl'
          AND tc.CONSTRAINT_TYPE = 'CHECK'
          AND cc.CHECK_CLAUSE LIKE '%request_details%'
    ");
    if (!($res instanceof mysqli_result)) {
        $res = null;
    }
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $clause = strtolower((string)($row['CHECK_CLAUSE'] ?? ''));
            if (strpos($clause, 'json_valid') !== false) {
                return true;
            }
        }
    }

    // Fallback for servers that don't expose CHECK_CONSTRAINTS reliably.
    $res = $conn->query("SHOW CREATE TABLE documentrequesttbl");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        $createSql = strtolower((string)($row['Create Table'] ?? $row['Create Table'] ?? ''));
        if (strpos($createSql, 'request_details') !== false && strpos($createSql, 'json_valid') !== false) {
            return true;
        }
    }

    return false;
}

function dr_request_details_token(string $documentTypeRaw, string $documentTypeNormalized): string {
    $raw = strtolower(trim($documentTypeRaw));
    if ($raw !== '') {
        return preg_replace('/[^a-z0-9]+/', '', $raw);
    }

    $map = [
        'certificate of cohabitation' => 'cohabitation',
        'certificate of indigency' => 'indigency',
        'certificateofindigency' => 'indigency',
        'first time job seeker certificate' => 'firsttimejobseeker',
        'certificate of identity' => 'identity',
        'certificate of residency' => 'residency',
        'certificate of good moral' => 'goodmoral',
    ];
    $key = strtolower(trim($documentTypeNormalized));
    if (isset($map[$key])) {
        return $map[$key];
    }
    return preg_replace('/[^a-z0-9]+/', '', $key);
}

function dr_request_id_is_numeric(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $res = $conn->query("SHOW COLUMNS FROM documentrequesttbl LIKE 'request_id'");
    if (!($res instanceof mysqli_result)) {
        $cached = false;
        return false;
    }
    $row = $res->fetch_assoc();
    $type = strtolower((string)($row['Type'] ?? ''));
    $cached = strpos($type, 'int') !== false;
    return $cached;
}

function dr_pick_any_status_id(mysqli $conn, array $preferred = []): ?int {
    foreach ($preferred as $name) {
        $sid = dr_find_status_id($conn, $name, ['DocumentRequest', 'Transaction', 'DocumentVerification', 'ResidentDocumentProfiling']);
        if ($sid !== null) {
            return $sid;
        }
    }
    $res = $conn->query("SELECT status_id FROM statuslookuptbl ORDER BY status_id ASC LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['status_id'])) {
            return (int)$row['status_id'];
        }
    }
    return null;
}

function dr_pick_doc_type_id(mysqli $conn, string $documentType): ?int {
    $stmt = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $documentType);
        $stmt->execute();
        $stmt->bind_result($id);
        if ($stmt->fetch()) {
            $stmt->close();
            return (int)$id;
        }
        $stmt->close();
    }

    $res = $conn->query("SELECT document_type_id FROM documenttypelookuptbl ORDER BY document_type_id ASC LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['document_type_id'])) {
            return (int)$row['document_type_id'];
        }
    }
    return null;
}

function dr_get_resident_name_parts(mysqli $conn, string $userId): array {
    $stmt = $conn->prepare("SELECT lastname, firstname, middlename, suffix FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return ['lastname' => '', 'firstname' => '', 'middlename' => '', 'suffix' => ''];
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'lastname' => (string)($row['lastname'] ?? ''),
        'firstname' => (string)($row['firstname'] ?? ''),
        'middlename' => (string)($row['middlename'] ?? ''),
        'suffix' => (string)($row['suffix'] ?? ''),
    ];
}

function dr_ensure_attachment_status_id(mysqli $conn, ?int $preferredStatusId = null): ?int {
    if ($preferredStatusId !== null && $preferredStatusId > 0) {
        return $preferredStatusId;
    }

    $statusId = dr_pick_any_status_id($conn, ['PendingVerification', 'PendingReview', 'Pending']);
    if ($statusId !== null) {
        return $statusId;
    }

    // Minimal bootstrap fallback when status lookups were cleared.
    $statusName = 'PendingVerification';
    $statusType = 'DocumentRequest';
    $insertId = 0;
    $sql = "INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)";
    $ins = $conn->prepare($sql);
    if ($ins) {
        $ins->bind_param('ss', $statusName, $statusType);
        $ok = $ins->execute();
        $insertId = $ok ? (int)$ins->insert_id : 0;
        $ins->close();
    }
    if ($insertId > 0) {
        return $insertId;
    }

    return dr_find_status_id($conn, $statusName, [$statusType]) ?? dr_find_status_id($conn, $statusName, []);
}

function dr_create_request_attachment(
    mysqli $conn,
    string $residentId,
    string $userId,
    string $documentType,
    string $payloadJson,
    ?int $documentTypeId = null,
    ?int $statusId = null
): ?int {
    $docTypeId = $documentTypeId;
    if ($docTypeId === null || $docTypeId <= 0) {
        $docTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest')
            ?? dr_pick_doc_type_id($conn, $documentType);
    }
    $statusId = dr_ensure_attachment_status_id($conn, $statusId);
    if ($docTypeId === null || $docTypeId <= 0 || $statusId === null || $statusId <= 0) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }
    $safeResident = preg_replace('/[^A-Za-z0-9_-]/', '', $residentId);
    if ($safeResident === '') {
        $safeResident = 'resident';
    }
    $folder = $baseDir . '/UnifiedFileAttachment/DocumentRequests/' . $safeResident;
    if (!is_dir($folder)) {
        if (!@mkdir($folder, 0775, true) && !is_dir($folder)) {
            error_log('[documentRequestWorkflow][attachment] failed to create folder: ' . $folder);
            return null;
        }
    }

    $fileName = 'request_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.json';
    $diskPath = $folder . '/' . $fileName;
    if (@file_put_contents($diskPath, $payloadJson) === false) {
        error_log('[documentRequestWorkflow][attachment] failed to write file: ' . $diskPath);
        return null;
    }
    $webPath = '/UnifiedFileAttachment/DocumentRequests/' . $safeResident . '/' . $fileName;

    $fileType = 'json';
    $remarks = 'document_request_payload';
    $idNumber = null;
    $sourceType = 'DocumentRequest';
    $sourceId = $residentId;
    $stmt = $conn->prepare("
        INSERT INTO unifiedfileattachmenttbl
        (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        error_log('[documentRequestWorkflow][attachment] prepare failed: ' . $conn->error);
        return null;
    }
    $stmt->bind_param(
        'ssissssiss',
        $sourceType,
        $sourceId,
        $docTypeId,
        $fileName,
        $webPath,
        $fileType,
        $userId,
        $statusId,
        $remarks,
        $idNumber
    );
    $ok = $stmt->execute();
    $insertId = $ok ? (int)$stmt->insert_id : 0;
    if (!$ok) {
        error_log('[documentRequestWorkflow][attachment] insert failed: ' . $stmt->error);
    }
    $stmt->close();

    if ($insertId <= 0 && file_exists($diskPath)) {
        @unlink($diskPath);
    }
    return $insertId > 0 ? $insertId : null;
}

if ($action === 'submit_request') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $documentTypeRaw = (string)($_POST['document_type'] ?? '');
    $documentType = dr_normalize_document_type($documentTypeRaw);
    if ($documentType === '') {
        $documentType = 'Certificate Request';
    }
    $documentTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest');

    $purpose = trim((string)($_POST['request_purpose'] ?? $_POST['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = trim((string)($_POST['request_officer'] ?? ''));
    }

    $payload = $_POST;
    unset($payload['action'], $payload['csrf_token']);

    $now = dr_now();
    $requestId = dr_generate_request_id($conn);
    if (is_array($drSubmitTiming)) {
        $drSubmitTiming['request_id'] = $requestId;
    }

    $pendingStatusId = dr_pick_any_status_id($conn, ['PendingVerification', 'PendingReview', 'Pending']);
    $payloadJson = dr_safe_json($payload);
    $attachmentId = dr_create_request_attachment(
        $conn,
        $residentId,
        $userId,
        $documentType,
        $payloadJson,
        $documentTypeId,
        $pendingStatusId
    );

    $values = [];
    $types = '';
    $params = [];

    $residentNames = dr_get_resident_name_parts($conn, $userId);
    $requestDetails = trim($documentType . ($purpose !== '' ? ' - ' . $purpose : ''));
    if ($requestDetails === '') {
        $requestDetails = 'Document request submitted';
    }
    $requestDetailsToken = dr_request_details_token($documentTypeRaw, $documentType);
    $requestDetailsJsonRequired = dr_request_details_requires_json($conn);
    $requestDetailsValue = $payloadJson;
    $defaultValidity = date('Y-m-d H:i:s', strtotime('+1 year'));

    $setIfColumn = function (string $column, string $type, $value) use (&$values, &$types, &$params, $conn) {
        if (!dr_has_column($conn, 'documentrequesttbl', $column)) {
            return;
        }
        $values[$column] = $value;
        $types .= $type;
        $params[] = $value;
    };

    if (dr_has_column($conn, 'documentrequesttbl', 'request_id') && !dr_request_id_is_numeric($conn)) {
        $setIfColumn('request_id', 's', $requestId);
    }
    $setIfColumn('resident_user_id', 's', $residentForeignId);
    $setIfColumn('resident_id', 's', $residentId);
    $setIfColumn('resident_name', 's', trim($residentNames['firstname'] . ' ' . $residentNames['middlename'] . ' ' . $residentNames['lastname']));
    $setIfColumn('document_type', 's', $documentType);
    if (dr_has_column($conn, 'documentrequesttbl', 'document_type_id') && $documentTypeId) {
        $values['document_type_id'] = (int)$documentTypeId;
        $types .= 'i';
        $params[] = (int)$documentTypeId;
    }
    $setIfColumn('purpose', 's', $purpose);
    $setIfColumn('submitted_at', 's', $now);
    $setIfColumn('created_at', 's', $now);
    $setIfColumn('updated_at', 's', $now);

    // Legacy required columns.
    $setIfColumn('last_name', 's', $residentNames['lastname']);
    $setIfColumn('first_name', 's', $residentNames['firstname']);
    $setIfColumn('middle_name', 's', $residentNames['middlename']);
    $setIfColumn('suffix', 's', $residentNames['suffix']);
    if (dr_has_column($conn, 'documentrequesttbl', 'attachment_id')) {
        if ($attachmentId !== null) {
            $values['attachment_id'] = $attachmentId;
            $types .= 'i';
            $params[] = $attachmentId;
        } else {
            // Short-schema mode allows NULL attachment_id; do not block request creation.
            error_log('[documentRequestWorkflow] attachment not created, continuing with NULL attachment_id');
        }
    }
    $setIfColumn('request_details', 's', $requestDetailsValue);
    if (dr_has_column($conn, 'documentrequesttbl', 'status_id_request') || dr_has_column($conn, 'documentrequesttbl', 'status_id')) {
        if ($pendingStatusId === null) {
            dr_respond_json(500, ['success' => false, 'message' => 'Pending status is not configured.']);
        }
        $statusCol = dr_has_column($conn, 'documentrequesttbl', 'status_id_request') ? 'status_id_request' : 'status_id';
        $values[$statusCol] = $pendingStatusId;
        $types .= 'i';
        $params[] = $pendingStatusId;
    }
    $setIfColumn('request_timestamp', 's', $now);
    $setIfColumn('review_timestamp', 's', null);
    $setIfColumn('release_timestamp', 's', null);
    $setIfColumn('document_validity', 's', $defaultValidity);
    $setIfColumn('qr_code_path', 's', '');

    if (!$values) {
        dr_respond_json(500, ['success' => false, 'message' => 'documentrequesttbl has no compatible columns.']);
    }

    $columns = array_keys($values);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO documentrequesttbl (" . implode(',', $columns) . ") VALUES (" . $placeholders . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request insert.']);
    }

    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmtClosed = false;
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $stmtClosed = true;

        // Compatibility retry for legacy CHECK constraints on request_details.
        if (stripos($err, 'request_details') !== false && isset($values['request_details'])) {
            if ($requestDetailsJsonRequired) {
                $fallbackCandidates = array_values(array_unique(array_filter([
                    dr_safe_json([
                        'summary' => $requestDetails,
                        'document_type' => $documentType,
                        'purpose' => $purpose,
                    ]),
                    '{}',
                ], static fn($v) => trim((string)$v) !== '')));
            } else {
                $fallbackCandidates = array_values(array_unique(array_filter([
                    $requestDetailsToken,
                    strtolower(trim((string)$documentTypeRaw)),
                    trim((string)$documentType),
                    trim((string)$purpose),
                    $requestDetails,
                    dr_safe_json(['document_type' => $documentType, 'purpose' => $purpose]),
                    '{}',
                    'certificate',
                ], static fn($v) => trim((string)$v) !== '')));
            }

            $saved = false;
            $lastRetryErr = $err;
            foreach ($fallbackCandidates as $fallbackDetails) {
                $values['request_details'] = $fallbackDetails;
                $params = array_values($values);
                $columns = array_keys($values);
                $types = '';
                foreach ($columns as $c) {
                    $types .= ($c === 'attachment_id' || $c === 'status_id' || $c === 'status_id_request' || $c === 'document_type_id') ? 'i' : 's';
                }

                $retrySql = "INSERT INTO documentrequesttbl (" . implode(',', $columns) . ") VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
                $retry = $conn->prepare($retrySql);
                if (!$retry) {
                    continue;
                }

                $retryRefs = [];
                foreach ($params as $k => $v) {
                    $retryRefs[$k] = &$params[$k];
                }
                array_unshift($retryRefs, $types);
                call_user_func_array([$retry, 'bind_param'], $retryRefs);
                if (!$retry->execute()) {
                    $lastRetryErr = $retry->error;
                    $retry->close();
                    continue;
                }

                $requestInsertId = (int)$retry->insert_id;
                if (dr_request_id_is_numeric($conn)) {
                    $requestId = $requestInsertId > 0 ? (string)$requestInsertId : (string)$residentForeignId . '-' . date('YmdHis');
                }
                $saved = true;
                $retry->close();
                break;
            }

            if (!$saved) {
                dr_respond_json(500, ['success' => false, 'message' => 'Failed to save request. ' . $lastRetryErr]);
            }
        } else {
            dr_respond_json(500, ['success' => false, 'message' => 'Failed to save request. ' . $err]);
        }
    } else {
        $requestInsertId = (int)$stmt->insert_id;
        if (dr_request_id_is_numeric($conn)) {
            $requestId = $requestInsertId > 0 ? (string)$requestInsertId : (string)$residentForeignId . '-' . date('YmdHis');
        }
    }
    if (!$stmtClosed) {
        $stmt->close();
    }

    if (dr_is_issuance_document_type($documentType)) {
        $certificateDetails = dr_safe_json([
            'purpose' => $purpose,
            'submitted_payload' => $payload,
            'resident_id' => $residentId,
            'resident_user_id' => $residentForeignId,
        ]);
        dr_upsert_certificate_request($conn, $requestId, $documentType, $certificateDetails);
    }

    $row = dr_fetch_request($conn, $requestId);
    if ($row) {
        dr_sync_transaction($conn, $row);
    }

    if ((isset($_POST['redirect']) && $_POST['redirect'] === '1') || strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false) {
        header('Location: ' . dr_app_base_path() . '/Resident-End/document_requests.php?created=' . urlencode($requestId));
        exit;
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Document request submitted.',
        'request_id' => $requestId,
    ]);
}

if ($action === 'submit_payment') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $requestId = trim((string)($_POST['request_id'] ?? ''));
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }

    $stageCol = dr_has_column($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $statusCol = dr_request_status_column($conn);
    $selCols = [
        'request_id',
        'resident_user_id',
        'document_type',
    ];
    if ($stageCol !== null) {
        $selCols[] = 'stage';
    }
    if ($statusCol !== null && !in_array($statusCol, $selCols, true)) {
        $selCols[] = $statusCol;
    }
    $selSql = "SELECT " . implode(', ', $selCols) . " FROM documentrequesttbl WHERE request_id = ? LIMIT 1";
    $sel = $conn->prepare($selSql);
    if (!$sel) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request lookup.']);
    }
    $sel->bind_param('s', $requestId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$row || (string)($row['resident_user_id'] ?? '') !== $residentForeignId) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    if ($stageCol === null && $statusCol !== null) {
        $statusId = (int)($row[$statusCol] ?? 0);
        if ($statusId > 0) {
            $statusName = dr_status_name_by_id($conn, $statusId);
            $mapped = dr_status_name_to_stage($statusName);
            if ($mapped !== null) {
                $row['stage'] = $mapped;
            }
        }
    }

    $stage = trim((string)($row['stage'] ?? ''));
    if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not ready for payment submission.']);
    }

    $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? 'gcash')));
    if (!in_array($paymentMethod, ['gcash', 'barangay'], true)) {
        $paymentMethod = 'gcash';
    }
    if ($paymentMethod !== 'gcash') {
        dr_respond_json(422, ['success' => false, 'message' => 'Online submission is only for GCash payments. For barangay payment, pay at the finance window.']);
    }

    $proofPath = null;
    $paymentReference = null;
    if ($paymentMethod === 'gcash') {
        $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
        if ($paymentReference === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'GCash transaction number is required.']);
        }
        $upload = dr_save_upload($_FILES['payment_proof'] ?? [], 'DocumentPayments', ['jpg', 'jpeg', 'png', 'webp']);
        $proofPath = (string)($upload['path'] ?? '');
        if ($proofPath === '') {
            $msg = trim((string)($upload['error'] ?? ''));
            dr_respond_json(422, ['success' => false, 'message' => $msg !== '' ? $msg : 'GCash payment proof is required.']);
        }
    }

    $now = dr_now();
    $requestStatusId = dr_find_request_status_id_by_stage($conn, DR_STAGE_PAYMENT_SUBMITTED);
    $txStatusId = dr_map_stage_to_transaction_status_id($conn, DR_STAGE_PAYMENT_SUBMITTED);

    $docSets = [];
    $docTypes = '';
    $docVals = [];
    if (dr_has_column($conn, 'documentrequesttbl', 'updated_at')) {
        $docSets[] = 'updated_at = ?';
        $docTypes .= 's';
        $docVals[] = $now;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'stage')) {
        $docSets[] = 'stage = ?';
        $docTypes .= 's';
        $docVals[] = DR_STAGE_PAYMENT_SUBMITTED;
    }
    $statusCol = dr_request_status_column($conn);
    if ($statusCol !== null && $requestStatusId !== null) {
        $docSets[] = $statusCol . ' = ?';
        $docTypes .= 'i';
        $docVals[] = $requestStatusId;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'review_timestamp')) {
        $docSets[] = 'review_timestamp = ?';
        $docTypes .= 's';
        $docVals[] = $now;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'status_remarks')) {
        $docSets[] = 'status_remarks = NULL';
    }
    if (!$docSets) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update payment state.']);
    }
    $docTypes .= 's';
    $docVals[] = $requestId;
    $docSql = 'UPDATE documentrequesttbl SET ' . implode(', ', $docSets) . ' WHERE request_id = ? LIMIT 1';
    $docStmt = $conn->prepare($docSql);
    if (!$docStmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to prepare payment update.']);
    }
    $docRefs = [];
    foreach ($docVals as $i => $v) {
        $docRefs[$i] = &$docVals[$i];
    }
    array_unshift($docRefs, $docTypes);
    call_user_func_array([$docStmt, 'bind_param'], $docRefs);
    $docOk = $docStmt->execute();
    $docStmt->close();
    if (!$docOk) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update payment state.']);
    }

    // Fast finance transaction write using upsert-style insert.
    if (dr_table_exists($conn, 'financetransactiontbl')) {
        $txDetails = dr_safe_json([
            'reference' => $paymentReference,
            'submitted_at' => $now,
        ]);
        $hasProofCol = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
        $hasDecisionCol = dr_column_exists($conn, 'financetransactiontbl', 'finance_decision_at');
        $hasPaymentTs = dr_column_exists($conn, 'financetransactiontbl', 'payment_timestamp');
        $hasPaymentMethod = dr_column_exists($conn, 'financetransactiontbl', 'payment_method');
        $hasTxDetails = dr_column_exists($conn, 'financetransactiontbl', 'transaction_details');
        $hasTxStatus = dr_column_exists($conn, 'financetransactiontbl', 'transaction_status_id');
        $hasOrNumber = dr_column_exists($conn, 'financetransactiontbl', 'or_number');

        $txId = '';
        $existingTx = $conn->prepare("SELECT transaction_id FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($existingTx) {
            $existingTx->bind_param('s', $requestId);
            $existingTx->execute();
            $existingRow = $existingTx->get_result()->fetch_assoc();
            $existingTx->close();
            if (is_array($existingRow)) {
                $txId = trim((string)($existingRow['transaction_id'] ?? ''));
            }
        }
        if ($txId === '') {
            if (function_exists('GenerateTransactionID')) {
                $txId = (string)GenerateTransactionID($conn, 'financetransactiontbl', 'transaction_id');
            }
            if ($txId === '') {
                $txId = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
            }
        }

        $insertCols = ['transaction_id', 'request_id'];
        $insertVals = [$txId, $requestId];
        $insertTypes = 'ss';
        $updateSets = [];

        if ($hasPaymentMethod) {
            $insertCols[] = 'payment_method';
            $insertVals[] = $paymentMethod;
            $insertTypes .= 's';
            $updateSets[] = 'payment_method = VALUES(payment_method)';
        }
        if ($hasProofCol) {
            $insertCols[] = 'payment_proof_path';
            $insertVals[] = $proofPath;
            $insertTypes .= 's';
            $updateSets[] = 'payment_proof_path = VALUES(payment_proof_path)';
        }
        if ($hasTxDetails) {
            $insertCols[] = 'transaction_details';
            $insertVals[] = $txDetails;
            $insertTypes .= 's';
            $updateSets[] = 'transaction_details = VALUES(transaction_details)';
        }
        if ($hasTxStatus) {
            $insertCols[] = 'transaction_status_id';
            $insertVals[] = $txStatusId;
            $insertTypes .= 'i';
            $updateSets[] = 'transaction_status_id = VALUES(transaction_status_id)';
        }
        if ($hasPaymentTs) {
            $insertCols[] = 'payment_timestamp';
            $insertVals[] = $now;
            $insertTypes .= 's';
            $updateSets[] = 'payment_timestamp = VALUES(payment_timestamp)';
        }
        if ($hasOrNumber) {
            $updateSets[] = 'or_number = NULL';
        }
        if ($hasDecisionCol) {
            $updateSets[] = 'finance_decision_at = NULL';
        }
        if (dr_column_exists($conn, 'financetransactiontbl', 'updated_at')) {
            $updateSets[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        if (!$updateSets) {
            $updateSets[] = 'request_id = request_id';
        }
        $insSql = "INSERT INTO financetransactiontbl (" . implode(', ', $insertCols) . ")
                   VALUES (" . implode(', ', array_fill(0, count($insertCols), '?')) . ")
                   ON DUPLICATE KEY UPDATE " . implode(', ', $updateSets);
        $ins = $conn->prepare($insSql);
        $financeSaved = false;
        if ($ins) {
            $insRefs = [];
            foreach ($insertVals as $i => $v) {
                $insRefs[$i] = &$insertVals[$i];
            }
            array_unshift($insRefs, $insertTypes);
            call_user_func_array([$ins, 'bind_param'], $insRefs);
            $financeSaved = (bool)$ins->execute();
            $ins->close();
        }

        if (!$financeSaved) {
            dr_respond_json(500, ['success' => false, 'message' => 'Payment was uploaded but finance queue update failed. Please submit again.']);
        }
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Payment submitted. Please wait for finance verification.',
        'request_id' => $requestId,
        'stage' => DR_STAGE_PAYMENT_SUBMITTED,
        'payment_method' => $paymentMethod,
        'payment_submitted_at' => $now,
    ]);
}

if ($action === 'select_payment_mode') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $requestId = trim((string)($_POST['request_id'] ?? ''));
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }

    $stageCol = dr_has_column($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $statusCol = dr_request_status_column($conn);
    $selCols = [
        'request_id',
        'resident_user_id',
    ];
    if ($stageCol !== null) {
        $selCols[] = 'stage';
    }
    if ($statusCol !== null && !in_array($statusCol, $selCols, true)) {
        $selCols[] = $statusCol;
    }
    $selSql = "SELECT " . implode(', ', $selCols) . " FROM documentrequesttbl WHERE request_id = ? LIMIT 1";
    $sel = $conn->prepare($selSql);
    if (!$sel) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request lookup.']);
    }
    $sel->bind_param('s', $requestId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$row || (string)($row['resident_user_id'] ?? '') !== $residentForeignId) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    if ($stageCol === null && $statusCol !== null) {
        $statusId = (int)($row[$statusCol] ?? 0);
        if ($statusId > 0) {
            $statusName = dr_status_name_by_id($conn, $statusId);
            $mapped = dr_status_name_to_stage($statusName);
            if ($mapped !== null) {
                $row['stage'] = $mapped;
            }
        }
    }

    $stage = trim((string)($row['stage'] ?? ''));
    if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Payment mode can only be selected while request is for payment.']);
    }

    $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? '')));
    if (!in_array($paymentMethod, ['gcash', 'barangay'], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Please choose a valid payment method.']);
    }

    // Fast-path update: avoid expensive full workflow recalculation for simple mode selection.
    if ($stage === DR_STAGE_PAYMENT_REJECTED) {
        $updated = dr_update_stage($conn, $requestId, DR_STAGE_FOR_PAYMENT, [
            'payment_method' => $paymentMethod,
            'payment_proof_path' => null,
            'payment_submitted_at' => null,
            'payment_reference' => null,
            'status_remarks' => null,
        ]);
        if (!$updated) {
            dr_respond_json(500, ['success' => false, 'message' => 'Unable to save payment mode.']);
        }
    } else {
        if (dr_table_exists($conn, 'financetransactiontbl')) {
            $proofCol = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
            $decisionCol = dr_column_exists($conn, 'financetransactiontbl', 'finance_decision_at');
            $detailsCol = dr_column_exists($conn, 'financetransactiontbl', 'transaction_details');
            $sql = "UPDATE financetransactiontbl
                    SET payment_method = ?, payment_timestamp = NULL, or_number = NULL, updated_at = CURRENT_TIMESTAMP";
            if ($proofCol) {
                $sql .= ", payment_proof_path = NULL";
            }
            if ($decisionCol) {
                $sql .= ", finance_decision_at = NULL";
            }
            if ($detailsCol) {
                $sql .= ", transaction_details = NULL";
            }
            $sql .= " WHERE request_id = ? LIMIT 1";
            $upd = $conn->prepare($sql);
            if ($upd) {
                $upd->bind_param('ss', $paymentMethod, $requestId);
                $upd->execute();
                $upd->close();
            }
        }
        if (dr_has_column($conn, 'documentrequesttbl', 'status_remarks')) {
            $clearRemarks = $conn->prepare("UPDATE documentrequesttbl SET status_remarks = NULL WHERE request_id = ? LIMIT 1");
            if ($clearRemarks) {
                $clearRemarks->bind_param('s', $requestId);
                $clearRemarks->execute();
                $clearRemarks->close();
            }
        }
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Payment mode saved.',
        'request_id' => $requestId,
        'payment_method' => $paymentMethod,
    ]);
}

if ($action === 'download_issued') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $stage = (string)($row['stage'] ?? '');
    if (!in_array($stage, [DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Document is not yet available for download. Release status is pending final review.');
    }

    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Issued file is not yet uploaded.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);

    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $filename = basename($absolute);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_issued') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $stage = (string)($row['stage'] ?? '');
    if (!in_array($stage, [DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Document is not yet available for viewing.');
    }

    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Issued file is not yet uploaded.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);

    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_payment_proof') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $publicPath = trim((string)($row['payment_proof_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'list') {
    if (!dr_table_exists($conn, 'documentrequesttbl')) {
        dr_respond_json(200, ['success' => true, 'items' => []]);
    }

    $orderCol = dr_has_column($conn, 'documentrequesttbl', 'submitted_at') ? 'submitted_at' : 'request_timestamp';
    $hasFinanceTable = dr_table_exists($conn, 'financetransactiontbl');
    $hasStatusLookup = dr_table_exists($conn, 'statuslookuptbl');
    $limit = max(1, min(300, (int)($_GET['limit'] ?? 120)));
    if ($hasFinanceTable) {
        $sql = "
            SELECT
                d.*,
                f.transaction_amount AS _tx_amount,
                f.payment_method AS _tx_payment_method,
                f.payment_proof_path AS _tx_payment_proof_path,
                f.transaction_details AS _tx_transaction_details,
                f.or_number AS _tx_or_number,
                f.transaction_status_id AS _tx_status_id,
                " . ($hasStatusLookup ? "s.status_name" : "''") . " AS _tx_status_name,
                f.payment_deadline AS _tx_payment_deadline,
                f.payment_timestamp AS _tx_payment_timestamp,
                f.finance_decision_at AS _tx_finance_decision_at,
                f.user_id_employee_process AS _tx_finance_user_id
            FROM documentrequesttbl d
            LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
            " . ($hasStatusLookup ? "LEFT JOIN statuslookuptbl s ON s.status_id = f.transaction_status_id" : "") . "
            WHERE d.resident_user_id = ?
            ORDER BY d.{$orderCol} DESC, d.request_id DESC
            LIMIT {$limit}
        ";
    } else {
        $sql = "
            SELECT
                d.*,
                NULL AS _tx_amount,
                '' AS _tx_payment_method,
                '' AS _tx_payment_proof_path,
                '' AS _tx_transaction_details,
                '' AS _tx_or_number,
                0 AS _tx_status_id,
                '' AS _tx_status_name,
                '' AS _tx_payment_deadline,
                '' AS _tx_payment_timestamp,
                '' AS _tx_finance_decision_at,
                '' AS _tx_finance_user_id
            FROM documentrequesttbl d
            WHERE d.resident_user_id = ?
            ORDER BY d.{$orderCol} DESC, d.request_id DESC
            LIMIT {$limit}
        ";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query.']);
    }
    $stmt->bind_param('s', $residentForeignId);
    $stmt->execute();
    $items = [];
    $docTypesForFee = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        dr_hydrate_request_derived_fields($conn, $row, false);

        // Populate finance data from join (avoid per-row finance query).
        $row['amount'] = isset($row['_tx_amount']) ? (float)$row['_tx_amount'] : null;
        $row['payment_method'] = (string)($row['_tx_payment_method'] ?? '');
        $row['payment_proof_path'] = (string)($row['_tx_payment_proof_path'] ?? '');
        $row['or_number'] = (string)($row['_tx_or_number'] ?? '');
        $row['payment_status_id'] = isset($row['_tx_status_id']) ? (int)$row['_tx_status_id'] : 0;
        $row['payment_status_name'] = (string)($row['_tx_status_name'] ?? '');
        $row['payment_deadline'] = (string)($row['_tx_payment_deadline'] ?? '');
        $row['payment_submitted_at'] = (string)($row['_tx_payment_timestamp'] ?? '');
        $row['finance_decision_at'] = (string)($row['_tx_finance_decision_at'] ?? '');
        $row['finance_user_id'] = (string)($row['_tx_finance_user_id'] ?? '');

        $txDetails = (string)($row['_tx_transaction_details'] ?? '');
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

        if (trim((string)($row['stage'] ?? '')) === '') {
            dr_sync_stage_from_status_lookup($conn, $row);
        }
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $docTypeForFee = trim((string)($row['document_type'] ?? ''));
        $row['fee_amount'] = null;
        if ($docTypeForFee !== '') $docTypesForFee[$docTypeForFee] = true;
        $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
        $row['payload'] = is_array($payload) ? $payload : [];
        unset(
            $row['_tx_amount'],
            $row['_tx_payment_method'],
            $row['_tx_payment_proof_path'],
            $row['_tx_transaction_details'],
            $row['_tx_or_number'],
            $row['_tx_status_id'],
            $row['_tx_status_name'],
            $row['_tx_payment_deadline'],
            $row['_tx_payment_timestamp'],
            $row['_tx_finance_decision_at'],
            $row['_tx_finance_user_id']
        );
        $items[] = $row;
    }
    $stmt->close();

    if ($items && $docTypesForFee) {
        $feeMap = dr_get_fee_map_for_document_types($conn, array_keys($docTypesForFee));
        foreach ($items as &$it) {
            $docType = trim((string)($it['document_type'] ?? ''));
            if ($docType !== '' && array_key_exists($docType, $feeMap)) {
                $it['fee_amount'] = $feeMap[$docType];
            }
        }
        unset($it);
    }

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
