<?php
session_start();

require_once "../General/connection.php";
require_once "../General/caseUserAccountForeignKeys.php";
require_once "../General/security.php";
require_once "../General/uniqueIDGenerate.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);
cuafk_ensure_case_useraccount_foreign_keys($conn);

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

    return trim(implode(' ', array_filter([$first, $middleInitial, $last, $suffix])));
}

function formatDisplayTimestamp(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    try {
        $date = new DateTime($value);
        return $date->format('M d, Y h:i A');
    } catch (Throwable $e) {
        return $value;
    }
}

function createBlotterFromComplaint(mysqli $conn, array $complaintRow, string $actorUserId, string $remarks, string $blotterNumber): array
{
    $blotterStatusId = getStatusId($conn, 'Active', 'Blotter');
    $blotterLevelId = getStatusId($conn, 'Blotter Only', 'BlotterLevel');
    if ($blotterStatusId <= 0 || $blotterLevelId <= 0) {
        throw new Exception('Blotter status or level mapping not found in statuslookuptbl.');
    }

    $blotterCaseId = GenerateCaseID($conn);
    $blotterId = GenerateBlotterID($conn);
    if (!$blotterCaseId || !$blotterId) {
        throw new Exception('Failed to generate blotter identifiers.');
    }

    $blotterNumber = trim($blotterNumber);
    if ($blotterNumber === '') {
        throw new Exception('Blotter number is required.');
    }
    $dateFiled = date('Y-m-d');
    $timeFiled = date('H:i:s');
    $residentUserId = trim((string)($complaintRow['resident_user_id'] ?? ''));
    if ($residentUserId === '') {
        $residentUserId = null;
    }

    $caseRemarks = trim((string)($complaintRow['case_remarks'] ?? ''));
    $endorsementNote = 'Approved from blotter review request for complaint ' . trim((string)($complaintRow['complaint_id'] ?? $complaintRow['case_id'] ?? ''));
    if ($remarks !== '') {
        $endorsementNote .= '. Review notes: ' . $remarks;
    }
    $caseRemarks = appendMultilineNote($caseRemarks, $endorsementNote);

    $insertCaseStmt = $conn->prepare("
        INSERT INTO casereportstbl
            (case_id, resident_user_id, report_type, incident_date, incident_time, incident_place, complaint_type,
             case_details, case_remarks, case_status_id, case_level_id, user_id_official_update_by, user_id_official_reviewed_by, user_id_official_record_by)
        VALUES
            (?, ?, 'Blotter', ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)
    ");
    if (!$insertCaseStmt) {
        throw new Exception('Failed to prepare blotter case insert.');
    }
    $insertCaseStmt->bind_param(
        'ssssssssiiss',
        $blotterCaseId,
        $residentUserId,
        $complaintRow['incident_date'],
        $complaintRow['incident_time'],
        $complaintRow['incident_place'],
        $complaintRow['complaint_type'],
        $complaintRow['case_details'],
        $caseRemarks,
        $blotterStatusId,
        $blotterLevelId,
        $actorUserId,
        $actorUserId
    );
    $insertCaseStmt->execute();
    $insertCaseStmt->close();

    $insertBlotterStmt = $conn->prepare("
        INSERT INTO barangayblottertbl
            (blotter_id, case_id, blotter_number, logbook_id, date_filed, time_filed)
        VALUES
            (?, ?, ?, NULL, ?, ?)
    ");
    if (!$insertBlotterStmt) {
        throw new Exception('Failed to prepare blotter insert.');
    }
    $insertBlotterStmt->bind_param('sssss', $blotterId, $blotterCaseId, $blotterNumber, $dateFiled, $timeFiled);
    $insertBlotterStmt->execute();
    $insertBlotterStmt->close();

    $participantSelectStmt = $conn->prepare("
        SELECT participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks
        FROM caseparticipantstbl
        WHERE case_id = ?
        ORDER BY participant_id ASC
    ");
    if (!$participantSelectStmt) {
        throw new Exception('Failed to load complaint participants: ' . $conn->error);
    }
    $participantSelectStmt->bind_param('s', $complaintRow['case_id']);
    $participantSelectStmt->execute();
    $participantRows = $participantSelectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $participantSelectStmt->close();

    if (!empty($participantRows)) {
        $participantInsertStmt = $conn->prepare("
            INSERT INTO caseparticipantstbl
                (case_id, participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$participantInsertStmt) {
            throw new Exception('Failed to prepare blotter participant insert.');
        }

        foreach ($participantRows as $participantRow) {
            $participantInsertStmt->bind_param(
                'ssssssssssss',
                $blotterCaseId,
                $participantRow['participant_role'],
                $participantRow['lastname'],
                $participantRow['firstname'],
                $participantRow['middlename'],
                $participantRow['suffix'],
                $participantRow['contact_number'],
                $participantRow['email'],
                $participantRow['address'],
                $participantRow['age'],
                $participantRow['sex'],
                $participantRow['remarks']
            );
            $participantInsertStmt->execute();
        }
        $participantInsertStmt->close();
    }

    if (tableExists($conn, 'caseupdateslogtbl')) {
        $blotterLogStmt = $conn->prepare("
            INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
            VALUES (?, ?, ?)
        ");
        if (!$blotterLogStmt) {
            throw new Exception('Failed to prepare blotter case log.');
        }
        $blotterLogEntry = $endorsementNote;
        $blotterLogStmt->bind_param('sss', $blotterCaseId, $blotterLogEntry, $actorUserId);
        $blotterLogStmt->execute();
        $blotterLogStmt->close();
    }

    return [
        'case_id' => $blotterCaseId,
        'blotter_id' => $blotterId,
        'blotter_number' => $blotterNumber,
    ];
}

if (!tableExists($conn, 'blotterrequeststbl')) {
    respond(false, [], 'Missing table blotterrequeststbl. Run the blotter request migration first.');
}

$action = '';
if ($requestMethod === 'POST') {
    $action = trim((string)($jsonInput['action'] ?? $_POST['action'] ?? ''));
}
if ($action === '') {
    $action = trim((string)($_GET['action'] ?? 'list'));
}

if ($action === 'list') {
    $stmt = $conn->prepare("
        SELECT
            br.request_id,
            br.complaint_case_id,
            br.complaint_id,
            br.review_notes,
            br.requested_at,
            br.reviewed_at,
            s.status_name AS request_status_name,
            c.complaint_type,
            ct.subject_display_name,
            cp.firstname,
            cp.middlename,
            cp.lastname,
            cp.suffix
        FROM blotterrequeststbl br
        INNER JOIN complaintstbl ct ON ct.complaint_id = br.complaint_id
        INNER JOIN casereportstbl c ON c.case_id = br.complaint_case_id AND c.report_type = 'Complaint'
        LEFT JOIN statuslookuptbl s ON s.status_id = br.request_status_id
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
        ) cp ON cp.case_id = br.complaint_case_id
        ORDER BY br.requested_at DESC, br.request_id DESC
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare blotter request list query.');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'request_id' => $row['request_id'] ?? '',
            'complaint_case_id' => $row['complaint_case_id'] ?? '',
            'complaint_id' => $row['complaint_id'] ?? '',
            'request_status_name' => $row['request_status_name'] ?? 'Pending',
            'complaint_type' => $row['complaint_type'] ?? '',
            'subject_display_name' => $row['subject_display_name'] ?? '',
            'review_notes' => $row['review_notes'] ?? '',
            'requested_at' => formatDisplayTimestamp($row['requested_at'] ?? ''),
            'reviewed_at' => formatDisplayTimestamp($row['reviewed_at'] ?? ''),
            'complainant_name' => participantDisplayName($row),
        ];
    }
    $stmt->close();
    respond(true, ['items' => $items]);
}

if ($action === 'detail') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        respond(false, [], 'Invalid request ID.');
    }

    $stmt = $conn->prepare("
        SELECT
            br.request_id,
            br.complaint_case_id,
            br.complaint_id,
            br.review_notes,
            br.requested_at,
            br.reviewed_at,
            br.approved_blotter_case_id,
            br.approved_blotter_id,
            s.status_name AS request_status_name,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            c.case_remarks,
            ct.subject_display_name,
            ct.subject_kind,
            ct.subject_contact_number,
            ct.subject_address,
            ct.screening_notes,
            ct.intake_notes
        FROM blotterrequeststbl br
        INNER JOIN complaintstbl ct ON ct.complaint_id = br.complaint_id
        INNER JOIN casereportstbl c ON c.case_id = br.complaint_case_id AND c.report_type = 'Complaint'
        LEFT JOIN statuslookuptbl s ON s.status_id = br.request_status_id
        WHERE br.request_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare blotter request detail query.');
    }
    $stmt->bind_param("s", $requestId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$detail) {
        respond(false, [], 'Blotter request not found.');
    }

    $stmt = $conn->prepare("
        SELECT participant_role, firstname, middlename, lastname, suffix, contact_number, address, age, sex
        FROM caseparticipantstbl
        WHERE case_id = ?
        ORDER BY participant_role ASC
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare participant query.');
    }
    $stmt->bind_param("s", $detail['complaint_case_id']);
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
        ];
    }
    $stmt->close();

    respond(true, ['detail' => [
        'request_id' => $detail['request_id'] ?? '',
        'complaint_case_id' => $detail['complaint_case_id'] ?? '',
        'complaint_id' => $detail['complaint_id'] ?? '',
        'request_status_name' => $detail['request_status_name'] ?? 'Pending',
        'review_notes' => $detail['review_notes'] ?? '',
        'requested_at' => formatDisplayTimestamp($detail['requested_at'] ?? ''),
        'reviewed_at' => formatDisplayTimestamp($detail['reviewed_at'] ?? ''),
        'approved_blotter_case_id' => $detail['approved_blotter_case_id'] ?? '',
        'approved_blotter_id' => $detail['approved_blotter_id'] ?? '',
        'incident_date' => $detail['incident_date'] ?? '',
        'incident_time' => $detail['incident_time'] ?? '',
        'incident_place' => $detail['incident_place'] ?? '',
        'complaint_type' => $detail['complaint_type'] ?? '',
        'case_details' => $detail['case_details'] ?? '',
        'case_remarks' => $detail['case_remarks'] ?? '',
        'subject_display_name' => $detail['subject_display_name'] ?? '',
        'subject_kind' => $detail['subject_kind'] ?? '',
        'subject_contact_number' => $detail['subject_contact_number'] ?? '',
        'subject_address' => $detail['subject_address'] ?? '',
        'screening_notes' => $detail['screening_notes'] ?? '',
        'intake_notes' => $detail['intake_notes'] ?? '',
        'complainant' => $participants['Complainant'] ?? null,
        'respondent' => $participants['Respondent'] ?? null,
        'witness' => $participants['Witness'] ?? null,
    ]]);
}

