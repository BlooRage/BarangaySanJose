<?php
function ensureIdSequenceTable(mysqli $conn): bool {
    $createSql = "
        CREATE TABLE IF NOT EXISTS idsequencetbl (
            seq_key VARCHAR(64) NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    if (!$conn->query($createSql)) {
        error_log("ensureIdSequenceTable create failed: " . $conn->error);
        return false;
    }

    // Upgrade older installs where seq_key may still be VARCHAR(16).
    $conn->query("ALTER TABLE idsequencetbl MODIFY seq_key VARCHAR(64) NOT NULL");
    return true;
}

function idg_is_valid_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
}

function idg_table_exists(mysqli $conn, string $table): bool {
    if (!idg_is_valid_identifier($table)) {
        return false;
    }

    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function idg_get_column_info(mysqli $conn, string $table, string $column): ?array {
    if (!idg_is_valid_identifier($table) || !idg_is_valid_identifier($column)) {
        return null;
    }

    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$columnEsc}'");
    if (!$res instanceof mysqli_result) {
        return null;
    }

    $row = $res->fetch_assoc();
    return is_array($row) ? $row : null;
}

function idg_ensure_numeric_generated_key(mysqli $conn, string $table, string $column, string $definition): void {
    static $cache = [];

    $cacheKey = strtolower($table . '|' . $column . '|' . $definition);
    if (isset($cache[$cacheKey])) {
        return;
    }
    $cache[$cacheKey] = true;

    if (!idg_table_exists($conn, $table)) {
        return;
    }

    $info = idg_get_column_info($conn, $table, $column);
    if (!$info) {
        return;
    }

    $type = strtolower(trim((string)($info['Type'] ?? '')));
    $null = strtoupper(trim((string)($info['Null'] ?? 'YES')));
    $extra = strtolower(trim((string)($info['Extra'] ?? '')));
    $expected = strtolower($definition);

    $needsTypeChange = false;
    if (strpos($expected, 'bigint') !== false) {
        $needsTypeChange = strpos($type, 'bigint') !== 0
            || (strpos($expected, 'unsigned') !== false && strpos($type, 'unsigned') === false);
    } elseif (strpos($expected, 'int') !== false) {
        $needsTypeChange = strpos($type, 'int') !== 0;
    }

    if ($needsTypeChange || $null !== 'NO' || strpos($extra, 'auto_increment') !== false) {
        $conn->query("ALTER TABLE {$table} MODIFY {$column} {$definition}");
    }
}

function idg_ensure_string_generated_key(mysqli $conn, string $table, string $column, int $length): void {
    static $cache = [];

    $cacheKey = strtolower($table . '|' . $column . '|varchar(' . $length . ')');
    if (isset($cache[$cacheKey])) {
        return;
    }
    $cache[$cacheKey] = true;

    if (!idg_table_exists($conn, $table)) {
        return;
    }

    $info = idg_get_column_info($conn, $table, $column);
    if (!$info) {
        return;
    }

    $type = strtolower(trim((string)($info['Type'] ?? '')));
    $null = strtoupper(trim((string)($info['Null'] ?? 'YES')));
    $extra = strtolower(trim((string)($info['Extra'] ?? '')));
    $expectedType = 'varchar(' . $length . ')';

    if ($type !== $expectedType || $null !== 'NO' || strpos($extra, 'auto_increment') !== false) {
        $conn->query("ALTER TABLE {$table} MODIFY {$column} VARCHAR({$length}) NOT NULL");
    }
}

function idg_column_matches_definition(mysqli $conn, string $table, string $column, string $definition): bool {
    $info = idg_get_column_info($conn, $table, $column);
    if (!$info) {
        return false;
    }

    $type = strtolower(trim((string)($info['Type'] ?? '')));
    $null = strtoupper(trim((string)($info['Null'] ?? 'YES')));
    $extra = strtolower(trim((string)($info['Extra'] ?? '')));
    $expected = strtolower($definition);

    $typeMatches = false;
    if (strpos($expected, 'bigint') !== false) {
        $typeMatches = strpos($type, 'bigint') === 0
            && (strpos($expected, 'unsigned') === false || strpos($type, 'unsigned') !== false);
    } elseif (strpos($expected, 'int') !== false) {
        $typeMatches = strpos($type, 'int') === 0;
    } else {
        $typeMatches = $type === $expected;
    }

    return $typeMatches && $null === 'NO' && strpos($extra, 'auto_increment') === false;
}

