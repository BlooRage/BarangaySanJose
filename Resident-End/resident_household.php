<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once "../PhpFiles/GET/getResidentProfile.php";
require_once "../PhpFiles/Resident-End/householdHeadVerification.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$residentId = (string)($residentinformationtbl['resident_id'] ?? '');
$headOfFamilyRaw = $residentinformationtbl['head_of_family'] ?? '';
$headOfFamilyNormalized = strtolower(trim((string)$headOfFamilyRaw));
$isHeadOfFamily = in_array($headOfFamilyNormalized, ['yes', 'true', '1', 'y'], true);
$residentStatusRaw = trim((string)($residentinformationtbl['status_name_resident'] ?? ''));
$residentStatusKey = strtolower(str_replace([' ', '_', '-'], '', $residentStatusRaw));
$isResidentVerified = in_array($residentStatusKey, ['verifiedresident', 'verified'], true);
$householdHeadVerification = hhv_get_resident_head_verification($conn, $residentId);
$canManageHouseholdMembers = $isHeadOfFamily && (bool)($householdHeadVerification['can_manage_members'] ?? false);
$householdManageMessage = trim((string)($householdHeadVerification['message'] ?? ''));
$canSendHouseholdInvite = $canManageHouseholdMembers && $isResidentVerified;
$residentCsrfToken = ensureCsrfToken();

$fullAddressParts = [];
$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$streetNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));

