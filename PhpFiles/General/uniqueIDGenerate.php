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
