<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../composer-email-handler/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // SMTP
$mail->isSMTP();
$mail->Host       = 'smtp.hostinger.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'official@barangaysanjose-montalban.com';
$mail->Password   = 'MAILBOX_PASSWORD';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
$mail->Port       = 465;

$mail->SMTPDebug  = 2;
$mail->Debugoutput = 'html';


    // Email
    $mail->setFrom('official@barangaysanjose-montalban.com', 'Barangay San Jose');
    $mail->addAddress('mendoza.jerome@ue.edu.ph', 'Rome');
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'SMTP is working.';

    $mail->send();
    echo '✅ Email sent';
} catch (Exception $e) {
    echo '❌ SMTP Error: ' . $mail->ErrorInfo;
}
