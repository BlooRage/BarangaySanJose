<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";
require_once "../PhpFiles/General/adminModulePermissions.php";

requireRoleSession(['SuperAdmin'], false);

if (empty($_SESSION['personnel_access_preview_token']) || !is_string($_SESSION['personnel_access_preview_token'])) {
  $_SESSION['personnel_access_preview_token'] = bin2hex(random_bytes(16));
}

if (!function_exists('pra_filter_catalog_for_personnel_roles')) {
  function pra_filter_catalog_for_personnel_roles(array $catalog): array
  {
    $filtered = [];

    foreach ($catalog as $section) {
      $sectionItems = [];
      foreach (($section['items'] ?? []) as $item) {
        $children = $item['children'] ?? [];
        if ($children) {
          $filteredChildren = [];
          foreach ($children as $child) {
            $key = trim((string)($child['key'] ?? ''));
            if ($key !== '') {
              $filteredChildren[] = $child;
            }
          }
          if ($filteredChildren) {
            $copy = $item;
            $copy['children'] = $filteredChildren;
            unset($copy['path']);
            $sectionItems[] = $copy;
          }
          continue;
        }

        $key = trim((string)($item['key'] ?? ''));
        if ($key !== '') {
          $sectionItems[] = $item;
        }
      }

      if ($sectionItems) {
        $copy = $section;
        $copy['items'] = $sectionItems;
        $filtered[] = $copy;
      }
    }

    return $filtered;
  }
}

