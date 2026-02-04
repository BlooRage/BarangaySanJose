<?php
// expects: $headline, $actionUrl, $buttonText, optional: $note
?>
<p style="margin:0 0 15px 0; font-size:16px; font-weight:bold;">
  <?= htmlspecialchars($headline ?? 'One-Time Access', ENT_QUOTES, 'UTF-8') ?>
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<p style="font-size:14px; line-height:1.6; margin:0 0 18px 0;">
  Use the button below to access your account using this one-time link.
</p>

<?php if (!empty($note)): ?>
  <p style="font-size:12px; color:#666; margin:0 0 18px 0;">
    <?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?>
  </p>
<?php endif; ?>

<div style="text-align:center; margin:22px 0 10px;">
  <a href="<?= htmlspecialchars($actionUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>"
     style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold;">
    <?= htmlspecialchars($buttonText ?? 'ACCESS ACCOUNT', ENT_QUOTES, 'UTF-8') ?>
  </a>
</div>

<p style="font-size:12px; color:#666; line-height:1.5; word-break:break-all; margin:18px 0 0;">
  If the button doesn’t work, copy and paste this link:<br>
  <?= htmlspecialchars($actionUrl ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>