function idg_next_prefixed_sequence_id(
    mysqli $conn,
    string $tableName,
    string $columnName,
    string $seqKey,
    string $prefix,
    int $sequenceDigits
) {
    if (!idg_is_valid_identifier($tableName) || !idg_is_valid_identifier($columnName) || $sequenceDigits <= 0) {
        return false;
    }

    if (!ensureIdSequenceTable($conn)) {
        error_log("idg_next_prefixed_sequence_id sequence table unavailable for {$tableName}.{$columnName}");
        return false;
    }

    $like = $prefix . '%';
    $maxValue = (int)pow(10, $sequenceDigits) - 1;

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(CAST({$columnName} AS CHAR), {$sequenceDigits}) AS UNSIGNED)), 0) + 1)
        FROM {$tableName}
        WHERE CAST({$columnName} AS CHAR) LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("idg_next_prefixed_sequence_id prepare failed for {$tableName}.{$columnName}: " . $conn->error);
        return false;
    }

    $stmt->bind_param('ss', $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("idg_next_prefixed_sequence_id execute failed for {$tableName}.{$columnName}: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > $maxValue) {
        error_log("idg_next_prefixed_sequence_id out of range for {$tableName}.{$columnName}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, $sequenceDigits, '0', STR_PAD_LEFT);
}

function idg_random_numeric_id(
    mysqli $conn,
    string $tableName,
    string $columnName,
    int $length,
    string $definition = 'BIGINT(20) UNSIGNED NOT NULL',
    int $maxAttempts = 30
) {
    if (!idg_is_valid_identifier($tableName) || !idg_is_valid_identifier($columnName) || $length <= 0) {
        return false;
    }

    idg_ensure_numeric_generated_key($conn, $tableName, $columnName, $definition);

    $min = (int)str_pad('1', $length, '0');
    $max = (int)str_repeat('9', $length);
    $stmt = $conn->prepare("SELECT 1 FROM {$tableName} WHERE {$columnName} = ? LIMIT 1");
    if (!$stmt) {
        error_log("idg_random_numeric_id prepare failed for {$tableName}.{$columnName}: " . $conn->error);
        return false;
    }

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $candidate = random_int($min, $max);
        $candidateStr = (string)$candidate;
        $stmt->bind_param('s', $candidateStr);
        if (!$stmt->execute()) {
            continue;
        }
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $candidate;
        }
        $stmt->free_result();
    }

    $stmt->close();
    error_log("idg_random_numeric_id exhausted attempts for {$tableName}.{$columnName}");
    return false;
}

function idg_department_prefix(string $department): string {
    $normalized = strtolower(trim($department));
    if ($normalized === '') {
        return 'M';
    }
    if (strpos($normalized, 'issuance') !== false) {
        return 'I';
    }
    if (strpos($normalized, 'monitor') !== false) {
        return 'M';
    }
    if (strpos($normalized, 'peace') !== false || strpos($normalized, 'order') !== false || strpos($normalized, 'police') !== false) {
        return 'P';
    }
    if (strpos($normalized, 'finance') !== false || strpos($normalized, 'treasurer') !== false) {
        return 'F';
    }
    return 'M';
}

function idg_role_access_code(string $roleAccess): string {
    $normalized = strtolower(trim($roleAccess));
    if ($normalized === 'official' || $normalized === 'officials' || $normalized === 'admin') {
        return 'O';
    }
    if ($normalized === 'superadmin') {
        return 'S';
    }
    if ($normalized === 'resident') {
        return 'R';
    }
    if ($normalized === 'personnel' || $normalized === 'personnels' || $normalized === 'employee') {
        return 'P';
    }
    return 'X';
}

function idg_last_six_user_digits(?string $userId): string {
    $digits = preg_replace('/\D+/', '', (string)$userId);
    $digits = $digits === null ? '' : $digits;
    return str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT);
}

function idg_user_id_yymm(?string $userId): string {
    $digits = preg_replace('/\D+/', '', (string)$userId);
    $digits = $digits === null ? '' : $digits;
    if (strlen($digits) >= 6) {
        return substr($digits, 2, 4);
    }
    return date('ym');
}

function idg_resident_address_suffix(mysqli $conn, string $residentId): string {
    $residentId = trim($residentId);
    if ($residentId === '' || !idg_table_exists($conn, 'residentaddresstbl')) {
        return '0000';
    }

    $stmt = $conn->prepare("
        SELECT address_id
        FROM residentaddresstbl
        WHERE resident_id = ?
        ORDER BY address_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return '0000';
    }

    $stmt->bind_param('s', $residentId);
    $stmt->execute();
    $stmt->bind_result($addressId);
    $addressValue = $stmt->fetch() ? (string)$addressId : '';
    $stmt->close();

    $digits = preg_replace('/\D+/', '', $addressValue);
    $digits = $digits === null ? '' : $digits;
    if ($digits === '') {
        return '0000';
    }

    return str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
}

