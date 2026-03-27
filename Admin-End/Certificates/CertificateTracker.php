<?php
require_once __DIR__ . '/../includes/admin_guard.php';

$certificateLaunchTab = strtolower(trim((string)($_GET['tab'] ?? '')));
$certificateLaunchDocument = strtolower(trim((string)($_GET['document'] ?? '')));
$certificateLaunchStage = strtolower(trim((string)($_GET['stage'] ?? '')));
$certificateLaunchEntry = strtolower(trim((string)($_GET['entry'] ?? '')));
$certificateLaunchFilterDocument = strtolower(trim((string)($_GET['filter_document'] ?? '')));
$isIdIssuanceTrackerView = $certificateLaunchEntry === 'id_issuance';
$certificateTrackerHeading = $certificateLaunchFilterDocument === '__clearances__'
  ? 'Clearance Issuance'
  : 'Certificate Issuance';
$barangayIdAdminNavActive = 'applications';

if ($certificateLaunchStage === 'release') {
  $barangayIdAdminNavActive = 'release';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificate Tracker</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/barangayIdAdminNav.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/CertificateTrackerStyle.css">
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; "><?= htmlspecialchars($certificateTrackerHeading, ENT_QUOTES, 'UTF-8') ?></h2>
    <hr class="mb-4">

    <!-- Page-level navigation -->
    <ul class="nav nav-tabs mb-0" id="certTrackerPageTabs" style="border-bottom:0">
      <li class="nav-item">
        <button class="nav-link active fw-semibold" id="tabDocRequests" type="button">
          <i class="fas fa-file-alt me-1"></i>Document Requests
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-semibold" id="tabManualIssuance" type="button">
          <i class="fas fa-pen-to-square me-1"></i>Manual Issuance
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-semibold" id="tabFeeRequests" type="button">
          <i class="fas fa-tags me-1"></i>Fee Change Requests
        </button>
      </li>
    </ul>

    <div id="docRequestsPanel" class="bg-white p-4 rounded-4 rounded-tl-0 shadow-sm border resident-masterlist-shell certificate-tracker-shell">
      <div class="admin-list-toolbar mb-3">
        <div class="admin-list-tabs">
          <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn stage-filter-btn active" data-stage-filter=""><?= $isIdIssuanceTrackerView ? 'ALL' : 'All' ?></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="pending"><?= $isIdIssuanceTrackerView ? 'PENDING' : 'Pending' ?> <span class="tab-count" id="pendingTabCount">0</span></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="release"><?= $isIdIssuanceTrackerView ? 'FOR PRINTING' : 'Release' ?> <span class="tab-count" id="releaseTabCount">0</span></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="completed"><?= $isIdIssuanceTrackerView ? 'COMPLETED' : 'Completed' ?></button>
        </div>

        <div class="admin-list-actions">
          <div class="input-group admin-search">
            <input type="text" id="searchInput" class="form-control" placeholder="Request ID, resident ID, name, address">
            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          </div>
          <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
            <i class="fas fa-filter"></i>
            <span class="visually-hidden">Filter</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnCertificateColumns" title="Columns" aria-label="Columns">
            <i class="fa-solid fa-sliders"></i>
            <span class="visually-hidden">Columns</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnRefreshList" title="Refresh table" aria-label="Refresh table">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span class="visually-hidden">Refresh</span>
          </button>
        </div>
      </div>

      <div class="table-responsive compact-admin-table-shell">
        <table id="table-certificateTracker" class="table align-middle compact-admin-table">
          <thead>
            <tr class="table-light">
              <th class="col-request-id">Request ID</th>
              <th class="col-resident-id">Resident ID</th>
              <th class="col-full-name">Full Name</th>
              <th class="col-document">Document Requested</th>
              <th class="col-purpose">Purpose</th>
              <th class="col-status">Status</th>
              <th class="col-submitted">Submitted Date</th>
              <th class="col-action">Action</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Loading requests...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="manualIssuancePanel" class="d-none bg-white p-4 rounded-4 shadow-sm border certificate-tracker-shell">
      <div class="manual-issuance-header mb-4">
        <div>
          <h5 class="fw-bold mb-1">Manual / Walk-in Document Issuance</h5>
          <p class="text-muted mb-0">
            Encode handwritten applications here, preview the final document before submission, then send the request into the release workflow. Paid documents still pass through finance, while free documents such as Barangay ID go straight to release. QR verification still applies to issued files.
          </p>
        </div>
        <div class="manual-issuance-badge">
          <i class="fas fa-shield-halved"></i>
          Admin-only issuance flow
        </div>
      </div>

      <div class="manual-issuance-steps mb-4">
        <div class="manual-step">
          <div class="manual-step-index">1</div>
          <div class="manual-step-title">Receive Form</div>
          <p class="manual-step-copy">Use the resident’s handwritten submission as the source document for this encoding flow.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">2</div>
          <div class="manual-step-title">Encode Details</div>
          <p class="manual-step-copy">Select a registered resident or encode a walk-in resident, then complete the matching certificate or clearance form.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">3</div>
          <div class="manual-step-title">Preview</div>
          <p class="manual-step-copy">Open the rendered document preview first so the encoded details match the physical form before submission.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">4</div>
          <div class="manual-step-title">Payment Routing</div>
          <p class="manual-step-copy">Only paid requests continue to finance for walk-in payment recording. Free requests such as Barangay ID skip finance and proceed directly to release.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">5</div>
          <div class="manual-step-title">Release by Print</div>
          <p class="manual-step-copy">After payment or interview handling, admin releases the final document through print while keeping QR verification active.</p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-8">
          <form id="manualIssuanceForm" novalidate>
            <input type="hidden" id="manualResidentId" name="resident_id">
            <input type="hidden" id="manualResidentUserId" name="resident_user_id">

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>Resident Source</h6>
                <span>Registered residents can be auto-filled, but every field stays editable for this encoded request.</span>
              </div>
              <div class="manual-issuance-mode-switch mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeExisting" value="existing" checked>
                  <label class="form-check-label" for="manualResidentModeExisting">
                    <i class="fas fa-user-check"></i>Registered Resident
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeWalkin" value="walkin">
                  <label class="form-check-label" for="manualResidentModeWalkin">
                    <i class="fas fa-user-pen"></i>Walk-in / Not Registered
                  </label>
                </div>
              </div>

              <div id="manualResidentLookupWrap">
                <div class="row g-3 align-items-end">
                  <div class="col-12">
                    <label class="form-label fw-semibold small">Search Registered Resident</label>
                    <div class="input-group">
                      <input type="text" id="manualResidentSearchInput" class="form-control" placeholder="Resident ID, user ID, or resident name">
                      <button type="button" class="btn btn-outline-secondary" id="manualResidentSearchBtn">
                        <i class="fas fa-search me-1"></i>Search
                      </button>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="manual-search-empty" id="manualResidentSearchHint">
                      Search a registered resident to auto-fill the form, or switch to walk-in mode to encode an unregistered resident.
                    </div>
                  </div>
                </div>
                <div class="mt-3 d-none" id="manualResidentResultsWrap">
                  <div class="manual-issuance-card-title mb-2">
                    <h6>Search Results</h6>
                    <span>Choose the resident record that matches the handwritten form.</span>
                  </div>
                  <div id="manualResidentResults" class="manual-resident-results"></div>
                </div>
              </div>

              <div id="manualSelectedResident" class="manual-selected-resident d-none mt-3">
                <strong id="manualSelectedResidentName">No resident linked yet</strong>
                <p id="manualSelectedResidentMeta">Linked registered resident details will auto-fill this form and can still be edited before submission.</p>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="manualClearSelectedResidentBtn">
                  <i class="fas fa-unlink me-1"></i>Unlink Resident
                </button>
              </div>
            </div>

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>1. Document Setup</h6>
                <span>Choose the form first. The matching fields and next step summary will update automatically.</span>
              </div>
              <div class="row g-3">
                <div class="col-lg-7">
                  <label for="manualDocumentType" class="form-label fw-semibold small">Certificate / Clearance Type <span class="text-danger">*</span></label>
                  <select id="manualDocumentType" class="form-select" required>
                    <option value="">Select a manual issuance form</option>
                  </select>
                </div>
                <div class="col-lg-5">
                  <label for="manualPurpose" class="form-label fw-semibold small">Purpose / Request For</label>
                  <input type="text" id="manualPurpose" class="form-control" placeholder="Purpose from the handwritten form">
                </div>
              </div>
            </div>

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>2. Personal Basic Information</h6>
                <span>These fields will be saved with the request and used in the generated document preview.</span>
              </div>
              <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                  <label for="manualLastName" class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                  <input type="text" id="manualLastName" class="form-control" required>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualFirstName" class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                  <input type="text" id="manualFirstName" class="form-control" required>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualMiddleName" class="form-label fw-semibold small">Middle Name</label>
                  <input type="text" id="manualMiddleName" class="form-control">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualSuffix" class="form-label fw-semibold small">Suffix</label>
                  <input type="text" id="manualSuffix" class="form-control" placeholder="Jr., Sr., III">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualBirthdate" class="form-label fw-semibold small">Birthdate</label>
                  <input type="date" id="manualBirthdate" class="form-control">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualSex" class="form-label fw-semibold small">Sex</label>
                  <select id="manualSex" class="form-select">
                    <option value="">Select sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualCivilStatus" class="form-label fw-semibold small">Civil Status</label>
                  <input type="text" id="manualCivilStatus" class="form-control" placeholder="Single, Married, etc.">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualContactNumber" class="form-label fw-semibold small">Contact Number</label>
                  <input type="text" id="manualContactNumber" class="form-control" placeholder="09XXXXXXXXX">
                </div>
                <div class="col-md-6">
                  <label for="manualBirthplace" class="form-label fw-semibold small">Birthplace</label>
                  <input type="text" id="manualBirthplace" class="form-control" placeholder="Place of birth">
                </div>
                <div class="col-md-3">
                  <label for="manualOccupation" class="form-label fw-semibold small">Occupation</label>
                  <input type="text" id="manualOccupation" class="form-control" placeholder="Occupation">
                </div>
                <div class="col-md-3">
                  <label for="manualReligion" class="form-label fw-semibold small">Religion</label>
                  <input type="text" id="manualReligion" class="form-control" placeholder="Religion">
                </div>
                <div class="col-12">
                  <label for="manualFullAddress" class="form-label fw-semibold small">Residential Address <span class="text-danger">*</span></label>
                  <textarea id="manualFullAddress" class="form-control" rows="2" required placeholder="House / street / phase / subdivision / area"></textarea>
                </div>
              </div>
            </div>

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>3. Document Specific Details</h6>
                <span id="manualSpecificFieldsHint">Select a certificate or clearance type to load its manual encoding fields.</span>
              </div>
              <div id="manualDynamicFields" class="row g-3"></div>
            </div>

            <div class="manual-issuance-card d-none" id="manualFeeWrap">
              <div class="manual-issuance-card-title">
                <h6>Tagged Clearance Fees</h6>
                <span>Tagged fees are only used for paid requests that continue to the finance step for walk-in payment recording.</span>
              </div>
              <div id="manualFeeList" class="manual-fee-list"></div>
              <div class="manual-fee-total">
                <span>Total Tagged Amount</span>
                <strong id="manualFeeTotal">PHP 0.00</strong>
              </div>
            </div>

            <div id="manualFormAlert" class="alert alert-warning d-none"></div>

            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" id="manualResetBtn">
                <i class="fas fa-rotate-left me-1"></i>Reset Form
              </button>
              <button type="button" class="btn btn-outline-primary" id="manualPreviewBtn">
                <i class="fas fa-eye me-1"></i>Preview Document
              </button>
              <button type="submit" class="btn btn-primary" id="manualSubmitBtn" disabled>
                <i class="fas fa-paper-plane me-1"></i>Submit Manual Issuance
              </button>
            </div>
          </form>
        </div>

        <div class="col-xl-4">
          <div class="manual-issuance-card sticky-xl-top" style="top: 1rem;">
            <div class="manual-issuance-card-title">
              <h6>Submission Summary</h6>
              <span>Use this as a quick check before you preview and submit.</span>
            </div>
            <div class="manual-issuance-summary mb-3">
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Resident Link</p>
                <p class="manual-summary-item-value" id="manualResidentSummary">Walk-in / not linked yet</p>
              </div>
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Document Type</p>
                <p class="manual-summary-item-value" id="manualDocumentSummary">Select a manual issuance form</p>
              </div>
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Next Step After Submit</p>
                <p class="manual-summary-item-value" id="manualNextStageSummary">Preview the document first to unlock submission.</p>
              </div>
            </div>
            <p class="manual-summary-note">
              Registered residents stay linked to their masterlist record, while walk-in residents can still be encoded and issued here without an online account. Paid requests continue to finance; free requests such as Barangay ID move directly to release. Issued files still carry the QR verification flow used by the existing generator.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── FEE CHANGE REQUESTS PANEL ──────────────────────────────────────── -->
    <div id="feeChangePanel" class="d-none bg-white p-4 rounded-4 shadow-sm border certificate-tracker-shell">

      <!-- Sub-tabs -->
      <ul class="nav nav-pills mb-4" id="feeChangeSubTabs">
        <li class="nav-item">
          <button class="nav-link active" id="subTabAddFeeType" type="button">
            <i class="fas fa-plus me-1"></i>Request New Fee Type
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="subTabEditPrice" type="button">
            <i class="fas fa-pen me-1"></i>Request Price Edit
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="subTabMyRequests" type="button">
            <i class="fas fa-list me-1"></i>Submitted Requests
          </button>
        </li>
      </ul>

      <!-- Sub-panel: Request New Fee Type -->
      <div id="fcrAddPanel">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="border rounded-3 p-3 bg-light">
              <h6 class="fw-semibold mb-3"><i class="fas fa-plus-circle me-1 text-primary"></i>Request New Fee Type</h6>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Fee Name <span class="text-danger">*</span></label>
                <input type="text" id="fcrAddName" class="form-control" placeholder="e.g. Inspection Fee">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Proposed Amount (₱)</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" id="fcrAddAmount" class="form-control" value="0.00" min="0" step="0.01">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Notes / Justification</label>
                <textarea id="fcrAddNotes" class="form-control" rows="3" placeholder="Why is this fee type needed?"></textarea>
              </div>
              <div id="fcrAddError" class="alert alert-danger d-none py-2 small mb-3" data-modal-inline="true"></div>
              <div id="fcrAddSuccess" class="alert alert-success d-none py-2 small mb-3" data-modal-inline="true"></div>
              <button type="button" class="btn btn-primary w-100" id="fcrAddSubmitBtn">
                <i class="fas fa-paper-plane me-1"></i>Submit Request
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sub-panel: Request Price Edit -->
      <div id="fcrEditPanel" class="d-none">
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-semibold mb-0">Current Fee Catalog</h6>
              <button class="btn btn-sm btn-outline-secondary" id="fcrEditRefreshBtn" title="Refresh">
                <i class="fa-solid fa-arrows-rotate"></i>
              </button>
            </div>
            <div class="table-responsive fee-catalog-table-shell">
              <table class="table table-sm table-hover align-middle fee-catalog-table">
                <thead class="table-light">
                  <tr>
                    <th>Fee Name</th>
                    <th>Current Amount</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody id="fcrEditCatalogBody">
                  <tr><td colspan="4" class="text-center text-muted py-3">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="border rounded-3 p-3 bg-light d-none" id="fcrEditFormWrap">
              <h6 class="fw-semibold mb-3" id="fcrEditFormTitle"><i class="fas fa-pen me-1 text-warning"></i>Request Price Edit</h6>
              <input type="hidden" id="fcrEditFeeTypeId">
              <div class="mb-2">
                <label class="form-label fw-semibold small">Fee Name</label>
                <input type="text" id="fcrEditFeeName" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label fw-semibold small">Current Amount</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="text" id="fcrEditCurrentAmount" class="form-control" readonly>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Proposed Amount (₱) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" id="fcrEditProposedAmount" class="form-control" min="0" step="0.01">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Notes</label>
                <textarea id="fcrEditNotes" class="form-control" rows="2"></textarea>
              </div>
              <div id="fcrEditError" class="alert alert-danger d-none py-2 small mb-3" data-modal-inline="true"></div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning flex-fill" id="fcrEditSubmitBtn">
                  <i class="fas fa-paper-plane me-1"></i>Submit
                </button>
                <button type="button" class="btn btn-outline-secondary" id="fcrEditCancelBtn">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            <div id="fcrEditSuccess" class="alert alert-success d-none py-2 small mb-3" data-modal-inline="true"></div>
            <div class="text-muted small text-center mt-4" id="fcrEditHint">
              <i class="fas fa-arrow-left me-1"></i>Select a fee type from the table to request a price edit
            </div>
          </div>
        </div>
      </div>

      <!-- Sub-panel: Submitted Requests -->
      <div id="fcrListPanel" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-semibold mb-0">My Submitted Requests</h6>
          <button class="btn btn-sm btn-outline-secondary" id="fcrListRefreshBtn" title="Refresh">
            <i class="fa-solid fa-arrows-rotate"></i>
          </button>
        </div>
        <div class="table-responsive compact-admin-table-shell">
          <table class="table align-middle mb-0 compact-admin-table compact-admin-table--wide">
            <thead>
              <tr class="table-light">
                <th>Type</th>
                <th>Fee Name</th>
                <th>Proposed Amount</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Submitted</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="fcrListBody">
              <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<div class="modal fade manual-photo-modal" id="manualBarangayIdPhotoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Barangay ID Photo</h5>
          <p class="text-muted small mb-0">Take a resident photo, adjust it inside the square guide, then crop and save it for the Barangay ID.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="manualBarangayIdPhotoStatus" class="alert alert-info py-2 small d-none"></div>

        <div id="manualBarangayIdCameraStage">
          <p class="manual-photo-stage-copy">
            Position the resident inside the square frame. The saved Barangay ID photo will use the centered square crop only.
          </p>
          <div class="manual-photo-workspace" id="manualBarangayIdCameraWorkspace">
            <video id="manualBarangayIdCameraVideo" playsinline autoplay muted></video>
            <div id="manualBarangayIdCameraEmpty" class="manual-photo-empty-state">
              Start the camera to capture the resident photo. If a linked resident photo already exists, you can also load and adjust that photo here.
            </div>
            <div class="manual-photo-frame"></div>
          </div>
        </div>

        <div id="manualBarangayIdCropStage" class="d-none">
          <p class="manual-photo-stage-copy">
            Drag the photo and use the zoom slider until the resident fits well inside the visible square. The darkened area will not be included.
          </p>
          <div class="manual-photo-workspace" id="manualBarangayIdCropWorkspace">
            <img id="manualBarangayIdCropImage" alt="Barangay ID crop preview">
            <div id="manualBarangayIdCropEmpty" class="manual-photo-empty-state d-none">
              Capture a photo first so it can be cropped and saved.
            </div>
            <div class="manual-photo-frame" id="manualBarangayIdCropFrame"></div>
          </div>
          <div class="manual-photo-controls">
            <label for="manualBarangayIdZoomRange">Zoom</label>
            <input type="range" id="manualBarangayIdZoomRange" min="100" max="400" step="1" value="100">
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex flex-wrap gap-2">
        <div class="manual-photo-footer-copy" id="manualBarangayIdPhotoFooterCopy">
          Allow camera access when prompted. Captured photos stay inside the Barangay ID request flow and will be cropped to a square before saving.
        </div>
        <button type="button" class="btn btn-outline-secondary" id="manualBarangayIdUseLinkedPhotoBtn">Use Linked Photo</button>
        <button type="button" class="btn btn-outline-secondary" id="manualBarangayIdStartCameraBtn">Start Camera</button>
        <button type="button" class="btn btn-outline-secondary d-none" id="manualBarangayIdRetakePhotoBtn">Retake</button>
        <button type="button" class="btn btn-primary" id="manualBarangayIdCapturePhotoBtn" disabled>Capture Photo</button>
        <button type="button" class="btn btn-success d-none" id="manualBarangayIdSavePhotoBtn">Crop and Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="fcrCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100">Cancel Fee Change Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body text-center">
        <p class="mb-3">Are you sure you want to cancel this fee change request?</p>
        <div id="fcrCancelModalError" class="alert alert-danger d-none py-2 small mb-0"></div>
      </div>
      <div class="modal-footer action-split border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="fcrCancelModalBackBtn">Back</button>
        <button type="button" class="btn btn-danger" id="fcrCancelModalConfirmBtn">Cancel Request</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content p-3" id="actionForm" enctype="multipart/form-data">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100" id="actionModalTitle">Update Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body">
        <input type="hidden" id="actionType" name="action">
        <input type="hidden" id="actionRequestId" name="request_id">
        <div id="actionPrompt" class="d-none mb-3"></div>

        <div id="actionReasonWrap" class="d-none mb-3">
          <label class="form-label">Reason</label>
          <textarea id="actionReason" name="reason" class="form-control" rows="3"></textarea>
        </div>

        <div id="actionAmountWrap" class="d-none mb-3">
          <label class="form-label">Amount</label>
          <input id="actionAmount" name="amount" type="number" min="0" step="0.01" class="form-control">
        </div>

        <div id="actionOrWrap" class="d-none mb-3">
          <label class="form-label">OR Number</label>
          <input id="actionOr" name="or_number" type="text" class="form-control">
        </div>

        <div id="actionIssuedWrap" class="d-none mb-3">
          <label class="form-label">Issued File (optional)</label>
          <input id="actionIssued" name="issued_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>

        <div id="actionBusinessApprovalWrap" class="d-none mb-3">
          <label class="form-label">Type of Approval</label>
          <input id="actionBusinessApproval" name="business_approval_type" type="hidden">
          <div id="actionBusinessApprovalOptions" class="d-grid gap-2">
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="not_banned">
              <span class="action-business-approval-copy">Not among those business or trade activities being banned to be established in this Barangay</span>
            </label>
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="no_objection">
              <span class="action-business-approval-copy">Interposes no objection for the issuance of the corresponding Business Permit being applied for.</span>
            </label>
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="temporary_clearance">
              <span class="action-business-approval-copy">Recommendations only the issuance of &quot;Temporary Barangay Clearance&quot; subject for revocation anytime provided that the requirements under existing Barangay Ordinance, Rules and Regulations should be complied with, otherwise this Barangay should take the necessary actions within legal bounds to stop its continued operations.</span>
            </label>
          </div>
        </div>

        <div id="actionPlateWrap" class="d-none mb-3">
          <label class="form-label">Plate Number</label>
          <input id="actionPlate" name="plate_number" type="text" class="form-control" placeholder="Enter plate number if applicable">
        </div>

        <div id="actionModalError" class="alert alert-danger d-none mb-0"></div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
        <button type="button" id="actionCancelBtn" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Return</button>
        <button type="submit" id="actionSubmitBtn" class="btn btn-primary flex-fill">Submit</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Filter Requests</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr>
      <div class="modal-body">
        <div class="mb-3">
          <label class="fw-bold small mb-2">Date Range</label>
          <div class="row g-2">
            <div class="col-6">
              <input type="date" class="form-control" id="filterDateFrom" aria-label="From date">
            </div>
            <div class="col-6">
              <input type="date" class="form-control" id="filterDateTo" aria-label="To date">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="fw-bold small mb-2">Type of Request</label>
          <div id="filterDocumentTypeList" class="d-grid gap-2"></div>
        </div>
        <div class="mb-3">
          <label class="fw-bold small mb-2">Area Number</label>
          <div id="filterAreaList" class="d-grid gap-2"></div>
        </div>
        <div class="mb-1">
          <label class="fw-bold small mb-2">Sector Membership</label>
          <div id="filterSectorList" class="d-grid gap-2"></div>
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

<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
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

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="viewModalBackBtn" class="btn btn-outline-secondary d-none">Back</button>
        </div>
        <div id="viewModalActions" class="d-flex flex-wrap gap-2 justify-content-center flex-grow-1"></div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="viewModalNextBtn" class="btn btn-primary">Next</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentProofTitle">Document Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="paymentProofReturnBtn" class="btn btn-secondary me-auto d-none">Return</button>
        <button type="button" id="paymentProofPrintBtn" class="btn btn-outline-dark d-none">Print</button>
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" id="paymentProofReleaseBtn" class="btn btn-success d-none">Release</button>
        <button type="button" id="paymentProofCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="idPrintProcessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Print Barangay ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-grid gap-3">
          <div id="idPrintProcessPreview" class="w-100 text-center"></div>
          <hr class="my-0">
          <p id="idPrintProcessStep" class="fw-semibold mb-1">Step 1 of 3</p>
          <p id="idPrintProcessCopy" class="text-muted mb-0">Print the front side of the Barangay ID first.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="idPrintProcessReturnBtn" class="btn btn-secondary me-auto">Return</button>
        <button type="button" id="idPrintProcessReprintBtn" class="btn btn-outline-dark">Reprint</button>
        <button type="button" id="idPrintProcessPrimaryBtn" class="btn btn-primary">Print Front</button>
      </div>
    </div>
  </div>
</div>

<style>
  #paymentProofWrap iframe {
    width: 100%;
    height: 70vh;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
  }
  #paymentProofWrap img {
    max-width: 100%;
    max-height: 70vh;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
  }
  #paymentProofWrap .doc-viewer-loading {
    min-height: 70vh;
    display: grid;
    place-items: center;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #475569;
    padding: 24px;
  }
  #paymentProofWrap .doc-viewer-loading__inner {
    display: grid;
    gap: 12px;
    justify-items: center;
  }
  #paymentProofWrap .doc-viewer-loading__spinner {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 3px solid #dbeafe;
    border-top-color: #2563eb;
    animation: doc-viewer-spin .8s linear infinite;
  }
  #paymentProofWrap .doc-viewer-loading__label {
    font-size: .95rem;
    font-weight: 600;
  }
  #idPrintProcessPreview .barangay-id-card {
    width: min(100%, 720px);
    margin-inline: auto;
  }
  @keyframes doc-viewer-spin {
    to { transform: rotate(360deg); }
  }
