<?php
declare(strict_types=1);

/**
 * Populate the configured testing database with coherent, idempotent demo data.
 *
 * Usage:
 *   php scripts/seed_demo_database.php --execute
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!in_array('--execute', $argv, true)) {
    fwrite(STDERR, "Dry run only. Re-run with --execute to seed the configured database.\n");
    exit(2);
}

require_once __DIR__ . '/../PhpFiles/General/connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function seed_exec(mysqli $conn, string $sql, array $params = []): void
{
    $stmt = $conn->prepare($sql);
    if ($params !== []) {
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->close();
}

function seed_one(mysqli $conn, string $sql, array $params = []): ?array
{
    $stmt = $conn->prepare($sql);
    if ($params !== []) {
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function seed_status(mysqli $conn, string $type, string $name): int
{
    $row = seed_one($conn, 'SELECT status_id FROM statuslookuptbl WHERE status_type = ? AND status_name = ? LIMIT 1', [$type, $name]);
    if (!$row) {
        throw new RuntimeException("Missing required status {$type}/{$name}");
    }
    return (int)$row['status_id'];
}

function seed_pii(?string $value): ?string
{
    return $value === null ? null : (string)pii_encrypt_string($value);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$activeAccount = seed_status($conn, 'UserAccount', 'Active');
$verifiedResident = seed_status($conn, 'Resident', 'VerifiedResident');
$residing = seed_status($conn, 'AddressResidency', 'Residing');
$docPending = seed_status($conn, 'DocumentVerification', 'PendingReview');
$docPayment = seed_status($conn, 'DocumentVerification', 'ForPayment');
$docComplete = seed_status($conn, 'DocumentVerification', 'Completed');
$paymentUnpaid = seed_status($conn, 'TransactionPayment', 'Unpaid');
$paymentVerified = seed_status($conn, 'TransactionPayment', 'Verified');
$complaintPending = seed_status($conn, 'Complaint', 'Pending');
$complaintResolved = seed_status($conn, 'Complaint', 'Resolved');
$complaintOnly = seed_status($conn, 'ComplaintLevel', 'Complaint Only');
$blotterActive = seed_status($conn, 'Blotter', 'Active');
$blotterOnly = seed_status($conn, 'BlotterLevel', 'Blotter Only');
$blotterRequestPending = seed_status($conn, 'BlotterRequest', 'Pending');
$appointmentPending = seed_status($conn, 'Appointment', 'Pending');
$householdActive = seed_status($conn, 'Household', 'Active');
$memberActive = seed_status($conn, 'HouseholdMember', 'Active');
$inviteActive = seed_status($conn, 'HouseholdInvite', 'Active');
$sectorVerified = seed_status($conn, 'SectorMembership', 'Verified');
$officialEmployment = seed_status($conn, 'Official/Personnel Management', 'Regular');
$attachmentVerified = seed_status($conn, 'DocumentVerification', 'Verified');

$superAdmin = (string)(seed_one($conn, "SELECT user_id FROM useraccountstbl WHERE role_access = 'SuperAdmin' ORDER BY user_id LIMIT 1")['user_id'] ?? '');
$official = (string)(seed_one($conn, "SELECT user_id FROM useraccountstbl WHERE role_access = 'Official' ORDER BY user_id LIMIT 1")['user_id'] ?? $superAdmin);
if ($superAdmin === '' || $official === '') {
    throw new RuntimeException('A SuperAdmin and an Official account are required before demo seeding.');
}

$people = [
    ['202608R09001', '2608900001', 'Demo', 'Dela Cruz', 'Maria', '1991-02-14', 'Female', 'Married', 'Area01', '09170009001', 'demo.maria@example.test'],
    ['202608R09002', '2608900002', 'Demo', 'Santos', 'Jose', '1987-06-21', 'Male', 'Married', 'Area02', '09170009002', 'demo.jose@example.test'],
    ['202608R09003', '2608900003', 'Demo', 'Reyes', 'Ana', '2000-11-05', 'Female', 'Single', 'Area03', '09170009003', 'demo.ana@example.test'],
    ['202608R09004', '2608900004', 'Demo', 'Garcia', 'Miguel', '1978-09-30', 'Male', 'Widowed', 'Area04', '09170009004', 'demo.miguel@example.test'],
    ['202608R09005', '2608900005', 'Demo', 'Mendoza', 'Liza', '1995-04-18', 'Female', 'Single', 'Area05', '09170009005', 'demo.liza@example.test'],
];

$conn->begin_transaction();
try {
    foreach ($people as $index => [$userId, $residentId, $middle, $last, $first, $birthdate, $sex, $civil, $area, $phone, $email]) {
        $contacts = pii_prepare_useraccount_contacts($email, $phone);
        seed_exec($conn, 'INSERT IGNORE INTO useraccountstbl
            (user_id, phone_number, phone_lookup_hash, phoneNum_verify, email, email_lookup_hash, email_verify, password_hash, status_id_account, role_access, account_created, last_login, last_password_changed)
            VALUES (?, ?, ?, 1, ?, ?, 1, ?, ?, \'Resident\', ?, ?, ?)', [
                $userId, $contacts['phone_number'], $contacts['phone_lookup_hash'], $contacts['email'], $contacts['email_lookup_hash'],
                password_hash('Demo@12345', PASSWORD_DEFAULT), (string)$activeAccount,
                $now->modify('-' . (20 - $index) . ' days')->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $now->format('Y-m-d'),
            ]);

        seed_exec($conn, 'INSERT IGNORE INTO residentinformationtbl
            (resident_id, user_id, lastname, firstname, middlename, sex, birthdate, birthplace, baranagayresidency, civil_status, family_role, head_of_family, voter_status, occupation, occupation_detail, religion, sector_membership, privacy_consent, status_id_resident)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)', [
                $residentId, $userId, seed_pii($last), seed_pii($first), seed_pii($middle), $sex, seed_pii($birthdate),
                seed_pii('Rodriguez, Rizal'), seed_pii('More than 5 years'), seed_pii($civil), seed_pii($index < 2 ? ($index === 0 ? 'Mother' : 'Father') : 'Child'),
                $index < 2 ? 1 : 0, 1, 1, seed_pii(['Teacher', 'Driver', 'Student', 'Vendor', 'Nurse'][$index]), seed_pii('Roman Catholic'),
                seed_pii($index === 3 ? 'Senior Citizen' : ($index === 2 ? 'Youth' : 'General Population')), $verifiedResident,
            ]);

        seed_exec($conn, 'INSERT IGNORE INTO residentaddresstbl
            (address_id, resident_id, unit_number, street_number, street_name, phase_number, subdivision, area_number, house_type, house_ownership, residency_duration, status_id_residency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                'DA' . str_pad((string)($index + 1), 8, '0', STR_PAD_LEFT), $residentId, seed_pii((string)($index + 1)), seed_pii((string)(100 + $index)),
                seed_pii(['Mabini', 'Rizal', 'Bonifacio', 'Luna', 'Del Pilar'][$index] . ' Street'), seed_pii('1'), seed_pii('Demo Village'),
                $area, seed_pii('Concrete'), seed_pii($index % 2 === 0 ? 'Owned' : 'Rented'), seed_pii((string)(6 + $index) . ' years'), $residing,
            ]);

        seed_exec($conn, 'INSERT IGNORE INTO emergencycontacttbl
            (emergency_id, user_id, last_name, first_name, phone_number, relationship, address)
            VALUES (?, ?, ?, ?, ?, ?, ?)', [
                990001 + $index, $userId, seed_pii('Dela Cruz'), seed_pii('Emergency Contact ' . ($index + 1)), seed_pii('09179909' . str_pad((string)$index, 3, '0', STR_PAD_LEFT)),
                seed_pii('Relative'), seed_pii('Barangay San Jose, Rodriguez, Rizal'),
            ]);
    }

    // Attachments and sector membership.
    $docType = seed_one($conn, "SELECT document_type_id FROM documenttypelookuptbl ORDER BY document_type_id LIMIT 1");
    $documentTypeId = (int)($docType['document_type_id'] ?? 1);
    seed_exec($conn, 'INSERT IGNORE INTO unifiedfileattachmenttbl
        (attachment_id, source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks)
        VALUES (990001, \'ResidentProfile\', \'2608900001\', ?, \'demo-proof.pdf\', \'Uploads/demo/demo-proof.pdf\', \'application/pdf\', \'202608R09001\', ?, \'Seeded demonstration attachment\')', [$documentTypeId, $attachmentVerified]);
    seed_exec($conn, 'INSERT IGNORE INTO residentsectormembershiptbl
        (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)
        VALUES (\'2608900004\', \'senior_citizen\', ?, 990001, \'Verified demonstration membership\', ?, ?)', [$sectorVerified, $now->format('Y-m-d H:i:s'), $superAdmin]);

    // Household cluster and pending member verification.
    seed_exec($conn, 'INSERT IGNORE INTO householdtbl (household_id, head_resident_id, status_id) VALUES (990001, \'2608900001\', ?)', [$householdActive]);
    foreach ([['2608900001', 'Head', null], ['2608900002', 'Member', '2608900001'], ['2608900003', 'Member', '2608900001']] as [$residentId, $role, $inviter]) {
        seed_exec($conn, 'INSERT IGNORE INTO householdmemberresidenttbl (household_id, resident_id, role, status_id, invited_by_resident_id, joined_at) VALUES (990001, ?, ?, ?, ?, ?)', [$residentId, $role, $memberActive, $inviter, $now->format('Y-m-d H:i:s')]);
    }
    seed_exec($conn, 'INSERT IGNORE INTO householdinvitetbl (invite_id, household_id, code_hash, expires_at, max_uses, uses_count, created_by_resident_id, status_id) VALUES (990001, 990001, ?, ?, 5, 2, \'2608900001\', ?)', [hash('sha256', 'DEMO-HOUSEHOLD'), $now->modify('+30 days')->format('Y-m-d H:i:s'), $inviteActive]);
    seed_exec($conn, 'INSERT IGNORE INTO householdmemberinfotbl (household_member_id, fam_head_id, last_name, first_name, middle_name, birthdate) VALUES (990001, \'2608900001\', \'Dela Cruz\', \'Paolo\', \'Demo\', \'2015-03-12\')');
    seed_exec($conn, 'INSERT IGNORE INTO householdmemberverificationtbl
        (request_id, fam_head_id, submitted_by_user_id, last_name, first_name, middle_name, birthdate, status_id, status, attachment_id)
        VALUES (990001, \'2608900001\', \'202608R09001\', \'Dela Cruz\', \'Paolo\', \'Demo\', \'2015-03-12\', ?, \'PendingReview\', 990001)', [seed_status($conn, 'HouseholdMember', 'PendingReview')]);

    // Four document workflows: certificate, clearance, ID, and completed certificate.
    $requests = [
        ['DEMO-REQ-CERT-001', '202608R09001', '2608900001', 'Barangay Residency Certificate', 'Employment requirement', 'submitted', $docPending],
        ['DEMO-REQ-CLR-001', '202608R09002', '2608900002', 'Business Clearance', 'New sari-sari store permit', 'for_payment', $docPayment],
        ['DEMO-REQ-ID-001', '202608R09003', '2608900003', 'Barangay ID', 'First-time ID application', 'submitted', $docPending],
        ['DEMO-REQ-CERT-002', '202608R09004', '2608900004', 'Certificate of Indigency', 'Medical assistance', 'completed', $docComplete],
    ];
    foreach ($requests as $offset => [$requestId, $userId, $residentId, $type, $purpose, $stage, $status]) {
        seed_exec($conn, 'INSERT IGNORE INTO documentrequesttbl
            (request_id, resident_user_id, resident_name, request_details, status_id_request, user_id_official_reviewed_by, user_id_official_released_by, request_timestamp, review_timestamp, release_timestamp, document_validity, submitted_at, personnel_user_id, personnel_decision_at, ready_at, completed_at, resident_id, document_type, purpose, payload_json, stage, certificate_number, verification_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $requestId, $userId, 'Demo Applicant ' . ($offset + 1), json_encode(['seeded' => true, 'module' => $type]), $status,
                $stage === 'submitted' ? null : $official, $stage === 'completed' ? $official : null,
                $now->modify('-' . (8 - $offset) . ' days')->format('Y-m-d H:i:s'), $stage === 'submitted' ? null : $now->modify('-4 days')->format('Y-m-d H:i:s'),
                $stage === 'completed' ? $now->modify('-2 days')->format('Y-m-d H:i:s') : null, $stage === 'completed' ? $now->modify('+1 year')->format('Y-m-d H:i:s') : null,
                $now->modify('-' . (8 - $offset) . ' days')->format('Y-m-d H:i:s'), $stage === 'submitted' ? null : $official,
                $stage === 'submitted' ? null : $now->modify('-4 days')->format('Y-m-d H:i:s'), $stage === 'completed' ? $now->modify('-3 days')->format('Y-m-d H:i:s') : null,
                $stage === 'completed' ? $now->modify('-2 days')->format('Y-m-d H:i:s') : null, $residentId, $type, $purpose,
                json_encode(['seeded' => true, 'applicant' => 'Demo Applicant ' . ($offset + 1)]), $stage,
                $stage === 'completed' ? 'DEMO-CERT-2026-001' : null, $stage === 'completed' ? 'DEMO-VERIFY-001' : null,
            ]);
    }
    seed_exec($conn, 'INSERT IGNORE INTO certificatesrequesttbl (certificate_id, request_id, certificate_type, certificate_details) VALUES (\'DC99000001\', \'DEMO-REQ-CERT-001\', \'Barangay Residency Certificate\', ?)', [json_encode(['purpose' => 'Employment requirement'])]);
    seed_exec($conn, 'INSERT IGNORE INTO certificatesrequesttbl (certificate_id, request_id, certificate_type, certificate_details, certificate_number, verification_code) VALUES (\'DC99000002\', \'DEMO-REQ-CERT-002\', \'Certificate of Indigency\', ?, \'DEMO-CERT-2026-001\', \'DEMO-VERIFY-001\')', [json_encode(['purpose' => 'Medical assistance'])]);
    seed_exec($conn, 'INSERT IGNORE INTO issuancerequesttbl (certificate_id, request_id, certificate_type, certificate_details) VALUES (\'DI99000001\', \'DEMO-REQ-CERT-001\', \'Barangay Residency Certificate\', ?)', [json_encode(['seeded' => true])]);
    seed_exec($conn, 'INSERT IGNORE INTO issuancerequesttbl (certificate_id, request_id, certificate_type, certificate_details, certificate_number, verification_code) VALUES (\'DI99000002\', \'DEMO-REQ-CERT-002\', \'Certificate of Indigency\', ?, \'DEMO-CERT-2026-001\', \'DEMO-VERIFY-001\')', [json_encode(['seeded' => true])]);
    seed_exec($conn, 'INSERT IGNORE INTO barangayidrequesttbl (barangay_id, request_id, id_details) VALUES (\'DB99000001\', \'DEMO-REQ-ID-001\', ?)', [json_encode(['blood_type' => 'O+', 'emergency_contact' => '09179909000'])]);
    seed_exec($conn, 'INSERT IGNORE INTO clearancerequesttbl (request_id, clearance_type, application_type, clearance_details) VALUES (\'DEMO-REQ-CLR-001\', \'Business Clearance\', \'New\', ?)', [json_encode(['business_name' => 'Demo Sari-Sari Store', 'business_address' => 'Area02, San Jose'])]);
    $clearanceId = (int)(seed_one($conn, 'SELECT clearance_id FROM clearancerequesttbl WHERE request_id = \'DEMO-REQ-CLR-001\'')['clearance_id'] ?? 0);
    seed_exec($conn, 'INSERT IGNORE INTO clearancefeestbl (clearance_fee_id, clearance_id, fee_type, amount) VALUES (990001, ?, \'Processing Fee\', 150.00)', [$clearanceId]);
    seed_exec($conn, 'INSERT IGNORE INTO clearanceinspectiontbl (inspection_id, clearance_id, inspector_name, date_inspected, remarks) VALUES (\'DINSP0001\', ?, \'Demo Inspector\', ?, \'Premises compliant; seeded inspection record.\')', [(string)$clearanceId, $now->modify('-3 days')->format('Y-m-d H:i:s')]);

    // Finance and resident transaction ledgers.
    seed_exec($conn, 'INSERT IGNORE INTO financetransactiontbl (transaction_id, request_id, transaction_amount, applicant_lastname, applicant_firstname, payment_method, transaction_details, transaction_status_id, payment_deadline) VALUES (\'DEMO-FIN-CLR-001\', \'DEMO-REQ-CLR-001\', 150.00, \'Santos\', \'Jose\', \'Cash\', ?, ?, ?)', [json_encode(['seeded' => true]), $paymentUnpaid, $now->modify('+7 days')->format('Y-m-d H:i:s')]);
    seed_exec($conn, 'INSERT IGNORE INTO financetransactiontbl (transaction_id, request_id, transaction_amount, applicant_lastname, applicant_firstname, payment_method, transaction_details, or_number, transaction_status_id, payment_timestamp, finance_decision_at, user_id_employee_process) VALUES (\'DEMO-FIN-CERT-002\', \'DEMO-REQ-CERT-002\', 0.00, \'Garcia\', \'Miguel\', \'No Fee\', ?, \'DEMO-OR-0001\', ?, ?, ?, ?)', [json_encode(['seeded' => true]), $paymentVerified, $now->modify('-3 days')->format('Y-m-d H:i:s'), $now->modify('-3 days')->format('Y-m-d H:i:s'), $official]);
    seed_exec($conn, 'INSERT IGNORE INTO residenttransactiontbl (transaction_id, user_id, resident_user_id, source_type, source_id, transaction_type, title, details, amount, status_id) VALUES (\'DRT9900001\', \'202608R09002\', \'202608R09002\', \'DocumentRequest\', \'DEMO-REQ-CLR-001\', \'Clearance\', \'Business Clearance\', \'Awaiting payment\', 150.00, ?)', [$paymentUnpaid]);

    // Complaint and e-blotter records with participants, history, and logs.
    seed_exec($conn, 'INSERT IGNORE INTO casereportstbl (case_id, resident_user_id, report_type, incident_date, incident_time, incident_place, incident_area_number, complaint_type, case_details, case_status_id, case_level_id, user_id_official_record_by) VALUES (\'DEMOCASE0001\', \'202608R09005\', \'Complaint\', ?, \'18:30:00\', \'Demo Village basketball court\', \'Area05\', \'Noise Complaint\', \'Repeated excessive noise during quiet hours.\', ?, ?, ?)', [$now->modify('-6 days')->format('Y-m-d'), $complaintPending, $complaintOnly, $official]);
    seed_exec($conn, 'INSERT IGNORE INTO complaintstbl (complaint_id, case_id, complaint_origin, subject_kind, subject_display_name, subject_contact_number, subject_address, witness_summary, intake_notes) VALUES (\'DCMP000001\', \'DEMOCASE0001\', \'ResidentPortal\', \'Resident\', \'Demo Neighbor\', \'09170009999\', \'Area05, San Jose\', \'Two neighbors witnessed the incident.\', \'Seeded complaint for workflow testing.\')');
    seed_exec($conn, 'INSERT IGNORE INTO blotterrequeststbl (request_id, complaint_case_id, complaint_id, request_status_id, review_notes, recommended_by_user_id) VALUES (\'DBREQ0000001\', \'DEMOCASE0001\', \'DCMP000001\', ?, \'Pending blotter escalation review.\', ?)', [$blotterRequestPending, $official]);
    seed_exec($conn, 'INSERT IGNORE INTO casereportstbl (case_id, resident_user_id, report_type, incident_date, incident_time, incident_place, incident_area_number, complaint_type, case_details, case_status_id, case_level_id, user_id_official_record_by) VALUES (\'DEMOCASE0002\', \'202608R09001\', \'Blotter\', ?, \'08:15:00\', \'Mabini Street\', \'Area01\', \'Property Dispute\', \'Boundary disagreement referred for barangay mediation.\', ?, ?, ?)', [$now->modify('-10 days')->format('Y-m-d'), $blotterActive, $blotterOnly, $official]);
    seed_exec($conn, 'INSERT IGNORE INTO barangayblottertbl (blotter_id, case_id, blotter_number, logbook_id, date_filed, time_filed) VALUES (\'DBLT000001\', \'DEMOCASE0002\', \'DEMO-BLT-2026-001\', \'LOG-DEMO-01\', ?, \'09:00:00\')', [$now->modify('-10 days')->format('Y-m-d')]);
    foreach ([['Complainant', 'Dela Cruz', 'Maria', 'Area01, San Jose'], ['Respondent', 'Villanueva', 'Carlo', 'Area01, San Jose'], ['Witness', 'Ramos', 'Elena', 'Area01, San Jose']] as [$role, $last, $first, $address]) {
        seed_exec($conn, 'INSERT INTO caseparticipantstbl (case_id, participant_role, lastname, firstname, address, area_number) SELECT \'DEMOCASE0002\', ?, ?, ?, ?, \'Area01\' WHERE NOT EXISTS (SELECT 1 FROM caseparticipantstbl WHERE case_id = \'DEMOCASE0002\' AND participant_role = ? AND lastname = ?)', [$role, $last, $first, $address, $role, $last]);
    }
    seed_exec($conn, 'INSERT INTO casestatushistorytbl (case_id, status_id, changed_by, remarks) SELECT \'DEMOCASE0002\', ?, ?, \'Initial seeded blotter status\' WHERE NOT EXISTS (SELECT 1 FROM casestatushistorytbl WHERE case_id = \'DEMOCASE0002\' AND remarks = \'Initial seeded blotter status\')', [$blotterActive, $official]);
    seed_exec($conn, 'INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id) SELECT \'DEMOCASE0002\', \'Parties notified for initial mediation schedule.\', ? WHERE NOT EXISTS (SELECT 1 FROM caseupdateslogtbl WHERE case_id = \'DEMOCASE0002\' AND log_entry = \'Parties notified for initial mediation schedule.\')', [$official]);

    // Appointment queue.
    seed_exec($conn, 'INSERT IGNORE INTO appointmentstbl (appointment_id, user_id_resident, name, contact_number, email_address, booking_channel, subject, purpose, preferred_schedule_timestamp, user_id_official_assigned, appointment_status_id, resident_notes) VALUES (\'DAPT000001\', \'202608R09003\', \'Ana Reyes\', \'09170009003\', \'demo.ana@example.test\', \'Resident Portal\', \'Document Follow-up\', \'Follow up Barangay ID request\', ?, ?, ?, \'Seeded appointment\')', [$now->modify('+3 days')->setTime(10, 0)->format('Y-m-d H:i:s'), $official, $appointmentPending]);
    seed_exec($conn, 'INSERT IGNORE INTO appointmentqueuetbl (appointment_id, queue_number, queue_status_id) VALUES (\'DAPT000001\', \'DEMO-Q-001\', ?)', [$appointmentPending]);

    // Empty governance/access tables receive safe demonstration rows.
    $positionId = (int)(seed_one($conn, 'SELECT position_id FROM governmentpositiontbl ORDER BY position_id LIMIT 1')['position_id'] ?? 1);
    $jurisdictionId = (int)(seed_one($conn, 'SELECT jurisdiction_id FROM governmentjurisdictiontbl ORDER BY jurisdiction_id DESC LIMIT 1')['jurisdiction_id'] ?? 1);
    seed_exec($conn, 'INSERT INTO governmentofficialdirectorytbl (official_name, position_id, jurisdiction_id, display_order, is_active) SELECT \'Demo Government Official\', ?, ?, 999, 1 WHERE NOT EXISTS (SELECT 1 FROM governmentofficialdirectorytbl WHERE official_name = \'Demo Government Official\')', [$positionId, $jurisdictionId]);
    $council = seed_one($conn, 'SELECT council_id, seat_name FROM barangaycounciltbl ORDER BY council_id LIMIT 1');
    $councilId = (int)($council['council_id'] ?? 1);
    seed_exec($conn, 'INSERT IGNORE INTO officialtransitionstbl (transition_id, council_id, batch_label, election_date, transition_type, position, department, area_number, outcome, status, effective_date, reason, created_by) VALUES (\'DEMOTRANSITION00000000001\', ?, \'Demo 2026 Transition\', ?, \'Appointment\', ?, \'Barangay Council\', \'BarangayWide\', \'Pending\', \'Open\', ?, \'Seeded transition workflow\', ?)', [$councilId, $now->modify('+60 days')->format('Y-m-d'), (string)($council['seat_name'] ?? 'Council Member'), $now->modify('+90 days')->format('Y-m-d'), $superAdmin]);
    seed_exec($conn, 'INSERT INTO upcomingofficialstbl (transition_id, candidate_type, candidate_name, candidate_first_name, candidate_last_name, candidate_email, candidate_mobile, is_selected, notes, encoded_by) SELECT \'DEMOTRANSITION00000000001\', \'New\', \'Demo Candidate\', \'Demo\', \'Candidate\', \'candidate@example.test\', \'09170009100\', 1, \'Seeded candidate\', ? WHERE NOT EXISTS (SELECT 1 FROM upcomingofficialstbl WHERE transition_id = \'DEMOTRANSITION00000000001\' AND candidate_name = \'Demo Candidate\')', [$superAdmin]);
    seed_exec($conn, 'INSERT IGNORE INTO clearancefeechangerequeststbl (fee_change_id, request_type, proposed_fee_name, proposed_amount, notes, status, requested_by_user_id) VALUES (990001, \'add_type\', \'Demo Special Processing Fee\', 25.00, \'Seeded pending fee request\', \'pending\', ?)', [$official]);

    // One audit row makes the seed operation traceable.
    seed_exec($conn, 'INSERT IGNORE INTO unifiedauditlogstbl (audit_id, user_id, role_access, module_affected, target_type, target_id, action_type, remarks) VALUES (\'DEMOSEED00000001\', ?, \'SuperAdmin\', \'Database Seeder\', \'DemoDataset\', \'DEMO-2026-08\', \'SEED\', \'Inserted idempotent linked demonstration records.\')', [$superAdmin]);

    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, 'Seed failed and was rolled back: ' . $error->getMessage() . "\n");
    exit(1);
}

echo "Demo database seed completed successfully.\n";
