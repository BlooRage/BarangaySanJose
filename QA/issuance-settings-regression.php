<?php
declare(strict_types=1);

require_once __DIR__ . '/../PhpFiles/General/documentModuleSettings.php';

$cases = [
    'Certificate of Indigency' => 'indigency',
    'Certificate of Residency' => 'residency',
    'Certificate of Good Moral' => 'good_moral',
    'Certificate of Cohabitation' => 'cohabitation',
    'Certificate for Jail Visitation' => 'jail_visitation',
    'First Time Job Seeker Certificate' => 'first_time_job_seeker',
];
foreach ($cases as $input => $expected) {
    if (dms_issuance_certificate_key($input) !== $expected) {
        throw new RuntimeException("Certificate mapping failed for {$input}");
    }
}

$clearanceCases = [
    'General Barangay Clearance' => 'general',
    'Barangay Clearance for Business Permit' => 'business_permit',
    'Barangay Clearance for Tricycle Permit' => 'tricycle_permit',
    'Barangay Clearance for Electrical Permit' => 'electrical_permit',
    'Barangay Clearance for Water Permit' => 'water_permit',
    'Barangay Clearance for Residential Permit' => 'residential_permit',
    'Barangay Clearance for Commercial Permit' => 'commercial_permit',
];
foreach ($clearanceCases as $input => $expected) {
    if (dms_clearance_type_key($input) !== $expected) {
        throw new RuntimeException("Clearance mapping failed for {$input}");
    }
}

if (!dms_purpose_is_allowed('Employment', ['Employment', 'Other'])) {
    throw new RuntimeException('Exact configured purpose was rejected.');
}
if (!dms_purpose_is_allowed('Custom resident purpose', ['Employment', 'Other'])) {
    throw new RuntimeException('Custom purpose was rejected while Other is configured.');
}
if (dms_purpose_is_allowed('Unconfigured', ['Employment'])) {
    throw new RuntimeException('Unconfigured purpose was accepted without Other.');
}

$filtered = dms_filter_essential_resident_profile([
    'resident_id' => 'R-1', 'full_name' => 'Resident', 'phone_number' => '0917',
    'email' => 'private@example.test', 'account_password' => 'secret',
]);
if (isset($filtered['email'], $filtered['account_password']) || ($filtered['resident_id'] ?? '') !== 'R-1') {
    throw new RuntimeException('Essential-details profile filtering failed.');
}

echo "Issuance settings regression checks passed.\n";
