<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';

$certificateLaunchTab = strtolower(trim((string)($_GET['tab'] ?? '')));
$certificateLaunchDocument = strtolower(trim((string)($_GET['document'] ?? '')));
$certificateLaunchStage = strtolower(trim((string)($_GET['stage'] ?? '')));
$certificateLaunchEntry = strtolower(trim((string)($_GET['entry'] ?? '')));
$certificateLaunchFilterDocument = strtolower(trim((string)($_GET['filter_document'] ?? '')));
$certificateFeeScope = strtolower(trim((string)($_GET['fee_scope'] ?? '')));
$isFeeSettingsView = $certificateLaunchTab === 'fees';
$isBarangayIdManualLaunch = $certificateLaunchTab === 'manual' && $certificateLaunchDocument === 'barangay_id';
$isIdIssuanceTrackerView = $certificateLaunchEntry === 'id_issuance' || $isBarangayIdManualLaunch;
$certificateTrackerHeading = $isIdIssuanceTrackerView
  ? 'Barangay ID Issuance'
  : ($isFeeSettingsView
      ? ($certificateFeeScope === 'monitoring' ? 'Clearance Fee Change Requests' : 'Certificate Fee Change Requests')
      : ($certificateLaunchFilterDocument === '__clearances__'
      ? 'Clearance Issuance'
      : 'Certificate Issuance'));
$certificateLaunchFilterToken = strtolower(trim($certificateLaunchFilterDocument));
$certificateSettingsHref = appUrl('Admin-End/Certificates/CertificateIssuanceSettings.php');
$certificateSettingsLabel = 'Issuance Settings';
if ($isIdIssuanceTrackerView) {
  $certificateSettingsHref = appUrl('Admin-End/Certificates/BarangayIdSettings.php');
  $certificateSettingsLabel = 'Barangay ID Settings';
} elseif (
  $certificateLaunchFilterToken === '__clearances__'
  || str_contains($certificateLaunchFilterToken, 'clearance')
  || str_contains($certificateLaunchFilterToken, 'business')
) {
  $certificateSettingsHref = appUrl('Admin-End/BusinessMonitoringSettings.php');
  $certificateSettingsLabel = 'Monitoring Settings';
}
if ($isFeeSettingsView) {
  $certificateSettingsHref = $certificateFeeScope === 'monitoring'
    ? appUrl('Admin-End/BusinessMonitoringSettings.php')
    : appUrl('Admin-End/Certificates/CertificateIssuanceSettings.php');
  $certificateSettingsLabel = 'Back to Settings';
}
$barangayIdAdminNavActive = 'applications';
$barangayIdOperationalSettings = dms_resolve_barangay_id_operational_settings($conn);
$issuanceOperationalSettings = dms_resolve_issuance_settings($conn);
$clearanceOperationalSettings = dms_resolve_clearance_settings($conn);
$agingAlertScope = ($certificateLaunchFilterToken === '__clearances__' || str_contains($certificateLaunchFilterToken, 'clearance')) ? 'clearance' : 'issuance';
$issuanceAgingAlert = !$isFeeSettingsView && !$isIdIssuanceTrackerView
  ? dms_build_aging_alert($conn, $agingAlertScope, trim((string)($_SESSION['user_id'] ?? '')))
  : null;
