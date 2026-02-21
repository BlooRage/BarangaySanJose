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

$inviteToken = trim((string)($_GET['invite'] ?? ''));
$tokenInvite = $inviteToken !== '' ? oi_find_invite_by_token($conn, $inviteToken) : null;

$loggedUserId = (string)($_SESSION['user_id'] ?? '');
$loggedRole = (string)($_SESSION['role'] ?? '');
$sessionInvite = null;
$account = null;

if ($loggedUserId !== '' && in_array($loggedRole, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)) {
    $sessionInvite = oi_find_active_invite_by_user($conn, $loggedUserId);
    $account = oi_get_account($conn, $loggedUserId);
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
                $activeStatusId = oi_get_status_id($conn, 'Active', ['UserAccount']);
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
                        $_SESSION['hard_expire_at'] = time() + 1800;
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

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
                    throw new RuntimeException('Birthdate is required.');
                }
                if (!in_array($sex, ['Male', 'Female', 'Other'], true)) {
                    throw new RuntimeException('Select a valid sex.');
                }
                if (!in_array($civil, ['Single', 'Married', 'Widowed', 'Separated'], true)) {
                    throw new RuntimeException('Select a valid civil status.');
                }

                $employmentStatusId = oi_get_status_id($conn, 'Active', ['Employment', 'OfficialEmployment', 'UserAccount']);
                if ($employmentStatusId === null) {
                    throw new RuntimeException('Active employment status is missing.');
                }

                $inviteId = (int)$sessionInvite['invite_id'];
                $firstname = (string)$sessionInvite['firstname'];
                $middlename = (string)$sessionInvite['middlename'];
                $lastname = (string)$sessionInvite['lastname'];
                $suffix = (string)$sessionInvite['suffix'];
                $roleAccess = (string)$sessionInvite['role_access'];
                $department = (string)$sessionInvite['department'];
                $phone10 = oi_normalize_phone10((string)$account['phone_number']);
                $email = (string)$account['email'];

                $stmt = $conn->prepare("
                    INSERT INTO officialinformationtbl
                        (user_id, lastname, firstname, middlename, suffix, birthdate, sex, civil_status, contact_number, email, role_access, department, status_id_employment, date_hired)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
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
                        department = VALUES(department),
                        status_id_employment = VALUES(status_id_employment),
                        last_updated = CURRENT_TIMESTAMP
                ");
                if (!$stmt) {
                    throw new RuntimeException('Failed to save official profile.');
                }
                $stmt->bind_param(
                    "ssssssssssssi",
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
                    $department,
                    $employmentStatusId
                );
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Failed to save official profile.');
                }
                $stmt->close();

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
    } elseif (!oi_official_info_exists($conn, $loggedUserId)) {
        $resumeStep = 'official_info';
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
                            <li class="<?= $onboardingStep === 3 ? 'active' : ($onboardingStep > 3 ? 'is-completed' : '') ?>">Verify Mobile</li>
                            <li class="<?= $onboardingStep === 4 ? 'active' : '' ?>">Official Information</li>
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
                <p class="text-muted">Complete your official information.</p>
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
                        <label class="form-label">Role Access</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['role_access'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Department</label>
                        <input class="form-control" value="<?= htmlspecialchars((string)$sessionInvite['department'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Birthdate</label>
                        <input type="date" class="form-control" name="birthdate" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sex</label>
                        <select class="form-select" name="sex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Civil Status</label>
                        <select class="form-select" name="civil_status" required>
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save and Continue</button>
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
