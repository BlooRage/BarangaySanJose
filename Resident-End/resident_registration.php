<?php
$allowUnregistered = true;
require_once __DIR__ . "/includes/resident_access_guard.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Resident Registration</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css"
    crossorigin="anonymous"
  />

  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/registrationStyle.css" />
  <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css" />

  <!-- Optional: server-side alert handling (if you use it) -->
  <script src="../JS-Script-Files/modalHandler.js"></script>

  <!-- Your wizard/validation JS -->
  <script src="../JS-Script-Files/Resident-End/registrationScript.js" defer></script>
</head>

<body>

  <!-- ================= NAVBAR ================= -->
  <div class="navbarWrapper">

    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
      <div class="container-fluid px-4">

        <a id="navbarBrand" class="navbar-brand" href="#">
          <img
            src="../Images/San_Jose_LOGO.jpg"
            alt="Logo"
            id="navbarLogo"
            class="d-inline-block align-text-center"
          />
          Barangay San Jose
        </a>

        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNav"
          aria-controls="navbarNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
          <ul id="navbarLinks" class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link logout-link" href="/BarangaySanJose/PhpFiles/Login/logout.php" data-logout-message="Are you sure you want to logout? Changes won't be saved.">Logout</a></li>
          </ul>
        </div>

      </div>
    </nav>
  </div>

  <section class="container my-5">
    <form
      id="residentRegistrationForm"
      action="../PhpFiles/Resident-End/residentRegistration.php"
      method="POST"
      enctype="multipart/form-data"
      autocomplete="on"
    >
      <input type="hidden" name="wizardStep" id="wizardStep" value="0" />
      <input type="hidden" name="clientSubmittedAt" value="" id="clientSubmittedAt" />

      <div class="formWizard">

        <div id="progressHeader" class="text-center mb-5">
          <h1 style="font-size: 48px; text-align: center; font-family: 'Charis SIL Bold';">Resident Profiling</h1>
          <p>All fields marked with <span class="text-danger">*</span> are required.</p>

          <div id="progressContainer" class="my-4">
            <ol class="progress-steps">
              <li class="active">Privacy Notice</li>
              <li>Head of the Family</li>
              <li>Personal Information</li>
              <li>Home Address</li>
              <li>Documents</li>
            </ol>
          </div>
        </div>

        <!-- ================= STEP 1: PRIVACY ================= -->
        <div id="form-privacyPolicy" class="step active-step">
          <h2 class="section-title mb-3">Privacy Policy</h2>

          <h4>1. Commitment to Data Privacy</h4>
          <p>
            Barangay San Jose is committed to protecting the privacy and personal information of all users of the
            Web-Based Barangay System. This Privacy Policy explains how personal data is collected, used, stored,
            and protected in compliance with the Data Privacy Act of 2012 (Republic Act No. 10173).
          </p>

          <h4>2. Information Collected</h4>
          <p>The system collects only information necessary for legitimate barangay operations, including:</p>
          <ul>
            <li><strong>Personal Information:</strong> Full name, residential address, contact number, email address,
              birthdate, and other relevant resident details.</li>
            <li><strong>Identification and Supporting Documents:</strong> Barangay ID information and other documents
              required for verification and service processing.</li>
            <li><strong>System and Transaction Records:</strong> Account registration details, login history, document
              requests, complaints, blotter records, appointments, payment submissions, notifications, and system-generated logs.</li>
          </ul>

          <h4>3. Purpose of Data Collection</h4>
          <p>Personal data collected through the system is used exclusively to:</p>
          <ul>
            <li>Process barangay document requests, permits, and clearances.</li>
            <li>Verify resident identity and eligibility for services.</li>
            <li>Manage complaints, blotter reports, appointments, and transactions.</li>
            <li>Send SMS or email notifications related to system activities and announcements.</li>
            <li>Generate administrative reports for monitoring, auditing, and decision-making.</li>
          </ul>

          <h4>4. Data Security Measures</h4>
          <p>The system implements security measures such as:</p>
          <ul>
            <li>Encryption of sensitive data.</li>
            <li>Secure authentication and role-based access control.</li>
            <li>Restricted access limited to authorized barangay officials and employees.</li>
            <li>System logging and regular backups.</li>
          </ul>

          <h4>5. Data Sharing and Disclosure</h4>
          <p>Personal data may only be disclosed to:</p>
          <ul>
            <li>Authorized barangay officials and employees.</li>
            <li>Relevant government units for official transactions.</li>
            <li>Law enforcement or authorities when required by law.</li>
          </ul>

          <h4>6. Data Subject Rights</h4>
          <ul>
            <li>Access personal data.</li>
            <li>Request correction of inaccurate information.</li>
            <li>Request deletion or restriction of data, subject to law.</li>
            <li>File complaints with the National Privacy Commission.</li>
          </ul>

          <h4>7. Consent</h4>
          <p>
            By registering and using the system, users voluntarily consent to the collection,
            processing, and storage of their personal data.
          </p>

          <hr class="my-4">

          <h2 class="section-title mb-3">Terms and Conditions</h2>

          <h4>1. Acceptance of Terms</h4>
          <p>
            By accessing the Web-Based Barangay System, users agree to comply with these Terms and Conditions.
            Non-acceptance results in denial of access.
          </p>

          <h4>2. Authorized Users</h4>
          <p>
            The system is intended for residents of Barangay San Jose and authorized barangay personnel only.
          </p>

          <h4>3. User Account Security</h4>
          <p>
            Users are responsible for safeguarding their login credentials and all activities under their account.
          </p>

          <h4>4. Proper Use of the System</h4>
          <ul>
            <li>No false or misleading information</li>
            <li>No impersonation</li>
            <li>No unauthorized system access</li>
            <li>No misuse or disruption</li>
          </ul>

          <h4>5. Transactions and Requests</h4>
          <p>
            All requests are subject to verification and approval. Submission does not guarantee approval.
          </p>

          <h4>6. System Availability</h4>
          <p>
            The barangay is not liable for temporary service interruptions due to maintenance or technical issues.
          </p>

          <hr class="my-4">

          <h2 class="section-title mb-3">Disclaimer</h2>
          <p>
            The system is provided to improve barangay services. Barangay San Jose does not guarantee uninterrupted
            availability or approval of all submissions.
          </p>
          <p>
            Users are responsible for the accuracy of information submitted and for securing their accounts.
          </p>

          <h5 class="mt-4 text-dark" style="color: #000;">
            Do you agree to the Privacy Policy, Terms and Conditions, and Disclaimer?
            <span class="text-danger">*</span>
          </h5>

          <div id="div-policyGroup" class="form-check form-switch mb-4 d-flex align-items-center">
            <input
              class="form-check-input"
              type="checkbox"
              role="switch"
              id="agreePolicy"
              name="privacyConsent"
              value="1"
              data-error-target="#div-policyGroup"
              required
            />
            <label class="form-check-label ms-2" for="agreePolicy" style="color: #000;">
              I agree to the Privacy Policy, Terms and Conditions, and Disclaimer.
            </label>
          </div>

          <div class="text-end">
            <button type="button" class="btn btn-primary px-4 next-btn" disabled>Next</button>
          </div>
        </div>

        <!-- ================= STEP 2: HEAD OF FAMILY ================= -->
        <div id="form-headFamily" class="step">
          <h2 class="section-title mb-0">Head of the Family</h2>
          <hr class="section-hr">

          <div id="headQualifications" class="mb-4">
            <h3>Qualifications for Head of the Family:</h3>
            <ul>
              <li>Primary decision-maker for the household</li>
              <li>Financially responsible for the family</li>
              <li>Registered as the main contact person</li>
            </ul>
          </div>

          <div class="row mb-4 justify-content-center">
            <div class="col-md-6">
              <label class="form-label" for="isHead">Are you the Head of the Family? <span class="text-danger">*</span></label>
              <select id="isHead" name="isHead" class="form-select" required>
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-4 prev-btn">Previous</button>
            <button type="button" class="btn btn-primary px-4 next-btn" disabled>Next</button>
          </div>
        </div>

        <!-- ================= STEP 3: PERSONAL INFO ================= -->
        <div id="form-personalInformation" class="step">
          <h2 class="section-title mb-0">Personal Information</h2>
          <hr class="section-hr">

          <!-- Name -->
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label" for="lastName">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="lastName" name="lastName" required autocomplete="family-name">
            </div>

            <div class="col-md-3">
              <label class="form-label" for="firstName">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="firstName" name="firstName" required autocomplete="given-name">
            </div>

            <div class="col-md-3">
              <label class="form-label" for="middleName">Middle Name</label>
              <input type="text" class="form-control" id="middleName" name="middleName" autocomplete="additional-name">
            </div>

            <div class="col-md-3">
              <label class="form-label" for="suffixSelect">Suffix</label>
              <select class="form-select toggle-other" name="suffix" id="suffixSelect" data-target="suffix-other">
                <option value="">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="I">I</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
                <option value="V">V</option>
                <option value="VI">VI</option>
                <option value="VII">VII</option>
                <option value="VIII">VIII</option>
                <option value="IX">IX</option>
                <option value="X">X</option>
                <option value="Other">Other</option>
              </select>

              <input
                type="text"
                class="form-control mt-2 d-none suffix-other"
                name="suffixOther"
                id="suffixOther"
                placeholder="Specify suffix"
              />
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label class="form-label" for="dateOfBirth">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" required>
            </div>

            <div class="col-md-2">
              <label class="form-label" for="sex">Sex <span class="text-danger">*</span></label>
              <select class="form-select" id="sex" name="sex" required>
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label" for="civilStatus">Civil Status <span class="text-danger">*</span></label>
              <select class="form-select" id="civilStatus" name="civilStatus" required>
                <option value="">Select</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label" for="religionSelect">Religion <span class="text-danger">*</span></label>
              <select
                class="form-select toggle-other"
                name="religion"
                id="religionSelect"
                data-target="religion-other"
                required>
                <option value="">Select</option>
                <option value="Roman Catholic">Roman Catholic</option>
                <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                <option value="Islam">Islam</option>
                <option value="Other">Other</option>
              </select>

              <input
                type="text"
                class="form-control mt-2 d-none religion-other"
                name="religionOther"
                id="religionOther"
                placeholder="Please specify religion"
              />
            </div>

            <div class="col-md-3">
              <label class="form-label" for="familyRole">Family Role <span class="text-danger">*</span></label>
              <select class="form-select" id="familyRole" name="familyRole" required>
                <option value="">Select</option>
                <option value="Spouse">Spouse</option>
                <option value="Child">Child</option>
                <option value="Parent">Parent</option>
                <option value="Sibling">Sibling</option>
              </select>
            </div>
          </div>

          <!-- Contact (UPDATED: +63 UI, locked, filled by AJAX) -->
          <div class="row mb-4">
            <div class="col-md-6">
              <label class="form-label" for="phoneNumber">Phone Number <span class="text-danger">*</span></label>

              <div class="input-group" id="phoneNumberGroup">
                <span class="input-group-text">
                  <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH Flag" width="24" style="margin-right:5px;">+63
                </span>
                <input
                  type="tel"
                  class="form-control phone-input"
                  id="phoneNumber"
                  value=""
                  readonly
                  disabled
                  data-error-target="#phoneNumberGroup"
                >
              </div>

              <!-- Disabled inputs don't POST; hidden will submit -->
              <input type="hidden" name="phoneNumber" id="phoneNumberHidden" value="">
              <div class="small text-muted mt-1">This is your account phone number and cannot be edited here.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="emailAddress">Email Address <span class="text-danger">*</span></label>
              <input
                type="email"
                class="form-control email-input"
                id="emailAddress"
                value=""
                readonly
                disabled
              >
              <input type="hidden" name="emailAddress" id="emailAddressHidden" value="">
              <div class="small text-muted mt-1">This is your account email and cannot be edited here.</div>
            </div>
          </div>

          <!-- Registered Voter & Occupation -->
          <div class="row mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold">
                Are you a Registered Voter? <span class="text-danger">*</span>
              </label>

              <div class="btn-group w-100 voter-toggle-group" id="registeredVoterGroup" role="group" aria-label="Registered Voter" style="color:#000">
                <input type="radio" class="btn-check" name="registeredVoter" id="voterYes" value="yes" required data-error-target="#registeredVoterGroup" style="color: #000;">
                <label class="btn btn-outline-warning" for="voterYes">Yes</label>

                <input type="radio" class="btn-check" name="registeredVoter" id="voterNo" value="no" required data-error-target="#registeredVoterGroup" style="color: #000;">
                <label class="btn btn-outline-warning" for="voterNo">No</label>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">
                Employment Status <span class="text-danger">*</span>
              </label>

              <div class="btn-group w-100 voter-toggle-group" id="employmentStatusGroup" role="group" aria-label="Employment Status">
                <input type="radio" class="btn-check" name="occupationStatus" id="employed" value="employed" required data-error-target="#employmentStatusGroup">
                <label class="btn btn-outline-warning" for="employed">Employed</label>

                <input type="radio" class="btn-check" name="occupationStatus" id="unemployed" value="unemployed" required data-error-target="#employmentStatusGroup">
                <label class="btn btn-outline-warning" for="unemployed">Unemployed</label>
              </div>

              <div id="occupationWrapper" class="mt-3 d-none">
                <label class="form-label" for="occupationInput">Occupation / Job Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="occupation" id="occupationInput">
              </div>
            </div>
          </div>

          <!-- Sector -->
          <h3 class="section-title mt-4 mb-0">Sector Membership</h3>

          <div class="row mb-4">
            <div class="col-md-12">
              <div id="sectorGroupCard">
                <div class="row g-2">

                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="sectorPWD" name="sectorMembership[]" value="PWD">
                      <label class="form-check-label" for="sectorPWD">Person with Disability (PWD)</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="sectorSP" name="sectorMembership[]" value="Single Parent">
                      <label class="form-check-label" for="sectorSP">Single Parent</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="sectorStudent" name="sectorMembership[]" value="Student">
                      <label class="form-check-label" for="sectorStudent">Student</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="sectorSenior" name="sectorMembership[]" value="Senior Citizen">
                      <label class="form-check-label" for="sectorSenior">Senior Citizen</label>
                    </div>
                  </div>

                  <div class="col-md-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="sectorIP" name="sectorMembership[]" value="Indigenous People">
                      <label class="form-check-label" for="sectorIP">Indigenous People</label>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <!-- Emergency -->
          <h2 class="section-title mt-4 mb-0 text-center">Emergency Contact</h2>
          <hr class="section-hr">

          <div class="row emergency-grid">
            <div class="col-md-3">
              <label class="form-label" for="emergencyLastName">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="emergencyLastName" name="emergencyLastName" required>
            </div>

            <div class="col-md-3">
              <label class="form-label" for="emergencyFirstName">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="emergencyFirstName" name="emergencyFirstName" required>
            </div>

            <div class="col-md-3">
              <label class="form-label" for="emergencyMiddleName">Middle Name</label>
              <input type="text" class="form-control" id="emergencyMiddleName" name="emergencyMiddleName">
            </div>

            <div class="col-md-3">
              <label class="form-label" for="emergencySuffixSelect">Suffix</label>
              <select
                class="form-select toggle-other"
                name="emergencySuffix"
                id="emergencySuffixSelect"
                data-target="emergency-suffix-other"
              >
                <option value="">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="I">I</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
                <option value="V">V</option>
                <option value="VI">VI</option>
                <option value="VII">VII</option>
                <option value="VIII">VIII</option>
                <option value="IX">IX</option>
                <option value="X">X</option>
                <option value="Other">Other</option>
              </select>

              <input
                type="text"
                class="form-control mt-2 d-none emergency-suffix-other"
                name="emergencySuffixOther"
                id="emergencySuffixOther"
                placeholder="Specify suffix"
              />
            </div>

            <!-- Emergency Phone (UPDATED to +63 input-group) -->
            <div class="col-md-4">
              <label class="form-label" for="emergencyPhoneNumber">Contact Number <span class="text-danger">*</span></label>

              <div class="input-group" id="emergencyPhoneGroup">
                <span class="input-group-text">
                  <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH Flag" width="24" style="margin-right:5px;">+63
                </span>
                <input
                  type="tel"
                  class="form-control phone-input"
                  id="emergencyPhoneNumber"
                  name="emergencyPhoneNumber"
                  placeholder="9XXXXXXXXX"
                  maxlength="10"
                  inputmode="numeric"
                  required
                  data-error-target="#emergencyPhoneGroup"
                >
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="emergencyRelationship">Relationship <span class="text-danger">*</span></label>
              <select
                class="form-select toggle-other"
                id="emergencyRelationship"
                name="emergencyRelationship"
                data-target="emergency-relationship-other"
                required>
                <option value="">Select</option>
                <option value="Parent">Parent</option>
                <option value="Spouse">Spouse</option>
                <option value="Sibling">Sibling</option>
                <option value="Child">Child</option>
                <option value="Relative">Relative</option>
                <option value="Friend">Friend</option>
                <option value="Guardian">Guardian</option>
                <option value="Other">Other</option>
              </select>
              <input
                type="text"
                class="form-control mt-2 d-none emergency-relationship-other"
                id="emergencyRelationshipOther"
                name="emergencyRelationshipOther"
                placeholder="Please specify relationship"
              />
            </div>

            <div class="col-md-4">
              <label class="form-label" for="emergencyAddress">Address <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="emergencyAddress" name="emergencyAddress" required>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-4 prev-btn">Previous</button>
            <button type="button" class="btn btn-primary px-4 next-btn" disabled>Next</button>
          </div>
        </div>

        <!-- ================= STEP 4: ADDRESS ================= -->
        <div id="form-addressInformation" class="step">
          <h2 class="section-title mb-0">Address Information</h2>
          <hr class="section-hr">

          <div class="row mb-3">
            <div class="col-12">
              <label class="form-label" for="addressSystem">Address System <span class="text-danger">*</span></label>
              <select class="form-select" id="addressSystem" name="addressSystem" required>
                <option value="">Select</option>
                <option value="house">House Numbering System</option>
                <option value="lot_block">Lot/Block System</option>
              </select>
            </div>
          </div>

          <!-- House numbering system -->
          <div id="houseSystemWrapper" class="d-none">
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label" for="unitNumber">Unit / Apartment Number</label>
                <input type="text" class="form-control" id="unitNumber" name="unitNumber">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="houseNumber">House Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="houseNumber" name="houseNumber">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="streetName">Street Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="streetName" name="streetName">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label" for="subdivisionSitio">Subdivision</label>
                <input type="text" class="form-control" id="subdivisionSitio" name="subdivisionSitio">
              </div>

              <div class="col-md-6">
                <label class="form-label" for="areaNumber">Area <span class="text-danger">*</span></label>
                <select class="form-select" id="areaNumber" name="areaNumber">
                  <option value="">Select</option>
                  <option value="Area 01">Area 01</option>
                  <option value="Area 1A">Area 1A</option>
                  <option value="Area 02">Area 02</option>
                  <option value="Area 03">Area 03</option>
                  <option value="Area 04">Area 04</option>
                  <option value="Area 05">Area 05</option>
                  <option value="Area 06">Area 06</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Lot/Block system -->
          <div id="lotBlockSystemWrapper" class="d-none">
            <div class="row mb-3">
              <div class="col-md-3">
                <label class="form-label" for="unitNumberLot">Unit / Apartment Number</label>
                <input type="text" class="form-control" id="unitNumberLot" name="unitNumber">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="lotNumber">Lot <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="lotNumber" name="lotNumber">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="blockNumber">Block <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="blockNumber" name="blockNumber">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="phaseNumber">Phase <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="phaseNumber" name="phaseNumber">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label" for="subdivisionLotBlock">Subdivision</label>
                <input type="text" class="form-control" id="subdivisionLotBlock" name="subdivisionSitio">
              </div>

              <div class="col-md-6">
                <label class="form-label" for="areaNumberLotBlock">Area <span class="text-danger">*</span></label>
                <select class="form-select" id="areaNumberLotBlock" name="areaNumber">
                  <option value="">Select</option>
                  <option value="Area 01">Area 01</option>
                  <option value="Area 1A">Area 1A</option>
                  <option value="Area 02">Area 02</option>
                  <option value="Area 03">Area 03</option>
                  <option value="Area 04">Area 04</option>
                  <option value="Area 05">Area 05</option>
                  <option value="Area 06">Area 06</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row mb-4">
            <div class="col-md-4">
              <label class="form-label" for="barangayDisplay">Barangay</label>
              <input type="text" class="form-control readonly-highlight" id="barangayDisplay" value="Barangay San Jose" readonly>
              <input type="hidden" name="barangay" value="Barangay San Jose">
            </div>

            <div class="col-md-4">
              <label class="form-label" for="municipalityDisplay">Municipality / City</label>
              <input type="text" class="form-control readonly-highlight" id="municipalityDisplay" value="Rodriguez" readonly>
              <input type="hidden" name="municipality" value="Rodriguez">
            </div>

            <div class="col-md-4">
              <label class="form-label" for="provinceDisplay">Province</label>
              <input type="text" class="form-control readonly-highlight" id="provinceDisplay" value="Rizal" readonly>
              <input type="hidden" name="province" value="Rizal">
            </div>
          </div>

          <h2 class="section-title mt-4 mb-0">House Information</h2>
          <hr class="section-hr">

          <div class="house-info-center">
            <div class="row mb-4">
              <div class="col-md-3">
                <label class="form-label" for="houseOwnership">House Ownership <span class="text-danger">*</span></label>
                <select class="form-select" id="houseOwnership" name="houseOwnership" required>
                  <option value="">Select</option>
                  <option value="Owner">Owner</option>
                  <option value="Tenant">Tenant</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label" for="houseTypeSelect">House Type <span class="text-danger">*</span></label>
                <select
                  class="form-select toggle-other"
                  name="houseType"
                  required
                  data-target="houseType-other"
                  id="houseTypeSelect"
                >
                  <option value="">Select</option>
                  <option value="Concrete">Concrete</option>
                  <option value="Semi-Concrete">Semi-Concrete</option>
                  <option value="Wood/Light Materials">Wood/Light Materials</option>
                  <option value="Makeshift/Salvaged Materials">Makeshift/Salvaged Materials</option>
                  <option value="Shanty/Informal">Shanty/Informal</option>
                  <option value="Other">Other</option>
                </select>

                <input
                  type="text"
                  class="form-control mt-2 d-none houseType-other"
                  name="houseTypeOther"
                  id="houseTypeOther"
                  placeholder="Specify house type"
                >
              </div>

              <div class="col-md-3">
                <label class="form-label" for="residencyDuration">Residency Duration <span class="text-danger">*</span></label>
                <select class="form-select" id="residencyDuration" name="residencyDuration" required>
                  <option value="">Select</option>
                  <option value="Less than 6 months">Less than 6 months</option>
                  <option value="6 months - 1 year">6 months - 1 year</option>
                  <option value="2-3 years">2-3 years</option>
                  <option value="4-5 years">4-5 years</option>
                  <option value="More than 5 years">More than 5 years</option>
                </select>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-4 prev-btn">Previous</button>
            <button type="button" class="btn btn-primary px-4 next-btn" disabled>Next</button>
          </div>
        </div>

        <!-- ================= STEP 5: DOCUMENTS ================= -->
        <div id="form-proofIdentity" class="step">
          <h2 class="section-title mb-0">Proof of Identification and Residency</h2>
          <hr class="section-hr">

          <div class="row g-3 mb-4" id="proofTypeWrapper">
            <div>
              <label class="form-label fw-semibold">
                Type of Proof of Identification <span class="text-danger">*</span>
              </label>
              <select class="form-select" id="proofTypeSelect" required>
                <option value="">Select</option>
                <option value="ID">ID</option>
                <option value="Document">Document</option>
              </select>
              <div class="small text-danger mt-1">
                The document or ID you present must prove you reside in the barangay.
              </div>
            </div>
          </div>


          <!-- Document fields -->
          <div id="proofIdentityFields" >
            <div id="idProofWrapper" class="d-none">
            <!-- Row 1 -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label" for="idTypeSelect">ID Type <span class="text-danger">*</span></label>
                <select class="form-select" name="idType" id="idTypeSelect">
                  <option value="">Select</option>
                  <option value="Passport">Passport</option>
                  <option value="Driver's License">Driver's License</option>
                  <option value="PhilHealth ID">PhilHealth ID</option>
                  <option value="Voter's ID">Voter's ID</option>
                  <option value="National ID">National ID</option>
                  <option value="Barangay ID">Barangay ID</option>
                  <option value="Student ID">Student ID</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label" for="idNumberInput">ID Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="idNumber" id="idNumberInput">
              </div>
            </div>

            <!-- Student ID only -->
            <div class="row g-3 mb-3 d-none" id="schoolNameWrapper">
              <div class="col-12">
                <label class="form-label" for="schoolNameInput">School Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="schoolName" id="schoolNameInput">
              </div>
            </div>

            <!-- Uploads -->
            <div class="row mb-2 mt-3">
              <label class="form-label" for="idFrontInput">Upload ID Front <span class="text-danger">*</span></label>
              <div class="upload-box position-relative">
                <div class="upload-text">
                  <i class="fa-solid fa-upload"></i>
                  <span>Drag & drop file</span>
                </div>
                <div class="upload-subtext mt-1">PDF or image</div>
                <input
                  type="file"
                  class="form-control upload-input"
                  id="idFrontInput"
                  name="idFront"
                  accept="image/*,.pdf,.heic,.heif">
              </div>
                <label class="form-label mb-2 mt-3" for="idBackInput">Upload ID Back <span class="text-danger">*</span></label>
              <div class="upload-box position-relative">
                <div class="upload-text">
                  <i class="fa-solid fa-upload"></i>
                  <span>Drag & drop file</span>
                </div>
                <div class="upload-subtext mt-1">PDF or image</div>
                <input
                  type="file"
                  class="form-control upload-input"
                  id="idBackInput"
                  name="idBack"
                  accept="image/*,.pdf,.heic,.heif">
              </div>
            </div>