function idg_ensure_household_generated_keys(mysqli $conn): void {
    static $cache = [];

    if (isset($cache['household_generated_keys'])) {
        return;
    }
    $cache['household_generated_keys'] = true;

    $definitions = [
        ['table' => 'householdmemberresidenttbl', 'column' => 'household_id', 'definition' => 'BIGINT(20) UNSIGNED NOT NULL'],
        ['table' => 'householdinvitetbl', 'column' => 'household_id', 'definition' => 'BIGINT(20) UNSIGNED NOT NULL'],
        ['table' => 'householdtbl', 'column' => 'household_id', 'definition' => 'BIGINT(20) UNSIGNED NOT NULL'],
        ['table' => 'householdinvitetbl', 'column' => 'invite_id', 'definition' => 'BIGINT(20) UNSIGNED NOT NULL'],
        ['table' => 'householdmemberinfotbl', 'column' => 'household_member_id', 'definition' => 'BIGINT(20) UNSIGNED NOT NULL'],
    ];

    $alterStatements = [];
    foreach ($definitions as $item) {
        if (!idg_table_exists($conn, $item['table'])) {
            continue;
        }
        if (!idg_column_matches_definition($conn, $item['table'], $item['column'], $item['definition'])) {
            $alterStatements[] = sprintf(
                'ALTER TABLE %s MODIFY %s %s',
                $item['table'],
                $item['column'],
                $item['definition']
            );
        }
    }

    if (empty($alterStatements)) {
        return;
    }

    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($alterStatements as $sql) {
        if (!$conn->query($sql)) {
            error_log('idg_ensure_household_generated_keys alter failed: ' . $conn->error . ' | SQL: ' . $sql);
        }
    }
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
}

function GenerateYearMonthSequenceID(
    mysqli $conn,
    string $tableName,
    string $columnName,
    int $sequenceDigits = 4,
    string $seqNamespace = 'GEN'
) {
    $prefix = date('Ym');
    return idg_next_prefixed_sequence_id(
        $conn,
        $tableName,
        $columnName,
        strtoupper(trim($seqNamespace)) . ':' . strtoupper($tableName) . ':' . $prefix,
        $prefix,
        $sequenceDigits
    );
}

function GenerateShortYearMonthSequenceID(
    mysqli $conn,
    string $tableName,
    string $columnName,
    int $sequenceDigits = 6,
    string $seqNamespace = 'GENYYMM'
) {
    $prefix = date('ym');
    return idg_next_prefixed_sequence_id(
        $conn,
        $tableName,
        $columnName,
        strtoupper(trim($seqNamespace)) . ':' . strtoupper($tableName) . ':' . $prefix,
        $prefix,
        $sequenceDigits
    );
}

function GenerateResidentAddressScopedID(
    mysqli $conn,
    string $tableName,
    string $columnName,
    string $residentId,
    int $sequenceDigits = 4,
    string $seqNamespace = 'ADDRSCOPED'
) {
    $prefix = date('ym') . idg_resident_address_suffix($conn, $residentId);
    return idg_next_prefixed_sequence_id(
        $conn,
        $tableName,
        $columnName,
        strtoupper(trim($seqNamespace)) . ':' . strtoupper($tableName) . ':' . $prefix,
        $prefix,
        $sequenceDigits
    );
}

function GenerateUnifiedAttachmentID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'unifiedfileattachmenttbl', 'attachment_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateYearMonthSequenceID($conn, 'unifiedfileattachmenttbl', 'attachment_id', 4, 'UFA');
}

function GenerateHouseholdHeadVerificationID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'householdheadverificationtbl', 'verification_id', 'INT NOT NULL');
    return GenerateYearMonthSequenceID($conn, 'householdheadverificationtbl', 'verification_id', 4, 'HHV');
}

function GenerateHouseholdMemberVerificationRequestID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'householdmemberverificationtbl', 'request_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateShortYearMonthSequenceID($conn, 'householdmemberverificationtbl', 'request_id', 6, 'HMV');
}

function GenerateTenDigitMetaID(mysqli $conn, string $tableName, string $columnName) {
    idg_ensure_numeric_generated_key($conn, $tableName, $columnName, 'INT NOT NULL');
    return GenerateYearMonthSequenceID($conn, $tableName, $columnName, 4, 'META');
}

function GenerateDepartmentScopedID(mysqli $conn, string $tableName, string $columnName, string $department) {
    $prefix = idg_department_prefix($department) . date('ym');
    return idg_next_prefixed_sequence_id(
        $conn,
        $tableName,
        $columnName,
        'DEPT:' . strtoupper($tableName) . ':' . $prefix,
        $prefix,
        5
    );
}

function GenerateResidentEditRequestID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'resident_edit_requesttbl', 'request_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateShortYearMonthSequenceID($conn, 'resident_edit_requesttbl', 'request_id', 6, 'RER');
}

function GeneratePasswordHistoryID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'userpasswordhistorytbl', 'pw_history_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateShortYearMonthSequenceID($conn, 'userpasswordhistorytbl', 'pw_history_id', 6, 'PWH');
}

function GenerateOtpRequestID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'otprequesttbl', 'otp_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateShortYearMonthSequenceID($conn, 'otprequesttbl', 'otp_id', 6, 'OTP');
}

