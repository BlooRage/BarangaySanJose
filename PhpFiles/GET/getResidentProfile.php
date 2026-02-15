<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../General/connection.php";

function getResidentProfileData(mysqli $conn, string $userId): array {
    $residentinformationtbl = [
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
        'street_number' => '',
        'street_name' => '',
        'phase_number' => '',
        'subdivision' => '',
        'area_number' => '',
        'residency_duration' => ''
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
            $residentId = $row['resident_id'];

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
                : 'Unemployed';

            $emergencyName = trim(
                ($row['emergency_first_name'] ?? '') . ' ' .
                (!empty($row['emergency_middle_name']) ? $row['emergency_middle_name'][0] . '. ' : '') .
                ($row['emergency_last_name'] ?? '') .
                (!empty($row['emergency_suffix']) ? ' ' . $row['emergency_suffix'] : '')
            );

            $residentinformationtbl = [
                'firstname' => $row['firstname'] ?? '',
                'middlename' => $row['middlename'] ?? '',
                'lastname' => $row['lastname'] ?? '',
                'suffix' => $row['suffix'] ?? '',
                'sex' => $row['sex'] ?? '',
                'birthdate' => $birthdateFormatted,
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
            SELECT unit_number, street_number, street_name, phase_number, subdivision, area_number, residency_duration
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
                $residentaddresstbl = [
                    'unit_number' => $addr['unit_number'] ?? '',
                    'street_number' => $addr['street_number'] ?? '',
                    'street_name' => $addr['street_name'] ?? '',
                    'phase_number' => $addr['phase_number'] ?? '',
                    'subdivision' => $addr['subdivision'] ?? '',
                    'area_number' => $addr['area_number'] ?? '',
                    'residency_duration' => $addr['residency_duration'] ?? ''
                ];
            }
            $stmtAddr->close();
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

        $verified = [];
        $pendingLabels = [];
        $pendingCount = 0;
        $usedNewTable = false;

        // Try new normalized table first.
        $stmtTbl = $conn->prepare("
            SELECT rsm.sector_key, s.status_name AS status_name
            FROM residentsectormembershiptbl rsm
            LEFT JOIN statuslookuptbl s
                ON rsm.sector_status_id = s.status_id
            WHERE rsm.resident_id = ?
        ");
        if ($stmtTbl) {
            $stmtTbl->bind_param("s", $residentId);
            if ($stmtTbl->execute()) {
                $usedNewTable = true;
                $res = $stmtTbl->get_result();
                $seen = [];
                while ($r = $res->fetch_assoc()) {
                    $sectorKey = (string)($r['sector_key'] ?? '');
                    $statusName = (string)($r['status_name'] ?? '');
                    if ($sectorKey === '') continue;
                    $label = $mapSectorKeyToLabel($sectorKey);
                    $dedupeKey = strtolower($label);
                    if (strcasecmp($statusName, 'Verified') === 0) {
                        if (!isset($seen[$dedupeKey])) {
                            $seen[$dedupeKey] = true;
                            $verified[] = $label;
                        }
                    } elseif (strcasecmp($statusName, 'PendingReview') === 0) {
                        $pendingCount++;
                        $pendingLabels[] = $label;
                    }
                }
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
