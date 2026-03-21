<?php
require_once __DIR__ . "/includes/admin_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Residents</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
</head>

<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">

    <!-- SIDEBAR -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">

        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
            Archived Residents
        </h2>

        <hr><br>

        <div class="bg-white p-4 rounded-4 shadow-sm border archive-shell">

	            <!-- SEARCH -->
	            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
	                <div class="admin-list-tabs"></div>

	                <div class="admin-list-actions">
	                    <div class="input-group admin-search">
	                        <input type="text" id="searchInput" class="form-control" placeholder="Resident ID or Name">
	                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
	                    </div>
	                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
	                      <i class="fas fa-filter"></i>
	                      <span class="visually-hidden">Filter</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnArchiveColumns" title="Columns" aria-label="Columns">
	                      <i class="fa-solid fa-sliders"></i>
	                      <span class="visually-hidden">Columns</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnArchiveRefresh" title="Refresh table" aria-label="Refresh table">
	                        <i class="fa-solid fa-arrows-rotate"></i>
	                        <span class="visually-hidden">Refresh</span>
	                    </button>
	                    <span id="archiveAutoRefreshCountdown" class="small text-muted d-none"></span>
	                </div>
	            </div>

            <!-- TABLE -->
            <div class="table-responsive compact-admin-table-shell">
                <table class="table align-middle compact-admin-table" id="table-residentArchive">
                    <thead>
                        <tr class="table-light">
                            <th>Resident ID</th>
                            <th>Resident Name</th>
                            <th>Account Status</th>
                            <th>Archived Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="archiveEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input
                        id="archiveEntriesPerPageInput"
                        type="number"
                        min="1"
                        step="1"
                        value="20"
                        class="form-control form-control-sm resident-entries-input"
                    />
                </div>
                <nav aria-label="Archive pagination">
                    <ul class="pagination pagination-sm mb-0" id="archivePagination"></ul>
                </nav>
            </div>

            <!-- FILTER MODAL -->
            <div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4">

                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Filter Residents</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <hr>

                        <div class="modal-body">

                            <!-- Head of Family -->
                            <div class="mb-3">
                                <label class="fw-bold small mb-1">Head of Family</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="1" data-field="head_of_family" id="filterHeadYes">
                                        <label class="form-check-label small" for="filterHeadYes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="0" data-field="head_of_family" id="filterHeadNo">
                                        <label class="form-check-label small" for="filterHeadNo">No</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Sex -->
                            <div class="mb-3">
                                <label class="fw-bold small mb-1">Sex</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Male" data-field="sex" id="filterSexMale">
                                        <label class="form-check-label small" for="filterSexMale">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Female" data-field="sex" id="filterSexFemale">
                                        <label class="form-check-label small" for="filterSexFemale">Female</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Civil Status -->
                            <div class="mb-3">
                                <label class="fw-bold small mb-1">Civil Status</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Single" data-field="civil_status" id="filterCivilSingle">
                                        <label class="form-check-label small" for="filterCivilSingle">Single</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Married" data-field="civil_status" id="filterCivilMarried">
                                        <label class="form-check-label small" for="filterCivilMarried">Married</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Widowed" data-field="civil_status" id="filterCivilWidowed">
                                        <label class="form-check-label small" for="filterCivilWidowed">Widowed</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Voter Status -->
                            <div class="mb-3">
                                <label class="fw-bold small mb-1">Voter Status</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="1" data-field="voter_status" id="filterVoterYes">
                                        <label class="form-check-label small" for="filterVoterYes">Registered</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="0" data-field="voter_status" id="filterVoterNo">
                                        <label class="form-check-label small" for="filterVoterNo">Not Registered</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Occupation -->
                            <div class="mb-3">
                                <label class="fw-bold small mb-1">Occupation</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Employed" data-field="occupation_display" id="filterOccEmp">
                                        <label class="form-check-label small" for="filterOccEmp">Employed</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Unemployed" data-field="occupation_display" id="filterOccUnemp">
                                        <label class="form-check-label small" for="filterOccUnemp">Unemployed</label>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="btnApplyFilter">Apply Filter</button>
                            <button type="button" class="btn btn-warning" id="btnResetModalFilters"><i class="fas fa-undo"></i>&nbsp;Reset</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- TABLE COLUMNS MODAL -->
<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Columns</h5>
      </div>
      <div class="modal-body">
        <div class="row g-2" id="tableColumnsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-residentArchive",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_resident_archive_v1"
  };
</script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/archiveResidentScript.js?v=20260219-1"></script>
</body>
</html>


