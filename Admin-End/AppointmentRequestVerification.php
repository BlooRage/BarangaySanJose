<?php
require_once __DIR__ . "/includes/admin_guard.php";
require_once __DIR__ . "/../PhpFiles/General/connection.php";

function ar_first_existing_col(array $cols, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (isset($cols[strtolower($candidate)])) {
            return $cols[strtolower($candidate)];
        }
    }
    return null;
}

function ar_value(array $row, ?string $col, string $default = ""): string {
    if (!$col || !array_key_exists($col, $row)) {
        return $default;
    }
    $value = $row[$col];
    if ($value === null) {
        return $default;
    }
    $value = trim((string)$value);
    return $value === "" ? $default : $value;
}

function ar_human_status(string $raw): string {
    $value = strtolower(trim($raw));
    if ($value === "approved" || $value === "approve") return "Approved";
    if ($value === "denied" || $value === "declined" || $value === "reject" || $value === "rejected") return "Denied";
    if ($value === "pending") return "Pending";
    return "Pending";
}

function ar_status_class(string $status): string {
    $normalized = strtolower($status);
    if ($normalized === "approved") return "approved";
    if ($normalized === "denied") return "denied";
    return "pending";
}

function ar_format_datetime(?string $value): string {
    if ($value === null || trim($value) === "") {
        return "-";
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return date("M d, Y h:i A", $ts);
}

$appointmentRows = [];
$loadError = "";

$tableCandidates = [
    "appointmentrequesttbl",
    "appointmentsrequesttbl",
    "appointmenttbl",
    "residentappointmenttbl"
];

$appointmentTable = null;
foreach ($tableCandidates as $candidate) {
    $safeCandidate = $conn->real_escape_string($candidate);
    $check = $conn->query("SHOW TABLES LIKE '{$safeCandidate}'");
    if ($check && $check->num_rows > 0) {
        $appointmentTable = $candidate;
        $check->free();
        break;
    }
    if ($check) {
        $check->free();
    }
}

if ($appointmentTable !== null) {
    $columns = [];
    $describe = $conn->query("DESCRIBE `{$appointmentTable}`");
    if ($describe) {
        while ($d = $describe->fetch_assoc()) {
            $field = (string)($d["Field"] ?? "");
            if ($field !== "") {
                $columns[strtolower($field)] = $field;
            }
        }
        $describe->free();
    }

    $idCol = ar_first_existing_col($columns, ["appointment_id", "request_id", "appointment_request_id", "id"]);
    $dateFiledCol = ar_first_existing_col($columns, ["date_filed", "filed_at", "created_at", "submitted_at", "request_timestamp"]);
    $purposeCol = ar_first_existing_col($columns, ["purpose", "appointment_purpose"]);
    $statusCol = ar_first_existing_col($columns, ["status", "appointment_status", "decision_status", "request_status"]);

    $nameCol = ar_first_existing_col($columns, ["applicant_name", "full_name", "resident_name", "name"]);
    $firstNameCol = ar_first_existing_col($columns, ["first_name", "firstname", "applicant_first_name"]);
    $middleNameCol = ar_first_existing_col($columns, ["middle_name", "middlename", "applicant_middle_name"]);
    $lastNameCol = ar_first_existing_col($columns, ["last_name", "lastname", "applicant_last_name"]);
    $suffixCol = ar_first_existing_col($columns, ["suffix", "applicant_suffix"]);

    $subjectCol = ar_first_existing_col($columns, ["subject", "appointment_subject"]);
    $appointmentDateCol = ar_first_existing_col($columns, ["appointment_date", "date_of_appointment"]);
    $appointmentTimeCol = ar_first_existing_col($columns, ["appointment_time", "time_of_appointment"]);
    $contactCol = ar_first_existing_col($columns, ["contact_number", "phone_number", "mobile_number"]);
    $addressCol = ar_first_existing_col($columns, ["full_address_display", "address", "complete_address"]);

    $query = "SELECT * FROM `{$appointmentTable}` ORDER BY " . ($dateFiledCol ? "`{$dateFiledCol}` DESC" : ($idCol ? "`{$idCol}` DESC" : "1 DESC"));
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fullName = ar_value($row, $nameCol, "");
            if ($fullName === "") {
                $first = ar_value($row, $firstNameCol, "");
                $middle = ar_value($row, $middleNameCol, "");
                $last = ar_value($row, $lastNameCol, "");
                $suffix = ar_value($row, $suffixCol, "");
                $nameParts = array_filter([$last, $first, $middle, $suffix], fn($v) => trim($v) !== "");
                $fullName = count($nameParts) ? implode(", ", array_filter([$last, trim($first . " " . $middle), $suffix], fn($v) => trim($v) !== "")) : "-";
            }

            $rawStatus = ar_value($row, $statusCol, "Pending");
            $status = ar_human_status($rawStatus);

            $appointmentRows[] = [
                "appointment_id" => ar_value($row, $idCol, "-"),
                "date_filed_raw" => ar_value($row, $dateFiledCol, ""),
                "date_filed" => ar_format_datetime(ar_value($row, $dateFiledCol, "")),
                "applicant_name" => $fullName === "" ? "-" : $fullName,
                "purpose" => ar_value($row, $purposeCol, "-"),
                "status" => $status,
                "status_class" => ar_status_class($status),
                "subject" => ar_value($row, $subjectCol, "-"),
                "appointment_date" => ar_value($row, $appointmentDateCol, "-"),
                "appointment_time" => ar_value($row, $appointmentTimeCol, "-"),
                "contact_number" => ar_value($row, $contactCol, "-"),
                "address" => ar_value($row, $addressCol, "-")
            ];
        }
        $result->free();
    } else {
        $loadError = "Unable to load appointment requests.";
    }
} else {
    $loadError = "No appointment request table was found.";
}

