<?php
// PhpFiles/General/mailConfigurations.php
// ✅ Pure config only. No PHPMailer usage here.

return [
  'host' => 'smtp.hostinger.com',
  'username' => 'official@barangaysanjose-montalban.com',
  'password' => 'SanJose.Brgy@2025!',

  'port' => 465,

  // ✅ use strings, not PHPMailer constants
  // 'ssl' for port 465, 'tls' for port 587
  'secure' => 'ssl',

  'smtp_auth' => true,

  'from_email' => 'official@barangaysanjose-montalban.com',
  'from_name'  => 'Barangay San Jose',

  // optional per-type sender
  'senders' => [
    'verify' => [
      'from_email' => 'verify@barangaysanjose-montalban.com',
      'from_name'  => 'Barangay San Jose Verification',
    ],
    'one_time' => [
      'from_email' => 'access@barangaysanjose-montalban.com',
      'from_name'  => 'Barangay San Jose Access',
    ],
    'announcement' => [
      'from_email' => 'announcements@barangaysanjose-montalban.com',
      'from_name'  => 'Barangay San Jose Announcements',
    ],
    'transaction' => [
      'from_email' => 'no-reply@barangaysanjose-montalban.com',
      'from_name'  => 'Barangay San Jose Notifications',
    ],
  ],
];

// ---- SMTP CONFIG (fill these with real values) ----
        // SMTP configuration
        //$this->mail->isSMTP();
        //$this->mail->Host       = 'smtp.hostinger.com';
        //$this->mail->SMTPAuth   = true;
        //$this->mail->Username   = 'official@barangaysanjose-montalban.com';
        //$this->mail->Password   = 'BrgySanJose.Verify@2025';
        //$this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        //$this->mail->Port       = 465;
        //$this->mail->setFrom('otp_verify@barangaysanjose-montalban.com', 'Barangay San Jose');
        //$this->mail->isHTML(true);
