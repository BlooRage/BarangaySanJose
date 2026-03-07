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
    $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
    if ($caseId <= 0) {
        respond(false, [], "Invalid case ID.");
    }

    $sql = "
        SELECT
            c.case_id,
            b.blotter_id,
            b.blotter_number,
            b.date_filed,
            b.time_filed,
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
    $stmt->bind_param("i", $caseId);
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
    $stmt->bind_param("i", $caseId);
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
        'status_name' => $detail['status_name'],
        'level_name' => $detail['level_name'],
        'incident_date' => $detail['incident_date'],
        'incident_time' => $detail['incident_time'],
        'incident_place' => $detail['incident_place'],
        'complaint_type' => $detail['complaint_type'],
        'narrative_type' => $narrativeType,
        'narrative_value' => $narrativeValue,
        'complainant' => $participants['Complainant'] ?? [],
        'respondent' => $participants['Respondent'] ?? []
    ];

    respond(true, ['detail' => $payload]);
}

if ($action === 'update_narrative') {
    if ($requestMethod !== 'POST') {
        respond(false, [], "Method not allowed.");
    }

    $caseId = isset($jsonInput['case_id']) ? (int)$jsonInput['case_id'] : (isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0);
    $narrative = trim((string)($jsonInput['narrative_report'] ?? $_POST['narrative_report'] ?? ''));
    if ($caseId <= 0) {
        respond(false, [], "Invalid case ID.");
    }
    if ($narrative === '') {
        respond(false, [], "Narrative report is required.");
    }

    $actorUserId = isset($_SESSION['user_id']) ? trim((string)$_SESSION['user_id']) : '';
    if ($actorUserId === '') {
        respond(false, [], "User session not found.");
    }

    $oldStmt = $conn->prepare("
        SELECT case_details, case_remarks
        FROM casereportstbl
        WHERE case_id = ? AND report_type = 'Blotter'
        LIMIT 1
    ");
    if (!$oldStmt) {
        respond(false, [], "Failed to prepare narrative fetch.");
    }
    $oldStmt->bind_param("i", $caseId);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if (!$oldRow) {
        respond(false, [], "Blotter case not found.");
    }

    $stmt = $conn->prepare("
        UPDATE casereportstbl
        SET case_details = ?, case_remarks = NULL, user_id_official_update_by = ?
        WHERE case_id = ? AND report_type = 'Blotter'
        LIMIT 1
    ");
    if (!$stmt) {
        respond(false, [], "Failed to prepare narrative update.");
    }
    $conn->begin_transaction();
    try {
        $stmt->bind_param("ssi", $narrative, $actorUserId, $caseId);
        $stmt->execute();
        $stmt->close();

        if (tableExists($conn, 'caseupdateslogtbl')) {
            $oldNarrative = trim((string)($oldRow['case_details'] ?? ''));
            $oldRemarks = strtolower(trim((string)($oldRow['case_remarks'] ?? '')));
            $fromFile = ($oldRemarks === 'narrative file uploaded');
            $changed = ($oldNarrative !== $narrative) || $fromFile;
            if ($changed) {
                $logEntry = $fromFile
                    ? "Narrative report updated (replaced previous narrative file)."
                    : "Narrative report updated.";
                $logStmt = $conn->prepare("
                    INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
                    VALUES (?, ?, ?)
                ");
                if ($logStmt) {
                    $logStmt->bind_param("iss", $caseId, $logEntry, $actorUserId);
                    $logStmt->execute();
                    $logStmt->close();
                }
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        respond(false, [], "Failed to update narrative report.");
    }

    respond(true, [], "Narrative updated.");
}

if ($action === 'add_case_log') {
    if ($requestMethod !== 'POST') {
        respond(false, [], "Method not allowed.");
    }

    if (!tableExists($conn, 'caseupdateslogtbl')) {
        respond(false, [], "Missing table caseupdateslogtbl. Run the migration first.");
    }

    $caseId = isset($jsonInput['case_id']) ? (int)$jsonInput['case_id'] : (isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0);
    $logEntry = trim((string)($jsonInput['log_entry'] ?? $_POST['log_entry'] ?? ''));
    if ($caseId <= 0) {
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
    $existsStmt->bind_param("i", $caseId);
    $existsStmt->execute();
    $exists = $existsStmt->get_result()->fetch_row();
    $existsStmt->close();
    if (!$exists) {
        respond(false, [], "Blotter case not found.");
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
        $logStmt->bind_param("iss", $caseId, $logEntry, $actorUserId);
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
        $updStmt->bind_param("si", $actorUserId, $caseId);
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
    $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
    if ($caseId <= 0) {
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
    $stmt->bind_param("i", $caseId);
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
