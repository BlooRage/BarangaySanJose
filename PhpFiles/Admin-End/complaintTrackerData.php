<?php
session_start();

require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

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

function respond($success, $payload = [], $message = ''): void
{
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'items' => $payload['items'] ?? null,
        'detail' => $payload['detail'] ?? null,
    ]);
    exit;
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function getStatusId(mysqli $conn, string $statusName, string $statusType): int
{
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

function appendMultilineNote(?string $existing, string $newNote): string
{
    $existing = trim((string)$existing);
    $newNote = trim($newNote);
    if ($existing === '') {
        return $newNote;
    }
    if ($newNote === '') {
        return $existing;
    }
    return $existing . "\n" . $newNote;
}

function participantDisplayName(array $row, string $prefix = ''): string
{
    $first = trim((string)($row[$prefix . 'firstname'] ?? ''));
    $middle = trim((string)($row[$prefix . 'middlename'] ?? ''));
    $last = trim((string)($row[$prefix . 'lastname'] ?? ''));
    $suffix = trim((string)($row[$prefix . 'suffix'] ?? ''));
    $middleInitial = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';

    return trim(implode(' ', array_filter([
        $first,
        $middleInitial,
        $last,
        $suffix
    ])));
}

if (!tableExists($conn, 'complaintstbl')) {
    respond(false, [], 'Missing table complaintstbl. Run the complaint migration first.');
}

$action = '';
if ($requestMethod === 'POST') {
    $action = trim((string)($jsonInput['action'] ?? $_POST['action'] ?? ''));
}
if ($action === '') {
    $action = trim((string)($_GET['action'] ?? 'list'));
}

if ($action === 'list') {
    $sql = "
        SELECT
            c.case_id,
            ct.complaint_id,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d %h:%i %p') AS submitted_at,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d') AS submitted_date,
            c.complaint_type,
            s.status_name,
            l.status_name AS level_name,
            ct.subject_display_name,
            ct.subject_kind,
            ct.escalated_to_blotter,
            ct.blotter_id,
            cp.firstname,
            cp.middlename,
            cp.lastname,
            cp.suffix
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        LEFT JOIN (
            SELECT
                case_id,
                MAX(firstname) AS firstname,
                MAX(middlename) AS middlename,
                MAX(lastname) AS lastname,
                MAX(suffix) AS suffix
            FROM caseparticipantstbl
            WHERE participant_role = 'Complainant'
            GROUP BY case_id
        ) cp
            ON cp.case_id = c.case_id
        WHERE c.report_type = 'Complaint'
        ORDER BY c.report_timestamp DESC, c.case_id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond(false, [], 'Failed to prepare complaint list query.');
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $statusName = trim((string)($row['status_name'] ?? 'Pending'));
        $statusKey = 'pending';
        if ((int)($row['escalated_to_blotter'] ?? 0) === 1) {
            $statusKey = 'escalated';
        } elseif (stripos($statusName, 'resolved') !== false) {
            $statusKey = 'resolved';
        } elseif (stripos($statusName, 'drop') !== false) {
            $statusKey = 'dropped';
        } elseif (stripos($statusName, 'endorse') !== false) {
            $statusKey = 'escalated';
        }

        $items[] = [
            'case_id' => (string)$row['case_id'],
            'complaint_id' => $row['complaint_id'] ?? '',
            'submitted_at' => $row['submitted_at'] ?? '',
            'submitted_date' => $row['submitted_date'] ?? '',
            'complaint_type' => $row['complaint_type'] ?? '',
            'status_name' => $statusName !== '' ? $statusName : 'Pending',
            'status_key' => $statusKey,
            'level_name' => $row['level_name'] ?? 'Complaint Only',
            'subject_display_name' => $row['subject_display_name'] ?? '',
            'subject_kind' => $row['subject_kind'] ?? '',
            'escalated_to_blotter' => (int)($row['escalated_to_blotter'] ?? 0),
            'blotter_id' => $row['blotter_id'] ?? null,
            'complainant_name' => participantDisplayName($row),
        ];
    }
    $stmt->close();

    respond(true, ['items' => $items]);
}

