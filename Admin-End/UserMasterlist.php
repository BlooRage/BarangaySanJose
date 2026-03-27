<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Masterlist</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    #main-display {
      min-width: 0;
    }
    .user-masterlist-shell {
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .user-masterlist-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .user-masterlist-shell .user-masterlist-table {
      min-width: 1280px;
    }
    .user-masterlist-shell .user-masterlist-table th:nth-child(2),
    .user-masterlist-shell .user-masterlist-table td:nth-child(2) {
      min-width: 220px;
      white-space: nowrap;
    }
    .user-masterlist-shell .user-masterlist-table th:nth-child(8),
    .user-masterlist-shell .user-masterlist-table td:nth-child(8) {
      min-width: 180px;
      white-space: nowrap;
    }
    .user-masterlist-shell td:nth-child(6),
    .user-masterlist-shell td:nth-child(7) {
      white-space: nowrap;
    }
    .user-masterlist-shell .user-masterlist-table th:last-child,
    .user-masterlist-shell .user-masterlist-table td:last-child {
      min-width: 250px;
    }
    .user-masterlist-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .user-lock-summary-card {
      border: 1px solid #ece7df;
      border-radius: 16px;
      padding: 16px 18px;
      background: #faf8f4;
    }
    .user-lock-summary-card .summary-label {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #7b7280;
    }
    .user-lock-summary-card .summary-value {
      font-weight: 700;
      color: #2f3640;
    }
    .user-lock-option {
      border: 1px solid #e7dfd4;
      border-radius: 14px;
      padding: 12px 14px;
      background: #fff;
    }
    .user-lock-option:has(input:checked) {
      border-color: #de710c;
      box-shadow: 0 0 0 2px rgba(222, 113, 12, 0.12);
      background: #fffaf5;
    }
    .user-lock-option input[type="radio"] {
      margin-top: 0.15rem;
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php
      require_once "../PhpFiles/General/connection.php";
      require_once "includes/admin_guard.php";
      requireRoleSession(['SuperAdmin'], false);
      include "includes/sidebar.php";
    ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        User Masterlist
      </h2>
      <hr><br>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell user-masterlist-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs">
            <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif" data-filter="Pending">
              &nbsp;&nbsp;Pending
              <span id="pendingUserBadge" class="pending-count-badge d-none">0</span>
            </button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-filter="Verified">&nbsp;&nbsp;Verified&nbsp;&nbsp;</button>
          </div>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
            <div class="input-group admin-search">
              <input id="userMasterSearch" class="form-control" placeholder="Search user ID / name / email / phone" />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <select id="userMasterRoleFilter" class="form-select" style="max-width: 190px;">
              <option value="ALL">All Roles</option>
              <option value="Resident">Resident</option>
              <option value="Official">Official</option>
              <option value="Personnel">Personnel</option>
              <option value="SuperAdmin">SuperAdmin</option>
            </select>
            <button id="btnUserMasterRefresh" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
            <a href="<?= htmlspecialchars(appUrl('Admin-End/UserArchive.php')) ?>" class="btn btn-outline-dark">
              User Archive
            </a>
          </div>
        </div>

        <div class="table-responsive compact-admin-table-shell">
          <table class="table table-hover align-middle mb-0 user-masterlist-table compact-admin-table compact-admin-table--wide">
            <thead class="table-light">
              <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Account Status</th>
                <th>Account Verification</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="userMasterTbody">
              <tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="userMasterEntriesInput" class="small text-muted mb-0">Entries</label>
            <input id="userMasterEntriesInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="User masterlist pagination">
            <ul class="pagination pagination-sm mb-0" id="userMasterPagination"></ul>
          </nav>
        </div>
      </div>

      <div class="modal fade" id="userLockModal" tabindex="-1" aria-labelledby="userLockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
              <h5 class="modal-title" id="userLockModalLabel">Manage Account Lock</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div id="userLockFeedback" class="alert d-none mb-3" role="alert"></div>

              <div class="user-lock-summary-card mb-3">
                <div class="summary-label">User</div>
                <div class="summary-value" id="userLockSummaryName">—</div>
                <div class="text-muted small" id="userLockSummaryMeta">—</div>
                <div class="mt-3">
                  <div class="summary-label">Current Status</div>
                  <div class="summary-value" id="userLockCurrentStatus">—</div>
                </div>
                <div class="mt-3">
                  <div class="summary-label">Current Reason</div>
                  <div class="text-muted small mb-0" id="userLockCurrentReason">No lock reason saved.</div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold d-block">Lock Type</label>
                <div class="d-grid gap-2">
                  <label class="user-lock-option">
                    <div class="d-flex gap-2">
                      <input class="form-check-input" type="radio" name="userLockMode" id="userLockModeTemporary" value="temporary" checked>
                      <div>
                        <div class="fw-semibold">Temporary lock</div>
                        <div class="text-muted small">Set the exact date and time when the account should unlock automatically.</div>
                      </div>
                    </div>
                  </label>
                  <label class="user-lock-option">
                    <div class="d-flex gap-2">
                      <input class="form-check-input" type="radio" name="userLockMode" id="userLockModePermanent" value="permanent">
                      <div>
                        <div class="fw-semibold">Permanent lock</div>
                        <div class="text-muted small">Keep the account locked until a SuperAdmin unlocks it manually.</div>
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              <div class="mb-3" id="userLockUntilWrapper">
                <label for="userLockUntil" class="form-label fw-semibold">Lock Until</label>
                <input type="datetime-local" class="form-control" id="userLockUntil">
                <div class="form-text">Use Manila time when choosing the unlock schedule.</div>
              </div>

              <div class="mb-0">
                <label for="userLockReason" class="form-label fw-semibold">Reason</label>
                <textarea class="form-control" id="userLockReason" rows="3" maxlength="255" placeholder="Optional note for the audit log and future admins."></textarea>
                <div class="form-text">Optional, up to 255 characters.</div>
              </div>
            </div>
            <div class="modal-footer justify-content-between">
              <button type="button" class="btn btn-outline-success d-none" id="btnUserUnlockAccount">Unlock Account</button>
              <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="btnUserSaveLock">Apply Lock</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../JS-Script-Files/Admin-End/userMasterlistScript.js?v=20260327-2"></script>
</body>
</html>
