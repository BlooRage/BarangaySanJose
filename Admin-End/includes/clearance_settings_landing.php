<?php
declare(strict_types=1);
$monitoringHead = (array)($documentSettingsRows['monitoring_head'] ?? []);
$monitoringHeadName = trim((string)($monitoringHead['name'] ?? '')) ?: 'Not configured';
$visibleFieldCount = count(array_filter($documentSettingsFieldVisibility));
$activeClearanceTypes = count(array_filter((array)($clearanceSettings['clearance_types'] ?? []), static fn(array $row): bool => !empty($row['enabled'])));
$activeNotifications = count(array_filter((array)($clearanceSettings['resident_notifications'] ?? []), static fn(array $row): bool => !empty($row['enabled'])));
$items = [
    ['General Clearance Settings', 'Manage online availability, validity, QR verification, privacy, letterhead, signatures, and generated PDF fields.', 'fa-sliders', appUrl('Admin-End/ClearanceGeneralSettings.php'), !empty($clearanceSettings['online_requests_enabled']) ? 'Online requests enabled' : 'Online requests suspended'],
    ['Clearance Types & Request Choices', 'Enable supported clearance forms and customize purpose choices shown to residents.', 'fa-stamp', appUrl('Admin-End/ClearanceTypeSettings.php'), $activeClearanceTypes . ' of ' . count(dms_clearance_type_catalog()) . ' active'],
    ['Signatories', 'Edit the Head, Monitoring & Collection Dept., manage displayed titles, and upload clearance signature files.', 'fa-file-signature', appUrl('Admin-End/ClearanceDocumentSettings.php#clearance-signatories'), $monitoringHeadName],
    ['Notifications', 'Configure resident status messages and alerts for clearance requests that remain pending too long.', 'fa-bell', appUrl('Admin-End/ClearanceNotificationSettings.php'), $activeNotifications . ' of 5 events active'],
    ['Fees & Change Requests', 'Submit clearance fee additions or price updates and review the current request queue.', 'fa-peso-sign', appUrl('Admin-End/Certificates/CertificateTracker.php?tab=fees&fee_scope=monitoring&filter_document=__clearances__'), 'Fee settings and requests'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Clearance Issuance Settings</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    :root{--cis-accent:#de710c}.cis-main{min-width:0;overflow-x:hidden}.cis-title{font-family:'Charis SIL Bold',serif;color:var(--cis-accent);margin-bottom:.3rem}.cis-panel{border:1px solid #dee2e6;border-radius:1rem;background:#fff;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);padding:1.25rem}.cis-panel-head{padding-bottom:.8rem;margin-bottom:.8rem;border-bottom:1px solid #edf0f3}.cis-list{display:grid;gap:.7rem}.cis-item{display:grid;grid-template-columns:2.7rem minmax(0,1fr) auto;align-items:center;gap:.9rem;padding:1rem;border:1px solid #e4e8ed;border-radius:.8rem;color:#212529;text-decoration:none;transition:.15s}.cis-item:hover,.cis-item:focus-visible{color:#212529;border-color:#f1b56d;background:#fffaf4;box-shadow:0 .25rem .75rem rgba(222,113,12,.08)}.cis-icon{display:grid;width:2.7rem;height:2.7rem;place-items:center;border-radius:.75rem;color:var(--cis-accent);background:#fff4e8}.cis-item-title{display:block;font-weight:700}.cis-copy{display:block;margin-top:.15rem;color:#6c757d;font-size:.9rem;line-height:1.35}.cis-status{display:block;margin-top:.35rem;color:#747b84;font-size:.78rem}.cis-arrow{color:#98a2b3}@media(max-width:575.98px){.cis-panel{padding:.85rem}.cis-item{grid-template-columns:2.5rem minmax(0,1fr)}.cis-arrow{display:none}}
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="cis-main flex-grow-1 p-3 p-md-4 bg-light" id="main-display">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div><h1 class="cis-title h2">Clearance Issuance Settings</h1><p class="mb-0 text-muted">Manage clearance documents, signatories, printing options, and fees.</p></div>
    </div>
    <hr class="mt-0 mb-4">
    <?php if ($documentSettingsSuccessMessage !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($documentSettingsSuccessMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($documentSettingsErrorMessage !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($documentSettingsErrorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="cis-panel">
      <div class="cis-panel-head"><h2 class="h5 mb-1">Clearance Issuance Configuration</h2><p class="text-muted mb-0">Choose the area you want to configure. Each area opens on its focused settings section.</p></div>
      <div class="cis-list">
        <?php foreach ($items as [$title, $copy, $icon, $url, $status]): ?>
          <a class="cis-item" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
            <span class="cis-icon"><i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <span><span class="cis-item-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span><span class="cis-copy"><?= htmlspecialchars($copy, ENT_QUOTES, 'UTF-8') ?></span><span class="cis-status"><i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></span>
            <i class="fa-solid fa-chevron-right cis-arrow"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