if ($action === 'detail') {
    $caseId = trim((string)($_GET['case_id'] ?? ''));
    if ($caseId === '') {
        respond(false, [], 'Invalid case ID.');
    }

    $stmt = $conn->prepare("
        SELECT
            c.case_id,
            ct.complaint_id,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d %h:%i %p') AS submitted_at,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            c.case_remarks,
            s.status_name,
            l.status_name AS level_name,
            ct.subject_kind,
            ct.subject_display_name,
            ct.subject_contact_number,
            ct.subject_address,
            ct.witness_summary,
            ct.intake_notes,
            ct.screening_notes,
            ct.escalated_to_blotter,
            ct.blotter_id,
            b.blotter_number,
            ct.complaint_origin
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        LEFT JOIN barangayblottertbl b ON b.blotter_id = ct.blotter_id
        WHERE c.case_id = ?
          AND c.report_type = 'Complaint'
        LIMIT 1
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare complaint detail query.');
    }

    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$detail) {
        respond(false, [], 'Complaint record not found.');
    }

    $stmt = $conn->prepare("
        SELECT participant_role, firstname, middlename, lastname, suffix, contact_number, address, age, sex, remarks
        FROM caseparticipantstbl
        WHERE case_id = ?
        ORDER BY participant_role ASC
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare participant query.');
    }

    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $participants = [
        'Complainant' => null,
        'Respondent' => null,
        'Witness' => null,
    ];
    while ($row = $res->fetch_assoc()) {
        $role = (string)($row['participant_role'] ?? '');
        $participants[$role] = [
            'full_name' => participantDisplayName($row),
            'contact_number' => $row['contact_number'] ?? '',
            'address' => $row['address'] ?? '',
            'age' => $row['age'] ?? '',
            'sex' => $row['sex'] ?? '',
            'remarks' => $row['remarks'] ?? '',
        ];
    }
    $stmt->close();

    respond(true, [
        'detail' => [
            'case_id' => (string)$detail['case_id'],
            'complaint_id' => $detail['complaint_id'] ?? '',
            'submitted_at' => $detail['submitted_at'] ?? '',
            'incident_date' => $detail['incident_date'] ?? '',
            'incident_time' => $detail['incident_time'] ?? '',
            'incident_place' => $detail['incident_place'] ?? '',
            'complaint_type' => $detail['complaint_type'] ?? '',
            'case_details' => $detail['case_details'] ?? '',
            'case_remarks' => $detail['case_remarks'] ?? '',
            'status_name' => $detail['status_name'] ?? 'Pending',
            'level_name' => $detail['level_name'] ?? 'Complaint Only',
            'subject_kind' => $detail['subject_kind'] ?? '',
            'subject_display_name' => $detail['subject_display_name'] ?? '',
            'subject_contact_number' => $detail['subject_contact_number'] ?? '',
            'subject_address' => $detail['subject_address'] ?? '',
            'witness_summary' => $detail['witness_summary'] ?? '',
            'intake_notes' => $detail['intake_notes'] ?? '',
            'screening_notes' => $detail['screening_notes'] ?? '',
            'escalated_to_blotter' => (int)($detail['escalated_to_blotter'] ?? 0),
            'blotter_id' => $detail['blotter_id'] ?? null,
            'blotter_number' => $detail['blotter_number'] ?? null,
            'complaint_origin' => $detail['complaint_origin'] ?? '',
            'complainant' => $participants['Complainant'] ?? null,
            'respondent' => $participants['Respondent'] ?? null,
            'witness' => $participants['Witness'] ?? null,
        ]
    ]);
}

