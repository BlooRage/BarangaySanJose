<?php
declare(strict_types=1);

require_once __DIR__ . '/uniqueIDGenerate.php';

/**
 * Best-effort audit logging.
 * If the table doesn't exist or schema differs, this function must not break the main workflow.
 */

function auditClamp(?string $value, int $maxLen = 2000): ?string {
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s, 'UTF-8') > $maxLen) {
            return mb_substr($s, 0, $maxLen, 'UTF-8');
        }
        return $s;
    }
    return strlen($s) > $maxLen ? substr($s, 0, $maxLen) : $s;
}

function auditEnsureTable(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("
        CREATE TABLE IF NOT EXISTS unifiedauditlogstbl (
            audit_id VARCHAR(16) NOT NULL,
            user_id VARCHAR(12),
            role_access VARCHAR(64) NOT NULL,
            module_affected VARCHAR(128) NOT NULL,
            target_type VARCHAR(64) NOT NULL,
            target_id VARCHAR(64) NOT NULL,
            action_type VARCHAR(64) NOT NULL,
            field_changed VARCHAR(128),
            old_value TEXT,
            new_value TEXT,
            remarks TEXT,
            action_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status_id_audit INT,
            PRIMARY KEY (audit_id),
            KEY idx_audit_ts (action_timestamp),
            KEY idx_audit_user (user_id),
            KEY idx_audit_module (module_affected),
            KEY idx_audit_target (target_type, target_id),
            KEY idx_audit_action (action_type),
            KEY idx_audit_status (status_id_audit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    idg_ensure_string_generated_key($conn, 'unifiedauditlogstbl', 'audit_id', 16);
}

function insertUnifiedAuditLog(
    mysqli $conn,
    ?string $userId,
    string $roleAccess,
    string $moduleAffected,
    string $targetType,
    string $targetId,
    string $actionType,
    ?string $fieldChanged = null,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $remarks = null,
    ?int $statusIdAudit = null
): void {
    auditEnsureTable($conn);

    $auditId = GenerateUnifiedAuditLogID($conn, $userId, $roleAccess);
    if ($auditId === false || $auditId === '') {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO unifiedauditlogstbl
            (audit_id, user_id, role_access, module_affected, target_type, target_id, action_type, field_changed, old_value, new_value, remarks, status_id_audit)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }

    $userId = auditClamp($userId, 12);
    $roleAccess = (string)auditClamp($roleAccess, 64);
    $moduleAffected = (string)auditClamp($moduleAffected, 128);
    $targetType = (string)auditClamp($targetType, 64);
    $targetId = (string)auditClamp($targetId, 64);
    $actionType = (string)auditClamp($actionType, 64);

    $fieldChanged = auditClamp($fieldChanged, 128);
    $oldValue = auditClamp($oldValue, 2000);
    $newValue = auditClamp($newValue, 2000);
    $remarks = auditClamp($remarks, 2000);

    $statusIdAudit = $statusIdAudit !== null ? (int)$statusIdAudit : null;

    // "i" cannot bind null reliably in some configs; bind as string and let MySQL coerce where needed.
    $statusIdStr = $statusIdAudit !== null ? (string)$statusIdAudit : null;

    $stmt->bind_param(
        "ssssssssssss",
        $auditId,
        $userId,
        $roleAccess,
        $moduleAffected,
        $targetType,
        $targetId,
        $actionType,
        $fieldChanged,
        $oldValue,
        $newValue,
        $remarks,
        $statusIdStr
    );

    $stmt->execute();
    $stmt->close();
}
