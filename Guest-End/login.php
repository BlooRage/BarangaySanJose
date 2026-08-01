<?php
// login.php — FRONT-END (OLD BASELINE + NEW INACTIVE VERIFY + OTP REUSE + SUCCESS MODAL)
// ✅ Based on OLD markup/IDs/paths to keep loginScripts.js working
// ✅ Keeps NEW inactive verify step + success modal
// ✅ JS include order matches OLD (loginScripts.js then modalHandler.js)

require_once __DIR__ . "/../PhpFiles/General/security.php";
require_once __DIR__ . "/../PhpFiles/General/connection.php";
require_once __DIR__ . "/../PhpFiles/Login/redirectDestination.php";
require_once __DIR__ . "/../PhpFiles/General/recaptcha.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$appRoot = appRootPath();
$guestBaseHref = ($appRoot === '' ? '' : $appRoot) . '/Guest-End/';
$requestedService = normalizeRequestedResidentService($_GET['service'] ?? '');
$requestedAuthMode = strtolower(trim((string)($_GET['auth'] ?? '')));
$serviceLabel = residentServiceDisplayName($requestedService);
$serviceAwareRedirect = appUrl('/account-redirect' . ($requestedService !== '' ? '?service=' . rawurlencode($requestedService) : ''));
$loginRecaptchaEnabled = recaptcha_v3_frontend_enabled();
$loginRecaptchaSiteKey = $loginRecaptchaEnabled ? recaptcha_v3_site_key() : '';
$websiteOptions = wms_load_settings($conn);
$registrationOpen = !empty($websiteOptions['registration_enabled']);

$inviteToken = trim((string)($_GET['invite'] ?? ''));
if ($inviteToken !== '') {
  header("Location: " . appUrl('/official-onboarding?invite=' . urlencode($inviteToken)));
  exit;
}

