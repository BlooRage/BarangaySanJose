<?php
require_once __DIR__ . '/../../PhpFiles/General/officialSignature.php';
$osigUserId = trim((string)($_SESSION['user_id'] ?? ''));
$osigAccount = isset($currentOfficialAccount) && is_array($currentOfficialAccount)
    ? $currentOfficialAccount
    : (($osigUserId !== '' && isset($conn) && $conn instanceof mysqli) ? amp_get_official_account_by_user_id($conn, $osigUserId) : null);
$osigIsChairman = is_array($osigAccount) && amp_get_protected_code($osigAccount) === 'BARANGAY_CAPTAIN';
$osigOfficialId = trim((string)($osigAccount['official_id'] ?? ''));
if ($osigIsChairman && $osigOfficialId !== '') {
    $osigNameStmt = $conn->prepare("SELECT firstname, lastname FROM officialinformationtbl WHERE official_id = ? LIMIT 1");
    if ($osigNameStmt) {
        $osigNameStmt->bind_param('s', $osigOfficialId);
        $osigNameStmt->execute();
        $osigNameRow = $osigNameStmt->get_result()->fetch_assoc();
        $osigNameStmt->close();
        if ($osigNameRow) $osigAccount = array_merge($osigAccount, pii_decrypt_official_row($osigNameRow) ?? $osigNameRow);
    }
}
$osigCurrent = $osigIsChairman ? osig_get_current($conn, $osigOfficialId, $osigUserId) : null;
$osigShowCard = !empty($officialSignatureShowCard);
$osigAutoPrompt = !empty($officialSignatureAutoPrompt) && !$osigCurrent && empty($_SESSION['official_signature_skipped']);
?>
<?php if ($osigIsChairman): ?>
<style>
  #officialSignatureModal,#officialSignatureReminderModal{font-family:'Geist',Arial,sans-serif;color:#172132;-webkit-font-smoothing:antialiased}
  #officialSignatureModal button,#officialSignatureModal input,#officialSignatureReminderModal button{font-family:'Geist',Arial,sans-serif}
  #officialSignatureModal .modal-content{border:0;border-radius:24px;overflow:hidden;box-shadow:0 28px 70px rgba(15,23,42,.24)}
  #officialSignatureModal .modal-dialog{width:calc(100% - 2rem);max-width:900px}
  #officialSignatureModal .modal-header{position:relative;display:block;padding:1.5rem 3.5rem 1.25rem;text-align:center;background:linear-gradient(180deg,#fffaf3,#fff);border-bottom:1px solid #f3dcc3}
  #officialSignatureModal .modal-header .btn-close{position:absolute;right:1.4rem;top:1.35rem}
  #officialSignatureModal .modal-body{padding:1.35rem 1.5rem;background:#fff}
  .osig-kicker{display:flex;align-items:center;gap:.45rem;color:var(--dashboard-accent-deep,#d97a1d);font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
  .osig-title{font-family:'Geist',Arial,sans-serif;color:#172132;font-size:1.35rem;font-weight:800;letter-spacing:0}
  .osig-status{display:inline-flex;align-items:center;gap:.4rem;margin-top:.65rem;padding:.35rem .7rem;border-radius:999px;background:#fff0d9;color:#a65306;font-size:.76rem;font-weight:800;border:1px solid #f4cf9d}
  .osig-status-dot{width:.5rem;height:.5rem;border-radius:50%;background:var(--dashboard-accent,#fe993c)}
  .osig-setup-icon{display:grid;place-items:center;width:58px;height:58px;margin:0 auto .7rem;border-radius:18px;background:var(--dashboard-accent-soft,#fff4e8);color:var(--dashboard-accent-deep,#d97a1d);font-size:1.35rem;box-shadow:0 9px 22px rgba(222,113,12,.12)}
  .osig-intro{display:flex;gap:.85rem;padding:1rem;border:1px solid #f2d4ad;border-radius:16px;background:#fffaf3;color:#5c4936}
  .osig-intro-icon{display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:13px;background:#ffe2bc;color:#c5670d;font-size:1.05rem}
  #osigTabs{gap:.5rem;padding:.35rem;background:#f4f6f8;border-radius:14px}
  #osigTabs .nav-link{border-radius:10px;color:#5f6877;font-weight:700;padding:.65rem 1rem}
  #osigTabs .nav-link.active{background:#fff;color:#c2630b;box-shadow:0 3px 12px rgba(15,23,42,.09)}
  .osig-workspace{border:1px solid #e2e7ed!important;border-radius:18px!important;background:#f8fafc!important}
  #osigCanvas{height:170px!important;border:2px dashed #cbd3dd!important;border-radius:14px!important}
  .osig-preview-card{background:linear-gradient(180deg,#fff,#fffaf4)!important;border-color:#efd8bb!important;border-radius:18px!important}
  .osig-placement-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:.75rem;align-items:end}
  .osig-placement-grid label{display:block;margin:0;color:#49515d}
  .osig-placement-grid input[type="range"]{width:100%;accent-color:#de710c}
  #officialSignatureModal .modal-footer{padding:1rem 1.5rem;background:#fbfcfd;border-top:1px solid #e6e9ee}
  #officialSignatureReminderModal .modal-dialog{max-width:520px}
  #officialSignatureReminderModal .modal-content{border:0;border-radius:12px;overflow:hidden;box-shadow:0 1rem 3rem rgba(15,23,42,.22)}
  #officialSignatureReminderModal .modal-header{padding:1.1rem 1.25rem .8rem;background:#fff}
  #officialSignatureReminderModal .modal-body{padding:1rem 1.25rem 1.1rem;text-align:center;background:#fff}
  #officialSignatureReminderModal .modal-footer{padding:0 1.25rem 1.25rem;background:#fff}
  .osig-reminder-icon{display:grid;place-items:center;width:58px;height:58px;margin:0 auto .9rem;border-radius:16px;background:var(--dashboard-accent-soft,#fff4e8);color:var(--dashboard-accent-deep,#d97a1d);font-size:1.35rem}
  .osig-reminder-title{font-family:'Geist',Arial,sans-serif;color:#172132;font-size:1.25rem;font-weight:800;letter-spacing:0}
  .osig-reminder-note{padding:.75rem .9rem;border-radius:10px;background:#fff4e8;color:#8a4a0d;font-size:.88rem}
  .osig-save-btn{background:var(--dashboard-accent-deep,#d97a1d)!important;border-color:var(--dashboard-accent-deep,#d97a1d)!important;font-weight:700}
  .osig-save-btn:hover{background:#bd6514!important;border-color:#bd6514!important}
  #osigColor{border-color:#e1b982;border-radius:10px}
  #osigWidth{accent-color:#de710c}
  @media(max-width:767.98px){.osig-placement-grid{grid-template-columns:1fr}}
  @media(max-width:575.98px){#officialSignatureModal .modal-header{padding:1.25rem 2.75rem 1rem}#officialSignatureModal .modal-body,#officialSignatureModal .modal-footer{padding:1rem}.osig-title{font-size:1.2rem}#osigCanvas{height:145px!important}}
</style>
<?php if ($osigShowCard): ?>
<div class="card shadow-sm mb-4 profile-card">
  <div class="card-header d-flex justify-content-between align-items-center"><span>Official Signature</span></div>
  <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <?php if ($osigCurrent): ?>
        <img src="<?= htmlspecialchars(appUrl((string)$osigCurrent['file_path']), ENT_QUOTES, 'UTF-8') ?>" alt="Current official signature" style="max-width:260px;max-height:90px;object-fit:contain;background:#fff;border:1px solid #dee2e6;border-radius:10px;padding:.5rem;">
        <div class="small text-muted mt-2">Active since <?= htmlspecialchars(date('M d, Y', strtotime((string)$osigCurrent['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
      <?php else: ?>
        <div class="fw-semibold">No official signature configured</div>
        <div class="small text-muted">Create one for supported certificates, clearances, and Barangay IDs.</div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#officialSignatureModal"><?= $osigCurrent ? 'Change Signature' : 'Set Up Signature' ?></button>
      <?php if ($osigCurrent): ?><button type="button" class="btn btn-outline-danger" id="osigRemoveButton">Remove</button><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($osigAutoPrompt): ?>
<div class="modal fade" id="officialSignatureReminderModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0 bg-white">
        <h5 class="modal-title w-100 text-center text-dark">Official Account Setup</h5>
      </div>
      <hr class="my-0">
      <div class="modal-body">
        <div class="osig-reminder-icon"><i class="fas fa-file-signature"></i></div>
        <div class="osig-kicker justify-content-center">Official account reminder</div>
        <h5 class="osig-reminder-title mt-2 mb-2">Set Up Your Official Signature</h5>
        <p class="text-muted mb-3">Your signature has not been configured yet. Set it up to use it on supported certificates, clearances, and Barangay IDs.</p>
        <div class="osig-reminder-note"><i class="fas fa-circle-info me-1"></i> You can skip this reminder and continue using your assigned modules.</div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2">
        <button type="button" class="btn btn-secondary flex-fill" id="osigSkipButton" data-bs-dismiss="modal">Skip for Now</button>
        <button type="button" class="btn btn-primary osig-save-btn flex-fill" id="osigSetupNow"><i class="fas fa-pen-nib me-1"></i> Set Up Now</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="officialSignatureModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="osig-setup-icon"><i class="fas fa-file-signature"></i></div>
          <div class="osig-kicker justify-content-center">Official account setup</div>
          <h5 class="modal-title osig-title mt-1 mb-1">Set Up Your Official Signature</h5>
          <div class="small text-muted">Complete this setup to sign supported certificates, clearances, and Barangay IDs.</div>
          <div class="osig-status"><span class="osig-status-dot"></span> Setup in progress</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="osig-intro mb-3">
          <div class="osig-intro-icon"><i class="fas fa-file-signature"></i></div>
          <div><div class="fw-bold">Required for digital document signing</div><div class="small">Your account remains accessible if you skip, but documents cannot use your official signature until setup is completed.</div></div>
        </div>
        <ul class="nav nav-pills mb-3" id="osigTabs">
          <li class="nav-item flex-fill"><button type="button" class="nav-link active w-100" data-osig-mode="draw"><i class="fas fa-pen me-1"></i> Draw</button></li>
          <li class="nav-item flex-fill"><button type="button" class="nav-link w-100" data-osig-mode="upload"><i class="fas fa-upload me-1"></i> Upload</button></li>
          <li class="nav-item flex-fill"><button type="button" class="nav-link w-100" data-osig-mode="type"><i class="fas fa-font me-1"></i> Type</button></li>
        </ul>
        <div id="osigAlert" class="alert alert-danger d-none"></div>
        <div class="border rounded-3 p-3 bg-light osig-workspace">
          <div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-bold">Signature workspace</div><div class="small text-muted">Sign inside the box</div></div>
          <canvas id="osigCanvas" width="900" height="260" style="display:block;width:100%;height:220px;background:#fff;border:1px dashed #adb5bd;border-radius:12px;touch-action:none;"></canvas>
          <div class="d-flex flex-wrap gap-2 mt-3 align-items-center" id="osigDrawTools">
            <label class="small fw-semibold">Ink <input type="color" id="osigColor" value="#111827" class="form-control form-control-color d-inline-block ms-1"></label>
            <label class="small fw-semibold">Thickness <input type="range" id="osigWidth" min="2" max="12" value="5" class="align-middle ms-1"></label>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="osigClear">Clear</button>
          </div>
          <div class="d-none mt-3" id="osigUploadTools">
            <label class="form-label fw-semibold">Choose signature image</label>
            <input type="file" class="form-control" id="osigFile" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">The image will be converted to a transparent-ready PNG canvas.</div>
            <div class="osig-placement-grid mt-3">
              <label class="small fw-semibold">Horizontal position <input type="range" id="osigUploadX" min="-100" max="100" value="0"></label>
              <label class="small fw-semibold">Vertical position <input type="range" id="osigUploadY" min="-100" max="100" value="0"></label>
              <label class="small fw-semibold">Size <input type="range" id="osigUploadScale" min="20" max="160" value="100"></label>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="osigResetPlacement">Reset</button>
            </div>
          </div>
          <div class="d-none mt-3" id="osigTypeTools">
            <label class="form-label fw-semibold">Type your full name</label>
            <input type="text" class="form-control" id="osigTypedName" maxlength="100" value="<?= htmlspecialchars(trim((string)($osigAccount['firstname'] ?? '') . ' ' . (string)($osigAccount['lastname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="osigRenderTyped">Generate Signature</button>
          </div>
        </div>
        <div class="mt-3 p-3 border rounded-3 text-center bg-white osig-preview-card">
          <div class="small text-uppercase text-muted fw-semibold">Document preview</div>
          <div style="height:80px;display:flex;align-items:end;justify-content:center;"><img id="osigPreview" alt="Signature preview" style="display:none;max-width:280px;max-height:75px;object-fit:contain;"></div>
          <div class="border-top mx-auto" style="max-width:320px;"></div>
          <div class="fw-bold mt-1"><?= htmlspecialchars(trim((string)($osigAccount['firstname'] ?? '') . ' ' . (string)($osigAccount['lastname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="small text-muted">Punong Barangay</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary osig-save-btn px-4" id="osigSave"><i class="fas fa-check me-1"></i> Complete Setup</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('officialSignatureModal');
  const canvas = document.getElementById('osigCanvas');
  if (!modalEl || !canvas) return;
  const ctx = canvas.getContext('2d');
  const preview = document.getElementById('osigPreview');
  const alertEl = document.getElementById('osigAlert');
  const uploadX = document.getElementById('osigUploadX');
  const uploadY = document.getElementById('osigUploadY');
  const uploadScale = document.getElementById('osigUploadScale');
  let mode = 'draw', drawing = false, hasInk = false;
  let uploadImage = null, uploadObjectUrl = '';
  const clear = () => { ctx.clearRect(0, 0, canvas.width, canvas.height); hasInk = false; preview.style.display = 'none'; };
  const resetUploadPlacement = () => { if(uploadX)uploadX.value='0'; if(uploadY)uploadY.value='0'; if(uploadScale)uploadScale.value='100'; };
  const renderUploadImage = () => {
    if (!uploadImage) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const baseScale = Math.min(canvas.width / uploadImage.width, canvas.height / uploadImage.height);
    const scale = baseScale * (Number(uploadScale?.value || 100) / 100);
    const w = uploadImage.width * scale;
    const h = uploadImage.height * scale;
    const x = ((canvas.width - w) / 2) + (Number(uploadX?.value || 0) / 100) * (canvas.width / 2);
    const y = ((canvas.height - h) / 2) + (Number(uploadY?.value || 0) / 100) * (canvas.height / 2);
    ctx.drawImage(uploadImage, x, y, w, h);
    hasInk = true;
    refresh();
  };
  const point = (e) => { const r=canvas.getBoundingClientRect(); const p=e.touches?.[0]||e; return {x:(p.clientX-r.left)*canvas.width/r.width,y:(p.clientY-r.top)*canvas.height/r.height}; };
  const start = e => { if(mode!=='draw')return; e.preventDefault(); drawing=true; const p=point(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); };
  const move = e => { if(!drawing)return; e.preventDefault(); const p=point(e); ctx.lineWidth=Number(document.getElementById('osigWidth').value); ctx.strokeStyle=document.getElementById('osigColor').value; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.lineTo(p.x,p.y); ctx.stroke(); hasInk=true; refresh(); };
  const stop = () => { drawing=false; ctx.closePath(); };
  const refresh = () => { if(!hasInk)return; preview.src=canvas.toDataURL('image/png'); preview.style.display='inline-block'; };
  ['pointerdown'].forEach(n=>canvas.addEventListener(n,start)); canvas.addEventListener('pointermove',move); window.addEventListener('pointerup',stop);
  document.getElementById('osigClear').addEventListener('click', clear);
  document.querySelectorAll('[data-osig-mode]').forEach(btn=>btn.addEventListener('click',()=>{ mode=btn.dataset.osigMode; document.querySelectorAll('[data-osig-mode]').forEach(b=>b.classList.toggle('active',b===btn)); document.getElementById('osigDrawTools').classList.toggle('d-none',mode!=='draw'); document.getElementById('osigUploadTools').classList.toggle('d-none',mode!=='upload'); document.getElementById('osigTypeTools').classList.toggle('d-none',mode!=='type'); if(mode!=='upload') uploadImage=null; clear(); }));
  document.getElementById('osigFile').addEventListener('change', e=>{ const file=e.target.files?.[0]; if(!file)return; const img=new Image(); if(uploadObjectUrl) URL.revokeObjectURL(uploadObjectUrl); uploadObjectUrl=URL.createObjectURL(file); img.onload=()=>{ uploadImage=img; resetUploadPlacement(); renderUploadImage(); }; img.src=uploadObjectUrl; });
  [uploadX,uploadY,uploadScale].forEach(input=>input?.addEventListener('input',renderUploadImage));
  document.getElementById('osigResetPlacement')?.addEventListener('click',()=>{ resetUploadPlacement(); renderUploadImage(); });
  document.getElementById('osigRenderTyped').addEventListener('click',()=>{ const name=document.getElementById('osigTypedName').value.trim(); clear(); if(!name)return; ctx.fillStyle=document.getElementById('osigColor').value; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.font='italic 92px cursive'; ctx.fillText(name,canvas.width/2,canvas.height/2,canvas.width-60); hasInk=true; refresh(); });
  document.getElementById('osigSave').addEventListener('click',async()=>{ if(!hasInk){alertEl.textContent='Create or upload a signature first.';alertEl.classList.remove('d-none');return;} const btn=document.getElementById('osigSave');btn.disabled=true; const body=new FormData();body.append('action','save');body.append('creation_method',mode);body.append('signature_data',canvas.toDataURL('image/png')); try{const res=await fetch('../PhpFiles/Admin-End/officialSignature.php',{method:'POST',body});const data=await res.json();if(!res.ok||!data.success)throw new Error(data.message||'Unable to save signature.');location.reload();}catch(e){alertEl.textContent=e.message;alertEl.classList.remove('d-none');btn.disabled=false;} });
  document.getElementById('osigRemoveButton')?.addEventListener('click',async()=>{if(!confirm('Remove the active official signature?'))return;const body=new FormData();body.append('action','remove');const res=await fetch('../PhpFiles/Admin-End/officialSignature.php',{method:'POST',body});const data=await res.json();if(data.success)location.reload();else alert(data.message||'Unable to remove signature.');});
  document.getElementById('osigSkipButton')?.addEventListener('click',()=>{const body=new FormData();body.append('action','skip');fetch('../PhpFiles/Admin-End/officialSignature.php',{method:'POST',body}).catch(()=>{});});
  const reminderEl = document.getElementById('officialSignatureReminderModal');
  document.getElementById('osigSetupNow')?.addEventListener('click',()=>{
    if (!reminderEl) return;
    reminderEl.addEventListener('hidden.bs.modal',()=>bootstrap.Modal.getOrCreateInstance(modalEl).show(),{once:true});
    bootstrap.Modal.getOrCreateInstance(reminderEl).hide();
  });
  <?php if ($osigAutoPrompt): ?>if(reminderEl) bootstrap.Modal.getOrCreateInstance(reminderEl).show();<?php endif; ?>
});
</script>
<?php endif; ?>