</div>
<div id="documentProofWrapper" class="d-none">

  <div class="row g-3 mb-3">
    <div class="col-12">
      <label class="form-label" for="documentTypeSelect">Document Type <span class="text-danger">*</span></label>
      <select class="form-select" id="documentTypeSelect" name="documentType">
        <option value="">Select</option>
        <option value="Billing Statement">Billing Statement</option>
        <option value="HOA Signed Certification of Residency">HOA Signed Certification of Residency</option>
      </select>
    </div>
  </div>

  <label class="form-label fw-semibold mb-3">
    Upload Supporting Document(s) <span class="text-danger">*</span>
  </label>

  <div id="documentUploadList" class="row">

    <!-- Attachment 1 -->
    <div class=" position-relative">
  <div class="upload-box position-relative">
    <div class="upload-text">
      <i class="fa-solid fa-upload"></i>
      <span>Drag & drop file</span>
    </div>
    <div class="upload-subtext mt-1">
      PDF or image
    </div>

    <input
      type="file"
      class="form-control upload-input"
      name="documentProof[]"
      accept=".pdf,image/*,.heic,.heif"
      required>
  </div>

  <small class="text-muted d-block text-center mt-2">
    Attachment 1
  </small>
</div>


  </div>

  <button
    type="button"
    class="btn btn-outline-secondary btn-sm mt-3"
    id="addDocumentBtn">
    + Add another attachment
  </button>

  <div class="small text-muted mt-2">
    Maximum of 3 attachments allowed.
  </div>
