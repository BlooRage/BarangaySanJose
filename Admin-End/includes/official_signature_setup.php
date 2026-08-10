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
  #officialSignatureModal .modal-header{position:relative;display:flex;align-items:center;justify-content:center;padding:.8rem 3.5rem;text-align:center;background:linear-gradient(180deg,#fffaf3,#fff);border-bottom:1px solid #f3dcc3}
  #officialSignatureModal .modal-header .btn-close{position:absolute;right:1.25rem;top:50%;transform:translateY(-50%)}
  #officialSignatureModal .modal-body{padding:1.35rem 1.5rem;background:#fff}
  .osig-kicker{display:flex;align-items:center;gap:.45rem;color:var(--dashboard-accent-deep,#d97a1d);font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
  .osig-title{font-family:'Geist',Arial,sans-serif;color:#172132;font-size:1.2rem;font-weight:800;line-height:1.15;letter-spacing:0}
  .osig-intro{display:flex;gap:.85rem;padding:1rem;border:1px solid #f2d4ad;border-radius:16px;background:#fffaf3;color:#5c4936}
  .osig-intro-icon{display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:13px;background:#ffe2bc;color:#c5670d;font-size:1.05rem}
  #osigTabs{gap:.5rem;padding:.35rem;background:#f4f6f8;border-radius:14px}
  #osigTabs .nav-link{border-radius:10px;color:#5f6877;font-weight:700;padding:.65rem 1rem}
  #osigTabs .nav-link.active{background:#fff;color:#c2630b;box-shadow:0 3px 12px rgba(15,23,42,.09)}
  .osig-workspace{border:1px solid #e2e7ed!important;border-radius:18px!important;background:#f8fafc!important}
  .osig-canvas-shell{position:relative}
  #osigCanvas{height:170px!important;border:2px dashed #cbd3dd!important;border-radius:14px!important}
  #osigCanvas.is-upload-dragover{border-color:#de710c!important;background:#fffaf3!important;box-shadow:0 0 0 .2rem rgba(222,113,12,.1)}
  #osigCanvas.osig-draw-launcher{cursor:pointer}
  .osig-upload-dropzone{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;padding:1rem;border:2px dashed #cbd3dd;border-radius:14px;background:#fff;color:#5f6877;text-align:center;cursor:pointer;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}
  .osig-upload-dropzone:hover,.osig-upload-dropzone.is-dragover{border-color:#de710c;background:#fffaf3;box-shadow:0 0 0 .2rem rgba(222,113,12,.1)}
  .osig-upload-dropzone i{color:#d97a1d;font-size:1.7rem}
  .osig-upload-dropzone strong{color:#172132;font-size:1rem}
  .osig-upload-dropzone span{font-size:.9rem}
  .osig-draw-modal{position:fixed;inset:0;z-index:1090;display:none;align-items:center;justify-content:center;padding:1.25rem;background:rgba(15,23,42,.7)}
  .osig-draw-modal.is-open{display:flex}
  .osig-draw-dialog{display:flex;flex-direction:column;width:min(1200px,96vw);max-height:94vh;overflow:hidden;border-radius:20px;background:#f8fafc;box-shadow:0 30px 90px rgba(0,0,0,.35)}
  .osig-draw-header,.osig-draw-footer{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;background:#fff}
  .osig-draw-header{border-bottom:1px solid #e2e7ed}.osig-draw-footer{justify-content:flex-end;border-top:1px solid #e2e7ed}
  .osig-draw-header .btn-close{margin-left:auto}
  .osig-draw-body{min-height:0;padding:1rem 1.25rem;overflow:auto}
  #osigLargeCanvas{display:block;width:100%;height:min(58vh,520px);background:#fff;border:2px dashed #cbd3dd;border-radius:14px;touch-action:none}
  .osig-draw-controls{display:flex;align-items:center;flex-wrap:wrap;gap:.75rem;margin-top:1rem}
  .osig-draw-controls input[type="range"]{width:min(260px,40vw);accent-color:#de710c}
  .osig-preview-card{background:linear-gradient(180deg,#fff,#fffaf4)!important;border-color:#efd8bb!important;border-radius:18px!important}
  .osig-preview-stage{position:relative;width:min(420px,100%);height:128px;margin:0 auto;overflow:visible;touch-action:none}
  .osig-preview-line{position:absolute;left:50%;bottom:10px;width:320px;max-width:76%;border-top:1px solid #d7dde5;transform:translateX(-50%);z-index:1}
  #osigPreview{position:absolute;left:50%;top:50%;z-index:2;display:none;max-width:340px;max-height:104px;object-fit:contain;cursor:grab;user-select:none;touch-action:none}
  #osigPreview.is-dragging{cursor:grabbing}
  .osig-preview-help{display:none;margin-top:.4rem;color:#98a2b3;font-size:.78rem}
  .osig-preview-card.has-signature .osig-preview-help{display:block}
  .osig-placement-grid{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.75rem;align-items:end}
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
  @media(max-width:575.98px){#officialSignatureModal .modal-header{padding:.75rem 2.75rem}#officialSignatureModal .modal-body,#officialSignatureModal .modal-footer{padding:1rem}.osig-title{font-size:1.1rem}#osigCanvas{height:145px!important}}
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
        <h5 class="modal-title osig-title mb-0">Official Signature Setup</h5>
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
        </ul>
        <div id="osigAlert" class="alert alert-danger d-none"></div>
        <div class="border rounded-3 p-3 bg-light osig-workspace">
          <div class="fw-bold mb-2">Signature workspace</div>
          <div class="osig-canvas-shell">
            <canvas id="osigCanvas" class="osig-draw-launcher" width="900" height="260" aria-label="Signature preview" role="button" tabindex="0" style="display:block;width:100%;height:220px;background:#fff;border:1px dashed #adb5bd;border-radius:12px;touch-action:none;"></canvas>
            <div class="osig-upload-dropzone d-none" id="osigUploadDropzone" role="button" tabindex="0" aria-label="Upload signature image">
              <i class="fas fa-cloud-arrow-up"></i>
              <strong>Drag and drop signature image</strong>
              <span>or select this box to browse PNG, JPG, or WebP</span>
            </div>
          </div>
          <div class="mt-3" id="osigDrawTools">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <label class="small fw-semibold">Ink <input type="color" id="osigColor" value="#111827" class="form-control form-control-color d-inline-block ms-1"></label>
              <label class="small fw-semibold">Thickness <input type="range" id="osigWidth" min="2" max="12" value="5" class="align-middle ms-1"></label>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="osigClear">Clear</button>
            </div>
          </div>
          <div class="d-none mt-3" id="osigUploadTools">
            <label class="form-label fw-semibold">Choose signature image</label>
            <input type="file" class="form-control d-none" id="osigFile" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">The image will be converted to a transparent-ready PNG canvas.</div>
            <div class="osig-placement-grid mt-3">
              <label class="small fw-semibold">Size <input type="range" id="osigUploadScale" min="20" max="160" value="100"></label>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="osigResetPlacement">Reset</button>
            </div>
          </div>
        </div>
        <div class="mt-3 p-3 border rounded-3 text-center bg-white osig-preview-card" id="osigPreviewCard">
          <div class="small text-uppercase text-muted fw-semibold">Document preview</div>
          <div class="osig-preview-stage" id="osigPreviewStage"><div class="osig-preview-line"></div><img id="osigPreview" alt="Signature preview"></div>
          <div class="fw-bold mt-1"><?= htmlspecialchars(trim((string)($osigAccount['firstname'] ?? '') . ' ' . (string)($osigAccount['lastname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="small text-muted">Punong Barangay</div>
          <div class="osig-preview-help">Drag the signature to adjust placement.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary osig-save-btn px-4" id="osigSave"><i class="fas fa-check me-1"></i> Complete Setup</button>
      </div>
    </div>
  </div>
</div>

<div class="osig-draw-modal" id="osigDrawModal" role="dialog" aria-modal="true" aria-labelledby="osigDrawModalTitle">
  <div class="osig-draw-dialog">
    <div class="osig-draw-header">
      <div><div class="fw-bold fs-5" id="osigDrawModalTitle">Draw Your Signature</div><div class="small text-muted">Use your mouse, stylus, or finger.</div></div>
      <button type="button" class="btn-close" id="osigDrawClose" aria-label="Close"></button>
    </div>
    <div class="osig-draw-body">
      <canvas id="osigLargeCanvas" width="1400" height="600"></canvas>
      <div class="osig-draw-controls">
        <label class="small fw-semibold">Ink <input type="color" id="osigLargeColor" value="#111827" class="form-control form-control-color d-inline-block ms-1"></label>
        <label class="small fw-semibold">Thickness <input type="range" id="osigLargeWidth" min="2" max="24" value="8" class="align-middle ms-1"></label>
        <button type="button" class="btn btn-outline-secondary" id="osigUndo"><i class="fas fa-rotate-left me-1"></i>Undo</button>
        <button type="button" class="btn btn-outline-secondary" id="osigRedo"><i class="fas fa-rotate-right me-1"></i>Redo</button>
        <button type="button" class="btn btn-outline-danger ms-auto" id="osigLargeClear"><i class="fas fa-eraser me-1"></i>Clear</button>
      </div>
    </div>
    <div class="osig-draw-footer">
      <button type="button" class="btn btn-outline-secondary me-auto" id="osigDrawReturn"><i class="fas fa-arrow-left me-1"></i>Return</button>
      <button type="button" class="btn btn-primary osig-save-btn px-4" id="osigDrawSave"><i class="fas fa-check me-1"></i>Save Signature</button>
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
  const previewStage = document.getElementById('osigPreviewStage');
  const previewCard = document.getElementById('osigPreviewCard');
  const alertEl = document.getElementById('osigAlert');
  const uploadScale = document.getElementById('osigUploadScale');
  const uploadDropzone = document.getElementById('osigUploadDropzone');
  const uploadFile = document.getElementById('osigFile');
  const drawModal = document.getElementById('osigDrawModal');
  const largeCanvas = document.getElementById('osigLargeCanvas');
  const largeCtx = largeCanvas?.getContext('2d');
  const largeColor = document.getElementById('osigLargeColor');
  const largeWidth = document.getElementById('osigLargeWidth');
  const undoButton = document.getElementById('osigUndo');
  const redoButton = document.getElementById('osigRedo');
  let mode = 'draw', drawing = false, hasInk = false;
  let uploadImage = null, uploadObjectUrl = '';
  let previewOffsetX = 0, previewOffsetY = 0, previewDrag = null;
  let largeDrawing = false, largeHasInk = false, largeHistory = [], largeRedo = [];
  const setupModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const applyPreviewPlacement = () => {
    preview.style.transform = `translate(calc(-50% + ${previewOffsetX}px), calc(-50% + ${previewOffsetY}px))`;
  };
  const clear = () => { ctx.clearRect(0, 0, canvas.width, canvas.height); hasInk = false; preview.style.display = 'none'; previewCard?.classList.remove('has-signature'); previewOffsetX = 0; previewOffsetY = 0; applyPreviewPlacement(); };
  const resetUploadPlacement = () => { if(uploadScale)uploadScale.value='100'; };
  const renderUploadImage = () => {
    if (!uploadImage) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const baseScale = Math.min(canvas.width / uploadImage.width, canvas.height / uploadImage.height);
    const scale = baseScale * (Number(uploadScale?.value || 100) / 100);
    const w = uploadImage.width * scale;
    const h = uploadImage.height * scale;
    const x = (canvas.width - w) / 2;
    const y = (canvas.height - h) / 2;
    ctx.drawImage(uploadImage, x, y, w, h);
    hasInk = true;
    uploadDropzone?.classList.add('d-none');
    refresh();
  };
  const loadUploadFile = file => {
    if(!file)return;
    if(!file.type || !['image/png','image/jpeg','image/webp'].includes(file.type)){
      alertEl.textContent='Please upload a PNG, JPG, or WebP signature image.';
      alertEl.classList.remove('d-none');
      return;
    }
    alertEl.classList.add('d-none');
    const img=new Image();
    if(uploadObjectUrl) URL.revokeObjectURL(uploadObjectUrl);
    uploadObjectUrl=URL.createObjectURL(file);
    img.onload=()=>{ uploadImage=img; resetUploadPlacement(); renderUploadImage(); };
    img.src=uploadObjectUrl;
  };
  const point = (e) => { const r=canvas.getBoundingClientRect(); const p=e.touches?.[0]||e; return {x:(p.clientX-r.left)*canvas.width/r.width,y:(p.clientY-r.top)*canvas.height/r.height}; };
  const start = e => { if(mode!=='draw')return; e.preventDefault(); drawing=true; const p=point(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); };
  const move = e => { if(!drawing)return; e.preventDefault(); const p=point(e); ctx.lineWidth=Number(document.getElementById('osigWidth').value); ctx.strokeStyle=document.getElementById('osigColor').value; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.lineTo(p.x,p.y); ctx.stroke(); hasInk=true; refresh(); };
  const stop = () => { drawing=false; ctx.closePath(); };
  const refresh = () => { if(!hasInk)return; preview.src=canvas.toDataURL('image/png'); preview.style.display='block'; previewCard?.classList.add('has-signature'); requestAnimationFrame(applyPreviewPlacement); };
  const largePoint = e => { const r=largeCanvas.getBoundingClientRect(); return {x:(e.clientX-r.left)*largeCanvas.width/r.width,y:(e.clientY-r.top)*largeCanvas.height/r.height}; };
  const updateHistoryButtons = () => { undoButton.disabled=!largeHistory.length; redoButton.disabled=!largeRedo.length; };
  const largeSnapshot = () => ({data:largeCanvas.toDataURL('image/png'),hasInk:largeHasInk});
  const restoreLargeSnapshot = snapshot => new Promise(resolve => {
    largeCtx.clearRect(0,0,largeCanvas.width,largeCanvas.height);
    largeHasInk=Boolean(snapshot?.hasInk);
    if(!snapshot?.data){resolve();return;}
    const image=new Image();
    image.onload=()=>{largeCtx.clearRect(0,0,largeCanvas.width,largeCanvas.height);largeCtx.drawImage(image,0,0);resolve();};
    image.src=snapshot.data;
  });
  const openDrawModal = () => {
    if(mode!=='draw'||!drawModal)return;
    largeCtx.clearRect(0,0,largeCanvas.width,largeCanvas.height);
    if(hasInk) largeCtx.drawImage(canvas,0,0,largeCanvas.width,largeCanvas.height);
    largeHasInk=hasInk; largeHistory=[]; largeRedo=[]; updateHistoryButtons();
    largeColor.value=document.getElementById('osigColor').value;
    largeWidth.value=String(Math.max(2,Number(document.getElementById('osigWidth').value)*1.6));
    const showDrawPad = () => { drawModal.classList.add('is-open'); document.body.style.overflow='hidden'; };
    if(modalEl.classList.contains('show')){
      modalEl.addEventListener('hidden.bs.modal',showDrawPad,{once:true});
      setupModal.hide();
    }else{
      showDrawPad();
    }
  };
  const returnToSetupModal = () => {
    drawModal?.classList.remove('is-open');
    document.body.style.overflow='';
    setupModal.show();
  };
  const saveLargeSignature = () => {
    ctx.clearRect(0,0,canvas.width,canvas.height);
    if(largeHasInk)ctx.drawImage(largeCanvas,0,0,canvas.width,canvas.height);
    hasInk=largeHasInk;
    document.getElementById('osigColor').value=largeColor.value;
    document.getElementById('osigWidth').value=String(Math.min(12,Math.max(2,Math.round(Number(largeWidth.value)/1.6))));
    if(hasInk)refresh();else clear();
    returnToSetupModal();
  };
  largeCanvas?.addEventListener('pointerdown',e=>{e.preventDefault();largeCanvas.setPointerCapture(e.pointerId);largeHistory.push(largeSnapshot());largeRedo=[];updateHistoryButtons();largeDrawing=true;const p=largePoint(e);largeCtx.beginPath();largeCtx.moveTo(p.x,p.y);});
  largeCanvas?.addEventListener('pointermove',e=>{if(!largeDrawing)return;e.preventDefault();const p=largePoint(e);largeCtx.lineWidth=Number(largeWidth.value);largeCtx.strokeStyle=largeColor.value;largeCtx.lineCap='round';largeCtx.lineJoin='round';largeCtx.lineTo(p.x,p.y);largeCtx.stroke();largeHasInk=true;});
  const stopLargeDrawing = () => {if(!largeDrawing)return;largeDrawing=false;largeCtx.closePath();};
  largeCanvas?.addEventListener('pointerup',stopLargeDrawing);largeCanvas?.addEventListener('pointercancel',stopLargeDrawing);
  undoButton?.addEventListener('click',async()=>{if(!largeHistory.length)return;largeRedo.push(largeSnapshot());await restoreLargeSnapshot(largeHistory.pop());updateHistoryButtons();});
  redoButton?.addEventListener('click',async()=>{if(!largeRedo.length)return;largeHistory.push(largeSnapshot());await restoreLargeSnapshot(largeRedo.pop());updateHistoryButtons();});
  document.getElementById('osigLargeClear')?.addEventListener('click',()=>{if(!largeHasInk)return;largeHistory.push(largeSnapshot());largeRedo=[];largeCtx.clearRect(0,0,largeCanvas.width,largeCanvas.height);largeHasInk=false;updateHistoryButtons();});
  document.getElementById('osigDrawSave')?.addEventListener('click',saveLargeSignature);
  document.getElementById('osigDrawClose')?.addEventListener('click',returnToSetupModal);document.getElementById('osigDrawReturn')?.addEventListener('click',returnToSetupModal);
  drawModal?.addEventListener('pointerdown',e=>{if(e.target===drawModal)returnToSetupModal();});
  document.addEventListener('keydown',e=>{if(!drawModal?.classList.contains('is-open'))return;if(e.key==='Escape')returnToSetupModal();if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'){e.preventDefault();(e.shiftKey?redoButton:undoButton)?.click();}});
  const adjustedSignatureData = () => {
    if (!hasInk || !previewStage) return canvas.toDataURL('image/png');
    const stageRect = previewStage.getBoundingClientRect();
    const stageScaleX = canvas.width / Math.max(stageRect.width, 1);
    const stageScaleY = canvas.height / Math.max(stageRect.height, 1);
    const output = document.createElement('canvas');
    output.width = canvas.width;
    output.height = canvas.height;
    const outputCtx = output.getContext('2d');
    outputCtx.clearRect(0, 0, output.width, output.height);
    outputCtx.drawImage(canvas, previewOffsetX * stageScaleX, previewOffsetY * stageScaleY);
    return output.toDataURL('image/png');
  };
  canvas.addEventListener('click',openDrawModal);
  canvas.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();openDrawModal();}});
  document.getElementById('osigClear').addEventListener('click', clear);
  document.querySelectorAll('[data-osig-mode]').forEach(btn=>btn.addEventListener('click',()=>{ const nextMode=btn.dataset.osigMode;if(mode===nextMode)return;mode=nextMode; document.querySelectorAll('[data-osig-mode]').forEach(b=>b.classList.toggle('active',b===btn)); document.getElementById('osigDrawTools').classList.toggle('d-none',mode!=='draw'); document.getElementById('osigUploadTools').classList.toggle('d-none',mode!=='upload'); if(mode!=='upload') uploadImage=null; clear(); uploadDropzone?.classList.toggle('d-none',mode!=='upload'||Boolean(uploadImage)); if(mode==='draw')requestAnimationFrame(openDrawModal); }));
  uploadFile?.addEventListener('change', e=>loadUploadFile(e.target.files?.[0]));
  const uploadTargets = [uploadDropzone, canvas].filter(Boolean);
  uploadTargets.forEach(target=>{
    target.addEventListener('click',()=>{if(mode==='upload')uploadFile?.click();});
    target.addEventListener('keydown',e=>{if(mode==='upload'&&(e.key==='Enter'||e.key===' ')){e.preventDefault();uploadFile?.click();}});
    ['dragenter','dragover'].forEach(type=>target.addEventListener(type,e=>{if(mode!=='upload')return;e.preventDefault();uploadDropzone?.classList.add('is-dragover');canvas.classList.add('is-upload-dragover');}));
    ['dragleave','drop'].forEach(type=>target.addEventListener(type,e=>{if(mode!=='upload')return;e.preventDefault();uploadDropzone?.classList.remove('is-dragover');canvas.classList.remove('is-upload-dragover');}));
    target.addEventListener('drop',e=>{if(mode==='upload')loadUploadFile(e.dataTransfer?.files?.[0]);});
  });
  uploadScale?.addEventListener('input',renderUploadImage);
  document.getElementById('osigResetPlacement')?.addEventListener('click',()=>{ resetUploadPlacement(); previewOffsetX=0; previewOffsetY=0; applyPreviewPlacement(); renderUploadImage(); });
  preview.addEventListener('pointerdown',e=>{ if(!hasInk)return; e.preventDefault(); preview.setPointerCapture(e.pointerId); preview.classList.add('is-dragging'); previewDrag={x:e.clientX,y:e.clientY,startX:previewOffsetX,startY:previewOffsetY}; });
  preview.addEventListener('pointermove',e=>{ if(!previewDrag)return; previewOffsetX=previewDrag.startX+(e.clientX-previewDrag.x); previewOffsetY=previewDrag.startY+(e.clientY-previewDrag.y); applyPreviewPlacement(); });
  preview.addEventListener('pointerup',()=>{ previewDrag=null; preview.classList.remove('is-dragging'); });
  preview.addEventListener('pointercancel',()=>{ previewDrag=null; preview.classList.remove('is-dragging'); });
  document.getElementById('osigSave').addEventListener('click',async()=>{ if(!hasInk){alertEl.textContent='Create or upload a signature first.';alertEl.classList.remove('d-none');return;} const btn=document.getElementById('osigSave');btn.disabled=true; const body=new FormData();body.append('action','save');body.append('creation_method',mode);body.append('signature_data',adjustedSignatureData()); try{const res=await fetch('../PhpFiles/Admin-End/officialSignature.php',{method:'POST',body});const data=await res.json();if(!res.ok||!data.success)throw new Error(data.message||'Unable to save signature.');location.reload();}catch(e){alertEl.textContent=e.message;alertEl.classList.remove('d-none');btn.disabled=false;} });
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
