<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['Resident'], true);

dr_ensure_table($conn);
dr_ensure_general_fees_table($conn);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
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

function dr_save_upload(array $file, string $folder): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $orig = (string)($file['name'] ?? '');
    if ($orig === '' || !dr_allowed_extension($orig)) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $name;

    if (!move_uploaded_file($tmp, $targetPath)) {
        return null;
    }

    return '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $name;
}

function dr_has_column(mysqli $conn, string $table, string $column): bool {
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableEsc} LIKE '{$colEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
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
        return false;
    }
    while ($row = $res->fetch_assoc()) {
        $clause = strtolower((string)($row['CHECK_CLAUSE'] ?? ''));
        if (strpos($clause, 'json_valid') !== false) {
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
    $res = $conn->query("SHOW COLUMNS FROM documentrequesttbl LIKE 'request_id'");
    if (!($res instanceof mysqli_result)) {
        return false;
    }
    $row = $res->fetch_assoc();
    $type = strtolower((string)($row['Type'] ?? ''));
    return strpos($type, 'int') !== false;
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

function dr_create_request_attachment(mysqli $conn, string $residentId, string $userId, string $documentType, string $payloadJson): ?int {
    $docTypeId = dr_pick_doc_type_id($conn, $documentType);
    $statusId = dr_pick_any_status_id($conn, ['PendingReview', 'Pending']);
    if ($docTypeId === null || $statusId === null) {
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
        @mkdir($folder, 0775, true);
    }

    $fileName = 'request_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.json';
    $diskPath = $folder . '/' . $fileName;
    if (@file_put_contents($diskPath, $payloadJson) === false) {
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
    $stmt->close();
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

    $purpose = trim((string)($_POST['request_purpose'] ?? $_POST['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = trim((string)($_POST['request_officer'] ?? ''));
    }

    $payload = $_POST;
    unset($payload['action'], $payload['csrf_token']);

    $now = dr_now();
    $stage = DR_STAGE_SUBMITTED;
    $requestId = dr_generate_request_id($conn);

    $payloadJson = dr_safe_json($payload);
    $attachmentId = dr_create_request_attachment($conn, $residentId, $userId, $documentType, $payloadJson);

    $values = [];
    $types = '';
    $params = [];

    $residentNames = dr_get_resident_name_parts($conn, $userId);
    $pendingStatusId = dr_pick_any_status_id($conn, ['PendingReview', 'Pending']);
    $requestDetails = trim($documentType . ($purpose !== '' ? ' - ' . $purpose : ''));
    if ($requestDetails === '') {
        $requestDetails = 'Document request submitted';
    }
    $requestDetailsToken = dr_request_details_token($documentTypeRaw, $documentType);
    $requestDetailsJsonRequired = dr_request_details_requires_json($conn);
    $requestDetailsValue = $requestDetails;
    if ($requestDetailsJsonRequired) {
        $requestDetailsValue = dr_safe_json([
            'summary' => $requestDetails,
            'document_type' => $documentType,
            'purpose' => $purpose,
        ]);
    }
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
    $setIfColumn('purpose', 's', $purpose);
    $setIfColumn('payload_json', 's', $payloadJson);
    $setIfColumn('stage', 's', $stage);
    $setIfColumn('submitted_at', 's', $now);
    $setIfColumn('created_at', 's', $now);
    $setIfColumn('updated_at', 's', $now);

    // Legacy required columns.
    $setIfColumn('last_name', 's', $residentNames['lastname']);
    $setIfColumn('first_name', 's', $residentNames['firstname']);
    $setIfColumn('middle_name', 's', $residentNames['middlename']);
    $setIfColumn('suffix', 's', $residentNames['suffix']);
    if (dr_has_column($conn, 'documentrequesttbl', 'attachment_id')) {
        if ($attachmentId === null) {
            dr_respond_json(500, ['success' => false, 'message' => 'Failed to create request attachment.']);
        }
        $values['attachment_id'] = $attachmentId;
        $types .= 'i';
        $params[] = $attachmentId;
    }
    $setIfColumn('request_details', 's', $requestDetailsValue);
    if (dr_has_column($conn, 'documentrequesttbl', 'status_id_request')) {
        if ($pendingStatusId === null) {
            dr_respond_json(500, ['success' => false, 'message' => 'Pending status is not configured.']);
        }
        $values['status_id_request'] = $pendingStatusId;
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
                    $types .= ($c === 'attachment_id' || $c === 'status_id_request') ? 'i' : 's';
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

    $certificateDetails = dr_safe_json([
        'purpose' => $purpose,
        'submitted_payload' => $payload,
        'resident_id' => $residentId,
        'resident_user_id' => $residentForeignId,
    ]);
    dr_upsert_certificate_request($conn, $requestId, $documentType, $certificateDetails);

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

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    $stage = (string)($row['stage'] ?? '');
    if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not ready for payment submission.']);
    }

    $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? 'gcash')));
    if (!in_array($paymentMethod, ['gcash', 'barangay'], true)) {
        $paymentMethod = 'gcash';
    }

    $proofPath = null;
    if ($paymentMethod === 'gcash') {
        $proofPath = dr_save_upload($_FILES['payment_proof'] ?? [], 'DocumentPayments');
        if (!$proofPath) {
            dr_respond_json(422, ['success' => false, 'message' => 'GCash payment proof is required.']);
        }
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_PAYMENT_SUBMITTED, [
        'payment_method' => $paymentMethod,
        'payment_proof_path' => $proofPath,
        'payment_submitted_at' => dr_now(),
        'status_reason' => null,
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update payment state.']);
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Payment submitted. Please wait for finance verification.',
        'request' => $updated,
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
    if (!in_array($stage, [DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Document is not yet available for download.');
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
    $stmt = $conn->prepare("SELECT * FROM documentrequesttbl WHERE resident_user_id = ? ORDER BY submitted_at DESC, request_id DESC");
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query.']);
    }
    $stmt->bind_param('s', $residentForeignId);
    $stmt->execute();
    $items = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $row['fee_amount'] = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
        $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
        $row['payload'] = is_array($payload) ? $payload : [];
        $items[] = $row;
    }
    $stmt->close();

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
