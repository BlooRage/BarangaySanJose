<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/householdMemberVerification.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);
    header('Content-Type: application/json; charset=utf-8');
}

function hmv_migration_has_column(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]+/', '', $table) ?? '';
    $safeColumn = $conn->real_escape_string($column);
    if ($safeTable === '') {
        return false;
    }
    $res = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    return $exists;
}

function hmv_migration_has_index(mysqli $conn, string $table, string $index): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]+/', '', $table) ?? '';
    $safeIndex = $conn->real_escape_string($index);
    if ($safeTable === '') {
        return false;
    }
    $res = $conn->query("SHOW INDEX FROM {$safeTable} WHERE Key_name = '{$safeIndex}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    return $exists;
}

try {
    $before = [
        'table_exists' => false,
        'columns' => [],
        'indexes' => [],
    ];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'householdmemberverificationtbl'");
    $before['table_exists'] = $tableCheck instanceof mysqli_result && $tableCheck->num_rows > 0;
    if ($tableCheck instanceof mysqli_result) {
        $tableCheck->free();
    }

    $trackedColumns = [
        'request_id',
        'fam_head_id',
        'submitted_by_user_id',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'birthdate',
        'status_id',
        'status',
        'attachment_id',
        'approved_household_member_id',
        'reviewed_by_user_id',
        'review_remarks',
        'submitted_at',
        'reviewed_at',
        'updated_at',
    ];
    foreach ($trackedColumns as $column) {
        $before['columns'][$column] = hmv_migration_has_column($conn, 'householdmemberverificationtbl', $column);
    }

    $trackedIndexes = [
        'idx_hmv_head_status',
        'idx_hmv_status_submitted',
        'idx_hmv_submitted_by',
    ];
    foreach ($trackedIndexes as $index) {
        $before['indexes'][$index] = hmv_migration_has_index($conn, 'householdmemberverificationtbl', $index);
    }

    hmv_ensure_request_table($conn);

    $trackedStatuses = [
        'PendingReview' => hmv_get_household_member_status_id($conn, 'PendingReview'),
        'Active' => hmv_get_household_member_status_id($conn, 'Active'),
        'Rejected' => hmv_get_household_member_status_id($conn, 'Rejected'),
        'Removed' => hmv_get_household_member_status_id($conn, 'Removed'),
    ];

    $after = [
        'table_exists' => false,
        'columns' => [],
        'indexes' => [],
        'request_status_mode' => hmv_request_uses_status_lookup($conn) ? 'status_id' : 'legacy_status',
        'status_lookup' => $trackedStatuses,
    ];
    $tableCheckAfter = $conn->query("SHOW TABLES LIKE 'householdmemberverificationtbl'");
    $after['table_exists'] = $tableCheckAfter instanceof mysqli_result && $tableCheckAfter->num_rows > 0;
    if ($tableCheckAfter instanceof mysqli_result) {
        $tableCheckAfter->free();
    }

    foreach ($trackedColumns as $column) {
        $after['columns'][$column] = hmv_migration_has_column($conn, 'householdmemberverificationtbl', $column);
    }
    foreach ($trackedIndexes as $index) {
        $after['indexes'][$index] = hmv_migration_has_index($conn, 'householdmemberverificationtbl', $index);
    }

    $response = [
        'success' => true,
        'message' => 'Household member verification table migration completed.',
        'before' => $before,
        'after' => $after,
    ];

    if ($isCli) {
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }
} catch (Throwable $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
    if (!$isCli) {
        http_response_code(500);
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    } else {
        fwrite(STDERR, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }
}
