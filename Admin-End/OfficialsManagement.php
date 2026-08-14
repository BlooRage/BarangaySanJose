<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";
require_once "../PhpFiles/General/adminModulePermissions.php";

requireRoleSession(['SuperAdmin'], false);

$managementMode = strtolower(trim((string)($managementMode ?? 'official')));
if (!in_array($managementMode, ['official', 'personnel', 'admin'], true)) {
  $managementMode = 'official';
}
$isPersonnelManagement = $managementMode === 'personnel';
$isAdminManagement = $managementMode === 'admin';
$supportsProfileManagement = $isPersonnelManagement || $isAdminManagement;
$managementShowLifecycleTabs = $managementMode === 'official';

$managementEntitySingular = match ($managementMode) {
  'personnel' => 'Personnel',
  'admin' => 'Admin',
  default => 'Official',
};
$managementEntityPlural = match ($managementMode) {
  'personnel' => 'Personnel',
  'admin' => 'Admins',
  default => 'Officials',
};
$managementPageTitle = match ($managementMode) {
  'personnel' => 'Personnel Tracker',
  'admin' => 'Admin Management',
  default => 'Official Records',
};
$managementDescription = match ($managementMode) {
  'personnel' => 'Maintain personnel profile records, check readiness for onboarding, and lock or unlock accounts without changing seat assignments.',
  'admin' => 'Maintain admin profile records separately from barangay officials, monitor onboarding readiness, and manage onboarding-safe account locking.',
  default => 'Review current and past official profiles, onboarding status, and account state. Seat assignments are managed from Seats & Onboarding.',
};
$managementCurrentLabel = match ($managementMode) {
  'personnel' => 'Current Personnel',
  'admin' => 'Admin Records',
  default => 'Current Officials',
};
$managementPastLabel = match ($managementMode) {
  'personnel' => 'Past Personnel',
  'admin' => 'Past Admins',
  default => 'Past Officials',
};
$managementSearchPlaceholder = match ($managementMode) {
  'personnel' => 'Search personnel ID / user ID / name / department',
  'admin' => 'Search admin ID / user ID / name / department',
  default => 'Search official ID / user ID / name / department',
};
$managementIdLabel = match ($managementMode) {
  'personnel' => 'Personnel ID',
  'admin' => 'Admin ID',
  default => 'Official ID',
};
$managementFilterTitle = match ($managementMode) {
  'personnel' => 'Filter Personnel Tracker',
  'admin' => 'Filter Admin Management',
  default => 'Filter Official Management',
};
$managementAccessTitle = match ($managementMode) {
  'personnel' => 'Manage Personnel Access',
  'admin' => 'Manage Admin Access',
  default => 'Manage Official Access',
};
$managementPromoteTitle = $isPersonnelManagement ? 'Promote Personnel' : 'Promote Official';
$managementSubjectLabel = $managementEntitySingular;
$managementPromotionPathLabel = $isPersonnelManagement ? 'Position Change Path' : 'Promotion Path';
$managementPromotionHelper = $isPersonnelManagement
  ? "This update changes the personnel's system role, position access, and assignment details."
  : "Promotion updates the official's system role, position access, and assignment details.";
$managementColumnsStorageKey = match ($managementMode) {
  'personnel' => 'admin_cols_personnel_management_v2',
  'admin' => 'admin_cols_admin_management_v1',
  default => 'admin_cols_officials_management_v2',
};
$managementDefaultHiddenColumnIdxs = [1, 3, 8];
$managementFilterRoleOptions = $isPersonnelManagement
  ? ['ALL' => 'All', 'Admin' => 'Admin', 'SuperAdmin' => 'SuperAdmin']
  : ['ALL' => 'All', 'Admin' => 'Admin', 'SuperAdmin' => 'SuperAdmin'];
$managementAccessRoleOptions = $isPersonnelManagement
  ? ['Admin' => 'Admin', 'SuperAdmin' => 'SuperAdmin']
  : ['Admin' => 'Admin', 'SuperAdmin' => 'SuperAdmin'];

