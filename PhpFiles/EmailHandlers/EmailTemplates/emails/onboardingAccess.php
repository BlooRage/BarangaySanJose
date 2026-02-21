<?php
// expects: $headline, $recipientName, $roleName, $actionUrl, $buttonText, optional: $expiresNote
?>
<p style="margin:0 0 15px 0; font-size:16px; font-weight:bold;">
  <?= htmlspecialchars($headline ?? 'Official Account Onboarding Access', ENT_QUOTES, 'UTF-8') ?>
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<p style="font-size:14px; line-height:1.6; margin:0 0 10px 0;">
  Hello <?= htmlspecialchars($recipientName ?? 'Official', ENT_QUOTES, 'UTF-8') ?>,
</p>
<p style="font-size:14px; line-height:1.6; margin:0 0 10px 0;">
  You were invited to onboard as <strong><?= htmlspecialchars($roleName ?? 'Official', ENT_QUOTES, 'UTF-8') ?></strong> in Barangay San Jose.
</p>
<p style="font-size:14px; line-height:1.6; margin:0 0 18px 0;">
  <strong>STRICTLY ONE-TIME ACCESS:</strong> This onboarding link can be used once only.
</p>

<?php if (!empty($expiresNote)): ?>
  <p style="font-size:12px; color:#666; margin:0 0 18px 0;">
    <?= htmlspecialchars($expiresNote, ENT_QUOTES, 'UTF-8') ?>
  </p>
<?php endif; ?>

<div style="text-align:center; margin:22px 0 10px;">
  <a href="<?= htmlspecialchars($actionUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>"
     style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold;">
    <?= htmlspecialchars($buttonText ?? 'START ONBOARDING', ENT_QUOTES, 'UTF-8') ?>
  </a>
</div>

<p style="font-size:12px; color:#666; line-height:1.5; word-break:break-all; margin:18px 0 0;">
  If the button does not work, copy and paste this link:<br>
  <?= htmlspecialchars($actionUrl ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>

<p style="font-size:12px; color:#666; line-height:1.6; margin:16px 0 0;">
  Disclaimer: This is an automated message from Barangay San Jose. Please do not reply to this email.
</p>