</div>

            <!-- 2x2 -->
            <div class="row g-3 mb-4">
              <label class="form-label mb-1" for="pictureInput">2x2 Picture <span class="text-danger">*</span></label>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-semibold text-black">Required:</span>
                <span class="text-muted">White background (2x2 ID photo).</span>
              </div>
              <div class="upload-box position-relative col-12">
                <div class="upload-text">
                  <i class="fa-solid fa-upload"></i>
                  <span>Drag & drop file</span>
                </div>
                <div class="upload-subtext mt-1">JPG or PNG</div>
                <input
                  type="file"
                  class="form-control upload-input"
                  id="pictureInput"
                  name="picture"
                  accept="image/*,.heic,.heif">
              </div>
            </div>
          </div>

          <!-- Skip switch (above submit) -->
          <div class="alert alert-warning border-0 mb-4" style="background: rgba(254,153,60,.14);">
            <div class="d-flex align-items-start gap-3">
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="skipProofSwitch"
                  name="skipProofIdentity"
                  value="1">
              </div>

              <div>
                <label class="fw-semibold mb-1" for="skipProofSwitch" style="cursor:pointer;">
                  Submit proof of identity later
                </label>
                <p class="mb-0 small text-muted">
                  Some services, modules, and transactions will be unavailable until your profile is fully verified.
                </p>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-4 prev-btn">Previous</button>
            <button type="submit" class="btn btn-success px-4" id="submitBtn" disabled>Submit</button>
          </div>
        </div>

      </div>
    </form>
  </section>

  <!-- Logout Confirm Modal -->
        <div class="modal fade uniform-modal" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-black">Confirm Logout</h5>
        </div>
        <div class="modal-body">
          <p id="logoutConfirmMessage" class="mb-0">Are you sure you want to logout?</p>
        </div>
        <div class="modal-footer">
          <div class="row g-2 w-100 logout-btn-row">
            <div class="col-6 logout-btn-col">
              <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</button>
            </div>
            <div class="col-6 logout-btn-col">
              <a id="logoutConfirmBtn" class="btn btn-danger w-100" href="#">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Expose user_id (if your JS needs it)
    window.CURRENT_USER_ID = <?php echo json_encode($_SESSION['user_id']); ?>;

    document.addEventListener("DOMContentLoaded", () => {
      // Timestamp for logs
      const ts = document.getElementById("clientSubmittedAt");
      if (ts) ts.value = "";

      const form = document.getElementById("residentRegistrationForm");
      if (form && ts) {
        form.addEventListener("submit", () => {
          ts.value = new Date().toISOString();
        });
      }

      // Digits-only and max 10 for phone inputs
      document.querySelectorAll(".phone-input").forEach(inp => {
        inp.addEventListener("input", () => {
          inp.value = inp.value.replace(/\D/g, "").slice(0, 10);
        });
      });

      // Fetch account contact details (phone/email) from backend endpoint
      // Create this endpoint: ../PhpFiles/GET/getAccountContact.php
      fetch("../PhpFiles/GET/getAccountContact.php", {
        method: "GET",
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.success) return;

        // Expect: phone_number = "9XXXXXXXXX" OR "09XXXXXXXXX"
        let phone = String(data.phone_number ?? "");
        phone = phone.replace(/\D/g, "");
        if (phone.length === 11 && phone.startsWith("0")) phone = phone.slice(1); // -> 10 digits

        const email = String(data.email ?? "");

        // Fill visible disabled inputs
        const phoneVisible = document.getElementById("phoneNumber");
        const emailVisible = document.getElementById("emailAddress");

        // Fill hidden inputs to submit with form
        const phoneHidden = document.getElementById("phoneNumberHidden");
        const emailHidden = document.getElementById("emailAddressHidden");

        if (phoneVisible) phoneVisible.value = phone;
        if (phoneHidden) phoneHidden.value = phone;

        if (emailVisible) emailVisible.value = email;
        if (emailHidden) emailHidden.value = email;
      })
      .catch(() => {});
    });
