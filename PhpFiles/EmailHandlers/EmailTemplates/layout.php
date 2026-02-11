<?php
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  
  <link rel="icon" href="/Images/favicon_sanjose.png">
<meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light">
  <title><?= htmlspecialchars($subject ?? 'Barangay San Jose', ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#ffffff !important;">
  <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="background:#ffffff !important;padding:20px 0;">
    <tr>
      <td align="center">

        <table width="600" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="background:#ffffff !important;border:1px solid #eeeeee;">
          <?php include __DIR__ . '/partials/header.php'; ?>

          <tr>
            <td bgcolor="#ffffff" style="padding:40px;color:#000000;background-image:url('https://BarangaySanJose-Montalban.com/Images/SanJose_Email_Watermark.png');background-repeat:no-repeat;background-position:center;background-size:45% auto;background-color:#ffffff !important;">
              <?php include $__content_template; ?>
              <div style="margin-top:24px;font-size:11px;color:#555;line-height:1.5;">
                If you did not request this email, you can ignore it.
              </div>
              <div style="margin-top:6px;font-size:11px;color:#555;line-height:1.5;">
                This is a system-generated email. Please do not reply to this message.
              </div>
            </td>
          </tr>

          <?php include __DIR__ . '/partials/footer.php'; ?>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
