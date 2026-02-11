<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/San_Jose_LOGO.jpg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Masterlist</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
</head>

<body>
<div class="d-flex" style="min-height: 100vh;">

    <!-- SIDEBAR INCLUDE -->
<?php
require_once "../PhpFiles/General/connection.php";
require_once 'includes/admin_guard.php';
include 'includes/sidebar.php';
?>

<?php
$pendingCount = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $statusStmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_type = 'Resident' AND (status_name = 'PendingVerification' OR status_name = 'Pending Verification' OR status_name LIKE 'Pending%Review%')
    ");
    $pendingStatusIds = [];
    if ($statusStmt) {
        $statusStmt->execute();
        $res = $statusStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $pendingStatusIds[] = (int)$row['status_id'];
        }
        $statusStmt->close();
    }

    if ($pendingStatusIds) {
        $inClause = implode(',', array_fill(0, count($pendingStatusIds), '?'));
        $types = str_repeat('i', count($pendingStatusIds));
        $countStmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM residentinformationtbl
            WHERE status_id_resident IN ($inClause)
        ");
        if ($countStmt) {
            $countStmt->bind_param($types, ...$pendingStatusIds);
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $pendingCount = (int)($countRow['total'] ?? 0);
            $countStmt->close();
        }
    }
}
?>

    <!-- MAIN CONTENT -->
    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
            Resident Masterlist
        </h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border">

            <!-- FILTER BUTTONS + SEARCH -->
        <div class="d-flex justify-content-between align-items-center mb-3">

            <!-- Status Filter Buttons -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary btn-sm me-2 status-filter-btn" data-filter="ALL">All</button>
                <button class="btn btn-outline-custom btn-sm me-2 status-filter-btn fw-bold" data-filter="VerifiedResident">Verified Resident</button>
                <button class="btn btn-outline-custom btn-sm me-2 status-filter-btn fw-bold" data-filter="NotVerified">Not Verified</button>
                <div class="position-relative">
                    <button class="btn btn-outline-custom btn-sm status-filter-btn fw-bold" data-filter="PendingVerification">
                        Pending Verification
                    </button>
                    <?php if ($pendingCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $pendingCount ?>
                        </span>
                    <?php endif; ?>
                </div>
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
                <table id="table-appData" class="table align-middle">
                    <thead>
                        <tr class="table-light">
                            <th>Resident ID</th>
                            <th>Resident Name</th>
                            <th>Account Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Filled dynamically by JS -->
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
  </div>
</div>

        </div>
    </main>
</div>

<!-- MODAL stays unchanged -->
<div class="modal fade" id="modal-viewEntry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="div-modalSizing" style="max-width: 1500px; width: 75vw;">
        <form id="form-updateStatus" method="POST" action="../PhpFiles/Admin-End/residentMasterlist.php" class="modal-content border-0 rounded-2 p-4">
            <div class="modal-header border-0">
                <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning"></span></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="input-appId" id="input-appId">

                <div id="div-infoGroup" class="div-infoContainer">

                    <div class="p-3 rounded-3 mb-3 border-0 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold mb-0" style="color: #000;">Personal Information</h5>
                        </div>

                        <div class="row g-3 align-items-center">
                            <div class="col-md-3 d-flex justify-content-center align-items-center">
                                <img id="img-modalIdPicture"
                                     src="../Images/Profile-Placeholder.png"
                                     alt="Resident 2x2 image"
                                     class="img-fluid rounded-circle"
                                     style="width: clamp(120px, 18vw, 170px); height: clamp(120px, 18vw, 170px); object-fit: cover;">
                            </div>

                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-12 col-lg-4">
                                        <p class="text-muted small mb-0">Full Name:</p>
                                        <p id="txt-modalName" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Sex:</p>
                                        <p id="txt-modalSex" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Religion:</p>
                                        <p id="txt-modalReligion" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Age:</p>
                                        <p id="txt-modalAge" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Civil Status:</p>
                                        <p id="txt-modalCivilStatus" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Occupation:</p>
                                        <p id="txt-modalOccupation" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Date of Birth:</p>
                                        <p id="txt-modalDob" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Head of Family:</p>
                                        <p id="txt-modalHeadOfFam" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <p class="text-muted small mb-0">Voter Status:</p>
                                        <p id="txt-modalVoterStatus" class="fw-bold mb-0"></p>
                                    </div>
                                    <div class="col-12">
                                        <p class="text-muted small mb-0">Sector Membership:</p>
                                        <p id="txt-modalSectorMembership" class="fw-bold mb-0"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="p-3 rounded-3 mb-3 border-0 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold mb-0" style="color: #000;">Emergency Contact</h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <p class="text-muted small mb-0">Full Name:</p>
                                <p id="txt-modalEmergencyFullName" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted small mb-0">Contact Number:</p>
                                <p id="txt-modalEmergencyContactNumber" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted small mb-0">Relationship:</p>
                                <p id="txt-modalEmergencyRelationship" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-12">
                                <p class="text-muted small mb-0">Address:</p>
                                <p id="txt-modalEmergencyAddress" class="fw-bold mb-0"></p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="p-3 rounded-3 mb-3 border-0 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold mb-0" style="color: #000;">Address Information</h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3" id="addr-unit-number">
                                <p class="text-muted small mb-0">Unit Number:</p>
                                <p id="txt-modalUnitNumber" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3" id="addr-house-number">
                                <p class="text-muted small mb-0">House Number:</p>
                                <p id="txt-modalHouseNum" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3" id="addr-street-name">
                                <p class="text-muted small mb-0">Street Name:</p>
                                <p id="txt-modalStreetName" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3" id="addr-phase-number">
                                <p class="text-muted small mb-0">Phase:</p>
                                <p id="txt-modalPhaseNumber" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3" id="addr-subdivision">
                                <p class="text-muted small mb-0">Subdivision:</p>
                                <p id="txt-modalSubdivision" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3" id="addr-area-number">
                                <p class="text-muted small mb-0">Area Number:</p>
                                <p id="txt-modalAreaNumber" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <p class="text-muted small mb-0">Barangay:</p>
                                <p id="txt-modalBarangay" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <p class="text-muted small mb-0">Municipality / City:</p>
                                <p id="txt-modalMunicipalityCity" class="fw-bold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <p class="text-muted small mb-0">Province:</p>
                                <p id="txt-modalProvince" class="fw-bold mb-0"></p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="p-3 rounded-3 border-0 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold mb-0" style="color: #000;">House Information</h5>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <p class="text-muted small mb-0">House Ownership:</p>
                                <p id="txt-modalHouseOwnership" class="fw-bold mb-0"></p>
                            </div>

                            <div class="col-md-4">
                                <p class="text-muted small mb-0">House Type:</p>
                                <p id="txt-modalHouseType" class="fw-bold mb-0"></p>
                            </div>

                            <div class="col-md-4">
                                <p class="text-muted small mb-0">Residency Duration:</p>
                                <p id="txt-modalResidencyDuration" class="fw-bold mb-0"></p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="p-3 rounded-3 border-0 bg-white d-none" id="view-verified-docs-wrapper">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold mb-0" style="color: #000;">Verified Documents</h5>
                        </div>
                        <div id="view-verified-docs-section">
                            <div id="view-verified-docs" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>

                </div>

                <div id="div-statusManagementGroup" class="mt-4">
                    <h5 class="fw-bold mb-2" style="color: #000;">Manage Status</h5>
                    <div id="div-statusBanner" class="mb-3"></div>

                    <label class="small fw-bold">Update Status:</label>
                    <select name="select-newStatus" id="select-newStatus" class="form-select mb-3" onchange="toggleDenialUI()">
                        <option value="PENDING">PENDING</option>
                        <option value="APPROVED">APPROVED</option>
                        <option value="DENIED">DENIED</option>
                    </select>

                    <div id="div-denialOptions" class="div-hide border p-3 rounded-3 bg-light">
                        <p class="text-danger fw-bold small mb-2">Reason for Denial:</p>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="radio-denialReason" id="radio-incomplete" value="Incomplete Requirements" checked onchange="toggleOthersBox()">
                            <label class="form-check-label small" for="radio-incomplete">Incomplete Requirements</label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="radio-denialReason" id="radio-invalid" value="Invalid Requirements" onchange="toggleOthersBox()">
                            <label class="form-check-label small" for="radio-invalid">Invalid Requirements</label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="radio-denialReason" id="radio-others" value="Others" onchange="toggleOthersBox()">
                            <label class="form-check-label small" for="radio-others">Others</label>
                        </div>

                        <textarea name="textarea-otherReason" id="textarea-otherReason" class="form-control mt-2 div-hide" placeholder="State reason..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="button-saveStatus" class="btn btn-success px-5">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT RESIDENT MODAL -->
<div class="modal fade" id="modal-editEntry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form id="form-editResident" class="modal-content p-4" method="POST" action="../PhpFiles/Admin-End/residentMasterlist.php">

      <div class="modal-header border-0">
        <h4 class="fw-bold" style="font-family: 'Charis SIL Bold', serif; font-size: 28px; color: #e78924">Edit Resident Profile</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="edit-residentId" name="resident_id">

        <!-- PERSONAL INFORMATION -->
        <h5 class="fw-bold mb-3">Personal Information</h5>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="small fw-bold">Last Name</label>
            <input type="text" id="edit-lastname" name="lastName" class="form-control" required autocomplete="family-name">
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">First Name</label>
            <input type="text" id="edit-firstname" name="firstName" class="form-control" required autocomplete="given-name">
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Middle Name</label>
            <input type="text" id="edit-middlename" name="middleName" class="form-control" autocomplete="additional-name">
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Suffix</label>
            <input type="text" id="edit-suffix" name="suffix" class="form-control" placeholder="Suffix">
          </div>

          <div class="col-md-3">
            <label class="small fw-bold">Date of Birth</label>
            <input type="date" id="edit-birthdate" name="dateOfBirth" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Sex</label>
            <select id="edit-sex" name="sex" class="form-select" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Civil Status</label>
            <select id="edit-civil" name="civilStatus" class="form-select" required>
              <option value="">Select</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Voter Status</label>
            <select id="edit-voterStatus" name="voterStatus" class="form-select" required>
              <option value="">Select</option>
              <option value="1">Registered</option>
              <option value="0">Not Registered</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="small fw-bold">Occupation</label>
            <input type="text" id="edit-occupation" name="occupation" class="form-control" placeholder="Occupation">
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Religion</label>
            <input type="text" id="edit-religion" name="religion" class="form-control" placeholder="Religion">
          </div>

          <div class="col-md-6">
            <label class="small fw-bold">Sector Membership</label>
            <div class="d-flex flex-wrap gap-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sectorPWD" name="sectorMembership[]" value="PWD">
                <label class="form-check-label small" for="sectorPWD">PWD</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sectorStudent" name="sectorMembership[]" value="Student">
                <label class="form-check-label small" for="sectorStudent">Student</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sectorSP" name="sectorMembership[]" value="Single Parent">
                <label class="form-check-label small" for="sectorSP">Single Parent</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sectorSenior" name="sectorMembership[]" value="Senior Citizen">
                <label class="form-check-label small" for="sectorSenior">Senior Citizen</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sectorIP" name="sectorMembership[]" value="Indigenous People">
                <label class="form-check-label small" for="sectorIP">Indigenous People</label>
              </div>
            </div>
          </div>
        </div>

        <br><hr><br>

        <!-- EMERGENCY CONTACT -->
        <input type="hidden" id="edit-userId" name="user_id">
        <h5 class="fw-bold mb-3">Emergency Contact</h5>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="small fw-bold">First Name</label>
            <input type="text" id="edit-ec-firstname" name="emergencyFirstName" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Last Name</label>
            <input type="text" id="edit-ec-lastname" name="emergencyLastName" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Middle Name</label>
            <input type="text" id="edit-ec-middlename" name="emergencyMiddleName" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="small fw-bold">Suffix</label>
            <input type="text" id="edit-ec-suffix" name="emergencySuffix" class="form-control" placeholder="Suffix">
          </div>

          <div class="col-md-4">
            <label class="small fw-bold">Contact Number</label>
            <input type="text" id="edit-ec-contact" name="emergencyPhoneNumber" class="form-control phone-input" placeholder="09XXXXXXXXX" required>
          </div>
          <div class="col-md-4">
            <label class="small fw-bold">Relationship</label>
            <input type="text" id="edit-ec-relationship" name="emergencyRelationship" class="form-control" placeholder="Relationship">
          </div>
          <div class="col-md-4">
            <label class="small fw-bold">Address</label>
            <input type="text" id="edit-ec-address" name="emergencyAddress" class="form-control" required>
          </div>
        </div>

        <br>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success px-4">Save Changes</button>
      </div>

    </form>
  </div>
</div>

<!-- VIEW SUBMITTED DOCUMENTS MODAL -->
<div class="modal fade docs-modal" id="modal-viewDocs" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <h4 class="fw-bold mb-0" id="docs-modal-title">Submitted Documents</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="docs-loading" class="text-muted small mb-2">Loading documents...</div>
        <div id="docs-empty" class="text-muted small d-none">No submitted documents found.</div>

        <div id="docs-section-pending" class="d-none mb-3">
          <div class="fw-bold mb-2">Pending</div>
          <div id="docs-list-pending" class="d-flex flex-column gap-2"></div>
        </div>

        <div id="docs-section-verified" class="d-none mb-3">
          <div class="fw-bold mb-2">Verified</div>
          <div id="docs-list-verified" class="d-flex flex-column gap-2"></div>
        </div>

        <div id="docs-section-denied" class="d-none">
          <div class="fw-bold mb-2">Denied</div>
          <div id="docs-list-denied" class="d-flex flex-column gap-2"></div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- DOCUMENT VIEWER MODAL -->
<div class="modal fade doc-viewer-modal" id="modal-docViewer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content p-3">
      <div class="modal-header border-0">
        <h5 class="fw-bold mb-0" id="doc-viewer-title">Document Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 d-none" id="doc-viewer-info">
          <div class="fw-bold" id="doc-viewer-fullname">—</div>
          <div class="row g-2 mt-2">
            <div class="col-md-4">
              <div class="fw-bold" id="doc-viewer-birthday">—</div>
            </div>
            <div class="col-md-8">
              <div class="fw-bold" id="doc-viewer-fulladdress">—</div>
            </div>
          </div>
        </div>
        <div id="doc-viewer-body" class="w-100 mb-3"></div>
        <div id="doc-viewer-actions" class="d-flex flex-wrap align-items-center gap-2"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" id="doc-viewer-return">Return</button>
      </div>
    </div>
  </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/Admin-End/residentMasterlistScript.js"></script>
</body>
</html>
