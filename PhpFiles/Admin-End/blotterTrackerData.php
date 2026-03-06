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

$action = $_GET['action'] ?? 'list';

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

respond(false, [], "Unsupported action.");