</style>

<div class="modal fade" id="submittedFileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="submittedFileTitle">Submitted Attachment Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="submittedFileWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="submittedFileReturnBtn" class="btn btn-secondary d-none">Return</button>
        <a id="submittedFileOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open Attachment in New Tab</a>
        <button type="button" id="submittedFileCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade tracker-profile-modal" id="residentProfileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="div-modalSizing">
    <div class="modal-content border-0 rounded-2 p-4">
      <div class="modal-header border-0">
        <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning">#—</span></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
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
                <div class="col-md-12 col-lg-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalName" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Sex:</p><p id="txt-modalSex" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Religion:</p><p id="txt-modalReligion" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Age:</p><p id="txt-modalAge" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Civil Status:</p><p id="txt-modalCivilStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Occupation:</p><p id="txt-modalOccupation" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Date of Birth:</p><p id="txt-modalDob" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Head of Family:</p><p id="txt-modalHeadOfFam" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Voter Status:</p><p id="txt-modalVoterStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-12">
                  <p class="text-muted small mb-0">Sector Membership:</p>
                  <p id="txt-modalSectorMembership" class="fw-bold mb-0">—</p>
                  <div id="div-modalSectorProofStatuses" class="mt-2 d-flex flex-wrap gap-2"></div>
                  <div id="div-modalSectorProofHint" class="text-muted small mt-1">
                    Sector proof status is based on uploaded documents tagged per sector.
                  </div>
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
            <div class="col-md-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalEmergencyFullName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Contact Number:</p><p id="txt-modalEmergencyContactNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Relationship:</p><p id="txt-modalEmergencyRelationship" class="fw-bold mb-0">—</p></div>
            <div class="col-md-12"><p class="text-muted small mb-0">Address:</p><p id="txt-modalEmergencyAddress" class="fw-bold mb-0">—</p></div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Address Information</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4" id="addr-unit-number"><p class="text-muted small mb-0">Unit Number:</p><p id="txt-modalUnitNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-house-number"><p class="text-muted small mb-0">House Number:</p><p id="txt-modalHouseNum" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-street-name"><p class="text-muted small mb-0">Street Name:</p><p id="txt-modalStreetName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-phase-number"><p class="text-muted small mb-0">Phase:</p><p id="txt-modalPhaseNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-subdivision"><p class="text-muted small mb-0">Subdivision:</p><p id="txt-modalSubdivision" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-area-number"><p class="text-muted small mb-0">Area Number:</p><p id="txt-modalAreaNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Barangay:</p><p id="txt-modalBarangay" class="fw-bold mb-0">Barangay San Jose</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Municipality / City:</p><p id="txt-modalMunicipalityCity" class="fw-bold mb-0">Rodriguez (Montalban)</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Province:</p><p id="txt-modalProvince" class="fw-bold mb-0">Rizal</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Ownership:</p><p id="txt-modalHouseOwnership" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Type:</p><p id="txt-modalHouseType" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Residency Duration:</p><p id="txt-modalResidencyDuration" class="fw-bold mb-0">—</p></div>
          </div>
        </div>

        <div id="div-statusReadOnlyGroup" class="mt-4">
          <h5 class="fw-bold mb-2" style="color: #000;">Resident Status</h5>
          <div id="div-statusBanner" class="mb-0"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="residentProfileReturnBtn">Return</button>
      </div>
    </div>
  </div>
