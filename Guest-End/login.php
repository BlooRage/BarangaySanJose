<?php
// login.php — FRONT-END (OLD BASELINE + NEW INACTIVE VERIFY + OTP REUSE + SUCCESS MODAL)
// ✅ Based on OLD markup/IDs/paths to keep loginScripts.js working
// ✅ Keeps NEW inactive verify step + success modal
// ✅ JS include order matches OLD (loginScripts.js then modalHandler.js)

session_start();

if (isset($_SESSION['user_id'])) {
  header("Location: ../Resident-End/resident_dashboard.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Barangay San Jose - Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css" />
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/LoginModule.css" />
    <link rel="stylesheet" href="../CSS-Styles/modalStyle.css" />

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

    <!-- ✅ OLD ORDER (keep this) -->
    <script src="../JS-Script-Files/loginScripts.js" defer></script>
    <script src="../JS-Script-Files/modalHandler.js" defer></script>
  </head>

  <body>
    <div class="navbarWrapper">    <nav class="navbar navbar-expand-xl align-items-center navbar-light bg-white shadow-sm">
      <div class="container-fluid align-items-center px-4">
        <a id="navbarBrand" class="navbar-brand" href="#">
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center" />
          Barangay San Jose
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul id="navbarLinks" class="navbar-nav ms-auto">
            <li class="nav-item mx-lg-3"><a class="nav-link" href="../index.html">Home</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="government.html">Government</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="services.html">Services</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="news.html">News</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="faq.html">FAQ</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="contact.html">Contact</a></li>
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="login.php">Log in</a></li>
          </ul>
        </div>
      </div>
    </nav>    </div>

    <main>
      <div class="login-signup-container">
        <div class="auth-image login-image" id="authImage"></div>

        <div class="form-wrapper">
          <!-- =========================
               LOGIN FORM (OLD IDs)
               ========================= -->
          <form class="form-box active" id="loginForm" action="../PhpFiles/Login/login.php" method="post" name="loginForm">
            <h1 class="mb-1 fs-2 text-center"><strong>Welcome Back!</strong></h1>
            <p class="text-center fs-6 text-muted intro-message">Please enter your credentials.</p>
            <h4 class="mb-2 fs-4 text-center"><strong>Login</strong></h4>

            <input type="text" name="user" id="userAccount" class="fs-6 form-control mb-3" placeholder="Email / Phone" required />

            <div class="input-group mb-3">
              <input type="password" name="loginPassword" id="loginPassword" class="form-control" placeholder="Password" required />
              <span class="input-group-text" style="cursor: pointer" onclick="togglePassword('loginPassword','eye2')">
                <i id="eye2" class="bi bi-eye"></i>
              </span>
            </div>

            <div class="w-100 d-flex justify-content-end mb-3">
              <a href="javascript:void(0)" id="forgotPasswordLink" class="text-primary text-decoration-underline">Forgot Password?</a>
            </div>

            <div id="loginFormErrors" class="text-danger" style="font-size: 0.9rem; margin-bottom: 10px"></div>

            <button type="submit" class="btn btn-primary w-100 mb-2">Login</button>

            <p class="mt-3 text-center">
              Don't have an account?
              <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="switchToSignup()">Register</a>
              now.
            </p>
          </form>

          <!-- =========================
               SIGNUP FORM (OLD IDs)
               ========================= -->
          <form class="form-box" id="signupForm" action="../PhpFiles/Login/RegisterAccount.php" method="post" name="signupForm">
            <h1 class="mb-1 fs-2 text-center"><strong>Good to see you!</strong></h1>
            <p class="text-center fs-6 text-muted intro-message">Sign up to get started</p>
            <h4 class="mb-3 text-center"><strong>Sign Up</strong></h4>

            <div class="input-group mb-2">
              <span class="input-group-text">
                <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH Flag" width="24" style="margin-right: 5px" />
                +63
              </span>
              <input type="tel" id="RPhoneNumber" name="RPhoneNumber" class="form-control" placeholder="9XXXXXXXXX" maxlength="10" pattern="9[0-9]{9}" inputmode="numeric" title="Enter a 10-digit mobile number starting with 9 (e.g., 9XXXXXXXXX)." required />
            </div>

            <input type="email" id="REmail" name="REmail" class="form-control mb-2" placeholder="Email" required />

            <div class="input-group mb-2">
              <input type="password" id="RPassword" name="RPassword" class="form-control" placeholder="Password" required />
              <span class="input-group-text" style="cursor: pointer" onclick="togglePassword('RPassword','eye1')">
                <i id="eye1" class="bi bi-eye"></i>
              </span>
            </div>

            <div class="input-group mb-2">
              <input type="password" id="RConfirmPassword" name="RConfirmPassword" class="form-control" placeholder="Confirm Password" required />
              <span class="input-group-text" style="cursor: pointer" onclick="togglePassword('RConfirmPassword','eye3')">
                <i id="eye3" class="bi bi-eye"></i>
              </span>
            </div>

            <div id="signupFormErrors" class="text-danger" style="font-size: 0.9rem; margin-bottom: 10px"></div>

            <button type="button" class="btn btn-success w-100" id="createAccountBtn">Create Account</button>

            <p class="mt-3 text-center">
              Already have an account?
              <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="switchToLogin()">Login</a>
            </p>
          </form>

          <!-- =========================
               ✅ INACTIVE VERIFY STEP (NEW)
               Uses fp-step like old forgot/otp steps so animations still work
               ========================= -->
          <form id="inactive-verify-step" class="form-box fp-step" name="inactive-verify-step">
            <h1 class="mb-1 fs-2 text-center"><strong>Verify Your Account</strong></h1>
            <p class="text-center fs-6 text-muted mb-3">Let's verify your account first.</p>

            <div class="alert alert-warning" role="alert">
              You’ve been inactive for a long time. The system needs to verify your account first.
            </div>

            <div id="inactiveVerifyErrors" class="text-danger mb-2" style="font-size: 0.9rem"></div>

            <button type="button" id="inactiveContinueBtn" class="btn btn-primary w-100">Continue</button>

            <p class="mt-3 text-center">
              Back to
              <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="backToLogin()">Login</a>
            </p>
          </form>

          <!-- =========================
               FORGOT PASSWORD: VERIFY (OLD IDs)
               ========================= -->
          <form id="forgotpassword-verify" class="form-box fp-step" name="forgotpassword-verify">
            <h1 class="mb-1 fs-2 text-center"><strong>Forgot Password</strong></h1>
            <p class="text-center fs-6 text-muted mb-3">Enter your Phone Number and Email to reset your password.</p>

            <div class="input-group mb-2">
              <span class="input-group-text">
                <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH Flag" width="24" style="margin-right: 5px" />
                +63
              </span>
              <input type="tel" id="forgotPhone" class="form-control" placeholder="9XXXXXXXXX" maxlength="10" pattern="9[0-9]{9}" inputmode="numeric" title="Enter a 10-digit mobile number starting with 9 (e.g., 9XXXXXXXXX)." required />
            </div>

            <div class="input-group mb-2">
              <input type="email" id="forgotEmail" class="form-control" placeholder="Email" required />
            </div>

            <div id="forgotFormErrors"></div>

            <button type="button" id="forgotContinueBtn" class="btn btn-primary w-100 mb-2">Continue</button>

            <p class="mt-3 text-center">
              Remembered your password?
              <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="backToLogin()">Login</a>
            </p>
          </form>

          <!-- =========================
               OTP STEP (OLD IDs)
               ✅ Keeps old structure so loginScripts.js continues to work
               ========================= -->
          <div id="otp-form" class="fp-step">
            <div class="otp-icon-wrapper text-center mb-3">
              <img src="../Images/SMS-OTP.png" alt="OTP Icon" class="otp-icon" />
            </div>

            <p class="otp-text text-center" id="otpMessage">
              Check your phone. An OTP has been sent to <strong>+63 •••••• XXXX</strong>
            </p>

            <div class="otp-inputs" id="otpInputs">
              <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
              <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
            </div>

            <button type="button" id="verifyOTPBtn" class="btn btn-primary w-100">Verify OTP</button>

            <div class="otp-actions text-center mt-3">
              <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                <a href="javascript:void(0)" id="resendOTP" class="text-primary text-decoration-underline">Resend OTP</a>
                <span id="resendTimer" style="font-size: 0.9rem"></span>
              </div>
              <br />
              <div>
                <a href="javascript:void(0)" id="returnToSignup" class="text-primary text-decoration-underline">Back to Signup</a>
              </div>
            </div>
          </div>

          <!-- =========================
               RESET PASSWORD STEP (OLD IDs)
               ========================= -->
          <form id="reset-password-step" class="form-box fp-step" name="reset-password-step">
            <h1 class="mb-1 fs-2 text-center"><strong>Reset Password</strong></h1>
            <p class="text-center fs-6 text-muted mb-3">Enter your new password below.</p>

            <div class="input-group mb-2">
              <input type="password" id="newPassword" class="form-control" placeholder="New Password" required />
              <span class="input-group-text" onclick="togglePassword('newPassword','eyeNew')" style="cursor: pointer">
                <i id="eyeNew" class="bi bi-eye"></i>
              </span>
            </div>

            <div class="input-group mb-2">
              <input type="password" id="confirmNewPassword" class="form-control" placeholder="Confirm Password" required />
              <span class="input-group-text" onclick="togglePassword('confirmNewPassword','eyeConfirm')" style="cursor: pointer">
                <i id="eyeConfirm" class="bi bi-eye"></i>
              </span>
            </div>

            <div id="resetPasswordErrors" class="text-danger mb-2"></div>

            <button type="button" id="submitNewPasswordBtn" class="btn btn-success w-100">Reset Password</button>

            <p class="mt-3 text-center">
              Remembered your password?
              <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="backToLogin()">Login</a>
            </p>
          </form>
        </div>
      </div>
    </main>

    <!-- ✅ NEW: SUCCESS MODAL (Bootstrap) -->
    <div class="modal fade" id="accountVerifiedModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Account Verified</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">Account verification successful.</div>
          <div class="modal-footer">
            <button type="button" id="verifiedContinueBtn" class="btn btn-success w-100">Continue</button>
          </div>
        </div>
      </div>
    </div>
      <script>
        document.addEventListener("click", function (event) {
            var navbar = document.getElementById("navbarNav");
            var toggler = document.querySelector(".navbar-toggler");
            if (!navbar || !toggler) {
                return;
            }
            var isShown = navbar.classList.contains("show");
            if (!isShown) {
                return;
            }
            var clickedInside = navbar.contains(event.target) || toggler.contains(event.target);
            if (!clickedInside) {
                var collapse = bootstrap.Collapse.getOrCreateInstance(navbar);
                collapse.hide();
            }
        });
    </script></body>
</html>






