<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Identity - Barangay San Jose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/applicationForms.css">
</head>
<body>
<div class="d-flex min-vh-100">
    <?php include 'includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
        <div class="main-head application-card orange-card py-3 rounded">
            <div class="main-head-content">
            <a href="certificate_landing_page.php" class="back-link">< Go Back</a>
            
            <h1 class="form-title">Identity</h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

           <form action="#" method="POST">

                <h2 class="section-title text-center text-dark">Childâ€™s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="child_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="child_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="child_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="child_suffix">
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Date of Birth<span class="required-asterisk">*</span></label>
                            <input type="date" name="child_dob" required>
                        </div>
                    </div>
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Kasarian / Sex<span class="required-asterisk">*</span></label>
                            <select name="child_sex" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Place of Birth<span class="required-asterisk">*</span></label>
                            <input type="text" name="child_birthplace" required>
                        </div>
                    </div>
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Nationality<span class="required-asterisk">*</span></label>
                            <input type="text" name="child_nationality" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="child_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="child_unit_number" name="child_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="child_house_number">House Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="child_house_number" name="child_house_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="child_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="child_street_name" name="child_street_name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Fatherâ€™s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="father_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="father_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="father_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="father_suffix">
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Motherâ€™s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="mother_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="mother_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="mother_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="mother_suffix">
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Contact Number<span class="required-asterisk">*</span></label>
                            <input type="text" name="contact_number" required>
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




