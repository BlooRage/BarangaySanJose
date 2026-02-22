<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$userId = $_SESSION['user_id'] ?? '';
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$unitNumber = htmlspecialchars($residentaddresstbl['unit_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetNumber = htmlspecialchars($residentaddresstbl['street_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetName = htmlspecialchars($residentaddresstbl['street_name'] ?? '', ENT_QUOTES, 'UTF-8');
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Good Moral Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
</head>
<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
                <div class="main-head-content">
                    <a href="/BarangaySanJose/Resident-End/Certificates/CertificatesLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Good Moral</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <h2 class="section-title text-center text-dark">Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name_display" class="text-bg-light" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" readonly value="<?php echo $phoneNumber; ?>">
                            </div>
                        </div>

                        <div id="houseSystemWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo $unitNumber; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="houseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo $streetNumber; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="streetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo $streetName; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <textarea name="purpose" rows="5" required></textarea>
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Document Upload</h2>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label" for="applicantType">Applicant Type <span class="required-asterisk">*</span></label>
                                <select id="applicantType" name="applicant_type" required>
                                    <option value="non_minor" selected>Non-minor</option>
                                    <option value="minor">Minor / Student</option>
                                </select>
                            </div>
                            <div>
                                <label class="top-label" for="documentType">Document Type <span class="required-asterisk">*</span></label>
                                <select id="documentType" name="document_type" required></select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="supportingFiles">Upload File(s) <span class="required-asterisk">*</span></label>
                                <label class="upload-dropzone" id="uploadDropzone" for="supportingFiles">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Drag files here or click to upload</span>
                                    <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                </label>
                                <input type="file" id="supportingFiles" name="supporting_files[]" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                                <div id="selectedFiles" class="selected-files small text-muted mt-2"></div>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementGoodMoral">
                                <input type="checkbox" id="agreementGoodMoral" required>
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>
                            <button type="submit" class="submit-btn">SUBMIT</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
  (() => {
    const applicantType = document.getElementById("applicantType");
    const documentType = document.getElementById("documentType");
    const fileInput = document.getElementById("supportingFiles");
    const fileList = document.getElementById("selectedFiles");
    const dropzone = document.getElementById("uploadDropzone");

    if (!applicantType || !documentType || !fileInput || !fileList || !dropzone) return;

    const documentOptions = {
      non_minor: [
        "Valid government ID",
        "Proof of residency",
        "Cedula / Community Tax Certificate"
      ],
      minor: [
        "Parent or guardian valid ID",
        "Authorization letter from parent/guardian",
        "School ID or Birth Certificate"
      ]
    };

    const renderDocOptions = () => {
      const key = applicantType.value === "minor" ? "minor" : "non_minor";
      documentType.innerHTML = "";
      documentOptions[key].forEach((label) => {
        const option = document.createElement("option");
        option.value = label;
        option.textContent = label;
        documentType.appendChild(option);
      });
    };

    const renderFiles = () => {
      const names = Array.from(fileInput.files || []).map((f) => f.name);
      fileList.textContent = names.length ? `Selected: ${names.join(", ")}` : "No file selected";
    };

    applicantType.addEventListener("change", renderDocOptions);
    fileInput.addEventListener("change", renderFiles);

    ["dragenter", "dragover"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.add("is-dragging");
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.remove("is-dragging");
      });
    });

    dropzone.addEventListener("drop", (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        fileInput.files = dt.files;
        renderFiles();
      }
    });

    renderDocOptions();
    renderFiles();
  })();
    </script>
</body>
</html>
