<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";

requireRoleSession(['SuperAdmin'], false);

// ── Council seats (source of truth for the transition module) ────────────────
$councilSeats = [];
$hasCouncilTbl = $conn->query("SHOW TABLES LIKE 'barangaycounciltbl'")->num_rows > 0;
if ($hasCouncilTbl) {
    $csRes = $conn->query("
        SELECT bc.council_id, bc.seat_name, bc.selection_method, bc.seat_group,
               bc.sort_order, bc.term_start, bc.term_end,
               bc.current_official_id,
               CONCAT(oi.lastname, ', ', oi.firstname,
                      IFNULL(CONCAT(' ', oi.middlename),''),
                      IFNULL(CONCAT(' ', oi.suffix),''))  AS current_official_name,
               COALESCE(sa.status_name,'')                 AS account_status
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi
               ON oi.official_id = bc.current_official_id
        LEFT JOIN useraccountstbl ua
               ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        WHERE bc.is_active = 1
        ORDER BY bc.sort_order, bc.council_id
    ");
    if ($csRes instanceof mysqli_result) {
        while ($r = $csRes->fetch_assoc()) $councilSeats[] = $r;
        $csRes->close();
    }
}

// Group seats by seat_group for the modal dropdowns
$councilSeatsByGroup = [];
foreach ($councilSeats as $cs) {
    $councilSeatsByGroup[$cs['seat_group']][] = $cs;
}

// Active officials still needed for Quick Actions / Restore / Credentials
$activeOfficials = [];
$aoRes = $conn->query("
    SELECT oi.official_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
           COALESCE(oi.position_access, oi.role_access) AS position,
           oi.department, oi.area_number,
           oi.acting_for_id,
           COALESCE(se.status_name,'') AS employment_status
    FROM officialinformationtbl oi
    LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
    INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
    INNER JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
    WHERE sa.status_name IN ('Active','Suspended','Acting')
    ORDER BY oi.lastname, oi.firstname
");
if ($aoRes instanceof mysqli_result) {
    while ($r = $aoRes->fetch_assoc()) $activeOfficials[] = $r;
    $aoRes->close();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$statOpen = $statPending = $statCompleted = 0;

$hasTransTbl = $conn->query("SHOW TABLES LIKE 'officialtransitionstbl'")->num_rows > 0;
if ($hasTransTbl) {
    $statRes = $conn->query("
        SELECT
            SUM(status IN ('Open','CandidateEncoding')) AS cnt_open,
            SUM(status = 'PendingDecision')             AS cnt_pending,
            SUM(status = 'Completed' AND YEAR(completed_at) = YEAR(CURDATE())) AS cnt_completed
        FROM officialtransitionstbl
    ");
    if ($statRes instanceof mysqli_result) {
        $statRow = $statRes->fetch_assoc();
        $statOpen      = (int)($statRow['cnt_open']      ?? 0);
        $statPending   = (int)($statRow['cnt_pending']   ?? 0);
        $statCompleted = (int)($statRow['cnt_completed'] ?? 0);
        $statRes->close();
    }
}

// ── Election schedules ────────────────────────────────────────────────────────
$electionSchedules = [];
if ($hasTransTbl) {
    $elRes = $conn->query("
        SELECT batch_label, election_date,
               MAX(notify_3mo_sent)       AS n3,
               MAX(notify_1mo_sent)       AS n1,
               MAX(deactivated_7d_before) AS n7,
               MAX(notify_post_sent)      AS np,
               COUNT(*)                   AS position_count,
               SUM(status='Completed')    AS completed_count
        FROM officialtransitionstbl
        WHERE election_date IS NOT NULL AND batch_label IS NOT NULL
        GROUP BY batch_label, election_date
        ORDER BY election_date DESC
    ");
    if ($elRes instanceof mysqli_result) {
        while ($r = $elRes->fetch_assoc()) {
            $electionSchedules[] = $r;
        }
        $elRes->close();
    }
}

$departmentOptions = [
    'Office of the Barangay',
    'Barangay Certificate Issuance',
    'Barangay Monitoring',
    'Barangay Treasurers Office',
    'Barangay Peace and Order',
];
$areaOptions = ['Barangay Wide','Area 01','Area 1A','Area 02','Area 03','Area 04','Area 05','Area 06'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Official Transition Module</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260319-1">
  <style>
    #main-display { min-width: 0; }

    /* ── Stat cards ── */
    .ot-stat-card { border-left: 4px solid; border-radius: .5rem; }
    .ot-stat-card.open     { border-color: #0d6efd; }
    .ot-stat-card.pending  { border-color: #ffc107; }
    .ot-stat-card.done     { border-color: #198754; }
    .ot-stat-card .stat-num { font-size: 2rem; font-weight: 700; line-height: 1; }

    /* ── Election schedule timeline pills ── */
    .notif-pill { font-size: .72rem; white-space: nowrap; }
    .notif-pill.sent    { background:#d1e7dd; color:#0f5132; }
    .notif-pill.pending { background:#fff3cd; color:#664d03; }
    .notif-pill.na      { background:#e9ecef; color:#6c757d; }

    /* ── Transition table ── */
    .ot-table-shell { overflow-x: auto; }
    .ot-table { min-width: 1100px; }
    .ot-table th { white-space: nowrap; font-size: .82rem; }
    .ot-table td { vertical-align: middle; font-size: .84rem; white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }

    /* ── Status badges ── */
    .badge-ot-open       { background:#cfe2ff; color:#0a58ca; }
    .badge-ot-encoding   { background:#fff3cd; color:#856404; }
    .badge-ot-pending    { background:#ffe5d0; color:#974002; }
    .badge-ot-decided    { background:#d2f4ea; color:#0a3622; }
    .badge-ot-completed  { background:#d1e7dd; color:#0f5132; }
    .badge-ot-cancelled  { background:#e2e3e5; color:#41464b; }

    /* ── Candidate list in modal ── */
    .candidate-item { border: 1px solid #dee2e6; border-radius: .4rem; }
    .candidate-item.selected { border-color: #198754; background: #f0fff4; }
    .candidate-type-badge { font-size: .7rem; }

    /* ── Quick actions ── */
    .quick-action-btn { text-align: left; }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include "includes/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">

    <!-- ══════════════════════════════════════════════════════════ HEADER -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h2 style="font-family:'Charis SIL Bold';color:#DE710C;">
          Official Transition Module
        </h2>
        <p class="text-muted mb-0" style="font-size:.9rem;">
          Manage election cycles, position handovers, candidates, and access for barangay officials.
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" id="btnOtQuickActions"
                data-bs-toggle="modal" data-bs-target="#modalQuickActions">
          <i class="fas fa-bolt me-1"></i> Quick Actions
        </button>
        <button class="btn btn-outline-primary btn-sm" id="btnNewBatch"
                data-bs-toggle="modal" data-bs-target="#modalNewBatch">
          <i class="fas fa-layer-group me-1"></i> New Batch
        </button>
        <button class="btn btn-primary btn-sm" id="btnNewTransition"
                data-bs-toggle="modal" data-bs-target="#modalNewTransition">
          <i class="fas fa-plus me-1"></i> New Transition
        </button>
      </div>
    </div>
    <hr class="mb-4">

    <!-- ══════════════════════════════════════════════════════════ STAT CARDS -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-4">
        <div class="bg-white p-3 rounded-3 shadow-sm ot-stat-card open d-flex align-items-center gap-3">
          <div class="text-primary"><i class="fas fa-folder-open fa-2x"></i></div>
          <div>
            <div class="stat-num text-primary" id="statOpen"><?= $statOpen ?></div>
            <div class="text-muted small">Open Transitions</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-4">
        <div class="bg-white p-3 rounded-3 shadow-sm ot-stat-card pending d-flex align-items-center gap-3">
          <div class="text-warning"><i class="fas fa-clock fa-2x"></i></div>
          <div>
            <div class="stat-num text-warning" id="statPending"><?= $statPending ?></div>
            <div class="text-muted small">Pending Decision</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-4">
        <div class="bg-white p-3 rounded-3 shadow-sm ot-stat-card done d-flex align-items-center gap-3">
          <div class="text-success"><i class="fas fa-check-circle fa-2x"></i></div>
          <div>
            <div class="stat-num text-success" id="statCompleted"><?= $statCompleted ?></div>
            <div class="text-muted small">Completed (This Year)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ ELECTION SCHEDULES -->
    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-calendar-alt text-primary"></i>
          <span class="fw-semibold">Election Schedules</span>
        </div>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddElection">
          <i class="fas fa-plus me-1"></i> Add Election Date
        </button>
      </div>
      <div class="p-3">
        <?php if (empty($electionSchedules)): ?>
          <p class="text-muted mb-0 small text-center py-2">No election dates scheduled.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="electionScheduleTable">
              <thead class="table-light">
                <tr>
                  <th>Batch / Label</th>
                  <th>Election Date</th>
                  <th>Positions</th>
                  <th>3-Month Notice</th>
                  <th>1-Month Notice</th>
                  <th>7-Day Deactivation</th>
                  <th>Post-Election Notice</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($electionSchedules as $es): ?>
                  <?php
                    $ed    = $es['election_date'];
                    $label = htmlspecialchars($es['batch_label'] ?? '', ENT_QUOTES, 'UTF-8');
                    $daysUntil = (int)round((strtotime($ed) - time()) / 86400);
                    $isPast = $daysUntil < 0;
                    $edFmt  = date('M d, Y', strtotime($ed));
                    function pillClass(int $sent): string {
                        return $sent ? 'sent' : 'pending';
                    }
                    function pillLabel(int $sent, bool $isPast, string $label): string {
                        return $sent ? 'Sent' : ($isPast ? 'Missed' : $label);
                    }
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= $label ?></td>
                    <td>
                      <?= $edFmt ?>
                      <?php if ($daysUntil > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $daysUntil ?>d away</span>
                      <?php elseif ($daysUntil === 0): ?>
                        <span class="badge bg-warning text-dark ms-1">Today</span>
                      <?php else: ?>
                        <span class="badge bg-light text-muted ms-1"><?= abs($daysUntil) ?>d ago</span>
                      <?php endif; ?>
                    </td>
                    <td><?= (int)$es['position_count'] ?> pos / <?= (int)$es['completed_count'] ?> done</td>
                    <td><span class="badge notif-pill <?= pillClass((int)$es['n3']) ?>"><?= pillLabel((int)$es['n3'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= pillClass((int)$es['n1']) ?>"><?= pillLabel((int)$es['n1'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= pillClass((int)$es['n7']) ?>"><?= pillLabel((int)$es['n7'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= pillClass((int)$es['np']) ?>"><?= pillLabel((int)$es['np'], $daysUntil > 0, 'Pending') ?></span></td>
                    <td>
                      <button class="btn btn-xs btn-outline-secondary py-0 px-2"
                              onclick="otResendNotif(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ed), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Manually trigger pending notifications">
                        <i class="fas fa-redo fa-xs"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ TRANSITIONS TABLE -->
    <div class="bg-white rounded-3 shadow-sm border">
      <!-- Toolbar -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 border-bottom">
        <div class="d-flex gap-1 flex-wrap" id="otTabBtns">
          <button class="btn btn-sm btn-outline-primary active" data-ot-tab="active">Active</button>
          <button class="btn btn-sm btn-outline-secondary" data-ot-tab="history">History</button>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap ms-auto">
          <div class="input-group" style="max-width:280px;">
            <input type="text" class="form-control form-control-sm" id="otSearch"
                   placeholder="Search position, official, batch…">
            <span class="input-group-text bg-white"><i class="fas fa-search fa-xs"></i></span>
          </div>
          <select class="form-select form-select-sm" id="otTypeFilter" style="max-width:180px;">
            <option value="">All types</option>
            <option value="BarangayElection">Barangay Election</option>
            <option value="SKElection">SK Election</option>
            <option value="Appointment">Appointment</option>
            <option value="Reappointment">Reappointment</option>
            <option value="Resignation">Resignation</option>
            <option value="Removal">Removal</option>
            <option value="Retirement">Retirement</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="btnOtRefresh" title="Refresh">
            <i class="fas fa-arrows-rotate"></i>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="p-3 ot-table-shell">
        <table class="table table-hover align-middle ot-table mb-0" id="otTable">
          <thead class="table-light">
            <tr>
              <th>Transition ID</th>
              <th>Type</th>
              <th>Position</th>
              <th>Outgoing Official</th>
              <th>Batch / Election</th>
              <th>Effective Date</th>
              <th>Candidates</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="otTableBody">
            <tr>
              <td colspan="9" class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading…
              </td>
            </tr>
          </tbody>
        </table>
        <div id="otTablePagination" class="d-flex justify-content-end mt-2"></div>
      </div>
    </div>

  </main><!-- /main -->
</div><!-- /flex wrapper -->


<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: New Individual Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewTransition" tabindex="-1" aria-labelledby="modalNewTransitionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewTransitionLabel">
          <i class="fas fa-plus-circle me-2 text-primary"></i> New Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewTransition" novalidate>
        <div class="modal-body">
          <?php if (empty($councilSeats)): ?>
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              No council seats found. Run <code>20260323_create_barangaycouncil_schema.sql</code> and assign officials to their seats first.
            </div>
          <?php else: ?>
          <div class="row g-3">
            <!-- Council Seat -->
            <div class="col-12">
              <label class="form-label fw-semibold">Council Seat <span class="text-danger">*</span></label>
              <select class="form-select" name="council_id" id="ntCouncilId" required>
                <option value="">— Select a seat —</option>
                <?php foreach ($councilSeatsByGroup as $group => $seats): ?>
                  <optgroup label="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($seats as $cs): ?>
                      <?php
                        $holder = $cs['current_official_name']
                            ? htmlspecialchars(trim($cs['current_official_name']), ENT_QUOTES, 'UTF-8')
                            : '<em>Vacant</em>';
                        $holderText = $cs['current_official_name']
                            ? trim($cs['current_official_name'])
                            : 'Vacant';
                      ?>
                      <option value="<?= (int)$cs['council_id'] ?>"
                              data-seat="<?= htmlspecialchars($cs['seat_name'], ENT_QUOTES, 'UTF-8') ?>"
                              data-method="<?= htmlspecialchars($cs['selection_method'], ENT_QUOTES, 'UTF-8') ?>"
                              data-official-id="<?= htmlspecialchars($cs['current_official_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-official-name="<?= htmlspecialchars($holderText, ENT_QUOTES, 'UTF-8') ?>"
                              data-account-status="<?= htmlspecialchars($cs['account_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($cs['seat_name'], ENT_QUOTES, 'UTF-8') ?>
                        — <?= $holderText ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Auto-filled seat info card -->
            <div class="col-12" id="ntSeatInfoWrap" style="display:none;">
              <div class="alert alert-secondary py-2 mb-0 small">
                <div class="d-flex gap-3 flex-wrap">
                  <div><span class="text-muted">Position:</span> <strong id="ntSeatName"></strong></div>
                  <div><span class="text-muted">Selection:</span> <strong id="ntSeatMethod"></strong></div>
                  <div><span class="text-muted">Current holder:</span> <strong id="ntSeatHolder"></strong>
                    <span id="ntSeatHolderStatus" class="badge ms-1"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Transition Type — options filtered by selection_method -->
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Transition Type <span class="text-danger">*</span></label>
              <select class="form-select" name="transition_type" id="ntType" required>
                <option value="">— Select a seat first —</option>
              </select>
            </div>

            <!-- Effective Date -->
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Effective Date</label>
              <input type="date" class="form-control" name="effective_date" id="ntEffectiveDate">
            </div>

            <!-- Batch Label (election types only) -->
            <div class="col-12 col-md-6" id="ntBatchLabelWrap" style="display:none;">
              <label class="form-label fw-semibold">Batch Label</label>
              <input type="text" class="form-control" name="batch_label" id="ntBatchLabel"
                     placeholder="e.g. 2025 Barangay Election">
            </div>
            <!-- Election Date (election types only) -->
            <div class="col-12 col-md-6" id="ntElectionDateWrap" style="display:none;">
              <label class="form-label fw-semibold">Election Date</label>
              <input type="date" class="form-control" name="election_date" id="ntElectionDate">
            </div>

            <!-- Reason -->
            <div class="col-12">
              <label class="form-label fw-semibold" id="ntReasonLabel">Reason</label>
              <textarea class="form-control" name="reason" id="ntReason" rows="2"
                        placeholder="Optional — required for Removal"></textarea>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitNewTransition">
            <i class="fas fa-save me-1"></i> Create Transition
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: New Batch Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewBatch" tabindex="-1" aria-labelledby="modalNewBatchLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewBatchLabel">
          <i class="fas fa-layer-group me-2 text-primary"></i> New Batch Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewBatch" novalidate>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Batch Label <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="batch_label" id="nbLabel" required
                     placeholder="e.g. 2025 Barangay Election">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Batch Type <span class="text-danger">*</span></label>
              <select class="form-select" name="batch_type" id="nbType" required>
                <option value="">— Select —</option>
                <option value="BarangayElection">Barangay Election (Term End)</option>
                <option value="SKElection">SK Election (Term End)</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Election Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="election_date" id="nbElectionDate" required>
            </div>
          </div>

          <p class="fw-semibold mb-2">Select which council seats are up for election:</p>
          <div class="alert alert-info py-2 small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            Check the seats whose current holders are transitioning out. The system will auto-identify who is outgoing per seat.
          </div>
          <?php if (empty($councilSeats)): ?>
            <div class="alert alert-warning small">No council seats found. Run the migration first.</div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th><input type="checkbox" id="nbSelectAll" title="Select all"> All</th>
                  <th>Seat</th>
                  <th>Selection</th>
                  <th>Current Holder</th>
                  <th>Account</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($councilSeats as $cs): ?>
                  <?php
                    $holderName = trim($cs['current_official_name'] ?? '');
                    $acctStatus = $cs['account_status'] ?? '';
                    $acctBadge  = match(true) {
                        stripos($acctStatus, 'Active')    !== false => 'bg-success',
                        stripos($acctStatus, 'Inactive')  !== false => 'bg-secondary',
                        stripos($acctStatus, 'Suspended') !== false => 'bg-warning text-dark',
                        default                                      => 'bg-light text-dark',
                    };
                  ?>
                  <tr>
                    <td>
                      <input type="checkbox" class="nb-official-check" name="officials[]"
                             value="<?= (int)$cs['council_id'] ?>">
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($cs['seat_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <span class="badge <?= $cs['selection_method'] === 'Elected' ? 'bg-primary' : 'bg-secondary' ?>">
                        <?= htmlspecialchars($cs['selection_method'], ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($holderName !== ''): ?>
                        <?= htmlspecialchars($holderName, ENT_QUOTES, 'UTF-8') ?>
                      <?php else: ?>
                        <span class="text-muted fst-italic">Vacant</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($acctStatus !== ''): ?>
                        <span class="badge <?= $acctBadge ?>"><?= htmlspecialchars($acctStatus, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitNewBatch">
            <i class="fas fa-layer-group me-1"></i> Create Batch
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Add Election Date (standalone, no batch)
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAddElection" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2 text-primary"></i> Add / Update Election Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddElection" novalidate>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Batch Label <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="batch_label" id="aeBatchLabel" required
                   placeholder="e.g. 2025 Barangay Election">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Election Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="election_date" id="aeElectionDate" required>
          </div>
          <div class="alert alert-warning py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            If you are updating an existing batch date, all unprocessed notification flags for that batch will be reset.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Election Date
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Manage Candidates
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCandidates" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-users me-2 text-primary"></i>
          Candidates — <span id="candidatesModalPositionLabel" class="text-muted fw-normal"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="candidatesTransitionId">
        <input type="hidden" id="candidatesTransitionStatus">

        <!-- Outgoing official info -->
        <div id="outgoingOfficialInfo" class="alert alert-secondary py-2 small mb-3 d-none">
          <i class="fas fa-sign-out-alt me-1"></i>
          <strong>Outgoing:</strong> <span id="outgoingOfficialName"></span>
          — <span id="outgoingOfficialPosition"></span>
        </div>

        <!-- Candidate list -->
        <div id="candidatesList" class="mb-3">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>

        <!-- Add candidate form -->
        <div id="addCandidateSection" class="border rounded p-3">
          <p class="fw-semibold mb-2 small">Add Candidate</p>
          <div class="row g-2">
            <div class="col-12 col-md-5">
              <input type="text" class="form-control form-control-sm" id="newCandidateName"
                     placeholder="Full name *">
            </div>
            <div class="col-12 col-md-4">
              <input type="text" class="form-control form-control-sm" id="newCandidateContact"
                     placeholder="Contact number">
            </div>
            <div class="col-12 col-md-3">
              <select class="form-select form-select-sm" id="newCandidateType">
                <option value="New">New Person</option>
                <option value="ReturningOfficial">Returning Official</option>
                <option value="ActiveOfficial">Active Official (Position Change)</option>
                <option value="Resident">Resident</option>
              </select>
            </div>
            <div class="col-12 col-md-8" id="linkedIdWrap" style="display:none;">
              <select class="form-select form-select-sm" id="newCandidateLinkedId">
                <option value="">— Link to existing record —</option>
              </select>
            </div>
            <div class="col-12">
              <input type="text" class="form-control form-control-sm" id="newCandidateNotes"
                     placeholder="Notes (optional)">
            </div>
            <div class="col-12">
              <button class="btn btn-sm btn-outline-primary w-100" id="btnAddCandidate">
                <i class="fas fa-plus me-1"></i> Add Candidate
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-warning btn-sm" id="btnMarkPendingDecision">
          <i class="fas fa-gavel me-1"></i> Ready for Decision
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Select Winner & Complete Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectWinner" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-trophy me-2 text-warning"></i>
          Select Winner & Complete Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="winnerTransitionId">

        <div class="alert alert-info py-2 small mb-3">
          <i class="fas fa-info-circle me-1"></i>
          Select the winning candidate, then choose what happens next. This action will update the outgoing official's status and initiate onboarding for the winner.
        </div>

        <!-- Candidates for winner selection -->
        <div id="winnerCandidatesList" class="mb-4">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>

        <!-- Outcome selector -->
        <div id="winnerOutcomeSection">
          <label class="form-label fw-semibold">Transition Outcome <span class="text-danger">*</span></label>
          <select class="form-select" id="winnerOutcome">
            <option value="">— Select outcome —</option>
            <option value="ReElected">Re-Elected (same person wins, no account change)</option>
            <option value="NewPerson">New Person (create invite & new account)</option>
            <option value="Reactivated">Returning Official (reactivate existing account)</option>
            <option value="PositionChange">Position Change (winner is an existing active official)</option>
            <option value="ActingReplacement">Acting / Temporary Replacement</option>
            <option value="NoSuccessor">No Successor (position abolished or unfilled)</option>
          </select>

          <!-- Conditional: Acting until date -->
          <div id="actingUntilWrap" class="mt-3 d-none">
            <label class="form-label fw-semibold">Acting Until Date</label>
            <input type="date" class="form-control" id="actingUntilDate">
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="btnCompleteTransition">
          <i class="fas fa-check-circle me-1"></i> Complete Transition
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Quick Actions
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalQuickActions" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-bolt me-2 text-warning"></i> Quick Actions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          These actions apply directly without a full transition workflow.
        </p>
        <div class="d-grid gap-2">
          <button class="btn btn-outline-success quick-action-btn" id="btnQaRestoreAccess">
            <i class="fas fa-unlock me-2"></i>
            <strong>Restore Access</strong>
            <small class="d-block text-muted fw-normal">Reactivate a suspended or inactive official's account.</small>
          </button>
          <button class="btn btn-outline-primary quick-action-btn" id="btnQaChangeCredentials">
            <i class="fas fa-key me-2"></i>
            <strong>Change Credentials</strong>
            <small class="d-block text-muted fw-normal">Update email, phone, or force a password reset.</small>
          </button>
          <button class="btn btn-outline-warning quick-action-btn" id="btnQaEndActing">
            <i class="fas fa-user-clock me-2"></i>
            <strong>End Acting Assignment</strong>
            <small class="d-block text-muted fw-normal">Restore the original official and remove the acting replacement's access.</small>
          </button>
          <button class="btn btn-outline-secondary quick-action-btn" id="btnQaChangePosition">
            <i class="fas fa-arrows-rotate me-2"></i>
            <strong>Direct Position Change</strong>
            <small class="d-block text-muted fw-normal">Move an official to a different position without an election.</small>
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Restore Access
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRestoreAccess" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-unlock me-2 text-success"></i> Restore Official Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="restoreSearch" placeholder="Search by name, ID, or position…">
        </div>
        <div id="restoreOfficialsList">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Change Credentials
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalChangeCredentials" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key me-2 text-primary"></i> Change Credentials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: Pick official -->
        <div id="credStep1">
          <div class="mb-3">
            <input type="text" class="form-control" id="credSearch" placeholder="Search official by name or ID…">
          </div>
          <div id="credOfficialsList">
            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
          </div>
        </div>
        <!-- Step 2: Edit fields -->
        <div id="credStep2" class="d-none">
          <p class="fw-semibold mb-3">Editing: <span id="credOfficialNameLabel"></span></p>
          <input type="hidden" id="credOfficialId">
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" id="credEmail" placeholder="New email address">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" class="form-control" id="credPhone" placeholder="New phone number">
          </div>
          <div class="mb-0">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="credForcePasswordReset">
              <label class="form-check-label" for="credForcePasswordReset">
                Force password reset on next login
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" id="btnCredBack" onclick="otCredStep(1)">
          <i class="fas fa-arrow-left me-1"></i> Back
        </button>
        <button class="btn btn-primary" id="btnCredSave" style="display:none;">
          <i class="fas fa-save me-1"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TOAST
══════════════════════════════════════════════════════════════════════════ -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="otToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-semibold" id="otToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- ── Data passed to JS ────────────────────────────────────────────────── -->
<script>
  window.OT_DATA = {
    activeOfficials: <?= json_encode(array_values($activeOfficials), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    councilSeats:    <?= json_encode(array_values($councilSeats),    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    departments: <?= json_encode($departmentOptions, JSON_UNESCAPED_UNICODE) ?>,
    areas: <?= json_encode($areaOptions, JSON_UNESCAPED_UNICODE) ?>
  };
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/Admin-End/officialTransitionsScript.js?v=20260323-1"></script>
</body>
</html>
