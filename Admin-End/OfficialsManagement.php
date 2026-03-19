<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";

requireRoleSession(['SuperAdmin'], false);

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

$officialsMgmtPositionsByRole = [
  'SuperAdmin' => ['IT Administrator', 'Barangay Chairman'],
  'Official' => ['Barangay Official', 'Barangay Secretary'],
  'Personnel' => [
    'Department Public Assistance Desk',
    'Department Secretary',
    'Department OIC (Officer In Charge)',
    'Barangay Police',
    'Desk Officer',
    'Area OIC',
  ],
];
$officialsMgmtAreaRequiredPositions = ['Barangay Secretary', 'Barangay Police', 'Desk Officer', 'Area OIC'];
$officialsMgmtPositionsByDepartment = [
  'Office of the Barangay'       => ['IT Administrator', 'Barangay Chairman', 'Barangay Official', 'Barangay Secretary'],
  'Barangay Peace and Order'     => ['Barangay Police', 'Desk Officer', 'Area OIC', 'Department OIC (Officer In Charge)'],
  'Barangay Certificate Issuance'=> ['Department Public Assistance Desk', 'Department Secretary', 'Department OIC (Officer In Charge)'],
  'Baranagay Monitoring'         => ['Department Public Assistance Desk', 'Department Secretary', 'Department OIC (Officer In Charge)'],
  'Barangay Treasurers Office'   => ['Department Public Assistance Desk', 'Department Secretary', 'Department OIC (Officer In Charge)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Officials Management</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
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
      min-width: 1200px;
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
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include "includes/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        Officials Management
      </h2>
      <hr><br>

      <div class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell officials-masterlist-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs">
            <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">All</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="SuperAdmin">SuperAdmin</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="Official">Official</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="Personnel">Personnel</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-permission-filter="Active">Active</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn has-notif" data-permission-filter="Revoked">
              Revoked
              <span id="revokedOfficialsBadge" class="pending-count-badge d-none">0</span>
            </button>
          </div>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
            <div class="input-group admin-search">
              <input id="officialsMgmtSearch" class="form-control" placeholder="Search official ID / user ID / name / department" />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnOfficialsMgmtFilter" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#modalOfficialsMgmtFilter">
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
            <span id="officialsMgmtAutoRefreshCountdown" class="small text-muted"></span>
          </div>
        </div>

        <div class="table-responsive">
          <table id="table-officialsMgmt" class="table table-hover align-middle mb-0 officials-masterlist-table">
            <thead class="table-light">
              <tr>
                <th>Official ID</th>
                <th>User ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Position Access</th>
                <th>Department</th>
                <th>Employment Status</th>
                <th>Date Hired</th>
                <th>Account Status</th>
                <th>Permissions</th>
                <th>Profile Approval</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="officialsMgmtTbody">
              <tr><td colspan="12" class="text-center text-muted py-4">Loading...</td></tr>
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
          <h5 class="modal-title fw-bold">Filter Officials</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <div>
            <label for="officialsMgmtRoleFilter" class="form-label small fw-bold mb-1">Role</label>
            <select id="officialsMgmtRoleFilter" class="form-select">
              <option value="ALL">All</option>
              <option value="SuperAdmin">SuperAdmin</option>
              <option value="Official">Official</option>
              <option value="Personnel">Personnel</option>
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

  <div class="modal fade" id="modalOfficialsMgmtPromote" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Promote Official</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <input type="hidden" id="officialsMgmtPromoteOfficialId">
          <div>
            <label class="form-label small fw-bold mb-1">Official</label>
            <div id="officialsMgmtPromoteSummary" class="form-control bg-light">-</div>
          </div>
          <div>
            <label class="form-label small fw-bold mb-1">Promotion Path</label>
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
          <div class="small text-muted">Promotion updates the official's system role, position access, and assignment details.</div>
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
            <label class="form-label small fw-bold mb-1">Official</label>
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
      storageKey: "admin_cols_officials_management_v1"
    };
    window.OFFICIALS_MGMT_OPTIONS = {
      departments: <?= json_encode(array_values($officialsMgmtDepartmentOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      positionsByRole: <?= json_encode($officialsMgmtPositionsByRole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      positionsByDepartment: <?= json_encode($officialsMgmtPositionsByDepartment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      areaRequiredPositions: <?= json_encode(array_values($officialsMgmtAreaRequiredPositions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      areaOptions: <?= json_encode(array_values($officialsMgmtAreaOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
  <script src="../JS-Script-Files/Admin-End/officialsManagementScript.js?v=20260317-2"></script>
</body>
</html>
