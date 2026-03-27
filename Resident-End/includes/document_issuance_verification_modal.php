<?php
$documentIssuanceVerifyModalId = trim((string)($documentIssuanceVerifyModalId ?? 'residentVerificationRequiredModal'));
if ($documentIssuanceVerifyModalId === '') {
    $documentIssuanceVerifyModalId = 'residentVerificationRequiredModal';
}

$documentIssuanceVerifyModalLabelId = $documentIssuanceVerifyModalId . 'Label';
$documentIssuanceVerifyHref = trim((string)($documentIssuanceVerifyHref ?? appUrl('Resident-End/DocumentUpload.php')));
if ($documentIssuanceVerifyHref === '') {
    $documentIssuanceVerifyHref = appUrl('Resident-End/DocumentUpload.php');
}

$documentIssuanceVerifyTitle = trim((string)($documentIssuanceVerifyTitle ?? 'Account Verification Required'));
if ($documentIssuanceVerifyTitle === '') {
    $documentIssuanceVerifyTitle = 'Account Verification Required';
}

$documentIssuanceVerifyMessage = trim((string)($documentIssuanceVerifyMessage ?? 'Verify your account first to access this service. Alternatively, you can walk in at the barangay to request this document.'));
if ($documentIssuanceVerifyMessage === '') {
    $documentIssuanceVerifyMessage = 'Verify your account first to access this service. Alternatively, you can walk in at the barangay to request this document.';
}
?>
<div class="modal fade" id="<?= htmlspecialchars($documentIssuanceVerifyModalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($documentIssuanceVerifyModalLabelId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="<?= htmlspecialchars($documentIssuanceVerifyModalLabelId, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($documentIssuanceVerifyTitle, ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2 text-center">
                <p class="mb-0"><?= htmlspecialchars($documentIssuanceVerifyMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <a href="<?= htmlspecialchars($documentIssuanceVerifyHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary px-4">
                    Verify Now
                </a>
            </div>
        </div>
    </div>
</div>
