<?php
function GenerateUserID($conn, $roleAccess) {
    // Only 3 roles
    $roleLetters = [
        "SuperAdmin" => "S",
        "Official"   => "O",
        "Resident"   => "R"
    ];

    if (!isset($roleLetters[$roleAccess])) {
        error_log("GenerateUserID: Invalid role value: $roleAccess");
        return false;
    }

    $roleLetter = $roleLetters[$roleAccess]; // Assign letter automatically
    $yearMonth  = date("Ym");
    $like       = $yearMonth . $roleLetter . "%"; // e.g., 202601R%

    // Get the last user_id for this role this month
    $stmt = $conn->prepare("
        SELECT user_id 
        FROM useraccountstbl
        WHERE user_id LIKE ?
        ORDER BY user_id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("GenerateUserID Prepare Failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($lastID);
    $stmt->fetch();
    $stmt->close();

    // Generate the next sequence number
    $newSeq = $lastID
        ? str_pad(((int)substr($lastID, -5)) + 1, 5, "0", STR_PAD_LEFT)
        : "00001";

    return $yearMonth . $roleLetter . $newSeq; // e.g., 202601R00001
}

function GenerateResidentID($conn) {
    // Format: YYMMXXXXXX (monthly sequence)
    $yearMonth = date("ym");
    $like = $yearMonth . "%"; // e.g., 2602%

    $stmt = $conn->prepare("
        SELECT resident_id
        FROM residentinformationtbl
        WHERE resident_id LIKE ?
        ORDER BY resident_id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("GenerateResidentID Prepare Failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($lastID);
    $stmt->fetch();
    $stmt->close();

    $newSeq = $lastID
        ? str_pad(((int)substr($lastID, -6)) + 1, 6, "0", STR_PAD_LEFT)
        : "000001";

    return $yearMonth . $newSeq; // e.g., 2602000001
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

    $stmt = $conn->prepare("
        SELECT address_id
        FROM residentaddresstbl
        WHERE address_id LIKE ?
        ORDER BY address_id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("GenerateAddressID prepare failed: " . $conn->error);
        $fallbackSeq = str_pad((string)random_int(1, 99999), 5, "0", STR_PAD_LEFT);
        return $prefix . $fallbackSeq;
    }

    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($lastId);
    $stmt->fetch();
    $stmt->close();

    $nextSeq = $lastId
        ? str_pad(((int)substr($lastId, -5)) + 1, 5, "0", STR_PAD_LEFT)
        : "00001";

    return $prefix . $nextSeq;
}

function GenerateTransactionID(
    mysqli $conn,
    string $tableName = 'unifiedtransactiontbl',
    string $columnName = 'transaction_id'
): string {
    // Format: MMYYYYXXXX (monthly sequence)
    $prefix = date("mY"); // e.g., 022026
    $like = $prefix . "%";

    // Validate dynamic identifiers to avoid SQL injection.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        error_log("GenerateTransactionID: Invalid table or column name.");
        return $prefix . str_pad((string)random_int(1, 9999), 4, "0", STR_PAD_LEFT);
    }

    $sql = "
        SELECT {$columnName}
        FROM {$tableName}
        WHERE {$columnName} LIKE ?
        ORDER BY {$columnName} DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Table may not exist yet; allow generation anyway.
        error_log("GenerateTransactionID Prepare Failed: " . $conn->error);
        return $prefix . str_pad((string)random_int(1, 9999), 4, "0", STR_PAD_LEFT);
    }

    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($lastId);
    $stmt->fetch();
    $stmt->close();

    $nextSeq = $lastId
        ? str_pad(((int)substr((string)$lastId, -4)) + 1, 4, "0", STR_PAD_LEFT)
        : "0001";

    return $prefix . $nextSeq; // e.g., 0220260001
}
