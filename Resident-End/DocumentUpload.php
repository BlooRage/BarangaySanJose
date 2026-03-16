<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";

$existingSectorMembership = [];
$existingSectorKeys = [];
$sectorStatusByKey = [];
$sectorKeysNeedingProof = [];
$sectorKeyToLabel = [
    'PWD' => 'PWD',
    'SingleParent' => 'Single Parent',
    'Student' => 'Student',
    'SeniorCitizen' => 'Senior Citizen',
    'IndigenousPeople' => 'Indigenous People',
];
$sectorLabelToKey = [
    'PWD' => 'PWD',
    'Single Parent' => 'SingleParent',
    'Student' => 'Student',
    'Senior Citizen' => 'SeniorCitizen',
    'Indigenous People' => 'IndigenousPeople',
];
if (isset($conn) && $conn instanceof mysqli) {
    $residentId = '';
    $stmt = $conn->prepare("SELECT resident_id, sector_membership FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($residentId, $sectorRaw);
        if ($stmt->fetch() && !empty($sectorRaw)) {
            $existingSectorMembership = array_values(array_filter(array_map('trim', explode(',', (string)$sectorRaw))));
        }
        $stmt->close();
    }

    foreach ($existingSectorMembership as $label) {
        foreach ($sectorLabelToKey as $knownLabel => $key) {
            if (strcasecmp($label, $knownLabel) === 0) {
                $existingSectorKeys[] = $key;
                break;
            }
        }
    }

    if ($residentId !== '') {
        $stmtSector = $conn->prepare("
            SELECT DISTINCT rsm.sector_key, COALESCE(s.status_name, '') AS status_name
            FROM residentsectormembershiptbl rsm
            LEFT JOIN statuslookuptbl s ON s.status_id = rsm.sector_status_id
            WHERE rsm.resident_id = ?
        ");
        if ($stmtSector) {
            $stmtSector->bind_param("s", $residentId);
            $stmtSector->execute();
            $resSector = $stmtSector->get_result();
            while ($row = $resSector ? $resSector->fetch_assoc() : null) {
                $rawKey = trim((string)($row['sector_key'] ?? ''));
                if ($rawKey === '') continue;
                foreach (array_keys($sectorKeyToLabel) as $knownKey) {
                    if (strcasecmp($rawKey, $knownKey) === 0) {
                        $existingSectorKeys[] = $knownKey;
                        $sectorStatusByKey[$knownKey] = (string)($row['status_name'] ?? '');
                        break;
                    }
                }
            }
            $stmtSector->close();
        }
    }
}

$existingSectorKeys = array_values(array_unique($existingSectorKeys));
foreach ($existingSectorKeys as $key) {
    $statusName = strtolower(trim((string)($sectorStatusByKey[$key] ?? '')));
    $statusKey = preg_replace('/[\s_-]+/', '', $statusName);
    $isPendingOrVerified = (
        $statusKey === 'verified' ||
        $statusKey === 'approved' ||
        strpos($statusKey, 'pending') !== false ||
        strpos($statusKey, 'review') !== false
    );
    if (!$isPendingOrVerified) {
        $sectorKeysNeedingProof[] = $key;
    }
}
$sectorKeysNeedingProof = array_values(array_unique($sectorKeysNeedingProof));
$resubmitMode = strtolower(trim((string)($_GET['mode'] ?? '')));
if (!in_array($resubmitMode, ['sector', 'profiling'], true)) {
    $resubmitMode = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Upload</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/DocumentUpload.css">
    <script src="../JS-Script-Files/modalHandler.js"></script>
</head>
<body>
<div class="page-wrapper d-flex" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <div id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="section-title mb-0">Proof of Identification and Residency</h2>
                    <hr class="section-hr">
                    <div id="uploadRequirementsNotice" class="alert alert-info d-none small" role="alert"></div>

                    <form id="documentUploadForm" method="POST" action="../PhpFiles/Resident-End/residentDocumentUpload.php" enctype="multipart/form-data">
                        <input type="hidden" name="resubmit_mode" value="<?= htmlspecialchars($resubmitMode, ENT_QUOTES, 'UTF-8') ?>">
                        <div id="proofRequirementSection">
                        <div class="row g-3 mb-4" id="proofTypeWrapper">
                            <div>
                                <label class="form-label fw-semibold">Type of Proof of Identification <span class="text-danger">*</span></label>
                                <select class="form-select" id="proofTypeSelect" name="proofType" required>
                                    <option value="">Select</option>
                                    <option value="ID">ID</option>
                                    <option value="Document">Document</option>
                                </select>
                                <div class="small text-danger mt-1">The document or ID you present must prove you reside in the barangay.</div>
                            </div>
                        </div>

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
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="idNumberInput">ID Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="idNumber" id="idNumberInput">
                                </div>
                            </div>

                            <label class="form-label fw-semibold mb-2" id="idUploadLabel">Upload ID Front and Back <span class="text-danger">*</span></label>
                            <div class="small text-muted mb-2" id="idUploadHint">Upload clear photos/scans of the front and back of your ID.</div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="upload-box position-relative">
                                        <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
                                        <div class="upload-subtext mt-1">PDF or image</div>
                                        <input type="file" class="form-control upload-input" id="idFrontInput" name="idFront" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                    </div>
                                    <small class="text-muted d-block text-center mt-2" id="idFrontCaption">Front</small>
                                </div>
                                <div class="col-md-6" id="idBackWrapper">
                                    <div class="upload-box position-relative">
                                        <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
                                        <div class="upload-subtext mt-1">PDF or image</div>
                                        <input type="file" class="form-control upload-input" id="idBackInput" name="idBack" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                    </div>
                                    <small class="text-muted d-block text-center mt-2" id="idBackCaption">Back</small>
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

                            <label class="form-label fw-semibold mb-3">Upload Supporting Document(s) <span class="text-danger">*</span></label>
                            <div id="documentUploadList" class="row g-2">
                                <div class="position-relative">
                                    <div class="upload-box position-relative">
                                        <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
                                        <div class="upload-subtext mt-1">PDF or image</div>
                                        <input type="file" class="form-control upload-input" name="documentProof[]" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    </div>
                                    <small class="text-muted d-block text-center mt-2">Attachment 1</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="addDocumentBtn">+ Add another attachment</button>
                            <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                        </div>
                        </div>

                        <div class="mb-4 mt-3" id="pictureRequirementSection">
                            <h3 class="section-title mb-0">2x2 Profile Picture:</h3>
                            <hr class="section-hr">
                            <p class="mb-2"><span class="fw-semibold text-black">Required<span class="text-danger">*</span>:</span> <span class="text-muted">White background (2x2 ID photo).</span></p>
                            <div class="upload-box position-relative">
                                <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
                                <div class="upload-subtext mt-1">JPG or PNG</div>
                                <input type="file" class="form-control upload-input" id="pictureInput" name="picture" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <?php if (!empty($existingSectorKeys)): ?>
                        <div class="mb-3 small text-muted">
                            Sector memberships on record:
                            <?= htmlspecialchars(implode(', ', array_values(array_map(static function ($key) use ($sectorKeyToLabel) { return $sectorKeyToLabel[$key] ?? $key; }, $existingSectorKeys))), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>

                        <div id="sectorProofSection" class="d-none">
                            <h3 class="section-title mt-2 mb-0">Sector Membership Supporting Documents</h3>
                            <hr class="section-hr">

                            <div id="sectorProofPWD" class="sector-proof-card d-none mb-3">
                                <label class="form-label fw-semibold" for="sectorDocTypePWD">PWD Proof of Disability <span class="text-danger">*</span></label>
                                <select class="form-select mb-2 sector-doc-type" id="sectorDocTypePWD" name="sectorDocType[PWD]" data-sector="PWD">
                                    <option value="">Select</option>
                                    <option value="PWD ID">PWD ID</option>
                                    <option value="Certificate of Disability">Certificate of Disability</option>
                                    <option value="Medical Certificate">Medical Certificate</option>
                                </select>
                                <div class="sector-upload-zone d-none" data-sector="PWD">
                                    <div class="small text-muted mb-2 sector-idpair-hint d-none" data-sector="PWD">If you selected an ID as proof, upload clear photos of the front and back.</div>
                                    <div class="sector-upload-idpair d-none" data-sector="PWD">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idfront" name="sectorDocFile[PWD][]" data-sector="PWD" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Front</small></div>
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idback" name="sectorDocFile[PWD][]" data-sector="PWD" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Back</small></div>
                                        </div>
                                    </div>
                                    <div class="sector-upload-list" data-sector="PWD"><div class="position-relative"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">PDF or image</div><input type="file" class="form-control upload-input sector-doc-file" id="sectorDocFilePWD" name="sectorDocFile[PWD][]" data-sector="PWD" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Attachment 1</small></div></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3 add-sector-doc-btn" data-sector="PWD">+ Add another attachment</button>
                                    <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                                </div>
                            </div>

                            <div id="sectorProofSenior" class="sector-proof-card d-none mb-3">
                                <label class="form-label fw-semibold" for="sectorDocTypeSenior">Senior Citizen Proof</label>
                                <select class="form-select mb-2 sector-doc-type" id="sectorDocTypeSenior" name="sectorDocType[SeniorCitizen]" data-sector="SeniorCitizen">
                                    <option value="">Select</option>
                                    <option value="Birth Certificate">Birth Certificate</option>
                                    <option value="Senior Citizen ID">Senior Citizen ID</option>
                                </select>
                                <div class="sector-upload-zone d-none" data-sector="SeniorCitizen">
                                    <div class="small text-muted mb-2 sector-idpair-hint d-none" data-sector="SeniorCitizen">If you selected an ID as proof, upload clear photos of the front and back.</div>
                                    <div class="sector-upload-idpair d-none" data-sector="SeniorCitizen">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idfront" name="sectorDocFile[SeniorCitizen][]" data-sector="SeniorCitizen" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Front</small></div>
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idback" name="sectorDocFile[SeniorCitizen][]" data-sector="SeniorCitizen" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Back</small></div>
                                        </div>
                                    </div>
                                    <div class="sector-upload-list" data-sector="SeniorCitizen"><div class="position-relative"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">PDF or image</div><input type="file" class="form-control upload-input sector-doc-file" id="sectorDocFileSenior" name="sectorDocFile[SeniorCitizen][]" data-sector="SeniorCitizen" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Attachment 1</small></div></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3 add-sector-doc-btn" data-sector="SeniorCitizen">+ Add another attachment</button>
                                    <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                                </div>
                                <div class="small text-muted mt-2">If you already used an ID as proof of identity above, senior-citizen proof is not required.</div>
                            </div>

                            <div id="sectorProofStudent" class="sector-proof-card d-none mb-3">
                                <label class="form-label fw-semibold" for="sectorDocTypeStudent">Student Proof <span class="text-danger">*</span></label>
                                <select class="form-select mb-2 sector-doc-type" id="sectorDocTypeStudent" name="sectorDocType[Student]" data-sector="Student">
                                    <option value="">Select</option>
                                    <option value="Registration Form">Registration Form</option>
                                    <option value="Student ID">Student ID</option>
                                    <option value="Report Card">Report Card</option>
                                </select>
                                <div class="sector-upload-zone d-none" data-sector="Student">
                                    <div class="small text-muted mb-2 sector-idpair-hint d-none" data-sector="Student">If you selected an ID as proof, upload clear photos of the front and back.</div>
                                    <div class="sector-upload-idpair d-none" data-sector="Student">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idfront" name="sectorDocFile[Student][]" data-sector="Student" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Front</small></div>
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idback" name="sectorDocFile[Student][]" data-sector="Student" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Back</small></div>
                                        </div>
                                    </div>
                                    <div class="sector-upload-list" data-sector="Student"><div class="position-relative"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">PDF or image</div><input type="file" class="form-control upload-input sector-doc-file" id="sectorDocFileStudent" name="sectorDocFile[Student][]" data-sector="Student" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Attachment 1</small></div></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3 add-sector-doc-btn" data-sector="Student">+ Add another attachment</button>
                                    <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                                </div>
                            </div>

                            <div id="sectorProofIP" class="sector-proof-card d-none mb-3">
                                <label class="form-label fw-semibold" for="sectorDocTypeIP">Indigenous People Proof <span class="text-danger">*</span></label>
                                <select class="form-select mb-2 sector-doc-type" id="sectorDocTypeIP" name="sectorDocType[IndigenousPeople]" data-sector="IndigenousPeople">
                                    <option value="">Select</option>
                                    <option value="Certificate of IP Membership (CIPM)">Certificate of IP Membership (CIPM)</option>
                                    <option value="Testimony of Elders/Community Members">Testimony of Elders/Community Members</option>
                                    <option value="Birth Certificate">Birth Certificate</option>
                                    <option value="PhilSys ID/ePhilID">PhilSys ID/ePhilID</option>
                                </select>
                                <div class="sector-upload-zone d-none" data-sector="IndigenousPeople">
                                    <div class="small text-muted mb-2 sector-idpair-hint d-none" data-sector="IndigenousPeople">If you selected an ID as proof, upload clear photos of the front and back.</div>
                                    <div class="sector-upload-idpair d-none" data-sector="IndigenousPeople">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idfront" name="sectorDocFile[IndigenousPeople][]" data-sector="IndigenousPeople" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Front</small></div>
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idback" name="sectorDocFile[IndigenousPeople][]" data-sector="IndigenousPeople" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Back</small></div>
                                        </div>
                                    </div>
                                    <div class="sector-upload-list" data-sector="IndigenousPeople"><div class="position-relative"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">PDF or image</div><input type="file" class="form-control upload-input sector-doc-file" id="sectorDocFileIP" name="sectorDocFile[IndigenousPeople][]" data-sector="IndigenousPeople" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Attachment 1</small></div></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3 add-sector-doc-btn" data-sector="IndigenousPeople">+ Add another attachment</button>
                                    <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                                </div>
                                <div class="small text-muted mt-2">If you used National ID/PhilSys as proof of identity above, this upload is not required.</div>
                            </div>

                            <div id="sectorProofSoloParent" class="sector-proof-card d-none mb-3">
                                <label class="form-label fw-semibold" for="sectorDocTypeSoloParent">Solo Parent Proof (Optional)</label>
                                <select class="form-select mb-2 sector-doc-type" id="sectorDocTypeSoloParent" name="sectorDocType[SingleParent]" data-sector="SingleParent">
                                    <option value="">Select</option>
                                    <option value="Birth Certificate/s">Birth Certificate/s</option>
                                    <option value="Barangay Certificate of Solo Parent">Barangay Certificate of Solo Parent</option>
                                    <option value="CENOMAR">CENOMAR</option>
                                    <option value="Abandoned/De Facto">Abandoned/De Facto</option>
                                    <option value="Spouse Death Certificate">Spouse Death Certificate</option>
                                </select>
                                <div class="sector-upload-zone d-none" data-sector="SingleParent">
                                    <div class="small text-muted mb-2 sector-idpair-hint d-none" data-sector="SingleParent">If you selected an ID as proof, upload clear photos of the front and back.</div>
                                    <div class="sector-upload-idpair d-none" data-sector="SingleParent">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idfront" name="sectorDocFile[SingleParent][]" data-sector="SingleParent" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Front</small></div>
                                            <div class="col-md-6"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">Image only</div><input type="file" class="form-control upload-input sector-doc-idback" name="sectorDocFile[SingleParent][]" data-sector="SingleParent" accept=".jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Back</small></div>
                                        </div>
                                    </div>
                                    <div class="sector-upload-list" data-sector="SingleParent"><div class="position-relative"><div class="upload-box position-relative"><div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div><div class="upload-subtext mt-1">PDF or image</div><input type="file" class="form-control upload-input sector-doc-file" id="sectorDocFileSoloParent" name="sectorDocFile[SingleParent][]" data-sector="SingleParent" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted d-block text-center mt-2">Attachment 1</small></div></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3 add-sector-doc-btn" data-sector="SingleParent">+ Add another attachment</button>
                                    <div class="small text-muted mt-2">Maximum of 3 attachments allowed.</div>
                                </div>
                                <div class="small text-muted mt-2">You may skip this for now and submit proper documents later.</div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success px-4" id="btnSubmitDocuments">Submit Documents</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("documentUploadForm");
  const requirementsNotice = document.getElementById("uploadRequirementsNotice");
  const proofRequirementSection = document.getElementById("proofRequirementSection");
  const pictureRequirementSection = document.getElementById("pictureRequirementSection");
  const proofTypeSelect = document.getElementById("proofTypeSelect");
  const idProofWrapper = document.getElementById("idProofWrapper");
  const documentProofWrapper = document.getElementById("documentProofWrapper");
  const idTypeSelect = document.getElementById("idTypeSelect");
  const idNumberInput = document.getElementById("idNumberInput");
  const idFrontInput = document.getElementById("idFrontInput");
  const idBackInput = document.getElementById("idBackInput");
  const idBackWrapper = document.getElementById("idBackWrapper");
  const documentTypeSelect = document.getElementById("documentTypeSelect");
  const documentUploadList = document.getElementById("documentUploadList");
  const addDocumentBtn = document.getElementById("addDocumentBtn");
  const pictureInput = document.getElementById("pictureInput");
  const submitBtn = document.getElementById("btnSubmitDocuments");
  const sectorProofSection = document.getElementById("sectorProofSection");
  let needsProof = true;
  let needsPicture = true;
  const resubmitMode = <?= json_encode($resubmitMode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const forceSectorOnly = resubmitMode === "sector";
  const forceProfilingOnly = resubmitMode === "profiling";
  const availableSectorKeys = <?= json_encode(array_values($sectorKeysNeedingProof), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const sectorMap = {
    PWD: { cardId: "sectorProofPWD", required: false },
    SeniorCitizen: { cardId: "sectorProofSenior", required: false },
    Student: { cardId: "sectorProofStudent", required: false },
    IndigenousPeople: { cardId: "sectorProofIP", required: false },
    SingleParent: { cardId: "sectorProofSoloParent", required: false }
  };

  function modalError(message) {
    if (window.UniversalModal?.open) {
      window.UniversalModal.open({ title: "Error", message, buttons: [{ label: "Close", class: "btn btn-outline-secondary" }] });
      return;
    }
    alert(message);
  }

  function modalSuccess(message, onClose) {
    if (window.UniversalModal?.open) {
      window.UniversalModal.open({ title: "Success", message, buttons: [{ label: "OK", class: "btn btn-success", onClick: onClose }] });
      return;
    }
    alert(message);
    if (onClose) onClose();
  }

  function setNotice(message, tone = "info") {
    if (!requirementsNotice) return;
    requirementsNotice.className = `alert alert-${tone} small`;
    requirementsNotice.textContent = message;
    requirementsNotice.classList.remove("d-none");
  }

  function disableInputsInSection(sectionEl, disabled) {
    if (!sectionEl) return;
    sectionEl.querySelectorAll("input, select, textarea, button").forEach((el) => {
      if (el === submitBtn) return;
      el.disabled = disabled;
    });
  }

  function isPassportSelected() {
    return (idTypeSelect?.value || "") === "Passport";
  }

  function normalizeIdType(value) {
    return String(value || "").toLowerCase().replace(/[^a-z0-9]/g, "");
  }

  function isNationalIdSelected() {
    const n = normalizeIdType(idTypeSelect?.value || "");
    return ["nationalid", "philsysid", "ephilid", "philsysidephilid"].includes(n);
  }

  function isIdLikeSectorDocType(value) {
    return /\bid\b/i.test(String(value || ""));
  }

  function getSelectedSectorKeys() {
    return availableSectorKeys.filter((key) => !!sectorMap[key]);
  }

  function isSectorUploadProhibited(sectorKey) {
    if (sectorKey === "SeniorCitizen" && proofTypeSelect.value === "ID") return true;
    if (sectorKey === "IndigenousPeople" && proofTypeSelect.value === "ID" && isNationalIdSelected()) return true;
    return false;
  }

  function clearFiles(inputs) {
    inputs.forEach((input) => {
      if (!input) return;
      input.value = "";
      const box = input.closest(".upload-box");
      if (box) {
        box.classList.remove("uploaded");
        box.querySelectorAll(".uploaded-filename,.upload-remove").forEach((el) => el.remove());
      }
    });
  }

  function getSectorElements(sectorKey) {
    const card = document.getElementById(sectorMap[sectorKey].cardId);
    const docType = card ? card.querySelector(".sector-doc-type") : null;
    const zone = card ? card.querySelector(`.sector-upload-zone[data-sector="${sectorKey}"]`) : null;
    const idPair = card ? card.querySelector(`.sector-upload-idpair[data-sector="${sectorKey}"]`) : null;
    const idHint = card ? card.querySelector(`.sector-idpair-hint[data-sector="${sectorKey}"]`) : null;
    const list = card ? card.querySelector(`.sector-upload-list[data-sector="${sectorKey}"]`) : null;
    const addBtn = card ? card.querySelector(`.add-sector-doc-btn[data-sector="${sectorKey}"]`) : null;
    const idFront = card ? card.querySelector(".sector-doc-idfront") : null;
    const idBack = card ? card.querySelector(".sector-doc-idback") : null;
    const fileInputs = list ? Array.from(list.querySelectorAll(".sector-doc-file")) : [];
    return { card, docType, zone, idPair, idHint, list, addBtn, idFront, idBack, fileInputs };
  }

  function ensureUploadBoxWiring(uploadBox) {
    if (!uploadBox) return;
    const input = uploadBox.querySelector('input[type="file"]');
    if (!input) return;

    function clearUploadVisual(targetInput) {
      if (!targetInput) return;
      const targetBox = targetInput.closest(".upload-box");
      if (!targetBox) return;
      targetBox.classList.remove("uploaded");
      targetBox.querySelectorAll(".uploaded-filename,.upload-remove").forEach((el) => el.remove());
    }

    async function convertHeicIfNeeded(targetInput) {
      if (!targetInput || !targetInput.files || targetInput.files.length === 0) return;
      const files = Array.from(targetInput.files);
      for (const file of files) {
        const ext = (file.name.split(".").pop() || "").toLowerCase();
        const isHeic = ext === "heic" || ext === "heif" || file.type === "image/heic" || file.type === "image/heif";
        if (isHeic) {
          targetInput.value = "";
          clearUploadVisual(targetInput);
          throw new Error("HEIC/HEIF is not supported. Please upload JPG, JPEG, PNG, WEBP, or PDF.");
        }
      }
    }

    uploadBox.addEventListener("click", () => input.click());
    input.addEventListener("click", (e) => e.stopPropagation());

    uploadBox.addEventListener("dragover", (e) => { e.preventDefault(); uploadBox.classList.add("dragover"); });
    uploadBox.addEventListener("dragleave", () => uploadBox.classList.remove("dragover"));
    uploadBox.addEventListener("drop", async (e) => {
      e.preventDefault();
      uploadBox.classList.remove("dragover");
      if (e.dataTransfer.files.length) {
        try {
          input.files = e.dataTransfer.files;
          await convertHeicIfNeeded(input);
          if (input.files.length) markUploaded(uploadBox, input);
        } catch (err) {
          clearUploadVisual(input);
          modalError(err?.message || "Unsupported file. Please upload JPG, JPEG, PNG, WEBP, or PDF.");
        }
      }
    });

    input.addEventListener("change", async () => {
      if (!input.files.length) return;
      try {
        await convertHeicIfNeeded(input);
        if (input.files.length) markUploaded(uploadBox, input);
      } catch (err) {
        clearUploadVisual(input);
        modalError(err?.message || "Unsupported file. Please upload JPG, JPEG, PNG, WEBP, or PDF.");
      }
    });
  }

  function markUploaded(box, input) {
    box.classList.add("uploaded");
    box.querySelectorAll(".uploaded-filename,.upload-remove").forEach((el) => el.remove());

    const filename = document.createElement("div");
    filename.className = "uploaded-filename small mt-2 text-center";
    filename.textContent = input.files[0]?.name || "";
    box.appendChild(filename);

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "upload-remove";
    removeBtn.innerHTML = "&times;";
    removeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      input.value = "";
      box.classList.remove("uploaded");
      filename.remove();
      removeBtn.remove();
    });
    box.appendChild(removeBtn);
  }

  function setIdUi() {
    const passport = isPassportSelected();
    if (idBackWrapper) idBackWrapper.classList.toggle("d-none", passport);
  }

  function setProofUi() {
    if (forceSectorOnly) {
      if (proofRequirementSection) {
        proofRequirementSection.classList.add("d-none");
        disableInputsInSection(proofRequirementSection, true);
      }
      if (pictureRequirementSection) {
        pictureRequirementSection.classList.add("d-none");
        disableInputsInSection(pictureRequirementSection, true);
      }
      return;
    }

    if (!needsProof) {
      if (proofRequirementSection) proofRequirementSection.classList.add("d-none");
      return;
    }

    if (proofRequirementSection) proofRequirementSection.classList.remove("d-none");
    const proofType = proofTypeSelect.value;
    idProofWrapper.classList.toggle("d-none", proofType !== "ID");
    documentProofWrapper.classList.toggle("d-none", proofType !== "Document");
    setIdUi();
    updateSectorUi();
  }

  function updateSectorUi() {
    if (forceProfilingOnly) {
      if (sectorProofSection) {
        sectorProofSection.classList.add("d-none");
        disableInputsInSection(sectorProofSection, true);
      }
      return;
    }

    const selectedKeys = getSelectedSectorKeys();
    sectorProofSection.classList.toggle("d-none", selectedKeys.length === 0);

    Object.keys(sectorMap).forEach((sectorKey) => {
      const selected = selectedKeys.includes(sectorKey);
      const { card, docType, zone, idPair, idHint, list, addBtn, idFront, idBack, fileInputs } = getSectorElements(sectorKey);
      if (!card) return;

      card.classList.toggle("d-none", !selected);
      if (!selected) {
        if (docType) docType.value = "";
        if (zone) zone.classList.add("d-none");
        return;
      }

      const prohibited = isSectorUploadProhibited(sectorKey);
      card.classList.toggle("opacity-75", prohibited);
      if (docType) docType.disabled = prohibited;

      if (prohibited) {
        if (zone) zone.classList.add("d-none");
        if (docType) docType.value = "";
        clearFiles([idFront, idBack, ...fileInputs]);
        return;
      }

      const docTypeValue = docType ? docType.value : "";
      const hasDocType = !!docTypeValue;
      if (zone) zone.classList.toggle("d-none", !hasDocType);
      const idLike = isIdLikeSectorDocType(docTypeValue);

      if (idPair) idPair.classList.toggle("d-none", !idLike);
      if (idHint) idHint.classList.toggle("d-none", !idLike);
      if (list) list.classList.toggle("d-none", idLike);
      if (addBtn) addBtn.classList.toggle("d-none", idLike);

      if (idLike) {
        clearFiles(fileInputs);
      } else {
        clearFiles([idFront, idBack]);
      }
    });
  }

  function addDocumentAttachment() {
    const count = documentUploadList.querySelectorAll('input[name="documentProof[]"]').length;
    if (count >= 3) return;

    const wrapper = document.createElement("div");
    wrapper.className = "position-relative";
    wrapper.innerHTML = `
      <div class="upload-box position-relative">
        <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
        <div class="upload-subtext mt-1">PDF or image</div>
        <input type="file" class="form-control upload-input" name="documentProof[]" accept=".pdf,.jpg,.jpeg,.png,.webp">
      </div>
      <small class="text-muted d-block text-center mt-2">Attachment ${count + 1}</small>
    `;
    documentUploadList.appendChild(wrapper);
    ensureUploadBoxWiring(wrapper.querySelector(".upload-box"));
  }

  function addSectorAttachment(sectorKey) {
    const { list } = getSectorElements(sectorKey);
    if (!list) return;
    const count = list.querySelectorAll(".sector-doc-file").length;
    if (count >= 3) return;

    const wrapper = document.createElement("div");
    wrapper.className = "position-relative mt-2";
    wrapper.innerHTML = `
      <div class="upload-box position-relative">
        <div class="upload-text"><i class="fa-solid fa-upload"></i><span>Drag & drop file</span></div>
        <div class="upload-subtext mt-1">PDF or image</div>
        <input type="file" class="form-control upload-input sector-doc-file" name="sectorDocFile[${sectorKey}][]" data-sector="${sectorKey}" accept=".pdf,.jpg,.jpeg,.png,.webp">
      </div>
      <small class="text-muted d-block text-center mt-2">Attachment ${count + 1}</small>
    `;
    list.appendChild(wrapper);
    ensureUploadBoxWiring(wrapper.querySelector(".upload-box"));
  }

  function countFiles(inputs) {
    return inputs.reduce((acc, input) => acc + (input && input.files && input.files.length ? 1 : 0), 0);
  }

  function hasAnySelectedUpload() {
    return Array.from(form?.querySelectorAll('input[type="file"]') || []).some((input) => {
      return !!(input.files && input.files.length > 0);
    });
  }

  function validateBeforeSubmit() {
    if (!hasAnySelectedUpload()) {
      return true;
    }

    if (!forceSectorOnly && needsProof && !proofTypeSelect.value) {
      modalError("Please select a proof type.");
      return false;
    }

    if (!forceSectorOnly && needsProof && proofTypeSelect.value === "ID") {
      if (!idTypeSelect.value) return modalError("Please select an ID type."), false;
      if (!idNumberInput.value.trim()) return modalError("Please provide your ID number."), false;
      if (!idFrontInput.files.length) return modalError("Please upload the ID front file."), false;
      if (!isPassportSelected() && !idBackInput.files.length) return modalError("Please upload the ID back file."), false;
    }

    if (!forceSectorOnly && needsProof && proofTypeSelect.value === "Document") {
      if (!documentTypeSelect.value) return modalError("Please select a document type."), false;
      const docFiles = Array.from(document.querySelectorAll('input[name="documentProof[]"]'));
      if (countFiles(docFiles) === 0) return modalError("Please upload at least one supporting document."), false;
    }

    if (!forceSectorOnly && needsPicture && !pictureInput.files.length) {
      modalError("Please upload a 2x2 profile picture.");
      return false;
    }

    if (forceProfilingOnly) {
      return true;
    }

    const selectedKeys = getSelectedSectorKeys();
    for (const sectorKey of selectedKeys) {
      if (isSectorUploadProhibited(sectorKey)) continue;

      const sectorMeta = sectorMap[sectorKey];
      const { docType, idFront, idBack, fileInputs } = getSectorElements(sectorKey);
      const required = !!sectorMeta.required;
      const docTypeValue = (docType?.value || "").trim();
      const hasAnyListFile = countFiles(fileInputs) > 0;
      const hasFrontAny = !!(idFront && idFront.files && idFront.files.length);
      const hasBackAny = !!(idBack && idBack.files && idBack.files.length);
      const hasAnySectorFile = hasAnyListFile || hasFrontAny || hasBackAny;
      const attemptedSectorUpload = (docTypeValue !== "" || hasAnySectorFile);

      if (required && !docTypeValue) {
        modalError("Please select a document type for required sector membership proofs.");
        return false;
      }

      if (!attemptedSectorUpload) continue;
      if (!docTypeValue) {
        modalError("Please select a document type for the sector upload.");
        return false;
      }

      if (isIdLikeSectorDocType(docTypeValue)) {
        const hasFront = hasFrontAny;
        const hasBack = hasBackAny;
        if (required && (!hasFront || !hasBack)) {
          modalError("Please upload both front and back images for ID-type sector proofs.");
          return false;
        }
        if (!required && (!hasFront || !hasBack)) {
          modalError("Please upload both front and back images for ID-type sector proofs.");
          return false;
        }
      } else {
        const hasAny = hasAnyListFile;
        if (required && !hasAny) {
          modalError("Please upload the required sector membership document.");
          return false;
        }
        if (!required && !hasAny) {
          modalError("Please upload at least one file for the selected sector document type.");
          return false;
        }
      }
    }

    return true;
  }

  function applyRequirementStates(requirements) {
    const proofState = String(requirements?.proof?.state || "missing");
    const pictureState = String(requirements?.picture?.state || "missing");
    needsProof = !!requirements?.proof?.needs_upload;
    needsPicture = !!requirements?.picture?.needs_upload;

    if (forceSectorOnly) {
      needsProof = false;
      needsPicture = false;
    }

    if (proofRequirementSection) {
      proofRequirementSection.classList.toggle("d-none", !needsProof);
      disableInputsInSection(proofRequirementSection, !needsProof || forceSectorOnly);
    }

    if (pictureRequirementSection) {
      pictureRequirementSection.classList.toggle("d-none", !needsPicture);
      disableInputsInSection(pictureRequirementSection, !needsPicture || forceSectorOnly);
    }

    if (forceProfilingOnly && sectorProofSection) {
      sectorProofSection.classList.add("d-none");
      disableInputsInSection(sectorProofSection, true);
    } else if (sectorProofSection) {
      disableInputsInSection(sectorProofSection, false);
    }

    if (!needsProof && !needsPicture) {
      if (forceSectorOnly) {
        setNotice("Resubmit mode: upload sector membership supporting document(s) only.", "info");
        if (submitBtn) submitBtn.disabled = false;
      } else {
        setNotice("All required documents are already submitted or verified. No re-upload is needed right now.", "success");
        if (submitBtn) submitBtn.disabled = true;
      }
    } else {
      if (submitBtn) submitBtn.disabled = false;
      const parts = [];
      if (needsProof) parts.push(`proof of residency/identification (${proofState})`);
      if (needsPicture) parts.push(`2x2 profile image (${pictureState})`);
      if (forceProfilingOnly) {
        setNotice(`Resubmit mode: upload resident profiling requirement(s) only: ${parts.join(" and ")}.`, "warning");
      } else if (forceSectorOnly) {
        setNotice("Resubmit mode: upload sector membership supporting document(s) only.", "info");
      } else {
        setNotice(`Please upload only the required item(s): ${parts.join(" and ")}.`, "warning");
      }
    }
  }

  async function loadRequirements() {
    try {
      const res = await fetch("../PhpFiles/Resident-End/getDocumentUploadRequirements.php", {
        method: "GET",
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success || !data?.requirements) {
        setNotice("Unable to load upload requirements right now. You may still submit required documents.", "warning");
        return;
      }
      applyRequirementStates(data.requirements);
    } catch (err) {
      setNotice("Unable to load upload requirements right now. You may still submit required documents.", "warning");
    }
  }

  if (proofTypeSelect) proofTypeSelect.addEventListener("change", setProofUi);
  if (idTypeSelect) idTypeSelect.addEventListener("change", () => { setIdUi(); updateSectorUi(); });
  if (addDocumentBtn) addDocumentBtn.addEventListener("click", addDocumentAttachment);

  Object.keys(sectorMap).forEach((sectorKey) => {
    const { docType, addBtn } = getSectorElements(sectorKey);
    if (docType) docType.addEventListener("change", updateSectorUi);
    if (addBtn) addBtn.addEventListener("click", () => addSectorAttachment(sectorKey));
  });

  document.querySelectorAll(".upload-box").forEach(ensureUploadBoxWiring);

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!validateBeforeSubmit()) return;

      if (submitBtn) submitBtn.disabled = true;
      try {
        const res = await fetch(form.action, { method: "POST", body: new FormData(form) });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
          modalError(data?.message || "Unable to submit documents right now. Please try again.");
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        modalSuccess(data.message || "Documents submitted successfully.", () => {
          window.location.href = data.redirect || "resident_dashboard.php";
        });
      } catch (err) {
        modalError("Network error. Please try again.");
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  loadRequirements().finally(() => {
    setProofUi();
    updateSectorUi();
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
</body>
</html>