</div>

<!-- Fee Tagging Modal (Admin tags clearance fees per request) -->
<div class="modal fade" id="feeTaggingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-tags me-2 text-warning"></i>Tag Clearance Fees</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="feeTaggingRequestId">
        <input type="hidden" id="feeTaggingMode">
        <div id="feeTaggingBody">Loading…</div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="text-muted small">Check the fees that apply, adjust amounts as needed, then confirm.</span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary d-none" id="feeTaggingReturnBtn">Return</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="feeTaggingSubmitBtn">Confirm Fees &amp; Send to Payment</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Fee Catalog Management Modal (also available from CertificateTracker for admins) -->
<div class="modal fade" id="feeCatalogModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Clearance Fee Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-7">
            <h6 class="fw-semibold mb-2">Existing Fee Types</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr><th>Name</th><th>Default Amount</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody id="feeCatalogTableBody">
                  <tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-5">
            <h6 class="fw-semibold mb-2" id="feeCatalogFormTitle">Add Fee Type</h6>
            <input type="hidden" id="feeCatalogFeeTypeId">
            <div class="mb-2">
              <label class="form-label small mb-1">Fee Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="feeCatalogFeeName" placeholder="e.g. Inspection Fee">
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">Default Amount (₱)</label>
              <input type="number" class="form-control form-control-sm" id="feeCatalogDefaultAmount" value="0.00" min="0" step="0.01">
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="feeCatalogIsActive" checked>
              <label class="form-check-label small" for="feeCatalogIsActive">Active</label>
            </div>
            <button type="button" class="btn btn-primary btn-sm w-100" id="feeCatalogSaveBtn">Save Fee Type</button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-1"
              onclick="editFeeType('','',0,1);document.getElementById('feeCatalogFormTitle').textContent='Add Fee Type';">
              Clear / New
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-certificateTracker",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_certificate_tracker_v1",
    defaultHiddenIdxs: [1, 4]
  };
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Shared/barangayIdDigital.js?v=20260324-01"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js?v=20260328-02"></script>
</body>
</html>
