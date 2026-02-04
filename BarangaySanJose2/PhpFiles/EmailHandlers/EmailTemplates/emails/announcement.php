<?php
// expects: $title, $message, optional: $ctaText, $ctaUrl
?>
<p style="margin:0 0 10px 0; font-size:16px; font-weight:bold;">
  <?= htmlspecialchars($title ?? 'Announcement', ENT_QUOTES, 'UTF-8') ?>
</p>

<hr style="border:0;border-top:1px solid #eee;margin:15px 0;">

<p style="font-size:14px; line-height:1.7; margin:0;">
  <?= nl2br(htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8')) ?>
</p>

<?php if (!empty($ctaUrl) && !empty($ctaText)): ?>
  <div style="text-align:center; margin:22px 0 0;">
    <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>"
       style="display:inline-block; background:#ff9f43; color:#fff; text-decoration:none; padding:12px 22px; border-radius:6px; font-weight:bold;">
      <?= htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') ?>
    </a>
  </div>
<?php endif; ?>