const proofTypeSelect = document.getElementById("proofTypeSelect");
const idProofWrapper = document.getElementById("idProofWrapper");
const documentProofWrapper = document.getElementById("documentProofWrapper");
const documentTypeSelect = document.getElementById("documentTypeSelect");
const addDocumentBtn = document.getElementById("addDocumentBtn");
const documentUploadList = document.getElementById("documentUploadList");

proofTypeSelect.addEventListener("change", () => {
  idProofWrapper.classList.add("d-none");
  documentProofWrapper.classList.add("d-none");

  // Disable all inputs first
  idProofWrapper.querySelectorAll("input, select").forEach(el => el.disabled = true);
  documentProofWrapper.querySelectorAll("input, select").forEach(el => el.disabled = true);

  if (proofTypeSelect.value === "ID") {
    idProofWrapper.classList.remove("d-none");
    idProofWrapper.querySelectorAll("input, select").forEach(el => el.disabled = false);
    if (documentTypeSelect) {
      documentTypeSelect.value = "";
      documentTypeSelect.required = false;
    }
  }

  if (proofTypeSelect.value === "Document") {
    documentProofWrapper.classList.remove("d-none");
    documentProofWrapper.querySelectorAll("input").forEach(el => el.disabled = false);
    documentProofWrapper.querySelectorAll("select").forEach(el => el.disabled = false);
    if (documentTypeSelect) {
      documentTypeSelect.required = true;
    }
  }
});