$officialsMgmtDepartmentOptions = [
  'Office of the Barangay',
  'Barangay Certificate Issuance',
  'Baranagay Monitoring',
  'Barangay Treasurers Office',
  'Barangay Peace and Order',
];
$departmentResult = $conn->query("
  SELECT DISTINCT department
  FROM officialinformationtbl
  WHERE department IS NOT NULL AND TRIM(department) <> ''
  ORDER BY department ASC
");
if ($departmentResult instanceof mysqli_result) {
  while ($row = $departmentResult->fetch_assoc()) {
    $value = trim((string)($row['department'] ?? ''));
    if ($value !== '' && !in_array($value, $officialsMgmtDepartmentOptions, true)) {
      $officialsMgmtDepartmentOptions[] = $value;
    }
  }
  $departmentResult->close();
}
sort($officialsMgmtDepartmentOptions);

$officialsMgmtAreaOptions = [
  'Barangay Wide',
  'Area 01',
  'Area 1A',
  'Area 02',
  'Area 03',
  'Area 04',
  'Area 05',
  'Area 06',
];
$areaResult = $conn->query("
  SELECT DISTINCT area_number
  FROM residentaddresstbl
  WHERE area_number IS NOT NULL AND TRIM(area_number) <> ''
  ORDER BY area_number ASC
");
if ($areaResult instanceof mysqli_result) {
  while ($row = $areaResult->fetch_assoc()) {
    $value = trim((string)($row['area_number'] ?? ''));
    if ($value !== '' && !in_array($value, $officialsMgmtAreaOptions, true)) {
      $officialsMgmtAreaOptions[] = $value;
    }
  }
  $areaResult->close();
}
sort($officialsMgmtAreaOptions);

$basePositionsByRole = [
  'SuperAdmin' => ['IT Administrator'],
  'Official' => ['Barangay Official', 'Barangay Secretary'],
  'Personnel' => [
    'Department Public Assistance Desk',
    'Department Secretary',
    'Department OIC (Officer In Charge)',
    'Barangay Police',
    'Desk Officer',
    'Area OIC',
    'Barangay Treasurer',
  ],
];

if ($isPersonnelManagement) {
  $officialsMgmtPositionsByRole = [
    'SuperAdmin' => ['IT Administrator'],
    'Personnel' => $basePositionsByRole['Personnel'],
  ];
  $officialsMgmtAreaRequiredPositions = ['Barangay Police', 'Desk Officer', 'Area OIC'];
  $officialsMgmtPositionsByDepartment = [];
  foreach ($officialsMgmtDepartmentOptions as $departmentOption) {
    $officialsMgmtPositionsByDepartment[$departmentOption] = $basePositionsByRole['Personnel'];
  }
  $officialsMgmtPositionsByDepartment['Office of the Barangay'] = array_values(array_unique(array_merge(
    ['IT Administrator'],
    $basePositionsByRole['Personnel']
  )));
} elseif ($isAdminManagement) {
  $officialsMgmtPositionsByRole = [
    'SuperAdmin' => $basePositionsByRole['SuperAdmin'],
  ];
  $officialsMgmtAreaRequiredPositions = [];
  $officialsMgmtPositionsByDepartment = [
    'Office of the Barangay' => $basePositionsByRole['SuperAdmin'],
  ];
} else {
  $officialsMgmtPositionsByRole = [
    'SuperAdmin' => $basePositionsByRole['SuperAdmin'],
    'Official' => $basePositionsByRole['Official'],
  ];
  $officialsMgmtAreaRequiredPositions = ['Barangay Secretary'];
  $officialsMgmtPositionsByDepartment = [
    'Office of the Barangay' => array_merge($basePositionsByRole['SuperAdmin'], $basePositionsByRole['Official']),
  ];
}
$officialsMgmtPermissionCatalog = amp_get_permission_catalog();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($managementPageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260319-1">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/OfficialWorkspaceStyle.css?v=20260812-2">
  <style>
    #main-display {
      min-width: 0;
    }
    .officials-page-header,
    #div-tableContainer {
      min-width: 0;
    }
    .officials-masterlist-shell {
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .officials-masterlist-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .officials-masterlist-shell .officials-masterlist-table {
      min-width: 1350px;
    }
    .officials-masterlist-shell .officials-masterlist-table th {
      white-space: nowrap;
    }
    .officials-masterlist-shell .officials-masterlist-table td {
      vertical-align: middle;
      max-width: 260px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .resident-masterlist-shell .status-filter-btn[data-permission-filter],
    .resident-masterlist-shell .status-filter-btn[data-lifecycle-filter] {
      color: #495057;
      border-color: #495057;
      background: #fff;
    }
    .resident-masterlist-shell .status-filter-btn[data-permission-filter]:hover,
    .resident-masterlist-shell .status-filter-btn[data-permission-filter]:focus-visible,
    .resident-masterlist-shell .status-filter-btn[data-lifecycle-filter]:hover,
    .resident-masterlist-shell .status-filter-btn[data-lifecycle-filter]:focus-visible {
      color: #343a40;
      border-color: #343a40;
      background: #f8f9fa;
    }
    .resident-masterlist-shell .status-filter-btn[data-permission-filter].active,
    .resident-masterlist-shell .status-filter-btn[data-lifecycle-filter].active {
      color: #fff !important;
      background-color: #495057 !important;
      border-color: #495057 !important;
      font-weight: 700;
    }
    .officials-module-summary {
      max-width: 280px;
      white-space: normal;
      line-height: 1.35;
    }
    .officials-protected-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      background: #fff4db;
      color: #9a6700;
      font-size: 0.78rem;
      font-weight: 700;
    }
    .officials-access-shell {
      border: 1px solid #ece9e1;
      border-radius: 14px;
      padding: 14px;
      background: #fcfcfd;
    }
    .officials-access-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    .officials-access-label {
      font-size: 0.78rem;
      font-weight: 700;
      color: #6b7280;
      margin-bottom: 4px;
    }
    .officials-access-value {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      min-height: 44px;
      padding: 10px 12px;
      font-weight: 600;
      color: #111827;
    }
    .officials-access-groups {
      display: grid;
      gap: 14px;
      max-height: 48vh;
      overflow-y: auto;
      padding-right: 4px;
    }
    .officials-access-group {
      border: 1px solid #ececec;
      border-radius: 14px;
      background: #fff;
      overflow: hidden;
    }
    .officials-access-group-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 14px;
      border-bottom: 1px solid #f1f1f1;
      background: #faf7f2;
    }
    .officials-access-group-title {
      font-weight: 700;
      color: #2f3640;
    }
    .officials-access-group-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .officials-access-items {
      display: grid;
      gap: 8px;
      padding: 12px 14px 14px;
    }
    .officials-access-item {
      border: 1px solid #edf0f4;
      border-radius: 12px;
      padding: 10px 12px;
      background: #fff;
    }
    .officials-access-item.is-child {
      margin-left: 18px;
      background: #fcfcfd;
    }
    .officials-access-item label {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      cursor: pointer;
      width: 100%;
    }
    .officials-access-item input[type="checkbox"] {
      margin-top: 0.15rem;
      flex: 0 0 auto;
    }
    .officials-access-item-main {
      font-weight: 700;
      color: #111827;
      line-height: 1.3;
    }
    .officials-access-item-sub {
      display: block;
      margin-top: 2px;
      color: #6b7280;
      font-size: 0.8rem;
      line-height: 1.3;
    }
    .officials-access-item.is-disabled {
      opacity: 0.6;
    }
    .officials-access-search {
      max-width: 280px;
    }
    .officials-profile-avatar {
      width: clamp(120px, 18vw, 170px);
      height: clamp(120px, 18vw, 170px);
      object-fit: cover;
    }
    .officials-profile-feedback:empty {
      display: none;
    }
    .officials-profile-feedback.d-none {
      display: none !important;
    }
    .officials-profile-readonly .form-control,
    .officials-profile-readonly .form-select,
    .officials-profile-readonly textarea {
      background-color: #f8f9fa;
    }
    .officials-profile-system-note {
      border: 1px dashed #d8c9b6;
      border-radius: 12px;
      padding: 12px 14px;
      background: #fffaf4;
      color: #6b5a45;
      font-size: 0.9rem;
    }
    .officials-wide-modal,
    .officials-profile-modal-dialog {
      width: min(1500px, calc(100vw - 2rem));
      max-width: none;
    }
    .officials-profile-modal-content {
      width: 100%;
    }
    .officials-masterlist-table td:last-child {
      white-space: normal;
    }
    .officials-masterlist-table td:last-child .btn,
    .officials-masterlist-table td:last-child .dropdown,
    .officials-masterlist-table td:last-child .dropdown-toggle {
      max-width: 100%;
    }
    .officials-access-value,
    .officials-module-summary,
    #officialsMgmtAccessSummary,
    #officialsMgmtAccessModulesSummary,
    #officialsMgmtPromoteSummary,
    #officialsMgmtDepartmentSummary,
    #officialsMgmtDepartmentPosition,
    #officialsMgmtPromotePath,
    #officialsMgmtDepartmentPreview,
    #officialsMgmtProfileSummaryName,
    #officialsMgmtConfirmMessage {
      white-space: normal;
      word-break: break-word;
    }
    .officials-confirm-modal {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background: rgba(15, 23, 42, 0.58);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.18s ease, visibility 0.18s ease;
      z-index: 2070;
    }
    .officials-confirm-modal.show {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .officials-confirm-card {
      width: min(100%, 30rem);
      background: #fff;
      border-radius: 1.1rem;
      box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.2);
      padding: 1.35rem;
    }
    .officials-confirm-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 0.65rem;
    }
    #officialsMgmtConfirmMessage {
      margin: 0;
      color: #4b5563;
      white-space: pre-line;
    }
    .officials-confirm-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      margin-top: 1.25rem;
    }
    @media (max-width: 1199.98px) {
      .officials-masterlist-shell .officials-masterlist-table {
        min-width: 1180px;
      }
      .officials-wide-modal,
      .officials-profile-modal-dialog {
        width: calc(100vw - 1.5rem);
      }
    }
    @media (max-width: 991.98px) {
      .officials-masterlist-shell .officials-masterlist-table {
        min-width: 1040px;
      }
      #div-tableContainer {
        padding: 1rem !important;
        border-radius: 1rem !important;
      }
      .officials-access-group-head {
        flex-direction: column;
        align-items: stretch;
      }
      .officials-access-group-actions {
        width: 100%;
        justify-content: flex-start;
      }
      .officials-access-search {
        max-width: none;
        width: 100%;
      }
      .officials-profile-modal-content {
        padding: 1.25rem !important;
      }
      #modalOfficialsMgmtProfile .modal-body {
        padding-top: 0.5rem;
      }
    }
    @media (max-width: 767.98px) {
      #main-display {
        padding: 1rem !important;
      }
      .officials-page-header h2 {
        font-size: clamp(1.5rem, 6vw, 2rem);
      }
      .officials-masterlist-shell .table-responsive {
        margin: 0 -0.35rem;
        padding: 0 0.35rem 0.25rem;
      }
      .officials-masterlist-shell .officials-masterlist-table {
        min-width: 920px;
      }
      .officials-masterlist-shell .officials-masterlist-table td {
        max-width: 180px;
      }
      .officials-masterlist-table td:last-child > .d-flex {
        flex-direction: column;
        align-items: stretch !important;
        width: 100%;
      }
      .officials-masterlist-table td:last-child .btn,
      .officials-masterlist-table td:last-child .dropdown,
      .officials-masterlist-table td:last-child .dropdown-toggle {
        width: 100%;
      }
      .officials-access-item.is-child {
        margin-left: 0;
      }
      .officials-access-groups {
        max-height: 42vh;
      }
      .officials-wide-modal,
      .officials-profile-modal-dialog {
        width: calc(100vw - 1rem);
        margin: 0.5rem auto;
      }
      .officials-profile-modal-content {
        padding: 1rem !important;
      }
      .officials-profile-avatar {
        width: 108px;
        height: 108px;
      }
      #modalOfficialsMgmtProfile .modal-footer,
      #modalOfficialsMgmtAccess .modal-footer,
      #modalOfficialsMgmtPromote .modal-footer,
      #modalOfficialsMgmtDepartment .modal-footer,
      #modalOfficialsMgmtFilter .modal-footer,
      #modalOfficialsMgmtColumns .modal-footer,
      .officials-confirm-actions {
        flex-direction: column-reverse;
        align-items: stretch;
      }
      #modalOfficialsMgmtProfile .modal-footer .btn,
      #modalOfficialsMgmtAccess .modal-footer .btn,
      #modalOfficialsMgmtPromote .modal-footer .btn,
      #modalOfficialsMgmtDepartment .modal-footer .btn,
      #modalOfficialsMgmtFilter .modal-footer .btn,
      #modalOfficialsMgmtColumns .modal-footer .btn,
      .officials-confirm-actions .btn {
        width: 100%;
      }
    }
    @media (max-width: 575.98px) {
      .officials-masterlist-shell .officials-masterlist-table {
        min-width: 840px;
      }
      .officials-masterlist-shell .officials-masterlist-table td {
        max-width: 150px;
      }
      #modalOfficialsMgmtProfile .modal-title {
        font-size: 1rem;
        line-height: 1.4;
      }
      #modalOfficialsMgmtProfile .tracker-form-section,
      #modalOfficialsMgmtAccess .officials-access-shell {
        padding: 0.85rem !important;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include "includes/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <?php if ($managementMode === 'official'): ?>
        <header class="official-workspace-header">
          <div>
            <h1 class="official-workspace-title"><?= htmlspecialchars($managementPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="official-workspace-description"><?= htmlspecialchars($managementDescription, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <div class="official-workspace-header__actions">
            <a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?panel=seat'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="fas fa-user-plus me-1"></i> Add &amp; Assign Official
            </a>
          </div>
        </header>
      <?php else: ?>
        <div class="mb-4 officials-page-header">
          <h2 class="mb-2" style="font-family: 'Charis SIL Bold'; color: #DE710C; "><?= htmlspecialchars($managementPageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="text-muted mb-0"><?= htmlspecialchars($managementDescription, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <hr><br>
      <?php endif; ?>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell officials-masterlist-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <?php if ($managementShowLifecycleTabs): ?>
          <div class="admin-list-tabs">
            <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-lifecycle-filter="current">&nbsp;&nbsp;<?= htmlspecialchars($managementCurrentLabel, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-lifecycle-filter="past">&nbsp;&nbsp;<?= htmlspecialchars($managementPastLabel, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;</button>
          </div>
          <?php endif; ?>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
            <button id="btnOfficialsMgmtSendAllInvites" class="btn btn-outline-primary btn-sm" type="button" title="Send onboarding invitations for profiles marked ready">
              <i class="fas fa-paper-plane me-1"></i> Send Ready Invites
            </button>
            <div class="input-group admin-search">
              <input id="officialsMgmtSearch" class="form-control" placeholder="<?= htmlspecialchars($managementSearchPlaceholder, ENT_QUOTES, 'UTF-8') ?>" />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnOfficialsMgmtFilter" class="btn btn-outline-secondary btn-icon admin-filter" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#modalOfficialsMgmtFilter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button id="btnOfficialsMgmtColumns" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#modalOfficialsMgmtColumns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <button id="btnOfficialsMgmtRefresh" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
          </div>
        </div>

        <div class="table-responsive compact-admin-table-shell">
          <table id="table-officialsMgmt" class="table table-hover align-middle mb-0 officials-masterlist-table compact-admin-table compact-admin-table--wide">
            <thead class="table-light">
              <tr>
                <th><?= htmlspecialchars($managementIdLabel, ENT_QUOTES, 'UTF-8') ?></th>
                <th>User ID</th>
                <th>Name</th>
                <th>Access Level</th>
                <th>Position Access</th>
                <th>Department</th>
                <th>Access Until</th>
                <th>Account Status</th>
                <th>Modules</th>
                <th>Profile Approval</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="officialsMgmtTbody">
              <tr><td colspan="11" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="officialsMgmtEntriesInput" class="small text-muted mb-0">Entries</label>
            <input id="officialsMgmtEntriesInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="Officials management pagination">
            <ul class="pagination pagination-sm mb-0" id="officialsMgmtPagination"></ul>
          </nav>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="modalOfficialsMgmtFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold"><?= htmlspecialchars($managementFilterTitle, ENT_QUOTES, 'UTF-8') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <div>
            <label for="officialsMgmtRoleFilter" class="form-label small fw-bold mb-1">Access Level</label>
            <select id="officialsMgmtRoleFilter" class="form-select">
              <?php foreach ($managementFilterRoleOptions as $roleValue => $roleLabel): ?>
              <option value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="officialsMgmtPermissionFilter" class="form-label small fw-bold mb-1">Permission</label>
            <select id="officialsMgmtPermissionFilter" class="form-select">
              <option value="ALL">All</option>
              <option value="Active">Active</option>
              <option value="Revoked">Revoked</option>
            </select>
          </div>
          <div>
            <label for="officialsMgmtDepartmentFilter" class="form-label small fw-bold mb-1">Department</label>
            <select id="officialsMgmtDepartmentFilter" class="form-select">
              <option value="ALL">All departments</option>
            </select>
          </div>
          <div>
            <label for="officialsMgmtEmploymentFilter" class="form-label small fw-bold mb-1">Employment Status</label>
            <select id="officialsMgmtEmploymentFilter" class="form-select">
              <option value="ALL">All employment statuses</option>
            </select>
          </div>
          <div>
            <label for="officialsMgmtAccountFilter" class="form-label small fw-bold mb-1">Account Status</label>
            <select id="officialsMgmtAccountFilter" class="form-select">
              <option value="ALL">All account statuses</option>
            </select>
          </div>
          <div>
            <label for="officialsMgmtApprovalFilter" class="form-label small fw-bold mb-1">Profile Approval</label>
            <select id="officialsMgmtApprovalFilter" class="form-select">
              <option value="ALL">All approval states</option>
              <option value="Approved">Approved</option>
              <option value="PendingApproval">Pending Approval</option>
              <option value="Rejected">Rejected</option>
              <option value="Onboarding">Onboarding</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" id="btnOfficialsMgmtFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="btnOfficialsMgmtFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalOfficialsMgmtColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="officialsMgmtColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnOfficialsMgmtColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalOfficialsMgmtAccess" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable officials-wide-modal">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold"><?= htmlspecialchars($managementAccessTitle, ENT_QUOTES, 'UTF-8') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <input type="hidden" id="officialsMgmtAccessOfficialId">

          <div class="officials-access-shell">
            <div class="officials-access-meta">
              <div>
                <div class="officials-access-label"><?= htmlspecialchars($managementSubjectLabel, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="officials-access-value" id="officialsMgmtAccessSummary">-</div>
              </div>
              <div>
                <label for="officialsMgmtAccessRole" class="officials-access-label">Display Role</label>
                <select id="officialsMgmtAccessRole" class="form-select">
                  <?php foreach ($managementAccessRoleOptions as $roleValue => $roleLabel): ?>
                  <option value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="officialsMgmtAccessExpiry" class="officials-access-label">Access Expires On</label>
                <input type="date" id="officialsMgmtAccessExpiry" class="form-control" data-date-modal-style="calendar">
              </div>
              <div>
                <div class="officials-access-label">Current Modules</div>
                <div class="officials-access-value" id="officialsMgmtAccessModulesSummary">-</div>
              </div>
            </div>
          </div>

          <div id="officialsMgmtAccessProtectedNotice" class="alert alert-warning d-none mb-0"></div>

          <div class="officials-access-shell">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
              <div>
                <div class="fw-bold">Module Checklist</div>
                <div class="small text-muted">This checklist mirrors the sidebar labels. Checked items stay visible and accessible.</div>
              </div>
              <div class="input-group officials-access-search">
                <input id="officialsMgmtPermissionSearch" class="form-control" placeholder="Search module labels">
                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
              </div>
            </div>
            <div id="officialsMgmtPermissionGroups" class="officials-access-groups"></div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnOfficialsMgmtAccessSubmit">Save Access</button>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isPersonnelManagement): ?>
  <div class="modal fade" id="modalOfficialsMgmtPromote" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold"><?= htmlspecialchars($managementPromoteTitle, ENT_QUOTES, 'UTF-8') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <input type="hidden" id="officialsMgmtPromoteOfficialId">
          <div>
            <label class="form-label small fw-bold mb-1"><?= htmlspecialchars($managementSubjectLabel, ENT_QUOTES, 'UTF-8') ?></label>
            <div id="officialsMgmtPromoteSummary" class="form-control bg-light">-</div>
          </div>
          <div>
            <label class="form-label small fw-bold mb-1"><?= htmlspecialchars($managementPromotionPathLabel, ENT_QUOTES, 'UTF-8') ?></label>
            <div id="officialsMgmtPromotePath" class="form-control bg-light">-</div>
          </div>
          <div>
            <label for="officialsMgmtPromotePosition" class="form-label small fw-bold mb-1">New Position Access</label>
            <select id="officialsMgmtPromotePosition" class="form-select"></select>
          </div>
          <div id="officialsMgmtPromoteAreaWrap" class="d-none">
            <label for="officialsMgmtPromoteArea" class="form-label small fw-bold mb-1">Area Coverage</label>
            <select id="officialsMgmtPromoteArea" class="form-select">
              <option value="">Select area</option>
              <?php foreach ($officialsMgmtAreaOptions as $areaOption): ?>
                <option value="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="small text-muted"><?= htmlspecialchars($managementPromotionHelper, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnOfficialsMgmtPromoteSubmit">Save Promotion</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalOfficialsMgmtDepartment" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Change Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <input type="hidden" id="officialsMgmtDepartmentOfficialId">
          <div>
            <label class="form-label small fw-bold mb-1"><?= htmlspecialchars($managementSubjectLabel, ENT_QUOTES, 'UTF-8') ?></label>
            <div id="officialsMgmtDepartmentSummary" class="form-control bg-light">-</div>
          </div>
          <div>
            <label class="form-label small fw-bold mb-1">Current Position</label>
            <div id="officialsMgmtDepartmentPosition" class="form-control bg-light">-</div>
          </div>
          <div>
            <label for="officialsMgmtDepartmentSelect" class="form-label small fw-bold mb-1">New Department</label>
            <select id="officialsMgmtDepartmentSelect" class="form-select">
              <option value="">Select department</option>
              <?php foreach ($officialsMgmtDepartmentOptions as $departmentOption): ?>
                <option value="<?= htmlspecialchars($departmentOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($departmentOption, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="officialsMgmtDepartmentNewPosition" class="form-label small fw-bold mb-1">New Position</label>
            <select id="officialsMgmtDepartmentNewPosition" class="form-select">
              <option value="">Select new position</option>
            </select>
          </div>
          <div id="officialsMgmtDepartmentAreaWrap" class="d-none">
            <label for="officialsMgmtDepartmentArea" class="form-label small fw-bold mb-1">Area Coverage</label>
            <select id="officialsMgmtDepartmentArea" class="form-select">
              <option value="">Select area</option>
              <?php foreach ($officialsMgmtAreaOptions as $areaOption): ?>
                <option value="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="officialsMgmtDepartmentPreview" class="small text-muted"></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnOfficialsMgmtDepartmentSubmit">Save Department</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div id="officialsMgmtConfirmModal" class="officials-confirm-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="officialsMgmtConfirmTitle" aria-describedby="officialsMgmtConfirmMessage" tabindex="-1">
    <div class="officials-confirm-card">
      <div class="officials-confirm-title" id="officialsMgmtConfirmTitle">Confirm Action</div>
      <p id="officialsMgmtConfirmMessage">Are you sure you want to continue?</p>
      <div class="officials-confirm-actions">
        <button type="button" class="btn btn-outline-secondary" id="btnOfficialsMgmtConfirmCancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnOfficialsMgmtConfirmOk">Confirm</button>
      </div>
    </div>
  </div>

  <?php if ($supportsProfileManagement): ?>
  <div class="modal fade" id="modalOfficialsMgmtProfile" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable officials-profile-modal-dialog">
      <form class="modal-content border-0 rounded-2 p-4 officials-profile-modal-content" id="formOfficialsMgmtProfile">
        <div class="modal-header border-0">
          <h5 class="modal-title"><?= htmlspecialchars($managementEntitySingular, ENT_QUOTES, 'UTF-8') ?> Details: <span id="officialsMgmtProfileDisplayId" class="text-warning"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="officialsMgmtProfileOfficialId">
          <div id="officialsMgmtProfileFeedback" class="alert officials-profile-feedback d-none mb-3" role="alert"></div>
          <div id="officialsMgmtProfileReadonlyNotice" class="alert alert-warning d-none mb-3" role="alert"></div>

          <div class="tracker-profile-view">
            <div class="p-3 rounded-3 mb-3 border-0 bg-white tracker-form-section">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0 tracker-form-section-title">Personal Information</h5>
              </div>

              <div class="row g-3 align-items-center">
                <div class="col-md-3 d-flex flex-column justify-content-center align-items-center">
                  <img
                    id="officialsMgmtProfileImage"
                    src="../Images/Profile-Placeholder.png"
                    alt="<?= htmlspecialchars($managementEntitySingular, ENT_QUOTES, 'UTF-8') ?> profile image"
                    class="img-fluid rounded-circle officials-profile-avatar"
                  >
                  <div class="small text-muted mt-3 text-center" id="officialsMgmtProfileSummaryName"><?= htmlspecialchars($managementEntitySingular, ENT_QUOTES, 'UTF-8') ?> profile</div>
                </div>

                <div class="col-md-9">
                  <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                      <label for="officialsMgmtProfileLastName" class="form-label small fw-bold mb-1">Last Name</label>
                      <input type="text" class="form-control" id="officialsMgmtProfileLastName" maxlength="100" required>
                    </div>
                    <div class="col-md-6 col-lg-3">
                      <label for="officialsMgmtProfileFirstName" class="form-label small fw-bold mb-1">First Name</label>
                      <input type="text" class="form-control" id="officialsMgmtProfileFirstName" maxlength="100" required>
                    </div>
                    <div class="col-md-6 col-lg-3">
                      <label for="officialsMgmtProfileMiddleName" class="form-label small fw-bold mb-1">Middle Name</label>
                      <input type="text" class="form-control" id="officialsMgmtProfileMiddleName" maxlength="100">
                    </div>
                    <div class="col-md-6 col-lg-3">
                      <label for="officialsMgmtProfileSuffix" class="form-label small fw-bold mb-1">Suffix</label>
                      <input type="text" class="form-control" id="officialsMgmtProfileSuffix" maxlength="20">
                    </div>

                    <div class="col-md-4">
                      <label for="officialsMgmtProfileBirthdate" class="form-label small fw-bold mb-1">Birthdate</label>
                      <input type="date" class="form-control" id="officialsMgmtProfileBirthdate" max="<?= date('Y-m-d') ?>" data-date-modal-style="calendar" required>
                    </div>
                    <div class="col-md-4">
                      <label for="officialsMgmtProfileSex" class="form-label small fw-bold mb-1">Sex</label>
                      <select class="form-select" id="officialsMgmtProfileSex" required>
                        <option value="">Select sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label for="officialsMgmtProfileCivilStatus" class="form-label small fw-bold mb-1">Civil Status</label>
                      <select class="form-select" id="officialsMgmtProfileCivilStatus" required>
                        <option value="">Select civil status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Separated">Separated</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <hr class="my-2">

            <div class="p-3 rounded-3 mb-3 border-0 bg-white tracker-form-section">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0 tracker-form-section-title">Contact Information</h5>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="officialsMgmtProfilePhone" class="form-label small fw-bold mb-1">Mobile Number</label>
                  <input type="text" class="form-control" id="officialsMgmtProfilePhone" maxlength="13" placeholder="09XXXXXXXXX" required>
                </div>
                <div class="col-md-6">
                  <label for="officialsMgmtProfileEmail" class="form-label small fw-bold mb-1">Email Address</label>
                  <input type="email" class="form-control" id="officialsMgmtProfileEmail" maxlength="255" required>
                </div>
              </div>
            </div>

            <hr class="my-2">

            <div class="p-3 rounded-3 mb-3 border-0 bg-white tracker-form-section">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0 tracker-form-section-title">Emergency Contact</h5>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label for="officialsMgmtProfileEmergencyName" class="form-label small fw-bold mb-1">Full Name</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileEmergencyName" maxlength="150">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileEmergencyRelationship" class="form-label small fw-bold mb-1">Relationship</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileEmergencyRelationship" maxlength="80">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileEmergencyPhone" class="form-label small fw-bold mb-1">Mobile Number</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileEmergencyPhone" maxlength="13" placeholder="09XXXXXXXXX">
                </div>
                <div class="col-12">
                  <label for="officialsMgmtProfileEmergencyAddress" class="form-label small fw-bold mb-1">Address</label>
                  <textarea class="form-control" id="officialsMgmtProfileEmergencyAddress" rows="3" maxlength="255"></textarea>
                </div>
              </div>
            </div>

            <hr class="my-2">

            <div class="p-3 rounded-3 mb-3 border-0 bg-white tracker-form-section">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0 tracker-form-section-title">Address Information</h5>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label for="officialsMgmtProfileAddressMode" class="form-label small fw-bold mb-1">Address Mode</label>
                  <select class="form-select" id="officialsMgmtProfileAddressMode">
                    <option value="street">House / Street</option>
                    <option value="block_lot">Block / Lot</option>
                  </select>
                </div>
                <div class="col-md-4 officials-profile-address-street">
                  <label for="officialsMgmtProfileHouseNumber" class="form-label small fw-bold mb-1">House Number</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileHouseNumber" maxlength="50">
                </div>
                <div class="col-md-4 officials-profile-address-street">
                  <label for="officialsMgmtProfileStreetName" class="form-label small fw-bold mb-1">Street Name</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileStreetName" maxlength="150">
                </div>
                <div class="col-md-4 officials-profile-address-blocklot d-none">
                  <label for="officialsMgmtProfileBlockNumber" class="form-label small fw-bold mb-1">Block Number</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileBlockNumber" maxlength="50">
                </div>
                <div class="col-md-4 officials-profile-address-blocklot d-none">
                  <label for="officialsMgmtProfileLotNumber" class="form-label small fw-bold mb-1">Lot Number</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileLotNumber" maxlength="50">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileSubdivision" class="form-label small fw-bold mb-1">Subdivision</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileSubdivision" maxlength="150">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileBarangay" class="form-label small fw-bold mb-1">Barangay</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileBarangay" maxlength="150">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileCity" class="form-label small fw-bold mb-1">Municipality / City</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileCity" maxlength="150">
                </div>
                <div class="col-md-4">
                  <label for="officialsMgmtProfileProvince" class="form-label small fw-bold mb-1">Province</label>
                  <input type="text" class="form-control" id="officialsMgmtProfileProvince" maxlength="150">
                </div>
              </div>
            </div>

            <hr class="my-2">

            <div class="p-3 rounded-3 mb-2 border-0 bg-white tracker-form-section">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0 tracker-form-section-title">Assignment Overview</h5>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Display Role</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileDisplayRole">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Position Access</label>
                  <div class="form-control bg-light" id="officialsMgmtProfilePosition">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Department</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileDepartment">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Area Coverage</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileArea">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Date Hired</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileDateHired">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Employment Status</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileEmploymentStatus">-</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold mb-1">Account Status</label>
                  <div class="form-control bg-light" id="officialsMgmtProfileAccountStatus">-</div>
                </div>
              </div>

              <div class="officials-profile-system-note mt-3">
                <?php if ($isPersonnelManagement): ?>
                  Assignment and access changes stay in the dedicated <strong>Manage Access</strong>, <strong>Promote</strong>, and <strong>Change Department</strong> actions.
                <?php else: ?>
                  Access changes stay in the dedicated <strong>Manage Access</strong> action.
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 d-flex justify-content-between flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary px-4" id="btnOfficialsMgmtProfileSave">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
      tableSelector: "#table-officialsMgmt",
      modalId: "modalOfficialsMgmtColumns",
      listId: "officialsMgmtColumnsList",
      resetBtnId: "btnOfficialsMgmtColumnsReset",
      storageKey: <?= json_encode($managementColumnsStorageKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      defaultHiddenIdxs: <?= json_encode($managementDefaultHiddenColumnIdxs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    window.OFFICIALS_MGMT_OPTIONS = {
      managementMode: <?= json_encode($managementMode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      showLifecycleTabs: <?= json_encode($managementShowLifecycleTabs) ?>,
      supportsProfileManagement: <?= json_encode($supportsProfileManagement) ?>,
      entitySingular: <?= json_encode($managementEntitySingular, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      entityPluralLower: <?= json_encode(strtolower($managementEntityPlural), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      apiUrl: "../PhpFiles/Admin-End/officialsManagement.php",
      emptyCurrentMessage: <?= json_encode($managementShowLifecycleTabs ? ('No current ' . strtolower($managementEntityPlural) . ' found.') : ('No ' . strtolower($managementEntityPlural) . ' found.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      emptyPastMessage: <?= json_encode('No past ' . strtolower($managementEntityPlural) . ' found.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      loadFailureMessage: <?= json_encode('Failed to load ' . strtolower($managementEntityPlural) . '.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      loadUnavailableMessage: <?= json_encode('Unable to load ' . strtolower($managementEntityPlural) . '.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      departments: <?= json_encode(array_values($officialsMgmtDepartmentOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      positionsByRole: <?= json_encode($officialsMgmtPositionsByRole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      positionsByDepartment: <?= json_encode($officialsMgmtPositionsByDepartment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      areaRequiredPositions: <?= json_encode(array_values($officialsMgmtAreaRequiredPositions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      areaOptions: <?= json_encode(array_values($officialsMgmtAreaOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      permissionCatalog: <?= json_encode($officialsMgmtPermissionCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script>
    document.getElementById('btnOfficialsMgmtSendAllInvites')?.addEventListener('click', async () => {
      if (typeof window.requestOfficialsManagementSecureConfirmation !== 'function') {
        window.alert('Secure confirmation is not ready right now.');
        return;
      }
      try {
        const secureConfirmation = await window.requestOfficialsManagementSecureConfirmation(
          'send_all_invites',
          '',
          'send all ready onboarding invites'
        );
        if (!secureConfirmation) return;
        const body = new URLSearchParams();
        body.set('action', 'send_all_invites');
        body.set('mode', <?= json_encode($managementMode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        body.set('challenge_key', secureConfirmation.challenge_key || '');
        body.set('otp_code', secureConfirmation.otp_code || '');
        const res = await fetch('../PhpFiles/Admin-End/officialsManagement.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body,
        });
        const data = await res.json();
        window.alert(data.message || (data.success ? 'Invites sent.' : 'Unable to send invites.'));
      } catch (error) {
        window.alert('Unable to send invites right now.');
      }
    });
  </script>
  <script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
  <script src="../JS-Script-Files/Admin-End/officialsManagementScript.js?v=20260815-modal-focus-1"></script>
</body>
</html>
