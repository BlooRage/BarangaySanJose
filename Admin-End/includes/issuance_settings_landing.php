<?php
declare(strict_types=1);

$activeCertificates = 0;
foreach ((array)($issuanceSettings['certificates'] ?? []) as $certificateSetting) {
    if (!empty($certificateSetting['enabled'])) $activeCertificates++;
}
$activeNotifications = 0;
foreach ((array)($issuanceSettings['resident_notifications'] ?? []) as $notificationSetting) {
    if (!empty($notificationSetting['enabled'])) $activeNotifications++;
}
$activeOfficials = count(array_filter($governmentOfficialRows, static fn(array $row): bool => !empty($row['is_active'])));
$manageUrl = $documentSettingsActionUrl . '?view=manage';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barangay Issuance Settings</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css')) ?>">
  <style>
    .isl-title{font-family:'Charis SIL Bold',serif;color:#de710c}.isl-lead{max-width:800px;color:#6c757d}.isl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.isl-card{display:flex;flex-direction:column;min-height:230px;border:1px solid #dee2e6;border-radius:1rem;background:#fff;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);overflow:hidden;transition:transform .18s ease,box-shadow .18s ease}.isl-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.1)}.isl-card-body{display:flex;flex-direction:column;flex:1;padding:1.25rem}.isl-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:.65rem;background:#fff2e6;color:#a95305;margin-bottom:1rem}.isl-card-title{font-size:1.05rem;font-weight:700;color:#212529;margin-bottom:.35rem}.isl-card-copy{color:#6c757d;font-size:.875rem;margin-bottom:1rem}.isl-status{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 0;margin-top:auto;border-top:1px solid #e9ecef;font-size:.84rem}.isl-status-label{color:#6c757d}.isl-status-value{font-weight:700;color:#212529;text-align:right}.isl-link{display:flex;justify-content:space-between;align-items:center;padding:.85rem 1.25rem;border-top:1px solid #dee2e6;background:#f8f9fa;color:#a95305;text-decoration:none;font-size:.875rem;font-weight:600}.isl-link:hover{color:#7d3d00;background:#fff7ef}.isl-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-bottom:1.25rem}.isl-stat{padding:1rem;border:1px solid #dee2e6;border-radius:.75rem;background:#fff}.isl-stat-label{color:#6c757d;font-size:.78rem;margin-bottom:.25rem}.isl-stat-value{color:#212529;font-size:1rem;font-weight:700}@media(max-width:992px){.isl-overview{grid-template-columns:repeat(2,1fr)}}@media(max-width:768px){.isl-grid{grid-template-columns:1fr}}@media(max-width:480px){.isl-overview{grid-template-columns:1fr}}
  </style>
</head>
<body><div class="d-flex flex-column flex-md-row" style="min-height:100vh"><?php include __DIR__.'/sidebar.php'; ?>
<main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><h1 class="isl-title h2 mb-2">Barangay Issuance Settings</h1><p class="isl-lead mb-0">Choose a settings area to manage certificate requests, resident choices, notifications, fees, and the Indigency recipient directory.</p></div><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($documentSettingsBackUrl) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Back to Module</a></div>
  <hr class="mt-3 mb-4">
  <?php if ($documentSettingsSuccessMessage !== ''): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($documentSettingsSuccessMessage) ?></div><?php endif; ?>
  <?php if ($documentSettingsErrorMessage !== ''): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($documentSettingsErrorMessage) ?></div><?php endif; ?>
  <div class="isl-overview">
    <div class="isl-stat"><div class="isl-stat-label">Online requests</div><div class="isl-stat-value"><?= !empty($issuanceSettings['online_requests_enabled'])?'Enabled':'Suspended' ?></div></div>
    <div class="isl-stat"><div class="isl-stat-label">Active certificates</div><div class="isl-stat-value"><?= $activeCertificates ?> of <?= count(dms_issuance_certificate_catalog()) ?></div></div>
    <div class="isl-stat"><div class="isl-stat-label">Default validity</div><div class="isl-stat-value"><?= (int)$issuanceSettings['default_validity_days'] ?> days</div></div>
    <div class="isl-stat"><div class="isl-stat-label">QR verification</div><div class="isl-stat-value"><?= !empty($issuanceSettings['qr_verification_enabled'])?'Enabled':'Disabled' ?></div></div>
  </div>
  <div class="isl-grid">
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-sliders"></i></span><h2 class="isl-card-title">General Issuance</h2><p class="isl-card-copy">Manage online availability, default and allowed validity periods, QR verification, privacy, and First-Time Job Seeker exemption.</p><div class="isl-status"><span class="isl-status-label">Current status</span><span class="isl-status-value"><?= !empty($issuanceSettings['online_requests_enabled'])?'Online requests enabled':'Online requests suspended' ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($manageUrl) ?>#general"><span>Open general settings</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-file-lines"></i></span><h2 class="isl-card-title">Certificates & Request Choices</h2><p class="isl-card-copy">Enable certificate types and customize the purpose options residents see on their request forms.</p><div class="isl-status"><span class="isl-status-label">Active certificates</span><span class="isl-status-value"><?= $activeCertificates ?> of <?= count(dms_issuance_certificate_catalog()) ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($manageUrl) ?>#certificates"><span>Manage certificates</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-user-tie"></i></span><h2 class="isl-card-title">Indigency Recipient Directory</h2><p class="isl-card-copy">Add, edit, deactivate, order, or remove the government officials available in Certificate of Indigency requests.</p><div class="isl-status"><span class="isl-status-label">Active officials</span><span class="isl-status-value"><?= $activeOfficials ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($manageUrl) ?>#indigency-officials"><span>Manage recipient directory</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-bell"></i></span><h2 class="isl-card-title">Notifications</h2><p class="isl-card-copy">Configure resident status messages and alerts for certificate requests that have remained pending too long.</p><div class="isl-status"><span class="isl-status-label">Active resident events</span><span class="isl-status-value"><?= $activeNotifications ?> of 5</span></div></div><a class="isl-link" href="<?= htmlspecialchars($manageUrl) ?>#notifications"><span>Manage notifications</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-peso-sign"></i></span><h2 class="isl-card-title">Fees & Exemptions</h2><p class="isl-card-copy">Review certificate fees, submit fee change requests, and manage fee-related issuance rules.</p><div class="isl-status"><span class="isl-status-label">First-Time Job Seeker</span><span class="isl-status-value"><?= !empty($issuanceSettings['first_time_job_seeker_exempt'])?'Exempt':'Standard fee rules' ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($documentSettingsFeeRequestsUrl) ?>"><span>Open fee management</span><i class="fa-solid fa-arrow-right"></i></a></article>
  </div>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