$manualGovernmentPositionOptions = [];
$manualGovernmentOfficials = [];
$manualGovernmentOfficialGroups = [
  ['id' => 'president', 'name' => 'President'],
  ['id' => 'vice_president', 'name' => 'Vice President'],
  ['id' => 'senate', 'name' => 'Senate'],
  ['id' => 'rizal_officials', 'name' => 'Rizal Officials'],
  ['id' => 'municipal_officials', 'name' => 'Rodriguez Municipal Officials'],
];
if (isset($conn) && $conn instanceof mysqli) {
  dms_ensure_government_official_dropdown_table($conn);
  $positionRes = $conn->query("
    SELECT DISTINCT position_name
    FROM governmentofficialdropdowntbl
    WHERE is_active = 1
    ORDER BY position_name ASC
  ");
  if ($positionRes instanceof mysqli_result) {
    while ($row = $positionRes->fetch_assoc()) {
      $manualGovernmentPositionOptions[] = (string)($row['position_name'] ?? '');
    }
    $positionRes->free();
  }
  $officialRes = $conn->query("
    SELECT government_official_id, official_name, position_name, jurisdiction_location, group_key
    FROM governmentofficialdropdowntbl
    WHERE is_active = 1
    ORDER BY display_order ASC, official_name ASC
  ");
  if ($officialRes instanceof mysqli_result) {
    while ($row = $officialRes->fetch_assoc()) {
      $manualGovernmentOfficials[] = [
        'id' => (string)($row['government_official_id'] ?? ''),
        'name' => (string)($row['official_name'] ?? ''),
        'position_name' => (string)($row['position_name'] ?? ''),
        'jurisdiction_location' => (string)($row['jurisdiction_location'] ?? ''),
        'group_key' => (string)($row['group_key'] ?? ''),
      ];
    }
    $officialRes->free();
  }
}

if ($certificateLaunchStage === 'release') {
  $barangayIdAdminNavActive = 'release';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <title>Certificate Tracker</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/barangayIdAdminNav.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260720-status-pill-consistency">
  <style>
    .certificate-tracker-shell {
      max-width: var(--admin-table-shell-max-width);
      width: 100%;
      min-width: 0;
      margin: 0 auto;
    }
    #certTrackerPageTabs {
      max-width: var(--admin-table-shell-max-width);
      margin: 0 auto -1px;
      padding-left: 0;
      border-bottom: 0;
      position: relative;
      z-index: 2;
      gap: 0.15rem;
    }
    body.admin-sidebar-collapsed .certificate-tracker-shell,
    body.admin-sidebar-collapsed #certTrackerPageTabs {
      max-width: var(--admin-table-shell-max-width-collapsed);
    }
    #certTrackerPageTabs .nav-link {
      color: #d76f12;
      font-weight: 600;
      border: 1px solid transparent;
      border-bottom-color: transparent;
      border-top-left-radius: 0.75rem;
      border-top-right-radius: 0.75rem;
      padding: 0.75rem 1rem;
      background: transparent;
    }
    #certTrackerPageTabs .nav-link:hover,
    #certTrackerPageTabs .nav-link:focus-visible {
      color: #b45309;
      border-color: transparent;
    }
    #certTrackerPageTabs .nav-link.active,
    #certTrackerPageTabs .nav-link.active:hover,
    #certTrackerPageTabs .nav-link.active:focus-visible {
      color: #d76f12;
      background: #ffffff;
      border-color: #dee2e6;
      border-bottom-color: #ffffff;
      box-shadow: none;
    }
    #docRequestsPanel,
    #manualIssuancePanel,
    #feeChangePanel {
      border-top-left-radius: 0 !important;
    }
    body.fee-settings-view #feeChangePanel {
      border-top-left-radius: 1rem !important;
    }
    .certificate-tracker-shell .stage-filter-btn {
      position: relative;
    }
    .certificate-tracker-shell .stage-filter-btn .tab-count {
      position: absolute;
      top: -7px;
      right: -7px;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: #dc3545;
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
      box-shadow: none;
    }
    #feeChangeSubTabs .nav-link {
      color: #d76f12 !important;
      border: 1px solid transparent;
      border-bottom-color: transparent;
      border-radius: .75rem .75rem 0 0;
      font-weight: 600;
      padding: .75rem 1rem;
      margin-bottom: -1px;
      background: transparent;
    }
    #feeChangeSubTabs .nav-link:hover,
    #feeChangeSubTabs .nav-link:focus-visible {
      color: #b45309 !important;
      border-color: transparent;
    }
    #feeChangeSubTabs .nav-link.active,
    #feeChangeSubTabs .nav-link.active:focus,
    #feeChangeSubTabs .nav-link.active:hover,
    #feeChangeSubTabs .nav-link.active i,
    #feeChangeSubTabs .nav-link.active:focus i,
    #feeChangeSubTabs .nav-link.active:hover i {
      color: #d76f12 !important;
      background: #fff !important;
      border-color: #dee2e6 !important;
      border-bottom-color: #fff !important;
      box-shadow: none;
    }
    body.fee-settings-view #feeChangePanel {
      padding: 0 !important;
      overflow: hidden;
    }
    body.fee-settings-view #feeChangeSubTabs {
      gap: .15rem;
      padding: 1rem 1.25rem 0;
      margin-bottom: 0 !important;
      border-bottom: 1px solid #dee2e6;
      background: #fff;
    }
    body.fee-settings-view #fcrAddPanel,
    body.fee-settings-view #fcrEditPanel,
    body.fee-settings-view #fcrListPanel {
      padding: 1.25rem;
    }
    body.fee-settings-view #fcrAddPanel .col-lg-6 {
      width: 100%;
      max-width: 680px;
    }
    body.fee-settings-view #fcrAddPanel .border {
      background: #fff !important;
      border-color: #dee2e6 !important;
    }
    body.fee-settings-view #fcrEditCatalogBody .btn-warning,
    body.fee-settings-view #fcrEditSubmitBtn {
      color: #fff;
      background: #de710c;
      border-color: #de710c;
    }
    body.fee-settings-view #fcrEditCatalogBody .btn-warning:hover,
    body.fee-settings-view #fcrEditSubmitBtn:hover {
      background: #a95305;
      border-color: #a95305;
    }
    .certificate-tracker-shell .tracker-doc-filter {
      min-width: 220px;
      max-width: 240px;
    }
    .certificate-tracker-shell .table-responsive {
      width: 100%;
      max-width: 100%;
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 8px;
      scrollbar-gutter: stable;
    }
    #docRequestsPanel {
      max-width: 100%;
      min-width: 0;
      overflow-x: hidden;
    }
    #docRequestsPanel .compact-admin-table-shell {
      width: 100%;
      max-width: 100%;
    }
    .certificate-tracker-shell .fee-catalog-table-shell {
      border: 1px solid #eceff3;
      border-radius: 8px;
      background: #fff;
      overflow: hidden;
      padding-bottom: 0;
    }
    .certificate-tracker-shell .fee-catalog-table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    .certificate-tracker-shell .fee-catalog-table thead th {
      padding: 0.68rem 0.9rem;
      font-size: 0.96rem;
    }
    .certificate-tracker-shell .fee-catalog-table tbody td {
      padding: 0.56rem 0.9rem;
      font-size: 0.96rem;
      vertical-align: middle;
    }
    .certificate-tracker-shell #feeTaggingRows tr[data-fee-row] td[data-fee-toggle] {
      cursor: pointer;
    }
    .certificate-tracker-shell #feeTaggingRows tr[data-fee-row]:hover td[data-fee-toggle] {
      background: #f8fbff;
    }
    .certificate-tracker-shell #feeTaggingRows tr[data-fee-row] input,
    .certificate-tracker-shell #feeTaggingRows tr[data-fee-row] button {
      cursor: auto;
    }
    .certificate-tracker-shell .compact-admin-table .compact-table-btn.btn-danger {
      color: #fff;
      border-color: #dc3545;
      background: #dc3545;
      font-weight: 400 !important;
      letter-spacing: 0.15px;
    }
    .certificate-tracker-shell .compact-admin-table .compact-table-btn.btn-danger:hover {
      color: #fff;
      border-color: #bb2d3b;
      background: #bb2d3b;
    }
    @media (max-width: 767.98px) {
      #certTrackerPageTabs {
        margin-bottom: 0.75rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
      }
      #certTrackerPageTabs .nav-item {
        flex: 0 0 auto;
      }
      #certTrackerPageTabs .nav-link {
        white-space: nowrap;
      }
    }
    #table-certificateTracker {
      table-layout: fixed;
      width: 100%;
      min-width: 0;
    }
    #table-certificateTracker th,
    #table-certificateTracker td {
      vertical-align: middle;
      padding: 0.68rem 0.72rem;
      overflow-wrap: anywhere;
    }
    #table-certificateTracker .col-request-id,
    #table-certificateTracker .col-resident-id {
      width: 10%;
      white-space: nowrap;
    }
    #table-certificateTracker .col-status {
      width: 13%;
      min-width: 0;
      white-space: normal;
    }
    #table-certificateTracker .col-submitted {
      width: 13%;
      min-width: 0;
      white-space: normal;
    }
    #table-certificateTracker .col-action {
      width: 12%;
      min-width: 0;
      white-space: normal;
    }
    #table-certificateTracker .col-full-name,
    #table-certificateTracker .col-document,
    #table-certificateTracker .col-purpose {
      width: 14%;
    }
    #table-certificateTracker .col-document {
      width: 13%;
    }
    #table-certificateTracker .col-purpose {
      width: 15%;
    }
    #table-certificateTracker .cell-truncate {
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }
    #table-certificateTracker td.col-purpose-cell {
      white-space: normal;
      vertical-align: top;
    }
    #table-certificateTracker .cell-purpose {
      display: block;
      white-space: normal;
      word-break: break-word;
      line-height: 1.35;
    }
    #table-certificateTracker td.col-status-cell {
      white-space: normal;
      vertical-align: top;
    }
    #table-certificateTracker td:last-child {
      text-align: left;
      white-space: normal;
    }
    #table-certificateTracker td:last-child .btn,
    #table-certificateTracker td:last-child .compact-table-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
      padding-inline: 0.55rem;
      margin-bottom: 0.2rem;
      white-space: normal;
      line-height: 1.15;
    }
    body.id-issuance-view #table-certificateTracker .id-issuance-table-actions {
      display: flex;
      flex-direction: row;
      align-items: center;
      flex-wrap: nowrap;
      gap: 0.35rem;
    }
    body.id-issuance-view #table-certificateTracker .id-issuance-table-actions .btn {
      width: auto;
      margin: 0 !important;
      white-space: nowrap;
    }
    @media (min-width: 992px) {
      #docRequestsPanel .compact-admin-table-shell {
        overflow-x: hidden !important;
        padding-bottom: 0;
        scrollbar-gutter: auto;
      }
      body.id-issuance-view #docRequestsPanel .compact-admin-table-shell {
        display: block;
        width: 100%;
        max-width: 100%;
        overflow-x: clip !important;
      }
      body.id-issuance-view #table-certificateTracker {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
      }
      body.id-issuance-view #table-certificateTracker th,
      body.id-issuance-view #table-certificateTracker td {
        min-width: 0 !important;
        max-width: none !important;
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: clamp(0.78rem, 0.72rem + 0.18vw, 0.95rem);
      }
      body.id-issuance-view #table-certificateTracker th:nth-child(1) { width: 16% !important; }
      body.id-issuance-view #table-certificateTracker th:nth-child(3) { width: 20% !important; }
      body.id-issuance-view #table-certificateTracker th:nth-child(4) { width: 18% !important; }
      body.id-issuance-view #table-certificateTracker th:nth-child(6) { width: 15% !important; }
      body.id-issuance-view #table-certificateTracker th:nth-child(7) { width: 17% !important; }
      body.id-issuance-view #table-certificateTracker th:nth-child(8) { width: 14% !important; }
      body.id-issuance-view #table-certificateTracker th:last-child,
      body.id-issuance-view #table-certificateTracker td:last-child {
        overflow: visible;
        text-overflow: clip;
      }
      body.id-issuance-view #docRequestsPanel .compact-admin-table-shell::-webkit-scrollbar {
        display: none;
      }
    }
    @media (max-width: 991.98px) {
      #table-certificateTracker {
        min-width: 900px;
      }
    }
    :is(#viewModal, #manualDocumentInlinePreview) .modal-dialog {
      width: 75vw;
      max-width: 1500px;
      height: 88vh;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .modal-content {
      border: 0;
      border-radius: .5rem;
      padding: 1rem;
      overflow: hidden;
      height: 100%;
      background: #ffffff;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .modal-header {
      border-bottom: 1px solid #e9ecef;
      background: #ffffff;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-view {
      display: grid;
      gap: 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .modal-body {
      overflow-y: auto;
      overflow-x: hidden;
      min-height: 0;
      background: #ffffff;
      padding: 14px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-doc-highlight {
      border: 1px solid #bfdbfe;
      background: #dbeafe;
      color: #1e3a8a;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-section {
      border: 1px solid #e78924;
      background: #ffffff;
      border-radius: 12px;
      padding: 12px;
      margin-top: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-section-title {
      margin: 0 0 10px;
      font-size: 1rem;
      font-weight: 700;
      color: #212529;
      border-bottom: 1px dashed #e9ecef;
      padding-bottom: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-subsection {
      display: grid;
      gap: 10px;
      padding: 12px;
      border: 1px solid #edf1f5;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-subsection + .tracker-form-subsection {
      margin-top: 4px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-subsection-title {
      margin: 0;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      color: #334155;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-field--wide {
      grid-column: 1 / -1;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-label {
      margin: 6px 0 0;
      font-size: .76rem;
      color: #6b7280;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-value {
      min-height: 38px;
      border: 1px solid #dbe0e6;
      border-radius: 8px;
      background: #f8fafc;
      padding: 8px 10px;
      font-size: .92rem;
      color: #111827;
      font-weight: 500;
      word-break: break-word;
    }
    :is(#viewModal, #manualDocumentInlinePreview) #viewModalActions .btn {
      padding: 0.52rem 1rem;
      border-radius: 10px;
      font-weight: 600;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-status-actions {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #e5e7eb;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      width: 100%;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-status-actions .btn {
      padding: 0.52rem 1rem;
      border-radius: 5px;
      font-weight: 600;
      min-width: 110px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-status-actions--split {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-status-actions--split .btn {
      width: 100%;
      min-width: 0;
      padding: 0.62rem 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-stack {
      position: relative;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlays {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field {
      position: absolute;
      display: flex;
      flex-direction: column;
      gap: 4px;
      pointer-events: auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field span {
      display: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field input,
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field textarea {
      width: 100%;
      border: 0;
      border-bottom: 2px solid rgba(37, 99, 235, .45);
      background: rgba(255,255,255,.28);
      color: #111827;
      border-radius: 0;
      padding: 2px 4px;
      font-size: .92rem;
      font-weight: 700;
      text-transform: uppercase;
      box-shadow: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field textarea {
      resize: vertical;
      min-height: 44px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field input:focus,
    :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field textarea:focus {
      outline: 2px solid rgba(37, 99, 235, .25);
      border-color: rgba(37, 99, 235, .85);
    }
    #actionModal .modal-footer.action-split {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      width: 100%;
    }
    #actionModal .modal-footer.action-split .btn {
      width: 100%;
      margin: 0;
      padding: 0.62rem 1rem;
      font-weight: 600;
    }
    #actionModal #actionPrompt {
      text-align: center;
      color: #000;
      font-weight: 500;
      padding: 0;
      margin-bottom: 12px;
      background: transparent;
      border: 0;
      box-shadow: none;
    }
    #actionModal .modal-body {
      text-align: center;
      color: #000;
    }
    #actionModal #actionValidityWrap {
      text-align: left;
      max-width: 520px;
      margin: 0 auto 1rem;
    }
    #actionModal #actionValidityWrap .form-label {
      font-weight: 700;
      margin-bottom: 0.45rem;
    }
    #actionModal #actionValidityWrap .form-text {
      margin-top: 0.45rem;
      color: #4b5563;
    }
    #actionModal .modal-footer {
      flex-wrap: nowrap !important;
    }
    #actionModal .modal-footer .btn {
      flex: 1 1 0;
      white-space: nowrap;
    }
    #actionModal #actionBusinessApprovalWrap {
      text-align: left;
    }
    #actionModal #actionBusinessApprovalOptions {
      gap: 12px;
    }
    #actionModal .action-business-approval-card {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      border: 1px solid #d6dbe4;
      border-radius: 14px;
      padding: 14px 16px;
      cursor: pointer;
      background: #fff;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    #actionModal .action-business-approval-card:hover {
      border-color: #9cbcf7;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
    }
    #actionModal .action-business-approval-option {
      margin: 2px 0 0 0;
      flex: 0 0 auto;
      width: 1.2rem;
      height: 1.2rem;
    }
    #actionModal .action-business-approval-copy {
      flex: 1 1 auto;
      font-size: 0.98rem;
      line-height: 1.55;
      color: #111827;
      text-align: left;
    }
    #actionModal .action-business-approval-card:has(.action-business-approval-option:checked) {
      border-color: #3b82f6;
      background: #f8fbff;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
    }
    #actionModal .action-business-approval-card:has(.action-business-approval-option:disabled) {
      background: #f8fafc;
      color: #94a3b8;
      cursor: not-allowed;
      opacity: .8;
    }
    #actionModal .action-business-approval-card:has(.action-business-approval-option:disabled) .action-business-approval-copy {
      color: #94a3b8;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-shell {
      display: grid;
      place-items: center;
      padding: 4px 0 14px;
    }
    #manualDocumentInlinePreview .doc-preview-shell {
      max-height: min(68vh, 720px);
      overflow: auto;
      place-items: start center;
      overscroll-behavior: contain;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-stage {
      border: 1px solid #d9dee6;
      border-radius: 10px;
      background:
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
      padding: 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-label {
      display: inline-block;
      margin-bottom: 10px;
      padding: 4px 10px;
      border-radius: 999px;
      background: #111827;
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .02em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper {
      width: min(100%, 794px);
      min-height: 1123px;
      border: 1px solid #cfd8e3;
      border-radius: 6px;
      background: #fff;
      box-shadow: 0 14px 30px rgba(15, 23, 42, .12);
      padding: 32px 42px;
      position: relative;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency {
      font-family: "Times New Roman", Times, serif;
      padding: 24px 40px 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center p {
      font-size: .88rem;
      line-height: 1.08;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office {
      font-size: 1.06rem;
      font-weight: 700;
      letter-spacing: .01em;
      line-height: 1.1;
      border: 0 !important;
      border-left: 0 !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin-left: 0 !important;
      text-indent: 0 !important;
      background: transparent !important;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::before,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title {
      font-size: 1.9rem;
      margin: 8px 0 16px;
      letter-spacing: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency {
      margin: 10px 0 6px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      text-transform: uppercase;
      line-height: 1.15;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .office {
      font-size: 17px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .certificate {
      margin-top: 2px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1rem;
      line-height: 1.62;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body p {
      margin: 0 0 14px;
      text-indent: 2rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1rem;
      line-height: 1.62;
      text-align: justify;
      margin-top: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature {
      position: absolute;
      right: 66px;
      bottom: 258px;
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature .name {
      min-width: 260px;
      margin-top: 0;
      padding-top: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-issuedby {
      position: absolute;
      left: 48px;
      bottom: 250px;
      font-size: .95rem;
      line-height: 1.35;
      text-align: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-footer {
      position: absolute;
      width: 68%;
      left: 16%;
      bottom: 10px;
      font-family: Arial, Helvetica, sans-serif;
      font-size: .78rem;
      text-align: center;
      font-style: italic;
      color: #111827;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr {
      right: 18px;
      bottom: 30px;
      width: 92px;
      font-size: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr-box {
      width: 84px;
      height: 84px;
      border-style: solid;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-to-block {
      display: grid;
      grid-template-columns: 34px 10px 1fr;
      align-items: start;
      margin: 0 0 14px;
      column-gap: 2px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines {
      padding-top: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines .line {
      display: block;
      width: 320px;
      max-width: 100%;
      border-bottom: 2px solid #1f2937;
      margin: 0 0 10px;
      height: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral {
      font-family: Arial, Helvetica, sans-serif;
      padding: 28px 46px 36px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-head-office {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.06rem;
      font-weight: 800;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-head-office-sub {
      font-family: Arial, Helvetica, sans-serif;
      margin-top: 2px;
      font-size: .98rem;
      font-weight: 800;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office {
      margin-top: 16px;
      margin-bottom: 18px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 800;
      line-height: 1.28;
      text-transform: uppercase;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office div:last-child {
      margin-top: 4px;
      font-size: .98rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-title {
      font-size: 1.02rem;
      margin: 16px 0 18px;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.02rem;
      line-height: 1.6;
      text-align: justify;
      margin-top: 8px;
      padding: 0 8px 0 2px;
      flex: 0 0 auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral {
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding-bottom: 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body p {
      margin: 0 0 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body p + p {
      text-indent: 42px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-preview-issued-line {
      margin-top: 22px;
      margin-bottom: 18px;
      text-indent: 52px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block {
      display: grid;
      grid-template-columns: 82px 10px 1fr;
      align-items: start;
      column-gap: 0;
      margin: 0 0 4px;
      text-indent: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block + .doc-to-block {
      margin-top: 2px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block div,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block strong {
      line-height: 1.45;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta {
      margin-top: 8px;
      margin-bottom: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-row {
      display: grid;
      grid-template-columns: 136px 120px;
      align-items: baseline;
      justify-content: start;
      column-gap: 10px;
      margin: 0 0 4px;
      text-align: left;
      text-indent: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-label,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-value {
      text-align: left;
      text-indent: 0;
      white-space: nowrap;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-line {
      display: inline-block;
      width: 72px;
      border-bottom: 1px solid #111827;
      transform: translateY(-2px);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(260px, 1fr) 96px;
      align-items: end;
      column-gap: 18px;
      margin-top: 8px;
      padding-top: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer-area.doc-preview-footer-area--noqr {
      grid-template-columns: minmax(220px, 1fr) minmax(260px, 1fr);
      column-gap: 28px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-signature .name {
      min-width: 260px;
      margin-top: 0;
      padding-top: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-issuedby {
      font-size: .95rem;
      line-height: 1.35;
      text-align: left;
      align-self: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-grid {
      display: grid;
      grid-template-columns: minmax(240px, 1fr) minmax(280px, 1.1fr);
      gap: 16px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-list {
      display: grid;
      gap: 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px 12px;
      background: #ffffff;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-item__label {
      font-size: .76rem;
      font-weight: 700;
      color: #6b7280;
      margin-bottom: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-item__meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #f8fafc;
      padding: 12px;
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 10px;
      min-height: 220px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__name {
      font-weight: 600;
      color: #111827;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__placeholder {
      color: #6b7280;
      font-size: .9rem;
      text-align: center;
      padding: 24px 8px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__body iframe {
      width: 100%;
      height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__body img {
      max-width: 100%;
      max-height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    @media (max-width: 992px) {
      :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-grid {
        grid-template-columns: 1fr;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__body iframe,
      :is(#viewModal, #manualDocumentInlinePreview) .submitted-docs-preview__body img {
        height: 45vh;
      }
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer {
      width: 68%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      font-family: Arial, Helvetica, sans-serif;
      font-size: .78rem;
      text-align: center;
      font-style: italic;
      color: #111827;
      margin: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-hint {
      display: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office {
      margin-top: 12px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.24;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office div:last-child {
      margin-top: 4px;
      font-size: .98rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-body {
      font-size: .98rem;
      line-height: 1.36;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-body p {
      margin: 0 0 14px;
      text-align: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-lead {
      margin-bottom: 18px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-intro {
      text-align: center;
      margin-bottom: 18px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-fields {
      width: 78%;
      margin: 0 auto 22px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field {
      margin: 18px 0 22px;
      font-size: 1.02rem;
      line-height: 1.35;
      text-transform: uppercase;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field .doc-editable {
      min-width: 260px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field .doc-editable.doc-editable-multiline {
      white-space: normal;
      min-width: 320px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-paragraph {
      width: 90%;
      margin: 0 auto 16px;
      line-height: 1.32;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-checks {
      width: 90%;
      margin: 10px auto 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-row {
      display: grid;
      grid-template-columns: 48px 1fr;
      column-gap: 10px;
      align-items: start;
      margin: 0 0 8px;
      line-height: 1.28;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 3px;
      min-width: 48px;
      height: 14px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-line {
      width: 17px;
      border-top: 2px solid #111;
      flex: 0 0 auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-tick {
      width: 11px;
      text-align: center;
      font-size: 1rem;
      line-height: 1;
      font-weight: 800;
      opacity: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-mark--selected .doc-preview-business-check-tick {
      opacity: 1;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-issued-line {
      width: 86%;
      margin: 24px auto 0;
      text-align: center;
      line-height: 1.3;
      text-indent: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-area {
      display: grid;
      grid-template-columns: 1fr;
      column-gap: 0;
      align-items: end;
      margin-top: 18px;
      padding-top: 14px;
      padding-right: 108px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-area.doc-preview-business-footer-area--noqr {
      grid-template-columns: 1fr;
      padding-right: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-main {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 26px;
      align-items: end;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 10px;
      transform: translateY(-3px);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-left-column {
      align-self: end;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signing {
      display: grid;
      justify-items: center;
      row-gap: 10px;
      text-align: center;
      transform: translateY(-3px);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature {
      justify-items: center;
      text-align: center;
      margin-top: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature:nth-child(2) {
      transform: translateY(3px);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature .name {
      min-width: 252px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature div:last-child {
      font-style: italic;
      text-transform: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta {
      width: 280px;
      margin-top: 30px;
      margin-left: 8px;
      font-size: .86rem;
      line-height: 1.2;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-row {
      display: grid;
      grid-template-columns: 96px 14px 1fr;
      column-gap: 6px;
      align-items: baseline;
      margin: 0 0 2px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-value .doc-editable {
      width: 57px;
      min-width: 57px;
      white-space: nowrap;
      text-align: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-line {
      display: inline-block;
      width: 57px;
      border-bottom: 1px solid #111827;
      transform: translateY(-2px);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-footer {
      width: 60%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      margin: 0;
      font-size: .78rem;
      text-align: center;
      font-style: italic;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-qr {
      position: absolute;
      right: 34px;
      bottom: 42px;
      width: 96px;
      font-size: 0;
      justify-self: auto;
      align-self: auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--business .doc-preview-qr-box {
      width: 88px;
      height: 88px;
      border-style: solid;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-hint {
      display: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head {
      margin-bottom: 14px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office {
      margin-top: 10px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.22;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office div:last-child {
      margin-top: 4px;
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-body {
      font-size: .98rem;
      line-height: 1.34;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-body p {
      margin: 0;
      text-align: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-lead {
      margin-bottom: 18px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-intro,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-purpose {
      width: 84%;
      margin: 0 auto;
      text-align: center !important;
      line-height: 1.4;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-intro {
      margin-bottom: 18px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-fields {
      width: 72%;
      margin: 0 auto 20px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field {
      display: grid;
      grid-template-columns: 158px 16px 1fr;
      align-items: start;
      margin: 0 0 2px;
      line-height: 1.24;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field-label,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field-colon {
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field--address .doc-preview-tricycle-field-value {
      line-height: 1.2;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-issued-line {
      display: block;
      width: calc(100% - 108px);
      margin: 22px 0 0 54px;
      font-size: 12pt;
      text-align: justify;
      text-justify: inter-word;
      text-align-last: left;
      line-height: 1.4;
      text-indent: 48px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta {
      width: 330px;
      margin: 18px 0 0 42px;
      font-size: .97rem;
      line-height: 1.18;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-row {
      display: grid;
      grid-template-columns: 110px 14px 1fr;
      column-gap: 4px;
      align-items: baseline;
      margin: 0 0 2px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-line {
      display: inline-flex;
      align-items: flex-end;
      min-height: 1.35em;
      padding: 0 4px 2px;
      box-sizing: border-box;
      border-bottom: 1px solid #111827;
      overflow: hidden;
      max-width: 100%;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-line-text {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      font-weight: 700;
      line-height: 1.1;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 34px;
      align-items: end;
      margin-top: 18px;
      padding-top: 18px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 12px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signing {
      display: grid;
      justify-items: center;
      row-gap: 18px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature .name {
      min-width: 252px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature div:last-child {
      font-style: italic;
      text-transform: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-footer {
      width: 64%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      margin: 0;
      font-size: .78rem;
      text-align: center;
      font-style: italic;
      color: #111827;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-qr {
      left: 50%;
      right: auto;
      bottom: 52px;
      width: 88px;
      transform: translateX(-50%);
      font-size: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-qr-box {
      width: 80px;
      height: 80px;
      border-style: solid;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-hint {
      display: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head {
      margin-bottom: 14px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office {
      margin-top: 10px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.22;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office div:last-child {
      margin-top: 4px;
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-body {
      font-size: .98rem;
      line-height: 1.34;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-body p {
      margin: 0;
      text-align: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-lead {
      margin-bottom: 20px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-intro {
      width: 86%;
      margin: 0 auto 18px;
      text-align: center !important;
      line-height: 1.42;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-fields {
      width: 72%;
      margin: 0 auto 20px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field {
      display: grid;
      grid-template-columns: 172px 16px 1fr;
      align-items: start;
      margin: 0 0 2px;
      line-height: 1.22;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-label,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-colon {
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-value {
      min-width: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-value .doc-editable {
      display: block;
      width: 100%;
      min-width: 0;
      box-sizing: border-box;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field--address .doc-preview-generalclearance-field-value {
      line-height: 1.18;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-note {
      display: block;
      width: calc(100% - 108px);
      margin: 20px 0 0 54px;
      font-size: 12pt;
      text-align: justify !important;
      text-justify: inter-word;
      text-align-last: left;
      line-height: 1.4;
      text-indent: 48px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-note-nowrap {
      white-space: nowrap;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-issued-line {
      display: block;
      width: calc(100% - 108px);
      margin: 14px 0 0 54px;
      font-size: 12pt;
      text-align: justify;
      text-justify: inter-word;
      text-align-last: left;
      line-height: 1.4;
      text-indent: 48px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta {
      width: 300px;
      margin: 18px 0 0 34px;
      font-size: .97rem;
      line-height: 1.24;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-row {
      display: grid;
      grid-template-columns: 126px 8px minmax(120px, 1fr);
      column-gap: 2px;
      align-items: center;
      margin: 0 0 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-value {
      min-width: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-line {
      display: flex;
      align-items: flex-end;
      width: 100%;
      min-height: 1.35em;
      padding: 0 4px 2px;
      box-sizing: border-box;
      border-bottom: 1px solid #111827;
      overflow: hidden;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-line-text {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      font-weight: 700;
      line-height: 1.1;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 36px;
      align-items: end;
      margin-top: 18px;
      padding-top: 18px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 16px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signing {
      display: grid;
      justify-items: center;
      row-gap: 20px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature .name {
      min-width: 270px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature div:last-child {
      font-style: italic;
      text-transform: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-footer {
      width: 58%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      margin: 0;
      font-size: .76rem;
      text-align: center;
      font-style: italic;
      color: #6b7280;
      line-height: 1.35;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-qr {
      left: 50%;
      right: auto;
      bottom: 58px;
      width: 88px;
      transform: translateX(-50%);
      font-size: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-qr-box {
      width: 80px;
      height: 80px;
      border-style: solid;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--cohabitation-children {
      min-height: 1320px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs {
      min-height: 1220px;
      padding-bottom: 124px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-goodmoral-office {
      margin-top: 10px;
      margin-bottom: 14px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-subtitle {
      margin-top: 2px;
      font-size: .74rem;
      line-height: 1.2;
      text-transform: none;
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-body {
      font-size: .96rem;
      line-height: 1.46;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-body p {
      margin-bottom: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-footer-area--ftjs {
      display: block;
      padding-top: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-signing {
      display: grid;
      justify-items: stretch;
      row-gap: 2px;
      position: absolute;
      right: 108px;
      bottom: 300px;
      width: 248px;
      margin: 0;
      font-size: .92rem;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-block {
      display: grid;
      justify-items: center;
      row-gap: 2px;
      width: 100%;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-name {
      width: 100%;
      font-size: 1rem;
      font-weight: 800;
      line-height: 1.2;
      text-align: center;
      text-decoration: underline;
      text-decoration-thickness: 1.5px;
      text-underline-offset: 4px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-role {
      font-size: .95rem;
      line-height: 1.15;
      font-style: italic;
      text-align: center;
      text-transform: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-issuedby em,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-business-issuedby em,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-generalclearance-issuedby em,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-tricycle-issuedby em,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature div:last-child {
      text-transform: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-line {
      width: 100%;
      margin-top: 6px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-line span {
      display: inline-block;
      min-width: 170px;
      max-width: 100%;
      font-weight: 700;
      line-height: 1.1;
      text-decoration: underline;
      text-decoration-thickness: 1.5px;
      text-underline-offset: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-witness-label,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-label {
      width: 100%;
      font-size: .88rem;
      line-height: 1.1;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-label {
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-witness-label {
      margin: 14px 0 12px;
      font-weight: 700;
      font-size: .96rem;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-footer {
      width: 58%;
      position: absolute;
      left: 21%;
      bottom: 10px;
      margin: 0;
      font-size: .76rem;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-qr {
      position: absolute;
      right: 34px;
      bottom: 56px;
      width: 96px;
      justify-self: auto;
      align-self: auto;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail {
      min-height: 1188px;
      padding-top: 34px;
      padding-bottom: 96px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-office {
      margin-top: 6px;
      margin-bottom: 28px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-hint {
      display: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body {
      font-size: 1rem;
      line-height: 1.48;
      text-align: justify;
      padding: 0 6px;
      margin-top: 16px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body p {
      margin: 0 0 22px;
      text-indent: 56px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body p + p {
      text-indent: 56px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-lead {
      text-align: left;
      margin-bottom: 26px;
      font-size: 1rem;
      text-indent: 0 !important;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-center {
      width: 84%;
      margin-left: auto;
      margin-right: auto;
      box-sizing: border-box;
      text-align: justify;
      text-align-last: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-ordinance {
      width: 84%;
      margin: 6px auto 26px;
      box-sizing: border-box;
      text-align: justify;
      text-align-last: left;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body .doc-preview-issued-line {
      width: 84%;
      margin: 10px auto 6px;
      box-sizing: border-box;
      text-indent: 56px;
      text-align: left;
      line-height: 1.52;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-meta {
      width: 220px;
      margin: 0 0 0 8px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-meta .doc-preview-meta-row {
      grid-template-columns: 88px 84px;
      column-gap: 10px;
      margin-bottom: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-footer-area {
      margin-top: 18px;
      padding-top: 12px;
      align-items: start;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-issuedby {
      align-self: start;
      padding-top: 14px;
      padding-bottom: 0;
      font-size: .92rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-signature {
      align-self: start;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-signature .name {
      min-width: 320px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-footer {
      width: 72%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      margin: 0;
      font-size: .8rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-qr {
      position: static;
      right: auto;
      bottom: auto;
      width: 96px;
      font-size: 0;
      justify-self: end;
      align-self: end;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-qr-box {
      width: 88px;
      height: 88px;
      border-style: solid;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head {
      display: grid;
      grid-template-columns: 100px 1fr 100px;
      align-items: center;
      gap: 14px;
      margin-bottom: 16px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-logo {
      width: 92px;
      height: 92px;
      object-fit: contain;
      border-radius: 50%;
      justify-self: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center {
      text-align: center;
      color: #111827;
      line-height: 1.18;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center p {
      margin: 0;
      font-size: .78rem;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center .rep {
      font-size: .94rem;
      font-weight: 800;
      letter-spacing: .02em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center .barangay {
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: .02em;
      margin-top: 2px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center .doc-head-office {
      font-size: .96rem;
      font-weight: 800;
      margin-top: 2px;
      border: 0 !important;
      border-left: 0 !important;
      box-shadow: none !important;
      padding: 0 !important;
      text-indent: 0 !important;
      background: transparent !important;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center .doc-head-office::before,
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-head-line {
      border-bottom: 2px solid #9ca3af;
      margin-top: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-title {
      text-align: center;
      font-size: 1.1rem;
      font-weight: 800;
      letter-spacing: .03em;
      margin: 10px 0 14px;
      text-transform: uppercase;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: .95rem;
      color: #111827;
      line-height: 1.55;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-body p {
      margin: 0 0 12px;
      text-align: justify;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-hint {
      margin: 0 0 10px;
      font-size: .78rem;
      color: #92400e;
      background: #fff7d6;
      border: 1px solid #fde68a;
      border-radius: 7px;
      padding: 6px 8px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-editable {
      background: #fff6bf;
      border: 1px dashed #d97706;
      border-radius: 4px;
      padding: 0 4px;
      min-width: 24px;
      display: inline-block;
      outline: none;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-editable--empty {
      min-height: 1.2em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-editable:focus {
      border-style: solid;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, .2);
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-editable-multiline {
      white-space: pre-line;
      min-width: 280px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature {
      margin-top: 14px;
      display: grid;
      justify-items: end;
      color: #111827;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink {
      min-height: 42px;
      width: 160px;
      display: flex;
      align-items: end;
      justify-content: center;
      margin-bottom: 4px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink img {
      max-width: 100%;
      max-height: 40px;
      object-fit: contain;
      display: block;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink--issuedby {
      justify-content: flex-start;
      width: 140px;
      min-height: 36px;
      margin-bottom: 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink--issuedby img {
      max-height: 34px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink--ftjs {
      width: 136px;
      min-height: 34px;
      margin: 0 auto 6px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature-ink--ftjs img {
      max-height: 32px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-signature .name {
      margin-top: 18px;
      font-weight: 700;
      border-top: 1px solid #1f2937;
      padding-top: 4px;
      min-width: 220px;
      text-align: center;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-qr {
      position: absolute;
      right: 18px;
      bottom: 24px;
      width: 96px;
      text-align: center;
      color: #374151;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .02em;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-qr-box {
      width: 88px;
      height: 88px;
      border: 1px dashed #6b7280;
      border-radius: 6px;
      margin: 0 auto 6px;
      display: grid;
      place-items: center;
      background: #f9fafb;
      overflow: hidden;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .doc-preview-qr-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-section {
      border: 0;
      border-radius: 0;
      background: transparent;
      padding: 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-section h6 {
      margin: 2px 0 8px;
      font-size: .9rem;
      color: #374151;
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-item {
      background: transparent;
      border: 0;
      border-radius: 0;
      padding: 2px 0;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-label {
      margin: 0 0 4px;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      color: #6b7280;
      font-weight: 700;
    }
    :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-value {
      margin: 0;
      font-size: .95rem;
      color: #111827;
      font-weight: 600;
      word-break: break-word;
    }
    .manual-issuance-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .manual-id-process {
      display: grid;
      grid-template-columns: repeat(var(--manual-process-step-count, 5), minmax(0, 1fr));
      border: 1px solid #fed7aa;
      border-radius: 20px;
      overflow: hidden;
      background: #fffaf5;
    }
    .manual-id-process-step {
      display: flex;
      align-items: center;
      gap: 9px;
      min-height: 68px;
      padding: 12px 16px;
      color: #7c2d12;
      font-size: .82rem;
      font-weight: 700;
    }
    .manual-id-process-step:not(:last-child) { border-right: 1px solid #fed7aa; }
    .manual-id-process-step > i { color: #ea580c; }
    .manual-id-process-step.is-active { color: #fff; background: #ea580c; }
    .manual-id-process-step.is-active > i { color: #fff; }
    .manual-id-process-step.is-active .manual-id-process-number { color: #ea580c; background: #fff; }
    .manual-id-process-step.is-complete { color: #166534; background: #f0fdf4; }
    .manual-id-process-step.is-complete > i { color: #16a34a; }
    .manual-id-process-step.is-complete .manual-id-process-number { background: #16a34a; }
    .manual-id-process-number {
      display: inline-grid;
      place-items: center;
      flex: 0 0 26px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      color: #fff;
      background: #ea580c;
      font-size: .75rem;
    }
    .manual-id-wizard-controls {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 16px;
      padding-top: 18px;
      border-top: 1px solid #e5e7eb;
    }
    .manual-id-wizard-controls .btn:first-child { justify-self: start; }
    .manual-id-wizard-controls .btn:last-child { justify-self: end; }
    .manual-id-wizard-controls .btn-primary {
      --bs-btn-color: #fff;
      --bs-btn-bg: #ea580c;
      --bs-btn-border-color: #ea580c;
      --bs-btn-hover-color: #fff;
      --bs-btn-hover-bg: #c2410c;
      --bs-btn-hover-border-color: #c2410c;
      --bs-btn-focus-shadow-rgb: 234, 88, 12;
      --bs-btn-active-color: #fff;
      --bs-btn-active-bg: #9a3412;
      --bs-btn-active-border-color: #9a3412;
      --bs-btn-disabled-color: #fff;
      --bs-btn-disabled-bg: #fdba74;
      --bs-btn-disabled-border-color: #fdba74;
    }
    .manual-id-wizard-position { color: #6b7280; font-size: .84rem; font-weight: 700; }
    .manual-id-inline-preview {
      min-height: 360px;
      padding: 20px;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      background: #f8fafc;
      overflow: hidden;
    }
    .manual-id-inline-preview-loading {
      min-height: 320px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      color: #64748b;
      font-weight: 600;
    }
    .manual-id-inline-preview .tracker-doc-highlight { display: none; }
    .manual-before-approve {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 16px;
      padding: 4px 0;
      border: 0;
      border-radius: 0;
      background: transparent;
    }
    .manual-before-approve-icon {
      display: grid;
      place-items: center;
      width: 42px;
      height: 42px;
      border-radius: 13px;
      color: #ea580c;
      background: #fff7ed;
      font-size: 1rem;
    }
    .manual-before-approve h6 { margin: 0 0 4px; color: #111827; font-weight: 800; }
    .manual-before-approve p { margin: 0 0 12px; color: #6b7280; font-size: .84rem; }
    .manual-before-approve-list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px 18px;
    }
    .manual-before-approve-list span {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      color: #374151;
      font-size: .82rem;
      line-height: 1.4;
    }
    .manual-before-approve-list i { margin-top: 3px; color: #16a34a; }
    .manual-validity-selection {
      width: 100%;
      max-width: none;
      padding-top: 20px;
    }
    .manual-area-options {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
    }
    .manual-area-picker-field { position: relative; }
    .manual-area-picker-field > i:first-child {
      position: absolute;
      left: 14px;
      top: 50%;
      z-index: 2;
      color: #ea580c;
      transform: translateY(-50%);
      pointer-events: none;
    }
    .manual-area-picker-field .form-control {
      padding-left: 42px;
      padding-right: 42px;
      cursor: pointer;
      background: #fff;
    }
    .manual-area-picker-field .form-control:hover {
      border-color: #fb923c;
      background: #fffaf5;
    }
    .manual-area-picker-chevron {
      position: absolute;
      right: 14px;
      top: 50%;
      color: #9ca3af;
      transform: translateY(-50%);
      pointer-events: none;
    }
    .manual-area-option {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 10px;
      min-height: 72px;
      padding: 14px 16px;
      border: 1px solid #d1d5db;
      border-radius: 14px;
      color: #374151;
      background: #fff;
      font-weight: 700;
      text-align: left;
    }
    .manual-area-option > i:first-child { color: #ea580c; }
    .manual-area-option-copy { display: grid; gap: 3px; }
    .manual-area-option-copy strong { color: #111827; font-size: .95rem; }
    .manual-area-option-copy small { color: #6b7280; font-size: .8rem; font-weight: 500; line-height: 1.4; }
    .manual-area-option-check { visibility: hidden; color: #16a34a; }
    .manual-area-option:hover { border-color: #fb923c; background: #fff7ed; }
    .manual-area-option.is-selected {
      border-color: #22c55e;
      color: #166534;
      background: #f0fdf4;
      box-shadow: 0 0 0 3px rgba(34, 197, 94, .1);
    }
    .manual-area-option.is-selected .manual-area-option-check { visibility: visible; }
    .manual-address-default,
    .manual-address-default:focus {
      color: #6b7280;
      background-color: #f3f4f6;
      border-color: #e5e7eb;
      box-shadow: none;
      cursor: default;
    }
    .manual-birthdate-dropdowns {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(64px, .7fr) minmax(78px, .9fr);
      gap: 8px;
    }
    .manual-birthdate-dropdowns .form-select {
      min-width: 0;
      padding-left: 10px;
      padding-right: 28px;
      background-position: right 8px center;
      font-size: .88rem;
    }
    .id-issuance-view #manualDynamicFields > div:has([data-manual-field="emergency_relationship"]),
    .id-issuance-view #manualDynamicFields > div:has([data-manual-field="emergency_contact"]) {
      flex: 0 0 auto;
      width: 50%;
    }
    .id-issuance-view #manualDynamicFields > div:has([data-manual-field="emergency_address"]) {
      flex: 0 0 auto;
      width: 100%;
    }
    .manual-issuance-steps {
      display: none !important;
    }
    .manual-issuance-card {
      border: 1px solid #e5e7eb;
      border-radius: 22px;
      background: #fff;
      padding: 18px 20px;
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
    }
    .manual-issuance-card + .manual-issuance-card {
      margin-top: 16px;
    }
    .manual-issuance-card-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .manual-issuance-card-title h6 {
      margin: 0;
      font-weight: 700;
      color: #111827;
    }
    .manual-issuance-card-title span {
      color: #6b7280;
      font-size: .82rem;
    }
    .manual-issuance-mode-switch {
      display: inline-flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .manual-issuance-mode-switch .form-check {
      margin: 0;
      padding: 0;
    }
    .manual-issuance-mode-switch .form-check-input {
      display: none;
    }
    .manual-issuance-mode-switch .form-check-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: 999px;
      background: #fff;
      color: #374151;
      font-weight: 600;
      cursor: pointer;
      transition: .18s ease;
    }
    .manual-issuance-mode-switch .form-check-input:checked + .form-check-label {
      background: #fff3e6;
      border-color: #fb923c;
      color: #9a3412;
      box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.12);
    }
    .id-issuance-view .manual-issuance-mode-switch {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      width: 100%;
      gap: 16px;
    }
    .id-issuance-view .manual-issuance-mode-switch .form-check-label {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 16px;
      width: 100%;
      min-height: 118px;
      padding: 20px;
      border: 2px solid #e5e7eb;
      border-radius: 20px;
      background: #fff;
    }
    .id-issuance-view .manual-issuance-mode-switch .form-check-label:hover {
      border-color: #fdba74;
      background: #fffaf5;
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(154, 52, 18, .08);
    }
    .id-issuance-view .manual-issuance-mode-switch .form-check-input:checked + .form-check-label {
      border-color: #ea580c;
      background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
      color: #9a3412;
      box-shadow: 0 0 0 4px rgba(234, 88, 12, .12);
    }
    .manual-source-choice-icon {
      display: grid;
      place-items: center;
      width: 52px;
      height: 52px;
      border-radius: 16px;
      color: #9a3412;
      background: #ffedd5;
      font-size: 1.25rem;
    }
    .manual-source-choice-copy { display: grid; gap: 5px; min-width: 0; }
    .manual-source-choice-copy strong { color: #111827; font-size: 1rem; }
    .manual-source-choice-copy small { color: #6b7280; font-size: .82rem; font-weight: 500; line-height: 1.45; }
    .manual-source-choice-check { color: #ea580c; font-size: 1.25rem; opacity: 0; transition: opacity .18s ease; }
    .manual-issuance-mode-switch .form-check-input:checked + .form-check-label .manual-source-choice-check { opacity: 1; }
    .manual-issuance-mode-switch .form-check-input:focus-visible + .form-check-label {
      outline: 3px solid rgba(37, 99, 235, .3);
      outline-offset: 3px;
    }
    .manual-resident-results {
      display: grid;
      gap: 10px;
    }
    .manual-resident-result {
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 14px 16px;
      background: #f8fafc;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      cursor: pointer;
      transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .manual-resident-result:hover,
    .manual-resident-result:focus-visible {
      border-color: #2563eb;
      background: #eff6ff;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
      transform: translateY(-1px);
      outline: none;
    }
    .manual-resident-result-name {
      font-size: .96rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 4px;
    }
    .manual-resident-result-meta {
      margin: 0;
      color: #6b7280;
      font-size: .84rem;
      line-height: 1.45;
    }
    .manual-selected-resident {
      border: 1px solid #fed7aa;
      border-radius: 18px;
      padding: 14px 16px;
      background: #fff7ed;
    }
    .manual-selected-resident strong {
      display: block;
      color: #9a3412;
      margin-bottom: 4px;
    }
    .manual-selected-resident p {
      margin: 0;
      color: #7c2d12;
      font-size: .86rem;
      line-height: 1.45;
    }
    .manual-selected-resident .btn {
      margin-top: 10px;
    }
    .manual-issuance-summary {
      display: grid;
      gap: 12px;
    }
    .manual-summary-item {
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      background: #f8fafc;
      padding: 14px 16px;
    }
    .manual-summary-item-label {
      margin: 0 0 6px;
      color: #6b7280;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .manual-summary-item-value {
      margin: 0;
      color: #111827;
      font-size: .96rem;
      font-weight: 700;
      line-height: 1.4;
    }
    .manual-summary-note {
      margin: 0;
      padding: 12px 14px;
      border-radius: 16px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
      font-size: .86rem;
      line-height: 1.5;
    }
    .manual-fee-list {
      display: grid;
      gap: 12px;
    }
    .manual-fee-item {
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 14px 16px;
      background: #f8fafc;
    }
    .manual-fee-item label {
      font-weight: 600;
      color: #111827;
    }
    .manual-fee-total {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px dashed #d1d5db;
      font-weight: 700;
      color: #111827;
    }
    .manual-fee-total strong {
      color: #0f766e;
    }
    .manual-search-empty {
      border: 1px dashed #d1d5db;
      border-radius: 16px;
      padding: 14px 16px;
      color: #6b7280;
      font-size: .88rem;
      background: #f8fafc;
    }
    #manualFormAlert {
      margin-top: 14px;
    }
    .manual-photo-field {
      border: 1px dashed #cbd5e1;
      border-radius: 18px;
      background: linear-gradient(180deg, #ffffff 0%, #eff6ff 100%);
      padding: 16px;
    }
    .id-issuance-view .manual-photo-field {
      max-width: 940px;
      margin: 8px auto 0;
      padding: 28px;
      border: 1px solid #e5e7eb;
      background: #f8fafc;
      box-shadow: none;
    }
    .manual-photo-capture-layout {
      display: grid;
      grid-template-columns: minmax(240px, 300px) minmax(0, 1fr);
      align-items: center;
      gap: 34px;
    }
    .manual-photo-guidance h6 {
      margin: 0 0 8px;
      color: #111827;
      font-size: 1.05rem;
      font-weight: 700;
    }
    .manual-photo-guidance p {
      margin: 0 0 12px;
      color: #64748b;
      font-size: .88rem;
      line-height: 1.5;
    }
    .manual-photo-guidance ul {
      display: grid;
      gap: 7px;
      margin: 0;
      padding-left: 20px;
      color: #475569;
      font-size: .84rem;
    }
    .manual-photo-field-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .manual-photo-field-header h6 {
      margin: 0 0 4px;
      color: #111827;
      font-weight: 700;
    }
    .manual-photo-field-header p {
      margin: 0;
      color: #64748b;
      font-size: .84rem;
      line-height: 1.45;
    }
    .manual-photo-preview-box {
      width: min(300px, 100%);
      aspect-ratio: 1 / 1;
      border-radius: 20px;
      background: #0f172a;
      border: 1px solid rgba(148, 163, 184, 0.35);
      overflow: hidden;
      position: relative;
      display: grid;
      place-items: center;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
    }
    .manual-photo-preview-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .manual-photo-preview-placeholder {
      padding: 18px;
      text-align: center;
      color: #cbd5e1;
      font-size: .85rem;
      line-height: 1.45;
    }
    .manual-photo-meta {
      display: grid;
      gap: 10px;
      align-content: start;
      min-width: min(320px, 100%);
      flex: 1 1 260px;
    }
    .manual-photo-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
      font-size: .8rem;
      font-weight: 700;
      width: fit-content;
      max-width: 100%;
    }
    .manual-photo-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .manual-photo-note {
      margin: 0;
      color: #475569;
      font-size: .83rem;
      line-height: 1.5;
    }
    .manual-photo-modal .modal-dialog {
      max-width: 980px;
    }
    .manual-photo-stage-copy {
      color: #64748b;
      font-size: .9rem;
      margin-bottom: 14px;
      line-height: 1.5;
    }
    .manual-photo-workspace {
      --manual-photo-frame-size: min(58vw, 340px);
      position: relative;
      min-height: 460px;
      border-radius: 26px;
      background:
        radial-gradient(circle at top, rgba(37, 99, 235, 0.16), transparent 42%),
        linear-gradient(180deg, #0f172a 0%, #020617 100%);
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, 0.18);
    }
    .manual-photo-workspace video,
    .manual-photo-workspace img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .manual-photo-workspace img {
      width: auto;
      height: auto;
      max-width: none;
      max-height: none;
      user-select: none;
      -webkit-user-drag: none;
      touch-action: none;
      transform-origin: 0 0;
      cursor: grab;
    }
    .manual-photo-workspace.is-dragging img {
      cursor: grabbing;
    }
    .manual-photo-frame {
      position: absolute;
      left: 50%;
      top: 50%;
      width: var(--manual-photo-frame-size);
      height: var(--manual-photo-frame-size);
      transform: translate(-50%, -50%);
      border-radius: 28px;
      border: 2px solid rgba(255, 255, 255, 0.96);
      box-shadow: 0 0 0 9999px rgba(2, 6, 23, 0.64);
      pointer-events: none;
    }
    .manual-photo-frame::before,
    .manual-photo-frame::after {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
    }
    .manual-photo-frame::before {
      border-radius: 28px;
      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.45),
        inset 0 0 0 999px rgba(255, 255, 255, 0.02);
    }
    .manual-photo-frame::after {
      background:
        linear-gradient(to right, transparent 33.1%, rgba(255,255,255,0.3) 33.1%, rgba(255,255,255,0.3) 33.6%, transparent 33.6%, transparent 66.4%, rgba(255,255,255,0.3) 66.4%, rgba(255,255,255,0.3) 66.9%, transparent 66.9%),
        linear-gradient(to bottom, transparent 33.1%, rgba(255,255,255,0.3) 33.1%, rgba(255,255,255,0.3) 33.6%, transparent 33.6%, transparent 66.4%, rgba(255,255,255,0.3) 66.4%, rgba(255,255,255,0.3) 66.9%, transparent 66.9%);
      border-radius: 28px;
      opacity: 0.9;
    }
    .manual-photo-empty-state {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      padding: 24px;
      text-align: center;
      color: #cbd5e1;
      font-size: .92rem;
      line-height: 1.55;
    }
    .manual-photo-controls {
      display: grid;
      gap: 8px;
      margin-top: 14px;
    }
    .manual-photo-controls--camera {
      margin-top: 0;
      margin-bottom: 14px;
    }
    .manual-photo-controls label {
      font-size: .82rem;
      font-weight: 700;
      color: #334155;
      margin: 0;
    }
    .manual-photo-controls .form-select {
      max-width: 360px;
    }
    .manual-photo-controls input[type="range"] {
      width: 100%;
    }
    .manual-photo-control-hint {
      margin: 0;
      color: #64748b;
      font-size: .79rem;
      line-height: 1.45;
    }
    .manual-photo-footer-copy {
      color: #64748b;
      font-size: .82rem;
      line-height: 1.45;
      margin-right: auto;
      max-width: 420px;
    }

    #residentProfileModal #div-modalSizing {
      max-width: 1200px;
      width: 70vw;
    }
    #residentProfileModal .modal-content {
      border: 0;
      border-radius: .5rem;
      padding: 1rem;
      background: #fff;
    }
    @media (max-width: 768px) {
      .manual-birthdate-dropdowns { grid-template-columns: 1fr 1fr 1fr; }
      .manual-before-approve-list { grid-template-columns: 1fr; }
      .manual-photo-capture-layout { grid-template-columns: 1fr; gap: 22px; }
      .id-issuance-view .manual-photo-field { padding: 18px; }
      .manual-photo-preview-box { width: min(280px, 100%); margin-inline: auto; }
      .id-issuance-view #manualDynamicFields > div:has([data-manual-field="emergency_relationship"]),
      .id-issuance-view #manualDynamicFields > div:has([data-manual-field="emergency_contact"]) {
        width: 100%;
      }
      .id-issuance-view .manual-issuance-mode-switch { grid-template-columns: 1fr; }
      .manual-id-process { grid-template-columns: 1fr; }
      .manual-id-process-step:not(:last-child) { border-right: 0; border-bottom: 1px solid #fed7aa; }
      .manual-id-wizard-controls { grid-template-columns: 1fr 1fr; }
      .manual-id-wizard-position { grid-column: 1 / -1; grid-row: 1; text-align: center; }
      .manual-area-options { grid-template-columns: 1fr; }
      .certificate-tracker-shell .stage-filter-btn {
        min-width: 0;
      }
      .certificate-tracker-shell .admin-search,
      .certificate-tracker-shell .tracker-doc-filter {
        min-width: 100%;
        max-width: 100%;
      }
      .manual-photo-workspace {
        --manual-photo-frame-size: min(72vw, 290px);
        min-height: 360px;
      }
      .manual-resident-result {
        flex-direction: column;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .tracker-profile-grid {
        grid-template-columns: 1fr;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid {
        grid-template-columns: 1fr;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid.cols-4 {
        grid-template-columns: 1fr;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .tracker-form-grid.cols-3 {
        grid-template-columns: 1fr;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlays {
        position: static;
        margin-top: 12px;
        display: grid;
        gap: 10px;
        pointer-events: auto;
      }
      :is(#viewModal, #manualDocumentInlinePreview) .template-preview-overlay-field {
        position: static;
      }
      #residentProfileModal #div-modalSizing {
        width: 96vw;
      }
    }
  </style>
</head>
<body class="<?= trim(($isIdIssuanceTrackerView ? 'id-issuance-view ' : '') . ($isFeeSettingsView ? 'fee-settings-view' : '')) ?>">
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" style="min-width:0;">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
      <h2 class="mb-0" style="font-family: 'Charis SIL Bold'; color: #DE710C; "><?= htmlspecialchars($certificateTrackerHeading, ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if (!$isIdIssuanceTrackerView): ?>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($certificateSettingsHref, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-gear me-2"></i><?= htmlspecialchars($certificateSettingsLabel, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endif; ?>
    </div>
    <hr class="mb-4">
    <?php if ($issuanceAgingAlert !== null): ?>
      <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid fa-clock-rotate-left mt-1"></i>
        <div><?= htmlspecialchars((string)$issuanceAgingAlert['message'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php endif; ?>

    <!-- Page-level navigation -->
    <ul class="nav nav-tabs mb-0 <?= $isFeeSettingsView ? 'd-none' : '' ?>" id="certTrackerPageTabs" style="border-bottom:0">
      <li class="nav-item">
        <button class="nav-link active fw-semibold" id="tabDocRequests" type="button">
          <i class="fas fa-file-alt me-1"></i>Document Requests
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-semibold" id="tabManualIssuance" type="button">
          <i class="fas fa-pen-to-square me-1"></i>Manual Issuance
        </button>
      </li>
    </ul>

    <div id="docRequestsPanel" class="bg-white p-4 rounded-4 rounded-tl-0 shadow-sm border resident-masterlist-shell certificate-tracker-shell">
      <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
        <div class="admin-list-tabs">
          <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn stage-filter-btn active" data-filter="" data-stage-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
          <?php if ($isIdIssuanceTrackerView): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="completed" data-stage-filter="completed">&nbsp;&nbsp;Completed&nbsp;&nbsp;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="release" data-stage-filter="release">&nbsp;&nbsp;Printing / Claim&nbsp;&nbsp;<span class="tab-count" id="releaseTabCount">0</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="pending" data-stage-filter="pending">&nbsp;&nbsp;Pending&nbsp;&nbsp;<span class="tab-count" id="pendingTabCount">0</span></button>
          <?php else: ?>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="completed" data-stage-filter="completed">&nbsp;&nbsp;Completed&nbsp;&nbsp;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="release" data-stage-filter="release">&nbsp;&nbsp;Release&nbsp;&nbsp;<span class="tab-count" id="releaseTabCount">0</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-filter="pending" data-stage-filter="pending">&nbsp;&nbsp;Pending&nbsp;&nbsp;<span class="tab-count" id="pendingTabCount">0</span></button>
          <?php endif; ?>
        </div>

        <div class="admin-list-actions">
          <div class="input-group admin-search">
            <input type="text" id="searchInput" class="form-control" placeholder="Request ID, resident ID, name, address">
            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          </div>
          <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
            <i class="fas fa-filter"></i>
            <span class="visually-hidden">Filter</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnCertificateColumns" title="Columns" aria-label="Columns">
            <i class="fa-solid fa-sliders"></i>
            <span class="visually-hidden">Columns</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnRefreshList" title="Refresh table" aria-label="Refresh table">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span class="visually-hidden">Refresh</span>
          </button>
        </div>
      </div>

      <div class="table-responsive compact-admin-table-shell">
        <table id="table-certificateTracker" class="table align-middle compact-admin-table" data-table-pagination>
          <thead>
            <tr class="table-light">
              <th class="col-request-id">Request ID</th>
              <th class="col-resident-id">Resident ID</th>
              <th class="col-full-name">Full Name</th>
              <th class="col-document">Document Requested</th>
              <th class="col-purpose">Purpose</th>
              <th class="col-status">Status</th>
              <th class="col-submitted">Submitted Date</th>
              <th class="col-action">Action</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Loading requests...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="manualIssuancePanel" class="d-none bg-white p-4 rounded-4 shadow-sm border certificate-tracker-shell">
      <div class="manual-issuance-header mb-4">
        <div>
          <h5 class="fw-bold mb-1"><?= $isIdIssuanceTrackerView ? 'Barangay ID Manual Issuance' : 'Manual / Walk-in Document Issuance' ?></h5>
          <?php if ($isIdIssuanceTrackerView): ?>
            <p class="text-muted mb-0">Complete each stage in order, then review the initial ID preview before approval.</p>
          <?php endif; ?>
        </div>
      </div>

        <nav class="manual-id-process mb-4" style="--manual-process-step-count: 5" aria-label="Manual issuance process">
          <?php foreach ($isIdIssuanceTrackerView ? [
            ['fa-user-magnifying-glass', 'Source Selection'],
            ['fa-address-card', 'Personal Information'],
            ['fa-camera', 'ID Photo'],
            ['fa-circle-check', 'Approval & Validity'],
            ['fa-id-card', 'Initial ID Preview'],
          ] : [
            ['fa-user-magnifying-glass', 'Source & Document Setup'],
            ['fa-address-card', 'Personal Information'],
            ['fa-list-check', 'Document Details'],
            ['fa-circle-check', 'Confirm & Validity'],
            ['fa-file-lines', 'Document Preview'],
          ] as $index => [$icon, $label]): ?>
            <div class="manual-id-process-step">
              <span class="manual-id-process-number"><?= $index + 1 ?></span>
              <i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
              <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </nav>

      <div class="manual-issuance-steps mb-4 d-none">
        <div class="manual-step">
          <div class="manual-step-index">1</div>
          <div class="manual-step-title">Receive Form</div>
          <p class="manual-step-copy">Use the resident’s handwritten submission as the source document for this encoding flow.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">2</div>
          <div class="manual-step-title">Encode Details</div>
          <p class="manual-step-copy">Select a registered resident or encode a walk-in resident, then complete the matching certificate or clearance form.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">3</div>
          <div class="manual-step-title">Set Validity</div>
          <p class="manual-step-copy">For certificates, set the document validity before previewing. If you leave it blank, the system uses the 45-day default.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">4</div>
          <div class="manual-step-title">Preview</div>
          <p class="manual-step-copy">Open the rendered document preview first so the encoded details match the physical form before submission.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">5</div>
          <div class="manual-step-title">Payment Routing</div>
          <p class="manual-step-copy">Only paid requests continue to finance for walk-in payment recording. Free requests such as Barangay ID skip finance and proceed directly to release.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">6</div>
          <div class="manual-step-title">Release by Print</div>
          <p class="manual-step-copy">After payment or interview handling, admin releases the final document through print while keeping QR verification active.</p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12">
          <form id="manualIssuanceForm" novalidate>
            <input type="hidden" id="manualResidentId" name="resident_id">
            <input type="hidden" id="manualResidentUserId" name="resident_user_id">

            <div class="manual-issuance-card" data-manual-step-panel="1">
              <div class="manual-issuance-card-title">
                <h6><?= $isIdIssuanceTrackerView ? '1. Source Selection' : '1. Source' ?></h6>
                <span>Link an existing resident record or encode only the essential details for a walk-in resident.</span>
              </div>
              <div class="manual-issuance-mode-switch mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeExisting" value="existing" checked>
                  <label class="form-check-label" for="manualResidentModeExisting">
                    <?php if ($isIdIssuanceTrackerView): ?>
                      <span class="manual-source-choice-icon"><i class="fas fa-user-check"></i></span>
                      <span class="manual-source-choice-copy">
                        <strong>Registered Resident</strong>
                        <small>Search the masterlist and automatically fill verified resident information.</small>
                      </span>
                      <span class="manual-source-choice-check"><i class="fa-solid fa-circle-check"></i></span>
                    <?php else: ?>
                      <i class="fas fa-user-check"></i>Registered Resident
                    <?php endif; ?>
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeWalkin" value="walkin">
                  <label class="form-check-label" for="manualResidentModeWalkin">
                    <?php if ($isIdIssuanceTrackerView): ?>
                      <span class="manual-source-choice-icon"><i class="fas fa-user-pen"></i></span>
                      <span class="manual-source-choice-copy">
                        <strong>Walk-in Resident</strong>
                        <small>Encode a new applicant who does not have a registered resident record.</small>
                      </span>
                      <span class="manual-source-choice-check"><i class="fa-solid fa-circle-check"></i></span>
                    <?php else: ?>
                      <i class="fas fa-user-pen"></i>Walk-in / Not Registered
                    <?php endif; ?>
                  </label>
                </div>
                <?php if ($isIdIssuanceTrackerView): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeRenewal" value="renewal">
                    <label class="form-check-label" for="manualResidentModeRenewal">
                      <span class="manual-source-choice-icon"><i class="fa-solid fa-id-card-clip"></i></span>
                      <span class="manual-source-choice-copy">
                        <strong>ID Renewal / Re-issue</strong>
                        <small>Link a registered resident to renew an expiring ID or re-issue a replacement card.</small>
                      </span>
                      <span class="manual-source-choice-check"><i class="fa-solid fa-circle-check"></i></span>
                    </label>
                  </div>
                <?php endif; ?>
              </div>

              <div id="manualResidentLookupWrap">
                <div class="row g-3 align-items-end">
                  <div class="col-12">
                    <label class="form-label fw-semibold small">Search Registered Resident</label>
                    <div class="input-group">
                      <input type="text" id="manualResidentSearchInput" class="form-control" placeholder="Search by Resident ID, name, or ID number">
                      <button type="button" class="btn btn-outline-secondary" id="manualResidentSearchBtn">
                        <i class="fas fa-search me-1"></i>Search
                      </button>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="manual-search-empty" id="manualResidentSearchHint">
                      Search a registered resident to auto-fill the form, or switch to walk-in mode to encode an unregistered resident.
                    </div>
                  </div>
                </div>
                <div class="mt-3 d-none" id="manualResidentResultsWrap">
                  <div class="manual-issuance-card-title mb-2">
                    <h6>Search Results</h6>
                    <span>Choose the resident record that matches the handwritten form.</span>
                  </div>
                  <div id="manualResidentResults" class="manual-resident-results"></div>
                </div>
              </div>

              <div id="manualSelectedResident" class="manual-selected-resident d-none mt-3">
                <strong id="manualSelectedResidentName">No resident linked yet</strong>
                <p id="manualSelectedResidentMeta">Linked registered resident details will auto-fill this form and can still be edited before submission.</p>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="manualClearSelectedResidentBtn">
                  <i class="fas fa-unlink me-1"></i>Unlink Resident
                </button>
              </div>
            </div>

            <div class="manual-issuance-card <?= $isIdIssuanceTrackerView ? 'd-none' : '' ?>" <?= !$isIdIssuanceTrackerView ? 'data-manual-step-panel="1"' : '' ?>>
              <div class="manual-issuance-card-title">
                <h6>Document Setup</h6>
                <span>Choose the form first. The matching fields and next step summary will update automatically.</span>
              </div>
              <div class="row g-3">
                <div class="col-12">
                  <label for="manualDocumentType" class="form-label fw-semibold small">Certificate / Clearance Type <span class="text-danger">*</span></label>
                  <select id="manualDocumentType" class="form-select" required>
                    <option value="">Select a manual issuance form</option>
                  </select>
                </div>
                <div class="col-12 d-none" id="manualValidityWrap">
                  <label for="manualValidityDate" class="form-label fw-semibold small" id="manualValidityLabel">Validity Period</label>
                  <select id="manualValidityDate" class="form-select">
                    <option value="">Select validity period</option>
                  </select>
                  <div class="form-text" id="manualValidityHelp">Choose the validity period that will be reflected in the issued document.</div>
                </div>
              </div>
            </div>

            <div class="manual-issuance-card" data-manual-step-panel="2">
              <div class="manual-issuance-card-title">
                <h6><?= $isIdIssuanceTrackerView ? '2. Personal Information — Basic Info' : '2. Personal Basic Information' ?></h6>
                <span><?= $isIdIssuanceTrackerView ? 'Fields marked * are required for Barangay ID issuance.' : 'Enter the resident details exactly as they should appear on the certificate.' ?></span>
              </div>
              <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                  <label for="manualLastName" class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                  <input type="text" id="manualLastName" class="form-control" required>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualFirstName" class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                  <input type="text" id="manualFirstName" class="form-control" required>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualMiddleName" class="form-label fw-semibold small">Middle Name</label>
                  <input type="text" id="manualMiddleName" class="form-control">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualSuffix" class="form-label fw-semibold small">Suffix</label>
                  <select id="manualSuffix" class="form-select">
                    <option value="">None</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                  </select>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualBirthdate" class="form-label fw-semibold small">Birthdate <span class="text-danger <?= $isIdIssuanceTrackerView ? '' : 'd-none' ?>" id="manualBirthdateRequiredMark">*</span></label>
                  <?php if ($isIdIssuanceTrackerView): ?>
                    <div class="manual-birthdate-dropdowns">
                      <select id="manualBirthMonth" class="form-select" required aria-label="Birth month">
                        <option value="">Month</option>
                        <?php foreach (['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'] as $monthValue => $monthLabel): ?>
                          <option value="<?= $monthValue ?>"><?= $monthLabel ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select id="manualBirthDay" class="form-select" required aria-label="Birth day">
                        <option value="">Day</option>
                      </select>
                      <select id="manualBirthYear" class="form-select" required aria-label="Birth year">
                        <option value="">Year</option>
                        <?php for ($birthYear = (int)date('Y'); $birthYear >= (int)date('Y') - 120; $birthYear--): ?>
                          <option value="<?= $birthYear ?>"><?= $birthYear ?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    <input type="hidden" id="manualBirthdate">
                  <?php else: ?>
                    <input type="date" id="manualBirthdate" class="form-control" max="<?= date('Y-m-d') ?>" data-date-modal-style="calendar">
                  <?php endif; ?>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualSex" class="form-label fw-semibold small">Sex <span class="text-danger">*</span></label>
                  <select id="manualSex" class="form-select">
                    <option value="">Select sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualCivilStatus" class="form-label fw-semibold small">Civil Status <span class="text-danger">*</span></label>
                  <select id="manualCivilStatus" class="form-select">
                    <option value="">Select civil status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                  </select>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualContactNumber" class="form-label fw-semibold small">Contact Number <span class="text-danger">*</span></label>
                  <input type="text" id="manualContactNumber" class="form-control" placeholder="09XXXXXXXXX">
                </div>
                <div class="<?= $isIdIssuanceTrackerView ? 'col-12' : 'col-md-6' ?>">
                  <label for="manualBirthplace" class="form-label fw-semibold small">Birthplace <span class="text-danger" id="manualBirthplaceRequiredMark">*</span></label>
                  <input type="text" id="manualBirthplace" class="form-control" placeholder="Place of birth">
                </div>
                <div class="col-md-3 <?= $isIdIssuanceTrackerView ? 'd-none' : '' ?>">
                  <label for="manualOccupation" class="form-label fw-semibold small">Occupation <span class="text-danger d-none" id="manualOccupationRequiredMark">*</span></label>
                  <input type="text" id="manualOccupation" class="form-control" placeholder="Occupation">
                </div>
                <div class="col-md-3 <?= $isIdIssuanceTrackerView ? 'd-none' : '' ?>">
                  <label for="manualReligion" class="form-label fw-semibold small">Religion <span class="text-danger d-none" id="manualReligionRequiredMark">*</span></label>
                  <input type="text" id="manualReligion" class="form-control" placeholder="Religion">
                </div>
                  <div class="col-12">
                    <label for="manualAddressLine" class="form-label fw-semibold small">Address <span class="text-danger">*</span></label>
                    <input type="text" id="manualAddressLine" class="form-control" required placeholder="House number, street, phase, or subdivision">
                  </div>
                  <div class="col-md-6">
                    <label for="manualAreaNumber" class="form-label fw-semibold small">Area Number <span class="text-danger">*</span></label>
                    <div class="manual-area-picker-field">
                      <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                      <input type="text" id="manualAreaNumber" class="form-control" required readonly placeholder="Choose an area and view covered places" role="button" aria-haspopup="dialog" aria-controls="manualAreaNumberModal">
                      <i class="fa-solid fa-chevron-right manual-area-picker-chevron" aria-hidden="true"></i>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label for="manualBarangay" class="form-label fw-semibold small">Barangay</label>
                    <input type="text" id="manualBarangay" class="form-control manual-address-default" value="San Jose" readonly aria-describedby="manualLocalityDefaultHelp">
                  </div>
                  <div class="col-md-6">
                    <label for="manualCity" class="form-label fw-semibold small">City / Municipality</label>
                    <input type="text" id="manualCity" class="form-control manual-address-default" value="Rodriguez (Montalban)" readonly aria-describedby="manualLocalityDefaultHelp">
                  </div>
                  <div class="col-md-6">
                    <label for="manualProvince" class="form-label fw-semibold small">Province</label>
                    <input type="text" id="manualProvince" class="form-control manual-address-default" value="Rizal" readonly aria-describedby="manualLocalityDefaultHelp">
                  </div>
                  <div class="col-12">
                    <div class="form-text mt-0" id="manualLocalityDefaultHelp"><i class="fa-solid fa-lock me-1"></i>Barangay, municipality, and province are system defaults.</div>
                  </div>
                  <input type="hidden" id="manualFullAddress" required>
              </div>
            </div>

            <div class="manual-issuance-card" data-manual-step-panel="2">
              <div class="manual-issuance-card-title">
                <h6><?= $isIdIssuanceTrackerView ? '2. Personal Information — Sector Membership' : '2. Sector Membership' ?></h6>
                <span>Select every applicable sector. Linked residents retain the membership recorded in the masterlist.</span>
              </div>
              <div class="row g-2" id="manualSectorMembershipWrap">
                <?php foreach (['PWD', 'Senior Citizen', 'Student', 'Indigenous People', 'Single Parent'] as $sectorOption): ?>
                  <?php $sectorId = preg_replace('/[^A-Za-z0-9]/', '', $sectorOption); ?>
                  <div class="col-md-6 col-lg-4">
                    <div class="form-check">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        id="manualSector<?= htmlspecialchars($sectorId, ENT_QUOTES, 'UTF-8') ?>"
                        data-manual-sector="<?= htmlspecialchars($sectorOption, ENT_QUOTES, 'UTF-8') ?>"
                      >
                      <label class="form-check-label" for="manualSector<?= htmlspecialchars($sectorId, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($sectorOption, ENT_QUOTES, 'UTF-8') ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="manual-issuance-card" data-manual-step-panel="<?= $isIdIssuanceTrackerView ? '2' : '3' ?>">
              <div class="manual-issuance-card-title">
                <h6 id="manualSpecificFieldsTitle"><?= $isIdIssuanceTrackerView ? '2. Personal Information — Emergency Contact' : '3. Document Specific Details' ?></h6>
                <span id="manualSpecificFieldsHint">Select a certificate or clearance type to load its manual encoding fields.</span>
              </div>
              <?php if (!$isIdIssuanceTrackerView): ?>
                <div class="row g-3 mb-3">
                  <div class="col-12">
                    <label for="manualPurpose" class="form-label fw-semibold small">Purpose / Request For <span class="text-danger">*</span></label>
                    <div class="d-none mb-2" id="manualPurposePresetWrap">
                      <select id="manualPurposePreset" class="form-select">
                        <option value="">Select purpose</option>
                        <option value="Local Employment">Local Employment</option>
                        <option value="Loan Application">Loan Application</option>
                        <option value="Bailbond">Bailbond</option>
                        <option value="Postal ID Requirement">Postal ID Requirement</option>
                        <option value="Tesda Requirement">Tesda Requirement</option>
                        <option value="Personal Collection">Personal Collection</option>
                        <option value="School Requirement">School Requirement</option>
                        <option value="Bank Requirement (open account)">Bank Requirement (open account)</option>
                        <option value="__other__">Other</option>
                      </select>
                      <div class="form-text">This manual document uses the residency certification layout. Only the purpose changes.</div>
                    </div>
                    <input type="text" id="manualPurpose" class="form-control" required placeholder="State the exact purpose shown on the issued document">
                  </div>
                </div>
              <?php endif; ?>
              <div id="manualDynamicFields" class="row g-3"></div>
            </div>

            <?php if ($isIdIssuanceTrackerView): ?>
              <div class="manual-issuance-card" data-manual-id-step-panel="3">
                <div class="manual-issuance-card-title">
                  <h6>3. ID Photo</h6>
                  <span>Capture or confirm the square photo that will appear on the Barangay ID.</span>
                </div>
                <div id="manualBarangayIdPhotoStepMount"></div>
              </div>

              <div class="manual-issuance-card" data-manual-id-step-panel="4">
                <div class="manual-issuance-card-title">
                  <h6>4. Approval with Validity Selection</h6>
                  <span>Confirm how long the approved Barangay ID remains valid.</span>
                </div>
                <aside class="manual-before-approve" aria-labelledby="manualBeforeApproveTitle">
                  <div class="manual-before-approve-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                  <div>
                    <h6 id="manualBeforeApproveTitle">Before you approve</h6>
                    <p>Make sure the Barangay ID record is complete and ready for its initial preview.</p>
                    <div class="manual-before-approve-list">
                      <span><i class="fa-solid fa-check"></i>Resident identity and address were verified</span>
                      <span><i class="fa-solid fa-check"></i>Emergency contact details are reachable</span>
                      <span><i class="fa-solid fa-check"></i>ID photo is clear, centered, and recent</span>
                      <span><i class="fa-solid fa-check"></i>Selected validity period is correct</span>
                    </div>
                  </div>
                </aside>
                <div class="manual-validity-selection mt-4">
                  <div id="manualValidityProcessMount"></div>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!$isIdIssuanceTrackerView): ?>
              <div class="manual-issuance-card" data-manual-step-panel="4">
                <div class="manual-issuance-card-title">
                  <h6>4. Before You Approve</h6>
                  <span>Confirm the encoded details and choose the certificate validity before previewing.</span>
                </div>
                <aside class="manual-before-approve" aria-labelledby="manualCertificateBeforeApproveTitle">
                  <div class="manual-before-approve-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                  <div>
                    <h6 id="manualCertificateBeforeApproveTitle">Confirmation checklist</h6>
                    <p>Review the source form against the information entered in the previous steps.</p>
                    <div class="manual-before-approve-list">
                      <span><i class="fa-solid fa-check"></i>Resident identity, area number, and address were verified</span>
                      <span><i class="fa-solid fa-check"></i>Sector membership and document-specific details are correct</span>
                      <span><i class="fa-solid fa-check"></i>Selected validity period is correct</span>
                    </div>
                  </div>
                </aside>
                <div class="manual-validity-selection mt-4" id="manualCertificateValidityMount"></div>
              </div>
            <?php endif; ?>

            <div class="manual-issuance-card d-none" id="manualFeeWrap" <?= !$isIdIssuanceTrackerView ? 'data-manual-step-panel="3" data-manual-optional-panel="clearance"' : '' ?>>
              <div class="manual-issuance-card-title">
                <h6>Tagged Clearance Fees</h6>
                <span>Tagged fees are only used for paid requests that continue to the finance step for walk-in payment recording.</span>
              </div>
              <div id="manualFeeList" class="manual-fee-list"></div>
              <div class="manual-fee-total">
                <span>Total Tagged Amount</span>
                <strong id="manualFeeTotal">PHP 0.00</strong>
              </div>
            </div>

            <div id="manualFormAlert" class="alert alert-warning d-none"></div>

            <div class="manual-issuance-card mt-3" data-manual-step-panel="5">
              <div class="manual-issuance-card-title">
                <h6><?= $isIdIssuanceTrackerView ? '5. Initial Barangay ID Preview' : '5. Editable Document Preview' ?></h6>
                <span><?= $isIdIssuanceTrackerView ? 'Review the actual front and back ID appearance before creating the record.' : 'Preview the document, edit its highlighted fields if needed, then approve.' ?></span>
              </div>
            <?php if ($isIdIssuanceTrackerView): ?>
              <div class="manual-id-inline-preview" id="manualIdInlinePreview" aria-live="polite">
                <div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Preparing ID preview…</div>
              </div>
            <?php else: ?>
            <div class="manual-id-inline-preview mb-3" id="manualDocumentInlinePreview" aria-live="polite">
              <div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Document preview will appear here automatically on this step.</div>
            </div>
            <div class="d-flex flex-wrap justify-content-end gap-2">
              <button type="submit" class="btn btn-primary" id="manualSubmitBtn" disabled>
                <i class="fas fa-paper-plane me-1"></i><?= $isIdIssuanceTrackerView ? 'Approve & Create ID Record' : 'Approve Certificate' ?>
              </button>
            </div>
            <?php endif; ?>
            </div>

            <?php if ($isIdIssuanceTrackerView): ?>
              <div class="manual-id-wizard-controls mt-4">
                <button type="button" class="btn btn-outline-secondary" id="manualIdWizardBack">
                  <i class="fa-solid fa-arrow-left me-1"></i>Back
                </button>
                <span class="manual-id-wizard-position" id="manualIdWizardPosition">Step 1 of 5</span>
                <button type="button" class="btn btn-primary" id="manualIdWizardNext">
                  Continue<i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
                <button type="submit" class="btn btn-primary d-none" id="manualSubmitBtn" disabled>
                  <i class="fas fa-paper-plane me-1"></i>Approve &amp; Create ID Record
                </button>
              </div>
            <?php else: ?>
              <div class="manual-id-wizard-controls mt-4">
                <button type="button" class="btn btn-outline-secondary" id="manualIdWizardBack"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
                <span class="manual-id-wizard-position" id="manualIdWizardPosition">Step 1 of 5</span>
                <button type="button" class="btn btn-primary" id="manualIdWizardNext">Continue<i class="fa-solid fa-arrow-right ms-1"></i></button>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <!-- â”€â”€ FEE CHANGE REQUESTS PANEL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div id="feeChangePanel" class="d-none bg-white p-4 rounded-4 shadow-sm border certificate-tracker-shell">

      <!-- Sub-tabs -->
      <ul class="nav nav-tabs mb-4" id="feeChangeSubTabs">
        <li class="nav-item">
          <button class="nav-link active" id="subTabAddFeeType" type="button">
            <i class="fas fa-plus me-1"></i>Request New Fee Type
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="subTabEditPrice" type="button">
            <i class="fas fa-pen me-1"></i>Request Price Edit
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="subTabMyRequests" type="button">
            <i class="fas fa-list me-1"></i>Submitted Requests
          </button>
        </li>
      </ul>

      <!-- Sub-panel: Request New Fee Type -->
      <div id="fcrAddPanel">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="border rounded-3 p-3 bg-light">
              <h6 class="fw-semibold mb-3"><i class="fas fa-plus-circle me-1 text-primary"></i>Request New Fee Type</h6>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Fee Name <span class="text-danger">*</span></label>
                <input type="text" id="fcrAddName" class="form-control" placeholder="e.g. Inspection Fee">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Proposed Amount (₱)</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" id="fcrAddAmount" class="form-control" value="0.00" min="0" step="0.01">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Notes / Justification</label>
                <textarea id="fcrAddNotes" class="form-control" rows="3" placeholder="Why is this fee type needed?"></textarea>
              </div>
              <div id="fcrAddError" class="alert alert-danger d-none py-2 small mb-3" data-modal-inline="true"></div>
              <div id="fcrAddSuccess" class="alert alert-success d-none py-2 small mb-3" data-modal-inline="true"></div>
              <button type="button" class="btn btn-primary w-100" id="fcrAddSubmitBtn">
                <i class="fas fa-paper-plane me-1"></i>Submit Request
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sub-panel: Request Price Edit -->
      <div id="fcrEditPanel" class="d-none">
        <div class="row g-4">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-semibold mb-0">Current Fee Catalog</h6>
              <button class="btn btn-sm btn-outline-secondary" id="fcrEditRefreshBtn" title="Refresh">
                <i class="fa-solid fa-arrows-rotate"></i>
              </button>
            </div>
            <div class="table-responsive fee-catalog-table-shell">
              <table class="table table-sm table-hover align-middle fee-catalog-table">
                <thead class="table-light">
                  <tr>
                    <th>Fee Name</th>
                    <th>Current Amount</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody id="fcrEditCatalogBody">
                  <tr><td colspan="4" class="text-center text-muted py-3">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-12">
            <div id="fcrEditSuccess" class="alert alert-success d-none py-2 small mb-3" data-modal-inline="true"></div>
          </div>
        </div>
      </div>

      <!-- Sub-panel: Submitted Requests -->
      <div id="fcrListPanel" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-semibold mb-0">My Submitted Requests</h6>
          <button class="btn btn-sm btn-outline-secondary" id="fcrListRefreshBtn" title="Refresh">
            <i class="fa-solid fa-arrows-rotate"></i>
          </button>
        </div>
        <div class="table-responsive compact-admin-table-shell">
          <table class="table align-middle mb-0 compact-admin-table compact-admin-table--wide">
            <thead>
              <tr class="table-light">
                <th>Type</th>
                <th>Fee Name</th>
                <th>Proposed Amount</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Submitted</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="fcrListBody">
              <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <div class="modal fade" id="fcrEditModal" tabindex="-1" aria-labelledby="fcrEditFormTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header">
            <h5 class="modal-title fw-semibold" id="fcrEditFormTitle"><i class="fas fa-pen me-2 text-warning"></i>Request Price Edit</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="fcrEditFormWrap">
            <input type="hidden" id="fcrEditFeeTypeId">
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="fcrEditFeeName">Fee Name</label>
              <input type="text" id="fcrEditFeeName" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="fcrEditCurrentAmount">Current Amount</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="text" id="fcrEditCurrentAmount" class="form-control" readonly>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="fcrEditProposedAmount">Proposed Amount (₱) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" id="fcrEditProposedAmount" class="form-control" min="0" step="0.01">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small" for="fcrEditNotes">Notes / Justification</label>
              <textarea id="fcrEditNotes" class="form-control" rows="3" placeholder="Why should this amount be changed?"></textarea>
            </div>
            <div id="fcrEditError" class="alert alert-danger d-none py-2 small mb-0" data-modal-inline="true"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" id="fcrEditCancelBtn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-warning" id="fcrEditSubmitBtn">
              <i class="fas fa-paper-plane me-1"></i>Submit Request
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="manualAreaNumberModal" tabindex="-1" aria-labelledby="manualAreaNumberModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="manualAreaNumberModalLabel">Barangay Area Guide</h5>
          <p class="text-muted small mb-0">Choose the area that covers the resident's address.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="manual-area-options">
          <?php foreach ([
            'Area 01' => 'San Jose Proper',
            'Area 1A' => 'Litex Village, Abatex Christine Creek, Med. Heights',
            'Area 02' => 'VFW, Amychelle, Christine Villa Parnshey, Villa Ana, Zaniga Farm',
            'Area 03' => 'Relocation',
            'Area 04' => 'Kasiglahan Phase 1-B, Phase 1-C, Phase 1-D, Phase 1-M, Phase 1-A',
            'Area 05' => 'Kasiglahan Phase 1-K, Phase 1K1, Phase 1K2, Phase 1-E, Phase 1-G',
            'Area 06' => 'Sub-Urban, Metro Manila Hills',
          ] as $areaOption => $coveredPlaces): ?>
            <button type="button" class="manual-area-option" data-manual-area-option="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>">
              <i class="fa-solid fa-location-dot"></i>
              <span class="manual-area-option-copy">
                <strong><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($coveredPlaces, ENT_QUOTES, 'UTF-8') ?></small>
              </span>
              <i class="fa-solid fa-check manual-area-option-check"></i>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade manual-photo-modal" id="manualBarangayIdPhotoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Barangay ID Photo</h5>
          <p class="text-muted small mb-0">Take a resident photo, adjust it inside the square guide, then crop and save it for the Barangay ID.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="manualBarangayIdPhotoStatus" class="alert alert-info py-2 small d-none"></div>

        <div id="manualBarangayIdCameraStage">
          <p class="manual-photo-stage-copy">
            Position the resident inside the square frame. If more than one camera is available, choose the correct one from the dropdown before capturing.
          </p>
          <div class="manual-photo-controls manual-photo-controls--camera">
            <label for="manualBarangayIdCameraSelect">Camera source</label>
            <select id="manualBarangayIdCameraSelect" class="form-select">
              <option value="">Detecting available cameras...</option>
            </select>
            <p class="manual-photo-control-hint">The camera list becomes selectable after the browser allows camera access.</p>
          </div>
          <div class="manual-photo-workspace" id="manualBarangayIdCameraWorkspace">
            <video id="manualBarangayIdCameraVideo" playsinline autoplay muted></video>
            <div id="manualBarangayIdCameraEmpty" class="manual-photo-empty-state">
              Start the camera to capture the resident photo. If you have multiple cameras, use the dropdown above to switch sources after access is allowed.
            </div>
            <div class="manual-photo-frame"></div>
          </div>
        </div>

        <div id="manualBarangayIdCropStage" class="d-none">
          <p class="manual-photo-stage-copy">
            Drag the photo and use the zoom slider until the resident fits well inside the visible square. The darkened area will not be included.
          </p>
          <div class="manual-photo-workspace" id="manualBarangayIdCropWorkspace">
            <img id="manualBarangayIdCropImage" alt="Barangay ID crop preview">
            <div id="manualBarangayIdCropEmpty" class="manual-photo-empty-state d-none">
              Capture a photo first so it can be cropped and saved.
            </div>
            <div class="manual-photo-frame" id="manualBarangayIdCropFrame"></div>
          </div>
          <div class="manual-photo-controls">
            <label for="manualBarangayIdZoomRange">Zoom</label>
            <input type="range" id="manualBarangayIdZoomRange" min="100" max="400" step="1" value="100">
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex flex-wrap gap-2">
        <div class="manual-photo-footer-copy" id="manualBarangayIdPhotoFooterCopy">
          Allow camera access when prompted. If more than one webcam is connected, you can switch cameras from the dropdown before capturing.
        </div>
        <button type="button" class="btn btn-outline-secondary" id="manualBarangayIdUseLinkedPhotoBtn">Use Linked Photo</button>
        <button type="button" class="btn btn-outline-secondary" id="manualBarangayIdStartCameraBtn">Start Camera</button>
        <button type="button" class="btn btn-outline-secondary d-none" id="manualBarangayIdRetakePhotoBtn">Retake</button>
        <button type="button" class="btn btn-primary" id="manualBarangayIdCapturePhotoBtn" disabled>Capture Photo</button>
        <button type="button" class="btn btn-success d-none" id="manualBarangayIdSavePhotoBtn">Crop and Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="fcrCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100">Cancel Fee Change Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body text-center">
        <p class="mb-3">Are you sure you want to cancel this fee change request?</p>
        <div id="fcrCancelModalError" class="alert alert-danger d-none py-2 small mb-0"></div>
      </div>
      <div class="modal-footer action-split border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="fcrCancelModalBackBtn">Back</button>
        <button type="button" class="btn btn-danger" id="fcrCancelModalConfirmBtn">Cancel Request</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content p-3" id="actionForm" enctype="multipart/form-data">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100" id="actionModalTitle">Update Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body">
        <input type="hidden" id="actionType" name="action">
        <input type="hidden" id="actionRequestId" name="request_id">

        <div id="actionValidityWrap" class="d-none mb-3">
          <label class="form-label" id="actionValidityLabel">Validity Period</label>
          <select id="actionValidity" name="document_validity" class="form-select">
            <option value="">Select validity period</option>
          </select>
          <div class="form-text" id="actionValidityHelp">Choose the validity period that will be reflected in the issued document.</div>
        </div>

        <div id="actionPrompt" class="d-none mb-3"></div>

        <div id="actionReasonWrap" class="d-none mb-3">
          <label class="form-label">Reason</label>
          <textarea id="actionReason" name="reason" class="form-control" rows="3"></textarea>
        </div>

        <div id="actionAmountWrap" class="d-none mb-3">
          <label class="form-label">Amount</label>
          <input id="actionAmount" name="amount" type="number" min="0" step="0.01" class="form-control">
        </div>

        <div id="actionOrWrap" class="d-none mb-3">
          <label class="form-label">OR Number</label>
          <input id="actionOr" name="or_number" type="text" class="form-control">
        </div>

        <div id="actionIssuedWrap" class="d-none mb-3">
          <label class="form-label">Issued File (optional)</label>
          <input id="actionIssued" name="issued_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>

        <div id="actionBusinessApprovalWrap" class="d-none mb-3">
          <label class="form-label">Type of Approval</label>
          <input id="actionBusinessApproval" name="business_approval_type" type="hidden">
          <div id="actionBusinessApprovalOptions" class="d-grid gap-2">
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="not_banned">
              <span class="action-business-approval-copy">Not among those business or trade activities being banned to be established in this Barangay</span>
            </label>
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="no_objection">
              <span class="action-business-approval-copy">Interposes no objection for the issuance of the corresponding Business Permit being applied for.</span>
            </label>
            <label class="action-business-approval-card">
              <input class="action-business-approval-option" type="checkbox" value="temporary_clearance">
              <span class="action-business-approval-copy">Recommendations only the issuance of &quot;Temporary Barangay Clearance&quot; subject for revocation anytime provided that the requirements under existing Barangay Ordinance, Rules and Regulations should be complied with, otherwise this Barangay should take the necessary actions within legal bounds to stop its continued operations.</span>
            </label>
          </div>
        </div>

        <div id="actionPlateWrap" class="d-none mb-3">
          <label class="form-label">Plate Number <span class="text-danger">*</span></label>
          <input id="actionPlate" name="plate_number" type="text" class="form-control" placeholder="Enter the plate number to issue" autocomplete="off">
        </div>

        <div id="actionModalError" class="alert alert-danger d-none mb-0"></div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
        <button type="button" id="actionCancelBtn" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Return</button>
        <button type="submit" id="actionSubmitBtn" class="btn btn-primary flex-fill">Submit</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Filter Requests</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr>
      <div class="modal-body">
        <div class="mb-3">
          <label class="fw-bold small mb-2">Date Range</label>
          <div class="row g-2">
            <div class="col-6">
              <input type="date" class="form-control" id="filterDateFrom" aria-label="From date">
            </div>
            <div class="col-6">
              <input type="date" class="form-control" id="filterDateTo" aria-label="To date">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="fw-bold small mb-2">Type of Request</label>
          <div id="filterDocumentTypeList" class="d-grid gap-2"></div>
        </div>
        <div class="mb-3">
          <label class="fw-bold small mb-2">Area Number</label>
          <div id="filterAreaList" class="d-grid gap-2"></div>
        </div>
        <div class="mb-1">
          <label class="fw-bold small mb-2">Sector Membership</label>
          <div id="filterSectorList" class="d-grid gap-2"></div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnApplyFilter">Apply Filter</button>
        <button type="button" class="btn btn-warning" id="btnResetModalFilters"><i class="fas fa-undo"></i>&nbsp;Reset</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Columns</h5>
      </div>
      <div class="modal-body">
        <div class="row g-2" id="tableColumnsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="viewModalBackBtn" class="btn btn-outline-secondary d-none">Back</button>
        </div>
        <div id="viewModalActions" class="d-flex flex-wrap gap-2 justify-content-center flex-grow-1"></div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="viewModalNextBtn" class="btn btn-primary">Next</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentProofTitle">Document Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="paymentProofReturnBtn" class="btn btn-secondary me-auto d-none">Return</button>
        <button type="button" id="paymentProofRegenerateBtn" class="btn btn-warning d-none"><i class="fas fa-rotate me-1"></i>Regenerate Document</button>
        <button type="button" id="paymentProofPrintBtn" class="btn btn-outline-dark d-none">Print</button>
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" id="paymentProofReleaseBtn" class="btn btn-success d-none">Release</button>
        <button type="button" id="paymentProofCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="regenerateIssuedConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-rotate me-2 text-warning"></i>Regenerate Issued Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Regenerate this issued document using the current admin settings?</p>
        <div class="alert alert-warning mb-0" role="alert">
          The current generated file will be replaced.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="regenerateIssuedCancelBtn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="regenerateIssuedConfirmBtn">
          <i class="fas fa-rotate me-1"></i>Regenerate Document
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="idPrintProcessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Print Barangay ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-grid gap-3">
          <div id="idPrintProcessPreview" class="w-100 text-center"></div>
          <hr class="my-0">
          <p id="idPrintProcessStep" class="fw-semibold mb-1">Step 1 of 3</p>
          <p id="idPrintProcessCopy" class="text-muted mb-0">Print the front side of the Barangay ID first.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="idPrintProcessReturnBtn" class="btn btn-secondary me-auto">Return</button>
        <button type="button" id="idPrintProcessReprintBtn" class="btn btn-outline-dark">Reprint</button>
        <button type="button" id="idPrintProcessPrimaryBtn" class="btn btn-primary">Print Front</button>
      </div>
    </div>
  </div>
</div>

<style>
  .tracker-action-dropdown { display: inline-block; white-space: nowrap; }
  .tracker-action-dropdown .dropdown-menu {
    --bs-dropdown-link-hover-bg: #f8fafc;
    --bs-dropdown-link-hover-color: #0f172a;
    --bs-dropdown-link-active-bg: #f8fafc;
    --bs-dropdown-link-active-color: #0f172a;
    min-width: 11.5rem;
    padding: .4rem;
    border: 1px solid #e2e8f0;
    border-radius: .65rem;
  }
  .tracker-action-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    border-radius: .4rem;
    padding: .55rem .7rem;
    background-color: transparent !important;
    color: #1f2937 !important;
    font-size: .875rem;
  }
  .tracker-action-dropdown .dropdown-item.action-effect-view {
    background-color: transparent !important;
    color: #1f2937 !important;
  }
  .tracker-action-dropdown .dropdown-menu .dropdown-item:hover,
  .tracker-action-dropdown .dropdown-menu .dropdown-item:focus,
  .tracker-action-dropdown .dropdown-menu .dropdown-item:active,
  .tracker-action-dropdown .dropdown-menu .dropdown-item.active {
    background-color: #f8fafc !important;
    color: #0f172a !important;
  }
  .tracker-action-dropdown .dropdown-item:focus-visible {
    outline: 2px solid rgba(13, 110, 253, .35);
    outline-offset: -2px;
  }
  #paymentProofWrap iframe {
    width: 100%;
    height: 70vh;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
  }
  #paymentProofWrap img {
    max-width: 100%;
    max-height: 70vh;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
  }
  #paymentProofWrap .doc-viewer-loading {
    min-height: 70vh;
    display: grid;
    place-items: center;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #475569;
    padding: 24px;
  }
  #paymentProofWrap .doc-viewer-loading__inner {
    display: grid;
    gap: 12px;
    justify-items: center;
  }
  #paymentProofWrap .doc-viewer-loading__spinner {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 3px solid #dbeafe;
    border-top-color: #2563eb;
    animation: doc-viewer-spin .8s linear infinite;
  }
  #paymentProofWrap .doc-viewer-loading__label {
    font-size: .95rem;
    font-weight: 600;
  }
  #idPrintProcessPreview .barangay-id-card {
    width: min(100%, 720px);
    margin-inline: auto;
  }
  @keyframes doc-viewer-spin {
    to { transform: rotate(360deg); }
  }
</style>

<div class="modal fade" id="submittedFileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="submittedFileTitle">Submitted Attachment Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="submittedFileWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="submittedFileReturnBtn" class="btn btn-secondary d-none">Return</button>
        <a id="submittedFileOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open Attachment in New Tab</a>
        <button type="button" id="submittedFileCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade tracker-profile-modal" id="residentProfileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="div-modalSizing">
    <div class="modal-content border-0 rounded-2 p-4">
      <div class="modal-header border-0">
        <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning">#&mdash;</span></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Personal Information</h5>
          </div>

          <div class="row g-3 align-items-center">
            <div class="col-12">
              <div class="row g-3">
                <div class="col-md-12 col-lg-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalName" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Sex:</p><p id="txt-modalSex" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Religion:</p><p id="txt-modalReligion" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Age:</p><p id="txt-modalAge" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Civil Status:</p><p id="txt-modalCivilStatus" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Occupation:</p><p id="txt-modalOccupation" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Date of Birth:</p><p id="txt-modalDob" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Head of Family:</p><p id="txt-modalHeadOfFam" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Voter Status:</p><p id="txt-modalVoterStatus" class="fw-bold mb-0">&mdash;</p></div>
                <div class="col-12">
                  <p class="text-muted small mb-0">Sector Membership:</p>
                  <p id="txt-modalSectorMembership" class="fw-bold mb-0">&mdash;</p>
                  <div id="div-modalSectorProofStatuses" class="mt-2 d-flex flex-wrap gap-2"></div>
                  <div id="div-modalSectorProofHint" class="text-muted small mt-1">
                    Sector proof status is based on uploaded documents tagged per sector.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Emergency Contact</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalEmergencyFullName" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Contact Number:</p><p id="txt-modalEmergencyContactNumber" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Relationship:</p><p id="txt-modalEmergencyRelationship" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-12"><p class="text-muted small mb-0">Address:</p><p id="txt-modalEmergencyAddress" class="fw-bold mb-0">&mdash;</p></div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Address Information</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4" id="addr-unit-number"><p class="text-muted small mb-0">Unit Number:</p><p id="txt-modalUnitNumber" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4" id="addr-house-number"><p class="text-muted small mb-0">House Number:</p><p id="txt-modalHouseNum" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4" id="addr-street-name"><p class="text-muted small mb-0">Street Name:</p><p id="txt-modalStreetName" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4" id="addr-phase-number"><p class="text-muted small mb-0">Phase:</p><p id="txt-modalPhaseNumber" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4" id="addr-subdivision"><p class="text-muted small mb-0">Subdivision:</p><p id="txt-modalSubdivision" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4" id="addr-area-number"><p class="text-muted small mb-0">Area Number:</p><p id="txt-modalAreaNumber" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Barangay:</p><p id="txt-modalBarangay" class="fw-bold mb-0">Barangay San Jose</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Municipality / City:</p><p id="txt-modalMunicipalityCity" class="fw-bold mb-0">Rodriguez (Montalban)</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Province:</p><p id="txt-modalProvince" class="fw-bold mb-0">Rizal</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Ownership:</p><p id="txt-modalHouseOwnership" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Type:</p><p id="txt-modalHouseType" class="fw-bold mb-0">&mdash;</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Residency Duration:</p><p id="txt-modalResidencyDuration" class="fw-bold mb-0">&mdash;</p></div>
          </div>
        </div>

        <div id="div-statusReadOnlyGroup" class="mt-4">
          <h5 class="fw-bold mb-2" style="color: #000;">Resident Status</h5>
          <div id="div-statusBanner" class="mb-0"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="residentProfileReturnBtn">Return</button>
      </div>
    </div>
  </div>
</div>

<!-- Fee Tagging Modal (Admin tags clearance fees per request) -->
<div class="modal fade" id="feeTaggingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-tags me-2 text-warning"></i>Tag Clearance Fees</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="feeTaggingRequestId">
        <input type="hidden" id="feeTaggingMode">
        <div id="feeTaggingBody">Loading…</div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="text-muted small">Check the fees that apply, adjust amounts as needed, then confirm.</span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary d-none" id="feeTaggingReturnBtn">Return</button>
          <button type="button" class="btn btn-primary" id="feeTaggingSubmitBtn">Confirm Fees &amp; Send to Payment</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Fee Catalog Management Modal (also available from CertificateTracker for admins) -->
<div class="modal fade" id="feeCatalogModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Clearance Fee Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-7">
            <h6 class="fw-semibold mb-2">Existing Fee Types</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr><th>Name</th><th>Default Amount</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody id="feeCatalogTableBody">
                  <tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-5">
            <h6 class="fw-semibold mb-2" id="feeCatalogFormTitle">Add Fee Type</h6>
            <input type="hidden" id="feeCatalogFeeTypeId">
            <div class="mb-2">
              <label class="form-label small mb-1">Fee Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="feeCatalogFeeName" placeholder="e.g. Inspection Fee">
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">Default Amount (₱)</label>
              <input type="number" class="form-control form-control-sm" id="feeCatalogDefaultAmount" value="0.00" min="0" step="0.01">
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="feeCatalogIsActive" checked>
              <label class="form-check-label small" for="feeCatalogIsActive">Active</label>
            </div>
            <button type="button" class="btn btn-primary btn-sm w-100" id="feeCatalogSaveBtn">Save Fee Type</button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-1"
              onclick="editFeeType('','',0,1);document.getElementById('feeCatalogFormTitle').textContent='Add Fee Type';">
              Clear / New
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
  window.BARANGAY_ID_SETTINGS = <?= json_encode($barangayIdOperationalSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  window.ISSUANCE_SETTINGS = <?= json_encode($issuanceOperationalSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  window.CLEARANCE_SETTINGS = <?= json_encode($clearanceOperationalSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  window.MANUAL_INDIGENCY_GOVERNMENT_DIRECTORY = <?= json_encode([
    'groups' => $manualGovernmentOfficialGroups,
    'positions' => $manualGovernmentPositionOptions,
    'officials' => $manualGovernmentOfficials,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-certificateTracker",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_certificate_tracker_v1",
    defaultHiddenIdxs: [1, 4]
  };
</script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Shared/barangayIdDigital.js?v=20260718-address-dedupe-33"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js?v=20260811-action-dropdown-neutral"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('paymentProofModal');
  const title = document.getElementById('paymentProofTitle');
  const openNew = document.getElementById('paymentProofOpenNew');
  const printBtn = document.getElementById('paymentProofPrintBtn');
  if (!modal || !title || !printBtn) return;
  const syncIssuedActions = () => {
    if (String(title.textContent || '').trim().toLowerCase() !== 'issued document') return;
    openNew?.classList.add('d-none');
    printBtn.classList.remove('d-none');
  };
  modal.addEventListener('show.bs.modal', syncIssuedActions);
  modal.addEventListener('shown.bs.modal', syncIssuedActions);
  printBtn.addEventListener('click', (event) => {
    if (String(title.textContent || '').trim().toLowerCase() !== 'issued document') return;
    const frame = document.querySelector('#paymentProofWrap iframe');
    if (!frame?.contentWindow) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    frame.contentWindow.focus();
    frame.contentWindow.print();
  }, true);
});
</script>
</body>
</html>
