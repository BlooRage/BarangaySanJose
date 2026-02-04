<?php
require_once '../General/sendSMS.php';

header('Content-Type: application/json');

// Validate POST parameters
if (!isset($_POST['recipient']) || !isset($_POST['otp_code'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$recipient = $_POST['recipient'];
$otp_code  = $_POST['otp_code'];
$message   = "Your OTP code is $otp_code";


try {
    // Send OTP using sendSMS function
    $sent = sendSMS($recipient, $message, $otp_code);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send SMS. Check API key, sender, and recipient number.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
