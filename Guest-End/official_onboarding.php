<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../PhpFiles/General/connection.php";
require_once __DIR__ . "/../PhpFiles/General/security.php";
require_once __DIR__ . "/../PhpFiles/General/officialInviteCommon.php";
require_once __DIR__ . "/../PhpFiles/General/uniqueIDGenerate.php";
require_once __DIR__ . "/../PhpFiles/General/sendSMS.php";
require_once __DIR__ . "/../PhpFiles/EmailHandlers/emailSender.php";
require_once __DIR__ . "/../PhpFiles/General/mailConfigurations.php";

oi_ensure_invite_table($conn);

$errors = [];
$success = '';

function oi_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function oi_ensure_official_info_columns(mysqli $conn): void
{
    $columns = [
        "emergency_contact_name VARCHAR(150) NULL",
        "emergency_contact_relationship VARCHAR(80) NULL",
        "emergency_contact_phone VARCHAR(15) NULL",
        "emergency_contact_address VARCHAR(255) NULL",
        "house_number VARCHAR(50) NULL",
        "street_name VARCHAR(150) NULL",
        "address_mode VARCHAR(20) NULL",
        "block_number VARCHAR(50) NULL",
        "lot_number VARCHAR(50) NULL",
        "barangay VARCHAR(150) NULL",
        "municipality_city VARCHAR(150) NULL",
        "province VARCHAR(150) NULL",
        "position_access VARCHAR(100) NULL",
        "area_number VARCHAR(50) NULL",
    ];

    foreach ($columns as $definition) {
        $columnName = strtok($definition, " ");
        if ($columnName === false || $columnName === '') {
            continue;
        }
        if (oi_column_exists($conn, 'officialinformationtbl', $columnName)) {
            continue;
        }
        $conn->query("ALTER TABLE officialinformationtbl ADD COLUMN {$definition}");
    }
    // Keep legacy role_access values visible in new column where available.
    $conn->query("
        UPDATE officialinformationtbl
        SET position_access = role_access
        WHERE (position_access IS NULL OR TRIM(position_access) = '')
          AND role_access IS NOT NULL
          AND TRIM(role_access) <> ''
    ");
}

oi_ensure_official_info_columns($conn);

function oi_find_invite_by_token(mysqli $conn, string $rawToken): ?array
{
    $rawToken = trim($rawToken);
    if ($rawToken === '') return null;
    $q = $conn->query("
        SELECT *
        FROM officialinvitetbl
        WHERE status IN ('Pending', 'InProgress')
          AND expires_at > NOW()
        ORDER BY invite_id DESC
        LIMIT 200
    ");
    if (!$q) return null;
    while ($row = $q->fetch_assoc()) {
        $hash = (string)($row['invite_token_hash'] ?? '');
        if ($hash !== '' && password_verify($rawToken, $hash)) {
            return $row;
        }
    }
    return null;
}

function oi_find_active_invite_by_user(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT *
        FROM officialinvitetbl
        WHERE user_id = ?
          AND status IN ('InProgress', 'Completed')
        ORDER BY invite_id DESC
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function oi_get_account(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT user_id, email, phone_number, email_verify, phoneNum_verify, role_access
        FROM useraccountstbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function oi_send_email_otp(string $email, string $otp): bool
{
    $smtpConfig = require __DIR__ . "/../PhpFiles/General/mailConfigurations.php";
    $sender = new EmailSender($smtpConfig);
    $html = '<p>Your Barangay San Jose onboarding OTP is:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>This code will expire in 5 minutes.</p>';
    return $sender->send([
        'to' => $email,
        'subject' => 'Official Account Onboarding OTP',
        'bodyHtml' => $html,
        'bodyText' => "Your onboarding OTP is {$otp}. This code expires in 5 minutes.",
    ]);
}

function oi_official_info_exists(mysqli $conn, string $userId): bool
{
    $stmt = $conn->prepare("SELECT official_id FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

function oi_get_official_info(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT *
        FROM officialinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function oi_non_empty(?string $v): bool
{
    return trim((string)$v) !== '';
}

function oi_has_info_and_contact(?array $row): bool
{
    if (!$row) return false;
    return oi_non_empty((string)($row['birthdate'] ?? ''))
        && oi_non_empty((string)($row['sex'] ?? ''))
        && oi_non_empty((string)($row['civil_status'] ?? ''))
        && oi_non_empty((string)($row['emergency_contact_name'] ?? ''))
        && oi_non_empty((string)($row['emergency_contact_relationship'] ?? ''))
        && oi_non_empty((string)($row['emergency_contact_phone'] ?? ''));
}

function oi_has_address(?array $row): bool
{
    if (!$row) return false;
    $mode = strtolower(trim((string)($row['address_mode'] ?? 'street')));
    $hasCore = oi_non_empty((string)($row['barangay'] ?? ''))
        && oi_non_empty((string)($row['municipality_city'] ?? ''))
        && oi_non_empty((string)($row['province'] ?? ''));
    if (!$hasCore) return false;
    if ($mode === 'block_lot') {
        return oi_non_empty((string)($row['block_number'] ?? ''))
            && oi_non_empty((string)($row['lot_number'] ?? ''));
    }
    return oi_non_empty((string)($row['house_number'] ?? ''))
        && oi_non_empty((string)($row['street_name'] ?? ''));
}

function oi_get_document_type_id(mysqli $conn, string $name, string $category = 'OfficialProfiling'): int
{
    $q = $conn->prepare("
        SELECT document_type_id
        FROM documenttypelookuptbl
        WHERE LOWER(document_type_name) = LOWER(?)
          AND document_category = ?
        LIMIT 1
    ");
    if (!$q) {
        throw new RuntimeException('Failed to prepare document type lookup.');
    }
    $q->bind_param("ss", $name, $category);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    if ($row && isset($row['document_type_id'])) {
        return (int)$row['document_type_id'];
    }

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, ?)");
    if (!$ins) {
        throw new RuntimeException('Failed to prepare document type creation.');
    }
    $ins->bind_param("ss", $name, $category);
    if (!$ins->execute()) {
        $ins->close();
        throw new RuntimeException("Failed to create document type {$name}.");
    }
    $newId = (int)$ins->insert_id;
    $ins->close();
    if ($newId <= 0) {
        throw new RuntimeException("Unable to resolve document type {$name}.");
    }
    return $newId;
}

function oi_get_or_create_status_id(mysqli $conn, string $name, array $types): ?int
{
    $id = oi_get_status_id($conn, $name, $types);
    if ($id !== null) {
        return $id;
    }
    $targetType = trim((string)($types[0] ?? ''));
    if ($targetType === '') {
        return null;
    }

    $ins = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if (!$ins) {
        return null;
    }
    $ins->bind_param("ss", $name, $targetType);
    $ok = $ins->execute();
    $newId = $ok ? (int)$ins->insert_id : 0;
    $ins->close();
    if ($newId > 0) {
        return $newId;
    }

    // If insert failed due to duplicates/race, try fetch again.
    return oi_get_status_id($conn, $name, $types);
}

function oi_resolve_pending_review_status_id(mysqli $conn): ?int
{
    $types = ['DocumentVerification', 'VerificationStatus', 'Verification'];
    $id = oi_get_status_id($conn, 'PendingReview', $types);
    if ($id !== null) {
        return $id;
    }
    // Legacy fallback if installation used "Pending" for document review.
    $id = oi_get_status_id($conn, 'Pending', $types);
    if ($id !== null) {
        return $id;
    }
    return oi_get_or_create_status_id($conn, 'PendingReview', ['DocumentVerification']);
}

function oi_to_db_web_path(string $absolutePath): string
{
    $absolutePath = str_replace("\\", "/", trim($absolutePath));
    $projectRoot = realpath(__DIR__ . "/..");
    $marker = "/UnifiedFileAttachment/";
    $markerPos = strpos($absolutePath, $marker);
    if ($markerPos !== false) {
        return ltrim(substr($absolutePath, $markerPos), "/");
    }
    if ($projectRoot) {
        $rootNorm = str_replace("\\", "/", $projectRoot);
        if (strpos($absolutePath, $rootNorm) === 0) {
            return ltrim(substr($absolutePath, strlen($rootNorm)), "/");
        }
    }
    return ltrim($absolutePath, "/");
}

function oi_sanitize_doc_type_token(string $docType): string
{
    $token = preg_replace('/[^A-Za-z0-9]+/', '', $docType);
    return $token !== '' ? $token : 'Document';
}

function oi_move_uploaded_file_with_doc_name(string $tmpName, string $dir, string $docType, string $userId, string $ext): array
{
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid upload source.');
    }

    $tmpSize = @filesize($tmpName);
    if ($tmpSize === false || (int)$tmpSize <= 0) {
        throw new RuntimeException('Uploaded file is empty.');
    }

    $index = 0;
    $ext = strtolower($ext);
    do {
        $base = oi_sanitize_doc_type_token($docType) . $userId . ($index > 0 ? "_{$index}" : '');
        $fileName = $base . '.' . $ext;
        $target = rtrim($dir, "/") . "/" . $fileName;
        $index++;
    } while (file_exists($target));

    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Failed to upload file.');
    }

    $movedSize = @filesize($target);
    if ($movedSize === false || (int)$movedSize <= 0) {
        @unlink($target);
        throw new RuntimeException('Uploaded file is empty.');
    }

    return [
        'file_name' => $fileName,
        'file_path' => oi_to_db_web_path($target),
        'disk_path' => $target,
    ];
}

function oi_has_uploaded_2x2(mysqli $conn, string $userId): bool
{
    $stmt = $conn->prepare("
        SELECT uf.attachment_id
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON dt.document_type_id = uf.document_type_id
        WHERE uf.source_type = 'OFFICIAL_PROFILE'
          AND uf.source_id = ?
          AND LOWER(dt.document_type_name) = LOWER('2x2 Picture')
          AND dt.document_category = 'OfficialProfiling'
        ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

$inviteToken = trim((string)($_GET['invite'] ?? ''));
$tokenInvite = $inviteToken !== '' ? oi_find_invite_by_token($conn, $inviteToken) : null;

$loggedUserId = (string)($_SESSION['user_id'] ?? '');
$loggedRole = (string)($_SESSION['role'] ?? '');
$sessionInvite = null;
$account = null;
$officialInfo = null;

if ($loggedUserId !== '' && in_array($loggedRole, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)) {
    $sessionInvite = oi_find_active_invite_by_user($conn, $loggedUserId);
    $account = oi_get_account($conn, $loggedUserId);
    $officialInfo = oi_get_official_info($conn, $loggedUserId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'set_password') {
        $rawToken = trim((string)($_POST['invite_token'] ?? ''));
        $invite = oi_find_invite_by_token($conn, $rawToken);
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!$invite) {
            $errors[] = 'Invite is invalid, expired, or already used.';
        } elseif (!empty($invite['token_used_at'])) {
            $errors[] = 'This invite link was already used. Please login to continue onboarding.';
        } elseif ($password === '' || $confirmPassword === '') {
            $errors[] = 'Password and confirmation are required.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
            $errors[] = 'Password must be at least 8 chars with uppercase, lowercase, number, and special character.';
        }

        if (empty($errors) && $invite) {
            $email = (string)$invite['invite_email'];
            $phone10 = oi_normalize_phone10((string)$invite['invite_phone']);
            $roleAccess = in_array((string)$invite['role_access'], ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)
                ? (string)$invite['role_access']
                : 'Official';

            $dup = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE email = ? OR phone_number = ? LIMIT 1");
            if ($dup) {
                $dup->bind_param("ss", $email, $phone10);
                $dup->execute();
                $hit = $dup->get_result()->fetch_assoc();
                $dup->close();
                if ($hit) {
                    $errors[] = 'Email or phone already exists in another account.';
                }
            }

            if (empty($errors)) {
                $activeStatusId = oi_get_or_create_status_id($conn, 'Active', ['UserAccount']);
                if ($activeStatusId === null) {
                    $errors[] = 'UserAccount Active status is missing.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $userId = GenerateUserID($conn, $roleAccess);
                        if (!$userId) {
                            throw new RuntimeException('Failed to generate user ID.');
                        }

                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $conn->prepare("
                            INSERT INTO useraccountstbl
                                (user_id, phone_number, phoneNum_verify, email, email_verify, password_hash, role_access, account_created, status_id_account)
                            VALUES
                                (?, ?, 0, ?, 0, ?, ?, NOW(), ?)
                        ");
                        if (!$ins) throw new RuntimeException('Failed to prepare account creation.');
                        $ins->bind_param("sssssi", $userId, $phone10, $email, $passwordHash, $roleAccess, $activeStatusId);
                        if (!$ins->execute()) {
                            $ins->close();
                            throw new RuntimeException('Failed to create account.');
                        }
                        $ins->close();

                        $up = $conn->prepare("
                            UPDATE officialinvitetbl
                            SET user_id = ?, token_used_at = NOW(), password_set_at = NOW(), onboarding_step = 'email_verify', status = 'InProgress'
                            WHERE invite_id = ?
                            LIMIT 1
                        ");
                        if (!$up) throw new RuntimeException('Failed to update invite.');
                        $inviteId = (int)$invite['invite_id'];
                        $up->bind_param("si", $userId, $inviteId);
                        $up->execute();
                        $up->close();

                        $conn->commit();
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['role'] = $roleAccess;
                        $_SESSION['logged_in'] = true;
                        $_SESSION['last_activity'] = time();
                        header('Location: official_onboarding.php');
                        exit;
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $errors[] = 'Failed to create account. Please try again.';
                    }
                }
            }
        }
    } elseif ($loggedUserId !== '' && $sessionInvite && $account) {
        if ($action === 'send_email_otp') {
            try {
                $otp = oi_generate_otp();
                oi_insert_otp($conn, $loggedUserId, (string)$account['email'], 'official_onboard_email', $otp, 5);
                if (!oi_send_email_otp((string)$account['email'], $otp)) {
                    throw new RuntimeException('Unable to send email OTP right now.');
                }
                $success = 'Email OTP sent.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'verify_email_otp') {
            try {
                $otp = trim((string)($_POST['email_otp'] ?? ''));
                oi_verify_latest_otp($conn, $loggedUserId, (string)$account['email'], 'official_onboard_email', $otp);
                $upUser = $conn->prepare("UPDATE useraccountstbl SET email_verify = 1 WHERE user_id = ? LIMIT 1");
                if ($upUser) {
                    $upUser->bind_param("s", $loggedUserId);
                    $upUser->execute();
                    $upUser->close();
                }
                $upInvite = $conn->prepare("UPDATE officialinvitetbl SET email_verified_at = NOW(), onboarding_step = 'phone_verify' WHERE invite_id = ? LIMIT 1");
                if ($upInvite) {
                    $inviteId = (int)$sessionInvite['invite_id'];
                    $upInvite->bind_param("i", $inviteId);
                    $upInvite->execute();
                    $upInvite->close();
                }
                $success = 'Email verified.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'send_phone_otp') {
            try {
                $phone10 = oi_normalize_phone10((string)$account['phone_number']);
                if (!oi_is_valid_phone10($phone10)) {
                    throw new RuntimeException('Invalid mobile number on account.');
                }
                $otp = oi_generate_otp();
                oi_insert_otp($conn, $loggedUserId, $phone10, 'official_onboard_phone', $otp, 5);
                if (!sendSMS('0' . $phone10, "Your Barangay San Jose onboarding OTP is {$otp}")) {
                    throw new RuntimeException('Unable to send SMS OTP right now.');
                }
                $success = 'SMS OTP sent.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'verify_phone_otp') {
            try {
                $phone10 = oi_normalize_phone10((string)$account['phone_number']);
                $otp = trim((string)($_POST['phone_otp'] ?? ''));
                oi_verify_latest_otp($conn, $loggedUserId, $phone10, 'official_onboard_phone', $otp);
                $upUser = $conn->prepare("UPDATE useraccountstbl SET phoneNum_verify = 1 WHERE user_id = ? LIMIT 1");
                if ($upUser) {
                    $upUser->bind_param("s", $loggedUserId);
                    $upUser->execute();
                    $upUser->close();
                }
                $upInvite = $conn->prepare("UPDATE officialinvitetbl SET phone_verified_at = NOW(), onboarding_step = 'official_info' WHERE invite_id = ? LIMIT 1");
                if ($upInvite) {
                    $inviteId = (int)$sessionInvite['invite_id'];
                    $upInvite->bind_param("i", $inviteId);
                    $upInvite->execute();
                    $upInvite->close();
                }
                $success = 'Mobile number verified.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'save_official_info') {
            try {
                $account = oi_get_account($conn, $loggedUserId);
                if (!$account || (int)$account['email_verify'] !== 1 || (int)$account['phoneNum_verify'] !== 1) {
                    throw new RuntimeException('Complete email and mobile verification first.');
                }

                $birthdate = trim((string)($_POST['birthdate'] ?? ''));
                $sex = trim((string)($_POST['sex'] ?? ''));
                $civil = trim((string)($_POST['civil_status'] ?? ''));
                $emergencyName = trim((string)($_POST['emergency_contact_name'] ?? ''));
                $emergencyRelationship = trim((string)($_POST['emergency_contact_relationship'] ?? ''));
                $emergencyPhone = oi_normalize_phone10((string)($_POST['emergency_contact_phone'] ?? ''));
                $emergencyAddress = trim((string)($_POST['emergency_contact_address'] ?? ''));

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
                    throw new RuntimeException('Birthdate is required.');
                }
                if (!in_array($sex, ['Male', 'Female', 'Other'], true)) {
                    throw new RuntimeException('Select a valid sex.');
                }
                if (!in_array($civil, ['Single', 'Married', 'Widowed', 'Separated'], true)) {
                    throw new RuntimeException('Select a valid civil status.');
                }
                if ($emergencyName === '' || $emergencyRelationship === '' || $emergencyAddress === '') {
                    throw new RuntimeException('All emergency contact fields are required.');
                }
                if (!oi_is_valid_phone10($emergencyPhone)) {
                    throw new RuntimeException('Emergency contact number must be 9XXXXXXXXX.');
                }

                $employmentStatusName = trim((string)($sessionInvite['employment_status'] ?? ''));
                if ($employmentStatusName === '') {
                    $employmentStatusName = 'Regular';
                }
                $employmentStatusId = oi_get_status_id($conn, $employmentStatusName, ['Official/Personnel Management', 'Employment', 'OfficialEmployment', 'UserAccount']);
                if ($employmentStatusId === null) {
                    $employmentStatusId = oi_get_status_id($conn, 'Active', ['Official/Personnel Management', 'Employment', 'OfficialEmployment', 'UserAccount']);
                }
                if ($employmentStatusId === null) {
                    throw new RuntimeException('Employment status is missing in status lookups.');
                }

                $inviteId = (int)$sessionInvite['invite_id'];
                $firstname = (string)$sessionInvite['firstname'];
                $middlename = (string)$sessionInvite['middlename'];
                $lastname = (string)$sessionInvite['lastname'];
                $suffix = (string)$sessionInvite['suffix'];
                $roleAccess = (string)$sessionInvite['role_access'];
                $positionAccess = trim((string)($sessionInvite['position_access'] ?? ''));
                $department = (string)$sessionInvite['department'];
                $areaNumber = trim((string)($sessionInvite['area_number'] ?? ''));
                $phone10 = oi_normalize_phone10((string)$account['phone_number']);
                $email = (string)$account['email'];
                if ($positionAccess === '') {
                    $positionAccess = $roleAccess;
                }

                $stmt = $conn->prepare("
                    INSERT INTO officialinformationtbl
                        (user_id, lastname, firstname, middlename, suffix, birthdate, sex, civil_status, contact_number, email, role_access, position_access, department, area_number, status_id_employment, date_hired, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_address)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        lastname = VALUES(lastname),
                        firstname = VALUES(firstname),
                        middlename = VALUES(middlename),
                        suffix = VALUES(suffix),
                        birthdate = VALUES(birthdate),
                        sex = VALUES(sex),
                        civil_status = VALUES(civil_status),
                        contact_number = VALUES(contact_number),
                        email = VALUES(email),
                        role_access = VALUES(role_access),
                        position_access = VALUES(position_access),
                        department = VALUES(department),
                        area_number = VALUES(area_number),
                        status_id_employment = VALUES(status_id_employment),
                        emergency_contact_name = VALUES(emergency_contact_name),
                        emergency_contact_relationship = VALUES(emergency_contact_relationship),
                        emergency_contact_phone = VALUES(emergency_contact_phone),
                        emergency_contact_address = VALUES(emergency_contact_address),
                        last_updated = CURRENT_TIMESTAMP
                ");
                if (!$stmt) {
                    throw new RuntimeException('Failed to save official profile.');
                }
                $stmt->bind_param(
                    "ssssssssssssssissss",
                    $loggedUserId,
                    $lastname,
                    $firstname,
                    $middlename,
                    $suffix,
                    $birthdate,
                    $sex,
                    $civil,
                    $phone10,
                    $email,
                    $roleAccess,
                    $positionAccess,
                    $department,
                    $areaNumber,
                    $employmentStatusId,
                    $emergencyName,
                    $emergencyRelationship,
                    $emergencyPhone,
                    $emergencyAddress
                );
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Failed to save official profile.');
                }
                $stmt->close();

                $upInvite = $conn->prepare("
                    UPDATE officialinvitetbl
                    SET onboarding_step = 'address_info',
                        status = 'InProgress'
                    WHERE invite_id = ?
                    LIMIT 1
                ");
                if ($upInvite) {
                    $upInvite->bind_param("i", $inviteId);
                    $upInvite->execute();
                    $upInvite->close();
                }
                $success = 'Information and emergency contact saved. Continue with address.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'save_official_address') {
            try {
                if (!oi_official_info_exists($conn, $loggedUserId)) {
                    throw new RuntimeException('Complete information and emergency contact first.');
                }
                $addressMode = strtolower(trim((string)($_POST['address_mode'] ?? 'street')));
                if (!in_array($addressMode, ['street', 'block_lot'], true)) {
                    $addressMode = 'street';
                }
                $houseNumber = trim((string)($_POST['house_number'] ?? ''));
                $streetName = trim((string)($_POST['street_name'] ?? ''));
                $blockNumber = trim((string)($_POST['block_number'] ?? ''));
                $lotNumber = trim((string)($_POST['lot_number'] ?? ''));
                $barangay = trim((string)($_POST['barangay'] ?? ''));
                $municipalityCity = trim((string)($_POST['municipality_city'] ?? ''));
                $province = trim((string)($_POST['province'] ?? ''));
                $missingCore = ($barangay === '' || $municipalityCity === '' || $province === '');
                $missingStreet = ($addressMode === 'street' && ($houseNumber === '' || $streetName === ''));
                $missingBlockLot = ($addressMode === 'block_lot' && ($blockNumber === '' || $lotNumber === ''));
                if ($missingCore || $missingStreet || $missingBlockLot) {
                    throw new RuntimeException('All address fields are required.');
                }
                if ($addressMode === 'street') {
                    $blockNumber = '';
                    $lotNumber = '';
                } else {
                    $houseNumber = '';
                    $streetName = '';
                }

                $stmt = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET address_mode = ?,
                        house_number = ?,
                        street_name = ?,
                        block_number = ?,
                        lot_number = ?,
                        barangay = ?,
                        municipality_city = ?,
                        province = ?,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE user_id = ?
                    LIMIT 1
                ");
                if (!$stmt) {
                    throw new RuntimeException('Failed to save address.');
                }
                $stmt->bind_param(
                    "sssssssss",
                    $addressMode,
                    $houseNumber,
                    $streetName,
                    $blockNumber,
                    $lotNumber,
                    $barangay,
                    $municipalityCity,
                    $province,
                    $loggedUserId
                );
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Failed to save address.');
                }
                $stmt->close();

                $inviteId = (int)$sessionInvite['invite_id'];
                $upInvite = $conn->prepare("
                    UPDATE officialinvitetbl
                    SET onboarding_step = 'document_upload',
                        status = 'InProgress'
                    WHERE invite_id = ?
                    LIMIT 1
                ");
                if ($upInvite) {
                    $upInvite->bind_param("i", $inviteId);
                    $upInvite->execute();
                    $upInvite->close();
                }
                $success = 'Address saved. Upload your required 2x2 picture to complete onboarding.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'upload_official_2x2') {
            try {
                if (!oi_official_info_exists($conn, $loggedUserId)) {
                    throw new RuntimeException('Complete your information first.');
                }
                if (!oi_has_address(oi_get_official_info($conn, $loggedUserId))) {
                    throw new RuntimeException('Complete your address first.');
                }
                if (!isset($_FILES['official_2x2']) || !is_array($_FILES['official_2x2'])) {
                    throw new RuntimeException('2x2 picture is required.');
                }

                $fileErr = (int)($_FILES['official_2x2']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($fileErr !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('2x2 picture is required.');
                }
                $tmpName = (string)($_FILES['official_2x2']['tmp_name'] ?? '');
                $origName = (string)($_FILES['official_2x2']['name'] ?? '');
                $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed, true)) {
                    throw new RuntimeException('Invalid file type for 2x2 picture. Allowed: JPG, JPEG, PNG, WEBP.');
                }

                $uploadDir = __DIR__ . "/../UnifiedFileAttachment/Documents/{$loggedUserId}/";
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                    throw new RuntimeException('Failed to create upload directory.');
                }
                $moved = oi_move_uploaded_file_with_doc_name($tmpName, $uploadDir, '2x2 Picture', $loggedUserId, $ext);
                $docTypeId = oi_get_document_type_id($conn, '2x2 Picture', 'OfficialProfiling');
                $statusVerifyId = oi_resolve_pending_review_status_id($conn);
                if ($statusVerifyId === null) {
                    throw new RuntimeException('Pending review status is missing.');
                }

                $sourceType = 'OFFICIAL_PROFILE';
                $sourceId = $loggedUserId;
                $remarks = 'Official onboarding 2x2';
                $idNumber = null;

                $ins = $conn->prepare("
                    INSERT INTO unifiedfileattachmenttbl
                        (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$ins) {
                    throw new RuntimeException('Failed to prepare document upload save.');
                }
                $ins->bind_param(
                    "ssissssiss",
                    $sourceType,
                    $sourceId,
                    $docTypeId,
                    $moved['file_name'],
                    $moved['file_path'],
                    $ext,
                    $loggedUserId,
                    $statusVerifyId,
                    $remarks,
                    $idNumber
                );
                if (!$ins->execute()) {
                    $ins->close();
                    throw new RuntimeException('Failed to save uploaded 2x2 picture.');
                }
                $ins->close();

                $inviteId = (int)$sessionInvite['invite_id'];
                $upInvite = $conn->prepare("
                    UPDATE officialinvitetbl
                    SET profile_completed_at = NOW(),
                        accepted_at = NOW(),
                        onboarding_step = 'completed',
                        status = 'Completed'
                    WHERE invite_id = ?
                    LIMIT 1
                ");
                if ($upInvite) {
                    $upInvite->bind_param("i", $inviteId);
                    $upInvite->execute();
                    $upInvite->close();
                }

                header('Location: ../PhpFiles/Login/unifiedProfileCheck.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // refresh state after POST
    $loggedUserId = (string)($_SESSION['user_id'] ?? '');
    $loggedRole = (string)($_SESSION['role'] ?? '');
    if ($loggedUserId !== '' && in_array($loggedRole, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)) {
        $sessionInvite = oi_find_active_invite_by_user($conn, $loggedUserId);
        $account = oi_get_account($conn, $loggedUserId);
        $officialInfo = oi_get_official_info($conn, $loggedUserId);
    }
}

$mode = 'invalid';
if ($loggedUserId !== '' && $sessionInvite && $account && in_array($loggedRole, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)) {
    $mode = 'resume';
} elseif ($tokenInvite && empty($tokenInvite['token_used_at']) && empty($tokenInvite['user_id'])) {
    $mode = 'password';
}

$resumeStep = 'email_verify';
if ($mode === 'resume') {
    if ((int)($account['email_verify'] ?? 0) !== 1) {
        $resumeStep = 'email_verify';
    } elseif ((int)($account['phoneNum_verify'] ?? 0) !== 1) {
        $resumeStep = 'phone_verify';
    } elseif (!oi_has_info_and_contact($officialInfo)) {
        $resumeStep = 'official_info';
    } elseif (!oi_has_address($officialInfo)) {
        $resumeStep = 'address_info';
    } elseif (!oi_has_uploaded_2x2($conn, $loggedUserId)) {
        $resumeStep = 'document_upload';
    } else {
        header('Location: ../PhpFiles/Login/unifiedProfileCheck.php');
        exit;
    }
}

$onboardingStep = 0;
if ($mode === 'password') {
    $onboardingStep = 1;
} elseif ($mode === 'resume') {
    if ($resumeStep === 'email_verify') {
        $onboardingStep = 2;
    } elseif ($resumeStep === 'phone_verify') {
        $onboardingStep = 3;
    } elseif ($resumeStep === 'official_info') {
        $onboardingStep = 4;
    } elseif ($resumeStep === 'address_info') {
        $onboardingStep = 5;
    } elseif ($resumeStep === 'document_upload') {
        $onboardingStep = 6;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official Account Onboarding</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/registrationStyle.css?v=20260213-6">
    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css">
    <style>
        body {
            background-color: #F8F9FA;
        }
        .onboarding-shell {
            max-width: 860px;
        }
        .onboarding-title {
            font-size: 48px;
            text-align: center;
            font-family: 'Charis SIL Bold', serif;
            margin-bottom: 0.5rem;
        }
        .onboarding-subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        .password-reqs.is-hidden { display: none; }
        .password-reqs-list { margin: 0; padding-left: 1.1rem; }
        .password-reqs-list li { color: #6c757d; }
        .password-reqs-list li.req-valid { color: #198754; }
        .password-reqs-list li.req-invalid { color: #dc3545; }
        .progress-steps li.is-completed::before {
            content: "\2713";
            background-color: #198754;
            color: #fff;
            border-color: #198754;
            font-weight: 700;
            line-height: 1;
            font-size: 0.9rem;
        }
        .verification-wrap {
            max-width: 760px;
            margin: 0 auto;
        }
        .verification-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        }
        .verification-contact-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .verification-contact-label {
            color: #475467;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
        }
        .verification-contact-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
            word-break: break-word;
        }
        .otp-verify-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.75rem;
            align-items: end;
        }
        .otp-input-group label {
            font-weight: 600;
            margin-bottom: 0.4rem;
        }
        .otp-input-group input {
            min-height: 52px;
            font-size: 1.1rem;
        }
        .otp-submit-btn {
            min-height: 52px;
            padding-inline: 1.25rem;
            white-space: nowrap;
        }
        .otp-send-btn {
            min-height: 46px;
            white-space: nowrap;
        }
        @media (max-width: 767.98px) {
            .otp-verify-row {
                grid-template-columns: 1fr;
            }
            .otp-submit-btn,
            .otp-send-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-light">
<div class="navbarWrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid px-4">
            <a id="navbarBrand" class="navbar-brand" href="#">
                <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center" />
                Barangay San Jose
            </a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul id="navbarLinks" class="navbar-nav ms-auto">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link logout-link" href="/BarangaySanJose/PhpFiles/Login/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/BarangaySanJose/Guest-End/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</div>

<section class="container my-5 onboarding-shell">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
            <div id="progressHeader" class="text-center mb-5">
                <h1 class="onboarding-title">Official Account Onboarding</h1>
                <p class="onboarding-subtitle">Complete your onboarding requirements to access the system.</p>
                <?php if ($onboardingStep > 0): ?>
                    <div id="progressContainer" class="my-4">
                        <ol class="progress-steps">
                            <li class="<?= $onboardingStep === 1 ? 'active' : ($onboardingStep > 1 ? 'is-completed' : '') ?>">Create Password</li>
                            <li class="<?= $onboardingStep === 2 ? 'active' : ($onboardingStep > 2 ? 'is-completed' : '') ?>">Verify Email</li>
                            <li class="<?= $onboardingStep === 3 ? 'active' : ($onboardingStep > 3 ? 'is-completed' : '') ?>">Verify Phone Number</li>
                            <li class="<?= $onboardingStep === 4 ? 'active' : ($onboardingStep > 4 ? 'is-completed' : '') ?>">Information and Contact</li>
                            <li class="<?= $onboardingStep === 5 ? 'active' : ($onboardingStep > 5 ? 'is-completed' : '') ?>">Address</li>
                            <li class="<?= $onboardingStep === 6 ? 'active' : '' ?>">Upload 2x2 Picture</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($mode === 'password'): ?>
                <p class="text-muted mb-3">Set your password to activate your account onboarding.</p>
                <div class="alert alert-warning">
                    <strong>STRICTLY ONE-TIME ACCESS:</strong> This invite link can only be used once. After saving your password, this link is no longer usable.
                </div>
                <form method="post" class="row g-3" id="onboardingPasswordForm">
                    <input type="hidden" name="action" value="set_password">
                    <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="onboardPassword" name="password" required>
                            <span class="input-group-text" id="toggleOnboardPassword" role="button" aria-label="Show or hide password">
                                <i class="bi bi-eye" id="onboardPasswordEye"></i>
                            </span>
                        </div>
                    </div>
                    <div id="onboardPasswordRequirements" class="password-reqs is-hidden">
                        <div class="password-reqs-title fw-semibold small mb-1">Password must contain:</div>
                        <ul class="password-reqs-list small">
                            <li data-req="uppercase">1 uppercase letter</li>
                            <li data-req="lowercase">1 lowercase letter</li>
                            <li data-req="number">1 number</li>
                            <li data-req="special">1 special character</li>
                            <li data-req="length">At least 8 characters</li>
                        </ul>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="onboardConfirmPassword" name="confirm_password" required>
                            <span class="input-group-text" id="toggleOnboardConfirmPassword" role="button" aria-label="Show or hide confirm password">
                                <i class="bi bi-eye" id="onboardConfirmPasswordEye"></i>
                            </span>
                        </div>
                        <div id="onboardPasswordMatchMsg" class="small mt-1 text-muted"></div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" id="onboardSavePasswordBtn" type="submit">Save Password</button>
                    </div>
                </form>
            <?php elseif ($mode === 'resume' && $resumeStep === 'email_verify'): ?>
                <div class="verification-wrap">
                    <p class="text-muted mb-3">Verify your email first.</p>
                    <div class="verification-card">
                        <div class="verification-contact-row">
                            <div>
                                <div class="verification-contact-label">Email address</div>
                                <div class="verification-contact-value"><?= htmlspecialchars((string)$account['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="send_email_otp">
                                <button class="btn btn-outline-primary otp-send-btn" type="submit">Send Email OTP</button>
                            </form>
                        </div>
                        <form method="post" class="otp-verify-row">
                            <input type="hidden" name="action" value="verify_email_otp">
                            <div class="otp-input-group">
                                <label for="emailOtpInput" class="form-label">Verification code</label>
                                <input id="emailOtpInput" class="form-control" name="email_otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
                            </div>
                            <button class="btn btn-success otp-submit-btn" type="submit">Verify Email</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($mode === 'resume' && $resumeStep === 'phone_verify'): ?>
                <div class="verification-wrap">
                    <p class="text-muted mb-3">Verify your mobile number.</p>
                    <div class="verification-card">
                        <div class="verification-contact-row">
                            <div>
                                <div class="verification-contact-label">Mobile number</div>
                                <div class="verification-contact-value">+63<?= htmlspecialchars((string)oi_normalize_phone10((string)$account['phone_number']), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="send_phone_otp">
                                <button class="btn btn-outline-primary otp-send-btn" type="submit">Send SMS OTP</button>
                            </form>
                        </div>
                        <form method="post" class="otp-verify-row">
                            <input type="hidden" name="action" value="verify_phone_otp">
                            <div class="otp-input-group">
                                <label for="phoneOtpInput" class="form-label">Verification code</label>
                                <input id="phoneOtpInput" class="form-control" name="phone_otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
                            </div>
                            <button class="btn btn-success otp-submit-btn" type="submit">Verify Mobile</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($mode === 'resume' && $resumeStep === 'official_info'): ?>
                <p class="text-muted">Complete your information and emergency contact.</p>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="save_official_info">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['firstname'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['lastname'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['middlename'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Suffix</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['suffix'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Position Access</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)($sessionInvite['position_access'] ?? $sessionInvite['role_access'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Department</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['department'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Area Number</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)(trim((string)($sessionInvite['area_number'] ?? '')) !== '' ? $sessionInvite['area_number'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Employment Status</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)($sessionInvite['employment_status'] ?? 'Regular'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Birthdate</label>
                        <input type="date" class="form-control" name="birthdate" value="<?= htmlspecialchars((string)($officialInfo['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sex</label>
                        <select class="form-select" name="sex" required>
                            <option value="">Select</option>
                            <option value="Male" <?= (($officialInfo['sex'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($officialInfo['sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= (($officialInfo['sex'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Civil Status</label>
                        <select class="form-select" name="civil_status" required>
                            <option value="">Select</option>
                            <option value="Single" <?= (($officialInfo['civil_status'] ?? '') === 'Single') ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= (($officialInfo['civil_status'] ?? '') === 'Married') ? 'selected' : '' ?>>Married</option>
                            <option value="Widowed" <?= (($officialInfo['civil_status'] ?? '') === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
                            <option value="Separated" <?= (($officialInfo['civil_status'] ?? '') === 'Separated') ? 'selected' : '' ?>>Separated</option>
                        </select>
                    </div>
                    <div class="col-12 pt-2">
                        <h5 class="mb-2">Emergency Contact</h5>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact Name</label>
                        <input class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars((string)($officialInfo['emergency_contact_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Relationship</label>
                        <input class="form-control" name="emergency_contact_relationship" value="<?= htmlspecialchars((string)($officialInfo['emergency_contact_relationship'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact Number (+63)</label>
                        <input class="form-control" name="emergency_contact_phone" inputmode="numeric" maxlength="10" placeholder="9XXXXXXXXX" value="<?= htmlspecialchars((string)($officialInfo['emergency_contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact Address</label>
                        <input class="form-control" name="emergency_contact_address" value="<?= htmlspecialchars((string)($officialInfo['emergency_contact_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save and Continue</button>
                    </div>
                </form>
            <?php elseif ($mode === 'resume' && $resumeStep === 'address_info'): ?>
                <p class="text-muted">Complete your address.</p>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="save_official_address">
                    <?php $addressMode = strtolower(trim((string)($officialInfo['address_mode'] ?? 'street'))); ?>
                    <div class="col-md-6">
                        <label class="form-label">Address System</label>
                        <select class="form-select" name="address_mode" id="officialAddressMode" required>
                            <option value="street" <?= $addressMode === 'block_lot' ? '' : 'selected' ?>>Street System</option>
                            <option value="block_lot" <?= $addressMode === 'block_lot' ? 'selected' : '' ?>>Block / Lot System</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House Number</label>
                        <input class="form-control" id="officialHouseNumber" name="house_number" value="<?= htmlspecialchars((string)($officialInfo['house_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Street Name</label>
                        <input class="form-control" id="officialStreetName" name="street_name" value="<?= htmlspecialchars((string)($officialInfo['street_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6 d-none" id="officialBlockWrap">
                        <label class="form-label">Block Number</label>
                        <input class="form-control" id="officialBlockNumber" name="block_number" value="<?= htmlspecialchars((string)($officialInfo['block_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6 d-none" id="officialLotWrap">
                        <label class="form-label">Lot Number</label>
                        <input class="form-control" id="officialLotNumber" name="lot_number" value="<?= htmlspecialchars((string)($officialInfo['lot_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Barangay</label>
                        <input class="form-control" name="barangay" value="<?= htmlspecialchars((string)($officialInfo['barangay'] ?? 'Barangay San Jose'), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Municipality / City</label>
                        <input class="form-control" name="municipality_city" value="<?= htmlspecialchars((string)($officialInfo['municipality_city'] ?? 'Rodriguez (Montalban)'), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Province</label>
                        <input class="form-control" name="province" value="<?= htmlspecialchars((string)($officialInfo['province'] ?? 'Rizal'), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save and Continue</button>
                    </div>
                </form>
            <?php elseif ($mode === 'resume' && $resumeStep === 'document_upload'): ?>
                <p class="text-muted">Upload your required 2x2 picture to complete onboarding.</p>
                <form method="post" class="row g-3" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_official_2x2">
                    <div class="col-12">
                        <label class="form-label">2x2 Picture <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="official_2x2" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        <div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Upload and Submit</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger mb-0">
                    Invite link is invalid or already used. If you already saved your password, login using your account to resume onboarding.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const modeEl = document.getElementById("officialAddressMode");
  const houseEl = document.getElementById("officialHouseNumber");
  const streetEl = document.getElementById("officialStreetName");
  const blockWrap = document.getElementById("officialBlockWrap");
  const lotWrap = document.getElementById("officialLotWrap");
  const blockEl = document.getElementById("officialBlockNumber");
  const lotEl = document.getElementById("officialLotNumber");
  if (!modeEl) return;

  const syncAddressMode = () => {
    const mode = String(modeEl.value || "street").toLowerCase();
    const isBlockLot = mode === "block_lot";
    if (blockWrap) blockWrap.classList.toggle("d-none", !isBlockLot);
    if (lotWrap) lotWrap.classList.toggle("d-none", !isBlockLot);
    if (houseEl) houseEl.required = !isBlockLot;
    if (streetEl) streetEl.required = !isBlockLot;
    if (blockEl) blockEl.required = isBlockLot;
    if (lotEl) lotEl.required = isBlockLot;
  };

  modeEl.addEventListener("change", syncAddressMode);
  syncAddressMode();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const form = document.getElementById('onboardingPasswordForm');
    if (!form) return;

    const passwordInput = document.getElementById('onboardPassword');
    const confirmInput = document.getElementById('onboardConfirmPassword');
    const reqWrap = document.getElementById('onboardPasswordRequirements');
    const matchMsg = document.getElementById('onboardPasswordMatchMsg');
    const saveBtn = document.getElementById('onboardSavePasswordBtn');
    const reqItems = {
        uppercase: reqWrap?.querySelector('[data-req="uppercase"]'),
        lowercase: reqWrap?.querySelector('[data-req="lowercase"]'),
        number: reqWrap?.querySelector('[data-req="number"]'),
        special: reqWrap?.querySelector('[data-req="special"]'),
        length: reqWrap?.querySelector('[data-req="length"]'),
    };

    const setReqState = (el, ok) => {
        if (!el) return;
        el.classList.remove('req-valid', 'req-invalid');
        if (ok === null) return;
        el.classList.add(ok ? 'req-valid' : 'req-invalid');
    };

    const updateRequirements = (password) => {
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[\W_]/.test(password);
        const hasLength = password.length >= 8;
        const isEmpty = password.length === 0;
        setReqState(reqItems.uppercase, isEmpty ? null : hasUpper);
        setReqState(reqItems.lowercase, isEmpty ? null : hasLower);
        setReqState(reqItems.number, isEmpty ? null : hasNumber);
        setReqState(reqItems.special, isEmpty ? null : hasSpecial);
        setReqState(reqItems.length, isEmpty ? null : hasLength);
        return hasUpper && hasLower && hasNumber && hasSpecial && hasLength;
    };

    const refresh = () => {
        const password = String(passwordInput?.value || '');
        const confirm = String(confirmInput?.value || '');
        const isStrong = updateRequirements(password);
        const showReq = password.length > 0 || document.activeElement === passwordInput;
        reqWrap?.classList.toggle('is-hidden', !showReq);

        const isMatch = confirm.length > 0 && password === confirm;
        if (!confirm.length) {
            if (matchMsg) {
                matchMsg.className = 'small mt-1 text-muted';
                matchMsg.textContent = '';
            }
        } else if (isMatch) {
            if (matchMsg) {
                matchMsg.className = 'small mt-1 text-success';
                matchMsg.textContent = 'Passwords match.';
            }
        } else {
            if (matchMsg) {
                matchMsg.className = 'small mt-1 text-danger';
                matchMsg.textContent = 'Passwords do not match.';
            }
        }

        if (saveBtn) {
            saveBtn.disabled = !(isStrong && isMatch);
        }
    };

    const wireToggle = (btnId, inputId, eyeId) => {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        if (!btn || !input || !eye) return;
        btn.addEventListener('click', () => {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            eye.classList.toggle('bi-eye', !hidden);
            eye.classList.toggle('bi-eye-slash', hidden);
        });
    };

    passwordInput?.addEventListener('focus', refresh);
    passwordInput?.addEventListener('blur', refresh);
    passwordInput?.addEventListener('input', refresh);
    confirmInput?.addEventListener('input', refresh);
    confirmInput?.addEventListener('blur', refresh);

    form.addEventListener('submit', (e) => {
        refresh();
        if (saveBtn?.disabled) {
            e.preventDefault();
        }
    });

    wireToggle('toggleOnboardPassword', 'onboardPassword', 'onboardPasswordEye');
    wireToggle('toggleOnboardConfirmPassword', 'onboardConfirmPassword', 'onboardConfirmPasswordEye');
    refresh();
})();
</script>
</body>
</html>