function GenerateClearanceFeeID(mysqli $conn) {
    return idg_random_numeric_id($conn, 'clearancefeestbl', 'clearance_fee_id', 10);
}

function GenerateClearanceFeeTypeID(mysqli $conn) {
    return idg_random_numeric_id($conn, 'clearancefeetypetbl', 'fee_type_id', 10);
}

function GenerateEmergencyContactID(mysqli $conn, ?string $userId) {
    idg_ensure_numeric_generated_key($conn, 'emergencycontacttbl', 'emergency_id', 'BIGINT(20) UNSIGNED NOT NULL');

    $safeUserId = trim((string)$userId);
    if ($safeUserId === '') {
        return false;
    }

    if (idg_table_exists($conn, 'emergencycontacttbl')) {
        $stmt = $conn->prepare("SELECT emergency_id FROM emergencycontacttbl WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $safeUserId);
            $stmt->execute();
            $stmt->bind_result($existingId);
            $found = $stmt->fetch();
            $stmt->close();
            if ($found && trim((string)$existingId) !== '') {
                return (int)$existingId;
            }
        }
    }

    $candidate = idg_user_id_yymm($safeUserId) . idg_last_six_user_digits($safeUserId);
    if (!preg_match('/^\d{10}$/', $candidate)) {
        return false;
    }

    if (idg_table_exists($conn, 'emergencycontacttbl')) {
        $stmt = $conn->prepare("SELECT user_id FROM emergencycontacttbl WHERE emergency_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $stmt->bind_result($existingUserId);
            $found = $stmt->fetch();
            $stmt->close();
            if ($found && trim((string)$existingUserId) !== '' && trim((string)$existingUserId) !== $safeUserId) {
                error_log("GenerateEmergencyContactID collision for user {$safeUserId} using {$candidate}");
                return false;
            }
        }
    }

    return (int)$candidate;
}

