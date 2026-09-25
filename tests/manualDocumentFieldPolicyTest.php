<?php
// Run with: php tests/manualDocumentFieldPolicyTest.php (no database required).
declare(strict_types=1);
require_once __DIR__ . '/../PhpFiles/General/manualDocumentFieldPolicy.php';

function expectSame($expected, $actual, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$cases = [
    ['Barangay ID', [], ['birthdate', 'birthplace', 'sex', 'contact_number']],
    ['Certificate of Good Moral', [], []],
    ['CertificateOfIndigency', [], []],
    ['Certificate of Residency', [], ['birthdate', 'birthplace']],
    ['General Certificate - Other', [], ['birthdate', 'birthplace']],
    ['Certificate of Identity', [], ['birthdate', 'birthplace', 'sex']],
    ['Certificate of Cohabitation', [], ['birthdate']],
    ['Certificate of Cohabitation', ['cohabitation_variant' => 'relationship_jail_visit'], []],
    ['Certificate of Cohabitation', ['cohabitation_variant' => 'conjugal_visit'], []],
    ['First Time Job Seeker Certificate', [], ['sex']],
];
foreach (['Electrical', 'Water', 'Residential', 'Residential Building', 'Commercial', 'Commercial Building', 'Business', 'Tricycle'] as $type) {
    $cases[] = ['Barangay Clearance for ' . $type . ' Permit', [], []];
}
foreach ($cases as [$type, $variant, $expected]) {
    expectSame($expected, manual_document_personal_fields($type, $variant), $type . ': required personal fields');
    $populated = array_fill_keys(['birthdate', 'birthplace', 'sex', 'contact_number', 'civil_status', 'occupation', 'religion'], 'profile value');
    $filtered = manual_document_filter_personal_fields($type, array_merge($populated, $variant));
    foreach ($populated as $key => $value) {
        expectSame(in_array($key, $expected, true), array_key_exists($key, $filtered), $type . ': ' . $key);
    }
}

// Regression: linking a resident used to restore unused malformed DOB aliases.
$unusedAliases = array_fill_keys(['birthdate', 'date_of_birth', 'birthDate', 'child_dob', 'age', 'birthplace', 'place_of_birth', 'child_birthplace', 'sex', 'gender', 'child_sex', 'civil_status', 'occupation', 'occupation_display', 'religion', 'contact_number', 'phone_number'], 'invalid profile value');
$essential = ['resident_id' => 'TEST', 'first_name' => 'Test', 'last_name' => 'Applicant', 'full_address' => 'Test address', 'area_number' => 'Area 01', 'request_purpose' => 'Application', 'sector_membership' => 'PWD'];
expectSame($essential, manual_document_filter_personal_fields('Certificate of Good Moral', array_merge($essential, $unusedAliases)), 'Linked resident cannot restore unused personal data');

// Monitoring must retain the permit and renewal details while omitting personal extras.
$business = $essential + ['application_type' => 'Renewal', 'business_name' => 'Test business', 'business_full_address' => 'Test business address', 'plate_number' => 'NEW001', 'previous_plate_number' => 'OLD001', 'business_approval_type' => 'no_objection'];
expectSame($business, manual_document_filter_personal_fields('Barangay Clearance for Business Permit', array_merge($business, $unusedAliases)), 'Business details retained');
$partner = ['cohabitation_variant' => 'standard', 'birthdate' => '1990-01-01', 'cohabitant_birthdate' => '1991-01-01'];
expectSame($partner, manual_document_filter_personal_fields('Certificate of Cohabitation', $partner + ['religion' => 'unused']), 'Both cohabitation birthdates retained');
$id = ['birthdate' => '1990-01-01', 'birthplace' => 'Test', 'sex' => 'Female', 'contact_number' => '09000000000', 'phone_number' => '09000000000', 'emergency_contact' => '09000000001'];
expectSame($id, manual_document_filter_personal_fields('Barangay ID', $id + ['occupation' => 'unused']), 'ID and emergency details retained');
echo 'PASS: ' . count($cases) . " document/variant policies, unused-profile aliases, and essential payload retention.\n";
