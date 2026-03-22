<?php
require_once __DIR__ . '/../includes/admin_guard.php';

$certificateLaunchTab = strtolower(trim((string)($_GET['tab'] ?? '')));
$certificateLaunchDocument = strtolower(trim((string)($_GET['document'] ?? '')));
$certificateLaunchStage = strtolower(trim((string)($_GET['stage'] ?? '')));
$certificateLaunchEntry = strtolower(trim((string)($_GET['entry'] ?? '')));
$isIdIssuanceTrackerView = $certificateLaunchEntry === 'id_issuance';
$barangayIdAdminNavActive = 'applications';

if ($certificateLaunchStage === 'release') {
  $barangayIdAdminNavActive = 'release';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificate Tracker</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/barangayIdAdminNav.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <style>
    .certificate-tracker-shell {
      max-width: 1340px;
      margin: 0 auto;
    }
    .certificate-tracker-shell .admin-list-toolbar {
      overflow-x: visible;
      overflow-y: visible;
      flex-wrap: wrap;
      row-gap: 12px;
    }
    .certificate-tracker-shell .admin-list-tabs {
      gap: 12px;
      overflow: visible;
    }
    #docRequestsPanel {
      border-top-left-radius: 0 !important;
    }
    .certificate-tracker-shell .stage-filter-btn {
      border-radius: 10px;
      border-width: 1px;
      min-width: 104px;
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
    .certificate-tracker-shell .admin-list-actions .form-select,
    .certificate-tracker-shell .admin-list-actions .input-group-text,
    .certificate-tracker-shell .admin-list-actions .form-control {
      height: 38px;
    }
    .certificate-tracker-shell .tracker-doc-filter {
      min-width: 220px;
      max-width: 240px;
    }
    .certificate-tracker-shell .admin-search {
      min-width: 300px;
      max-width: 360px;
    }
    .certificate-tracker-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    #table-certificateTracker {
      table-layout: auto;
      width: 100%;
      min-width: 1100px;
    }
    #table-certificateTracker th,
    #table-certificateTracker td {
      vertical-align: middle;
    }
    #table-certificateTracker .col-request-id { width: 11%; }
    #table-certificateTracker .col-resident-id { width: 11%; }
    #table-certificateTracker .col-full-name { width: 18%; }
    #table-certificateTracker .col-document { width: 15%; }
    #table-certificateTracker .col-purpose { width: 17%; }
    #table-certificateTracker .col-status { width: 13%; }
    #table-certificateTracker .col-submitted { width: 10%; }
    #table-certificateTracker .col-action { width: 15%; }
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
    #viewModal .modal-dialog {
      width: 75vw;
      max-width: 1500px;
      height: 88vh;
    }
    #viewModal .modal-content {
      border: 0;
      border-radius: .5rem;
      padding: 1rem;
      overflow: hidden;
      height: 100%;
      background: #ffffff;
    }
    #viewModal .modal-header {
      border-bottom: 1px solid #e9ecef;
      background: #ffffff;
    }
    #viewModal .tracker-profile-view {
      display: grid;
      gap: 12px;
    }
    #viewModal .modal-body {
      overflow-y: auto;
      overflow-x: hidden;
      min-height: 0;
      background: #ffffff;
      padding: 14px;
    }
    #viewModal .tracker-doc-highlight {
      border: 1px solid #bfdbfe;
      background: #dbeafe;
      color: #1e3a8a;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
    }
    #viewModal .tracker-form-section {
      border: 1px solid #e78924;
      background: #ffffff;
      border-radius: 12px;
      padding: 12px;
      margin-top: 10px;
    }
    #viewModal .tracker-form-section-title {
      margin: 0 0 10px;
      font-size: 1rem;
      font-weight: 700;
      color: #212529;
      border-bottom: 1px dashed #e9ecef;
      padding-bottom: 6px;
    }
    #viewModal .tracker-form-subsection {
      display: grid;
      gap: 10px;
      padding: 12px;
      border: 1px solid #edf1f5;
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    }
    #viewModal .tracker-form-subsection + .tracker-form-subsection {
      margin-top: 4px;
    }
    #viewModal .tracker-form-subsection-title {
      margin: 0;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      color: #334155;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 12px;
    }
    #viewModal .tracker-form-grid.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    #viewModal .tracker-form-grid.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #viewModal .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    #viewModal .tracker-form-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    #viewModal .tracker-form-field--wide {
      grid-column: 1 / -1;
    }
    #viewModal .tracker-form-label {
      margin: 6px 0 0;
      font-size: .76rem;
      color: #6b7280;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }
    #viewModal .tracker-form-value {
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
    #viewModal #viewModalActions .btn {
      padding: 0.52rem 1rem;
      border-radius: 10px;
      font-weight: 600;
    }
    #viewModal .tracker-status-actions {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #e5e7eb;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      width: 100%;
    }
    #viewModal .tracker-status-actions .btn {
      padding: 0.52rem 1rem;
      border-radius: 5px;
      font-weight: 600;
      min-width: 110px;
    }
    #viewModal .tracker-status-actions--split {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    #viewModal .tracker-status-actions--split .btn {
      width: 100%;
      min-width: 0;
      padding: 0.62rem 1rem;
    }
    #viewModal .template-preview-stack {
      position: relative;
    }
    #viewModal .template-preview-overlays {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }
    #viewModal .template-preview-overlay-field {
      position: absolute;
      display: flex;
      flex-direction: column;
      gap: 4px;
      pointer-events: auto;
    }
    #viewModal .template-preview-overlay-field span {
      display: none;
    }
    #viewModal .template-preview-overlay-field input,
    #viewModal .template-preview-overlay-field textarea {
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
    #viewModal .template-preview-overlay-field textarea {
      resize: vertical;
      min-height: 44px;
    }
    #viewModal .template-preview-overlay-field input:focus,
    #viewModal .template-preview-overlay-field textarea:focus {
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
    #actionModal .modal-footer {
      flex-wrap: nowrap !important;
    }
    #actionModal .modal-footer .btn {
      flex: 1 1 0;
      white-space: nowrap;
    }
    #viewModal .doc-preview-shell {
      display: grid;
      place-items: center;
      padding: 4px 0 14px;
    }
    #viewModal .doc-preview-stage {
      border: 1px solid #d9dee6;
      border-radius: 10px;
      background:
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
      padding: 12px;
    }
    #viewModal .doc-preview-label {
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
    #viewModal .doc-preview-paper {
      width: min(100%, 794px);
      min-height: 1123px;
      border: 1px solid #cfd8e3;
      border-radius: 6px;
      background: #fff;
      box-shadow: 0 14px 30px rgba(15, 23, 42, .12);
      padding: 32px 42px;
      position: relative;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency {
      font-family: "Times New Roman", Times, serif;
      padding: 28px 46px 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center p {
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office {
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
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::before,
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title {
      font-size: 1.9rem;
      margin: 8px 0 16px;
      letter-spacing: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency {
      margin: 14px 0 10px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      text-transform: uppercase;
      line-height: 1.2;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .office {
      font-size: 17px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .certificate {
      margin-top: 4px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.08rem;
      line-height: 1.75;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body p {
      margin: 0 0 16px;
      text-indent: 2.5rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.02rem;
      line-height: 1.72;
      text-align: justify;
      margin-top: 4px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature {
      position: absolute;
      right: 66px;
      bottom: 258px;
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature .name {
      min-width: 260px;
      margin-top: 0;
      padding-top: 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-issuedby {
      position: absolute;
      left: 48px;
      bottom: 250px;
      font-size: .95rem;
      line-height: 1.35;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-footer {
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
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr {
      right: 18px;
      bottom: 30px;
      width: 92px;
      font-size: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr-box {
      width: 84px;
      height: 84px;
      border-style: solid;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-block {
      display: grid;
      grid-template-columns: 56px 18px 1fr;
      align-items: start;
      margin: 0 0 18px;
      column-gap: 4px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines {
      padding-top: 2px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines .line {
      display: block;
      width: 320px;
      max-width: 100%;
      border-bottom: 2px solid #1f2937;
      margin: 0 0 10px;
      height: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral {
      font-family: Arial, Helvetica, sans-serif;
      padding: 28px 46px 36px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-head-office {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.06rem;
      font-weight: 800;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-head-office-sub {
      font-family: Arial, Helvetica, sans-serif;
      margin-top: 2px;
      font-size: .98rem;
      font-weight: 800;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office {
      margin-top: 16px;
      margin-bottom: 18px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 800;
      line-height: 1.28;
      text-transform: uppercase;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-office div:last-child {
      margin-top: 4px;
      font-size: .98rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-title {
      font-size: 1.02rem;
      margin: 16px 0 18px;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 1.02rem;
      line-height: 1.6;
      text-align: justify;
      margin-top: 8px;
      padding: 0 8px 0 2px;
      flex: 0 0 auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral {
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding-bottom: 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body p {
      margin: 0 0 12px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body p + p {
      text-indent: 42px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-preview-issued-line {
      margin-top: 22px;
      margin-bottom: 18px;
      text-indent: 52px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block {
      display: grid;
      grid-template-columns: 145px 18px 1fr;
      align-items: start;
      column-gap: 0;
      margin: 0 0 4px;
      text-indent: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block + .doc-to-block {
      margin-top: 2px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block div,
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-body .doc-to-block strong {
      line-height: 1.45;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta {
      margin-top: 8px;
      margin-bottom: 10px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-row {
      display: grid;
      grid-template-columns: 136px 120px;
      align-items: baseline;
      justify-content: start;
      column-gap: 10px;
      margin: 0 0 4px;
      text-align: left;
      text-indent: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-label,
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-value {
      text-align: left;
      text-indent: 0;
      white-space: nowrap;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-goodmoral-meta .doc-preview-meta-line {
      display: inline-block;
      width: 72px;
      border-bottom: 1px solid #111827;
      transform: translateY(-2px);
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(260px, 1fr) 96px;
      align-items: end;
      column-gap: 18px;
      margin-top: 8px;
      padding-top: 10px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer-area.doc-preview-footer-area--noqr {
      grid-template-columns: minmax(220px, 1fr) minmax(260px, 1fr);
      column-gap: 28px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-signature .name {
      min-width: 260px;
      margin-top: 0;
      padding-top: 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-issuedby {
      font-size: .95rem;
      line-height: 1.35;
      text-align: left;
      align-self: center;
    }
    #viewModal .submitted-docs-grid {
      display: grid;
      grid-template-columns: minmax(240px, 1fr) minmax(280px, 1.1fr);
      gap: 16px;
    }
    #viewModal .submitted-docs-list {
      display: grid;
      gap: 12px;
    }
    #viewModal .submitted-docs-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px 12px;
      background: #ffffff;
    }
    #viewModal .submitted-docs-item__label {
      font-size: .76rem;
      font-weight: 700;
      color: #6b7280;
      margin-bottom: 6px;
    }
    #viewModal .submitted-docs-item__meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    #viewModal .submitted-docs-preview {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #f8fafc;
      padding: 12px;
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 10px;
      min-height: 220px;
    }
    #viewModal .submitted-docs-preview__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    #viewModal .submitted-docs-preview__name {
      font-weight: 600;
      color: #111827;
    }
    #viewModal .submitted-docs-preview__placeholder {
      color: #6b7280;
      font-size: .9rem;
      text-align: center;
      padding: 24px 8px;
    }
    #viewModal .submitted-docs-preview__body iframe {
      width: 100%;
      height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    #viewModal .submitted-docs-preview__body img {
      max-width: 100%;
      max-height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    @media (max-width: 992px) {
      #viewModal .submitted-docs-grid {
        grid-template-columns: 1fr;
      }
      #viewModal .submitted-docs-preview__body iframe,
      #viewModal .submitted-docs-preview__body img {
        height: 45vh;
      }
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-footer {
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
    #viewModal .doc-preview-paper.doc-preview-paper--business {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-hint {
      display: none;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office {
      margin-top: 12px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.24;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-office div:last-child {
      margin-top: 4px;
      font-size: .98rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-body {
      font-size: .98rem;
      line-height: 1.36;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-body p {
      margin: 0 0 14px;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-lead {
      margin-bottom: 18px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-intro {
      text-align: center;
      margin-bottom: 18px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-fields {
      width: 78%;
      margin: 0 auto 22px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field {
      margin: 18px 0 22px;
      font-size: 1.02rem;
      line-height: 1.35;
      text-transform: uppercase;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field .doc-editable {
      min-width: 260px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-field .doc-editable.doc-editable-multiline {
      white-space: normal;
      min-width: 320px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-paragraph {
      width: 90%;
      margin: 0 auto 16px;
      line-height: 1.32;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-checks {
      width: 90%;
      margin: 10px auto 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-row {
      display: grid;
      grid-template-columns: 48px 1fr;
      column-gap: 10px;
      align-items: start;
      margin: 0 0 8px;
      line-height: 1.28;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 3px;
      min-width: 48px;
      height: 14px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-line {
      width: 17px;
      border-top: 2px solid #111;
      flex: 0 0 auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-tick {
      width: 11px;
      text-align: center;
      font-size: 1rem;
      line-height: 1;
      font-weight: 800;
      opacity: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-check-mark--selected .doc-preview-business-check-tick {
      opacity: 1;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-issued-line {
      width: 86%;
      margin: 24px auto 0;
      text-align: center;
      line-height: 1.3;
      text-indent: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-area {
      display: grid;
      grid-template-columns: 1fr;
      column-gap: 0;
      align-items: end;
      margin-top: 18px;
      padding-top: 14px;
      padding-right: 108px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-area.doc-preview-business-footer-area--noqr {
      grid-template-columns: 1fr;
      padding-right: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-footer-main {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 26px;
      align-items: end;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 10px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signing {
      display: grid;
      justify-items: center;
      row-gap: 18px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature {
      justify-items: center;
      text-align: center;
      margin-top: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature .name {
      min-width: 252px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-signature div:last-child {
      font-style: italic;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta {
      width: 280px;
      margin-top: 18px;
      margin-left: 8px;
      font-size: .97rem;
      line-height: 1.2;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-row {
      display: grid;
      grid-template-columns: 96px 14px 1fr;
      column-gap: 6px;
      align-items: baseline;
      margin: 0 0 2px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-value .doc-editable {
      min-width: 96px;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-business-meta-line {
      display: inline-block;
      width: 72px;
      border-bottom: 1px solid #111827;
      transform: translateY(-2px);
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-footer {
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
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-qr {
      position: absolute;
      right: 34px;
      bottom: 42px;
      width: 96px;
      font-size: 0;
      justify-self: auto;
      align-self: auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--business .doc-preview-qr-box {
      width: 88px;
      height: 88px;
      border-style: solid;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-hint {
      display: none;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head {
      margin-bottom: 14px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office {
      margin-top: 10px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.22;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-office div:last-child {
      margin-top: 4px;
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-body {
      font-size: .98rem;
      line-height: 1.34;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-body p {
      margin: 0;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-lead {
      margin-bottom: 18px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-intro,
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-purpose {
      width: 84%;
      margin: 0 auto;
      text-align: center !important;
      line-height: 1.4;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-intro {
      margin-bottom: 18px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-fields {
      width: 72%;
      margin: 0 auto 20px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field {
      display: grid;
      grid-template-columns: 158px 16px 1fr;
      align-items: start;
      margin: 0 0 2px;
      line-height: 1.24;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field-label,
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field-colon {
      font-weight: 700;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-field--address .doc-preview-tricycle-field-value {
      line-height: 1.2;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-issued-line {
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
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta {
      width: 330px;
      margin: 18px 0 0 42px;
      font-size: .97rem;
      line-height: 1.18;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-row {
      display: grid;
      grid-template-columns: 110px 14px 1fr;
      column-gap: 4px;
      align-items: baseline;
      margin: 0 0 2px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-line {
      display: inline-flex;
      align-items: flex-end;
      min-height: 1.35em;
      padding: 0 4px 2px;
      box-sizing: border-box;
      border-bottom: 1px solid #111827;
      overflow: hidden;
      max-width: 100%;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-meta-line-text {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      font-weight: 700;
      line-height: 1.1;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 34px;
      align-items: end;
      margin-top: 18px;
      padding-top: 18px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 12px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signing {
      display: grid;
      justify-items: center;
      row-gap: 18px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature .name {
      min-width: 252px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-tricycle-signature div:last-child {
      font-style: italic;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-footer {
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
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-qr {
      left: 50%;
      right: auto;
      bottom: 52px;
      width: 88px;
      transform: translateX(-50%);
      font-size: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--tricycle .doc-preview-qr-box {
      width: 80px;
      height: 80px;
      border-style: solid;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance {
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      min-height: 1188px;
      padding: 28px 44px 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-hint {
      display: none;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head {
      margin-bottom: 14px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center p {
      font-family: "Times New Roman", Times, serif;
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office {
      margin-top: 10px;
      margin-bottom: 18px;
      text-align: center;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.22;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office div:first-child {
      font-size: 1.06rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-office div:last-child {
      margin-top: 4px;
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-body {
      font-size: .98rem;
      line-height: 1.34;
      text-align: left;
      padding: 0 8px 0 4px;
      flex: 1 1 auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-body p {
      margin: 0;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-lead {
      margin-bottom: 20px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-intro {
      width: 86%;
      margin: 0 auto 18px;
      text-align: center !important;
      line-height: 1.42;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-fields {
      width: 72%;
      margin: 0 auto 20px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field {
      display: grid;
      grid-template-columns: 172px 16px 1fr;
      align-items: start;
      margin: 0 0 2px;
      line-height: 1.22;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-label,
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-colon {
      font-weight: 700;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-value {
      min-width: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field-value .doc-editable {
      display: block;
      width: 100%;
      min-width: 0;
      box-sizing: border-box;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-field--address .doc-preview-generalclearance-field-value {
      line-height: 1.18;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-note {
      width: 84%;
      margin: 20px auto 0;
      text-align: center !important;
      line-height: 1.38;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-note-nowrap {
      white-space: nowrap;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-issued-line {
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
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta {
      width: 340px;
      margin: 18px 0 0 34px;
      font-size: .97rem;
      line-height: 1.24;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-row {
      display: grid;
      grid-template-columns: 126px 16px minmax(120px, 1fr);
      column-gap: 6px;
      align-items: center;
      margin: 0 0 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-value {
      min-width: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-line {
      display: flex;
      align-items: flex-end;
      width: 100%;
      min-height: 1.35em;
      padding: 0 4px 2px;
      box-sizing: border-box;
      border-bottom: 1px solid #111827;
      overflow: hidden;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-meta-line-text {
      display: block;
      max-width: 100%;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      font-weight: 700;
      line-height: 1.1;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-footer-area {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(270px, 1fr);
      column-gap: 36px;
      align-items: end;
      margin-top: 18px;
      padding-top: 18px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-issuedby {
      font-size: .95rem;
      line-height: 1.3;
      text-align: left;
      align-self: end;
      padding-bottom: 16px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signing {
      display: grid;
      justify-items: center;
      row-gap: 20px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature {
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature .name {
      min-width: 270px;
      margin-top: 0;
      padding-top: 4px;
      font-size: 1rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-generalclearance-signature div:last-child {
      font-style: italic;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-footer {
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
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-qr {
      left: 50%;
      right: auto;
      bottom: 58px;
      width: 88px;
      transform: translateX(-50%);
      font-size: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--generalclearance .doc-preview-qr-box {
      width: 80px;
      height: 80px;
      border-style: solid;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--cohabitation-children {
      min-height: 1320px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs {
      min-height: 1220px;
      padding-bottom: 124px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-goodmoral-office {
      margin-top: 10px;
      margin-bottom: 14px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-subtitle {
      margin-top: 2px;
      font-size: .74rem;
      line-height: 1.2;
      text-transform: none;
      font-weight: 700;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-body {
      font-size: .96rem;
      line-height: 1.46;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-body p {
      margin-bottom: 10px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-footer-area--ftjs {
      display: block;
      padding-top: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-signing {
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
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-block {
      display: grid;
      justify-items: center;
      row-gap: 2px;
      width: 100%;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-name {
      width: 100%;
      font-size: 1rem;
      font-weight: 800;
      line-height: 1.2;
      text-align: center;
      text-decoration: underline;
      text-decoration-thickness: 1.5px;
      text-underline-offset: 4px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-role {
      font-size: .95rem;
      line-height: 1.15;
      font-style: italic;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-line {
      width: 100%;
      margin-top: 6px;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-line span {
      display: inline-block;
      min-width: 170px;
      max-width: 100%;
      font-weight: 700;
      line-height: 1.1;
      text-decoration: underline;
      text-decoration-thickness: 1.5px;
      text-underline-offset: 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-witness-label,
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-label {
      width: 100%;
      font-size: .88rem;
      line-height: 1.1;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-date-label {
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-ftjs-witness-label {
      margin: 14px 0 12px;
      font-weight: 700;
      font-size: .96rem;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-footer {
      width: 58%;
      position: absolute;
      left: 21%;
      bottom: 10px;
      margin: 0;
      font-size: .76rem;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--ftjs .doc-preview-qr {
      position: absolute;
      right: 34px;
      bottom: 56px;
      width: 96px;
      justify-self: auto;
      align-self: auto;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail {
      min-height: 1188px;
      padding-top: 34px;
      padding-bottom: 96px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-office {
      margin-top: 6px;
      margin-bottom: 28px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-hint {
      display: none;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body {
      font-size: 1rem;
      line-height: 1.48;
      text-align: justify;
      padding: 0 6px;
      margin-top: 16px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body p {
      margin: 0 0 22px;
      text-indent: 56px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body p + p {
      text-indent: 56px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-lead {
      text-align: left;
      margin-bottom: 26px;
      font-size: 1rem;
      text-indent: 0 !important;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-center {
      width: 84%;
      margin-left: auto;
      margin-right: auto;
      box-sizing: border-box;
      text-align: justify;
      text-align-last: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-jail-ordinance {
      width: 84%;
      margin: 6px auto 26px;
      box-sizing: border-box;
      text-align: justify;
      text-align-last: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-body .doc-preview-issued-line {
      width: 84%;
      margin: 10px auto 6px;
      box-sizing: border-box;
      text-indent: 56px;
      text-align: left;
      line-height: 1.52;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-meta {
      width: 220px;
      margin: 0 0 0 8px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-goodmoral-meta .doc-preview-meta-row {
      grid-template-columns: 88px 84px;
      column-gap: 10px;
      margin-bottom: 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-footer-area {
      margin-top: 18px;
      padding-top: 12px;
      align-items: start;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-issuedby {
      align-self: start;
      padding-top: 14px;
      padding-bottom: 0;
      font-size: .92rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-signature {
      align-self: start;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-signature .name {
      min-width: 320px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral.doc-preview-paper--jail .doc-preview-footer {
      width: 72%;
      position: absolute;
      left: 50%;
      bottom: 10px;
      transform: translateX(-50%);
      margin: 0;
      font-size: .8rem;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-qr {
      position: static;
      right: auto;
      bottom: auto;
      width: 96px;
      font-size: 0;
      justify-self: end;
      align-self: end;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--goodmoral .doc-preview-qr-box {
      width: 88px;
      height: 88px;
      border-style: solid;
    }
    #viewModal .doc-preview-head {
      display: grid;
      grid-template-columns: 100px 1fr 100px;
      align-items: center;
      gap: 14px;
      margin-bottom: 16px;
    }
    #viewModal .doc-preview-logo {
      width: 92px;
      height: 92px;
      object-fit: contain;
      border-radius: 50%;
      justify-self: center;
    }
    #viewModal .doc-preview-head-center {
      text-align: center;
      color: #111827;
      line-height: 1.18;
    }
    #viewModal .doc-preview-head-center p {
      margin: 0;
      font-size: .78rem;
    }
    #viewModal .doc-preview-head-center .rep {
      font-size: .94rem;
      font-weight: 800;
      letter-spacing: .02em;
    }
    #viewModal .doc-preview-head-center .barangay {
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: .02em;
      margin-top: 2px;
    }
    #viewModal .doc-preview-head-center .doc-head-office {
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
    #viewModal .doc-preview-head-center .doc-head-office::before,
    #viewModal .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    #viewModal .doc-preview-head-line {
      border-bottom: 2px solid #9ca3af;
      margin-top: 10px;
    }
    #viewModal .doc-preview-title {
      text-align: center;
      font-size: 1.1rem;
      font-weight: 800;
      letter-spacing: .03em;
      margin: 10px 0 14px;
      text-transform: uppercase;
    }
    #viewModal .doc-preview-body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: .95rem;
      color: #111827;
      line-height: 1.55;
    }
    #viewModal .doc-preview-body p {
      margin: 0 0 12px;
      text-align: justify;
    }
    #viewModal .doc-preview-hint {
      margin: 0 0 10px;
      font-size: .78rem;
      color: #92400e;
      background: #fff7d6;
      border: 1px solid #fde68a;
      border-radius: 7px;
      padding: 6px 8px;
    }
    #viewModal .doc-editable {
      background: #fff6bf;
      border: 1px dashed #d97706;
      border-radius: 4px;
      padding: 0 4px;
      min-width: 24px;
      display: inline-block;
      outline: none;
    }
    #viewModal .doc-editable--empty {
      min-height: 1.2em;
    }
    #viewModal .doc-editable:focus {
      border-style: solid;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, .2);
    }
    #viewModal .doc-editable-multiline {
      white-space: pre-line;
      min-width: 280px;
    }
    #viewModal .doc-preview-signature {
      margin-top: 14px;
      display: grid;
      justify-items: end;
      color: #111827;
    }
    #viewModal .doc-preview-signature .name {
      margin-top: 18px;
      font-weight: 700;
      border-top: 1px solid #1f2937;
      padding-top: 4px;
      min-width: 220px;
      text-align: center;
    }
    #viewModal .doc-preview-qr {
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
    #viewModal .doc-preview-qr-box {
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
    #viewModal .doc-preview-qr-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    #viewModal .tracker-profile-section {
      border: 0;
      border-radius: 0;
      background: transparent;
      padding: 0;
    }
    #viewModal .tracker-profile-section h6 {
      margin: 2px 0 8px;
      font-size: .9rem;
      color: #374151;
      font-weight: 700;
    }
    #viewModal .tracker-profile-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    #viewModal .tracker-profile-item {
      background: transparent;
      border: 0;
      border-radius: 0;
      padding: 2px 0;
    }
    #viewModal .tracker-profile-label {
      margin: 0 0 4px;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      color: #6b7280;
      font-weight: 700;
    }
    #viewModal .tracker-profile-value {
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
    .manual-issuance-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: #fff3e6;
      border: 1px solid #fed7aa;
      color: #9a3412;
      font-size: .85rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .manual-issuance-steps {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 12px;
    }
    .manual-step {
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fff7ed 100%);
      padding: 14px 16px;
      min-height: 130px;
    }
    .manual-step-index {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 999px;
      background: #ea580c;
      color: #fff;
      font-size: .85rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .manual-step-title {
      font-size: .95rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 6px;
    }
    .manual-step-copy {
      font-size: .82rem;
      color: #6b7280;
      line-height: 1.45;
      margin: 0;
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
      .certificate-tracker-shell .stage-filter-btn {
        min-width: 0;
      }
      .certificate-tracker-shell .admin-search,
      .certificate-tracker-shell .tracker-doc-filter {
        min-width: 100%;
        max-width: 100%;
      }
      .manual-issuance-steps {
        grid-template-columns: 1fr;
      }
      .manual-resident-result {
        flex-direction: column;
      }
      #viewModal .tracker-profile-grid {
        grid-template-columns: 1fr;
      }
      #viewModal .tracker-form-grid {
        grid-template-columns: 1fr;
      }
      #viewModal .tracker-form-grid.cols-4 {
        grid-template-columns: 1fr;
      }
      #viewModal .tracker-form-grid.cols-3 {
        grid-template-columns: 1fr;
      }
      #viewModal .template-preview-overlays {
        position: static;
        margin-top: 12px;
        display: grid;
        gap: 10px;
        pointer-events: auto;
      }
      #viewModal .template-preview-overlay-field {
        position: static;
      }
      #residentProfileModal #div-modalSizing {
        width: 96vw;
      }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">Certificate Issuance</h2>
    <hr class="mb-4">

    <!-- Page-level navigation -->
    <ul class="nav nav-tabs mb-0" id="certTrackerPageTabs" style="border-bottom:0">
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
      <li class="nav-item">
        <button class="nav-link fw-semibold" id="tabFeeRequests" type="button">
          <i class="fas fa-tags me-1"></i>Fee Change Requests
        </button>
      </li>
    </ul>

    <div id="docRequestsPanel" class="bg-white p-4 rounded-4 rounded-tl-0 shadow-sm border resident-masterlist-shell certificate-tracker-shell">
      <div class="admin-list-toolbar mb-3">
        <div class="admin-list-tabs">
          <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn stage-filter-btn active" data-stage-filter=""><?= $isIdIssuanceTrackerView ? 'ALL' : 'All' ?></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="pending"><?= $isIdIssuanceTrackerView ? 'PENDING' : 'Pending' ?> <span class="tab-count" id="pendingTabCount">0</span></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="release"><?= $isIdIssuanceTrackerView ? 'FOR PRINTING' : 'Release' ?> <span class="tab-count" id="releaseTabCount">0</span></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-stage-filter="completed"><?= $isIdIssuanceTrackerView ? 'COMPLETED' : 'Completed' ?></button>
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
        <table id="table-certificateTracker" class="table align-middle compact-admin-table">
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
          <h5 class="fw-bold mb-1">Manual / Walk-in Document Issuance</h5>
          <p class="text-muted mb-0">
            Encode handwritten applications here, preview the final document before submission, then send the request into the existing finance and release workflow. QR verification still applies to issued files.
          </p>
        </div>
        <div class="manual-issuance-badge">
          <i class="fas fa-shield-halved"></i>
          Admin-only issuance flow
        </div>
      </div>

      <div class="manual-issuance-steps mb-4">
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
          <div class="manual-step-title">Preview</div>
          <p class="manual-step-copy">Open the rendered document preview first so the encoded details match the physical form before submission.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">4</div>
          <div class="manual-step-title">Finance Payment</div>
          <p class="manual-step-copy">Paid requests continue to finance for walk-in payment recording using the same request pipeline.</p>
        </div>
        <div class="manual-step">
          <div class="manual-step-index">5</div>
          <div class="manual-step-title">Release by Print</div>
          <p class="manual-step-copy">After payment or interview handling, admin releases the final document through print while keeping QR verification active.</p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-8">
          <form id="manualIssuanceForm" novalidate>
            <input type="hidden" id="manualResidentId" name="resident_id">
            <input type="hidden" id="manualResidentUserId" name="resident_user_id">

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>Resident Source</h6>
                <span>Registered residents can be auto-filled, but every field stays editable for this encoded request.</span>
              </div>
              <div class="manual-issuance-mode-switch mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeExisting" value="existing" checked>
                  <label class="form-check-label" for="manualResidentModeExisting">
                    <i class="fas fa-user-check"></i>Registered Resident
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manualResidentMode" id="manualResidentModeWalkin" value="walkin">
                  <label class="form-check-label" for="manualResidentModeWalkin">
                    <i class="fas fa-user-pen"></i>Walk-in / Not Registered
                  </label>
                </div>
              </div>

              <div id="manualResidentLookupWrap">
                <div class="row g-3 align-items-end">
                  <div class="col-lg-8">
                    <label class="form-label fw-semibold small">Search Registered Resident</label>
                    <div class="input-group">
                      <input type="text" id="manualResidentSearchInput" class="form-control" placeholder="Resident ID, user ID, or resident name">
                      <button type="button" class="btn btn-outline-secondary" id="manualResidentSearchBtn">
                        <i class="fas fa-search me-1"></i>Search
                      </button>
                    </div>
                  </div>
                  <div class="col-lg-4">
                    <div class="manual-search-empty h-100 d-flex align-items-center" id="manualResidentSearchHint">
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

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>Document Setup</h6>
                <span>Choose the form first. The matching fields and next step summary will update automatically.</span>
              </div>
              <div class="row g-3">
                <div class="col-lg-7">
                  <label for="manualDocumentType" class="form-label fw-semibold small">Certificate / Clearance Type <span class="text-danger">*</span></label>
                  <select id="manualDocumentType" class="form-select" required>
                    <option value="">Select a manual issuance form</option>
                  </select>
                </div>
                <div class="col-lg-5">
                  <label for="manualPurpose" class="form-label fw-semibold small">Purpose / Request For</label>
                  <input type="text" id="manualPurpose" class="form-control" placeholder="Purpose from the handwritten form">
                </div>
              </div>
            </div>

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>Personal Basic Information</h6>
                <span>These fields will be saved with the request and used in the generated document preview.</span>
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
                  <input type="text" id="manualSuffix" class="form-control" placeholder="Jr., Sr., III">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualBirthdate" class="form-label fw-semibold small">Birthdate</label>
                  <input type="date" id="manualBirthdate" class="form-control">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualSex" class="form-label fw-semibold small">Sex</label>
                  <select id="manualSex" class="form-select">
                    <option value="">Select sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualCivilStatus" class="form-label fw-semibold small">Civil Status</label>
                  <input type="text" id="manualCivilStatus" class="form-control" placeholder="Single, Married, etc.">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label for="manualContactNumber" class="form-label fw-semibold small">Contact Number</label>
                  <input type="text" id="manualContactNumber" class="form-control" placeholder="09XXXXXXXXX">
                </div>
                <div class="col-md-6">
                  <label for="manualBirthplace" class="form-label fw-semibold small">Birthplace</label>
                  <input type="text" id="manualBirthplace" class="form-control" placeholder="Place of birth">
                </div>
                <div class="col-md-3">
                  <label for="manualOccupation" class="form-label fw-semibold small">Occupation</label>
                  <input type="text" id="manualOccupation" class="form-control" placeholder="Occupation">
                </div>
                <div class="col-md-3">
                  <label for="manualReligion" class="form-label fw-semibold small">Religion</label>
                  <input type="text" id="manualReligion" class="form-control" placeholder="Religion">
                </div>
                <div class="col-12">
                  <label for="manualFullAddress" class="form-label fw-semibold small">Residential Address <span class="text-danger">*</span></label>
                  <textarea id="manualFullAddress" class="form-control" rows="2" required placeholder="House / street / phase / subdivision / area"></textarea>
                </div>
              </div>
            </div>

            <div class="manual-issuance-card">
              <div class="manual-issuance-card-title">
                <h6>Document Specific Details</h6>
                <span id="manualSpecificFieldsHint">Select a certificate or clearance type to load its manual encoding fields.</span>
              </div>
              <div id="manualDynamicFields" class="row g-3"></div>
            </div>

            <div class="manual-issuance-card d-none" id="manualFeeWrap">
              <div class="manual-issuance-card-title">
                <h6>Tagged Clearance Fees</h6>
                <span>These tagged fees will be carried into the finance step for walk-in payment recording.</span>
              </div>
              <div id="manualFeeList" class="manual-fee-list"></div>
              <div class="manual-fee-total">
                <span>Total Tagged Amount</span>
                <strong id="manualFeeTotal">PHP 0.00</strong>
              </div>
            </div>

            <div id="manualFormAlert" class="alert alert-warning d-none"></div>

            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" id="manualResetBtn">
                <i class="fas fa-rotate-left me-1"></i>Reset Form
              </button>
              <button type="button" class="btn btn-outline-primary" id="manualPreviewBtn">
                <i class="fas fa-eye me-1"></i>Preview Document
              </button>
              <button type="submit" class="btn btn-primary" id="manualSubmitBtn" disabled>
                <i class="fas fa-paper-plane me-1"></i>Submit Manual Issuance
              </button>
            </div>
          </form>
        </div>

        <div class="col-xl-4">
          <div class="manual-issuance-card sticky-xl-top" style="top: 1rem;">
            <div class="manual-issuance-card-title">
              <h6>Submission Summary</h6>
              <span>Use this as a quick check before you preview and submit.</span>
            </div>
            <div class="manual-issuance-summary mb-3">
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Resident Link</p>
                <p class="manual-summary-item-value" id="manualResidentSummary">Walk-in / not linked yet</p>
              </div>
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Document Type</p>
                <p class="manual-summary-item-value" id="manualDocumentSummary">Select a manual issuance form</p>
              </div>
              <div class="manual-summary-item">
                <p class="manual-summary-item-label">Next Step After Submit</p>
                <p class="manual-summary-item-value" id="manualNextStageSummary">Preview the document first to unlock submission.</p>
              </div>
            </div>
            <p class="manual-summary-note">
              Registered residents stay linked to their masterlist record, while walk-in residents can still be encoded and issued here without an online account. Paid requests continue to finance, and issued files still carry the QR verification flow used by the existing generator.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── FEE CHANGE REQUESTS PANEL ──────────────────────────────────────── -->
    <div id="feeChangePanel" class="d-none bg-white p-4 rounded-4 shadow-sm border certificate-tracker-shell">

      <!-- Sub-tabs -->
      <ul class="nav nav-pills mb-4" id="feeChangeSubTabs">
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
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-semibold mb-0">Current Fee Catalog</h6>
              <button class="btn btn-sm btn-outline-secondary" id="fcrEditRefreshBtn" title="Refresh">
                <i class="fa-solid fa-arrows-rotate"></i>
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
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
          <div class="col-lg-5">
            <div class="border rounded-3 p-3 bg-light d-none" id="fcrEditFormWrap">
              <h6 class="fw-semibold mb-3" id="fcrEditFormTitle"><i class="fas fa-pen me-1 text-warning"></i>Request Price Edit</h6>
              <input type="hidden" id="fcrEditFeeTypeId">
              <div class="mb-2">
                <label class="form-label fw-semibold small">Fee Name</label>
                <input type="text" id="fcrEditFeeName" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label fw-semibold small">Current Amount</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="text" id="fcrEditCurrentAmount" class="form-control" readonly>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Proposed Amount (₱) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" id="fcrEditProposedAmount" class="form-control" min="0" step="0.01">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Notes</label>
                <textarea id="fcrEditNotes" class="form-control" rows="2"></textarea>
              </div>
              <div id="fcrEditError" class="alert alert-danger d-none py-2 small mb-3" data-modal-inline="true"></div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning flex-fill" id="fcrEditSubmitBtn">
                  <i class="fas fa-paper-plane me-1"></i>Submit
                </button>
                <button type="button" class="btn btn-outline-secondary" id="fcrEditCancelBtn">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            <div id="fcrEditSuccess" class="alert alert-success d-none py-2 small mb-3" data-modal-inline="true"></div>
            <div class="text-muted small text-center mt-4" id="fcrEditHint">
              <i class="fas fa-arrow-left me-1"></i>Select a fee type from the table to request a price edit
            </div>
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
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
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
  </main>
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
          <select id="actionBusinessApproval" name="business_approval_type" class="form-select">
            <option value="">Select approval type</option>
            <option value="not_banned">Not among those business or trade activities being banned to be established in this Barangay</option>
            <option value="no_objection">Interposes no objection for the issuance of the corresponding Business Permit being applied for.</option>
            <option value="temporary_clearance">Recommendations only the issuance of &quot;Temporary Barangay Clearance&quot; subject for revocation anytime provided that the requirements under existing Barangay Ordinance, Rules and Regulations should be complied with, otherwise this Barangay should take the necessary actions within legal bounds to stop its continued operations.</option>
          </select>
        </div>

        <div id="actionPlateWrap" class="d-none mb-3">
          <label class="form-label">Plate Number</label>
          <input id="actionPlate" name="plate_number" type="text" class="form-control" placeholder="Enter plate number if applicable">
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
        <button type="button" id="paymentProofPrintBtn" class="btn btn-outline-dark d-none">Print</button>
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" id="paymentProofReleaseBtn" class="btn btn-success d-none">Release</button>
        <button type="button" id="paymentProofCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

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
        <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning">#—</span></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Personal Information</h5>
          </div>

          <div class="row g-3 align-items-center">
            <div class="col-md-3 d-flex justify-content-center align-items-center">
              <img id="img-modalIdPicture"
                   src="../Images/Profile-Placeholder.png"
                   alt="Resident 2x2 image"
                   class="img-fluid rounded-circle"
                   style="width: clamp(120px, 18vw, 170px); height: clamp(120px, 18vw, 170px); object-fit: cover;">
            </div>

            <div class="col-md-9">
              <div class="row g-3">
                <div class="col-md-12 col-lg-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalName" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Sex:</p><p id="txt-modalSex" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Religion:</p><p id="txt-modalReligion" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Age:</p><p id="txt-modalAge" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Civil Status:</p><p id="txt-modalCivilStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Occupation:</p><p id="txt-modalOccupation" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Date of Birth:</p><p id="txt-modalDob" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Head of Family:</p><p id="txt-modalHeadOfFam" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Voter Status:</p><p id="txt-modalVoterStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-12">
                  <p class="text-muted small mb-0">Sector Membership:</p>
                  <p id="txt-modalSectorMembership" class="fw-bold mb-0">—</p>
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
            <div class="col-md-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalEmergencyFullName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Contact Number:</p><p id="txt-modalEmergencyContactNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Relationship:</p><p id="txt-modalEmergencyRelationship" class="fw-bold mb-0">—</p></div>
            <div class="col-md-12"><p class="text-muted small mb-0">Address:</p><p id="txt-modalEmergencyAddress" class="fw-bold mb-0">—</p></div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Address Information</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4" id="addr-unit-number"><p class="text-muted small mb-0">Unit Number:</p><p id="txt-modalUnitNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-house-number"><p class="text-muted small mb-0">House Number:</p><p id="txt-modalHouseNum" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-street-name"><p class="text-muted small mb-0">Street Name:</p><p id="txt-modalStreetName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-phase-number"><p class="text-muted small mb-0">Phase:</p><p id="txt-modalPhaseNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-subdivision"><p class="text-muted small mb-0">Subdivision:</p><p id="txt-modalSubdivision" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-area-number"><p class="text-muted small mb-0">Area Number:</p><p id="txt-modalAreaNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Barangay:</p><p id="txt-modalBarangay" class="fw-bold mb-0">Barangay San Jose</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Municipality / City:</p><p id="txt-modalMunicipalityCity" class="fw-bold mb-0">Rodriguez (Montalban)</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Province:</p><p id="txt-modalProvince" class="fw-bold mb-0">Rizal</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Ownership:</p><p id="txt-modalHouseOwnership" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Type:</p><p id="txt-modalHouseType" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Residency Duration:</p><p id="txt-modalResidencyDuration" class="fw-bold mb-0">—</p></div>
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
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-certificateTracker",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_certificate_tracker_v1",
    defaultHiddenIdxs: [1, 4]
  };
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Shared/barangayIdDigital.js?v=20260321-04"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js?v=20260322-12"></script>
</body>
</html>