function UpsertEmergencyContactRecord(
    mysqli $conn,
    string $userId,
    string $lastName,
    string $firstName,
    ?string $middleName,
    ?string $suffix,
    string $phoneNumber,
    string $relationship,
    string $address
) {
    $emergencyId = GenerateEmergencyContactID($conn, $userId);
    if ($emergencyId === false) {
        error_log("UpsertEmergencyContactRecord failed to generate emergency_id for user {$userId}");
        return false;
    }

    $encrypted = pii_encrypt_field_map([
        'last_name' => $lastName,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'suffix' => $suffix,
        'phone_number' => pii_normalize_phone10($phoneNumber),
        'relationship' => $relationship,
        'address' => $address,
    ]);

    $sql = "
        INSERT INTO emergencycontacttbl
            (emergency_id, user_id, last_name, first_name, middle_name, suffix, phone_number, relationship, address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            last_name = VALUES(last_name),
            first_name = VALUES(first_name),
            middle_name = VALUES(middle_name),
            suffix = VALUES(suffix),
            phone_number = VALUES(phone_number),
            relationship = VALUES(relationship),
            address = VALUES(address),
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('UpsertEmergencyContactRecord prepare failed: ' . $conn->error);
        return false;
    }

    $emergencyIdStr = (string)$emergencyId;
    $stmt->bind_param(
        'sssssssss',
        $emergencyIdStr,
        $userId,
        $encrypted['last_name'],
        $encrypted['first_name'],
        $encrypted['middle_name'],
        $encrypted['suffix'],
        $encrypted['phone_number'],
        $encrypted['relationship'],
        $encrypted['address']
    );

    if (!$stmt->execute()) {
        error_log('UpsertEmergencyContactRecord execute failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return $emergencyId;
}

function GenerateHouseholdID(mysqli $conn, string $headResidentId) {
    idg_ensure_household_generated_keys($conn);
    return GenerateResidentAddressScopedID($conn, 'householdtbl', 'household_id', $headResidentId, 4, 'HOUSEHOLD');
}

function GenerateHouseholdInviteID(mysqli $conn, string $residentId) {
    idg_ensure_household_generated_keys($conn);
    return GenerateResidentAddressScopedID($conn, 'householdinvitetbl', 'invite_id', $residentId, 4, 'HOUSEHOLDINVITE');
}

function GenerateHouseholdMemberInfoID(mysqli $conn, string $headResidentId) {
    idg_ensure_household_generated_keys($conn);
    return GenerateResidentAddressScopedID($conn, 'householdmemberinfotbl', 'household_member_id', $headResidentId, 4, 'HOUSEHOLDMEMBERINFO');
}

function GenerateUnifiedAuditLogID(mysqli $conn, ?string $userId, string $roleAccess) {
    idg_ensure_string_generated_key($conn, 'unifiedauditlogstbl', 'audit_id', 16);

    $prefix = date('ym') . idg_role_access_code($roleAccess) . idg_last_six_user_digits($userId);
    return idg_next_prefixed_sequence_id(
        $conn,
        'unifiedauditlogstbl',
        'audit_id',
        'AUD:' . $prefix,
        $prefix,
        5
    );
}

function idg_is_generated_official_personnel_id(string $officialId): bool {
    return preg_match('/^\d{10}$/', trim($officialId)) === 1;
}

function idg_official_personnel_prefix_for_date(?string $dateValue = null): string {
    $normalized = trim((string)$dateValue);
    if ($normalized !== '') {
        $timestamp = strtotime($normalized);
        if ($timestamp !== false) {
            return date('mY', $timestamp);
        }
    }

    return date('mY');
}

function idg_next_official_personnel_id(mysqli $conn, string $prefix) {
    return idg_next_prefixed_sequence_id(
        $conn,
        'officialinformationtbl',
        'official_id',
        'OFFID:' . $prefix,
        $prefix,
        4
    );
}

function idg_update_official_id_reference(mysqli $conn, string $table, string $column, string $legacyOfficialId, string $newOfficialId): void {
    $legacyOfficialId = trim($legacyOfficialId);
    $newOfficialId = trim($newOfficialId);

    if ($legacyOfficialId === '' || $newOfficialId === '' || $legacyOfficialId === $newOfficialId) {
        return;
    }
    if (!idg_table_exists($conn, $table) || !idg_get_column_info($conn, $table, $column)) {
        return;
    }

    $stmt = $conn->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?");
    if (!$stmt) {
        error_log("idg_update_official_id_reference prepare failed for {$table}.{$column}: " . $conn->error);
        return;
    }

    $stmt->bind_param('ss', $newOfficialId, $legacyOfficialId);
    if (!$stmt->execute()) {
        error_log("idg_update_official_id_reference execute failed for {$table}.{$column}: " . $stmt->error);
    }
    $stmt->close();
}

function idg_migrate_legacy_official_personnel_id(mysqli $conn, string $legacyOfficialId, ?string $preferredDate = null): string {
    $legacyOfficialId = trim($legacyOfficialId);
    if ($legacyOfficialId === '' || idg_is_generated_official_personnel_id($legacyOfficialId)) {
        return $legacyOfficialId;
    }

    $prefix = idg_official_personnel_prefix_for_date($preferredDate);
    $newOfficialId = idg_next_official_personnel_id($conn, $prefix);
    if ($newOfficialId === false) {
        error_log("idg_migrate_legacy_official_personnel_id failed to generate replacement for legacy ID {$legacyOfficialId}");
        return $legacyOfficialId;
    }

    $newOfficialId = trim((string)$newOfficialId);
    if ($newOfficialId === '' || $newOfficialId === $legacyOfficialId) {
        return $legacyOfficialId;
    }

    $referenceMap = [
        ['officialinformationtbl', 'official_id'],
        ['officialaccessprofiletbl', 'official_id'],
        ['officialmodulepermissionstbl', 'official_id'],
        ['officialinformationtbl', 'acting_for_id'],
        ['barangaycounciltbl', 'current_official_id'],
        ['officialtransitionstbl', 'incoming_official_id'],
        ['officialtransitionstbl', 'outgoing_official_id'],
        ['upcomingofficialstbl', 'linked_official_id'],
    ];

    try {
        $conn->begin_transaction();

        foreach ($referenceMap as [$table, $column]) {
            idg_update_official_id_reference($conn, $table, $column, $legacyOfficialId, $newOfficialId);
        }

        $conn->commit();
        return $newOfficialId;
    } catch (Throwable $throwable) {
        $conn->rollback();
        error_log('idg_migrate_legacy_official_personnel_id failed: ' . $throwable->getMessage());
        return $legacyOfficialId;
    }
}

function GenerateOfficialPersonnelID(mysqli $conn, string $userId = '') {
    idg_ensure_string_generated_key($conn, 'officialinformationtbl', 'official_id', 10);

    $userId = trim($userId);
    if ($userId !== '' && idg_table_exists($conn, 'officialinformationtbl')) {
        $stmt = $conn->prepare("SELECT official_id, date_hired FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $existing = trim((string)($row['official_id'] ?? ''));
            if ($existing !== '') {
                if (idg_is_generated_official_personnel_id($existing)) {
                    return $existing;
                }

                return idg_migrate_legacy_official_personnel_id(
                    $conn,
                    $existing,
                    (string)($row['date_hired'] ?? '')
                );
            }
        }
    }

    $prefix = date('mY');
    return idg_next_official_personnel_id($conn, $prefix);
}

function insertUnifiedFileAttachment(mysqli $conn, array $payload, string $errorContext = 'attachment'): int {
    $attachmentId = (int)($payload['attachment_id'] ?? 0);
    if ($attachmentId <= 0) {
        $generatedId = GenerateUnifiedAttachmentID($conn);
        $attachmentId = $generatedId !== false ? (int)$generatedId : 0;
    }
    if ($attachmentId <= 0) {
        throw new RuntimeException("Failed to generate {$errorContext} ID.");
    }

    $sourceType = trim((string)($payload['source_type'] ?? ''));
    $sourceId = trim((string)($payload['source_id'] ?? ''));
    $documentTypeId = (int)($payload['document_type_id'] ?? 0);
    $fileName = (string)($payload['file_name'] ?? '');
    $filePath = (string)($payload['file_path'] ?? '');
    $fileType = (string)($payload['file_type'] ?? '');
    $userIdUploadedBy = trim((string)($payload['user_id_uploaded_by'] ?? ''));
    $statusIdVerify = (int)($payload['status_id_verify'] ?? 0);
    $remarks = isset($payload['remarks']) ? (string)$payload['remarks'] : null;
    $idNumber = isset($payload['id_number']) ? (string)$payload['id_number'] : null;

    $stmt = $conn->prepare("
        INSERT INTO unifiedfileattachmenttbl
            (attachment_id, source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException("Failed to prepare {$errorContext} insert.");
    }

    $stmt->bind_param(
        'ississssiss',
        $attachmentId,
        $sourceType,
        $sourceId,
        $documentTypeId,
        $fileName,
        $filePath,
        $fileType,
        $userIdUploadedBy,
        $statusIdVerify,
        $remarks,
        $idNumber
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException("Failed to save {$errorContext}: {$error}");
    }
    $stmt->close();

    return $attachmentId;
}

function insertResidentEditRequest(
    mysqli $conn,
    string $residentId,
    string $userId,
    string $requestType,
    int $statusId,
    string $requestedChanges
): int {
    $requestId = GenerateResidentEditRequestID($conn);
    if ($requestId === false) {
        throw new RuntimeException('Failed to generate resident edit request ID.');
    }

    $requestIdInt = (int)$requestId;
    $requestType = trim($requestType);
    $stmt = $conn->prepare("
        INSERT INTO resident_edit_requesttbl
            (request_id, resident_id, user_id, request_type, status_id, requested_changes)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare edit request insert.');
    }

    $stmt->bind_param('isssis', $requestIdInt, $residentId, $userId, $requestType, $statusId, $requestedChanges);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to submit edit request. ' . $error);
    }
    $stmt->close();

    return $requestIdInt;
}

function insertPasswordHistoryEntry(mysqli $conn, string $userId, string $oldPasswordHash): int {
    $historyId = GeneratePasswordHistoryID($conn);
    if ($historyId === false) {
        throw new RuntimeException('Failed to generate password history ID.');
    }

    $historyIdInt = (int)$historyId;
    $stmt = $conn->prepare("
        INSERT INTO userpasswordhistorytbl (pw_history_id, user_id, old_pw_hash)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare password history insert.');
    }

    $stmt->bind_param('iss', $historyIdInt, $userId, $oldPasswordHash);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to save password history. ' . $error);
    }
    $stmt->close();

    return $historyIdInt;
}

function insertOtpRequest(
    mysqli $conn,
    ?string $userId,
    string $recipient,
    string $purpose,
    string $otpHash,
    string $expiryTime,
    string $requestTime,
    int $statusIdOtp
): int {
    $otpId = GenerateOtpRequestID($conn);
    if ($otpId === false) {
        throw new RuntimeException('Failed to generate OTP request ID.');
    }

    $otpIdInt = (int)$otpId;
    $stmt = $conn->prepare("
        INSERT INTO otprequesttbl
            (otp_id, user_id, recipient, purpose, otp_code_hash, otp_expiry, request_timestamp, status_id_otp)
        VALUES
            (?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare OTP insert.');
    }

    $safeUserId = trim((string)$userId);
    $stmt->bind_param('issssssi', $otpIdInt, $safeUserId, $recipient, $purpose, $otpHash, $expiryTime, $requestTime, $statusIdOtp);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to store OTP. ' . $error);
    }
    $stmt->close();

    return $otpIdInt;
}

function insertHouseholdRecord(mysqli $conn, string $headResidentId, int $statusId): int {
    $householdId = GenerateHouseholdID($conn, $headResidentId);
    if ($householdId === false) {
        throw new RuntimeException('Failed to generate household ID.');
    }

    $householdIdInt = (int)$householdId;
    $stmt = $conn->prepare("
        INSERT INTO householdtbl (household_id, head_resident_id, status_id)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare household insert.');
    }

    $stmt->bind_param('isi', $householdIdInt, $headResidentId, $statusId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to create household. ' . $error);
    }
    $stmt->close();

    return $householdIdInt;
}

function insertHouseholdInvite(
    mysqli $conn,
    int $householdId,
    string $codeHash,
    string $expiresAt,
    int $maxUses,
    string $createdByResidentId,
    int $statusId
): int {
    $inviteId = GenerateHouseholdInviteID($conn, $createdByResidentId);
    if ($inviteId === false) {
        throw new RuntimeException('Failed to generate household invite ID.');
    }

    $inviteIdInt = (int)$inviteId;
    $stmt = $conn->prepare("
        INSERT INTO householdinvitetbl
            (invite_id, household_id, code_hash, expires_at, max_uses, uses_count, created_by_resident_id, status_id)
        VALUES
            (?, ?, ?, ?, ?, 0, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare household invite insert.');
    }

    $stmt->bind_param('iissisi', $inviteIdInt, $householdId, $codeHash, $expiresAt, $maxUses, $createdByResidentId, $statusId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to create household invite. ' . $error);
    }
    $stmt->close();

    return $inviteIdInt;
}

function insertHouseholdMemberInfo(
    mysqli $conn,
    string $headResidentId,
    string $lastName,
    string $firstName,
    ?string $middleName,
    ?string $suffix,
    string $birthdate
): int {
    $memberId = GenerateHouseholdMemberInfoID($conn, $headResidentId);
    if ($memberId === false) {
        throw new RuntimeException('Failed to generate household member info ID.');
    }

    $memberIdInt = (int)$memberId;
    $stmt = $conn->prepare("
        INSERT INTO householdmemberinfotbl
            (household_member_id, fam_head_id, last_name, first_name, middle_name, suffix, birthdate)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare household member insert.');
    }

    $stmt->bind_param('issssss', $memberIdInt, $headResidentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to add household member. ' . $error);
    }
    $stmt->close();

    return $memberIdInt;
}

function GenerateUserID($conn, $roleAccess) {
    // Only 3 roles
    $roleLetters = [
        "SuperAdmin" => "S",
        "Personnel"  => "P",
        "Personnels" => "P",
        "Official"   => "O",
        "Officials"  => "O",
        "Resident"   => "R"
    ];

    if (!isset($roleLetters[$roleAccess])) {
        error_log("GenerateUserID: Invalid role value: $roleAccess");
        return false;
    }

    $roleLetter = $roleLetters[$roleAccess];
    $yearMonth  = date("Ym");
    $prefix     = $yearMonth . $roleLetter; // e.g., 202601R
    $like       = $prefix . "%";

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateUserID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(user_id, 5) AS UNSIGNED)), 0) + 1)
        FROM useraccountstbl
        WHERE user_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateUserID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $prefix, $like);
    if (!$stmt->execute()) {
        error_log("GenerateUserID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 99999) {
        error_log("GenerateUserID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 5, "0", STR_PAD_LEFT); // e.g., 202601R00001
}

function GenerateResidentID($conn) {
    // Format: YYMMXXXXXX (monthly sequence, monotonic per prefix)
    $yearMonth = date("ym");
    $prefix = $yearMonth;
    $like = $prefix . "%"; // e.g., 2602%
    $seqKey = 'RID:' . $prefix;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateResidentID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(resident_id, 6) AS UNSIGNED)), 0) + 1)
        FROM residentinformationtbl
        WHERE resident_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateResidentID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateResidentID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 999999) {
        error_log("GenerateResidentID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 6, "0", STR_PAD_LEFT); // e.g., 2602000001
}

function GenerateAddressID(mysqli $conn, string $areaNumber): string {
    $cleanArea = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($areaNumber)));
    if ($cleanArea === '') {
        $cleanArea = 'A00';
    }
    $areaCode = str_pad(substr($cleanArea, 0, 3), 3, '0', STR_PAD_RIGHT);
    $year = date("y");
    $prefix = $areaCode . $year;
    $like = $prefix . "%";

    $seqKey = 'AID:' . $prefix;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateAddressID sequence table unavailable.");
        $fallbackSeq = str_pad((string)random_int(1, 99999), 5, "0", STR_PAD_LEFT);
        return $prefix . $fallbackSeq;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(address_id, 5) AS UNSIGNED)), 0) + 1)
        FROM residentaddresstbl
        WHERE address_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateAddressID sequence prepare failed: " . $conn->error);
        $fallbackSeq = str_pad((string)random_int(1, 99999), 5, "0", STR_PAD_LEFT);
        return $prefix . $fallbackSeq;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateAddressID sequence execute failed: " . $stmt->error);
        $stmt->close();
        $fallbackSeq = str_pad((string)random_int(1, 99999), 5, "0", STR_PAD_LEFT);
        return $prefix . $fallbackSeq;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 99999) {
        $fallbackSeq = str_pad((string)random_int(1, 99999), 5, "0", STR_PAD_LEFT);
        return $prefix . $fallbackSeq;
    }

    return $prefix . str_pad((string)$nextSeq, 5, "0", STR_PAD_LEFT);
}

