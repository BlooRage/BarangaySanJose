<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    
  <link rel="icon" href="/Images/favicon_sanjose.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Resident</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AddResidentStyle.css">
</head>

<body>
    <div class="d-flex" style="min-height: 100vh;">

        <!-- SIDEBAR -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">

            <h1 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">Add Resident</h1>
            <hr>

            <!-- KEEP ALL YOUR FORM CONTENT BELOW THIS -->
            <div class="container-fluid p-4 my-2">
                <div class="border rounded-2 p-4 shadow-sm" id="outer-div">
                    <h1 class="mt-3 fw-semibold text-center">Resident Form</h1>
                    <p class="mt-2 text-center">
                        Fill out the resident information form to add a new resident to the database.
                    </p>

                    <div id="form-div">
                                    <div class="row g-3 mt-1">
                                        <h3 id="form-personalInformation" class="step">Qualifications for Head of the Family:</h3>
                                        <hr class="section-hr">
                                        <br>
                                        <label class="form-label" for="isHead">Is the resident the Head of the Family? <span class="text-danger">*</span></label>
                                        <select id="isHead" name="isHead" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="yes">Yes</option>
                                            <option value="no">No</option>
                                        </select>
                                    </div>
                                    <br><br>
                                    <div id="form-personalInformation" class="step">
                                        <h3 class="section-title mb-0 pt-3">Personal Information</h3>
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
                                                    placeholder="Specify suffix" />
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
                                                <select class="form-select" name="religion" id="religionSelect" required>
                                                    <option value="">Select</option>
                                                    <option value="Roman Catholic">Roman Catholic</option>
                                                    <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                                                    <option value="Islam">Islam</option>
                                                    <option value="Other">Other</option>
                                                </select>

                                                <input
                                                    type="text"
                                                    class="form-control mt-2 d-none"
                                                    name="religionOther"
                                                    id="religionOther"
                                                    placeholder="Please specify religion" />
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
                                        <!-- Contact -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <label class="form-label" for="phoneNumber">Phone Number <span class="text-danger">*</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control phone-input"
                                                    id="phoneNumber"
                                                    name="phoneNumber"
                                                    required
                                                    inputmode="numeric"
                                                    autocomplete="tel"
                                                    placeholder="09XXXXXXXXX">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label" for="emailAddress">Email Address <span class="text-danger">*</span></label>
                                                <input
                                                    type="email"
                                                    class="form-control email-input"
                                                    id="emailAddress"
                                                    name="emailAddress"
                                                    required
                                                    autocomplete="email"
                                                    placeholder="name@gmail.com">
                                            </div>
                                        </div>

                                        <!-- Registered Voter & Occupation -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    Is the resident a Registered Voter? <span class="text-danger">*</span>
                                                </label>

                                                <div class="btn-group w-100" role="group" aria-label="Registered Voter">
                                                    <input type="radio" class="btn-check" name="registeredVoter" id="voterYes" value="yes" required>
                                                    <label class="btn btn-outline-dark" for="voterYes">Yes</label>

                                                    <input type="radio" class="btn-check" name="registeredVoter" id="voterNo" value="no" required>
                                                    <label class="btn btn-outline-dark" for="voterNo">No</label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    Employment Status <span class="text-danger">*</span>
                                                </label>

                                                <div class="btn-group w-100" role="group" aria-label="Employment Status">
                                                    <input type="radio" class="btn-check" name="occupationStatus" id="employed" value="employed" required>
                                                    <label class="btn btn-outline-dark" for="employed">Employed</label>

                                                    <input type="radio" class="btn-check" name="occupationStatus" id="unemployed" value="unemployed" required>
                                                    <label class="btn btn-outline-dark" for="unemployed">Unemployed</label>
                                                </div>

                                                <div id="occupationWrapper" class="mt-3 d-none">
                                                    <label class="form-label" for="occupationInput">Occupation / Job Title <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="occupation" id="occupationInput">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sector -->
                                        <h4 class="section-title mt-4 mb-0 pb-3">Sector Membership</h4>

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
                                        <h3 class="section-title mt-4 mb-0 pt-3">Emergency Contact</h3>
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
                                                    data-target="emergency-suffix-other">
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
                                                    placeholder="Specify suffix" />
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label" for="emergencyPhoneNumber">Contact Number <span class="text-danger">*</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control phone-input"
                                                    id="emergencyPhoneNumber"
                                                    name="emergencyPhoneNumber"
                                                    required
                                                    inputmode="numeric"
                                                    placeholder="09XXXXXXXXX">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label" for="emergencyRelationship">Relationship <span class="text-danger">*</span></label>
                                                <select class="form-select" id="emergencyRelationship" name="emergencyRelationship" required>
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
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label" for="emergencyAddress">Address <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="emergencyAddress" name="emergencyAddress" required>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                    <div id="form-addressInformation" class="step pt-4">
                                        <h3 class="section-title mb-0 pt-3">Address Information</h3>
                                        <hr class="section-hr">

                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <label class="form-label" for="houseNumber">House Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="houseNumber" name="houseNumber" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label" for="streetName">Street Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="streetName" name="streetName" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label" for="subdivisionSitio">Subdivision / Sitio <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="subdivisionSitio" name="subdivisionSitio" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label" for="areaNumber">Area <span class="text-danger">*</span></label>
                                                <select class="form-select" id="areaNumber" name="areaNumber" required>
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

                                        <!-- These are readonly but still POST to backend using hidden inputs -->
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label class="form-label" for="barangayDisplay">Barangay</label>
                                                <input type="text" class="form-control" id="barangayDisplay" value="Barangay San Jose" readonly>
                                                <input type="hidden" name="barangay" value="Barangay San Jose">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label" for="municipalityDisplay">Municipality / City</label>
                                                <input type="text" class="form-control" id="municipalityDisplay" value="Rodriguez" readonly>
                                                <input type="hidden" name="municipality" value="Rodriguez">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label" for="provinceDisplay">Province</label>
                                                <input type="text" class="form-control" id="provinceDisplay" value="Rizal" readonly>
                                                <input type="hidden" name="province" value="Rizal">
                                            </div>
                                        </div>
                                        <h3 class="section-title mt-4 mb-0 pt-3">House Information</h3>
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
                                                        id="houseTypeSelect">
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
                                                        placeholder="Specify house type">
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
                                        <div class="mt-4 justify-content-end d-flex gap-2 pt-5">
                                            <button type="submit" class="btn btn-primary">Submit</button>
                                            <button type="reset" class="btn btn-secondary">Reset</button>
                                        </div>
                                    </div>
                                </div>

                </div>
            </div>

        </main>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS-Scripts/Admin-End-JS/AdminDashboardScript.js"></script>

    <script>
        document.addEventListener("change", (e) => {
            if (e.target.name !== "occupationStatus") return;

            const wrapper = document.getElementById("occupationWrapper");
            const input = document.getElementById("occupationInput");

            if (e.target.value === "employed") {
                wrapper.classList.remove("d-none");
                input.required = true;
            } else {
                wrapper.classList.add("d-none");
                input.required = false;
                input.value = "";
            }
        });
    </script>
</body>
</html>
