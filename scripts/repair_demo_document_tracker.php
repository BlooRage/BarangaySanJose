<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--execute', $argv, true)) { fwrite(STDERR, "Use --execute to apply the tracker repair.\n"); exit(2); }

require_once __DIR__ . '/../PhpFiles/General/connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$certificateTypes = [
    'Certificate of Residency',
    'Certificate of Indigency',
    'Certificate of Good Moral',
    'First Time Job Seeker Certificate',
    'Certificate of Cohabitation',
    'Certificate of Identity',
];
$clearanceTypes = [
    'Barangay Clearance for Business Permit',
    'Barangay Clearance for Tricycle Permit',
    'Barangay Clearance for Electrical Permit',
    'Barangay Clearance for Water Permit',
    'Barangay Clearance for Residential Permit',
    'Barangay Clearance for Residential Building Permit',
    'Barangay Clearance for Commercial Permit',
    'Barangay Clearance for Commercial Building Permit',
];

function repair_exec(mysqli $conn, string $sql, array $values): void {
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

$conn->begin_transaction();
try {
    for ($i = 1; $i <= 60; $i++) {
        $requestId = 'BULK-CERT-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $type = $certificateTypes[($i - 1) % count($certificateTypes)];
        repair_exec($conn, 'UPDATE documentrequesttbl SET document_type=?, request_details=payload_json WHERE request_id=?', [$type, $requestId]);
        repair_exec($conn, 'UPDATE certificatesrequesttbl SET certificate_type=? WHERE request_id=?', [$type, $requestId]);
        repair_exec($conn, 'UPDATE issuancerequesttbl SET certificate_type=? WHERE request_id=?', [$type, $requestId]);
    }

    for ($i = 1; $i <= 30; $i++) {
        $requestId = 'BULK-CLR-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $type = $clearanceTypes[($i - 1) % count($clearanceTypes)];
        repair_exec($conn, 'UPDATE documentrequesttbl SET document_type=?, request_details=payload_json WHERE request_id=?', [$type, $requestId]);
        repair_exec($conn, 'UPDATE clearancerequesttbl SET clearance_type=? WHERE request_id=?', [$type, $requestId]);
    }

    // ID rows use the same channel-decoding path.
    $conn->query("UPDATE documentrequesttbl SET request_details=payload_json WHERE request_id LIKE 'BULK-ID-%'");
    $conn->commit();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    fwrite(STDERR, 'Tracker repair failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Demo document tracker records normalized successfully.\n";
