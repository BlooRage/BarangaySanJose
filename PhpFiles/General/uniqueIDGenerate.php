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

function GenerateUserID($conn, $roleAccess) {
    // Only 3 roles
    $roleLetters = [
        "SuperAdmin" => "S",
        "Personnel"  => "P",
        "Personnels" => "P",
        "Employee"   => "E",
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
    string $tableName = 'unifiedtransactiontbl',
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