// Prevent redirect loops when a stale session has user_id but missing role.
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
  header("Location: " . resolveRequestedPostLoginRedirect($conn, (string)$_SESSION['user_id'], (string)$_SESSION['role'], $requestedService));
  exit;
}
if (!empty($_SESSION['user_id']) && empty($_SESSION['role'])) {
  unset($_SESSION['user_id'], $_SESSION['logged_in'], $_SESSION['last_activity']);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <base href="<?= htmlspecialchars($guestBaseHref, ENT_QUOTES, 'UTF-8') ?>">
    
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Barangay San Jose - Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <?php if ($loginRecaptchaEnabled): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($loginRecaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>

    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css?v=20260706-navbar-fix" />
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/LoginModule.css?v=20260801-vertical-center2" />
    <link rel="stylesheet" href="../CSS-Styles/modalStyle.css" />

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
      window.APP_LOGIN_RESOLVE_REDIRECT = <?= json_encode($serviceAwareRedirect) ?>;
      window.APP_LOGIN_AUTH_MODE = <?= json_encode($requestedAuthMode) ?>;
      window.APP_LOGIN_REQUESTED_SERVICE = <?= json_encode($requestedService) ?>;
      window.APP_LOGIN_RECAPTCHA_ENABLED = <?= json_encode($loginRecaptchaEnabled) ?>;
      window.APP_LOGIN_RECAPTCHA_SITE_KEY = <?= json_encode($loginRecaptchaSiteKey) ?>;
    </script>

    <!-- ✅ OLD ORDER (keep this) -->
    <script src="../JS-Script-Files/loginScripts.js?v=20260801-admin-2fa-fix2" defer></script>
    <script src="../JS-Script-Files/modalHandler.js?v=20260801-02" defer></script>
  </head>

  <body data-cms-page="login" data-cms-endpoint="../PhpFiles/GET/getSiteContent.php" data-cms-asset-base="../">
    <div class="navbarWrapper">    <nav class="navbar navbar-expand-xl align-items-center navbar-light bg-white shadow-sm">
      <div class="container-fluid align-items-center px-4">
        <a id="navbarBrand" class="navbar-brand" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center" />
          Barangay San Jose
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul id="navbarLinks" class="navbar-nav ms-auto">
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Home</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/government'), ENT_QUOTES, 'UTF-8') ?>">Government</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/services'), ENT_QUOTES, 'UTF-8') ?>">Services</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/news'), ENT_QUOTES, 'UTF-8') ?>">News</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/faq'), ENT_QUOTES, 'UTF-8') ?>">FAQ</a></li>
            <li class="nav-item mx-lg-3"><a class="nav-link" href="<?= htmlspecialchars(appUrl('/contact'), ENT_QUOTES, 'UTF-8') ?>">Contact</a></li>
            <li class="nav-item mx-lg-3">
              <a class="nav-link active" aria-current="page" href="<?= htmlspecialchars(appUrl('/login'), ENT_QUOTES, 'UTF-8') ?>">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>    </div>

    <main>
      <div class="login-signup-container" data-cms-login-root>
        <div class="auth-image login-image" id="authImage"></div>

        <div class="form-wrapper">
          <!-- =========================
               LOGIN FORM (OLD IDs)
               ========================= -->
          <form class="form-box active" id="loginForm" action="../PhpFiles/Login/login.php" method="post" name="loginForm">
            <?php if ($serviceLabel !== ''): ?>
            <div class="alert alert-warning text-center py-2 mb-3" role="alert">
              Continue to <strong><?= htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8') ?></strong> after signing in.
            </div>
            <?php endif; ?>
            <h1 class="mb-1 fs-2 text-center"><strong>Welcome Back!</strong></h1>
            <p class="text-center fs-6 text-muted intro-message">Please enter your credentials.</p>
            <h4 class="mb-3 fs-4 text-center"><strong>Login</strong></h4>

            <input type="text" name="user" id="userAccount" class="fs-6 form-control mb-3" placeholder="Email / Phone" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required />

            <div class="input-group mb-3">
              <input type="password" name="loginPassword" id="loginPassword" class="form-control" placeholder="Password" autocomplete="current-password" autocapitalize="none" autocorrect="off" spellcheck="false" required />
              <span class="input-group-text" style="cursor: pointer" onclick="togglePassword('loginPassword','eye2')">
                <i id="eye2" class="bi bi-eye"></i>
              </span>
            </div>

            <div class="w-100 d-flex justify-content-center mb-3">
              <a href="javascript:void(0)" id="forgotPasswordLink" class="text-primary text-decoration-underline">Forgot Password?</a>
            </div>

            <div id="loginFormErrors" class="text-danger" style="font-size: 0.9rem; margin-bottom: 10px"></div>

            <button type="submit" class="btn btn-primary w-100 mb-2 d-inline-flex align-items-center justify-content-center gap-2" id="loginSubmitBtn">
              <span class="spinner-border spinner-border-sm d-none" id="loginSubmitSpinner" aria-hidden="true"></span>
              <span id="loginSubmitLabel">Login</span>
            </button>

            <p class="mt-3 text-center">
              Don't have an account?
              <?php if ($registrationOpen): ?>
                <a href="javascript:void(0)" class="text-primary text-decoration-underline" onclick="switchToSignup()">Register</a> now.
              <?php else: ?>
                <span class="text-muted">Registration is currently closed.</span>
              <?php endif; ?>
            </p>
          </form>

          <!-- =========================
               SIGNUP FORM (OLD IDs)
               ========================= -->
          <form class="form-box" id="signupForm" action="../PhpFiles/Login/RegisterAccount.php" method="post" name="signupForm" <?= $registrationOpen ? '' : 'aria-disabled="true"' ?>>
            <?php if (!$registrationOpen): ?><div class="alert alert-warning"><?= htmlspecialchars((string)$websiteOptions['registration_message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($serviceLabel !== ''): ?>
            <div class="alert alert-warning text-center py-2 mb-3" role="alert">
              Create an account to continue to <strong><?= htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8') ?></strong>.
            </div>
            <?php endif; ?>
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

            <div class="password-reqs-anchor mb-2">
              <div class="input-group mb-0">
                <input type="password" id="RPassword" name="RPassword" class="form-control" placeholder="Password" required />
                <span class="input-group-text" style="cursor: pointer" onclick="togglePassword('RPassword','eye1')">
                  <i id="eye1" class="bi bi-eye"></i>
                </span>
              </div>

              <div id="passwordRequirements" class="password-reqs is-hidden" aria-live="polite">
                <div class="password-reqs-title">Password must contain:</div>
                <ul class="password-reqs-list">
                  <li data-req="uppercase">1 uppercase letter</li>
                  <li data-req="lowercase">1 lowercase letter</li>
                  <li data-req="number">1 number</li>
                  <li data-req="special">1 special character</li>
                  <li data-req="length">At least 8 characters</li>
                </ul>
              </div>
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

            <div class="alert alert-warning" role="alert" data-modal-inline="true">
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
            <div class="otp-icon-wrapper text-center">
              <img src="../Images/SMS-OTP.png" alt="OTP Icon" class="otp-icon" />
            </div>

            <h1 class="mb-1 fs-2 text-center"><strong>Verify Your Number</strong></h1>
            <p class="text-center fs-6 text-muted mb-2">We only send an OTP after you confirm the request.</p>
            <p class="otp-text text-center" id="otpMessage">Ready to send an OTP to <strong>+63 •••••• XXXX</strong>.</p>

            <button type="button" id="sendOTPBtn" class="btn btn-outline-primary w-100">Send OTP</button>
            <p class="otp-helper-text text-center mb-0" id="otpHelperText">Tap Send OTP to receive the 6-digit verification code by SMS.</p>

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

            <div id="resetPasswordRequirements" class="password-reqs mb-2 is-hidden" aria-live="polite">
              <div class="password-reqs-title">Password must contain:</div>
              <ul class="password-reqs-list">
                <li data-req="uppercase">1 uppercase letter</li>
                <li data-req="lowercase">1 lowercase letter</li>
                <li data-req="number">1 number</li>
                <li data-req="special">1 special character</li>
                <li data-req="length">At least 8 characters</li>
              </ul>
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
    <script src="../JS-Script-Files/publicPagePrefetch.js?v=20260622-1"></script>
    <script src="../JS-Script-Files/siteContentRuntime.js" defer></script>

    <!-- ✅ NEW: SUCCESS MODAL (Bootstrap) -->
    <div class="modal fade" id="accountVerifiedModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="accountVerifiedModalTitle">Account Verified</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="accountVerifiedModalBody">Account verification successful.</div>
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
