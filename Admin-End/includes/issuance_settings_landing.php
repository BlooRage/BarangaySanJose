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
$sectionUrls=[
 'general'=>appUrl('Admin-End/Certificates/IssuanceGeneralSettings.php'),
 'certificates'=>appUrl('Admin-End/Certificates/IssuanceCertificateSettings.php'),
 'indigency'=>appUrl('Admin-End/Certificates/IndigencyRecipientSettings.php'),
 'notifications'=>appUrl('Admin-End/Certificates/IssuanceNotificationSettings.php'),
 'fees'=>appUrl('Admin-End/Certificates/IssuanceFeeSettings.php'),
];
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
    .isl-title{font-family:'Charis SIL Bold',serif;color:#de710c}.isl-lead{max-width:780px;color:#6c757d}.isl-eyebrow{display:inline-flex;align-items:center;gap:.45rem;margin-bottom:.45rem;color:#a95305;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.isl-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.isl-card{position:relative;display:flex;flex-direction:column;min-height:190px;border:1px solid #e1e4e8;border-radius:.9rem;background:#fff;box-shadow:0 .125rem .35rem rgba(24,32,43,.055);overflow:hidden;transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease}.isl-card:before{content:"";position:absolute;inset:0 auto 0 0;width:3px;background:#de710c;opacity:0;transition:opacity .18s}.isl-card:hover{transform:translateY(-2px);border-color:#e8c5a4;box-shadow:0 .55rem 1.25rem rgba(35,30,25,.09)}.isl-card:hover:before{opacity:1}.isl-card-body{display:grid;grid-template-columns:42px minmax(0,1fr);grid-template-rows:auto 1fr auto;column-gap:.85rem;flex:1;padding:1rem}.isl-icon{grid-row:1/3;width:42px;height:42px;display:grid;place-items:center;border-radius:.65rem;background:#fff1e4;color:#aa5204}.isl-card-title{align-self:center;font-size:.98rem;font-weight:700;color:#212529;margin:0}.isl-card-copy{grid-column:2;color:#6c757d;font-size:.82rem;line-height:1.45;margin:.55rem 0 .8rem}.isl-status{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:auto;padding-top:.7rem;border-top:1px solid #edf0f2;font-size:.78rem}.isl-status-label{color:#7a828c}.isl-status-value{display:inline-flex;align-items:center;padding:.25rem .5rem;border-radius:999px;background:#f1f3f5;color:#343a40;font-weight:700;text-align:right}.isl-link{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1rem;border-top:1px solid #e5e8eb;background:#fbfbfc;color:#a95305;text-decoration:none;font-size:.81rem;font-weight:700}.isl-link:hover{color:#7d3d00;background:#fff7ef}.isl-card--wide{grid-column:span 2}@media(max-width:1200px){.isl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.isl-card--wide{grid-column:auto}}@media(max-width:768px){.isl-grid{grid-template-columns:1fr}}
  </style>
</head>
<body><div class="d-flex flex-column flex-md-row" style="min-height:100vh"><?php include __DIR__.'/sidebar.php'; ?>
<main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><span class="isl-eyebrow"><i class="fa-solid fa-gear"></i>Configuration center</span><h1 class="isl-title h2 mb-2">Barangay Issuance Settings</h1><p class="isl-lead mb-0">Manage how certificates are requested, processed, verified, and communicated to residents.</p></div><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($documentSettingsBackUrl) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Back to Module</a></div>
  <hr class="mt-3 mb-4">
  <?php if ($documentSettingsSuccessMessage !== ''): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($documentSettingsSuccessMessage) ?></div><?php endif; ?>
  <?php if ($documentSettingsErrorMessage !== ''): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($documentSettingsErrorMessage) ?></div><?php endif; ?>
  <div class="isl-grid">
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-sliders"></i></span><h2 class="isl-card-title">General Issuance</h2><p class="isl-card-copy">Manage online availability, default and allowed validity periods, QR verification, privacy, and First-Time Job Seeker exemption.</p><div class="isl-status"><span class="isl-status-label">Current status</span><span class="isl-status-value"><?= !empty($issuanceSettings['online_requests_enabled'])?'Online requests enabled':'Online requests suspended' ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($sectionUrls['general']) ?>"><span>Open general settings</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-file-lines"></i></span><h2 class="isl-card-title">Certificates & Request Choices</h2><p class="isl-card-copy">Enable certificate types and customize the purpose options residents see on their request forms.</p><div class="isl-status"><span class="isl-status-label">Active certificates</span><span class="isl-status-value"><?= $activeCertificates ?> of <?= count(dms_issuance_certificate_catalog()) ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($sectionUrls['certificates']) ?>"><span>Manage certificates</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-user-tie"></i></span><h2 class="isl-card-title">Indigency Recipient Directory</h2><p class="isl-card-copy">Add, edit, deactivate, order, or remove the government officials available in Certificate of Indigency requests.</p><div class="isl-status"><span class="isl-status-label">Active officials</span><span class="isl-status-value"><?= $activeOfficials ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($sectionUrls['indigency']) ?>"><span>Manage recipient directory</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-bell"></i></span><h2 class="isl-card-title">Notifications</h2><p class="isl-card-copy">Configure resident status messages and alerts for certificate requests that have remained pending too long.</p><div class="isl-status"><span class="isl-status-label">Active resident events</span><span class="isl-status-value"><?= $activeNotifications ?> of 5</span></div></div><a class="isl-link" href="<?= htmlspecialchars($sectionUrls['notifications']) ?>"><span>Manage notifications</span><i class="fa-solid fa-arrow-right"></i></a></article>
    <article class="isl-card isl-card--wide"><div class="isl-card-body"><span class="isl-icon"><i class="fa-solid fa-peso-sign"></i></span><h2 class="isl-card-title">Fees & Exemptions</h2><p class="isl-card-copy">Review certificate fees, submit fee change requests, and manage fee-related issuance rules.</p><div class="isl-status"><span class="isl-status-label">First-Time Job Seeker</span><span class="isl-status-value"><?= !empty($issuanceSettings['first_time_job_seeker_exempt'])?'Exempt':'Standard fee rules' ?></span></div></div><a class="isl-link" href="<?= htmlspecialchars($sectionUrls['fees']) ?>"><span>Open fee management</span><i class="fa-solid fa-arrow-right"></i></a></article>
  </div>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
