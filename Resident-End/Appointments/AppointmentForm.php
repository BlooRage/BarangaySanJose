<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

$residentinformationtbl = [
    "firstname" => "Juan",
    "middlename" => "",
    "lastname" => "Dela Cruz",
    "suffix" => "",
    "sex" => "Male",
    "birthdate" => "January 1, 1999",
    "age" => 27,
    "civil_status" => "Single",
    "head_of_family" => "No",
    "voter_status" => "Registered Voter",
    "occupation" => "Barista",
    "employment_status" => "Employed",
    "occupation_detail" => "",
    "religion" => "Roman Catholic",
    "sector_membership" => "Student, PWD",
    "emergency_name" => "Maria Dela Cruz",
    "emergency_contact" => "09123456789",
    "profile_pic" => "profile_pic_juan.png",
];

$residentaddresstbl = [
    "address_id" => 1,
    "resident_id" => 101,
    "street_number" => "14A",
    "street_name" => "Chico St",
    "subdivision" => "",
    "area_number" => "Area 01",
    "unit_number" => "Unit 5B",
    "barangay" => "San Jose",
];

$useraccountstbl = [
    "type" => "Resident",
    "created" => "March 12, 2024",
    "last_password_change" => "August 3, 2025",
    "email" => "juan.delacruz@email.com",
    "phone_number" => "09123456789",
];

$addressParts = array_filter([
    $residentaddresstbl["unit_number"] ? "Unit " . $residentaddresstbl["unit_number"] : "",
    $residentaddresstbl["street_number"],
    $residentaddresstbl["street_name"],
    $residentaddresstbl["subdivision"],
    $residentaddresstbl["area_number"],
    $residentaddresstbl["barangay"],
]);
$fullAddress = implode(", ", $addressParts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
</head>
<body>
    <div class="d-flex min-vh-100">

        <?php include '../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card py-3 mt-5 rounded">
                <div class="main-head-content">
                    <a href="/BarangaySanJose/Resident-End/Appointments/AppointmentsLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title" style="color: #de710c">Appointment Form</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="">
                        <h2 class="section-title text-center text-dark">Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($residentinformationtbl["firstname"]); ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($residentinformationtbl["lastname"]); ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($residentinformationtbl["middlename"]); ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name" class="text-bg-light" readonly value="<?php echo htmlspecialchars($residentinformationtbl["suffix"]); ?>" disabled>
                                    <option value="" <?php echo ($residentinformationtbl["suffix"] === "") ? "selected" : ""; ?>>None</option>
                                    <option value="Jr." <?php echo ($residentinformationtbl["suffix"] === "Jr.") ? "selected" : ""; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($residentinformationtbl["suffix"] === "Sr.") ? "selected" : ""; ?>>Sr.</option>
                                    <option value="III" <?php echo ($residentinformationtbl["suffix"] === "III") ? "selected" : ""; ?>>III</option>
                                    <option value="IV" <?php echo ($residentinformationtbl["suffix"] === "IV") ? "selected" : ""; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($residentinformationtbl["suffix"]); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($useraccountstbl["phone_number"]); ?>" readonly>
                            </div>
                        </div>

                        <div id="houseSystemWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo htmlspecialchars($residentaddresstbl["unit_number"]); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="houseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo htmlspecialchars($residentaddresstbl["street_number"]); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="streetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo htmlspecialchars($residentaddresstbl["street_name"]); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                                <select name="subject" required>
                                    <option value="">Select</option>
                                    <option value="document_claiming">Document Claiming</option>
                                    <option value="follow_up">Follow-up Concern</option>
                                    <option value="consultation">Consultation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Date of Appointment <span class="required-asterisk">*</span></label>
                                <input type="date" name="appointment_date" required>
                            </div>
                            <div>
                                <label class="top-label">Time of Appointment <span class="required-asterisk">*</span></label>
                                <input type="time" name="appointment_time" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <textarea name="purpose" rows="5" required></textarea>
                            </div>
                        </div>

                        <div class="agreement-row">
                                    <label class="agreement-text check-item">
                                        <input type="checkbox" required>I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                                    </label>

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

