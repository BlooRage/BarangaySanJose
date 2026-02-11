<?php
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  
  <link rel="icon" href="/Images/San_Jose_LOGO.jpg">
<meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($subject ?? 'Barangay San Jose', ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f6f6f6;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:20px 0;">
    <tr>
      <td align="center">

        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #eeeeee;">
          <?php include __DIR__ . '/partials/header.php'; ?>

          <tr>
            <td style="padding:40px;color:#000000;">
              <?php include $__content_template; ?>
            </td>
          </tr>

          <?php include __DIR__ . '/partials/footer.php'; ?>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
