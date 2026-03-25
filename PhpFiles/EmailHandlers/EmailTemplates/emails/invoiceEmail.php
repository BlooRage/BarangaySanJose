<?php
// Invoice / Official Receipt email template.
// Expected variables (passed via $data array in EmailSender):
// $or_number, $resident_name, $document_type, $purpose, $amount,
// $payment_method, $payment_date, $certificate_number, $request_id

$orNumberValue      = trim((string)($or_number      ?? ''));
$residentNameValue  = trim((string)($resident_name  ?? ''));
$documentTypeValue  = trim((string)($document_type  ?? 'Document Request'));
$purposeValue       = trim((string)($purpose        ?? ''));
$amountValue        = trim((string)($amount         ?? ''));
$paymentMethodValue = trim((string)($payment_method ?? ''));
$paymentDateValue   = trim((string)($payment_date   ?? ''));
$certNumberValue    = trim((string)($certificate_number ?? ''));
$requestIdValue     = trim((string)($request_id     ?? ''));

if ($orNumberValue     === '') $orNumberValue     = '-';
if ($documentTypeValue === '') $documentTypeValue = 'Document Request';
if ($paymentDateValue  === '') $paymentDateValue  = date('F j, Y');
?>

<p style="margin:0 0 6px 0; font-size:16px; font-weight:bold; color:#de710c;">
    Official Receipt
</p>
<p style="margin:0 0 14px 0; font-size:13px; color:#555;">
    Your payment has been verified. Please find your official receipt details below.
    <?php if (true): ?>
        <br>A copy of this receipt is also attached to this email as a PDF.
    <?php endif; ?>
</p>

<hr style="border:0; border-top:2px solid #de710c; margin:0 0 16px 0;">

<table width="100%" cellpadding="0" cellspacing="0"
       style="font-size:14px; line-height:1.7; border-collapse:collapse;">

    <tr style="background:#fff6ec;">
        <td style="padding:7px 10px; color:#7c3f00; font-weight:bold; width:160px; border-bottom:1px solid #fde4c0;">
            OR No.
        </td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #fde4c0;">
            <?= htmlspecialchars($orNumberValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>

    <?php if ($residentNameValue !== ''): ?>
    <tr>
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Received From</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($residentNameValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endif; ?>

    <tr style="background:#fafafa;">
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Document Type</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($documentTypeValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>

    <?php if ($purposeValue !== ''): ?>
    <tr>
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Purpose</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($purposeValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ($amountValue !== ''): ?>
    <tr style="background:#fafafa;">
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Amount Paid</td>
        <td style="padding:7px 10px; font-weight:bold; color:#177245; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($amountValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endif; ?>

    <tr>
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Payment Method</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($paymentMethodValue !== '' ? $paymentMethodValue : '-', ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>

    <tr style="background:#fafafa;">
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Date</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($paymentDateValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>

    <?php if ($certNumberValue !== ''): ?>
    <tr>
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Certificate No.</td>
        <td style="padding:7px 10px; font-weight:bold; color:#111; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($certNumberValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ($requestIdValue !== ''): ?>
    <tr style="background:#fafafa;">
        <td style="padding:7px 10px; color:#555; border-bottom:1px solid #f0f0f0;">Request ID</td>
        <td style="padding:7px 10px; color:#7c3f00; border-bottom:1px solid #f0f0f0;">
            <?= htmlspecialchars($requestIdValue, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endif; ?>

</table>

<div style="margin-top:18px; padding:12px 16px; background:#eefaf2; border-left:4px solid #22c55e;
            border-radius:4px; font-size:13px; color:#177245;">
    <strong>Payment Verified.</strong>
    Your document request is now being processed for release. You will be notified once it is ready.
</div>