if ($action === 'update_request_status') {
    if ($requestMethod !== 'POST') {
        respond(false, [], 'Method not allowed.');
    }

    $requestId = trim((string)($jsonInput['request_id'] ?? ''));
    $actionType = strtolower(trim((string)($jsonInput['action_type'] ?? '')));
    $reviewNotes = trim((string)($jsonInput['review_notes'] ?? ''));
    $blotterNumber = trim((string)($jsonInput['blotter_number'] ?? ''));

    if ($requestId === '') {
        respond(false, [], 'Invalid request ID.');
    }
    if (!in_array($actionType, ['approved', 'rejected'], true)) {
        respond(false, [], 'Invalid request action.');
    }
    if ($actionType === 'rejected' && $reviewNotes === '') {
        respond(false, [], 'Review notes are required when rejecting a request.');
    }
    if ($actionType === 'approved') {
        if ($blotterNumber === '') {
            respond(false, [], 'Blotter number is required.');
        }
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $blotterNumber)) {
            respond(false, [], 'Blotter number may contain letters, numbers, and hyphens only.');
        }
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($actorUserId === '') {
        respond(false, [], 'User session not found.');
    }

    $stmt = $conn->prepare("
        SELECT
            br.request_id,
            br.complaint_case_id,
            br.complaint_id,
            br.review_notes AS existing_review_notes,
            s.status_name AS request_status_name,
            c.resident_user_id,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            c.case_remarks,
            ct.screening_notes,
            ct.blotter_id
        FROM blotterrequeststbl br
        INNER JOIN complaintstbl ct ON ct.complaint_id = br.complaint_id
        INNER JOIN casereportstbl c ON c.case_id = br.complaint_case_id AND c.report_type = 'Complaint'
        LEFT JOIN statuslookuptbl s ON s.status_id = br.request_status_id
        WHERE br.request_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to load blotter request.');
    }
    $stmt->bind_param("s", $requestId);
    $stmt->execute();
    $requestRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$requestRow) {
        respond(false, [], 'Blotter request not found.');
    }

    if (strtolower(trim((string)($requestRow['request_status_name'] ?? ''))) !== 'pending') {
        respond(false, [], 'Only pending requests can be updated.');
    }

    $approvedStatusId = getStatusId($conn, 'Approved', 'BlotterRequest');
    $rejectedStatusId = getStatusId($conn, 'Rejected', 'BlotterRequest');
    $pendingComplaintStatusId = getStatusId($conn, 'Pending', 'Complaint');
    $endorsedComplaintStatusId = getStatusId($conn, 'Endorsed', 'Complaint');
    $complaintOnlyLevelId = getStatusId($conn, 'Complaint Only', 'ComplaintLevel');
    $endorsedLevelId = getStatusId($conn, 'Endorsed to Blotter', 'ComplaintLevel');

    if ($approvedStatusId <= 0 || $rejectedStatusId <= 0 || $pendingComplaintStatusId <= 0 || $endorsedComplaintStatusId <= 0 || $complaintOnlyLevelId <= 0 || $endorsedLevelId <= 0) {
        respond(false, [], 'Required status or level mappings were not found.');
    }

    $updatedReviewNotes = appendMultilineNote($requestRow['existing_review_notes'] ?? '', $reviewNotes);
    $conn->begin_transaction();
    try {
        $createdBlotter = null;
        if ($actionType === 'approved') {
            if (trim((string)($requestRow['blotter_id'] ?? '')) !== '') {
                throw new Exception('Complaint is already linked to a blotter.');
            }

            $createdBlotter = createBlotterFromComplaint($conn, [
                'case_id' => $requestRow['complaint_case_id'],
                'complaint_id' => $requestRow['complaint_id'],
                'resident_user_id' => $requestRow['resident_user_id'],
                'incident_date' => $requestRow['incident_date'],
                'incident_time' => $requestRow['incident_time'],
                'incident_place' => $requestRow['incident_place'],
                'complaint_type' => $requestRow['complaint_type'],
                'case_details' => $requestRow['case_details'],
                'case_remarks' => $requestRow['case_remarks'],
            ], $actorUserId, $reviewNotes, $blotterNumber);

            $updateRequestStmt = $conn->prepare("
                UPDATE blotterrequeststbl
                SET request_status_id = ?,
                    review_notes = ?,
                    reviewed_by_user_id = ?,
                    reviewed_at = NOW(),
                    approved_blotter_case_id = ?,
                    approved_blotter_id = ?
                WHERE request_id = ?
                LIMIT 1
            ");
            if (!$updateRequestStmt) {
                throw new Exception('Failed to prepare blotter request approval.');
            }
            $updateRequestStmt->bind_param(
                "isssss",
                $approvedStatusId,
                $updatedReviewNotes,
                $actorUserId,
                $createdBlotter['case_id'],
                $createdBlotter['blotter_id'],
                $requestId
            );
            $updateRequestStmt->execute();
            $updateRequestStmt->close();

            $updatedScreeningNotes = appendMultilineNote($requestRow['screening_notes'] ?? '', $reviewNotes);
            $complaintRemark = appendMultilineNote($requestRow['case_remarks'] ?? '', 'Blotter request approved.' . ($reviewNotes !== '' ? ' Review notes: ' . $reviewNotes : ''));

            $updateCaseStmt = $conn->prepare("
                UPDATE casereportstbl
                SET case_status_id = ?, case_level_id = ?, case_remarks = ?, user_id_official_update_by = ?
                WHERE case_id = ? AND report_type = 'Complaint'
                LIMIT 1
            ");
            if (!$updateCaseStmt) {
                throw new Exception('Failed to prepare complaint case approval update.');
            }
            $updateCaseStmt->bind_param("iisss", $endorsedComplaintStatusId, $endorsedLevelId, $complaintRemark, $actorUserId, $requestRow['complaint_case_id']);
            $updateCaseStmt->execute();
            $updateCaseStmt->close();

            $updateComplaintStmt = $conn->prepare("
                UPDATE complaintstbl
                SET escalated_to_blotter = 1,
                    escalated_to_blotter_at = NOW(),
                    escalated_by_user_id = ?,
                    screening_notes = ?,
                    blotter_id = ?
                WHERE complaint_id = ?
                LIMIT 1
            ");
            if (!$updateComplaintStmt) {
                throw new Exception('Failed to prepare complaint approval metadata update.');
            }
            $updateComplaintStmt->bind_param("ssss", $actorUserId, $updatedScreeningNotes, $createdBlotter['blotter_id'], $requestRow['complaint_id']);
            $updateComplaintStmt->execute();
            $updateComplaintStmt->close();
        } else {
            $updateRequestStmt = $conn->prepare("
                UPDATE blotterrequeststbl
                SET request_status_id = ?,
                    review_notes = ?,
                    reviewed_by_user_id = ?,
                    reviewed_at = NOW()
                WHERE request_id = ?
                LIMIT 1
            ");
            if (!$updateRequestStmt) {
                throw new Exception('Failed to prepare blotter request rejection.');
            }
            $updateRequestStmt->bind_param("isss", $rejectedStatusId, $updatedReviewNotes, $actorUserId, $requestId);
            $updateRequestStmt->execute();
            $updateRequestStmt->close();

            $updatedScreeningNotes = appendMultilineNote($requestRow['screening_notes'] ?? '', $reviewNotes);
            $complaintRemark = appendMultilineNote($requestRow['case_remarks'] ?? '', 'Blotter request rejected. Review notes: ' . $reviewNotes);

            $updateCaseStmt = $conn->prepare("
                UPDATE casereportstbl
                SET case_status_id = ?, case_level_id = ?, case_remarks = ?, user_id_official_update_by = ?
                WHERE case_id = ? AND report_type = 'Complaint'
                LIMIT 1
            ");
            if (!$updateCaseStmt) {
                throw new Exception('Failed to prepare complaint case rejection update.');
            }
            $updateCaseStmt->bind_param("iisss", $pendingComplaintStatusId, $complaintOnlyLevelId, $complaintRemark, $actorUserId, $requestRow['complaint_case_id']);
            $updateCaseStmt->execute();
            $updateCaseStmt->close();

            $updateComplaintStmt = $conn->prepare("
                UPDATE complaintstbl
                SET screening_notes = ?
                WHERE complaint_id = ?
                LIMIT 1
            ");
            if (!$updateComplaintStmt) {
                throw new Exception('Failed to prepare complaint rejection metadata update.');
            }
            $updateComplaintStmt->bind_param("ss", $updatedScreeningNotes, $requestRow['complaint_id']);
            $updateComplaintStmt->execute();
            $updateComplaintStmt->close();
        }

        if (tableExists($conn, 'caseupdateslogtbl')) {
            $logStmt = $conn->prepare("
                INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
                VALUES (?, ?, ?)
            ");
            if ($logStmt) {
                $statusLabel = $actionType === 'approved' ? 'approved' : 'rejected';
                $logEntry = 'Blotter review request ' . $requestId . ' ' . $statusLabel . '.' . ($reviewNotes !== '' ? ' Review notes: ' . $reviewNotes : '');
                $logStmt->bind_param("sss", $requestRow['complaint_case_id'], $logEntry, $actorUserId);
                $logStmt->execute();
                $logStmt->close();
            }
        }

        $conn->commit();
        respond(true, [], $actionType === 'approved' ? 'Blotter request approved.' : 'Blotter request rejected.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, [], 'Failed to update blotter request: ' . $e->getMessage());
    }
}

respond(false, [], 'Unsupported action.');