// Add up to 3 document uploads


document.addEventListener("DOMContentLoaded", () => {

  /* =========================
     GENERIC UPLOAD BOX HANDLER
     ========================= */

  async function convertHeicIfNeeded(input) {
    if (!input || !input.files || input.files.length === 0) return;

    const files = Array.from(input.files);
    const converted = [];

    for (const file of files) {
      const ext = (file.name.split(".").pop() || "").toLowerCase();
      const isHeic = ext === "heic" || ext === "heif" || file.type === "image/heic" || file.type === "image/heif";
      if (!isHeic) {
        converted.push(file);
        continue;
      }

      if (typeof heic2any !== "function") {
        alert("HEIC conversion failed. Please upload JPG or PNG.");
        return;
      }

      try {
        const jpgBlob = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.9 });
        const safeName = file.name.replace(/\.(heic|heif)$/i, ".jpg");
        const jpgFile = new File([jpgBlob], safeName, { type: "image/jpeg" });
        converted.push(jpgFile);
      } catch (err) {
        console.error(err);
        alert("HEIC conversion failed. Please upload JPG or PNG.");
        return;
      }
    }

    const dt = new DataTransfer();
    converted.forEach((f) => dt.items.add(f));
    input.files = dt.files;
  }

  function initUploadBox(uploadBox) {
    const input = uploadBox.querySelector('input[type="file"]');
    if (!input) return;

    // Click anywhere to open file picker
    uploadBox.addEventListener("click", () => input.click());
    input.addEventListener("click", (e) => e.stopPropagation());

    // Drag over
    uploadBox.addEventListener("dragover", e => {
      e.preventDefault();
      uploadBox.classList.add("dragover");
    });

    // Drag leave
    uploadBox.addEventListener("dragleave", () => {
      uploadBox.classList.remove("dragover");
    });

    // Drop file
    uploadBox.addEventListener("drop", async e => {
      e.preventDefault();
      uploadBox.classList.remove("dragover");

      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        await convertHeicIfNeeded(input);
        if (input.files.length) {
          markUploaded(uploadBox, input);
        }
      }
    });

    // File selected
    input.addEventListener("change", async () => {
      if (input.files.length) {
        await convertHeicIfNeeded(input);
        if (input.files.length) {
          markUploaded(uploadBox, input);
        }
      }
    });
  }

  function markUploaded(box, input) {
    box.classList.add("uploaded");

    // Optional: show filename safely (no HTML replacement)
    let filename = box.querySelector(".uploaded-filename");
    if (!filename) {
      filename = document.createElement("div");
      filename.className = "uploaded-filename small mt-2 text-center";
      box.appendChild(filename);
    }
    filename.textContent = input.files[0].name;

    let removeBtn = box.querySelector(".upload-remove");
    if (!removeBtn) {
      removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "upload-remove";
      removeBtn.setAttribute("aria-label", "Remove file");
      removeBtn.innerHTML = "&times;";
      removeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        input.value = "";
        box.classList.remove("uploaded");
        if (filename && filename.parentNode) filename.parentNode.removeChild(filename);
        if (removeBtn && removeBtn.parentNode) removeBtn.parentNode.removeChild(removeBtn);
        updateNextButtonState();
        updateSubmitButtonState();
      });
      box.appendChild(removeBtn);
    }
  }

  /* =========================
     INITIALIZE EXISTING BOXES
     ========================= */

  document.querySelectorAll(".upload-box").forEach(initUploadBox);

  /* =========================
     DOCUMENT ATTACHMENTS (MAX 3)
     ========================= */

  const addDocumentBtn = document.getElementById("addDocumentBtn");
  const documentUploadList = document.getElementById("documentUploadList");

  if (addDocumentBtn && documentUploadList) {
    addDocumentBtn.addEventListener("click", () => {
      const count = documentUploadList.children.length;
      if (count >= 3) return;

      const col = document.createElement("div");
      col.className = "position-relative";

      col.innerHTML = `
        <div class="upload-box position-relative">
          <div class="upload-text">
            <i class="fa-solid fa-upload"></i>
            <span>Drag & drop file</span>
          </div>
          <div class="upload-subtext mt-1">
            PDF or image
          </div>

          <input
            type="file"
            class="form-control upload-input"
            name="documentProof[]"
            accept=".pdf,image/*,.heic,.heif"
            required>
        </div>

        <small class="text-muted d-block text-center mt-2">
          Attachment ${count + 1}
        </small>
      `;

      documentUploadList.appendChild(col);

      // IMPORTANT: initialize drag & drop for the new box
      initUploadBox(col.querySelector(".upload-box"));
    });
  }

});

