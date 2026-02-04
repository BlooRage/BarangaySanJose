<?php
// expects: $transactionId, $status, $amount, optional: $details (array), $ctaText, $ctaUrl
?>
<p style="margin:0 0 10px 0; font-size:16px; font-weight:bold;">
  Transaction Notification
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; line-height:1.6;">
  <tr>
    <td style="padding:6px 0; color:#666; width:160px;">Transaction ID:</td>
    <td style="padding:6px 0; font-weight:bold;"><?= htmlspecialchars($transactionId ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
  </tr>
  <tr>
    <td style="padding:6px 0; color:#666;">Status:</td>
    <td style="padding:6px 0; font-weight:bold;"><?= htmlspecialchars($status ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
  </tr>
  <tr>
    <td style="padding:6px 0; color:#666;">Amount:</td>
    <td style="padding:6px 0; font-weight:bold;"><?= htmlspecialchars($amount ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
  </tr>
</table>

<?php if (!empty($details) && is_array($details)): ?>
  <div style="margin-top:16px; font-size:12px; color:#666;">
    <b>Details:</b>
    <ul style="margin:8px 0 0 18px; padding:0;">
      <?php foreach ($details as $k => $v): ?>
        <li><b><?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>:</b> <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($ctaUrl) && !empty($ctaText)): ?>
  <div style="text-align:center; margin:22px 0 0;">
    <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>"
       style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold;">
      <?= htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') ?>
    </a>
  </div>
<?php endif; ?>
