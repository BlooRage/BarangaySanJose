<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once "../PhpFiles/GET/getResidentProfile.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'];
$residentaddresstbl = $data['residentaddresstbl'];
$useraccountstbl = $data['useraccountstbl'];

$computedAge = '';
if (!empty($residentinformationtbl['birthdate'])) {
    $dobDate = DateTime::createFromFormat('Y-m-d', $residentinformationtbl['birthdate']);
    if (!$dobDate) {
        try {
            $dobDate = new DateTime($residentinformationtbl['birthdate']);
        } catch (Exception $e) {
            $dobDate = null;
        }
    }
    if ($dobDate) {
        $computedAge = $dobDate->diff(new DateTime('today'))->y;
    }
}

$profileImage = '../Images/Profile-Placeholder.png';
$residentId = $residentinformationtbl['resident_id'] ?? '';
$headOfFamilyRaw = $residentinformationtbl['head_of_family'] ?? '';
$headOfFamilyNormalized = strtolower(trim((string)$headOfFamilyRaw));
$isHeadOfFamily = in_array($headOfFamilyNormalized, ['yes', 'true', '1', 'y'], true);

if (!function_exists('toPublicPath')) {
function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $parts = explode('/', $normalized);
    $cleanParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($cleanParts);
            continue;
        }
        $cleanParts[] = $part;
    }
    $normalized = '/' . implode('/', $cleanParts);

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        $public = substr($normalized, $markerPos);
        return '..' . $public;
    }

    $webRoot = realpath(__DIR__ . "/..");
    if ($webRoot) {
        $rootNorm = str_replace("\\", "/", $webRoot);
        if (strpos($normalized, $rootNorm) === 0) {
            $rel = substr($normalized, strlen($rootNorm));
            if ($rel === '') {
                return null;
            }
            if ($rel[0] !== '/') {
                $rel = '/' . $rel;
            }
            return '../' . ltrim($rel, '/');
        }
    }

    return '../' . ltrim($normalized, '/');
}
}
if ($residentId !== '' && isset($conn) && $conn instanceof mysqli) {
    $stmtPic = $conn->prepare("
        SELECT uf.file_path
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        INNER JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_type_name = '2x2 Picture'
          AND dt.document_category = 'ResidentProfiling'
          AND s.status_name = 'Verified'
          AND s.status_type = 'ResidentDocumentProfiling'
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtPic) {
        $stmtPic->bind_param("s", $residentId);
        $stmtPic->execute();
        $stmtPic->bind_result($verifiedPicPath);
        if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
            $publicPath = toPublicPath($verifiedPicPath);
            if (!empty($publicPath)) {
                $profileImage = $publicPath;
            }
        }
        $stmtPic->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<title>Resident Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="../JS-Script-Files/modalHandler.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdMembers.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileOccupation.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileSidebar.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileVerifyEmail.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdInviteModal.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdJoin.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileTabs.js" defer></script>
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
</head>

<body>

    <div class="d-flex" style="min-height: 100vh;">

        <?php include 'includes/resident_sidebar.php'; ?>

        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn" id="btn-burger" type="button">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                    <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                    <span class="logo-name">Barangay San Jose</span>
                </div>
            </div>
        </header>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head text-center py-1 rounded my-2">
                <h3 class="mb-0 text-black">ACCOUNT</h3>
            </div>
            <hr class="mt-1 mb-2">

            <ul class="nav profile-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button" role="tab" aria-controls="pane-profile" aria-selected="true">
                        Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-household" data-bs-toggle="tab" data-bs-target="#pane-household" type="button" role="tab" aria-controls="pane-household" aria-selected="false">
                        Household
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="pane-profile" role="tabpanel" aria-labelledby="tab-profile" tabindex="0">

            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>PERSONAL INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        Edit
                    </button>
                </div>

                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-12 col-lg-3 d-flex align-items-center justify-content-center">
                            <img src="<?= htmlspecialchars($profileImage) ?>"
                                id="img-profileAvatar"
                                class="img-fluid rounded-circle mb-2"
                                style="width:170px; height: 170px;">
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="d-flex flex-column gap-2">
                                <div><strong>Name:</strong> <?= $residentinformationtbl['firstname'] . ' ' . $residentinformationtbl['lastname'] ?></div>
                                <div><strong>Age:</strong> <?= $computedAge !== '' ? $computedAge : '—' ?></div>
                                <div><strong>Birthdate:</strong> <?= $residentinformationtbl['birthdate'] ?></div>
                                <div><strong>Sex:</strong> <?= $residentinformationtbl['sex'] ?></div>
                                <div><strong>Civil Status:</strong> <?= $residentinformationtbl['civil_status'] ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-5">
                            <div class="d-flex flex-column gap-2">
                                <div><strong>Religion:</strong> <?= $residentinformationtbl['religion'] ?></div>
                                <div><strong>Voter Status:</strong> <?= $residentinformationtbl['voter_status'] ?></div>
                                <div><strong>Head of the Family:</strong> <?= $residentinformationtbl['head_of_family'] ?></div>
                                <div><strong>Occupation:</strong> <?= $residentinformationtbl['occupation'] ?></div>
                                <div><strong>Sector Membership:</strong> <?= $residentinformationtbl['sector_membership'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>ADDRESS INFORMATION</strong>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        Edit Address
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-8">
                            <div class="text-muted small mb-1">Full Address</div>
                            <div class="fw-semibold">
                                <?php
                                  $unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
                                  $houseNo = trim((string)($residentaddresstbl['street_number'] ?? ''));
                                  $streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
                                  $phase = trim((string)($residentaddresstbl['phase_number'] ?? ''));
                                  $subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
                                  $area = trim((string)($residentaddresstbl['area_number'] ?? ''));

                                  $streetDisplay = $streetName;
                                  if ($streetName !== '' && stripos($streetName, 'block') === false) {
                                    $streetDisplay = $streetName . ' Street';
                                  }
                                  $parts = [];
                                  if ($unitNumber !== '') $parts[] = 'Unit ' . $unitNumber;
                                  if ($houseNo !== '') $parts[] = $houseNo;
                                  if ($streetDisplay !== '') $parts[] = $streetDisplay;
                                  if ($phase !== '') $parts[] = $phase;
                                  if ($subdivision !== '') $parts[] = $subdivision;
                                  $parts[] = 'San Jose';
                                  if ($area !== '') $parts[] = $area;
                                  $parts[] = 'Rodriguez';
                                  $parts[] = 'Rizal';
                                  $parts[] = '1860';

                                  echo implode(', ', array_filter($parts, fn($v) => $v !== ''));
                                ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">Residency Duration</div>
                            <div class="fw-semibold"><?= $residentaddresstbl['residency_duration'] ?? '—' ?></div>
                        </div>
                    </div>
                </div>
            </div>
             <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>EMERGENCY CONTACT INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editEmergencyContactModal">
                        Edit
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong>Contact Person:</strong> <?= $residentinformationtbl['emergency_name'] ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Contact Number:</strong> <?= $residentinformationtbl['emergency_contact'] ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header"><strong>ACCOUNT INFORMATION</strong></div>
                <div class="card-body small">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div><strong>Account Type:</strong> <?= $useraccountstbl['type'] ?></div>
                            <div><strong>Account Created:</strong> <?= $useraccountstbl['created'] ?></div>
                            <div>
                                <strong>Account Status:</strong>
                                <?php
                                  $statusLabelRaw = trim((string)($residentinformationtbl['status_name_resident'] ?? ''));
                                  $statusLabel = $statusLabelRaw !== '' ? $statusLabelRaw : 'NotVerified';
                                  $statusKey = strtolower(str_replace([' ', '_', '-'], '', $statusLabel));
                                  $statusClass = 'status-badge status-badge--default';

                                  if ($statusKey === 'pendingverification' || $statusKey === 'pendingreview') {
                                      $statusClass = 'status-badge status-badge--pending';
                                  } elseif ($statusKey === 'verifiedresident') {
                                      $statusClass = 'status-badge status-badge--verified';
                                  } elseif ($statusKey === 'notverified') {
                                      $statusClass = 'status-badge status-badge--denied';
                                  } elseif ($statusKey === 'archived') {
                                      $statusClass = 'status-badge status-badge--archived';
                                  }

                                  $statusDisplay = preg_replace('/(?<!^)([A-Z])/', ' $1', $statusLabel);
                                ?>
                                <span class="<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                  <?= htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div><strong>Mobile Number:</strong> +63<?= $useraccountstbl['phone_number'] ?></div>
                            <div><strong>Email:</strong> <?= $useraccountstbl['email'] ?>
                                <?php
                                  $emailVerified = (int)($useraccountstbl['email_verify'] ?? 0) === 1;
                                  $emailVerifyClass = $emailVerified
                                      ? 'status-badge status-badge--verified'
                                      : 'status-badge status-badge--denied';
                                  $emailVerifyLabel = $emailVerified ? 'Verified' : 'Not Verified';
                                ?>
                                <span class="<?= htmlspecialchars($emailVerifyClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($emailVerifyLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Password</a></div>
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Email</a></div>
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Phone Number</a></div>
                        <?php if (!(int)($useraccountstbl['email_verify'] ?? 0)): ?>
                            <div class="col-12 col-md-6">
                                <a href="javascript:void(0)" id="verifyEmailLink">Verify Email</a>
                            </div>
                        <?php else: ?>
                            <div class="col-12 col-md-6 text-muted fst-italic">Email already verified</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                </div>
                <div class="tab-pane fade" id="pane-household" role="tabpanel" aria-labelledby="tab-household" tabindex="0">
                    <?php if (!$isHeadOfFamily): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <strong>JOIN HOUSEHOLD</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label for="householdJoinCode" class="form-label small text-muted">Invite Code</label>
                                    <input type="text" class="form-control" id="householdJoinCode" placeholder="Enter invite code">
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-primary w-100" id="btnJoinHousehold">Join Household</button>
                                </div>
                            </div>
                            <div id="householdJoinResult" class="small mt-2"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>HOUSEHOLD INFORMATION</strong>
                            <?php if ($isHeadOfFamily): ?>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#householdInviteModal">
                                Add Household Member
                            </button>
                            <?php else: ?>
                            <button class="btn btn-danger btn-sm" id="btnLeaveHousehold">
                                Leave Household
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-muted small">Address</div>
                                <div id="householdAddress" class="fw-semibold">—</div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-4">
                                    <div class="text-muted small">Minors</div>
                                    <div id="householdMinorCount" class="fw-semibold">0</div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-muted small">Adults</div>
                                    <div id="householdAdultCount" class="fw-semibold">0</div>
                                </div>
                            </div>
                            <div id="householdMembersGrid" class="row g-3"></div>
                            <div id="householdMembersEmpty" class="text-muted small mt-2 d-none">
                                No household members yet.
                            </div>
                            <div class="mt-3 text-muted small">
                                Only the head of the family can add or manage household members.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <?php if ($isHeadOfFamily): ?>
    <div class="modal fade" id="householdInviteModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Invite Household Members</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <p class="text small mb-2">
                            Invite members with accounts via SMS.
                        </p>
                        <div id="householdInvitePhoneList" class="d-flex flex-column gap-2">
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="text" class="form-control household-invite-phone" placeholder="9XXXXXXXXX" inputmode="numeric" pattern="^\d{9}$" maxlength="9">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" id="btnAddInvitePhone">
                            Add Another Number
                        </button>
                        <div class="form-text mt-2">Use PH format starting with +63.</div>
                        <div id="householdInviteResult" class="small mt-2"></div>
                    </div>
                    <hr class="my-3">
                    <div>
                        <p class="text small mb-2">
                            Add member without an account.
                        </p>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hmLastName" placeholder="Last Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hmFirstName" placeholder="First Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Middle Name</label>
                                <input type="text" class="form-control" id="hmMiddleName" placeholder="Middle Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Suffix</label>
                                <input type="text" class="form-control" id="hmSuffix" placeholder="Suffix (e.g. Jr.)">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Birthdate</label>
                                <input type="date" class="form-control" id="hmBirthdate">
                            </div>
                        </div>
                        <div id="householdMemberAddResult" class="small mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-outline-primary" id="btnAddHouseholdMemberInfo" disabled>Add Member</button>
                    <button class="btn btn-success" id="btnSendHouseholdInvite">Send Invites</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="editProfileModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">Full Name</label>
                    <div class="d-flex gap-2 mb-3">
                        <input class="form-control" value="<?= $residentinformationtbl['firstname'] ?>" placeholder="First Name" >
                        <input class="form-control" value="<?= $residentinformationtbl['middlename'] ?>" placeholder="Middle Name" >
                        <input class="form-control" value="<?= $residentinformationtbl['lastname'] ?>" placeholder="Last Name" >
                        <select class="form-control" value="<?= $residentinformationtbl['suffix'] ?>" placeholder="Suffix (e.g. Jr., Sr.)" >
                            <option value="">N/A</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="III">III</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sex</label>
                            <input class="form-control" value="<?= $residentinformationtbl['sex'] ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input class="form-control" value="<?= $residentinformationtbl['birthdate'] ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Civil Status</label>
                            <select class="form-control" value="<?= $residentinformationtbl['civil_status'] ?>">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Religion</label>
                            <select class="form-control" value="<?= $residentinformationtbl['religion'] ?>">
                                <option value="Roman Catholic">Roman Catholic</option>
                                <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                                <option value="Muslim">Muslim</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">

                         <div class="col-md-6">
                            <label class="form-label">Registered Voter</label>
                            <input class="form-select" name="voter_status" value="<?= $residentinformationtbl['voter_status'] ?>" readonly>
                        </div>

                         <div class="col-md-6">
                            <label class="form-label">Employment Status</label>
                            <select class="form-select" name="employment_status" id="employmentStatus" onchange="toggleOccupation()">
                                <option value="Employed" <?= ($residentinformationtbl['employment_status'] == 'Employed') ? 'selected' : '' ?>>Employed</option>
                                <option value="Unemployed" <?= ($residentinformationtbl['employment_status'] == 'Unemployed') ? 'selected' : '' ?>>Unemployed</option>
                            </select>
                        </div>

                    </div>

                     <div class="row mb-3" id="occupationRow" style="display: none;">
                        <div class="col-md-12">
                            <label class="form-label">Occupation</label>
                            <input type="text"
                                class="form-control"
                                name="occupation"
                                value="<?= $residentinformationtbl['occupation'] ?>">
                        </div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Sector Membership</label>
                        <select class="form-select" value="<?= $residentinformationtbl['sector_membership'] ?>">
                            <option value="PWD">Person with Disability (PWD)</option>
                            <option value="Single Parent">Single Parent</option>
                            <option value="Student">Student</option>
                            <option value="Senior Citizen">Senior Citizen</option>
                            <option value="Indigenous People">Indigenous People</option>
                            <option value="NA">N/A</option>
                        </select>
                    </div>
                </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Next</button>
                    </div>

                </div>

            </div>
        </div>
    </div>
    </div>


     <div class="modal fade" id="addAddressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5>Add Address</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="text" class="form-control mb-2" placeholder="Street Number">
                    <input type="text" class="form-control mb-2" placeholder="Street Name">
                    <input type="text" class="form-control mb-2" placeholder="Subdivision">
                    <input type="text" class="form-control mb-2" placeholder="Area Number">
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Save</button>
                </div>

            </div>
        </div>
    </div>

         <div class="modal fade" id="editEmergencyContactModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Edit Emergency Contact</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Contact Person</label>
                            <input class="form-control" value="<?= $residentinformationtbl['emergency_name'] ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input class="form-control" value="<?= $residentinformationtbl['emergency_contact'] ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
