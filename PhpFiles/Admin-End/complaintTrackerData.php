<?php
session_start();

require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

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

function participantDisplayName(array $row, string $prefix = ''): string
{
    return trim(implode(' ', array_filter([
        $row[$prefix . 'firstname'] ?? '',
        $row[$prefix . 'middlename'] ?? '',
        $row[$prefix . 'lastname'] ?? '',
        $row[$prefix . 'suffix'] ?? ''
    ])));
}

if (!tableExists($conn, 'complaintstbl')) {
    respond(false, [], 'Missing table complaintstbl. Run the complaint migration first.');
}

$action = trim((string)($_GET['action'] ?? 'list'));

if ($action === 'list') {
    $sql = "
        SELECT
            c.case_id,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d %h:%i %p') AS submitted_at,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d') AS submitted_date,
            c.complaint_type,
            s.status_name,
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
        LEFT JOIN caseparticipantstbl cp
            ON cp.case_id = c.case_id
            AND cp.participant_role = 'Complainant'
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
        } elseif (stripos($statusName, 'review') !== false) {
            $statusKey = 'review';
        }

        $items[] = [
            'case_id' => (int)$row['case_id'],
            'submitted_at' => $row['submitted_at'] ?? '',
            'submitted_date' => $row['submitted_date'] ?? '',
            'complaint_type' => $row['complaint_type'] ?? '',
            'status_name' => $statusName !== '' ? $statusName : 'Pending',
            'status_key' => $statusKey,
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
    $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
    if ($caseId <= 0) {
        respond(false, [], 'Invalid case ID.');
    }

    $stmt = $conn->prepare("
        SELECT
            c.case_id,
            DATE_FORMAT(c.report_timestamp, '%Y-%m-%d %h:%i %p') AS submitted_at,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            c.case_remarks,
            s.status_name,
            ct.subject_kind,
            ct.subject_display_name,
            ct.subject_contact_number,
            ct.subject_address,
            ct.witness_summary,
            ct.intake_notes,
            ct.screening_notes,
            ct.escalated_to_blotter,
            ct.blotter_id,
            ct.complaint_origin
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE c.case_id = ?
          AND c.report_type = 'Complaint'
        LIMIT 1
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare complaint detail query.');
    }

    $stmt->bind_param("i", $caseId);
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

    $stmt->bind_param("i", $caseId);
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
            'case_id' => (int)$detail['case_id'],
            'submitted_at' => $detail['submitted_at'] ?? '',
            'incident_date' => $detail['incident_date'] ?? '',
            'incident_time' => $detail['incident_time'] ?? '',
            'incident_place' => $detail['incident_place'] ?? '',
            'complaint_type' => $detail['complaint_type'] ?? '',
            'case_details' => $detail['case_details'] ?? '',
            'case_remarks' => $detail['case_remarks'] ?? '',
            'status_name' => $detail['status_name'] ?? 'Pending',
            'subject_kind' => $detail['subject_kind'] ?? '',
            'subject_display_name' => $detail['subject_display_name'] ?? '',
            'subject_contact_number' => $detail['subject_contact_number'] ?? '',
            'subject_address' => $detail['subject_address'] ?? '',
            'witness_summary' => $detail['witness_summary'] ?? '',
            'intake_notes' => $detail['intake_notes'] ?? '',
            'screening_notes' => $detail['screening_notes'] ?? '',
            'escalated_to_blotter' => (int)($detail['escalated_to_blotter'] ?? 0),
            'blotter_id' => $detail['blotter_id'] ?? null,
            'complaint_origin' => $detail['complaint_origin'] ?? '',
            'complainant' => $participants['Complainant'] ?? null,
            'respondent' => $participants['Respondent'] ?? null,
            'witness' => $participants['Witness'] ?? null,
        ]
    ]);
}

respond(false, [], 'Unsupported action.');