function GenerateTransactionID(
    mysqli $conn,
    string $tableName = 'residenttransactiontbl',
    string $columnName = 'transaction_id'
): string {
    // Format: MMYYYYXXXX (non-sequential random suffix).
    $prefix = date("mY"); // e.g., 022026

    // Validate dynamic identifiers to avoid SQL injection.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        error_log("GenerateTransactionID: Invalid table or column name.");
        return $prefix . str_pad((string)random_int(1, 9999), 4, "0", STR_PAD_LEFT);
    }

    $existsStmt = $conn->prepare("
        SELECT 1
        FROM {$tableName}
        WHERE {$columnName} = ?
        LIMIT 1
    ");
    if (!$existsStmt) {
        // Table may not exist yet; allow generation anyway.
        error_log("GenerateTransactionID existence-check prepare failed: " . $conn->error);
        return $prefix . str_pad((string)random_int(0, 9999), 4, "0", STR_PAD_LEFT);
    }

    // Try multiple random candidates and return first unused ID.
    for ($i = 0; $i < 64; $i++) {
        $suffix = str_pad((string)random_int(0, 9999), 4, "0", STR_PAD_LEFT);
        $candidate = $prefix . $suffix;
        $existsStmt->bind_param("s", $candidate);
        $existsStmt->execute();
        $res = $existsStmt->get_result();
        $taken = $res && $res->num_rows > 0;
        if (!$taken) {
            $existsStmt->close();
            return $candidate;
        }
    }

    // Fallback if random-space is saturated for this month.
    $existsStmt->close();
    return $prefix . str_pad((string)random_int(0, 9999), 4, "0", STR_PAD_LEFT);
}

