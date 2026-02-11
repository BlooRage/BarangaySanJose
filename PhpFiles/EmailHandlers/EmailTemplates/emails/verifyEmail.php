<?php
// expects: $headline, $verifyUrl, $buttonText, optional: $expiresNote
?>
<p style="margin:0 0 15px 0; font-size:16px; font-weight:bold;">
  <?= htmlspecialchars($headline ?? 'MALIGAYANG BATI KA-BARANGAY SAN JOSE!', ENT_QUOTES, 'UTF-8') ?>
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<p style="font-size:14px; line-height:1.6; margin:0 0 10px 0;">
  Please verify your email address to activate your account.
</p>
<p style="font-size:13px; line-height:1.6; margin:0 0 18px 0; color:#333;">
  This helps us confirm it’s really you and keeps your account secure.
</p>

<?php if (!empty($expiresNote)): ?>
  <p style="font-size:12px; color:#666; margin:0 0 18px 0;">
    <?= htmlspecialchars($expiresNote, ENT_QUOTES, 'UTF-8') ?>
  </p>
<?php endif; ?>

<div style="text-align:center; margin:22px 0 10px;">
  <a href="<?= htmlspecialchars($verifyUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>"
     style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold;">
    <?= htmlspecialchars($buttonText ?? 'VERIFY EMAIL', ENT_QUOTES, 'UTF-8') ?>
  </a>
</div>

<p style="font-size:12px; color:#666; line-height:1.5; word-break:break-all; margin:18px 0 0;">
  If the button doesn’t work, copy and paste this link:<br>
  <?= htmlspecialchars($verifyUrl ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>
