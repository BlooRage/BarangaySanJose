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
        'emergency_name' => '',
        'emergency_contact' => '',
        'profile_pic' => ''
    ];

    $residentaddresstbl = [
        'street_number' => '',
        'street_name' => '',
        'subdivision' => '',
        'area_number' => ''
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
            e.phone_number AS emergency_contact
        FROM residentinformationtbl r
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
                'emergency_name' => $emergencyName,
                'emergency_contact' => $row['emergency_contact'] ?? '',
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
            SELECT street_number, street_name, subdivision, area_number
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
                    'street_number' => $addr['street_number'] ?? '',
                    'street_name' => $addr['street_name'] ?? '',
                    'subdivision' => $addr['subdivision'] ?? '',
                    'area_number' => $addr['area_number'] ?? ''
                ];
            }
            $stmtAddr->close();
        }
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