$pendingCount = 0;
$approvedCount = 0;
$deniedCount = 0;
foreach ($appointmentRows as $r) {
    if ($r["status"] === "Pending") $pendingCount++;
    if ($r["status"] === "Approved") $approvedCount++;
    if ($r["status"] === "Denied") $deniedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appointment Request Verification</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    #main-display { min-width: 0; }
    #div-tableContainer { overflow: hidden; }

    .appointment-shell {
      border-color: #f1e1cf !important;
      width: 100%;
      max-width: 100%;
      overflow: hidden;
    }

    .appointment-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
      max-width: 100%;
    }

    .appointment-shell #table-appointmentQueue {
      width: max-content;
      min-width: 100%;
    }

    .appointment-shell #table-appointmentQueue th,
    .appointment-shell #table-appointmentQueue td {
      white-space: nowrap;
      vertical-align: middle;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 108px;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .status-pill.pending { color: #6c5a06; background: #f4e8b7; border-color: #e9db9f; }
    .status-pill.approved { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .status-pill.denied { color: #991b1b; background: #fee2e2; border-color: #fecaca; }

    .status-filter-btn { display: inline-flex; align-items: center; gap: 6px; }

    .appointment-shell .status-filter-btn {
      border-radius: 10px;
      border-width: 1px;
      overflow: visible;
    }

    .appointment-shell .status-filter-btn[data-filter="ALL"] {
      color: #0d6efd;
      border-color: #0d6efd;
      background: #fff;
    }

    .appointment-shell .status-filter-btn[data-filter="Pending"],
    .appointment-shell .status-filter-btn[data-filter="Approved"],
    .appointment-shell .status-filter-btn[data-filter="Denied"] {
      color: #495057;
      border-color: #6c757d;
      background: #fff;
    }

    .appointment-shell .status-filter-btn[data-filter="ALL"].active {
      color: #fff !important;
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
      font-weight: 700;
    }

    .appointment-shell .status-filter-btn[data-filter="Pending"].active,
    .appointment-shell .status-filter-btn[data-filter="Approved"].active,
    .appointment-shell .status-filter-btn[data-filter="Denied"].active {
      color: #fff !important;
      background-color: #495057 !important;
      border-color: #495057 !important;
      font-weight: 700;
    }

    .appointment-shell .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      height: 38px;
      padding: 0 12px;
      border-radius: 10px;
      white-space: nowrap;
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Appointment Request Verification</h2>
      <hr><br>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border appointment-shell">
        <div class="admin-list-toolbar mb-3 flex-wrap">
          <div class="admin-list-tabs d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn active fw-bold" data-filter="ALL">All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn has-notif fw-bold" data-filter="Pending">
              Pending
              <span id="pendingAppointmentBadge" class="pending-count-badge <?= $pendingCount > 0 ? "" : "d-none" ?>"><?= (int)$pendingCount ?></span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn fw-bold" data-filter="Approved">Approved</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn fw-bold" data-filter="Denied">Denied</button>
          </div>
          <div class="admin-list-actions d-flex align-items-center gap-2 ms-auto">
            <div class="input-group admin-search me-2">
              <input id="appointmentSearch" class="form-control" placeholder="Search appointment/applicant/purpose..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnAppointmentFilter" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#modalAppointmentFilter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button id="btnAppointmentColumns" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#modalAppointmentColumns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <button id="btnAppointmentRefresh" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="table-appointmentQueue">
            <thead class="table-light">
              <tr>
                <th>Appointment ID</th>
                <th>Date Filed</th>
                <th>Applicant Name</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="appointmentTbody">
              <?php if ($loadError !== ""): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4"><?= htmlspecialchars($loadError) ?></td>
                </tr>
              <?php elseif (count($appointmentRows) === 0): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">No appointment requests found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($appointmentRows as $row): ?>
                  <tr
                    data-status="<?= htmlspecialchars($row["status"]) ?>"
                    data-search="<?= htmlspecialchars(strtolower(implode(" ", [
                      $row["appointment_id"],
                      $row["date_filed"],
                      $row["applicant_name"],
                      $row["purpose"],
                      $row["status"]
                    ]))) ?>"
                  >
                    <td><?= htmlspecialchars($row["appointment_id"]) ?></td>
                    <td><?= htmlspecialchars($row["date_filed"]) ?></td>
                    <td><?= htmlspecialchars($row["applicant_name"]) ?></td>
                    <td><?= htmlspecialchars($row["purpose"]) ?></td>
                    <td><span class="status-pill <?= htmlspecialchars($row["status_class"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-outline-primary btn-sm btn-view-appointment"
                        data-bs-toggle="modal"
                        data-bs-target="#modalViewAppointment"
                        data-appointment-id="<?= htmlspecialchars($row["appointment_id"]) ?>"
                        data-date-filed="<?= htmlspecialchars($row["date_filed"]) ?>"
                        data-applicant-name="<?= htmlspecialchars($row["applicant_name"]) ?>"
                        data-contact-number="<?= htmlspecialchars($row["contact_number"]) ?>"
                        data-address="<?= htmlspecialchars($row["address"]) ?>"
                        data-subject="<?= htmlspecialchars($row["subject"]) ?>"
                        data-appointment-date="<?= htmlspecialchars($row["appointment_date"]) ?>"
                        data-appointment-time="<?= htmlspecialchars($row["appointment_time"]) ?>"
                        data-purpose="<?= htmlspecialchars($row["purpose"]) ?>"
                        data-status="<?= htmlspecialchars($row["status"]) ?>"
                      >
                        View Entry
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="appointmentEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
            <input id="appointmentEntriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="Appointment queue pagination">
            <ul class="pagination pagination-sm mb-0" id="appointmentPagination"></ul>
          </nav>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="modalViewAppointment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Appointment Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="small text-muted">Appointment ID</label>
              <div class="form-control bg-light" id="viewAppointmentId">-</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="small text-muted">Date Filed</label>
              <div class="form-control bg-light" id="viewDateFiled">-</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="small text-muted">Applicant Name</label>
              <div class="form-control bg-light" id="viewApplicantName">-</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="small text-muted">Contact Number</label>
              <div class="form-control bg-light" id="viewContactNumber">-</div>
            </div>
            <div class="col-12">
              <label class="small text-muted">Complete Address</label>
              <div class="form-control bg-light" id="viewAddress">-</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="small text-muted">Subject of Appointment</label>
              <div class="form-control bg-light" id="viewSubject">-</div>
            </div>
            <div class="col-12 col-md-3">
              <label class="small text-muted">Appointment Date</label>
              <div class="form-control bg-light" id="viewAppointmentDate">-</div>
            </div>
            <div class="col-12 col-md-3">
              <label class="small text-muted">Appointment Time</label>
              <div class="form-control bg-light" id="viewAppointmentTime">-</div>
            </div>
            <div class="col-12">
              <label class="small text-muted">Purpose</label>
              <div class="form-control bg-light" id="viewPurpose">-</div>
            </div>
            <div class="col-12 col-md-4">
              <label class="small text-muted">Status</label>
              <div class="form-control bg-light" id="viewStatus">-</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="modalAppointmentFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Filter Appointment Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label small fw-bold mb-1" for="appointmentStatusFilterSelect">Status</label>
          <select id="appointmentStatusFilterSelect" class="form-select">
            <option value="ALL">All</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Denied">Denied</option>
          </select>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" id="btnAppointmentFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="btnAppointmentFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="modalAppointmentColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="appointmentColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnAppointmentColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
      tableSelector: "#table-appointmentQueue",
      modalId: "modalAppointmentColumns",
      listId: "appointmentColumnsList",
      resetBtnId: "btnAppointmentColumnsReset",
      storageKey: "admin_cols_appointment_verification_v1"
    };
  </script>
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
  <script>
    (() => {
      const tbody = document.getElementById("appointmentTbody");
      if (!tbody) return;

      const searchInput = document.getElementById("appointmentSearch");
      const entriesInput = document.getElementById("appointmentEntriesPerPageInput");
      const pagination = document.getElementById("appointmentPagination");
      const filterButtons = Array.from(document.querySelectorAll(".status-filter-btn"));
      const pendingBadge = document.getElementById("pendingAppointmentBadge");
      const statusFilterSelect = document.getElementById("appointmentStatusFilterSelect");
      const btnFilterApply = document.getElementById("btnAppointmentFilterApply");
      const btnFilterReset = document.getElementById("btnAppointmentFilterReset");
      const refreshBtn = document.getElementById("btnAppointmentRefresh");
      let activeFilter = "ALL";
      let currentPage = 1;

      const getRows = () => Array.from(tbody.querySelectorAll("tr")).filter((row) => row.querySelector("td"));

      const getFilteredRows = () => {
        const allRows = getRows();
        const term = (searchInput?.value || "").trim().toLowerCase();
        return allRows.filter((row) => {
          const rowStatus = (row.dataset.status || "").toLowerCase();
          const rowSearch = row.dataset.search || "";
          const passFilter = activeFilter === "ALL" || rowStatus === activeFilter.toLowerCase();
          const passSearch = term === "" || rowSearch.includes(term);
          return passFilter && passSearch;
        });
      };

      const updatePendingBadge = () => {
        if (!pendingBadge) return;
        const count = getRows().filter((row) => (row.dataset.status || "").toLowerCase() === "pending").length;
        pendingBadge.textContent = String(count);
        pendingBadge.classList.toggle("d-none", count <= 0);
      };

      const activateStatusButton = (status) => {
        filterButtons.forEach((b) => {
          const isActive = (b.dataset.filter || "ALL") === status;
          b.classList.toggle("active", isActive);
          if (isActive) {
            b.classList.add("btn-outline-primary");
            b.classList.remove("btn-outline-secondary");
          } else {
            b.classList.remove("btn-outline-primary");
            b.classList.add("btn-outline-secondary");
          }
        });
        if (statusFilterSelect) statusFilterSelect.value = status;
      };

      const renderPagination = (totalPages) => {
        if (!pagination) return;
        pagination.innerHTML = "";
        if (totalPages <= 1) return;

        const createItem = (label, page, disabled = false, active = false) => {
          const li = document.createElement("li");
          li.className = "page-item" + (disabled ? " disabled" : "") + (active ? " active" : "");
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "page-link";
          btn.textContent = label;
          btn.disabled = disabled;
          btn.addEventListener("click", () => {
            currentPage = page;
            render();
          });
          li.appendChild(btn);
          return li;
        };

        pagination.appendChild(createItem("Prev", Math.max(1, currentPage - 1), currentPage === 1));
        for (let p = 1; p <= totalPages; p++) {
          pagination.appendChild(createItem(String(p), p, false, p === currentPage));
        }
        pagination.appendChild(createItem("Next", Math.min(totalPages, currentPage + 1), currentPage === totalPages));
      };

      const render = () => {
        const allRows = getRows();
        const filteredRows = getFilteredRows();
        const perPage = Math.max(1, parseInt(entriesInput?.value || "20", 10) || 20);
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        allRows.forEach((row) => { row.style.display = "none"; });
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        filteredRows.slice(start, end).forEach((row) => { row.style.display = ""; });

        renderPagination(totalPages);
      };

      filterButtons.forEach((btn) => {
        btn.addEventListener("click", () => {
          activeFilter = btn.dataset.filter || "ALL";
          currentPage = 1;
          activateStatusButton(activeFilter);
          render();
        });
      });

      btnFilterApply?.addEventListener("click", () => {
        activeFilter = statusFilterSelect?.value || "ALL";
        currentPage = 1;
        activateStatusButton(activeFilter);
        render();
      });
      btnFilterReset?.addEventListener("click", () => {
        activeFilter = "ALL";
        currentPage = 1;
        activateStatusButton(activeFilter);
        render();
      });

      const setRefreshLoading = (on) => {
        if (!refreshBtn) return;
        refreshBtn.classList.toggle("is-loading", !!on);
        refreshBtn.disabled = !!on;
      };
      const triggerRefresh = () => {
        setRefreshLoading(true);
        window.setTimeout(() => {
          window.location.reload();
        }, 250);
      };
      refreshBtn?.addEventListener("click", triggerRefresh);

      searchInput?.addEventListener("input", () => {
        currentPage = 1;
        render();
      });
      entriesInput?.addEventListener("input", () => {
        currentPage = 1;
        render();
      });

      const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value && String(value).trim() !== "" ? value : "-";
      };

      document.querySelectorAll(".btn-view-appointment").forEach((btn) => {
        btn.addEventListener("click", () => {
          setText("viewAppointmentId", btn.dataset.appointmentId);
          setText("viewDateFiled", btn.dataset.dateFiled);
          setText("viewApplicantName", btn.dataset.applicantName);
          setText("viewContactNumber", btn.dataset.contactNumber);
          setText("viewAddress", btn.dataset.address);
          setText("viewSubject", btn.dataset.subject);
          setText("viewAppointmentDate", btn.dataset.appointmentDate);
          setText("viewAppointmentTime", btn.dataset.appointmentTime);
          setText("viewPurpose", btn.dataset.purpose);
          setText("viewStatus", btn.dataset.status);
        });
      });

      updatePendingBadge();
      activateStatusButton(activeFilter);
      render();
    })();
  </script>
</body>
</html>