if ($action === 'update_intake_notes') {
    if ($requestMethod !== 'POST') {
        respond(false, [], 'Method not allowed.');
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? ''));
    $intakeNotes = trim((string)($jsonInput['intake_notes'] ?? ''));

    if ($caseId === '') {
        respond(false, [], 'Invalid case ID.');
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($actorUserId === '') {
        respond(false, [], 'User session not found.');
    }

    $existsStmt = $conn->prepare("
        SELECT 1
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        WHERE c.case_id = ? AND c.report_type = 'Complaint'
        LIMIT 1
    ");
    if (!$existsStmt) {
        respond(false, [], 'Failed to validate complaint case.');
    }
    $existsStmt->bind_param("s", $caseId);
    $existsStmt->execute();
    $exists = $existsStmt->get_result()->fetch_row();
    $existsStmt->close();
    if (!$exists) {
        respond(false, [], 'Complaint case not found.');
    }

    $conn->begin_transaction();
    try {
        $updateComplaintStmt = $conn->prepare("
            UPDATE complaintstbl
            SET intake_notes = ?
            WHERE case_id = ?
            LIMIT 1
        ");
        if (!$updateComplaintStmt) {
            throw new Exception('Failed to prepare intake notes update.');
        }
        $updateComplaintStmt->bind_param("ss", $intakeNotes, $caseId);
        $updateComplaintStmt->execute();
        $updateComplaintStmt->close();

        $updateCaseStmt = $conn->prepare("
            UPDATE casereportstbl
            SET user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Complaint'
            LIMIT 1
        ");
        if (!$updateCaseStmt) {
            throw new Exception('Failed to prepare complaint updater.');
        }
        $updateCaseStmt->bind_param("ss", $actorUserId, $caseId);
        $updateCaseStmt->execute();
        $updateCaseStmt->close();

        if (tableExists($conn, 'caseupdateslogtbl')) {
            $logStmt = $conn->prepare("
                INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
                VALUES (?, ?, ?)
            ");
            if (!$logStmt) {
                throw new Exception('Failed to prepare complaint case log.');
            }
            $logEntry = 'Intake notes updated.';
            $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();
        respond(true, [], 'Intake notes updated.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, [], 'Failed to update intake notes.');
    }
}

if ($action === 'update_case_outcome') {
    if ($requestMethod !== 'POST') {
        respond(false, [], 'Method not allowed.');
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? ''));
    $actionType = strtolower(trim((string)($jsonInput['action_type'] ?? '')));
    $remarks = trim((string)($jsonInput['remarks'] ?? ''));

    if ($caseId === '') {
        respond(false, [], 'Invalid case ID.');
    }
    if ($remarks === '') {
        respond(false, [], 'Remarks are required.');
    }
    if (!in_array($actionType, ['resolved', 'endorsement', 'dropped'], true)) {
        respond(false, [], 'Invalid action type.');
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($actorUserId === '') {
        respond(false, [], 'User session not found.');
    }

    $oldStmt = $conn->prepare("
        SELECT
            c.case_id,
            s.status_name AS old_status_name,
            l.status_name AS old_level_name,
            c.case_remarks,
            ct.screening_notes,
            ct.escalated_to_blotter,
            ct.blotter_id
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        WHERE c.case_id = ? AND c.report_type = 'Complaint'
        LIMIT 1
    ");
    if (!$oldStmt) {
        respond(false, [], 'Failed to load current complaint state.');
    }
    $oldStmt->bind_param("s", $caseId);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if (!$oldRow) {
        respond(false, [], 'Complaint case not found.');
    }

    $oldStatusName = trim((string)($oldRow['old_status_name'] ?? 'Pending'));
    $oldLevelName = trim((string)($oldRow['old_level_name'] ?? 'Complaint Only'));
    if (in_array(strtolower($oldStatusName), ['resolved', 'dropped', 'endorsed'], true)) {
        respond(false, [], 'Complaint status is already finalized and cannot be changed again.');
    }

    $newStatusName = '';
    $newLevelName = 'Complaint Only';
    $markEscalated = 0;
    if ($actionType === 'resolved') {
        $newStatusName = 'Resolved';
    } elseif ($actionType === 'dropped') {
        $newStatusName = 'Dropped';
    } else {
        $newStatusName = 'Endorsed';
        $newLevelName = 'Endorsed to Blotter';
        $markEscalated = 1;
    }

    $newStatusId = getStatusId($conn, $newStatusName, 'Complaint');
    $newLevelId = getStatusId($conn, $newLevelName, 'ComplaintLevel');
    if ($newStatusId <= 0 || $newLevelId <= 0) {
        respond(false, [], 'Status or complaint level mapping not found in statuslookuptbl.');
    }

    $updatedScreeningNotes = appendMultilineNote($oldRow['screening_notes'] ?? '', $remarks);
    $logEntry = "Complaint status updated: {$oldStatusName} -> {$newStatusName}; Complaint level: {$oldLevelName} -> {$newLevelName}; Remarks: {$remarks}";

    $conn->begin_transaction();
    try {
        $updateStmt = $conn->prepare("
            UPDATE casereportstbl
            SET case_status_id = ?, case_level_id = ?, case_remarks = ?, user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Complaint'
            LIMIT 1
        ");
        if (!$updateStmt) {
            throw new Exception('Failed to prepare complaint update.');
        }
        $updateStmt->bind_param("iisss", $newStatusId, $newLevelId, $oldRow['case_remarks'], $actorUserId, $caseId);
        $updateStmt->execute();
        $updateStmt->close();

        $complaintUpdateStmt = $conn->prepare("
            UPDATE complaintstbl
            SET escalated_to_blotter = ?,
                escalated_to_blotter_at = CASE WHEN ? = 1 THEN NOW() ELSE escalated_to_blotter_at END,
                escalated_by_user_id = CASE WHEN ? = 1 THEN ? ELSE escalated_by_user_id END,
                screening_notes = ?
            WHERE case_id = ?
            LIMIT 1
        ");
        if (!$complaintUpdateStmt) {
            throw new Exception('Failed to prepare complaint metadata update.');
        }
        $complaintUpdateStmt->bind_param("iiisss", $markEscalated, $markEscalated, $markEscalated, $actorUserId, $updatedScreeningNotes, $caseId);
        $complaintUpdateStmt->execute();
        $complaintUpdateStmt->close();

        if (tableExists($conn, 'caseupdateslogtbl')) {
            $logStmt = $conn->prepare("
                INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
                VALUES (?, ?, ?)
            ");
            if (!$logStmt) {
                throw new Exception('Failed to prepare complaint case log.');
            }
            $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();
        respond(true, [], 'Complaint updated.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, [], 'Failed to update complaint.');
    }
}

respond(false, [], 'Unsupported action.');
