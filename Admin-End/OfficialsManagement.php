<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";
require_once "../PhpFiles/General/adminModulePermissions.php";

requireRoleSession(['SuperAdmin'], false);

$managementMode = strtolower(trim((string)($managementMode ?? 'official')));
if (!in_array($managementMode, ['official', 'personnel'], true)) {
  $managementMode = 'official';
}
$isPersonnelManagement = $managementMode === 'personnel';
$managementShowLifecycleTabs = !$isPersonnelManagement;

$managementEntitySingular = $isPersonnelManagement ? 'Personnel' : 'Official';
$managementEntityPlural = $isPersonnelManagement ? 'Personnel' : 'Officials';
$managementPageTitle = $isPersonnelManagement ? 'Personnel Tracker' : 'Official Management';
$managementDescription = $isPersonnelManagement
  ? 'Track all personnel records in one list, including SuperAdmin accounts. Access changes stay inside the checklist modal.'
  : 'Track current and past barangay officials. Current officials are shown by default, and access changes stay inside the checklist modal.';
$managementCurrentLabel = $isPersonnelManagement ? 'Current Personnel' : 'Current Officials';
$managementPastLabel = $isPersonnelManagement ? 'Past Personnel' : 'Past Officials';
$managementSearchPlaceholder = $isPersonnelManagement
  ? 'Search personnel ID / user ID / name / department'
  : 'Search official ID / user ID / name / department';
$managementIdLabel = $isPersonnelManagement ? 'Personnel ID' : 'Official ID';
$managementFilterTitle = $isPersonnelManagement ? 'Filter Personnel Tracker' : 'Filter Official Management';
$managementAccessTitle = $isPersonnelManagement ? 'Manage Personnel Access' : 'Manage Official Access';
$managementPromoteTitle = $isPersonnelManagement ? 'Promote Personnel' : 'Promote Official';
$managementSubjectLabel = $managementEntitySingular;
$managementPromotionPathLabel = $isPersonnelManagement ? 'Position Change Path' : 'Promotion Path';
$managementPromotionHelper = $isPersonnelManagement
  ? "This update changes the personnel's system role, position access, and assignment details."
  : "Promotion updates the official's system role, position access, and assignment details.";
$managementColumnsStorageKey = $isPersonnelManagement
  ? 'admin_cols_personnel_management_v2'
  : 'admin_cols_officials_management_v2';
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
  <style>
    #main-display {
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
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include "includes/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <div class="mb-4">
        <h2 class="mb-2" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
          <?= htmlspecialchars($managementPageTitle, ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="text-muted mb-0">
          <?= htmlspecialchars($managementDescription, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
      <hr><br>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell officials-masterlist-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <?php if ($managementShowLifecycleTabs): ?>
          <div class="admin-list-tabs">
            <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-lifecycle-filter="current">&nbsp;&nbsp;<?= htmlspecialchars($managementCurrentLabel, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-lifecycle-filter="past">&nbsp;&nbsp;<?= htmlspecialchars($managementPastLabel, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;</button>
          </div>
          <?php endif; ?>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
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
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
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
                <input type="date" id="officialsMgmtAccessExpiry" class="form-control">
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
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
  <script src="../JS-Script-Files/Admin-End/officialsManagementScript.js?v=20260324-4"></script>
</body>
</html>
