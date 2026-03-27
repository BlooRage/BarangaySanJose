<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . '/../General/residentSeniorCitizenSync.php';

requireAuthenticatedSession(true);

function getResidentProfileData(mysqli $conn, string $userId): array {
    $deriveResidentAddressSystem = static function (array $address): string {
        $storedSystem = strtolower(trim((string)($address['address_system'] ?? '')));
        if (in_array($storedSystem, ['house', 'lot_block'], true)) {
            return $storedSystem;
        }

        $streetNumber = trim((string)($address['street_number'] ?? ''));
        $streetName = trim((string)($address['street_name'] ?? ''));
        if (
            preg_match('/^\s*lot\b/i', $streetNumber) ||
            preg_match('/^\s*block\b/i', $streetName)
        ) {
            return 'lot_block';
        }

        return 'house';
    };

    $residentinformationtbl = [
        'resident_id' => '',
        'firstname' => '',
        'middlename' => '',
        'lastname' => '',
        'suffix' => '',
        'sex' => '',
        'birthdate' => '',
        'age' => '',
        'civil_status' => '',
        'head_of_family' => '',
        'voter_status' => '',
        'occupation' => '',
        'employment_status' => '',
        'occupation_detail' => '',
        'religion' => '',
        'sector_membership' => '',
        'sector_membership_pending_review' => 0,
        'sector_membership_pending_labels' => '',
        'status_name_resident' => '',
        'emergency_name' => '',
        'emergency_contact' => '',
        'emergency_first_name' => '',
        'emergency_middle_name' => '',
        'emergency_last_name' => '',
        'emergency_suffix' => '',
        'emergency_relationship' => '',
        'emergency_address' => '',
        'profile_pic' => ''
    ];

	    $residentaddresstbl = [
	        'unit_number' => '',
	        'street_number' => '',
	        'street_name' => '',
	        'phase_number' => '',
	        'subdivision' => '',
	        'area_number' => '',
	        'house_type' => '',
	        'house_ownership' => '',
	        'residency_duration' => '',
            'address_system' => 'house'
	    ];

    $useraccountstbl = [
        'type' => '',
        'created' => '',
        'last_password_change' => '',
        'email' => '',
        'phone_number' => ''
    ];

    $residentId = null;

    $stmt = $conn->prepare("
        SELECT
            r.resident_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.sex,
            r.birthdate,
            r.birthplace,
            r.civil_status,
            r.head_of_family,
            r.voter_status,
            r.occupation,
            r.occupation_detail,
            r.religion,
            r.sector_membership,
        s.status_name AS status_name_resident,
        u.role_access,
        u.account_created,
        u.last_password_changed,
        u.email,
        u.phone_number,
        u.email_verify,
        u.phoneNum_verify,
            e.first_name AS emergency_first_name,
            e.middle_name AS emergency_middle_name,
            e.last_name AS emergency_last_name,
            e.suffix AS emergency_suffix,
            e.phone_number AS emergency_contact,
            e.relationship AS emergency_relationship,
            e.address AS emergency_address
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
        LEFT JOIN useraccountstbl u ON u.user_id = r.user_id
        LEFT JOIN emergencycontacttbl e ON e.user_id = r.user_id
        WHERE r.user_id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row = pii_decrypt_resident_row($row) ?? $row;
            $row = pii_decrypt_assoc($row, ['email', 'phone_number', 'emergency_first_name', 'emergency_middle_name', 'emergency_last_name', 'emergency_suffix', 'emergency_contact', 'emergency_relationship', 'emergency_address']);
            $residentId = $row['resident_id'];

            if ($residentId !== '') {
                $seniorSync = resident_sync_auto_senior_citizen($conn, (string)$residentId, $row);
                if (!empty($seniorSync['sector_membership'])) {
                    $row['sector_membership'] = (string)$seniorSync['sector_membership'];
                }
            }

            $birthdateFormatted = '';
            $age = '';
            if (!empty($row['birthdate'])) {
                $dob = new DateTime($row['birthdate']);
                $birthdateFormatted = $dob->format('F j, Y');
                $age = (new DateTime())->diff($dob)->y;
            }

            $employmentStatus = ((int)$row['occupation'] === 1) ? 'Employed' : 'Unemployed';
            $occupationText = ((int)$row['occupation'] === 1 && !empty($row['occupation_detail']))
                ? $row['occupation_detail']
                : '';

            $emergencyName = trim(
                ($row['emergency_first_name'] ?? '') . ' ' .
                (!empty($row['emergency_middle_name']) ? $row['emergency_middle_name'][0] . '. ' : '') .
                ($row['emergency_last_name'] ?? '') .
                (!empty($row['emergency_suffix']) ? ' ' . $row['emergency_suffix'] : '')
            );

            $residentinformationtbl = [
                'resident_id' => $row['resident_id'] ?? '',
                'firstname' => $row['firstname'] ?? '',
                'middlename' => $row['middlename'] ?? '',
                'lastname' => $row['lastname'] ?? '',
                'suffix' => $row['suffix'] ?? '',
                'sex' => $row['sex'] ?? '',
                'birthdate' => $birthdateFormatted,
                'birthplace' => $row['birthplace'] ?? '',
                'age' => $age,
                'civil_status' => $row['civil_status'] ?? '',
                'head_of_family' => ((int)$row['head_of_family'] === 1) ? 'Yes' : 'No',
                'voter_status' => ((int)$row['voter_status'] === 1) ? 'Registered Voter' : 'Not Registered',
                'occupation' => $occupationText,
                'employment_status' => $employmentStatus,
                'occupation_detail' => $row['occupation_detail'] ?? '',
                'religion' => $row['religion'] ?? '',
                'sector_membership' => !empty($row['sector_membership']) ? $row['sector_membership'] : 'None',
                'status_name_resident' => $row['status_name_resident'] ?? '',
                'emergency_name' => $emergencyName,
                'emergency_contact' => $row['emergency_contact'] ?? '',
                'emergency_first_name' => $row['emergency_first_name'] ?? '',
                'emergency_middle_name' => $row['emergency_middle_name'] ?? '',
                'emergency_last_name' => $row['emergency_last_name'] ?? '',
                'emergency_suffix' => $row['emergency_suffix'] ?? '',
                'emergency_relationship' => $row['emergency_relationship'] ?? '',
                'emergency_address' => $row['emergency_address'] ?? '',
                'profile_pic' => ''
            ];

            $useraccountstbl = [
                'type' => $row['role_access'] ?? '',
                'created' => !empty($row['account_created']) ? date('F j, Y', strtotime($row['account_created'])) : '',
                'last_password_change' => !empty($row['last_password_changed']) ? date('F j, Y', strtotime($row['last_password_changed'])) : '',
                'email' => $row['email'] ?? '',
                'phone_number' => $row['phone_number'] ?? '',
                'email_verify' => isset($row['email_verify']) ? (int)$row['email_verify'] : 0,
                'phone_verify' => isset($row['phoneNum_verify']) ? (int)$row['phoneNum_verify'] : 0
            ];
        }
        $stmt->close();
    }

	    if ($residentId) {
	        $stmtAddr = $conn->prepare("
	            SELECT unit_number, street_number, street_name, phase_number, subdivision, area_number,
	                   house_type, house_ownership, residency_duration
	            FROM residentaddresstbl
	            WHERE resident_id = ?
	            ORDER BY address_id DESC
	            LIMIT 1
	        ");
        if ($stmtAddr) {
            $stmtAddr->bind_param("s", $residentId);
            $stmtAddr->execute();
            $addr = $stmtAddr->get_result()->fetch_assoc();
	            if ($addr) {
                    $addr = pii_decrypt_resident_address_row($addr) ?? $addr;
	                $residentaddresstbl = [
	                    'unit_number' => $addr['unit_number'] ?? '',
	                    'street_number' => $addr['street_number'] ?? '',
	                    'street_name' => $addr['street_name'] ?? '',
	                    'phase_number' => $addr['phase_number'] ?? '',
	                    'subdivision' => $addr['subdivision'] ?? '',
	                    'area_number' => $addr['area_number'] ?? '',
	                    'house_type' => $addr['house_type'] ?? '',
	                    'house_ownership' => $addr['house_ownership'] ?? '',
	                    'residency_duration' => $addr['residency_duration'] ?? '',
                        'address_system' => 'house'
	                ];
                    $residentaddresstbl['address_system'] = $deriveResidentAddressSystem($residentaddresstbl);
	            }
            $stmtAddr->close();
	        }

            // Ensure profile reflects the most recently approved address change request,
            // even when address_id lexicographic ordering is not chronological.
            $approvedAddressStatusId = null;
            $stmtStatus = $conn->prepare("
                SELECT status_id
                FROM statuslookuptbl
                WHERE status_name = 'ApprovedRequest' AND status_type = 'EditRequest'
                LIMIT 1
            ");
            if ($stmtStatus) {
                $stmtStatus->execute();
                $stmtStatus->bind_result($approvedAddressStatusId);
                if (!$stmtStatus->fetch()) {
                    $approvedAddressStatusId = null;
                }
                $stmtStatus->close();
            }

            if ($approvedAddressStatusId !== null) {
                $stmtLatestApproved = $conn->prepare("
                    SELECT requested_changes
                    FROM resident_edit_requesttbl
                    WHERE resident_id = ?
                      AND request_type = 'address'
                      AND status_id = ?
                    ORDER BY reviewed_at DESC, request_id DESC
                    LIMIT 1
                ");
                if ($stmtLatestApproved) {
                    $stmtLatestApproved->bind_param("si", $residentId, $approvedAddressStatusId);
                    $stmtLatestApproved->execute();
                    $rowApproved = $stmtLatestApproved->get_result()->fetch_assoc();
                    $stmtLatestApproved->close();

                    if ($rowApproved && isset($rowApproved['requested_changes'])) {
                        $approvedChanges = json_decode((string)$rowApproved['requested_changes'], true);
                        if (is_array($approvedChanges)) {
                            $residentaddresstbl = [
                                'unit_number' => (string)($approvedChanges['unit_number'] ?? $residentaddresstbl['unit_number']),
                                'street_number' => (string)($approvedChanges['street_number'] ?? $residentaddresstbl['street_number']),
                                'street_name' => (string)($approvedChanges['street_name'] ?? $residentaddresstbl['street_name']),
                                'phase_number' => (string)($approvedChanges['phase_number'] ?? $residentaddresstbl['phase_number']),
                                'subdivision' => (string)($approvedChanges['subdivision'] ?? $residentaddresstbl['subdivision']),
                                'area_number' => (string)($approvedChanges['area_number'] ?? $residentaddresstbl['area_number']),
                                'house_type' => (string)($approvedChanges['house_type'] ?? $residentaddresstbl['house_type']),
                                'house_ownership' => (string)($approvedChanges['house_ownership'] ?? $residentaddresstbl['house_ownership']),
                                'residency_duration' => (string)($approvedChanges['residency_duration'] ?? $residentaddresstbl['residency_duration']),
                                'address_system' => (string)($approvedChanges['address_system'] ?? $residentaddresstbl['address_system'])
                            ];
                            $residentaddresstbl['address_system'] = $deriveResidentAddressSystem($residentaddresstbl);
                        }
                    }
                }
            }
	    }

    // Sector membership status (prefer normalized table; fallback to legacy unifiedfileattachmenttbl scan).
    if ($residentId) {
        $mapSectorKeyToLabel = static function ($sectorKey): string {
            $normalized = strtolower(trim((string)$sectorKey));
            $normalized = preg_replace('/[^a-z]/', '', $normalized);
            $map = [
                'pwd' => 'PWD',
                'seniorcitizen' => 'Senior Citizen',
                'student' => 'Student',
                'indigenouspeople' => 'Indigenous People',
                'indigenousperson' => 'Indigenous People',
                'singleparent' => 'Single Parent'
            ];
            return $map[$normalized] ?? trim((string)$sectorKey);
        };
        $toSectorNormKey = static function ($value): string {
            $normalized = strtolower(trim((string)$value));
            return preg_replace('/[^a-z]/', '', $normalized);
        };
        $parseSectorCsv = static function ($csv): array {
            $parts = array_map('trim', explode(',', (string)$csv));
            $parts = array_filter($parts, static function ($v) {
                $value = strtolower(trim((string)$v));
                return $value !== '' && $value !== 'none' && $value !== 'n/a';
            });
            return array_values(array_unique($parts));
        };

        $verified = [];
        $pendingLabels = [];
        $pendingCount = 0;
        $usedNewTable = false;
        $suppressedSectorNormKeys = [];
        $declaredSectors = $parseSectorCsv((string)($residentinformationtbl['sector_membership'] ?? ''));

        // Try new normalized table first.
        $stmtTbl = $conn->prepare("
            SELECT
                rsm.sector_key,
                s.status_name AS status_name,
                COALESCE(rsm.updated_at, rsm.upload_timestamp, rsm.created_at) AS status_ts,
                COALESCE(rsm.latest_attachment_id, 0) AS latest_attachment_id
            FROM residentsectormembershiptbl rsm
            LEFT JOIN statuslookuptbl s
                ON rsm.sector_status_id = s.status_id
            WHERE rsm.resident_id = ?
            ORDER BY status_ts DESC, latest_attachment_id DESC
        ");
        if ($stmtTbl) {
            $stmtTbl->bind_param("s", $residentId);
            if ($stmtTbl->execute()) {
                $usedNewTable = true;
                $res = $stmtTbl->get_result();
                $sectorState = []; // dedupeKey => ['label'=>..., 'has_verified'=>bool, 'has_pending'=>bool]
                while ($r = $res->fetch_assoc()) {
                    $sectorKey = (string)($r['sector_key'] ?? '');
                    $statusName = (string)($r['status_name'] ?? '');
                    if ($sectorKey === '') continue;
                    $label = $mapSectorKeyToLabel($sectorKey);
                    $dedupeKey = strtolower($label);
                    if (!isset($sectorState[$dedupeKey])) {
                        $sectorState[$dedupeKey] = [
                            'label' => $label,
                            'has_verified' => false,
                            'has_pending' => false
                        ];
                    }

                    $statusKey = strtolower(trim((string)$statusName));
                    $statusKey = preg_replace('/[\s_-]+/', '', $statusKey);
                    $isInactive = (
                        $statusKey === 'inactive'
                        || strpos($statusKey, 'inactive') !== false
                    );
                    $isRejected = (
                        strpos($statusKey, 'reject') !== false
                        || strpos($statusKey, 'cancel') !== false
                        || strpos($statusKey, 'failed') !== false
                        || strpos($statusKey, 'invalid') !== false
                    );
                    $isVerified = (
                        in_array($statusKey, ['verified', 'approved', 'verifiedresident', 'completed'], true)
                        || strpos($statusKey, 'verified') !== false
                        || strpos($statusKey, 'approved') !== false
                        || strpos($statusKey, 'complete') !== false
                    );
                    $isPending = (
                        !$isVerified
                        && !$isRejected
                        && (
                            strpos($statusKey, 'pending') !== false
                            || strpos($statusKey, 'review') !== false
                            || strpos($statusKey, 'verify') !== false
                            || strpos($statusKey, 'submitted') !== false
                            || strpos($statusKey, 'inspection') !== false
                            || strpos($statusKey, 'interview') !== false
                            || strpos($statusKey, 'for') !== false
                        )
                    );

                    if ($isInactive || $isRejected) {
                        $suppressedSectorNormKeys[$toSectorNormKey($label)] = true;
                        $sectorState[$dedupeKey]['has_pending'] = false;
                        $sectorState[$dedupeKey]['has_verified'] = false;
                    } elseif ($isVerified) {
                        $sectorState[$dedupeKey]['has_verified'] = true;
                    } elseif ($isPending) {
                        $sectorState[$dedupeKey]['has_pending'] = true;
                    }
                }

                foreach ($sectorState as $state) {
                    if (!empty($state['has_verified'])) {
                        $verified[] = (string)$state['label'];
                    } elseif (!empty($state['has_pending'])) {
                        $pendingLabels[] = (string)$state['label'];
                    }
                }
                $pendingCount = count($pendingLabels);
            }
            $stmtTbl->close();
        }

        if (!$usedNewTable) {
            // Legacy fallback: count pending + derive verified from attachments.
            $stmtPending = $conn->prepare("
                SELECT uf.remarks
                FROM unifiedfileattachmenttbl uf
                INNER JOIN statuslookuptbl s
                    ON uf.status_id_verify = s.status_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = ?
                  AND uf.remarks LIKE 'sector:%'
                  AND s.status_name = 'PendingReview'
                  AND s.status_type = 'ResidentDocumentProfiling'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
            ");
            if ($stmtPending) {
                $stmtPending->bind_param("s", $residentId);
                $stmtPending->execute();
                $res = $stmtPending->get_result();
                $seenPending = [];
	                while ($r = $res->fetch_assoc()) {
	                    $remarks = trim((string)($r['remarks'] ?? ''));
	                    if ($remarks === '') continue;
	                    $marker = trim((string)(explode(';', $remarks)[0] ?? ''));
	                    $lower = strtolower($marker);
	                    if (strpos($lower, 'sector:') !== 0) continue;
	                    $keyRaw = trim(substr($marker, strlen('sector:')));
	                    $key = trim((string)(explode(':', $keyRaw, 2)[0] ?? ''));
	                    if ($key === '') continue;
	                    $label = $mapSectorKeyToLabel($key);
	                    $dk = strtolower($label);
	                    if (isset($seenPending[$dk])) continue;
                    $seenPending[$dk] = true;
                    $pendingLabels[] = $label;
                }
                $stmtPending->close();
                $pendingCount = count($pendingLabels);
            }

            $stmtVerified = $conn->prepare("
                SELECT uf.remarks
                FROM unifiedfileattachmenttbl uf
                INNER JOIN statuslookuptbl s
                    ON uf.status_id_verify = s.status_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = ?
                  AND uf.remarks LIKE 'sector:%'
                  AND s.status_name = 'Verified'
                  AND s.status_type = 'ResidentDocumentProfiling'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
            ");
            if ($stmtVerified) {
                $stmtVerified->bind_param("s", $residentId);
                $stmtVerified->execute();
                $res = $stmtVerified->get_result();
                $seen = [];
	                while ($r = $res->fetch_assoc()) {
	                    $remarks = trim((string)($r['remarks'] ?? ''));
	                    if ($remarks === '') continue;
	                    $marker = trim((string)(explode(';', $remarks)[0] ?? ''));
	                    $lower = strtolower($marker);
	                    if (strpos($lower, 'sector:') !== 0) continue;
	                    $keyRaw = trim(substr($marker, strlen('sector:')));
	                    $key = trim((string)(explode(':', $keyRaw, 2)[0] ?? ''));
	                    if ($key === '') continue;
	                    $label = $mapSectorKeyToLabel($key);
	                    $dedupeKey = strtolower($label);
	                    if (isset($seen[$dedupeKey])) continue;
                    $seen[$dedupeKey] = true;
                    $verified[] = $label;
                }
                $stmtVerified->close();
            }
        }

        // De-dupe pending labels and remove ones already verified (avoid "Pending Review / PWD" if PWD is verified).
        $verifiedKeys = [];
        foreach ($verified as $v) {
            $verifiedKeys[strtolower($v)] = true;
        }
        $pendingOut = [];
        $seenPendingOut = [];
        foreach ($pendingLabels as $p) {
            $k = strtolower(trim((string)$p));
            if ($k === '' || isset($verifiedKeys[$k]) || isset($seenPendingOut[$k])) continue;
            $seenPendingOut[$k] = true;
            $pendingOut[] = $p;
        }
        $pendingLabels = $pendingOut;
        $pendingCount = count($pendingLabels);

        // Fallback: when declared sectors exist but no normalized status rows yet,
        // treat undeclared-as-verified entries as pending review on profile display.
        if ($pendingCount === 0 && !empty($declaredSectors)) {
            $verifiedKeysNorm = [];
            foreach ($verified as $v) {
                $verifiedKeysNorm[$toSectorNormKey($v)] = true;
            }

            foreach ($declaredSectors as $declared) {
                $label = $mapSectorKeyToLabel($declared);
                $norm = $toSectorNormKey($label);
                if ($norm === '' || isset($verifiedKeysNorm[$norm]) || isset($suppressedSectorNormKeys[$norm])) {
                    continue;
                }
                $pendingLabels[] = $label;
            }

            if (!empty($pendingLabels)) {
                $pendingLabels = array_values(array_unique($pendingLabels));
                $pendingCount = count($pendingLabels);
            }
        }

        $residentinformationtbl['sector_membership_pending_review'] = $pendingCount;
        $residentinformationtbl['sector_membership_pending_labels'] = $pendingLabels ? implode(', ', $pendingLabels) : '';
        $residentinformationtbl['sector_membership'] = $verified ? implode(', ', $verified) : 'None';
    }

    return [
        'residentinformationtbl' => $residentinformationtbl,
        'residentaddresstbl' => $residentaddresstbl,
        'useraccountstbl' => $useraccountstbl
    ];
}

if (empty(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $data = getResidentProfileData($conn, $_SESSION['user_id']);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
