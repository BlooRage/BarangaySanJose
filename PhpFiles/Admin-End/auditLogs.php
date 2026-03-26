<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fetch_audit_logs'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

function auditLogsFormatName($fn, $mn, $ln, $suf): string
{
    $fn = trim((string)$fn);
    $mn = trim((string)$mn);
    $ln = trim((string)$ln);
    $suf = trim((string)$suf);
    if ($fn === '' && $ln === '') {
        return '';
    }

    $mid = $mn !== '' ? (substr($mn, 0, 1) . '. ') : '';
    $name = trim($fn . ' ' . $mid . $ln);
    if ($suf !== '') {
        $name .= ' ' . $suf;
    }
    return trim($name);
}

function auditLogsDecryptRow(array $row): array
{
    $row = pii_decrypt_assoc($row, [
        'o_firstname',
        'o_middlename',
        'o_lastname',
        'o_suffix',
        'r_firstname',
        'r_middlename',
        'r_lastname',
        'r_suffix',
        'old_value',
        'new_value',
        'remarks',
    ]);

    $officialName = auditLogsFormatName(
        $row['o_firstname'] ?? '',
        $row['o_middlename'] ?? '',
        $row['o_lastname'] ?? '',
        $row['o_suffix'] ?? ''
    );
    $residentName = auditLogsFormatName(
        $row['r_firstname'] ?? '',
        $row['r_middlename'] ?? '',
        $row['r_lastname'] ?? '',
        $row['r_suffix'] ?? ''
    );

    $row['display_name'] = $officialName !== '' ? $officialName : ($residentName !== '' ? $residentName : '');
    return $row;
}

function auditLogsMatchesSearch(array $row, string $needle): bool
{
    return pii_search_match($row, [
        'audit_id',
        'user_id',
        'display_name',
        'role_access',
        'module_affected',
        'target_type',
        'target_id',
        'action_type',
        'field_changed',
        'old_value',
        'new_value',
        'remarks',
        'action_timestamp',
    ], $needle);
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 200);
    if ($limit <= 0) $limit = 200;
    if ($limit > 500) $limit = 500;
    $queryLimit = $q !== '' ? 500 : $limit;

    $bindParams = static function (mysqli_stmt $stmt, string $types, array $params): void {
        if ($types === '') return;
        $refs = [];
        $refs[] = $types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    };

    $sql = "
        SELECT
            a.audit_id,
            a.user_id,
            a.role_access,
            a.module_affected,
            a.target_type,
            a.target_id,
            a.action_type,
            a.field_changed,
            a.old_value,
            a.new_value,
            a.remarks,
            a.action_timestamp,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix
        FROM unifiedauditlogstbl a
        LEFT JOIN officialinformationtbl oi
            ON oi.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN residentinformationtbl ri
            ON ri.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
    ";

    $sql .= " ORDER BY a.action_timestamp DESC, a.audit_id DESC LIMIT ?";
    $params = [$queryLimit];
    $types = 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // bind dynamically
    $bindParams($stmt, $types, $params);

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row = auditLogsDecryptRow($row);
        if ($q !== '' && !auditLogsMatchesSearch($row, $q)) {
            continue;
        }

        unset(
            $row['o_firstname'], $row['o_middlename'], $row['o_lastname'], $row['o_suffix'],
            $row['r_firstname'], $row['r_middlename'], $row['r_lastname'], $row['r_suffix']
        );
        $rows[] = $row;
    }
    $stmt->close();

    if ($q !== '' && count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
