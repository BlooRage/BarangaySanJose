<?php
require_once "../General/security.php";

header('Content-Type: application/json; charset=utf-8');

$allowedRoles = ['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'];
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestAction = trim((string)($_GET['action'] ?? 'list'));
$authChecked = false;
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

function complaintTrackerCachePath(string $key): string
{
    $safeKey = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $key) ?? 'cache';
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_complaint_tracker_' . $safeKey . '.cache';
}

function complaintTrackerCacheGet(string $key, int $ttlSeconds): ?array
{
    if ($key === '' || $ttlSeconds <= 0) {
        return null;
    }

    $path = complaintTrackerCachePath($key);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return null;
    }

    $createdAt = (int)($payload['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > $ttlSeconds) {
        return null;
    }

    $value = $payload['value'] ?? null;
    return is_array($value) ? $value : null;
}

function complaintTrackerCachePut(string $key, array $value): void
{
    if ($key === '') {
        return;
    }

    $payload = json_encode([
        'created_at' => time(),
        'value' => $value,
    ]);
    if (!is_string($payload) || $payload === '') {
        return;
    }

    @file_put_contents(complaintTrackerCachePath($key), $payload, LOCK_EX);
}

function complaintTrackerCacheClear(): void
{
    $pattern = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_complaint_tracker_*.cache';
    $matches = glob($pattern);
    if (!is_array($matches)) {
        return;
    }

    foreach ($matches as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
}

function complaintTrackerListCacheKey(array $query, string $userId, string $role): string
{
    ksort($query);

    return md5(json_encode([
        'query' => $query,
        'user_id' => $userId,
        'role' => normalizeRoleName($role),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
}

if ($requestMethod === 'GET' && $requestAction === 'list') {
    requireRoleSession($allowedRoles);
    $authChecked = true;

    $listCacheKey = complaintTrackerListCacheKey(
        $_GET,
        trim((string)($_SESSION['user_id'] ?? '')),
        (string)($_SESSION['role'] ?? '')
    );
    $cachedResponse = complaintTrackerCacheGet($listCacheKey, 60);
    if (is_array($cachedResponse)) {
        echo json_encode($cachedResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

require_once "../General/connection.php";
require_once "../General/caseUserAccountForeignKeys.php";
require_once "../General/complaintTypeDetails.php";
require_once "../General/uniqueIDGenerate.php";

if (!$authChecked) {
    requireRoleSession($allowedRoles);
}
cuafk_ensure_case_useraccount_foreign_keys($conn);

function respond($success, $payload = [], $message = ''): void
{
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'items' => $payload['items'] ?? null,
        'detail' => $payload['detail'] ?? null,
        'meta' => $payload['meta'] ?? null,
    ]);
    exit;
}

function tableExists(mysqli $conn, string $tableName): bool
{
    static $cache = [];

    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    $cacheKey = strtolower($tableName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

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

    return $cache[$cacheKey] = !empty($row);
}

function columnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    static $cache = [];

    $tableName = trim($tableName);
    $columnName = trim($columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $cacheKey = strtolower($tableName . '|' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$cacheKey] = !empty($row);
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

function ensureStatusId(mysqli $conn, string $statusName, string $statusType): int
{
    $existingId = getStatusId($conn, $statusName, $statusType);
    if ($existingId > 0) {
        return $existingId;
    }

    $stmt = $conn->prepare("
        INSERT INTO statuslookuptbl (status_name, status_type)
        VALUES (?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Failed to create status lookup entry.');
    }

    $stmt->bind_param("ss", $statusName, $statusType);
    $stmt->execute();
    $statusId = (int)$conn->insert_id;
    $stmt->close();

    return $statusId;
}

function ensureComplaintWorkflowLookups(mysqli $conn): array
{
    $statusIds = [];
    foreach (['Pending', 'Under Investigation', 'Action in Progress', 'Resolved', 'Closed', 'Endorsed'] as $statusName) {
        $statusIds[$statusName] = ensureStatusId($conn, $statusName, 'Complaint');
    }

    $levelIds = [];
    foreach (['Complaint Only', 'Endorsed to Blotter'] as $levelName) {
        $levelIds[$levelName] = ensureStatusId($conn, $levelName, 'ComplaintLevel');
    }

    return [
        'status' => $statusIds,
        'level' => $levelIds,
    ];
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

function parseCsvValues($value): array
{
    $rawValues = is_array($value) ? $value : explode(',', (string)$value);
    $items = [];
    foreach ($rawValues as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $items[$item] = true;
        }
    }

    return array_keys($items);
}

function complaintClassificationOptions(): array
{
    $definitions = function_exists('complaintTypeDefinitions')
        ? complaintTypeDefinitions()
        : [];

    $options = [];
    foreach ($definitions as $key => $definition) {
        $label = trim((string)($definition['label'] ?? $key));
        if ($label !== '') {
            $options[$label] = $label;
        }
    }

    return array_values($options);
}

function isValidComplaintClassification(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    return in_array($value, complaintClassificationOptions(), true);
}

function syncComplaintCaseDetailsClassification(?string $caseDetails, string $classification): string
{
    $classification = trim($classification);
    $rawCaseDetails = (string)$caseDetails;
    if ($classification === '' || !function_exists('complaintTypeParseCaseDetails') || !function_exists('complaintTypeBuildCaseDetails')) {
        return $rawCaseDetails;
    }

    $parsed = complaintTypeParseCaseDetails($rawCaseDetails);
    if (!is_array($parsed) || !is_array($parsed['meta'] ?? null)) {
        return $rawCaseDetails;
    }

    $selectedType = isValidComplaintClassification($classification) ? $classification : trim((string)($parsed['meta']['selected_type'] ?? ''));
    if ($selectedType === '') {
        $selectedType = 'Other';
    }

    return complaintTypeBuildCaseDetails(
        (string)($parsed['narration'] ?? ''),
        [
            'selected_type' => $selectedType,
            'complaint_type' => $classification,
            'fields' => $parsed['fields'] ?? [],
        ],
        [
            'incident_area_number' => (string)($parsed['incident_area_number'] ?? ''),
            'attachments' => is_array($parsed['attachments'] ?? null) ? $parsed['attachments'] : [],
        ]
    );
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $stmt->bind_param($types, ...$params);
}

function getLatestBlotterRequest(mysqli $conn, string $caseId): ?array
{
    if (!tableExists($conn, 'blotterrequeststbl')) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            br.request_id,
            br.review_notes,
            br.requested_at,
            br.reviewed_at,
            br.approved_blotter_case_id,
            br.approved_blotter_id,
            s.status_name AS request_status_name
        FROM blotterrequeststbl br
        LEFT JOIN statuslookuptbl s ON s.status_id = br.request_status_id
        WHERE br.complaint_case_id = ?
        ORDER BY br.requested_at DESC, br.request_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $caseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function createBlotterRequest(mysqli $conn, array $complaintRow, string $actorUserId, string $remarks): array
{
    if (!tableExists($conn, 'blotterrequeststbl')) {
        throw new Exception('Missing table blotterrequeststbl. Run the blotter request migration first.');
    }

    $requestStatusId = getStatusId($conn, 'Pending', 'BlotterRequest');
    if ($requestStatusId <= 0) {
        throw new Exception('Blotter request pending status mapping not found in statuslookuptbl.');
    }

    $existing = getLatestBlotterRequest($conn, (string)$complaintRow['case_id']);
    if ($existing && strtolower(trim((string)($existing['request_status_name'] ?? ''))) === 'rejected') {
        $reopenedNotes = appendMultilineNote($existing['review_notes'] ?? '', $remarks);
        $stmt = $conn->prepare("
            UPDATE blotterrequeststbl
            SET request_status_id = ?,
                review_notes = ?,
                recommended_by_user_id = ?,
                reviewed_by_user_id = NULL,
                reviewed_at = NULL,
                approved_blotter_case_id = NULL,
                approved_blotter_id = NULL,
                updated_at = CURRENT_TIMESTAMP()
            WHERE request_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare blotter request reopen.');
        }
        $stmt->bind_param("isss", $requestStatusId, $reopenedNotes, $actorUserId, $existing['request_id']);
        $stmt->execute();
        $stmt->close();

        return [
            'request_id' => $existing['request_id'],
            'request_status_name' => 'Pending',
        ];
    }

    $requestId = GenerateBlotterRequestID($conn);
    if (!$requestId) {
        throw new Exception('Failed to generate blotter request ID.');
    }

    $stmt = $conn->prepare("
        INSERT INTO blotterrequeststbl
            (request_id, complaint_case_id, complaint_id, request_status_id, review_notes, recommended_by_user_id)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare blotter request insert.');
    }
    $stmt->bind_param(
        "sssiss",
        $requestId,
        $complaintRow['case_id'],
        $complaintRow['complaint_id'],
        $requestStatusId,
        $remarks,
        $actorUserId
    );
    $stmt->execute();
    $stmt->close();

    return [
        'request_id' => $requestId,
        'request_status_name' => 'Pending',
    ];
}

function createBlotterFromComplaint(mysqli $conn, array $complaintRow, string $actorUserId, string $remarks): array
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

    $blotterNumber = $blotterId;
    $dateFiled = date('Y-m-d');
    $timeFiled = date('H:i:s');
    $residentUserId = trim((string)($complaintRow['resident_user_id'] ?? ''));
    if ($residentUserId === '') {
        $residentUserId = null;
    }

    $caseRemarks = trim((string)($complaintRow['case_remarks'] ?? ''));
    $endorsementNote = 'Endorsed from complaint ' . trim((string)($complaintRow['complaint_id'] ?? $complaintRow['case_id'] ?? ''));
    if ($remarks !== '') {
        $endorsementNote .= '. Screening notes: ' . $remarks;
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
if (!tableExists($conn, 'complaintstbl')) {
    respond(false, [], 'Missing table complaintstbl. Run the complaint migration first.');
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
    $listCacheKey = complaintTrackerListCacheKey(
        $_GET,
        trim((string)($_SESSION['user_id'] ?? '')),
        (string)($_SESSION['role'] ?? '')
    );
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $searchTerm = trim((string)($_GET['search'] ?? ''));
    $statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
    if (!in_array($statusFilter, ['', 'active', 'resolved', 'closed'], true)) {
        $statusFilter = '';
    }
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $complaintTypeFilters = parseCsvValues($_GET['complaint_type'] ?? '');
    $areaFilters = parseCsvValues($_GET['area_number'] ?? '');
    $sectorFilters = parseCsvValues($_GET['sector_membership'] ?? '');

    $residentSelect = ",
            '' AS sector_membership,
            '' AS area_number";
    $residentJoin = '';
    if (
        tableExists($conn, 'residentinformationtbl')
        && tableExists($conn, 'residentaddresstbl')
        && columnExists($conn, 'casereportstbl', 'resident_user_id')
    ) {
        $residentSelect = ",
            COALESCE(ri.sector_membership, '') AS sector_membership,
            COALESCE(ra.area_number, '') AS area_number";
        $residentJoin = "
        LEFT JOIN residentinformationtbl ri ON ri.user_id = c.resident_user_id
        LEFT JOIN (
            SELECT ra1.resident_id, ra1.area_number
            FROM residentaddresstbl ra1
            INNER JOIN (
                SELECT resident_id, MAX(address_id) AS latest_address_id
                FROM residentaddresstbl
                GROUP BY resident_id
            ) latest_ra
                ON latest_ra.latest_address_id = ra1.address_id
        ) ra ON ra.resident_id = ri.resident_id";
    }

    $baseSql = "
        SELECT
            c.case_id,
            ct.complaint_id,
            c.report_timestamp AS submitted_at_raw,
            c.complaint_type,
            COALESCE(s.status_name, 'Pending') AS status_name,
            COALESCE(l.status_name, 'Complaint Only') AS level_name,
            ct.subject_display_name,
            ct.subject_kind,
            ct.escalated_to_blotter,
            ct.blotter_id,
            br.request_id,
            br.request_status_name,
            TRIM(CONCAT_WS(' ', cp.firstname, cp.middlename, cp.lastname, cp.suffix)) AS complainant_name,
            cp.firstname,
            cp.middlename,
            cp.lastname,
            cp.suffix
            {$residentSelect},
            CASE
                WHEN COALESCE(ct.escalated_to_blotter, 0) = 1 THEN 'escalated'
                WHEN LOWER(COALESCE(br.request_status_name, '')) IN ('pending', 'approved') THEN 'escalated'
                WHEN LOWER(COALESCE(s.status_name, '')) LIKE '%under investigation%' THEN 'under_investigation'
                WHEN LOWER(COALESCE(s.status_name, '')) LIKE '%action in progress%' THEN 'action_in_progress'
                WHEN LOWER(COALESCE(s.status_name, 'pending')) LIKE '%resolved%' THEN 'resolved'
                WHEN LOWER(COALESCE(s.status_name, '')) LIKE '%closed%' THEN 'closed'
                WHEN LOWER(COALESCE(s.status_name, '')) LIKE '%drop%' THEN 'dropped'
                WHEN LOWER(COALESCE(s.status_name, '')) LIKE '%endorse%' THEN 'escalated'
                ELSE 'pending'
            END AS status_key
        FROM casereportstbl c
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
        LEFT JOIN (
            SELECT
                br1.complaint_case_id,
                br1.request_id,
                COALESCE(s1.status_name, 'Pending') AS request_status_name
            FROM blotterrequeststbl br1
            INNER JOIN (
                SELECT complaint_case_id, MAX(request_id) AS latest_request_id
                FROM blotterrequeststbl
                GROUP BY complaint_case_id
            ) latest_br
                ON latest_br.latest_request_id = br1.request_id
            LEFT JOIN statuslookuptbl s1 ON s1.status_id = br1.request_status_id
        ) br
            ON br.complaint_case_id = c.case_id
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
        {$residentJoin}
        WHERE c.report_type = 'Complaint'
    ";

    $filterSql = '';
    $filterTypes = '';
    $filterParams = [];

    if ($statusFilter === 'active') {
        $filterSql .= " AND complaint_rows.status_key IN ('pending', 'under_investigation', 'action_in_progress', 'escalated')";
    } elseif ($statusFilter === 'closed') {
        $filterSql .= " AND complaint_rows.status_key IN ('closed', 'dropped')";
    } elseif ($statusFilter === 'resolved') {
        $filterSql .= " AND complaint_rows.status_key = ?";
        $filterTypes .= 's';
        $filterParams[] = $statusFilter;
    }

    if ($dateFrom !== '') {
        $filterSql .= " AND DATE(complaint_rows.submitted_at_raw) >= ?";
        $filterTypes .= 's';
        $filterParams[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $filterSql .= " AND DATE(complaint_rows.submitted_at_raw) <= ?";
        $filterTypes .= 's';
        $filterParams[] = $dateTo;
    }

    if (!empty($complaintTypeFilters)) {
        $placeholders = implode(', ', array_fill(0, count($complaintTypeFilters), '?'));
        $filterSql .= " AND complaint_rows.complaint_type IN ({$placeholders})";
        $filterTypes .= str_repeat('s', count($complaintTypeFilters));
        array_push($filterParams, ...$complaintTypeFilters);
    }

    if (!empty($areaFilters)) {
        $placeholders = implode(', ', array_fill(0, count($areaFilters), '?'));
        $filterSql .= " AND complaint_rows.area_number IN ({$placeholders})";
        $filterTypes .= str_repeat('s', count($areaFilters));
        array_push($filterParams, ...$areaFilters);
    }

    if (!empty($sectorFilters)) {
        $sectorParts = [];
        foreach ($sectorFilters as $sectorFilter) {
            $sectorParts[] = "LOWER(complaint_rows.sector_membership) LIKE ?";
            $filterTypes .= 's';
            $filterParams[] = '%' . strtolower($sectorFilter) . '%';
        }
        $filterSql .= " AND (" . implode(' OR ', $sectorParts) . ")";
    }

    if ($searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $searchColumns = [
            'complaint_rows.complaint_id',
            'complaint_rows.case_id',
            'complaint_rows.complainant_name',
            'complaint_rows.subject_display_name',
            'complaint_rows.complaint_type',
            'complaint_rows.status_name',
            'complaint_rows.area_number',
            'complaint_rows.sector_membership',
        ];
        $searchParts = [];
        foreach ($searchColumns as $searchColumn) {
            $searchParts[] = "{$searchColumn} LIKE ?";
            $filterTypes .= 's';
            $filterParams[] = $like;
        }
        $filterSql .= " AND (" . implode(' OR ', $searchParts) . ")";
    }

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM ({$baseSql}) complaint_rows
        WHERE 1=1 {$filterSql}
    ");
    if (!$countStmt) {
        respond(false, [], 'Failed to prepare complaint count query.');
    }
    bindDynamicParams($countStmt, $filterTypes, $filterParams);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $totalItems = (int)($countRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $pendingCount = 0;
    $pendingCountStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM ({$baseSql}) complaint_rows
        WHERE complaint_rows.status_key = 'pending'
    ");
    if ($pendingCountStmt) {
        $pendingCountStmt->execute();
        $pendingCountRow = $pendingCountStmt->get_result()->fetch_assoc();
        $pendingCount = (int)($pendingCountRow['total'] ?? 0);
        $pendingCountStmt->close();
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM ({$baseSql}) complaint_rows
        WHERE 1=1 {$filterSql}
        ORDER BY complaint_rows.submitted_at_raw DESC, complaint_rows.case_id DESC
        LIMIT ? OFFSET ?
    ");
    if (!$stmt) {
        respond(false, [], 'Failed to prepare complaint list query.');
    }

    $listTypes = $filterTypes . 'ii';
    $listParams = $filterParams;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    bindDynamicParams($stmt, $listTypes, $listParams);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $statusName = trim((string)($row['status_name'] ?? 'Pending'));
        $requestStatusName = trim((string)($row['request_status_name'] ?? ''));

        $items[] = [
            'case_id' => (string)$row['case_id'],
            'complaint_id' => $row['complaint_id'] ?? '',
            'submitted_at_raw' => (string)($row['submitted_at_raw'] ?? ''),
            'submitted_at' => formatDisplayTimestamp($row['submitted_at_raw'] ?? ''),
            'complaint_type' => $row['complaint_type'] ?? '',
            'area_number' => $row['area_number'] ?? '',
            'sector_membership' => $row['sector_membership'] ?? '',
            'status_name' => $statusName !== '' ? $statusName : 'Pending',
            'status_key' => $row['status_key'] ?? 'pending',
            'level_name' => $row['level_name'] ?? 'Complaint Only',
            'subject_display_name' => $row['subject_display_name'] ?? '',
            'subject_kind' => $row['subject_kind'] ?? '',
            'escalated_to_blotter' => (int)($row['escalated_to_blotter'] ?? 0),
            'blotter_id' => $row['blotter_id'] ?? null,
            'blotter_request_id' => $row['request_id'] ?? null,
            'blotter_request_status' => $requestStatusName !== '' ? $requestStatusName : null,
            'complainant_name' => $row['complainant_name'] !== '' ? $row['complainant_name'] : participantDisplayName($row),
        ];
    }
    $stmt->close();

    $complaintTypes = [];
    $typeStmt = $conn->prepare("
        SELECT DISTINCT complaint_type
        FROM casereportstbl
        WHERE report_type = 'Complaint'
          AND TRIM(COALESCE(complaint_type, '')) <> ''
        ORDER BY complaint_type ASC
    ");
    if ($typeStmt) {
        $typeStmt->execute();
        $typeRes = $typeStmt->get_result();
        while ($typeRow = $typeRes->fetch_assoc()) {
            $complaintTypes[] = (string)$typeRow['complaint_type'];
        }
        $typeStmt->close();
    }

    $responsePayload = [
        'items' => $items,
        'meta' => [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'badges' => [
                'pending_count' => $pendingCount,
            ],
            'filters' => [
                'complaint_types' => $complaintTypes,
            ],
        ],
    ];

    complaintTrackerCachePut($listCacheKey, [
        'success' => true,
        'message' => '',
        'items' => $responsePayload['items'],
        'detail' => null,
        'meta' => $responsePayload['meta'],
    ]);

    respond(true, $responsePayload);
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
            c.report_timestamp AS submitted_at_raw,
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

    $detail['submitted_at'] = formatDisplayTimestamp($detail['submitted_at_raw'] ?? '');
    $parsedCaseDetails = complaintTypeParseCaseDetails($detail['case_details'] ?? '');
    $classificationOptions = complaintClassificationOptions();
    $currentClassification = trim((string)($detail['complaint_type'] ?? ''));

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
        'Witness' => [],
    ];
    while ($row = $res->fetch_assoc()) {
        $role = (string)($row['participant_role'] ?? '');
        $participantPayload = [
            'full_name' => participantDisplayName($row),
            'contact_number' => $row['contact_number'] ?? '',
            'address' => $row['address'] ?? '',
            'age' => $row['age'] ?? '',
            'sex' => $row['sex'] ?? '',
            'remarks' => $row['remarks'] ?? '',
        ];
        if ($role === 'Witness') {
            $participants['Witness'][] = $participantPayload;
            continue;
        }
        $participants[$role] = $participantPayload;
    }
    $stmt->close();

    $requestDetail = getLatestBlotterRequest($conn, $caseId);

    respond(true, [
        'detail' => [
            'case_id' => (string)$detail['case_id'],
            'complaint_id' => $detail['complaint_id'] ?? '',
            'submitted_at' => $detail['submitted_at'] ?? '',
            'incident_date' => $detail['incident_date'] ?? '',
            'incident_time' => $detail['incident_time'] ?? '',
            'incident_place' => $detail['incident_place'] ?? '',
            'incident_area_number' => $parsedCaseDetails['incident_area_number'] ?? '',
            'complaint_type' => $detail['complaint_type'] ?? '',
            'classification_options' => $classificationOptions,
            'complaint_type_is_standard' => isValidComplaintClassification($currentClassification),
            'case_details' => $detail['case_details'] ?? '',
            'complaint_narration' => $parsedCaseDetails['narration'] ?? '',
            'complaint_detail_fields' => $parsedCaseDetails['fields'] ?? [],
            'attachments' => $parsedCaseDetails['attachments'] ?? [],
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
            'blotter_request_id' => $requestDetail['request_id'] ?? null,
            'blotter_request_status' => $requestDetail['request_status_name'] ?? null,
            'blotter_request_notes' => $requestDetail['review_notes'] ?? null,
            'blotter_request_requested_at' => isset($requestDetail['requested_at']) ? formatDisplayTimestamp($requestDetail['requested_at']) : null,
            'blotter_request_reviewed_at' => isset($requestDetail['reviewed_at']) ? formatDisplayTimestamp($requestDetail['reviewed_at']) : null,
            'complaint_origin' => $detail['complaint_origin'] ?? '',
            'complainant' => $participants['Complainant'] ?? null,
            'respondent' => $participants['Respondent'] ?? null,
            'witnesses' => $participants['Witness'] ?? [],
        ]
    ]);
}

if ($action === 'update_case_classification') {
    if ($requestMethod !== 'POST') {
        respond(false, [], 'Method not allowed.');
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? ''));
    $classification = trim((string)($jsonInput['complaint_type'] ?? ''));
    if ($caseId === '') {
        respond(false, [], 'Invalid case ID.');
    }
    if (!isValidComplaintClassification($classification)) {
        respond(false, [], 'Please select a valid complaint classification.');
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($actorUserId === '') {
        respond(false, [], 'User session not found.');
    }

    $existsStmt = $conn->prepare("
        SELECT complaint_type, case_details
        FROM casereportstbl
        WHERE case_id = ? AND report_type = 'Complaint'
        LIMIT 1
    ");
    if (!$existsStmt) {
        respond(false, [], 'Failed to validate complaint case.');
    }
    $existsStmt->bind_param("s", $caseId);
    $existsStmt->execute();
    $existingCase = $existsStmt->get_result()->fetch_assoc();
    $existsStmt->close();
    if (!$existingCase) {
        respond(false, [], 'Complaint case not found.');
    }

    $updatedCaseDetails = syncComplaintCaseDetailsClassification($existingCase['case_details'] ?? '', $classification);

    $conn->begin_transaction();
    try {
        $updateCaseStmt = $conn->prepare("
            UPDATE casereportstbl
            SET complaint_type = ?,
                case_details = ?,
                user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Complaint'
            LIMIT 1
        ");
        if (!$updateCaseStmt) {
            throw new Exception('Failed to prepare complaint classification update.');
        }
        $updateCaseStmt->bind_param("ssss", $classification, $updatedCaseDetails, $actorUserId, $caseId);
        $updateCaseStmt->execute();
        $updateCaseStmt->close();

        if (tableExists($conn, 'caseupdateslogtbl')) {
            $logStmt = $conn->prepare("
                INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
                VALUES (?, ?, ?)
            ");
            if (!$logStmt) {
                throw new Exception('Failed to prepare complaint classification log.');
            }
            $logEntry = 'Complaint classification updated to: ' . $classification;
            $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();
        complaintTrackerCacheClear();
        respond(true, [], 'Complaint classification updated.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, [], 'Failed to update complaint classification.');
    }
}

if ($action === 'add_witness') {
    if ($requestMethod !== 'POST') {
        respond(false, [], 'Method not allowed.');
    }

    $caseId = trim((string)($jsonInput['case_id'] ?? ''));
    $fullName = trim((string)($jsonInput['full_name'] ?? ''));
    $contactNumber = trim((string)($jsonInput['contact_number'] ?? ''));
    $address = trim((string)($jsonInput['address'] ?? ''));
    $remarks = trim((string)($jsonInput['remarks'] ?? ''));

    if ($caseId === '') {
        respond(false, [], 'Invalid case ID.');
    }
    if ($fullName === '') {
        respond(false, [], 'Witness full name is required.');
    }
    if ($contactNumber === '') {
        respond(false, [], 'Witness contact number is required.');
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
        $insertStmt = $conn->prepare("
            INSERT INTO caseparticipantstbl
                (case_id, participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks)
            VALUES
                (?, 'Witness', '', ?, '', '', ?, '', ?, '', '', ?)
        ");
        if (!$insertStmt) {
            throw new Exception('Failed to prepare witness insert.');
        }
        $insertStmt->bind_param("sssss", $caseId, $fullName, $contactNumber, $address, $remarks);
        $insertStmt->execute();
        $insertStmt->close();

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
                throw new Exception('Failed to prepare witness log.');
            }
            $logEntry = 'Witness added to complaint: ' . $fullName;
            $logStmt->bind_param("sss", $caseId, $logEntry, $actorUserId);
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();
        complaintTrackerCacheClear();
        respond(true, [], 'Witness added to complaint.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, [], 'Failed to add witness.');
    }
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
        complaintTrackerCacheClear();
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
    if (!in_array($actionType, ['under_investigation', 'action_in_progress', 'resolved', 'endorsement', 'closed'], true)) {
        respond(false, [], 'Invalid action type.');
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($actorUserId === '') {
        respond(false, [], 'User session not found.');
    }

    $oldStmt = $conn->prepare("
        SELECT
            c.case_id,
            c.resident_user_id,
            c.incident_date,
            c.incident_time,
            c.incident_place,
            c.complaint_type,
            c.case_details,
            s.status_name AS old_status_name,
            l.status_name AS old_level_name,
            c.case_remarks,
            ct.complaint_id,
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
        error_log('Failed to prepare current complaint state query: ' . $conn->error);
        respond(false, [], 'Failed to load current complaint state: ' . $conn->error);
    }
    $oldStmt->bind_param("s", $caseId);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if (!$oldRow) {
        respond(false, [], 'Complaint case not found.');
    }
    if (!isValidComplaintClassification((string)($oldRow['complaint_type'] ?? ''))) {
        respond(false, [], 'Complaint classification must be set by admin before updating the complaint status.');
    }

    $oldStatusName = trim((string)($oldRow['old_status_name'] ?? 'Pending'));
    $oldLevelName = trim((string)($oldRow['old_level_name'] ?? 'Complaint Only'));
    $existingBlotterId = trim((string)($oldRow['blotter_id'] ?? ''));
    $existingRequest = getLatestBlotterRequest($conn, $caseId);
    $existingRequestStatus = strtolower(trim((string)($existingRequest['request_status_name'] ?? '')));
    if (in_array(strtolower($oldStatusName), ['resolved', 'closed', 'dropped'], true)) {
        respond(false, [], 'Complaint status is already finalized and cannot be changed again.');
    }
    if ($actionType === 'endorsement' && $existingBlotterId !== '') {
        respond(false, [], 'Complaint is already linked to a blotter.');
    }
    if ($actionType === 'endorsement' && in_array($existingRequestStatus, ['pending', 'approved'], true)) {
        respond(false, [], 'A blotter review request already exists for this complaint.');
    }
    if (strtolower($oldStatusName) === 'endorsed' && $existingBlotterId !== '') {
        respond(false, [], 'Complaint status is already finalized and cannot be changed again.');
    }

    $newStatusName = '';
    $newLevelName = 'Complaint Only';
    $markEscalated = 0;
    ensureComplaintWorkflowLookups($conn);

    if ($actionType === 'under_investigation') {
        $newStatusName = 'Under Investigation';
    } elseif ($actionType === 'action_in_progress') {
        $newStatusName = 'Action in Progress';
    } elseif ($actionType === 'resolved') {
        $newStatusName = 'Resolved';
    } elseif ($actionType === 'closed') {
        $newStatusName = 'Closed';
    } else {
        $newLevelName = 'Endorsed to Blotter';
        $newStatusName = $oldStatusName !== '' ? $oldStatusName : 'Pending';
    }

    $newStatusId = getStatusId($conn, $newStatusName, 'Complaint');
    $newLevelId = getStatusId($conn, $newLevelName, 'ComplaintLevel');
    if ($newStatusId <= 0 || $newLevelId <= 0) {
        respond(false, [], 'Status or complaint level mapping not found in statuslookuptbl.');
    }

    $updatedScreeningNotes = appendMultilineNote($oldRow['screening_notes'] ?? '', $remarks);
    $caseRemarksNote = "Screening notes ({$newStatusName}): {$remarks}";
    $updatedCaseRemarks = appendMultilineNote($oldRow['case_remarks'] ?? '', $caseRemarksNote);
    $createdRequest = null;
    $logEntry = "Complaint status updated: {$oldStatusName} -> {$newStatusName}; Complaint level: {$oldLevelName} -> {$newLevelName}; Remarks: {$remarks}";

    $conn->begin_transaction();
    try {
        if ($actionType === 'endorsement') {
            $createdRequest = createBlotterRequest($conn, $oldRow, $actorUserId, $remarks);
            $logEntry .= '; Blotter review request created: ' . ($createdRequest['request_id'] ?? '');
        }

        $updateStmt = $conn->prepare("
            UPDATE casereportstbl
            SET case_status_id = ?, case_level_id = ?, case_remarks = ?, user_id_official_update_by = ?
            WHERE case_id = ? AND report_type = 'Complaint'
            LIMIT 1
        ");
        if (!$updateStmt) {
            throw new Exception('Failed to prepare complaint update.');
        }
        $updateStmt->bind_param("iisss", $newStatusId, $newLevelId, $updatedCaseRemarks, $actorUserId, $caseId);
        $updateStmt->execute();
        $updateStmt->close();

        $complaintUpdateStmt = $conn->prepare("
            UPDATE complaintstbl
            SET escalated_to_blotter = ?,
                escalated_to_blotter_at = CASE WHEN ? = 1 THEN NOW() ELSE escalated_to_blotter_at END,
                escalated_by_user_id = CASE WHEN ? = 1 THEN ? ELSE escalated_by_user_id END,
                screening_notes = ?,
                blotter_id = CASE WHEN ? = 1 AND ? <> '' THEN ? ELSE blotter_id END
            WHERE case_id = ?
            LIMIT 1
        ");
        if (!$complaintUpdateStmt) {
            throw new Exception('Failed to prepare complaint metadata update.');
        }
        $newBlotterId = '';
        $complaintUpdateStmt->bind_param("iiississs", $markEscalated, $markEscalated, $markEscalated, $actorUserId, $updatedScreeningNotes, $markEscalated, $newBlotterId, $newBlotterId, $caseId);
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
        complaintTrackerCacheClear();
        respond(true, [], 'Complaint updated.');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Failed to update complaint endorsement: ' . $e->getMessage());
        respond(false, [], 'Failed to update complaint: ' . $e->getMessage());
    }
}

respond(false, [], 'Unsupported action.');
