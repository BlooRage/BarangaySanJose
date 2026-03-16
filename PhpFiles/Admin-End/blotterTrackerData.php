<?php
session_start();

require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

function respond($success, $payload = [], $message = '') {
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'items' => $payload['items'] ?? null,
        'detail' => $payload['detail'] ?? null,
    ]);
    exit;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jsonInput = [];
if ($requestMethod === 'POST') {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $jsonInput = $decoded;
        }
    }
}

$action = '';
if ($requestMethod === 'POST') {
    $action = trim((string)($jsonInput['action'] ?? $_POST['action'] ?? ''));
}
if ($action === '') {
    $action = trim((string)($_GET['action'] ?? 'list'));
}

function tableExists(mysqli $conn, string $tableName): bool {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return !empty($row);
}

function getStatusId(mysqli $conn, string $statusName, string $statusType): int {
    $stmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_name = ? AND status_type = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("ss", $statusName, $statusType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return isset($row['status_id']) ? (int)$row['status_id'] : 0;
}

function getCurrentBlotterStatusName(mysqli $conn, string $caseId): string {
    $stmt = $conn->prepare("
        SELECT s.status_name
        FROM casereportstbl c
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE c.case_id = ? AND c.report_type = 'Blotter'
        LIMIT 1
    ");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['status_name'] ?? ''));
}

function loadCaseSignatures(mysqli $conn, string $caseId): array {
    if (!tableExists($conn, 'casesignaturestbl')) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT signature_role, file_path, mime_type, captured_at
        FROM casesignaturestbl
        WHERE case_id = ?
        ORDER BY signature_id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $role = trim((string)($row['signature_role'] ?? ''));
        if ($role === '') {
            continue;
        }
        $items[$role] = [
            'file_path' => $row['file_path'] ?? '',
            'mime_type' => $row['mime_type'] ?? 'image/png',
            'captured_at' => $row['captured_at'] ?? ''
        ];
    }
    $stmt->close();

    return $items;
}

if ($action === 'list') {
    $sql = "
        SELECT
            c.case_id,
            b.blotter_id,
            b.blotter_number,
            b.date_filed,
            b.time_filed,
            s.status_name AS status_name,
            l.status_name AS level_name,
            cp.firstname,
            cp.middlename,
            cp.lastname,
            cp.suffix,
            rp.firstname AS respondent_firstname,
            rp.middlename AS respondent_middlename,
            rp.lastname AS respondent_lastname,
            rp.suffix AS respondent_suffix
        FROM casereportstbl c
        INNER JOIN barangayblottertbl b ON b.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        LEFT JOIN caseparticipantstbl cp
            ON cp.case_id = c.case_id
            AND cp.participant_role = 'Complainant'
        LEFT JOIN caseparticipantstbl rp
            ON rp.case_id = c.case_id
            AND rp.participant_role = 'Respondent'
        WHERE c.report_type = 'Blotter'
        ORDER BY b.date_filed DESC, b.time_filed DESC, b.blotter_id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond(false, [], "Failed to prepare list query.");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $fullName = trim(implode(' ', array_filter([
            $row['firstname'] ?? '',
            $row['middlename'] ?? '',
            $row['lastname'] ?? '',
            $row['suffix'] ?? ''
        ])));
        $respondentName = trim(implode(' ', array_filter([
            $row['respondent_firstname'] ?? '',
            $row['respondent_middlename'] ?? '',
            $row['respondent_lastname'] ?? '',
            $row['respondent_suffix'] ?? ''
        ])));
        $items[] = [
            'case_id' => $row['case_id'],
            'blotter_id' => $row['blotter_id'],
            'blotter_number' => $row['blotter_number'],
            'date_filed' => $row['date_filed'],
            'time_filed' => $row['time_filed'],
            'status_name' => $row['status_name'],
            'level_name' => $row['level_name'],
            'complainant_name' => $fullName,
            'respondent_name' => $respondentName,
        ];
    }
    $stmt->close();
    respond(true, ['items' => $items]);
}