</script>
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const links = document.querySelectorAll(".logout-link");
      if (!links.length) return;

      const modalEl = document.getElementById("logoutConfirmModal");
      const msgEl = document.getElementById("logoutConfirmMessage");
      const btnEl = document.getElementById("logoutConfirmBtn");
      if (!modalEl || !msgEl || !btnEl) return;

      const modal = new bootstrap.Modal(modalEl);
      links.forEach((link) => {
        link.addEventListener("click", (e) => {
          e.preventDefault();
          msgEl.textContent = link.dataset.logoutMessage || "Are you sure you want to logout?";
          btnEl.href = link.href;
          modal.show();
        });
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const dobInput = document.getElementById("dateOfBirth");
      const seniorCheckbox = document.getElementById("sectorSenior");

      const toggleSenior = () => {
        if (!dobInput || !seniorCheckbox) return;
        const value = dobInput.value;
        if (!value) {
          seniorCheckbox.checked = false;
          return;
        }
        const dob = new Date(value);
        if (isNaN(dob.getTime())) return;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
          age--;
        }
        seniorCheckbox.checked = age >= 60;
      };

      if (dobInput) {
        dobInput.addEventListener("change", toggleSenior);
        toggleSenior();
      }
    });
  </script>

  <script>
    // Force reload when navigating back so the guard runs and pushes users without a profile back to registration.
    history.replaceState(null, "", location.href);
    window.addEventListener("pageshow", (event) => {
      if (event.persisted || window.performance?.getEntriesByType("navigation")[0]?.type === "back_forward") {
        window.location.reload();
      }
    });
  </script>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"
  ></script>

  <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

</body>
</html>
