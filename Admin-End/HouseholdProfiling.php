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
            <div></div>

            <div class="d-flex align-items-center gap-3">
            <!-- SEARCH -->
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Address ID or Address Name">
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
                            <th>Address ID</th>
                            <th>Address</th>
                            <th>Households</th>
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
                            <h5 class="modal-title fw-bold">Filter Households</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <hr>

                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="fw-bold small mb-2">Area Number</label>
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 01" id="filterArea01">
                                        <label class="form-check-label small" for="filterArea01">Area 01</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 1A" id="filterArea1A">
                                        <label class="form-check-label small" for="filterArea1A">Area 1A</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 02" id="filterArea02">
                                        <label class="form-check-label small" for="filterArea02">Area 02</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 03" id="filterArea03">
                                        <label class="form-check-label small" for="filterArea03">Area 03</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 04" id="filterArea04">
                                        <label class="form-check-label small" for="filterArea04">Area 04</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 05" id="filterArea05">
                                        <label class="form-check-label small" for="filterArea05">Area 05</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input filter-checkbox" type="checkbox" value="Area 06" id="filterArea06">
                                        <label class="form-check-label small" for="filterArea06">Area 06</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2 mt-3">
                                <label class="fw-bold small mb-2">Number of Households</label>
                                <input type="number" id="filterHouseholdCountInput" class="form-control" min="0" placeholder="Enter number of households">
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
                <h3 class="fw-bold">Address Details: <span id="span-displayAddress" class="text-warning"></span></h3>
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
                            <p class="text-muted small mb-0">Phase Number:</p>
                            <p id="txt-modalPhaseNumber" class="fw-bold"></p>
                        </div>

                        <div class="col-md-3">
                            <p class="text-muted small mb-0">Subdivision:</p>
                            <p id="txt-modalSubdivision" class="fw-bold"></p>
                        </div>

                        <div class="col-md-3 mt-2">
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
                    <div id="div-householdGroups"></div>

                    <hr>

                    <h5 class="fw-bold mb-3" style="color: #000;">Other Residing Members</h5>
                    <ul id="list-otherResidents" class="small mb-0 ps-3"></ul>

                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ADD HOUSEHOLD MEMBER MODAL -->
<div class="modal fade" id="modal-addHouseholdMember" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="form-addHouseholdMember" class="modal-content p-4">
            <div class="modal-header border-0">
                <h4 class="fw-bold" style="font-family: 'Charis SIL Bold', serif; font-size: 28px; color: #e78924">
                    Add Household Member
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">All fields marked with <span class="text-danger">*</span> are required.</p>
                <h5 class="fw-bold mb-3">Member Information</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="small fw-bold">Family Head <span class="text-danger">*</span></label>
                        <select id="add-famHeadId" name="fam_head_id" class="form-select" required></select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="small fw-bold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="add-lastname" name="last_name" class="form-control" required autocomplete="family-name" placeholder="Last Name">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="add-firstname" name="first_name" class="form-control" required autocomplete="given-name" placeholder="First Name">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Middle Name</label>
                        <input type="text" id="add-middlename" name="middle_name" class="form-control" autocomplete="additional-name" placeholder="Middle Name">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Suffix (optional)</label>
                        <input type="text" id="add-suffix" name="suffix" class="form-control" placeholder="Suffix">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Birthday <span class="text-danger">*</span></label>
                        <input type="date" id="add-birthdate" name="birthdate" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btn-addMemberSave" class="btn btn-success px-4" disabled>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ASSIGN OTHER RESIDENT MODAL -->
<div class="modal fade" id="modal-assignResident" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="form-assignResident" class="modal-content p-4">
            <div class="modal-header border-0">
                <h5 class="fw-bold mb-0">Assign Resident to Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assign-residentId" name="assign_resident_id">
                <div class="mb-3">
                    <label class="small fw-bold">Select Household Head</label>
                    <select id="assign-famHeadSelect" name="assign_fam_head_id" class="form-select" required></select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/Admin-End/householdProfilingScript.js"></script>
</body>
</html>
