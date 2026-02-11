<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/DocumentUpload.css">

</head>

<body>

<div class="page-wrapper">

    <!-- Sidebar -->
    <?php include 'includes/resident_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">

        <div class="container-fluid">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    <h2 class="section-title mb-0">Proof of Identification and Residency</h2>
                    <hr class="section-hr">

                    <form method="POST" action="submit_upload.php" enctype="multipart/form-data">

                        <!-- ================= PROOF TYPE ================= -->
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

                        <!-- ================= ID PROOF ================= -->
                        <div id="idProofWrapper" class="d-none">

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

                            <!-- Student ID -->
                            <div class="row g-3 mb-3 d-none" id="schoolNameWrapper">
                                <div class="col-12">
                                    <label class="form-label" for="schoolNameInput">School Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="schoolName" id="schoolNameInput">
                                </div>
                            </div>

                            <!-- Upload Front -->
                            <label class="form-label mt-3" for="idFrontInput">
                                Upload ID Front <span class="text-danger">*</span>
                            </label>
                            <div class="upload-box position-relative mb-3">
                                <div>
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Drag & drop file</span>
                                    <div class="upload-subtext mt-1">PDF or image</div>
                                </div>
                                <input type="file"
                                    class="form-control upload-input"
                                    id="idFrontInput"
                                    name="idFront"
                                    accept="image/*,.pdf,.heic,.heif">
                            </div>

                            <!-- Upload Back -->
                            <label class="form-label mt-3" for="idBackInput">
                                Upload ID Back <span class="text-danger">*</span>
                            </label>
                            <div class="upload-box position-relative">
                                <div>
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Drag & drop file</span>
                                    <div class="upload-subtext mt-1">PDF or image</div>
                                </div>
                                <input type="file"
                                    class="form-control upload-input"
                                    id="idBackInput"
                                    name="idBack"
                                    accept="image/*,.pdf,.heic,.heif">
                            </div>

                        </div>

                        <!-- ================= DOCUMENT PROOF ================= -->
                        <div id="documentProofWrapper" class="d-none">

                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label" for="documentTypeSelect">
                                        Document Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="documentTypeSelect" name="documentType">
                                        <option value="">Select</option>
                                        <option value="Billing Statement">Billing Statement</option>
                                        <option value="HOA Signed Certification of Residency">
                                            HOA Signed Certification of Residency
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <label class="form-label fw-semibold mb-3">
                                Upload Supporting Document(s) <span class="text-danger">*</span>
                            </label>

                            <div id="documentUploadList">

                                <div class="position-relative mb-3">
                                    <div class="upload-box position-relative">
                                        <div>
                                            <i class="fa-solid fa-upload"></i>
                                            <span>Drag & drop file</span>
                                            <div class="upload-subtext mt-1">PDF or image</div>
                                        </div>

                                        <input type="file"
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

                            <button type="button"
                                class="btn btn-outline-secondary btn-sm mt-3"
                                id="addDocumentBtn">
                                + Add another attachment
                            </button>

                            <div class="small text-muted mt-2">
                                Maximum of 3 attachments allowed.
                            </div>

                        </div>

                        <!-- ================= 2x2 ================= -->
                        <div class="row g-3 mt-4">
                            <label class="form-label" for="pictureInput">
                                2x2 Picture <span class="text-danger">*</span>
                            </label>

                            <div class="upload-box position-relative">
                                <div>
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Drag & drop file</span>
                                    <div class="upload-subtext mt-1">JPG or PNG</div>
                                </div>

                                <input type="file"
                                    class="form-control upload-input"
                                    id="pictureInput"
                                    name="picture"
                                    accept="image/*,.heic,.heif">
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success px-4">
                                Submit Documents
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>

    </div>
</div>

<!-- ================= SCRIPT ================= -->
<script>
const proofTypeSelect = document.getElementById("proofTypeSelect");
const idWrapper = document.getElementById("idProofWrapper");
const docWrapper = document.getElementById("documentProofWrapper");
const addBtn = document.getElementById("addDocumentBtn");
const uploadList = document.getElementById("documentUploadList");
const idTypeSelect = document.getElementById("idTypeSelect");
const schoolWrapper = document.getElementById("schoolNameWrapper");

let attachmentCount = 1;

proofTypeSelect.addEventListener("change", function () {
    idWrapper.classList.add("d-none");
    docWrapper.classList.add("d-none");

    if (this.value === "ID") {
        idWrapper.classList.remove("d-none");
    }

    if (this.value === "Document") {
        docWrapper.classList.remove("d-none");
    }
});

idTypeSelect.addEventListener("change", function () {
    schoolWrapper.classList.toggle("d-none", this.value !== "Student ID");
});

addBtn.addEventListener("click", function () {
    if (attachmentCount >= 3) return;

    attachmentCount++;

    const div = document.createElement("div");
    div.classList.add("position-relative", "mb-3");

    div.innerHTML = `
        <div class="upload-box position-relative">
            <div>
                <i class="fa-solid fa-upload"></i>
                <span>Drag & drop file</span>
                <div class="upload-subtext mt-1">PDF or image</div>
            </div>
            <input type="file"
                class="form-control upload-input"
                name="documentProof[]"
                accept=".pdf,image/*,.heic,.heif">
        </div>
        <small class="text-muted d-block text-center mt-2">
            Attachment ${attachmentCount}
        </small>
    `;

    uploadList.appendChild(div);
});
</script>

</body>
</html>