$personnelRolePermissionCatalog = pra_filter_catalog_for_personnel_roles(amp_get_permission_catalog());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Personnel Access Control</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <style>
    #main-display {
      min-width: 0;
    }
    .role-access-stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
    }
    .role-access-stat-card {
      border: 1px solid #ece9e1;
      border-radius: 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fbf8f4 100%);
      padding: 18px 20px;
      box-shadow: 0 10px 26px rgba(34, 34, 34, 0.05);
    }
    .role-access-stat-label {
      font-size: 0.82rem;
      font-weight: 700;
      color: #8b6d4b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .role-access-stat-value {
      font-size: 1.85rem;
      font-weight: 800;
      color: #1f2937;
      line-height: 1.15;
      margin-top: 6px;
    }
    .role-access-shell {
      width: 100%;
      overflow-x: hidden;
    }
    .role-access-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
    }
    .role-access-toolbar-main {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      flex: 1 1 620px;
    }
    .role-access-toolbar-main .input-group {
      min-width: 260px;
      flex: 1 1 300px;
    }
    .role-access-toolbar-main .form-select {
      min-width: 200px;
      flex: 1 1 220px;
    }
    .role-access-toolbar-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }
    .role-access-table td,
    .role-access-table th {
      vertical-align: middle;
    }
    .role-access-action-stack {
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: stretch;
      min-width: 154px;
    }
    .role-access-modules {
      white-space: normal;
      min-width: 260px;
    }
    .role-access-source-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      border: 1px solid transparent;
    }
    .role-access-source-badge.is-custom {
      background: #eaf6ec;
      color: #1c6b3d;
      border-color: #b7e0c0;
    }
    .role-access-source-badge.is-default {
      background: #eef2ff;
      color: #3b4cca;
      border-color: #cfd7ff;
    }
    .role-access-meta-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    .role-access-meta-card {
      border: 1px solid #ece9e1;
      border-radius: 14px;
      background: #fcfcfd;
      padding: 12px 14px;
    }
    .role-access-meta-label {
      font-size: 0.78rem;
      font-weight: 700;
      color: #6b7280;
      margin-bottom: 4px;
    }
    .role-access-meta-value {
      font-weight: 700;
      color: #111827;
      line-height: 1.35;
      white-space: normal;
    }
    .role-access-groups {
      display: grid;
      gap: 14px;
      max-height: 48vh;
      overflow-y: auto;
      padding-right: 4px;
    }
    .role-access-group {
      border: 1px solid #ececec;
      border-radius: 14px;
      background: #fff;
      overflow: hidden;
    }
    .role-access-group-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 14px;
      border-bottom: 1px solid #f1f1f1;
      background: #faf7f2;
    }
    .role-access-group-title {
      font-weight: 700;
      color: #2f3640;
    }
    .role-access-group-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .role-access-items {
      display: grid;
      gap: 8px;
      padding: 12px 14px 14px;
    }
    .role-access-item {
      border: 1px solid #edf0f4;
      border-radius: 12px;
      padding: 10px 12px;
      background: #fff;
    }
    .role-access-item.is-child {
      margin-left: 18px;
      background: #fcfcfd;
    }
    .role-access-item label {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      cursor: pointer;
      width: 100%;
    }
    .role-access-item input[type="checkbox"] {
      margin-top: 0.15rem;
      flex: 0 0 auto;
    }
    .role-access-item-main {
      font-weight: 700;
      color: #111827;
      line-height: 1.3;
    }
    .role-access-item-sub {
      display: block;
      margin-top: 2px;
      color: #6b7280;
      font-size: 0.8rem;
      line-height: 1.3;
    }
    .role-access-search {
      max-width: 280px;
    }
    .role-access-preview-modal .modal-dialog {
      max-width: min(1120px, calc(100vw - 48px));
      height: min(82dvh, 760px);
    }
    .role-access-preview-modal .modal-header {
      flex: 0 0 auto;
    }
    .role-access-preview-modal .modal-content {
      display: flex;
      flex-direction: column;
      height: 100%;
      min-height: 0;
      padding: 1rem !important;
    }
    .role-access-preview-modal .modal-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden;
      padding-top: 0.5rem;
    }
    .role-access-preview-shell {
      display: grid;
      grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
      width: 100%;
      height: 100%;
      min-height: 0;
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      overflow: hidden;
      background: #ffffff;
    }
    .role-access-preview-sidebar {
      min-width: 0;
      min-height: 0;
      border-right: 1px solid #e5e7eb;
      background: #ffffff;
      font-family: 'Geist', sans-serif;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .role-access-preview-profile {
      padding: 14px;
      border-bottom: 1px solid #e5e7eb;
      background: #fff;
    }
    .role-access-preview-profile-title {
      font-weight: 800;
      color: #111827;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }
    .role-access-preview-profile-meta {
      margin-top: 4px;
      font-size: 0.82rem;
      color: #6b7280;
    }
    .role-access-preview-search {
      padding: 12px 14px;
      border-bottom: 1px solid #e5e7eb;
    }
    .role-access-preview-nav {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overscroll-behavior: contain;
      padding: 10px 14px 14px;
    }
    .role-access-preview-sidebar-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .role-access-preview-section-title {
      padding: 10px 10px 6px;
      color: #6b7280;
      font-size: 0.78rem;
      font-weight: 650;
      text-transform: uppercase;
      letter-spacing: 0;
    }
    .role-access-preview-main {
      width: 100%;
      min-height: 42px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      border: 0;
      border-radius: 8px;
      background: transparent;
      color: #1f2937;
      padding: 8px 10px;
      text-align: left;
      font-weight: 600;
      line-height: 1.25;
      transition: background-color 0.2s ease, color 0.2s ease;
    }
    .role-access-preview-main:hover,
    .role-access-preview-main:focus {
      background-color: #f1f3f5;
      color: #111827;
    }
    .role-access-preview-main.is-active {
      background-color: rgba(222, 113, 12, 0.1);
      color: #111827;
      font-weight: 700;
    }
    .role-access-preview-main:not(.role-access-preview-toggle).is-active {
      background-color: #de710c;
      color: #ffffff;
      font-weight: 600;
    }
    .role-access-preview-main:not(.role-access-preview-toggle).is-active i,
    .role-access-preview-main:not(.role-access-preview-toggle).is-active .role-access-preview-label {
      color: #ffffff;
    }
    .role-access-preview-main .role-access-preview-chevron {
      transition: transform 0.2s ease;
    }
    .role-access-preview-main.is-collapsed .role-access-preview-chevron {
      transform: rotate(-90deg);
    }
    .role-access-preview-main-left {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    .role-access-preview-icon-wrap {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.2rem;
      min-width: 1.2rem;
    }
    .role-access-preview-label {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .role-access-preview-subnav {
      margin: 2px 0 6px;
      padding-left: 1.45rem;
      list-style: none;
    }
    .role-access-preview-subnav.is-collapsed {
      display: none;
    }
    .role-access-preview-subnav button {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin: 2px 0;
      padding: 6px 10px;
      border: 0;
      border-radius: 8px;
      background: transparent;
      color: #444;
      text-align: left;
      font-size: 0.85rem;
      line-height: 1.3;
      transition: background-color 0.2s ease, color 0.2s ease, padding-left 0.2s ease;
    }
    .role-access-preview-subnav button:hover,
    .role-access-preview-subnav button:focus {
      background-color: #f1f3f5;
      color: #000;
      padding-left: 16px;
    }
    .role-access-preview-subnav button.is-active {
      background-color: #de710c;
      color: #ffffff;
      font-weight: 600;
    }
    .role-access-preview-stage {
      min-width: 0;
      min-height: 0;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: #f3f4f6;
    }
    .role-access-preview-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      min-height: 66px;
      padding: 12px 14px;
      border-bottom: 1px solid #e5e7eb;
      background: #ffffff;
    }
    .role-access-preview-current {
      min-width: 0;
    }
    .role-access-preview-current-title {
      font-weight: 800;
      color: #111827;
      line-height: 1.25;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .role-access-preview-current-path {
      margin-top: 2px;
      color: #6b7280;
      font-size: 0.8rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .role-access-preview-frame-wrap {
      position: relative;
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden;
      overscroll-behavior: contain;
      background: #ffffff;
    }
    .role-access-preview-frame {
      display: block;
      width: 100%;
      height: 100%;
      min-height: 0;
      border: 0;
      background: #ffffff;
      overflow: auto;
    }
    .role-access-preview-empty {
      display: grid;
      place-items: center;
      height: 100%;
      min-height: 0;
      padding: 24px;
      color: #6b7280;
      text-align: center;
      background: #ffffff;
    }
    @media (max-width: 767.98px) {
      .role-access-preview-modal .modal-dialog {
        max-width: calc(100vw - 20px);
        height: calc(100dvh - 32px);
        margin: 1rem auto;
      }
      .role-access-preview-modal .modal-content {
        padding: 0.75rem !important;
      }
      .role-access-toolbar-main,
      .role-access-toolbar-meta {
        width: 100%;
      }
      .role-access-toolbar-meta {
        justify-content: flex-end;
      }
      .role-access-action-stack {
        min-width: 0;
      }
      .role-access-preview-shell {
        grid-template-columns: 1fr;
        grid-template-rows: minmax(160px, 32%) minmax(0, 1fr);
        height: 100%;
        min-height: 0;
      }
      .role-access-preview-sidebar {
        max-height: none;
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
      }
      .role-access-preview-bar {
        align-items: flex-start;
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include "includes/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <div class="mb-4">
        <h2 class="mb-2" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Personnel Access Control</h2>
        <p class="text-muted mb-0">
          Set the default module permissions for each personnel department position and use these profiles as the approval baseline for incoming access.
          Official seat and governance access templates are handled separately under Barangay Official Governance.
        </p>
      </div>

      <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelTracker.php')) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-list me-1"></i> Tracker
        </a>
        <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialInvites.php')) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-user-plus me-1"></i> Account Invite
        </a>
        <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelRoleAccess.php')) ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-shield-halved me-1"></i> Personnel Access Control
        </a>
      </div>

      <div class="role-access-stat-grid mb-4">
        <div class="role-access-stat-card">
          <div class="role-access-stat-label">Position Profiles</div>
          <div class="role-access-stat-value" id="roleAccessStatProfiles">0</div>
        </div>
        <div class="role-access-stat-card">
          <div class="role-access-stat-label">Custom Permissions</div>
          <div class="role-access-stat-value" id="roleAccessStatCustom">0</div>
        </div>
        <div class="role-access-stat-card">
          <div class="role-access-stat-label">Default Permissions</div>
          <div class="role-access-stat-value" id="roleAccessStatDefault">0</div>
        </div>
        <div class="role-access-stat-card">
          <div class="role-access-stat-label">Personnel Covered</div>
          <div class="role-access-stat-value" id="roleAccessStatPersonnel">0</div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-4 shadow-sm border role-access-shell">
        <div class="role-access-toolbar mb-3">
          <div class="role-access-toolbar-main">
            <div class="input-group">
              <input id="personnelRoleAccessSearch" class="form-control" placeholder="Search department / position / permission">
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <select id="personnelRoleAccessDepartmentFilter" class="form-select">
              <option value="ALL">All departments</option>
            </select>
            <select id="personnelRoleAccessPositionFilter" class="form-select">
              <option value="ALL">All positions</option>
            </select>
          </div>
          <div class="role-access-toolbar-meta">
            <div class="small text-muted" id="personnelRoleAccessMeta">Loading permission profiles...</div>
            <button id="btnPersonnelRoleAccessCreate" class="btn btn-primary btn-sm" type="button">
              <i class="fas fa-plus me-1"></i> Add Access Profile
            </button>
            <button id="btnPersonnelRoleAccessRefresh" class="btn btn-outline-secondary btn-sm" type="button">
              <i class="fas fa-arrows-rotate me-1"></i> Refresh
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 role-access-table">
            <thead class="table-light">
              <tr>
                <th>Department</th>
                <th>Position</th>
                <th>Personnel Covered</th>
                <th>Permission Source</th>
                <th>Enabled Permissions</th>
                <th>Last Updated</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="personnelRoleAccessTbody">
              <tr><td colspan="7" class="text-center text-muted py-4">Loading access profiles...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="modalPersonnelRoleAccess" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold" id="personnelRoleAccessModalTitle">Manage Access Control Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <input type="hidden" id="personnelRoleAccessDepartment">
          <input type="hidden" id="personnelRoleAccessPosition">

          <div class="alert alert-info mb-0" id="personnelRoleAccessModalNotice">
            This profile applies to the selected department position. Saving here updates the default permissions that users in this position inherit when Access Control approves their access.
          </div>

          <div class="role-access-meta-grid">
            <div class="role-access-meta-card">
              <div class="role-access-meta-label">Profile Scope</div>
              <div class="role-access-meta-value" id="personnelRoleAccessScope">-</div>
            </div>
            <div class="role-access-meta-card">
              <div class="role-access-meta-label">Personnel Covered</div>
              <div class="role-access-meta-value" id="personnelRoleAccessMemberCount">0</div>
            </div>
            <div class="role-access-meta-card">
              <div class="role-access-meta-label">Permission Source</div>
              <div class="role-access-meta-value" id="personnelRoleAccessSource">-</div>
            </div>
            <div class="role-access-meta-card">
              <div class="role-access-meta-label">Current Permissions</div>
              <div class="role-access-meta-value" id="personnelRoleAccessModulesSummary">-</div>
            </div>
          </div>

          <div class="border rounded-4 p-3 bg-light-subtle">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
              <div>
                <div class="fw-bold">Permission Checklist</div>
                <div class="small text-muted">Checked permissions become part of the default access for this position.</div>
              </div>
              <div class="input-group role-access-search">
                <input id="personnelRoleAccessPermissionSearch" class="form-control" placeholder="Search module labels">
                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
              </div>
            </div>
            <div id="personnelRoleAccessGroups" class="role-access-groups"></div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-danger me-auto" id="btnPersonnelRoleAccessReset">Reset To Default Permissions</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnPersonnelRoleAccessSave">Save Permissions</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade role-access-preview-modal" id="modalPersonnelRoleAccessPreview" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold" id="personnelRoleAccessPreviewTitle">Preview Access</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="role-access-preview-shell">
            <aside class="role-access-preview-sidebar">
              <div class="role-access-preview-profile">
                <div class="role-access-preview-profile-title" id="personnelRoleAccessPreviewScope">-</div>
                <div class="role-access-preview-profile-meta" id="personnelRoleAccessPreviewMeta">0 granted modules</div>
              </div>
              <div class="role-access-preview-search">
                <div class="input-group input-group-sm">
                  <input id="personnelRoleAccessPreviewSearch" class="form-control" placeholder="Search granted modules">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                </div>
              </div>
              <div class="role-access-preview-nav" id="personnelRoleAccessPreviewNav"></div>
            </aside>
            <section class="role-access-preview-stage">
              <div class="role-access-preview-bar">
                <div class="role-access-preview-current">
                  <div class="role-access-preview-current-title" id="personnelRoleAccessPreviewCurrentTitle">-</div>
                  <div class="role-access-preview-current-path" id="personnelRoleAccessPreviewCurrentPath">-</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPersonnelRoleAccessPreviewReload">
                    <i class="fas fa-arrows-rotate me-1"></i> Refresh
                  </button>
                </div>
              </div>
              <div class="role-access-preview-frame-wrap">
                <iframe class="role-access-preview-frame" id="personnelRoleAccessPreviewFrame" title="Access preview" scrolling="yes" sandbox="allow-same-origin allow-scripts allow-modals"></iframe>
                <div class="role-access-preview-empty d-none" id="personnelRoleAccessPreviewEmpty">No modules are granted for this access profile.</div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalPersonnelRoleProfileCreate" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Create Role Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-grid gap-3">
          <div class="alert alert-info mb-0">
            Choose the department and personnel position first. You can save a default permission profile even before anyone is assigned to that role.
          </div>

          <div>
            <label for="personnelRoleAccessCreateDepartment" class="form-label small fw-bold mb-1">Department</label>
            <select id="personnelRoleAccessCreateDepartment" class="form-select">
              <option value="">Select department</option>
            </select>
          </div>

          <div>
            <label for="personnelRoleAccessCreatePosition" class="form-label small fw-bold mb-1">Position</label>
            <select id="personnelRoleAccessCreatePosition" class="form-select">
              <option value="">Select position</option>
            </select>
          </div>

          <div class="small text-muted" id="personnelRoleAccessCreateHint">
            Existing profiles can be reopened here. New profiles start with the current default permissions already checked.
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="btnPersonnelRoleAccessCreateContinue">Continue</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.PERSONNEL_ROLE_ACCESS_OPTIONS = {
      apiUrl: <?= json_encode(appUrl('PhpFiles/Admin-End/personnelRoleAccess.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      appBaseUrl: <?= json_encode(rtrim(appUrl(''), '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      previewToken: <?= json_encode((string)$_SESSION['personnel_access_preview_token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      permissionCatalog: <?= json_encode($personnelRolePermissionCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      defaultPermissionKeys: <?= json_encode(array_values(amp_get_default_admin_permission_keys()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script src="../JS-Script-Files/Admin-End/personnelRoleAccessScript.js?v=20260830-preview-10"></script>
</body>
</html>
