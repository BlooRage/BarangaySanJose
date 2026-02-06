<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Profiling</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
</head>

<body>
<div class="d-flex" style="min-height: 100vh;">

    <!-- SIDEBAR -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">

        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
            Household Profiling
        </h2>

        <hr><br>

        <div class="bg-white p-4 rounded-4 shadow-sm border">

            <!-- SEARCH -->
            <div class="d-flex justify-content-between align-items-center mb-3">
      
            <!-- Status Filter Buttons -->
            <div>
            </div>

            <div class="d-flex align-items-center gap-3">
            <!-- SEARCH -->
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Resident ID or Name">
                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton"><i class="fas fa-filter"></i>&nbsp;Filter</button>
            </div>
            

            </div>

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="table-light">
                            <th>Resident ID</th>
                            <th>Head of Family Name</th>
                            <th>Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
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

<!-- VIEW HOUSEHOLD MODAL (Similar to Resident Masterlist) -->
<div class="modal fade" id="modal-viewHousehold" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" id="div-modalSizing">
        <div class="modal-content border-0 rounded-2 p-4">
            <div class="modal-header border-0">
                <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning"></span></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="div-infoGroup" class="div-infoContainer">

                    <h5 class="fw-bold mb-3" style="color: #000;">Address Information</h5>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <p class="text-muted small mb-0">House Number</p>
                            <p id="txt-modalHouseNum" class="fw-bold"></p>
                        </div>

                        <div class="col-md-3">
                            <p class="text-muted small mb-0">Street Name</p>
                            <p id="txt-modalStreetName" class="fw-bold"></p>
                        </div>

                        <div class="col-md-3">
                            <p class="text-muted small mb-0">Subdivision:</p>
                            <p id="txt-modalSubdivision" class="fw-bold"></p>
                        </div>

                        <div class="col-md-3">
                            <p class="text-muted small mb-0">Area Number:</p>
                            <p id="txt-modalAreaNumber" class="fw-bold"></p>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Barangay:</p>
                            <p id="txt-modalBarangay" class="fw-bold"></p>
                        </div>

                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Municipality / City:</p>
                            <p id="txt-modalMunicipalityCity" class="fw-bold"></p>
                        </div>

                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Province:</p>
                            <p id="txt-modalProvince" class="fw-bold"></p>
                        </div>
                    </div>

                    <hr>

                    <h5 class="fw-bold mb-3" style="color: #000;">Household Information</h5>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Head of Family:</p>
                            <p id="txt-householdHeadName" class="fw-bold"></p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Number of Adults:</p>
                            <p id="txt-householdAdultCount" class="fw-bold"></p>
                            <ul id="list-householdAdults" class="small mb-0 ps-3"></ul>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted small mb-0">Number of Minors:</p>
                            <p id="txt-householdMinorCount" class="fw-bold"></p>
                            <ul id="list-householdMinors" class="small mb-0 ps-3"></ul>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/Admin-End/householdProfilingScript.js"></script>
</body>
</html>
