<?php
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../PhpFiles/General/websiteMaintenance.php';
require_once __DIR__ . '/../PhpFiles/General/audit.php';

$websiteSettings = wms_load_settings(isset($conn) && $conn instanceof mysqli ? $conn : null);
$websiteSettingsUserId = trim((string)($_SESSION['user_id'] ?? ''));
$websiteSettingsRole = trim((string)($_SESSION['role'] ?? 'SuperAdmin'));
$websiteSettingsPath = appUrl('Admin-End/WebsiteSettings.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_website_settings') {
    verifyCsrfToken(false);

    $existingSettings = wms_load_settings(isset($conn) && $conn instanceof mysqli ? $conn : null);
    try {
        $savedSettings = wms_save_settings([
            'enabled' => isset($_POST['maintenance_enabled']),
            'message' => (string)($_POST['maintenance_message'] ?? ''),
            'subcopy' => (string)($_POST['maintenance_subcopy'] ?? ''),
            'registration_enabled' => isset($_POST['registration_enabled']),
            'registration_message' => (string)($_POST['registration_message'] ?? ''),
            'resident_timeout_minutes' => (int)($_POST['resident_timeout_minutes'] ?? 30),
            'admin_timeout_minutes' => (int)($_POST['admin_timeout_minutes'] ?? 30),
            'admin_2fa_enabled' => isset($_POST['admin_2fa_enabled']),
            'default_language' => (string)($_POST['default_language'] ?? 'en'),
            'default_font_scale' => (string)($_POST['default_font_scale'] ?? '100'),
            'high_contrast' => isset($_POST['high_contrast']),
            'reduced_motion' => isset($_POST['reduced_motion']),
        ], $websiteSettingsUserId, isset($conn) && $conn instanceof mysqli ? $conn : null);

        if (isset($conn) && $conn instanceof mysqli) {
            insertUnifiedAuditLog(
                $conn,
                $websiteSettingsUserId !== '' ? $websiteSettingsUserId : null,
                $websiteSettingsRole !== '' ? $websiteSettingsRole : 'SuperAdmin',
                'website_settings',
                'website_configuration',
                'public_site',
                'update_website_settings',
                'website_configuration',
                json_encode($existingSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($savedSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Updated website maintenance, registration, security, language, and accessibility settings.'
            );
        }

        $successMessage = 'Website settings saved successfully.';

        header('Location: ' . $websiteSettingsPath . '?success=' . rawurlencode($successMessage));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $websiteSettingsPath . '?error=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$websiteSettings = wms_load_settings(isset($conn) && $conn instanceof mysqli ? $conn : null);
$websiteSettingsEnabled = !empty($websiteSettings['enabled']);
$websiteSettingsUpdatedBy = trim((string)($websiteSettings['updated_by_user_id'] ?? ''));
$websiteSettingsUpdatedAt = trim((string)($websiteSettings['updated_at'] ?? ''));
$websiteSettingsUpdatedAtLabel = '-';
if ($websiteSettingsUpdatedAt !== '') {
    $ts = strtotime($websiteSettingsUpdatedAt);
    if ($ts !== false) {
        $websiteSettingsUpdatedAtLabel = date('M j, Y g:i A', $ts);
    } else {
        $websiteSettingsUpdatedAtLabel = $websiteSettingsUpdatedAt;
    }
}

$websiteSuccess = trim((string)($_GET['success'] ?? ''));
$websiteError = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Website Settings</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    .website-settings-shell {
      border-color: #f1e1cf !important;
    }
    .website-settings-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      margin-bottom: 0.4rem;
    }
    .website-settings-lead {
      color: #5b6470;
      max-width: 760px;
    }
    .website-settings-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
      gap: 1.25rem;
      align-items: start;
    }
    .website-settings-card {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.04);
      padding: 1.25rem;
    }
    .website-settings-status {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      padding: 0.55rem 0.85rem;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.92rem;
      border: 1px solid transparent;
    }
    .website-settings-status.is-live {
      color: #166534;
      background: #dcfce7;
      border-color: #86efac;
    }
    .website-settings-status.is-maintenance {
      color: #991b1b;
      background: #fee2e2;
      border-color: #fca5a5;
    }
    .website-settings-meta {
      display: grid;
      gap: 0.85rem;
    }
    .website-settings-meta-item {
      border: 1px solid #edf1f5;
      border-radius: 14px;
      padding: 0.9rem 1rem;
      background: #fbfcfe;
    }
    .website-settings-meta-label {
      margin-bottom: 0.25rem;
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #667085;
    }
    .website-settings-meta-value {
      font-size: 0.96rem;
      color: #1f2937;
      word-break: break-word;
    }
    .website-settings-toggle {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      border: 1px solid #eceff3;
      border-radius: 16px;
      padding: 1rem;
      background: #fffaf4;
    }
    .website-settings-toggle .form-check-input {
      width: 3rem;
      height: 1.6rem;
      margin-top: 0.1rem;
    }
    .website-settings-checklist {
      margin: 0;
      padding-left: 1.1rem;
      color: #475467;
      display: grid;
      gap: 0.45rem;
    }
    .website-settings-preview {
      border: 1px dashed #d8dee6;
      border-radius: 16px;
      padding: 1rem;
      background: #f8fafc;
    }
    .website-settings-preview-copy {
      color: #475467;
      line-height: 1.65;
      margin-bottom: 0.75rem;
    }
    .website-settings-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
    }
    @media (max-width: 991.98px) {
      .website-settings-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 767.98px) {
      .website-settings-card {
        padding: 1rem;
      }
      .website-settings-toggle {
        flex-direction: column;
      }
      .website-settings-actions {
        flex-direction: column-reverse;
      }
      .website-settings-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <h2 class="website-settings-title">Website Settings</h2>
      <p class="website-settings-lead mb-3">
        Control whether the public-facing website stays open or redirects visitors to the maintenance page. Admin pages remain accessible so you can continue internal work while the public side is locked.
      </p>
      <hr class="mt-0 mb-4">

      <?php if ($websiteSuccess !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($websiteSuccess, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($websiteError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($websiteError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="bg-white rounded-4 shadow-sm border p-4 website-settings-shell">
        <div class="website-settings-grid">
          <section class="website-settings-card">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
              <div>
                <h3 class="h5 mb-2">Maintenance Mode</h3>
                <p class="text-muted mb-0">Switch the front-facing site into maintenance mode whenever updates, hotfixes, or content work need a quiet window.</p>
              </div>
              <span class="website-settings-status <?= $websiteSettingsEnabled ? 'is-maintenance' : 'is-live' ?>">
                <i class="fa-solid <?= $websiteSettingsEnabled ? 'fa-lock' : 'fa-earth-asia' ?>"></i>
                <?= $websiteSettingsEnabled ? 'Maintenance Active' : 'Website Live' ?>
              </span>
            </div>

            <form method="post" action="<?= htmlspecialchars($websiteSettingsPath, ENT_QUOTES, 'UTF-8') ?>">
              <?= csrfTokenField() ?>
              <input type="hidden" name="action" value="save_website_settings">

              <div class="website-settings-toggle mb-4">
                <div>
                  <h4 class="h6 mb-1">Lock the public and resident-facing website</h4>
                  <p class="text-muted mb-0">When enabled, visitors are redirected to the maintenance page instead of the normal site pages.</p>
                </div>
                <div class="form-check form-switch m-0">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="maintenanceEnabled"
                    name="maintenance_enabled"
                    <?= $websiteSettingsEnabled ? 'checked' : '' ?>>
                </div>
              </div>

              <div class="mb-3">
                <label for="maintenanceMessage" class="form-label fw-semibold">Main Message</label>
                <textarea
                  class="form-control"
                  id="maintenanceMessage"
                  name="maintenance_message"
                  rows="4"
                  maxlength="600"
                  placeholder="Tell visitors what is happening."><?= htmlspecialchars((string)($websiteSettings['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

              <div class="mb-4">
                <label for="maintenanceSubcopy" class="form-label fw-semibold">Secondary Message</label>
                <textarea
                  class="form-control"
                  id="maintenanceSubcopy"
                  name="maintenance_subcopy"
                  rows="3"
                  maxlength="400"
                  placeholder="Optional follow-up note for visitors."><?= htmlspecialchars((string)($websiteSettings['subcopy'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

              <div class="website-settings-preview mb-4">
                <div class="small text-uppercase fw-bold text-muted mb-2">Maintenance Page Preview Copy</div>
                <p class="website-settings-preview-copy mb-2" id="maintenancePreviewMessage">
                  <?= htmlspecialchars((string)($websiteSettings['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="website-settings-preview-copy mb-0 text-secondary" id="maintenancePreviewSubcopy">
                  <?= htmlspecialchars((string)($websiteSettings['subcopy'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>

              <hr class="my-4">
              <h3 class="h5 mb-3">Resident Registration</h3>
              <div class="website-settings-toggle mb-3">
                <div><h4 class="h6 mb-1">Allow online registration</h4><p class="text-muted mb-0">Turn this off to prevent new resident accounts while keeping login available.</p></div>
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="registration_enabled" <?= !empty($websiteSettings['registration_enabled']) ? 'checked' : '' ?>></div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold" for="registrationMessage">Closed-registration message</label>
                <textarea class="form-control" id="registrationMessage" name="registration_message" rows="2" maxlength="400"><?= htmlspecialchars((string)($websiteSettings['registration_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>

              <hr class="my-4">
              <h3 class="h5 mb-3">Security</h3>
              <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Resident inactivity timeout</label><div class="input-group"><input class="form-control" type="number" name="resident_timeout_minutes" min="5" max="240" value="<?= (int)($websiteSettings['resident_timeout_minutes'] ?? 30) ?>"><span class="input-group-text">minutes</span></div></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Admin inactivity timeout</label><div class="input-group"><input class="form-control" type="number" name="admin_timeout_minutes" min="5" max="120" value="<?= (int)($websiteSettings['admin_timeout_minutes'] ?? 30) ?>"><span class="input-group-text">minutes</span></div></div>
              </div>
              <div class="website-settings-toggle mb-4">
                <div><h4 class="h6 mb-1">Require administrator two-factor authentication</h4><p class="text-muted mb-0">Require an OTP after a valid password for administrator-side accounts.</p></div>
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="admin_2fa_enabled" <?= !empty($websiteSettings['admin_2fa_enabled']) ? 'checked' : '' ?>></div>
              </div>

              <hr class="my-4">
              <h3 class="h5 mb-3">Language &amp; Accessibility</h3>
              <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Default language</label><select class="form-select" name="default_language"><option value="en" <?= ($websiteSettings['default_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option><option value="fil" <?= ($websiteSettings['default_language'] ?? '') === 'fil' ? 'selected' : '' ?>>Filipino</option></select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Default text size</label><select class="form-select" name="default_font_scale"><?php foreach (['90'=>'Small (90%)','100'=>'Standard (100%)','110'=>'Large (110%)','120'=>'Extra large (120%)'] as $value=>$label): ?><option value="<?= $value ?>" <?= (string)($websiteSettings['default_font_scale'] ?? '100') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="high_contrast" id="highContrast" <?= !empty($websiteSettings['high_contrast']) ? 'checked' : '' ?>><label class="form-check-label" for="highContrast">Use high-contrast colors by default</label></div>
              <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="reduced_motion" id="reducedMotion" <?= !empty($websiteSettings['reduced_motion']) ? 'checked' : '' ?>><label class="form-check-label" for="reducedMotion">Reduce animations and transitions by default</label></div>

              <div class="website-settings-actions">
                <button type="submit" class="btn btn-primary">
                  Save Website Settings
                </button>
              </div>
            </form>
          </section>

          <aside class="website-settings-meta">
            <section class="website-settings-card">
              <h3 class="h6 mb-3">Current Status</h3>
              <div class="website-settings-meta">
                <div class="website-settings-meta-item">
                  <div class="website-settings-meta-label">Mode</div>
                  <div class="website-settings-meta-value"><?= $websiteSettingsEnabled ? 'Maintenance mode is enabled.' : 'Website is currently open to visitors.' ?></div>
                </div>
                <div class="website-settings-meta-item">
                  <div class="website-settings-meta-label">Last Updated By</div>
                  <div class="website-settings-meta-value"><?= $websiteSettingsUpdatedBy !== '' ? htmlspecialchars($websiteSettingsUpdatedBy, ENT_QUOTES, 'UTF-8') : '-' ?></div>
                </div>
                <div class="website-settings-meta-item">
                  <div class="website-settings-meta-label">Last Updated At</div>
                  <div class="website-settings-meta-value"><?= htmlspecialchars($websiteSettingsUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>
            </section>

            <section class="website-settings-card">
              <h3 class="h6 mb-3">What Stays Accessible</h3>
              <ul class="website-settings-checklist">
                <li>Admin pages under <code>Admin-End</code></li>
                <li>Admin-side processing endpoints</li>
                <li>Login, logout, and account redirect routes</li>
                <li>The direct maintenance page route at <code>/maintenance</code></li>
              </ul>
            </section>

            <section class="website-settings-card">
              <h3 class="h6 mb-3">What Gets Locked</h3>
              <ul class="website-settings-checklist">
                <li>Homepage and public guest pages</li>
                <li>Resident-facing pages under <code>Resident-End</code></li>
                <li>Extensionless public routes like <code>/services</code> and <code>/contact</code></li>
              </ul>
            </section>
          </aside>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      const messageInput = document.getElementById("maintenanceMessage");
      const subcopyInput = document.getElementById("maintenanceSubcopy");
      const messagePreview = document.getElementById("maintenancePreviewMessage");
      const subcopyPreview = document.getElementById("maintenancePreviewSubcopy");

      const syncPreview = () => {
        if (messagePreview && messageInput) {
          const value = messageInput.value.trim();
          messagePreview.textContent = value !== "" ? value : "Our developers are currently upgrading the system to deliver a smoother, faster, and better experience for everyone.";
        }
        if (subcopyPreview && subcopyInput) {
          const value = subcopyInput.value.trim();
          subcopyPreview.textContent = value !== "" ? value : "The public pages will be available again once the improvements are complete.";
        }
      };

      messageInput?.addEventListener("input", syncPreview);
      subcopyInput?.addEventListener("input", syncPreview);
      syncPreview();
    })();
  </script>
</body>
</html>
