<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <title>Clearance Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
    <style>
        .requirements-top {
            list-style-type: disc;
        }

        .requirements-top > li::marker {
            color: #000;
            font-size: 1.1em;
        }

        .requirements-numeric {
            list-style-type: decimal;
        }

        .requirements-alpha {
            list-style-type: lower-alpha;
        }

        .requirements-square {
            list-style-type: square;
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
            <h1 class="page-title">Barangay Clearances</h1>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Clearance Application. Please select the clearance you need from the options below to begin your application. Make sure the details you provide are complete and accurate to avoid processing delays.
            </p>

            <p class="section-label">List of clearances:</p>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 certificate-grid justify-content-center">
                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/businessclearance.png" class="certificate-icon" alt="For Business Clearance">
                        <h3>FOR BUSINESS CLEARANCE</h3>
                        <p class="certificate-text">
                            Apply for barangay business clearance for new applications, renewals, and compliance checks.
                        </p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Business Clearance Requirements"
                            data-apply-href="BusinessClearanceForm.php"
                            data-body="
                                <p class='mb-2'><span class='fw-semibold'>Filing a renewal application is required one (1) year from the issuance date of your permit.</span></p>
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li class='mb-2'>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>
                                                Business Registration
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>DTI Certificate for sole proprietorship</li>
                                                    <li>SEC Certificate for company or partnership</li>
                                                </ol>
                                            </li>
                                            <li>
                                                Proof of Business Address
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>
                                                        If renter:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Contract of Lease</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        If owner, one of the following:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title</li>
                                                            <li>Tax Declaration</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </li>
                                        </ol>
                                    </li>
                                    <li class='mb-2'>
                                        Renewal
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>Business Clearance from the previous year</li>
                                            <li>
                                                Updated Business Registration
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>DTI Certificate for sole proprietorship</li>
                                                    <li>SEC Certificate for company or partnership</li>
                                                </ol>
                                            </li>
                                        </ol>
                                    </li>
                                    <!-- <li>
                                        For Ownership Change
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Original Copy of Business Clearance</li>
                                            <li>Affidavit of Change of Ownership</li>
                                            <li>Valid ID of Old and New Owner</li>
                                            <li>Authorization Letter</li>
                                        </ol>
                                    </li> -->
                                </ul>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/tricycle.png" class="certificate-icon" alt="For Tricycle Permit">
                        <h3>FOR TRICYCLE PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for tricycle permit processing.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Tricycle Permit Requirements"
                            data-apply-href="TricycleForm.php"
                            data-body="
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li class='mb-2'>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>TODA/PODA Certification</li>
                                            <li>LTO Registration Documents (O.R. and C.R.)</li>
                                        </ol>
                                    </li>
                                    <li class='mb-2'>
                                        Renewal
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>TODA/PODA Certification</li>
                                            <li>LTO Registration Documents (O.R. and C.R.)</li>
                                            <li>Barangay Clearance from the previous year</li>
                                        </ol>
                                    </li>
                                </ul>
                                <p class='mt-2 mb-0 text-muted small'>If LTO Registration is not named to the owner/operator, please upload a notarized Deed of Sale.</p>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/electricity.png" class="certificate-icon" alt="For Electrical Permit">
                        <h3>FOR ELECTRICAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for electrical permit processing.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Barangay Clearance for Electrical Permit Requirements"
                            data-apply-href="ElectricalForm.php"
                            data-body="
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>
                                                Proof of Address, one of the following:
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>
                                                        If the lot title is named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title</li>
                                                            <li>Tax Declaration</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        If the lot title is not named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title from the title owner with Notarized Deed of Sale</li>
                                                            <li>Tax Declaration from the title owner with Notarized Deed of Sale</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </li>
                                        </ol>
                                    </li>
                                </ul>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/water.png" class="certificate-icon" alt="For Water Permit">
                        <h3>FOR WATER PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for water permit processing.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Barangay Clearance for Water Permit Requirements"
                            data-apply-href="WaterForm.php"
                            data-body="
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>
                                                Proof of Address, one of the following:
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>
                                                        If the lot title is named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title</li>
                                                            <li>Tax Declaration</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        If the lot title is not named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title from the title owner with Notarized Deed of Sale</li>
                                                            <li>Tax Declaration from the title owner with Notarized Deed of Sale</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </li>
                                        </ol>
                                    </li>
                                </ul>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/residential.png" class="certificate-icon" alt="For Residential Permit">
                        <h3>FOR RESIDENTIAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for residential permit processing.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Barangay Clearance for Residential Permit Requirements"
                            data-apply-href="ResidentialForm.php"
                            data-body="
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>
                                                Proof of Address, one of the following:
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>
                                                        If the lot title is named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title</li>
                                                            <li>Tax Declaration</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        If the lot title is not named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title from the title owner with Notarized Deed of Sale</li>
                                                            <li>Tax Declaration from the title owner with Notarized Deed of Sale</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </li>
                                        </ol>
                                    </li>
                                </ul>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/commercial.png" class="certificate-icon" alt="For Commercial Permit">
                        <h3>FOR COMMERCIAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for commercial permit processing.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#requirementsModal"
                            data-title="Barangay Clearance for Commercial Permit Requirements"
                            data-apply-href="CommercialForm.php"
                            data-body="
                                <ul class='mb-0 ps-3 requirements-top'>
                                    <li>
                                        New Application
                                        <ol class='mt-1 ps-3 requirements-numeric'>
                                            <li>Valid Government-Issued ID</li>
                                            <li>
                                                Proof of Address, one of the following:
                                                <ol class='mt-1 ps-3 requirements-alpha'>
                                                    <li>
                                                        If the lot title is named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title</li>
                                                            <li>Tax Declaration</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        If the lot title is not named to Applicant:
                                                        <ul class='mt-1 ps-3 requirements-square'>
                                                            <li>Transfer Certificate of Title from the title owner with Notarized Deed of Sale</li>
                                                            <li>Tax Declaration from the title owner with Notarized Deed of Sale</li>
                                                        </ul>
                                                    </li>
                                                </ol>
                                            </li>
                                            <li>SEC Certificate</li>
                                        </ol>
                                    </li>
                                </ul>
                            "
                        >
                            Apply Now
                        </button>
                    </div>
                </div>

                <!-- <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/clearancepermits.png" class="certificate-icon" alt="For Other Permits">
                        <h3>FOR OTHER PERMITS</h3>
                        <p class="certificate-text">Apply for barangay clearance required for other permit processing.</p>
                        <button class="btn apply-btn" onclick="location.href='OtherPermitsForm.php'">Apply Now</button>
                    </div>
                </div> -->
            </div>
        </main>
    </div>

    <div class="modal fade" id="requirementsModal" tabindex="-1" aria-labelledby="requirementsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requirementsModalLabel">Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="requirementsModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a class="btn btn-primary" id="requirementsProceedBtn" href="#">Proceed to Application</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const requirementsModal = document.getElementById('requirementsModal');

        requirementsModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const title = button?.getAttribute('data-title') || 'Requirements';
            const body = button?.getAttribute('data-body') || '';
            const applyHref = button?.getAttribute('data-apply-href') || '#';
            const modalTitle = requirementsModal.querySelector('.modal-title');
            const modalBody = requirementsModal.querySelector('#requirementsModalBody');
            const proceedBtn = requirementsModal.querySelector('#requirementsProceedBtn');

            modalTitle.textContent = title;
            modalBody.innerHTML = body;
            proceedBtn.setAttribute('href', applyHref);
        });
    </script>
</body>

</html>
