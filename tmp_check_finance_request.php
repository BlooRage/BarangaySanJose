<?php
require __DIR__ . '/PhpFiles/General/connection.php';

$requestId = 'DR26070004';
$stmt = $conn->prepare(
    "SELECT
        d.request_id,
        d.stage,
        d.document_type,
        d.resident_name,
        d.amount,
        d.fee_amount,
        d.status_reason,
        f.payment_status_id,
        f.payment_status_name,
        f.payment_status_label,
        f.payment_method,
        f.payment_reference,
        f.payment_submitted_at,
        f.payment_proof_path,
        f.or_number
     FROM documentrequesttbl d
     LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
     WHERE d.request_id = ?
     LIMIT 1"
);

if (!$stmt) {
    fwrite(STDERR, $conn->error . PHP_EOL);
    exit(1);
}

$stmt->bind_param('s', $requestId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