function GenerateCaseID(mysqli $conn) {
    $yearMonth = date("ym");
    $prefix = "CS" . $yearMonth;
    $like = $prefix . "%";
    $seqKey = 'CASE:' . $yearMonth;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateCaseID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(case_id, 6) AS UNSIGNED)), 0) + 1)
        FROM casereportstbl
        WHERE case_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateCaseID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateCaseID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 999999) {
        error_log("GenerateCaseID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 6, "0", STR_PAD_LEFT);
}

function GenerateComplaintID(mysqli $conn) {
    $year = date("Y");
    $prefix = "C" . $year;
    $like = $prefix . "%";
    $seqKey = 'CID:' . $year;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateComplaintID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(complaint_id, 5) AS UNSIGNED)), 0) + 1)
        FROM complaintstbl
        WHERE complaint_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateComplaintID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateComplaintID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 99999) {
        error_log("GenerateComplaintID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 5, "0", STR_PAD_LEFT);
}

function GenerateBlotterID(mysqli $conn) {
    $year = date("Y");
    $prefix = "B" . $year;
    $like = $prefix . "%";
    $seqKey = 'BID:' . $year;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateBlotterID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(blotter_id, 5) AS UNSIGNED)), 0) + 1)
        FROM barangayblottertbl
        WHERE blotter_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateBlotterID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateBlotterID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 99999) {
        error_log("GenerateBlotterID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 5, "0", STR_PAD_LEFT);
}

function GenerateBlotterRequestID(mysqli $conn) {
    $year = date("Y");
    $prefix = "BR" . $year;
    $like = $prefix . "%";
    $seqKey = 'BRID:' . $year;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateBlotterRequestID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(request_id, 5) AS UNSIGNED)), 0) + 1)
        FROM blotterrequeststbl
        WHERE request_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateBlotterRequestID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateBlotterRequestID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 99999) {
        error_log("GenerateBlotterRequestID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 5, "0", STR_PAD_LEFT);
}

function GenerateAppointmentID(mysqli $conn) {
    $year = date("y");
    $prefix = "AP" . $year;
    $like = $prefix . "%";
    $seqKey = 'AID:' . $year;

    if (!ensureIdSequenceTable($conn)) {
        error_log("GenerateAppointmentID sequence table unavailable.");
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO idsequencetbl (seq_key, last_seq)
        SELECT ?, LAST_INSERT_ID(COALESCE(MAX(CAST(RIGHT(appointment_id, 6) AS UNSIGNED)), 0) + 1)
        FROM appointmentstbl
        WHERE appointment_id LIKE ?
        ON DUPLICATE KEY UPDATE
            last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        error_log("GenerateAppointmentID sequence prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ss", $seqKey, $like);
    if (!$stmt->execute()) {
        error_log("GenerateAppointmentID sequence execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();

    $nextSeq = (int)$conn->insert_id;
    if ($nextSeq <= 0 || $nextSeq > 999999) {
        error_log("GenerateAppointmentID sequence out of range for prefix {$prefix}: {$nextSeq}");
        return false;
    }

    return $prefix . str_pad((string)$nextSeq, 6, "0", STR_PAD_LEFT);
}

function GenerateAppointmentQueueID(mysqli $conn) {
    idg_ensure_numeric_generated_key($conn, 'appointmentqueuetbl', 'queue_id', 'BIGINT(20) UNSIGNED NOT NULL');
    return GenerateYearMonthSequenceID($conn, 'appointmentqueuetbl', 'queue_id', 4, 'APQUEUE');
}