if ($action === 'detail') {
    $caseId = trim((string)($_GET['case_id'] ?? ''));
    if ($caseId === '') {
        respond(false, [], "Invalid case ID.");
    }

    $sql = "
        SELECT
            c.case_id,
            b.blotter_id,
            b.blotter_number,
            b.date_filed,
            b.time_filed,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d %h:%i %p') AS report_timestamp,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            c.case_remarks,
            s.status_name AS status_name,
            l.status_name AS level_name
        FROM casereportstbl c
        INNER JOIN barangayblottertbl b ON b.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        WHERE c.case_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond(false, [], "Failed to prepare detail query.");
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$detail) {
        respond(false, [], "Blotter record not found.");
    }

    $stmt = $conn->prepare("
        SELECT participant_role, firstname, middlename, lastname, suffix, contact_number, address, age, sex
        FROM caseparticipantstbl
        WHERE case_id = ?
    ");
    if (!$stmt) {
        respond(false, [], "Failed to prepare participant query.");
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $participants = [
        'Complainant' => null,
        'Respondent' => null
    ];
    while ($row = $res->fetch_assoc()) {
        $fullName = trim(implode(' ', array_filter([
            $row['firstname'] ?? '',
            $row['middlename'] ?? '',
            $row['lastname'] ?? '',
            $row['suffix'] ?? ''
        ])));
        $payload = [
            'full_name' => $fullName,
            'contact_number' => $row['contact_number'] ?? '',
            'address' => $row['address'] ?? '',
            'age' => $row['age'] ?? '',
            'sex' => $row['sex'] ?? ''
        ];
        if ($row['participant_role'] === 'Complainant') {
            $participants['Complainant'] = $payload;
        } elseif ($row['participant_role'] === 'Respondent') {
            $participants['Respondent'] = $payload;
        }
    }
    $stmt->close();

    $narrativeType = 'text';
    $narrativeValue = $detail['case_details'] ?? '';
    $remarks = strtolower(trim((string)($detail['case_remarks'] ?? '')));
    if ($remarks === 'narrative file uploaded') {
        $narrativeType = 'file';
    }

    $payload = [
        'case_id' => $detail['case_id'],
        'blotter_id' => $detail['blotter_id'],
        'blotter_number' => $detail['blotter_number'],
        'date_filed' => $detail['date_filed'],
        'time_filed' => $detail['time_filed'],
        'report_timestamp' => $detail['report_timestamp'],
        'status_name' => $detail['status_name'],
        'level_name' => $detail['level_name'],
        'incident_date' => $detail['incident_date'],
        'incident_time' => $detail['incident_time'],
        'incident_place' => $detail['incident_place'],
        'complaint_type' => $detail['complaint_type'],
        'narrative_type' => $narrativeType,
        'narrative_value' => $narrativeValue,
        'signatures' => loadCaseSignatures($conn, $caseId),
        'complainant' => $participants['Complainant'] ?? [],
        'respondent' => $participants['Respondent'] ?? []
    ];

    respond(true, ['detail' => $payload]);
}

if ($action === 'add_narrative_entry') {
    if ($requestMethod !== 'POST') {
        respond(false, [], "Method not allowed.");
    }

    if (!tableExists($conn, 'caseupdateslogtbl')) {
        respond(false, [], "Missing table caseupdateslogtbl. Run the migration first.");
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? $_POST['case_id'] ?? ''));
    $narrative = trim((string)($jsonInput['narrative_report'] ?? $_POST['narrative_report'] ?? ''));
    if ($caseId === '') {
        respond(false, [], "Invalid case ID.");
    }
    if ($narrative === '') {
        respond(false, [], "Narrative report is required.");
    }

    $actorUserId = isset($_SESSION['user_id']) ? trim((string)$_SESSION['user_id']) : '';
    if ($actorUserId === '') {
        respond(false, [], "User session not found.");
    }

    $existsStmt = $conn->prepare("SELECT 1 FROM casereportstbl WHERE case_id = ? AND report_type = 'Blotter' LIMIT 1");
    if (!$existsStmt) {
        respond(false, [], "Failed to validate case.");
    }
    $existsStmt->bind_param("s", $caseId);
    $existsStmt->execute();
    $exists = $existsStmt->get_result()->fetch_row();
    $existsStmt->close();
    if (!$exists) {
        respond(false, [], "Blotter case not found.");
    }
    if (strtolower(getCurrentBlotterStatusName($conn, $caseId)) !== 'active') {
        respond(false, [], "Case is finalized. New narrative updates are not allowed.");
    }

    $logEntry = "Narrative report added: " . $narrative;
    $conn->begin_transaction();
    try {
        $logStmt = $conn->prepare("
            INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
            VALUES (?, ?, ?)
        ");
        if (!$logStmt) {
            throw new Exception("Failed to prepare narrative log insert.");
        }
        $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
        $logStmt->execute();
        $logStmt->close();

        $updStmt = $conn->prepare("
            UPDATE casereportstbl
            SET user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Blotter'
            LIMIT 1
        ");
        if (!$updStmt) {
            throw new Exception("Failed to prepare case updater.");
        }
        $updStmt->bind_param("ss", $actorUserId, $caseId);
        $updStmt->execute();
        $updStmt->close();

        $conn->commit();
        respond(true, [], "Narrative entry added.");
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, [], "Failed to add narrative entry.");
    }
}

if ($action === 'update_case_outcome') {
    if ($requestMethod !== 'POST') {
        respond(false, [], "Method not allowed.");
    }

    if (!tableExists($conn, 'caseupdateslogtbl')) {
        respond(false, [], "Missing table caseupdateslogtbl. Run the migration first.");
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? ''));
    $actionType = strtolower(trim((string)($jsonInput['action_type'] ?? '')));
    $endorsementTarget = strtolower(trim((string)($jsonInput['endorsement_target'] ?? '')));
    $remarks = trim((string)($jsonInput['remarks'] ?? ''));

    if ($caseId === '') {
        respond(false, [], "Invalid case ID.");
    }
    if ($remarks === '') {
        respond(false, [], "Remarks are required.");
    }
    if (!in_array($actionType, ['resolved', 'endorsement', 'dropped'], true)) {
        respond(false, [], "Invalid action type.");
    }
    if ($actionType === 'endorsement' && !in_array($endorsementTarget, ['lupon', 'pnp'], true)) {
        respond(false, [], "Invalid endorsement target.");
    }

    $actorUserId = isset($_SESSION['user_id']) ? trim((string)$_SESSION['user_id']) : '';
    if ($actorUserId === '') {
        respond(false, [], "User session not found.");
    }

    $newStatusName = '';
    $newLevelName = '';
    if ($actionType === 'resolved') {
        $newStatusName = 'Resolved';
        $newLevelName = 'Settled';
    } elseif ($actionType === 'dropped') {
        $newStatusName = 'Dropped';
        $newLevelName = 'Unsettled';
    } elseif ($actionType === 'endorsement') {
        $newStatusName = 'Endorsed';
        $newLevelName = ($endorsementTarget === 'lupon') ? 'Endorsed to Lupon' : 'Endorsed to PNP';
    }

    $newStatusId = getStatusId($conn, $newStatusName, 'Blotter');
    $newLevelId = getStatusId($conn, $newLevelName, 'BlotterLevel');
    if ($newStatusId <= 0 || $newLevelId <= 0) {
        respond(false, [], "Status or case level mapping not found in statuslookuptbl.");
    }

    $oldStmt = $conn->prepare("
        SELECT
            s.status_name AS old_status_name,
            l.status_name AS old_level_name
        FROM casereportstbl c
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        WHERE c.case_id = ? AND c.report_type = 'Blotter'
        LIMIT 1
    ");
    if (!$oldStmt) {
        respond(false, [], "Failed to load current case state.");
    }
    $oldStmt->bind_param("s", $caseId);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if (!$oldRow) {
        respond(false, [], "Blotter case not found.");
    }

    $oldStatusName = trim((string)($oldRow['old_status_name'] ?? 'Unknown'));
    $oldLevelName = trim((string)($oldRow['old_level_name'] ?? 'Unknown'));
    if (strtolower($oldStatusName) !== 'active') {
        respond(false, [], "Case status is already finalized and cannot be changed again.");
    }
    $logEntry = "Case status updated: {$oldStatusName} -> {$newStatusName}; Case level: {$oldLevelName} -> {$newLevelName}; Remarks: {$remarks}";

    $conn->begin_transaction();
    try {
        $updateStmt = $conn->prepare("
            UPDATE casereportstbl
            SET case_status_id = ?, case_level_id = ?, user_id_official_update_by = ?, resolution_remarks = ?
            WHERE case_id = ? AND report_type = 'Blotter'
            LIMIT 1
        ");
        if (!$updateStmt) {
            throw new Exception("Failed to prepare case update.");
        }
        $updateStmt->bind_param("iisss", $newStatusId, $newLevelId, $actorUserId, $remarks, $caseId);
        $updateStmt->execute();
        $updateStmt->close();

        $logStmt = $conn->prepare("
            INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
            VALUES (?, ?, ?)
        ");
        if (!$logStmt) {
            throw new Exception("Failed to prepare case log.");
        }
        $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();
        respond(true, [], "Case updated.");
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, [], "Failed to update case.");
    }
}

if ($action === 'add_case_log') {
    if ($requestMethod !== 'POST') {
        respond(false, [], "Method not allowed.");
    }

    if (!tableExists($conn, 'caseupdateslogtbl')) {
        respond(false, [], "Missing table caseupdateslogtbl. Run the migration first.");
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? $_POST['case_id'] ?? ''));
    $logEntry = trim((string)($jsonInput['log_entry'] ?? $_POST['log_entry'] ?? ''));
    if ($caseId === '') {
        respond(false, [], "Invalid case ID.");
    }
    if ($logEntry === '') {
        respond(false, [], "Log entry is required.");
    }

    $actorUserId = isset($_SESSION['user_id']) ? trim((string)$_SESSION['user_id']) : '';
    if ($actorUserId === '') {
        respond(false, [], "User session not found.");
    }

    $existsStmt = $conn->prepare("SELECT 1 FROM casereportstbl WHERE case_id = ? AND report_type = 'Blotter' LIMIT 1");
    if (!$existsStmt) {
        respond(false, [], "Failed to validate case.");
    }
    $existsStmt->bind_param("s", $caseId);
    $existsStmt->execute();
    $exists = $existsStmt->get_result()->fetch_row();
    $existsStmt->close();
    if (!$exists) {
        respond(false, [], "Blotter case not found.");
    }
    if (strtolower(getCurrentBlotterStatusName($conn, $caseId)) !== 'active') {
        respond(false, [], "Case is finalized. New case logs are not allowed.");
    }

    $conn->begin_transaction();
    try {
        $logStmt = $conn->prepare("
            INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
            VALUES (?, ?, ?)
        ");
        if (!$logStmt) {
            throw new Exception("Failed to prepare case log insert.");
        }
        $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
        $logStmt->execute();
        $logStmt->close();

        $updStmt = $conn->prepare("
            UPDATE casereportstbl
            SET user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Blotter'
            LIMIT 1
        ");
        if (!$updStmt) {
            throw new Exception("Failed to prepare case updater.");
        }
        $updStmt->bind_param("ss", $actorUserId, $caseId);
        $updStmt->execute();
        $updStmt->close();

        $conn->commit();
        respond(true, [], "Case log added.");
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, [], "Failed to add case log.");
    }
}

if ($action === 'case_logs') {
    $caseId = trim((string)($_GET['case_id'] ?? ''));
    if ($caseId === '') {
        respond(false, [], "Invalid case ID.");
    }

    if (!tableExists($conn, 'caseupdateslogtbl')) {
        respond(true, ['items' => []]);
    }

    $stmt = $conn->prepare("
        SELECT
            l.log_entry,
            DATE_FORMAT(l.logged_at, '%Y-%m-%d %h:%i %p') AS logged_at,
            l.logged_by_user_id,
            TRIM(CONCAT_WS(' ', o.firstname, o.middlename, o.lastname, o.suffix)) AS logged_by_name,
            u.role_access AS logged_by_role
        FROM caseupdateslogtbl l
        LEFT JOIN officialinformationtbl o ON o.user_id = l.logged_by_user_id
        LEFT JOIN useraccountstbl u ON u.user_id = l.logged_by_user_id
        WHERE l.case_id = ?
        ORDER BY l.logged_at DESC, l.case_log_id DESC
    ");
    if (!$stmt) {
        respond(false, [], "Failed to prepare case logs query.");
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $loggedByUserId = trim((string)($row['logged_by_user_id'] ?? ''));
        $loggedByName = trim((string)($row['logged_by_name'] ?? ''));
        $loggedByRole = trim((string)($row['logged_by_role'] ?? ''));
        $loggedByDisplay = $loggedByName !== '' ? $loggedByName : ($loggedByUserId !== '' ? $loggedByUserId : 'Unknown User');
        if ($loggedByRole !== '') {
            $loggedByDisplay .= " ({$loggedByRole})";
        }

        $items[] = [
            'log_entry' => $row['log_entry'] ?? '',
            'logged_at' => $row['logged_at'] ?? '',
            'logged_by_name' => $loggedByName,
            'logged_by_user_id' => $loggedByUserId,
            'logged_by_display' => $loggedByDisplay
        ];
    }
    $stmt->close();
    respond(true, ['items' => $items]);
}

respond(false, [], "Unsupported action.");