if ($unitNumber !== '') {
    $fullAddressParts[] = 'Unit ' . $unitNumber;
}
if ($streetNumber !== '' || $streetName !== '') {
    $streetDisplay = $streetName;
    if ($streetName !== '' && stripos($streetName, 'block') === false && stripos($streetName, 'street') === false) {
        $streetDisplay .= ' Street';
    }
    $fullAddressParts[] = trim($streetNumber . ' ' . $streetDisplay);
}
if ($phaseNumber !== '') {
    $fullAddressParts[] = $phaseNumber;
}
if ($subdivision !== '') {
    $fullAddressParts[] = $subdivision;
}
$fullAddressParts[] = 'San Jose';
$fullAddressParts[] = 'Rodriguez';
$fullAddressParts[] = 'Rizal';
$residentAddressPreview = implode(', ', array_filter($fullAddressParts, static fn($value) => trim((string)$value) !== ''));
if ($residentAddressPreview === '') {
    $residentAddressPreview = 'Address information will appear once your household record is loaded.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
    <title>Household Profile</title>
    <script>
      window.RESIDENT_HOUSEHOLD_IS_HEAD = <?= $isHeadOfFamily ? 'true' : 'false' ?>;
      window.RESIDENT_HOUSEHOLD_CAN_MANAGE = <?= $canManageHouseholdMembers ? 'true' : 'false' ?>;
      window.RESIDENT_HOUSEHOLD_MANAGE_MESSAGE = <?= json_encode($householdManageMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.RESIDENT_CSRF_TOKEN = <?= json_encode($residentCsrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="../JS-Script-Files/modalHandler.js" defer></script>
    <script src="../JS-Script-Files/Resident-End/profileSidebar.js" defer></script>
    <script src="../JS-Script-Files/Resident-End/householdMembers.js?v=20260328-2" defer></script>
    <script src="../JS-Script-Files/Resident-End/householdInviteModal.js?v=20260328-3" defer></script>
    <script src="../JS-Script-Files/Resident-End/householdJoin.js" defer></script>
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <style>
      .household-page {
        background: #f8f9fa;
      }

      .resident-page-header {
        margin-bottom: 0;
      }

      .resident-page-title {
        margin: 0;
        font-family: 'Charis SIL Bold', serif;
        font-size: 3rem;
        line-height: 1.1;
        color: #de710c;
        letter-spacing: 0;
        margin-bottom: 1.5rem;
      }

      .resident-page-rule {
        margin: 0 0 1.5rem;
        border: 0;
        border-top: 1px solid #d6d8dc;
        opacity: 1;
      }

      .household-hero-card,
      .household-surface-card,
      .household-info-card {
        border: 1px solid rgba(222, 113, 12, 0.12);
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 22px 50px rgba(15, 23, 42, 0.06);
      }

      .household-hero-card {
        overflow: hidden;
      }

      .household-hero-card .card-body {
        padding: 1.8rem;
      }

      .household-hero-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.75fr) minmax(320px, 0.95fr);
        gap: 1.5rem;
        align-items: stretch;
      }

      .household-hero-main {
        min-width: 0;
      }

      .household-hero-aside {
        display: grid;
        gap: 0.9rem;
        align-content: end;
      }

      .household-page-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 1rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: #fff1df;
        color: #c76b12;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .household-page-title {
        margin: 0;
        color: #1a1f2b;
        font-size: clamp(2rem, 3vw, 2.8rem);
        font-weight: 800;
        letter-spacing: -0.03em;
      }

      .household-page-copy {
        margin: 1rem 0 0;
        color: #5f6b7a;
        font-size: 1rem;
        line-height: 1.75;
      }

      .household-hero-badges {
        display: grid;
        gap: 0.9rem;
        margin: 0;
      }

      .household-hero-badge {
        display: flex;
        align-items: flex-start;
        gap: 0.45rem;
        min-height: 88px;
        padding: 1rem 1rem;
        border-radius: 18px;
        background: #f8fafc;
        color: #334155;
        font-size: 0.92rem;
        font-weight: 600;
        border: 1px solid rgba(148, 163, 184, 0.14);
      }

      .household-hero-badge i {
        margin-top: 0.15rem;
        font-size: 1rem;
        color: #de710c;
      }

      .household-hero-badge-content {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
      }

      .household-hero-badge-label {
        color: #64748b;
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .household-hero-badge-value {
        color: #334155;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.5;
        word-break: break-word;
      }

      .household-section-title {
        margin: 0 0 0.5rem;
        color: #1f2937;
        font-size: 1.1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        text-transform: uppercase;
      }

      .household-section-copy {
        margin: 0;
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.7;
      }

      .household-rule-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
      }

      .household-rule-card {
        padding: 1.2rem 1.25rem;
      }

      .household-rule-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.9rem;
        border-radius: 14px;
        background: linear-gradient(180deg, #fff4e5 0%, #ffe0be 100%);
        color: #c96c12;
        font-size: 1.1rem;
      }

      .household-action-card .card-body,
      .household-info-card .card-body {
        padding: 1.5rem;
      }

      .household-action-card .card-header,
      .household-info-card .card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        background: linear-gradient(180deg, rgba(255, 247, 237, 0.95) 0%, rgba(255, 255, 255, 0.95) 100%);
      }

      .household-code-group {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 240px;
        gap: 0.9rem;
        align-items: end;
      }

      .household-code-input {
        min-height: 58px;
        border-radius: 16px;
        border-color: #d9dee8;
        padding: 0.95rem 1rem;
        font-size: 1rem;
      }

      .household-primary-btn,
      .household-secondary-btn,
      .household-danger-btn {
        min-height: 50px;
        border-radius: 14px;
        font-weight: 700;
      }

      .household-primary-btn {
        background: linear-gradient(180deg, #fe993c 0%, #e47c1c 100%);
        border-color: #e47c1c;
        color: #ffffff;
      }

      .household-primary-btn:hover,
      .household-primary-btn:focus {
        background: linear-gradient(180deg, #f28a2d 0%, #d96f0f 100%);
        border-color: #d96f0f;
        color: #ffffff;
      }

      .household-secondary-btn {
        background: #f0fdf4;
        border: 1px solid #86efac;
        color: #166534;
      }

      .household-secondary-btn:hover,
      .household-secondary-btn:focus {
        background: #dcfce7;
        color: #14532d;
      }

      .household-danger-btn {
        background: #fff1f2;
        border: 1px solid #fda4af;
        color: #be123c;
      }

      .household-danger-btn:hover,
      .household-danger-btn:focus {
        background: #ffe4e6;
        color: #9f1239;
      }

      .household-status-row {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) repeat(2, minmax(0, 0.7fr));
        gap: 1rem;
        margin-bottom: 1.2rem;
      }

      .household-stat-card {
        min-height: 132px;
        padding: 1.1rem 1.2rem;
        border-radius: 20px;
        border: 1px solid rgba(222, 113, 12, 0.12);
        background: linear-gradient(180deg, #ffffff 0%, #fffaf5 100%);
      }

      .household-stat-label {
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .household-stat-value {
        margin-top: 0.5rem;
        color: #111827;
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.03em;
      }

      .household-stat-copy {
        margin-top: 0.4rem;
        color: #64748b;
        font-size: 0.92rem;
        line-height: 1.6;
      }

      .household-member-shell .border.rounded {
        border: 1px solid rgba(148, 163, 184, 0.22) !important;
        border-radius: 18px !important;
        background: #ffffff;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.04);
      }

      #householdPendingRequestsList {
        border: 1px solid rgba(148, 163, 184, 0.18) !important;
        border-radius: 18px !important;
        background: #fbfdff !important;
      }

      .household-system-note {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: #fff8ef;
        border: 1px solid rgba(222, 113, 12, 0.14);
        color: #6b4f2b;
        font-size: 0.93rem;
        line-height: 1.65;
      }

      .household-empty-copy {
        padding: 1rem 0;
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.7;
      }

      @media (max-width: 1199px) {
        .household-hero-layout {
          grid-template-columns: 1fr;
        }

        .household-hero-aside {
          align-content: start;
        }

        .household-rule-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .household-status-row {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .household-status-row .household-stat-card:first-child {
          grid-column: 1 / -1;
        }
      }

      @media (max-width: 767px) {
        .resident-page-header {
          margin-bottom: 0;
        }

        .resident-page-title {
          font-size: 2rem;
        }

        .resident-page-rule {
          margin: 0 0 1rem;
        }

        .household-hero-card .card-body,
        .household-action-card .card-body,
        .household-info-card .card-body {
          padding: 1.1rem;
        }

        .household-action-card .card-header,
        .household-info-card .card-header {
          padding: 0.95rem 1.1rem;
        }

        .household-rule-grid,
        .household-status-row,
        .household-code-group {
          grid-template-columns: 1fr;
        }

        .household-hero-badge {
          min-height: 0;
        }

        .household-page-copy {
          font-size: 0.95rem;
        }

        .household-page-title {
          font-size: 1.8rem;
        }
      }
    </style>
</head>
<body>
    <div class="d-flex household-page" style="min-height: 100vh;">

        <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

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

        <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-4">

            <section class="resident-page-header">
                <h1 class="resident-page-title">Household Profile</h1>
                <hr class="resident-page-rule">
            </section>

            <section class="card household-hero-card shadow-sm mb-4">
                <div class="card-body">
                    <div class="household-hero-layout">
                        <div class="household-hero-main">
                            <span class="household-page-kicker">
                                <i class="fa-solid fa-house-user" aria-hidden="true"></i>
                                Resident Household
                            </span>
                            <h1 class="household-page-title">Manage your family record in one place</h1>
                            <p class="household-page-copy">
                                Household profiling helps the barangay keep an accurate record of family groups, addresses, and member counts within Barangay San Jose. This page uses the live household system already built into the portal, including invite-code joining, head-of-family controls, pending member verification, and resident-detail matching to help review possible duplicate household entries.
                            </p>
                        </div>
                        <aside class="household-hero-aside">
                            <div class="household-hero-badges">
                                <div class="household-hero-badge">
                                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                                    <div class="household-hero-badge-content">
                                        <span class="household-hero-badge-label">Account Type</span>
                                        <span class="household-hero-badge-value"><?= $isHeadOfFamily ? 'Head of family account' : 'Household member account' ?></span>
                                    </div>
                                </div>
                                <div class="household-hero-badge">
                                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                    <div class="household-hero-badge-content">
                                        <span class="household-hero-badge-label">Household Address</span>
                                        <span class="household-hero-badge-value"><?= htmlspecialchars($residentAddressPreview, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="household-rule-grid mb-4">
                <article class="card household-surface-card household-rule-card shadow-sm">
                    <div class="household-rule-icon">
                        <i class="fa-solid fa-users-viewfinder" aria-hidden="true"></i>
                    </div>
                    <h2 class="household-section-title">What this page tracks</h2>
                    <p class="household-section-copy">
                        The system keeps one household record for the head of the family, shows the active address used by the household, and counts adults and minors from active resident members and approved unregistered member entries.
                    </p>
                </article>
                <article class="card household-surface-card household-rule-card shadow-sm">
                    <div class="household-rule-icon">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                    </div>
                    <h2 class="household-section-title">How members join</h2>
                    <p class="household-section-copy">
                        Verified heads of family can send SMS invite codes to existing verified resident accounts. Non-registered household members can be submitted here for admin verification with a birth certificate before they appear in the household record.
                    </p>
                </article>
                <article class="card household-surface-card household-rule-card shadow-sm">
                    <div class="household-rule-icon">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    </div>
                    <h2 class="household-section-title">System safeguards</h2>
                    <p class="household-section-copy">
                        If a newly registered resident appears to match an existing household entry, the system can compare details such as name and birthdate so the resident and barangay can confirm the record instead of creating confusion in the household list.
                    </p>
                </article>
            </section>

            <?php if (!$isHeadOfFamily): ?>
            <section class="card household-surface-card household-action-card shadow-sm mb-4">
                <div class="card-header">
                    <strong>JOIN HOUSEHOLD</strong>
                </div>
                <div class="card-body">
                    <p class="household-section-copy mb-3">
                        Enter the invite code sent by your household head to join an existing family record.
                    </p>
                    <div class="household-code-group">
                        <div>
                            <label for="householdJoinCode" class="form-label small text-muted">Invite Code</label>
                            <input type="text" class="form-control household-code-input" id="householdJoinCode" placeholder="Enter invite code">
                        </div>
                        <div>
                            <button class="btn household-primary-btn w-100" id="btnJoinHousehold">Join Household</button>
                        </div>
                    </div>
                    <div id="householdJoinResult" class="small mt-3"></div>
                </div>
            </section>
            <?php endif; ?>

            <section class="card household-info-card shadow-sm mb-4">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <strong>HOUSEHOLD INFORMATION</strong>
                        <div class="text-muted small mt-1">Live household data updates automatically from the current resident record.</div>
                    </div>
                    <?php if ($isHeadOfFamily): ?>
                    <button class="btn household-secondary-btn btn-sm px-3" data-bs-toggle="modal" data-bs-target="#householdInviteModal" <?= $canManageHouseholdMembers ? '' : 'disabled' ?>>
                        <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>
                        Manage Members
                    </button>
                    <?php else: ?>
                    <button class="btn household-danger-btn btn-sm px-3" id="btnLeaveHousehold">
                        <i class="fa-solid fa-person-walking-arrow-right me-2" aria-hidden="true"></i>
                        Leave Household
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="household-status-row">
                        <article class="household-stat-card">
                            <div class="household-stat-label">Household Address</div>
                            <div id="householdAddress" class="household-stat-value">—</div>
                            <div class="household-stat-copy">
                                The head resident's active address is used as the household reference.
                            </div>
                        </article>
                        <article class="household-stat-card">
                            <div class="household-stat-label">Minors</div>
                            <div id="householdMinorCount" class="household-stat-value">0</div>
                            <div class="household-stat-copy">Members below 18 years old.</div>
                        </article>
                        <article class="household-stat-card">
                            <div class="household-stat-label">Adults</div>
                            <div id="householdAdultCount" class="household-stat-value">0</div>
                            <div class="household-stat-copy">Members 18 years old and above.</div>
                        </article>
                    </div>

                    <?php if ($isHeadOfFamily && $householdManageMessage !== ''): ?>
                    <div id="householdHeadVerificationNotice" class="alert alert-warning small mb-3<?= $canManageHouseholdMembers ? ' d-none' : '' ?>">
                        <?= htmlspecialchars($householdManageMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($isHeadOfFamily): ?>
                    <div id="householdPendingRequestsWrap" class="mb-4 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="household-section-title mb-0">Pending Verification Requests</div>
                            <div id="householdPendingRequestCount" class="fw-semibold text-dark">0</div>
                        </div>
                        <div id="householdPendingRequestsList"></div>
                    </div>
                    <?php endif; ?>

                    <div class="household-system-note mb-4">
                        Only the head of the family can send invite codes, submit non-registered members for verification, or remove members from the household. Household members can still view the live roster and leave the household from this page.
                    </div>

                    <div class="household-member-shell">
                        <div class="household-section-title">Household Members</div>
                        <div class="household-section-copy mb-3">
                            Active resident members appear together with approved unregistered member entries under the same household record.
                        </div>
                        <div id="householdMembersGrid" class="row g-3"></div>
                        <div id="householdMembersEmpty" class="household-empty-copy d-none">
                            No household members are recorded yet. Once members join through invite code or finish verification, they will appear here automatically.
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <?php if ($isHeadOfFamily): ?>
    <div class="modal fade" id="householdInviteModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Household Members</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$isResidentVerified): ?>
                        <div class="alert alert-warning small mb-2">
                            Your account must be verified before sending household invite codes via SMS.
                        </div>
                    <?php endif; ?>
                    <?php if ($householdManageMessage !== ''): ?>
                        <div class="alert alert-warning small mb-2<?= $canManageHouseholdMembers ? ' d-none' : '' ?>" id="householdManageAlert">
                            <?= htmlspecialchars($householdManageMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <p class="text small mb-2">
                            Send invite codes to existing verified resident accounts using their mobile numbers.
                        </p>
                        <div id="householdInvitePhoneList" class="d-flex flex-column gap-2">
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="text" class="form-control household-invite-phone" placeholder="9XXXXXXXXX" inputmode="numeric" pattern="^\d{10}$" maxlength="10">
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
                            Submit a non-registered household member for verification.
                        </p>
                        <div class="alert alert-info small mb-3">
                            A birth certificate is required. The member will only be added to the household after admin verification.
                        </div>
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
                                <label class="form-label small text-muted">Birthdate <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="hmBirthdate" max="<?= date('Y-m-d') ?>" data-date-modal-style="calendar">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">Birth Certificate <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="hmBirthCertificate" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text">Accepted file types: PDF, JPG, JPEG, PNG.</div>
                            </div>
                        </div>
                        <div id="householdMemberAddResult" class="small mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-outline-primary" id="btnAddHouseholdMemberInfo" disabled>Submit Verification Request</button>
                    <button class="btn btn-success" id="btnSendHouseholdInvite" data-verified="<?= $isResidentVerified ? '1' : '0' ?>" <?= $canSendHouseholdInvite ? '' : 'disabled' ?>>Send Invites</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="householdMemberSubmitSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
                <div class="modal-header justify-content-center border-0 pb-0">
                    <h5 class="modal-title fw-bold text-center w-100">Verification Request Submitted</h5>
                </div>
                <hr>
                <div class="modal-body text-center">
                    <p class="mb-0" id="householdMemberSubmitSuccessMessage">Household member verification request submitted. Please wait for admin review.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
</body>
</html>
