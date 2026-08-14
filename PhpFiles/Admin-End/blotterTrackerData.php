<?php
require_once "../General/security.php";
require_once "../General/adminModulePermissions.php";

header('Content-Type: application/json; charset=utf-8');

$allowedRoles = ['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'];
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestAction = trim((string)($_GET['action'] ?? 'list'));
$authChecked = false;
$permissionChecked = false;

function blotterTrackerCachePath(string $key): string {
    $safeKey = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $key) ?? 'cache';
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_blotter_tracker_' . $safeKey . '.cache';
}

function blotterTrackerCacheGet(string $key, int $ttlSeconds): ?array {
    if ($key === '' || $ttlSeconds <= 0) {
        return null;
    }

    $path = blotterTrackerCachePath($key);
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

function blotterTrackerCachePut(string $key, array $value): void {
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

    @file_put_contents(blotterTrackerCachePath($key), $payload, LOCK_EX);
}

function blotterTrackerCacheClear(): void {
    $pattern = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_blotter_tracker_*.cache';
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

function blotterTrackerListCacheKey(array $query, string $userId, string $role): string {
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

    if (amp_normalize_storage_role((string)($_SESSION['role'] ?? '')) !== 'SuperAdmin') {
        require_once "../General/connection.php";
    }
    $permissionConnection = isset($conn) && $conn instanceof mysqli ? $conn : null;
    amp_require_json_module_permission($permissionConnection, 'blotter_tracker', [
        'success' => false,
        'message' => 'You do not have permission to access the blotter tracker.',
        'items' => null,
        'detail' => null,
        'meta' => null,
    ]);
    $permissionChecked = true;

    $listCacheKey = blotterTrackerListCacheKey(
        $_GET,
        trim((string)($_SESSION['user_id'] ?? '')),
        (string)($_SESSION['role'] ?? '')
    );
    $cachedResponse = blotterTrackerCacheGet($listCacheKey, 60);
    if (is_array($cachedResponse)) {
        echo json_encode($cachedResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

require_once "../General/connection.php";
require_once "../General/caseUserAccountForeignKeys.php";

if (!$authChecked) {
    requireRoleSession($allowedRoles);
}
if (!$permissionChecked) {
    amp_require_json_module_permission($conn, 'blotter_tracker', [
        'success' => false,
        'message' => 'You do not have permission to access the blotter tracker.',
        'items' => null,
        'detail' => null,
        'meta' => null,
    ]);
}
cuafk_ensure_case_useraccount_foreign_keys($conn);

function respond($success, $payload = [], $message = '') {
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'items' => $payload['items'] ?? null,
        'detail' => $payload['detail'] ?? null,
        'meta' => $payload['meta'] ?? null,
    ]);
    exit;
}

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
    static $cache = [];

    $tableName = trim($tableName);
    if ($tableName === '') return false;

    $cacheKey = strtolower($tableName);
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    $stmt = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$cacheKey] = !empty($row);
}

function columnExists(mysqli $conn, string $tableName, string $columnName): bool {
    static $cache = [];

    $tableName = trim($tableName);
    $columnName = trim($columnName);
    if ($tableName === '' || $columnName === '') return false;

    $cacheKey = strtolower($tableName . '|' . $columnName);
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$cacheKey] = !empty($row);
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

function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $parts = explode('/', $normalized);
    $cleanParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($cleanParts);
            continue;
        }
        $cleanParts[] = $part;
    }
    $normalized = '/' . implode('/', $cleanParts);

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        return appRootPath() . substr($normalized, $markerPos);
    }

    $webRoot = realpath(__DIR__ . "/../..");
    if ($webRoot) {
        $rootNorm = str_replace("\\", "/", $webRoot);
        if (strpos($normalized, $rootNorm) === 0) {
            $rel = substr($normalized, strlen($rootNorm));
            if ($rel === '') {
                return null;
            }
            if ($rel[0] !== '/') {
                $rel = '/' . $rel;
            }
            return appRootPath() . $rel;
        }
    }

    return appRootPath() . '/' . ltrim($normalized, '/');
}

function inferMimeTypeFromPath(?string $path): string {
    $path = strtolower(trim((string)$path));
    if ($path === '') {
        return '';
    }

    $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
    return match ($extension) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        default => '',
    };
}

