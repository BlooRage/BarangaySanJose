<?php
require '../General/connection.php';

header('Content-Type: application/json');

$email       = $_POST['email'] ?? '';
$phone       = $_POST['phone'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';

$response = ['success' => false];

if (!$email || !$phone || !$newPassword) {
    $response['error'] = 'Missing required fields.';
    echo json_encode($response);
    exit;
}


$stmt = $conn->prepare("
    SELECT user_id, password_hash
    FROM useraccountstbl
    WHERE email = ? AND phone_number = ?
");
$stmt->bind_param("ss", $email, $phone);
$stmt->execute();
$stmt->bind_result($userId, $currentHash);

if (!$stmt->fetch()) {
    $response['error'] = 'User not found.';
    echo json_encode($response);
    exit;
}
$stmt->close();


if (password_verify($newPassword, $currentHash)) {
    $response['error'] = 'New password must be different from old password';
    echo json_encode($response);
    exit;
}


$stmt = $conn->prepare("
    SELECT old_pw_hash, change_timestamp
    FROM userpasswordhistorytbl
    WHERE user_id = ?
    ORDER BY change_timestamp DESC
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stmt->bind_result($oldHash, $changeTimestamp);

$now = new DateTime();
$historyIndex = 0;

while ($stmt->fetch()) {
    $usedAt = new DateTime($changeTimestamp);
    $monthsAgo = ($now->diff($usedAt)->y * 12) + $now->diff($usedAt)->m;


    if ($historyIndex < 3 && password_verify($newPassword, $oldHash)) {
        $response['error'] = 'You’ve already used this password';
        echo json_encode($response);
        exit;
    }


    if ($monthsAgo < 6 && password_verify($newPassword, $oldHash)) {
        $response['error'] = 'You’ve already used this password';
        echo json_encode($response);
        exit;
    }

    $historyIndex++;
}
$stmt->close();


$stmt = $conn->prepare("
    INSERT INTO userpasswordhistorytbl (user_id, old_pw_hash)
    VALUES (?, ?)
");
$stmt->bind_param("ss", $userId, $currentHash);
$stmt->execute();
$stmt->close();


$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    UPDATE useraccountstbl
    SET password_hash = ?, last_password_changed = NOW()
    WHERE user_id = ?
");
$stmt->bind_param("ss", $hashedPassword, $userId);

if (!$stmt->execute()) {
    $response['error'] = 'Failed to update password.';
    echo json_encode($response);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("
    DELETE FROM userpasswordhistorytbl
    WHERE user_id = ?
      AND pw_history_id NOT IN (
          SELECT pw_history_id FROM (
              SELECT pw_history_id
              FROM userpasswordhistorytbl
              WHERE user_id = ?
              ORDER BY change_timestamp DESC
              LIMIT 3
          ) x
      )
      AND change_timestamp < NOW() - INTERVAL 6 MONTH
");
$stmt->bind_param("ss", $userId, $userId);
$stmt->execute();
$stmt->close();

$response['success'] = true;
echo json_encode($response);
