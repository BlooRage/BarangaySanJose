<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permit Clearance - Barangay San Jose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .form-row .check-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .top-label {
            display: block;
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 5px;
            color: #333;
            text-align: left;
        }

        .flex-input {
            width: 100%;
        }

        .input-stack {
            display: flex;
            flex-direction: column;
            width: 100%;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="d-flex min-vh-100">
    <?php include 'includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
        <div class="main-head application-card orange-card py-3 rounded">
            <div class="main-head-content">
            <a href="/BarangaySanJose/Resident-End/ApplicationsLandingPage.php" class="back-link">< Go Back</a>
            
            <h1 class="form-title">Application for Barangay Clearance for Permits</h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">
                

                <h2 class="section-title text-center text-dark">Ownerâ€™s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="owner_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="owner_suffix">
                    </div>
                </div>

                <div class="form-row">
                    <div class="contact">
                        <div class="input-stack">
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="owner_phone">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="owner_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="owner_unit_number" name="owner_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="owner_house_number">House Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="owner_house_number" name="owner_house_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="owner_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="owner_street_name" name="owner_street_name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width d-flex justify-content-center gap-5">
                        <div class="check-item"><input type="checkbox" name="app_type" id="new"><label for="new">New Application</label></div>
                        <div class="check-item"><input type="checkbox" name="app_type" id="ren"><label for="ren">Renewal</label></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="check-item"><input type="checkbox" id="t1"><label for="t1">Tricycle</label></div>
                        <div class="check-item"><input type="checkbox" id="t2"><label for="t2">Electrical</label></div>
                        <div class="check-item"><input type="checkbox" id="t3"><label for="t3">Water</label></div>
                        <div class="check-item">
                            <input type="checkbox" id="t4">
                            <label for="t4" style="min-width: 50px;">Others:</label>
                            <input type="text" name="others_spec" placeholder="Please specify" class="flex-input">
                        </div>
                    </div>
                    <div class="phone">
                        <p style="font-size: 14px; font-weight: bold; color: #FE993C; margin-bottom: 10px;">IF BUILDING/ELECTRICAL/WATER:</p>
                        <div class="check-item"><input type="checkbox" id="c1"><label for="c1">Residential</label></div>
                        <div class="check-item"><input type="checkbox" id="c2"><label for="c2">Commercial</label></div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">For Tricycles</h2>
                <h2 class="section-title text-center text-dark" style="font-size: 16px; margin-top: 0;">Driver's Information</h2>
                
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="d_ln">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="d_fn">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="d_mn">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="d_sfx">
                    </div>
                </div>

                <div class="form-row">
                    <div class="contact">
                        <div class="input-stack">
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="d_phone">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="driver_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="driver_unit_number" name="driver_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="driver_house_number">House Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="driver_house_number" name="driver_house_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="driver_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="driver_street_name" name="driver_street_name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="check-item">
                            <input type="checkbox" id="pri"><label for="pri">Private</label>
                        </div>
                        
                        <div class="input-stack">
                            <label class="top-label">OR/CR Number:</label>
                            <input type="text" name="or_cr" class="flex-input">
                        </div>

                        <div class="input-stack">
                            <label class="top-label">Plate Number:</label>
                            <input type="text" name="plate" class="flex-input">
                        </div>

                        <div class="input-stack">
                            <label class="top-label">Body Number:</label>
                            <input type="text" name="body" class="flex-input">
                        </div>
                    </div>

                    <div class="phone">
                        <div class="check-item">
                            <input type="checkbox" id="toda"><label for="toda">TODA</label>
                        </div>

                        <div class="input-stack">
                            <label class="top-label">Specify:</label>
                            <input type="text" name="spec_toda" class="flex-input">
                        </div>

                        <div class="check-item" style="margin-top: 5px;">
                            <input type="checkbox" id="poda"><label for="poda">PODA</label>
                        </div>

                        <div class="input-stack">
                            <label class="top-label">Specify:</label>
                            <input type="text" name="spec_poda" class="flex-input">
                        </div>
                    </div>
                </div>

                <div class="agreement-row">
                    <div class="agreement-text check-item">
                        <input type="checkbox" id="agreement" required>
                        <label for="agreement">I hereby certify that the above information is true and correct to the best of my knowledge and belief.</label>
                    </div>
                    <button type="submit" class="submit-btn">SUBMIT</button>
                </div>

            </form>
            </div>
        </div>
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