function formatFiledDateTime($dateValue, $timeValue): string {
    $dateValue = trim((string)$dateValue);
    $timeValue = trim((string)$timeValue);

    if ($dateValue === '' && $timeValue === '') {
        return '';
    }

    $combined = trim($dateValue . ' ' . $timeValue);
    if ($combined !== '') {
        $timestamp = strtotime($combined);
        if ($timestamp !== false) {
            return date('M j, Y g:i A', $timestamp);
        }
    }

    if ($dateValue !== '') {
        $dateOnly = strtotime($dateValue);
        if ($dateOnly !== false) {
            return $timeValue !== ''
                ? date('M j, Y', $dateOnly) . ' ' . $timeValue
                : date('M j, Y', $dateOnly);
        }
    }

    return $combined;
}

function parseCsvValues($value): array {
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

function bindDynamicParams(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '' || empty($params)) {
        return;
    }

    $stmt->bind_param($types, ...$params);
}

if ($action === 'list') {
    $listCacheKey = blotterTrackerListCacheKey(
        $_GET,
        trim((string)($_SESSION['user_id'] ?? '')),
        (string)($_SESSION['role'] ?? '')
    );
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $searchTerm = trim((string)($_GET['search'] ?? ''));
    $statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
    if (!in_array($statusFilter, ['', 'active', 'endorsed', 'dropped'], true)) {
        $statusFilter = '';
    }
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $complaintTypeFilters = parseCsvValues($_GET['complaint_type'] ?? '');
    $areaFilters = parseCsvValues($_GET['area_number'] ?? '');
    $sectorFilters = parseCsvValues($_GET['sector_membership'] ?? '');

    $residentSelect = ",
            COALESCE(c.complaint_type, '') AS complaint_type,
            '' AS sector_membership,
            '' AS area_number";
    $residentJoin = '';
    if (
        tableExists($conn, 'residentinformationtbl')
        && tableExists($conn, 'residentaddresstbl')
        && columnExists($conn, 'casereportstbl', 'resident_user_id')
    ) {
        $residentSelect = ",
            COALESCE(c.complaint_type, '') AS complaint_type,
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
            b.blotter_id,
            b.blotter_number,
            b.date_filed,
            b.time_filed,
            COALESCE(s.status_name, '') AS status_name,
            COALESCE(l.status_name, '') AS level_name,
            TRIM(CONCAT_WS(' ', cp.firstname, cp.middlename, cp.lastname, cp.suffix)) AS complainant_name,
            TRIM(CONCAT_WS(' ', rp.firstname, rp.middlename, rp.lastname, rp.suffix)) AS respondent_name,
            cp.firstname AS complainant_firstname,
            cp.middlename AS complainant_middlename,
            cp.lastname AS complainant_lastname,
            cp.suffix AS complainant_suffix,
            rp.firstname AS respondent_firstname,
            rp.middlename AS respondent_middlename,
            rp.lastname AS respondent_lastname,
            rp.suffix AS respondent_suffix
            {$residentSelect}
        FROM casereportstbl c
        INNER JOIN barangayblottertbl b ON b.case_id = c.case_id
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
        ) cp ON cp.case_id = c.case_id
        LEFT JOIN (
            SELECT
                case_id,
                MAX(firstname) AS firstname,
                MAX(middlename) AS middlename,
                MAX(lastname) AS lastname,
                MAX(suffix) AS suffix
            FROM caseparticipantstbl
            WHERE participant_role = 'Respondent'
            GROUP BY case_id
        ) rp ON rp.case_id = c.case_id
        {$residentJoin}
        WHERE c.report_type = 'Blotter'
    ";

    $filterSql = '';
    $filterTypes = '';
    $filterParams = [];

    if ($statusFilter !== '') {
        $filterSql .= " AND LOWER(blotter_rows.status_name) = ?";
        $filterTypes .= 's';
        $filterParams[] = $statusFilter;
    }

    if ($dateFrom !== '') {
        $filterSql .= " AND blotter_rows.date_filed >= ?";
        $filterTypes .= 's';
        $filterParams[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $filterSql .= " AND blotter_rows.date_filed <= ?";
        $filterTypes .= 's';
        $filterParams[] = $dateTo;
    }

    if (!empty($complaintTypeFilters)) {
        $placeholders = implode(', ', array_fill(0, count($complaintTypeFilters), '?'));
        $filterSql .= " AND blotter_rows.complaint_type IN ({$placeholders})";
        $filterTypes .= str_repeat('s', count($complaintTypeFilters));
        array_push($filterParams, ...$complaintTypeFilters);
    }

    if (!empty($areaFilters)) {
        $placeholders = implode(', ', array_fill(0, count($areaFilters), '?'));
        $filterSql .= " AND blotter_rows.area_number IN ({$placeholders})";
        $filterTypes .= str_repeat('s', count($areaFilters));
        array_push($filterParams, ...$areaFilters);
    }

    if (!empty($sectorFilters)) {
        $sectorParts = [];
        foreach ($sectorFilters as $sectorFilter) {
            $sectorParts[] = "LOWER(blotter_rows.sector_membership) LIKE ?";
            $filterTypes .= 's';
            $filterParams[] = '%' . strtolower($sectorFilter) . '%';
        }
        $filterSql .= " AND (" . implode(' OR ', $sectorParts) . ")";
    }

    if ($searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $searchColumns = [
            'blotter_rows.blotter_id',
            'blotter_rows.blotter_number',
            'blotter_rows.case_id',
            'blotter_rows.complainant_name',
            'blotter_rows.respondent_name',
            'blotter_rows.complaint_type',
            'blotter_rows.area_number',
            'blotter_rows.sector_membership',
            'blotter_rows.status_name',
            'blotter_rows.level_name',
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
        FROM ({$baseSql}) blotter_rows
        WHERE 1=1 {$filterSql}
    ");
    if (!$countStmt) {
        respond(false, [], "Failed to prepare blotter count query.");
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

    $activeCount = 0;
    $activeCountStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM ({$baseSql}) blotter_rows
        WHERE LOWER(blotter_rows.status_name) = 'active'
    ");
    if ($activeCountStmt) {
        $activeCountStmt->execute();
        $activeCountRow = $activeCountStmt->get_result()->fetch_assoc();
        $activeCount = (int)($activeCountRow['total'] ?? 0);
        $activeCountStmt->close();
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM ({$baseSql}) blotter_rows
        WHERE 1=1 {$filterSql}
        ORDER BY blotter_rows.date_filed DESC, blotter_rows.time_filed DESC, blotter_rows.blotter_id DESC
        LIMIT ? OFFSET ?
    ");
    if (!$stmt) {
        respond(false, [], "Failed to prepare list query.");
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
        $items[] = [
            'case_id' => $row['case_id'],
            'blotter_id' => $row['blotter_id'],
            'blotter_number' => $row['blotter_number'],
            'date_filed_raw' => $row['date_filed'],
            'date_filed' => formatFiledDateTime($row['date_filed'], $row['time_filed']),
            'complaint_type' => $row['complaint_type'] ?? '',
            'area_number' => $row['area_number'] ?? '',
            'sector_membership' => $row['sector_membership'] ?? '',
            'status_name' => $row['status_name'],
            'level_name' => $row['level_name'],
            'complainant_name' => $row['complainant_name'] ?? '',
            'respondent_name' => $row['respondent_name'] ?? '',
            'complainant_firstname' => $row['complainant_firstname'] ?? '',
            'complainant_middlename' => $row['complainant_middlename'] ?? '',
            'complainant_lastname' => $row['complainant_lastname'] ?? '',
            'complainant_suffix' => $row['complainant_suffix'] ?? '',
            'respondent_firstname' => $row['respondent_firstname'] ?? '',
            'respondent_middlename' => $row['respondent_middlename'] ?? '',
            'respondent_lastname' => $row['respondent_lastname'] ?? '',
            'respondent_suffix' => $row['respondent_suffix'] ?? '',
        ];
    }
    $stmt->close();

    $complaintTypes = [];
    $typeStmt = $conn->prepare("
        SELECT DISTINCT complaint_type
        FROM casereportstbl
        WHERE report_type = 'Blotter'
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
                'active_count' => $activeCount,
            ],
            'filters' => [
                'complaint_types' => $complaintTypes,
            ],
        ],
    ];

    blotterTrackerCachePut($listCacheKey, [
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
            c.incident_area_number,
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
        'date_filed' => formatFiledDateTime($detail['date_filed'], $detail['time_filed']),
        'report_timestamp' => $detail['report_timestamp'],
        'status_name' => $detail['status_name'],
        'level_name' => $detail['level_name'],
        'incident_date' => $detail['incident_date'],
        'incident_time' => $detail['incident_time'],
        'incident_place' => $detail['incident_place'],
        'incident_area_number' => $detail['incident_area_number'] ?? '',
        'complaint_type' => $detail['complaint_type'],
        'narrative_type' => $narrativeType,
        'narrative_value' => $narrativeValue,
        'narrative_url' => $narrativeType === 'file' ? toPublicPath($narrativeValue) : null,
        'narrative_mime_type' => $narrativeType === 'file' ? inferMimeTypeFromPath($narrativeValue) : '',
        'signatures' => array_map(static function (array $signature): array {
            $signature['file_url'] = toPublicPath($signature['file_path'] ?? '');
            return $signature;
        }, loadCaseSignatures($conn, $caseId)),
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
        blotterTrackerCacheClear();
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
        blotterTrackerCacheClear();
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
        blotterTrackerCacheClear();
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
