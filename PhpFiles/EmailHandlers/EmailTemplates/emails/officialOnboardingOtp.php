<?php
// expects: $otp, optional: $expiresNote
$otpSafe = htmlspecialchars((string)($otp ?? ''), ENT_QUOTES, 'UTF-8');
?>

<p style="margin:0 0 15px 0; font-size:16px; font-weight:bold;">
  Official Account Onboarding OTP
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<p style="font-size:14px; line-height:1.6; margin:0 0 14px 0;">
  Enter this One-Time Password (OTP) in the onboarding page to verify your email address:
</p>

<div style="text-align:center; margin:22px 0 8px;">
  <div
    style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold; font-size:22px; letter-spacing:2px;">
    <?= $otpSafe ?>
  </div>
</div>

<?php if (!empty($expiresNote)): ?>
  <p style="font-size:12px; color:#666; margin:12px 0 0 0; text-align:center;">
    <?= htmlspecialchars((string)$expiresNote, ENT_QUOTES, 'UTF-8') ?>
  </p>
<?php endif; ?>
