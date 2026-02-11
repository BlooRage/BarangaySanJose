<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<title>Barangay ID Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/BarangayId.css">
</head>

<body>

<div class="d-flex min-vh-100">

<?php include 'includes/resident_sidebar.php'; ?>

<main class="application-main">
<div class="application-card">

<a href="javascript:history.back()" class="back-link">&lt; Go Back</a>

<h1 class="form-title">Application for Barangay ID</h1>
<p class="form-subtitle">All fields marked with  *  are required</p>

<form method="POST" action="">

<!-- PERSONAL INFORMATION -->
<h2 class="section-title text-center">Personal Information</h2>

<div class="status-row">
    <label><input type="checkbox" name="pwd" class="text-center"> PWD</label>
    <label><input type="checkbox" name="senior" class="text-center"> Senior Citizen</label>
</div>

<!-- add date and time today to the form input format pre-filled. philippine time-->
 <div class="status-row">
    <label for="application_date">Application Date:</label>
    <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
</div>

<div class="form-row">
    <div>
        <label>Last Name  <span>* </span></label>
        <input type="text" name="last_name" required>
    </div>

    <div>
        <label>First Name  <span>* </span></label>
        <input type="text" name="first_name" required>
    </div>

    <div>
        <label>Middle Name</label>
        <input type="text" name="middle_name">
    </div>

    <div>
        <label>Suffix</label>
        <select name="suffix">
            <option value="">None</option>
            <option value="Jr.">Jr.</option>
            <option value="Sr.">Sr.</option>
            <option value="III">III</option>
            <option value="IV">IV</option>
        </select>
    </div>
</div>

<div class="form-row">
    <div>
        <label>Date of Birth <span>*</span></label>
        <input type="date">
    </div>

    <div>
        <label>Birthplace <span>*</span></label>
        <input type="text">
    </div>
    <div class="phonenum">
        <label>Phone Number <span>*</span></label>
        <input type="tel" name="phone_number" required>
        </div>



</div>

<div class="form-row">
    <div class="full-width">
        <label>Complete Address <span>*</span>  </label>
        <input type="text" name="address" required>
    </div>
</div>
<br>
<!-- EMERGENCY CONTACT -->
<h2 class="section-title text-center pb-2">
Contact Person in case of Emergency (Family Member)
</h2>


<div class="form-row">
    <div>
        <label>First Name  <span>*</span> </label>
        <input type="text" name="emergency_first" required>
    </div>

    <div>
        <label>Last Name  <span>*</span> </label>
        <input type="text" name="emergency_last" required>
    </div>

    <div>
        <label>Middle Name</label>
        <input type="text" name="emergency_middle">
    </div>

    <div>
        <!-- suffix is select drop down-->
        <label>Suffix</label>
        <select name="emergency_suffix">
            <option value="">None</option>
            <option value="Jr.">Jr.</option>
            <option value="Sr.">Sr.</option>
            <option value="III">III</option>
            <option value="IV">IV</option>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="full-width">
        <label>Contact Number  <span>*</span> </label>
        <input type="tel" name="emergency_contact" required>
    </div>
</div>

<!-- CERTIFICATION -->
<div class="certification-row">
    <label class="certification-text">
        <input type="checkbox" required>
        I hereby certify that the above information is true and correct to the best of my knowledge and belief.
    </label>

    <button type="submit" class="submit-btn">SUBMIT</button>
</div>

</form>
</div>
</main>

</div>
</body>
</html>


