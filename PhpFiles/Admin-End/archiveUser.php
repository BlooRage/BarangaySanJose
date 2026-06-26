<?php
session_start();

require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/userAccountLocks.php";

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

ual_ensure_archive_support($conn);

function archiveUserNormalizeRole(string $role): string
{
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official') return 'Official';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'admin') return 'Official';
    if ($k === 'employee') return 'Personnel';
    if ($k === 'superadmin') return 'SuperAdmin';
    if ($k === 'resident') return 'Resident';
    return trim($role) !== '' ? trim($role) : 'Unknown';
}

function archiveUserDisplayName(array $row): string
{
    $mkName = static function ($fn, $mn, $ln, $suf): string {
        $fn = trim((string)$fn);
        $mn = trim((string)$mn);
        $ln = trim((string)$ln);
        $suf = trim((string)$suf);
        if ($fn === '' && $ln === '') return '';
        $mi = $mn !== '' ? substr($mn, 0, 1) . '. ' : '';
        return trim($fn . ' ' . $mi . $ln . ($suf !== '' ? ' ' . $suf : ''));
    };

    $nameOfficial = $mkName($row['o_firstname'] ?? '', $row['o_middlename'] ?? '', $row['o_lastname'] ?? '', $row['o_suffix'] ?? '');
    $nameResident = $mkName($row['r_firstname'] ?? '', $row['r_middlename'] ?? '', $row['r_lastname'] ?? '', $row['r_suffix'] ?? '');
    return $nameOfficial !== '' ? $nameOfficial : ($nameResident !== '' ? $nameResident : '—');
}

function archiveUserDecryptRow(array $row): array
{
    $row = pii_decrypt_useraccount_row($row) ?? $row;
    return pii_decrypt_assoc($row, [
        'r_firstname',
        'r_middlename',
        'r_lastname',
        'r_suffix',
        'o_firstname',
        'o_middlename',
        'o_lastname',
        'o_suffix',
    ]);
}

try {
    $archiveSupport = ual_ensure_archive_support($conn);
    $statusIds = is_array($archiveSupport['status_ids'] ?? null)
        ? $archiveSupport['status_ids']
        : ual_load_status_ids($conn);
    $archivedStatusId = isset($archiveSupport['archived_status_id'])
        ? (int)$archiveSupport['archived_status_id']
        : (int)($statusIds['archived'] ?? 0);

    if ($archivedStatusId <= 0) {
        throw new Exception('Archived status is not configured yet.');
    }

    $stmt = $conn->prepare("
        SELECT
            ua.user_id,
            ua.email,
            ua.phone_number,
            ua.role_access,
            ua.archived_at,
            ua.archived_prev_status_id,
            COALESCE(sp.status_name, '') AS previous_status,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix
        FROM useraccountstbl ua
        LEFT JOIN statuslookuptbl sp ON sp.status_id = ua.archived_prev_status_id
        LEFT JOIN residentinformationtbl ri ON ri.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN officialinformationtbl oi ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        WHERE ua.status_id_account = ?
        ORDER BY COALESCE(ua.archived_at, ua.updated_at, ua.account_created) DESC, ua.user_id DESC
    ");
    if (!$stmt) {
        throw new Exception('Unable to load archived users.');
    }

    $stmt->bind_param('i', $archivedStatusId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row = archiveUserDecryptRow($row);
        $rows[] = [
            'user_id' => (string)($row['user_id'] ?? ''),
            'display_name' => archiveUserDisplayName($row),
            'role_access' => archiveUserNormalizeRole((string)($row['role_access'] ?? '')),
            'email' => (string)($row['email'] ?? ''),
            'phone_number' => (string)($row['phone_number'] ?? ''),
            'previous_status' => trim((string)($row['previous_status'] ?? '')) !== '' ? (string)$row['previous_status'] : 'Active',
            'archived_at' => (string)($row['archived_at'] ?? ''),
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
