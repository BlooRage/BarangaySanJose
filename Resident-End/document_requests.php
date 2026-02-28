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

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="paymentForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Submit Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payRequestId" name="request_id">

        <label class="form-label">Payment Method</label>
        <select class="form-select mb-3" name="payment_method" id="payMethod">
          <option value="gcash">GCash Upload</option>
          <option value="barangay">Pay at Barangay</option>
        </select>

        <div id="proofWrap">
          <label class="form-label">Payment Proof</label>
          <input type="file" class="form-control" name="payment_proof" id="payProof" accept=".jpg,.jpeg,.png,.webp,.pdf">
          <div class="form-text">Required for GCash payments.</div>
        </div>

        <div class="alert alert-danger d-none mt-3" id="payError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Submit Payment</button>
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
  const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
  const paymentForm = document.getElementById('paymentForm');
  const payRequestId = document.getElementById('payRequestId');
  const payMethod = document.getElementById('payMethod');
  const proofWrap = document.getElementById('proofWrap');
  const payError = document.getElementById('payError');
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
          action = `${viewBtn}${proofBtn}<button class="btn btn-sm btn-outline-primary" data-pay="${escapeHtml(r.request_id)}">Submit Payment</button>`;
        } else if (r.stage === 'completed') {
          action = `${viewBtn}${proofBtn}${issuedBtn}`;
        } else {
          action = `${viewBtn}${proofBtn}`;
        }

        const reason = r.status_remarks ? `<div class="text-danger small mt-1">Reason: ${escapeHtml(r.status_remarks)}</div>` : '';
        const feeText = (r.fee_amount !== null && r.fee_amount !== undefined && String(r.fee_amount) !== '')
          ? `₱${Number(r.fee_amount).toFixed(2)}`
          : '-';
        return `
          <tr>
            <td class="fw-semibold">${escapeHtml(r.request_id)}</td>
            <td>${escapeHtml(r.document_type)}</td>
            <td>${escapeHtml(r.purpose || '-')}</td>
            <td>${escapeHtml(feeText)}</td>
            <td>${badge(r.stage, escapeHtml(r.stage_label || r.stage || ''))}${reason}</td>
            <td>${escapeHtml(r.submitted_at || '-')}</td>
            <td>${action}</td>
          </tr>
        `;
      }).join('');

      tbody.querySelectorAll('button[data-pay]').forEach((btn) => {
        btn.addEventListener('click', () => {
          payError.classList.add('d-none');
          payError.textContent = '';
          paymentForm.reset();
          payRequestId.value = btn.getAttribute('data-pay') || '';
          proofWrap.classList.remove('d-none');
          paymentModal.show();
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

  payMethod.addEventListener('change', () => {
    if (payMethod.value === 'barangay') {
      proofWrap.classList.add('d-none');
    } else {
      proofWrap.classList.remove('d-none');
    }
  });

  paymentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    payError.classList.add('d-none');
    payError.textContent = '';

    const fd = new FormData(paymentForm);
    fd.append('action', 'submit_payment');

    try {
      const data = await fetchJson(endpoint, {
        method: 'POST',
        body: fd
      });
      if (!data.success) throw new Error(data.message || 'Unable to submit payment.');
      paymentModal.hide();
      await load();
    } catch (err) {
      payError.textContent = err.message || String(err);
      payError.classList.remove('d-none');
    }
  });

  btnRefresh.addEventListener('click', load);
  load();
})();
</script>
</body>
</html>
