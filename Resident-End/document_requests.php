<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $residentSegmentPos = strpos($scriptName, '/Resident-End/');
    $baseUrl = '';
    if ($residentSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $residentSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}
?>
<?php
$allowUnregistered = false;
require_once __DIR__ . '/includes/resident_access_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Requests</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
  <style>
    #viewModal .modal-body {
      background: #f8fafc;
    }
    #viewModal .tracker-doc-highlight {
      background: #dbeafe;
      color: #1e40af;
      border-radius: 8px;
      padding: 10px 12px;
      font-weight: 700;
      margin-bottom: 12px;
    }
    #viewModal .tracker-form-section {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 12px;
      margin-bottom: 12px;
    }
    #viewModal .tracker-form-section-title {
      font-size: 1rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 10px;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    #viewModal .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    #viewModal .tracker-form-field {
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 8px 10px;
    }
    #viewModal .tracker-form-label {
      margin: 0 0 3px 0;
      font-size: .78rem;
      color: #6b7280;
      font-weight: 600;
      text-transform: capitalize;
    }
    #viewModal .tracker-form-value {
      margin: 0;
      color: #111827;
      font-weight: 600;
      word-break: break-word;
    }
    @media (max-width: 767px) {
      #viewModal .tracker-form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="d-flex min-vh-100">
  <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

  <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
    <div class="bg-white rounded-4 shadow-sm border p-4 mt-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0" style="font-family:'Charis SIL Bold';color:#DE710C;">Document Requests</h3>
        <button id="btnRefresh" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate"></i> Refresh</button>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th>Request ID</th>
              <th>Document</th>
              <th>Purpose</th>
              <th>Fee</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="requestRows">
            <tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="paymentModeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="paymentModeForm">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModeTitle">Select Mode of Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="paymentModeRequestId" name="request_id">
        <label class="form-label">Payment Option</label>
        <select class="form-select" id="paymentModeSelect" name="payment_method" required>
          <option value="">Select an option</option>
          <option value="barangay">Pay in Barangay</option>
          <option value="gcash">Pay via GCash</option>
        </select>
        <div class="alert alert-danger d-none mt-3" id="paymentModeError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="barangayPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pay in Barangay Instructions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Follow these steps:</p>
        <ol class="mb-3">
          <li>Go to the finance window.</li>
          <li>Show your request ID.</li>
          <li>Pay the required amount.</li>
          <li>Go to the issuance office to claim your requested document.</li>
        </ol>
        <p class="text-muted small mb-0" id="barangayDeadlineNote">You have until - to complete the payment before the request is automatically cancelled.</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="barangayChangeModeBtn" class="btn btn-outline-primary">Change Mode of Payment</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="gcashPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="gcashPaymentForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Pay via GCash</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="gcashRequestId" name="request_id">
        <input type="hidden" name="payment_method" value="gcash">
        <div class="mb-3 text-center">
          <div class="form-text mb-2">Scan this QR for GCash payment.</div>
          <img
            id="gcashQrImage"
            src="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/GCASH_QR.jpg"
            alt="GCash QR Code"
            style="max-width:220px;width:100%;height:auto;border:1px solid #ddd;border-radius:8px;"
            onerror="this.style.display='none';document.getElementById('gcashQrMissing')?.classList.remove('d-none');"
          >
          <div id="gcashQrMissing" class="form-text text-danger d-none">GCash QR image not found. Please contact the barangay office.</div>
        </div>
        <label class="form-label">Reference ID / Transaction Number</label>
        <input type="text" class="form-control mb-3" name="payment_reference" id="gcashReference" placeholder="Enter GCash transaction number" required>
        <label class="form-label">Payment Proof</label>
        <input type="file" class="form-control" name="payment_proof" id="gcashProof" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
        <div class="form-text">Upload your GCash payment proof.</div>
        <div class="alert alert-danger d-none mt-3" id="gcashError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="gcashChangeModeBtn" class="btn btn-outline-secondary">Change Mode of Payment</button>
        <button type="submit" class="btn btn-primary">Pay Now</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentProofTitle">Payment Proof</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const endpoint = '<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php';
  const tbody = document.getElementById('requestRows');
  const btnRefresh = document.getElementById('btnRefresh');
  const paymentModeModal = new bootstrap.Modal(document.getElementById('paymentModeModal'));
  const paymentModeForm = document.getElementById('paymentModeForm');
  const paymentModeTitle = document.getElementById('paymentModeTitle');
  const paymentModeRequestId = document.getElementById('paymentModeRequestId');
  const paymentModeSelect = document.getElementById('paymentModeSelect');
  const paymentModeError = document.getElementById('paymentModeError');
  const barangayPaymentModal = new bootstrap.Modal(document.getElementById('barangayPaymentModal'));
  const barangayChangeModeBtn = document.getElementById('barangayChangeModeBtn');
  const barangayDeadlineNote = document.getElementById('barangayDeadlineNote');
  const gcashPaymentModal = new bootstrap.Modal(document.getElementById('gcashPaymentModal'));
  const gcashPaymentForm = document.getElementById('gcashPaymentForm');
  const gcashRequestId = document.getElementById('gcashRequestId');
  const gcashReference = document.getElementById('gcashReference');
  const gcashProof = document.getElementById('gcashProof');
  const gcashError = document.getElementById('gcashError');
  const gcashChangeModeBtn = document.getElementById('gcashChangeModeBtn');
  const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const paymentProofModal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
  const paymentProofTitle = document.getElementById('paymentProofTitle');
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  let itemById = new Map();

  function badge(stage, label) {
    const key = String(stage || '').toLowerCase();
    if (key === 'cancelled') return `<span class="badge bg-danger">${label}</span>`;
    if (key.includes('rejected')) return `<span class="badge bg-danger">${label}</span>`;
    if (key === 'completed') return `<span class="badge bg-success">${label}</span>`;
    if (key === 'ready_for_claim') return `<span class="badge bg-primary">${label}</span>`;
    if (key === 'for_payment') return `<span class="badge bg-warning text-dark">${label}</span>`;
    return `<span class="badge bg-secondary">${label}</span>`;
  }

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'}[m]));
  }

  function friendlyLabel(key) {
    const raw = String(key || '').trim();
    if (!raw) return '';
    return raw.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function isEmptyFieldValue(value) {
    const text = String(value ?? '').trim();
    if (!text) return true;
    return ['-', '—', 'n/a', 'na', 'null', 'undefined'].includes(text.toLowerCase());
  }

  function formField(label, value) {
    return `
      <div class="tracker-form-field">
        <p class="tracker-form-label">${escapeHtml(label)}</p>
        <p class="tracker-form-value">${escapeHtml(value)}</p>
      </div>
    `;
  }

  function formSection(title, content) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">${escapeHtml(title)}</h6>
        ${content}
      </section>
    `;
  }

  function parsePayload(payloadLike) {
    if (payloadLike && typeof payloadLike === 'object' && !Array.isArray(payloadLike)) {
      return payloadLike;
    }
    const text = String(payloadLike ?? '').trim();
    if (!text) return {};
    try {
      const parsed = JSON.parse(text);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return parsed;
      }
    } catch (_) {}
    return {};
  }

  function formatDateTime(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const normalized = raw.replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleString();
  }

  function paymentActions(row) {
    const requestId = String(row.request_id || '');
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary me-1" data-view="${escapeHtml(requestId)}">View</button>`;
    const method = String(row.payment_method || '').toLowerCase();
    const hasMode = method === 'gcash' || method === 'barangay';
    const modeLabel = hasMode ? 'Change Mode of Payment' : 'Select Mode of Payment';
    const modeButtonClass = hasMode ? 'btn-outline-secondary' : 'btn-outline-primary';
    const modeBtn = `<button type="button" class="btn btn-sm ${modeButtonClass} me-1" data-open-mode="${escapeHtml(requestId)}">${modeLabel}</button>`;
    const payNowBtn = `<button class="btn btn-sm btn-primary" data-pay-now="${escapeHtml(requestId)}">Pay Now</button>`;

    if (method === 'gcash') {
      return `${viewBtn}${payNowBtn}${modeBtn}`;
    }
    if (method === 'barangay') {
      return `${viewBtn}${modeBtn}`;
    }
    return `${viewBtn}${modeBtn}`;
  }

  function openModeModal(requestId) {
    const row = itemById.get(String(requestId || ''));
    const method = String(row?.payment_method || '').toLowerCase();
    paymentModeError.classList.add('d-none');
    paymentModeError.textContent = '';
    paymentModeForm.reset();
    paymentModeRequestId.value = String(requestId || '');
    paymentModeTitle.textContent = method ? 'Change Mode of Payment' : 'Select Mode of Payment';
    if (method === 'gcash' || method === 'barangay') {
      paymentModeSelect.value = method;
    }
    paymentModeModal.show();
  }

  function openGcashModal(requestId) {
    gcashError.classList.add('d-none');
    gcashError.textContent = '';
    gcashPaymentForm.reset();
    gcashRequestId.value = String(requestId || '');
    gcashPaymentModal.show();
  }

  function updateBarangayDeadlineNote(requestId) {
    if (!barangayDeadlineNote) return;
    const row = itemById.get(String(requestId || ''));
    const deadlineRaw = String(row?.payment_deadline || '').trim();
    if (deadlineRaw) {
      const normalized = deadlineRaw.replace(' ', 'T');
      const dt = new Date(normalized);
      const mmddyyyy = Number.isNaN(dt.getTime())
        ? deadlineRaw
        : `${String(dt.getMonth() + 1).padStart(2, '0')}/${String(dt.getDate()).padStart(2, '0')}/${dt.getFullYear()}`;
      barangayDeadlineNote.textContent = `You have until ${mmddyyyy} to complete the payment before the request is automatically cancelled.`;
    } else {
      barangayDeadlineNote.textContent = 'You have until the payment deadline to complete the payment before the request is automatically cancelled.';
    }
  }

  async function setPaymentMode(requestId, paymentMethod) {
    const fd = new FormData();
    fd.append('action', 'select_payment_mode');
    fd.append('request_id', String(requestId || ''));
    fd.append('payment_method', String(paymentMethod || ''));

    const data = await fetchJson(endpoint, {
      method: 'POST',
      body: fd
    });
    if (!data.success) {
      throw new Error(data.message || 'Unable to save payment mode.');
    }
    return data;
  }

  async function fetchJson(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const res = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    const contentType = (res.headers.get('content-type') || '').toLowerCase();
    const text = await res.text();
    const looksHtml = text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html');

    if (!contentType.includes('application/json')) {
      if (looksHtml) {
        throw new Error('Session expired or invalid endpoint response. Please reload and login again.');
      }
      throw new Error('Unexpected response format from server.');
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (_) {
      throw new Error('Invalid JSON response from server.');
    }

    if (!res.ok) {
      throw new Error(data?.message || `Request failed (${res.status}).`);
    }
    return data;
  }

  async function load() {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>';
    try {
      const data = await fetchJson(endpoint + '?action=list');
      if (!data.success) throw new Error(data.message || 'Unable to load requests');

      const items = Array.isArray(data.items) ? data.items : [];
      itemById = new Map(items.map((it) => [String(it.request_id), it]));
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No requests yet.</td></tr>';
        return;
      }

      tbody.innerHTML = items.map((r) => {
        let action = '<span class="text-muted small">-</span>';
        const viewBtn = `<button class="btn btn-sm btn-outline-secondary me-1" data-view="${escapeHtml(r.request_id)}">View</button>`;
        const proofBtn = r.payment_proof_path
          ? `<button class="btn btn-sm btn-outline-dark me-1" data-proof="${escapeHtml(r.request_id)}">View Payment</button>`
          : '';
        const issuedBtn = `<button class="btn btn-sm btn-success" data-issued="${escapeHtml(r.request_id)}">View Document</button>`;
        if (r.stage === 'for_payment' || r.stage === 'payment_rejected') {
          action = paymentActions(r);
        } else if (r.stage === 'completed') {
          action = `${viewBtn}${proofBtn}${issuedBtn}`;
        } else {
          action = `${viewBtn}${proofBtn}`;
        }

        const reason = r.status_remarks ? `<div class="text-danger small mt-1">Reason: ${escapeHtml(r.status_remarks)}</div>` : '';
        const paymentDeadline = (r.stage === 'for_payment' || r.stage === 'payment_rejected') && r.payment_deadline
          ? `<div class="text-muted small mt-1">Payment deadline: ${escapeHtml(formatDateTime(r.payment_deadline))}</div>`
          : '';
        const feeText = (r.fee_amount !== null && r.fee_amount !== undefined && String(r.fee_amount) !== '')
          ? `₱${Number(r.fee_amount).toFixed(2)}`
          : '-';
        return `
          <tr>
            <td class="fw-semibold">${escapeHtml(r.request_id)}</td>
            <td>${escapeHtml(r.document_type)}</td>
            <td>${escapeHtml(r.purpose || '-')}</td>
            <td>${escapeHtml(feeText)}</td>
            <td>${badge(r.stage, escapeHtml(r.stage_label || r.stage || ''))}${reason}${paymentDeadline}</td>
            <td>${escapeHtml(r.submitted_at || '-')}</td>
            <td>${action}</td>
          </tr>
        `;
      }).join('');

      tbody.querySelectorAll('button[data-open-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-open-mode') || '');
          if (!id) return;
          openModeModal(id);
        });
      });

      tbody.querySelectorAll('button[data-pay-now]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-pay-now') || '');
          openGcashModal(id);
        });
      });

      tbody.querySelectorAll('button[data-view]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-view') || '');
          const row = itemById.get(id);
          if (!row) return;
          const payload = parsePayload(row.payload);
          const technicalKeys = new Set(['action', 'csrf_token', 'redirect']);
          const submittedFields = [];
          Object.keys(payload).forEach((key) => {
            const k = String(key || '').trim();
            if (!k || technicalKeys.has(k)) return;
            const value = payload[key];
            const text = Array.isArray(value) || (value && typeof value === 'object')
              ? JSON.stringify(value)
              : String(value ?? '').trim();
            if (isEmptyFieldValue(text)) return;
            submittedFields.push({ label: friendlyLabel(k), value: text });
          });

          const requestDetailsRaw = String(row.request_details ?? '').trim();
          if (!submittedFields.length && requestDetailsRaw && requestDetailsRaw !== '{}' && requestDetailsRaw !== '[]') {
            submittedFields.push({ label: 'Request Details', value: requestDetailsRaw });
          }

          if (!submittedFields.length) {
            [
              ['Purpose', row.purpose],
              ['Status', row.stage_label || row.stage],
              ['Submitted At', row.submitted_at],
              ['Status Remarks', row.status_remarks]
            ].forEach(([label, value]) => {
              const text = String(value ?? '').trim();
              if (!isEmptyFieldValue(text)) {
                submittedFields.push({ label, value: text });
              }
            });
          }

          const gridClass = submittedFields.length <= 1 ? 'cols-1' : '';
          const gridHtml = submittedFields.length
            ? `<div class="tracker-form-grid ${gridClass}">${submittedFields.map((f) => formField(f.label, f.value)).join('')}</div>`
            : '<div class="text-muted">No submitted details.</div>';

          let html = `<div class="tracker-doc-highlight">Document Requested: ${escapeHtml(row.document_type || '-')}</div>`;
          html += formSection('Submitted Form Details', gridHtml);
          viewDetailsBody.innerHTML = html;
          if (viewModalTitle) {
            const requestId = String(row.request_id || '').trim();
            viewModalTitle.textContent = requestId ? `Certificate Request (#${requestId})` : 'Certificate Request';
          }
          viewModal.show();
        });
      });

      tbody.querySelectorAll('button[data-proof]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-proof') || '');
          const row = itemById.get(id);
          if (!row || !row.payment_proof_path) return;
          const proofUrl = `${endpoint}?action=view_payment_proof&request_id=${encodeURIComponent(id)}`;
          openFileViewerModal({
            title: 'Payment Proof',
            viewUrl: proofUrl,
            linkText: 'Open in New Tab',
            linkUrl: proofUrl,
            isPdf: String(row.payment_proof_path || '').toLowerCase().endsWith('.pdf')
          });
        });
      });

      tbody.querySelectorAll('button[data-issued]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-issued') || '');
          const viewUrl = `${endpoint}?action=view_issued&request_id=${encodeURIComponent(id)}`;
          const downloadUrl = `${endpoint}?action=download_issued&request_id=${encodeURIComponent(id)}`;
          openFileViewerModal({
            title: 'Issued Document (PDF)',
            viewUrl,
            linkText: 'Download',
            linkUrl: downloadUrl,
            isPdf: true
          });
        });
      });
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.message || err)}</td></tr>`;
    }
  }

  function openFileViewerModal({ title, viewUrl, linkText, linkUrl, isPdf }) {
    if (paymentProofTitle) {
      paymentProofTitle.textContent = String(title || 'Document');
    }
    paymentProofOpenNew.textContent = String(linkText || 'Open in New Tab');
    paymentProofOpenNew.href = String(linkUrl || viewUrl || '#');

    if (isPdf) {
      paymentProofWrap.innerHTML = `<iframe src="${viewUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
    } else {
      paymentProofWrap.innerHTML = `<img src="${viewUrl}" alt="Document Preview" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
    }
    paymentProofModal.show();
  }

  gcashPaymentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    gcashError.classList.add('d-none');
    gcashError.textContent = '';

    const fd = new FormData(gcashPaymentForm);
    fd.append('action', 'submit_payment');

    try {
      const data = await fetchJson(endpoint, {
        method: 'POST',
        body: fd
      });
      if (!data.success) throw new Error(data.message || 'Unable to submit payment.');
      gcashPaymentModal.hide();
      await load();
    } catch (err) {
      gcashError.textContent = err.message || String(err);
      gcashError.classList.remove('d-none');
    }
  });

  paymentModeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    paymentModeError.classList.add('d-none');
    paymentModeError.textContent = '';

    const requestId = String(paymentModeRequestId.value || '');
    const mode = String(paymentModeSelect.value || '').toLowerCase();
    if (!requestId || (mode !== 'gcash' && mode !== 'barangay')) {
      paymentModeError.textContent = 'Please select a valid payment option.';
      paymentModeError.classList.remove('d-none');
      return;
    }

    try {
      await setPaymentMode(requestId, mode);
      paymentModeModal.hide();
      paymentModeRequestId.value = requestId;
      await load();
      if (mode === 'barangay') {
        updateBarangayDeadlineNote(requestId);
        barangayPaymentModal.show();
      }
    } catch (err) {
      paymentModeError.textContent = err.message || String(err);
      paymentModeError.classList.remove('d-none');
    }
  });

  gcashChangeModeBtn.addEventListener('click', () => {
    const requestId = String(gcashRequestId.value || '');
    gcashPaymentModal.hide();
    if (requestId) openModeModal(requestId);
  });

  barangayChangeModeBtn.addEventListener('click', () => {
    const requestId = String(paymentModeRequestId.value || gcashRequestId.value || '');
    barangayPaymentModal.hide();
    if (requestId) openModeModal(requestId);
  });

  btnRefresh.addEventListener('click', load);
  load();
})();
</script>
</body>
</html>
