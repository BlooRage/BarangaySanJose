<?php
declare(strict_types=1);

if (!function_exists('hhv_normalize_simple')) {
    function hhv_normalize_simple($value): string
    {
        $value = strtolower(trim((string)$value));
        return (string)preg_replace('/[^a-z0-9]/', '', $value);
    }
}

if (!function_exists('hhv_normalize_phase')) {
    function hhv_normalize_phase($value): string
    {
        $value = hhv_normalize_simple($value);
        return (string)preg_replace('/^(phase|ph)/', '', $value);
    }
}

if (!function_exists('hhv_normalize_subdivision')) {
    function hhv_normalize_subdivision($value): string
    {
        $value = strtolower(trim((string)$value));
        $value = (string)preg_replace('/\bsubdivision\b/i', '', $value);
        $value = (string)preg_replace('/\bsubd\.?\b/i', '', $value);
        return hhv_normalize_simple($value);
    }
}

if (!function_exists('hhv_normalize_street')) {
    function hhv_normalize_street($value): string
    {
        $value = strtolower(trim((string)$value));
        $value = (string)preg_replace('/\bstreet\b/i', '', $value);
        $value = (string)preg_replace('/\bst\.?\b/i', '', $value);
        return hhv_normalize_simple($value);
    }
}

if (!function_exists('hhv_build_group_key')) {
    function hhv_build_group_key(array $row, string $residentId = ''): string
    {
        $groupKey = implode('|', [
            hhv_normalize_simple($row['house_number'] ?? ''),
            hhv_normalize_street($row['street_name'] ?? ''),
            hhv_normalize_phase($row['phase_number'] ?? ''),
            hhv_normalize_subdivision($row['subdivision'] ?? ''),
            hhv_normalize_simple($row['area_number'] ?? ''),
        ]);

        if (trim($groupKey, '|') === '') {
            return $residentId !== '' ? 'unknown|' . $residentId : '';
        }

        return $groupKey;
    }
}

if (!function_exists('hhv_get_resident_head_verification')) {
    function hhv_get_resident_head_verification(mysqli $conn, string $residentId): array
    {
        $state = [
            'resident_id' => $residentId,
            'group_key' => '',
            'decision_status' => 'NotApplicable',
            'selected_resident_id' => '',
            'is_head' => false,
            'can_manage_members' => true,
            'pending' => false,
            'approved' => false,
            'message' => '',
        ];

        $residentId = trim($residentId);
        if ($residentId === '') {
            $state['can_manage_members'] = false;
            $state['message'] = 'Resident profile not found.';
            return $state;
        }

        $stmt = $conn->prepare("
            SELECT
                r.head_of_family,
                a.street_number AS house_number,
                a.street_name,
                a.phase_number,
                a.subdivision,
                a.area_number
            FROM residentinformationtbl r
            LEFT JOIN residentaddresstbl a
                ON a.address_id = (
                    SELECT a2.address_id
                    FROM residentaddresstbl a2
                    WHERE a2.resident_id = r.resident_id
                    ORDER BY a2.address_id DESC
                    LIMIT 1
                )
            WHERE r.resident_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            $state['can_manage_members'] = false;
            $state['message'] = 'Unable to validate head of family verification.';
            return $state;
        }

        $stmt->bind_param('s', $residentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $state['can_manage_members'] = false;
            $state['message'] = 'Resident profile not found.';
            return $state;
        }

        $headRaw = strtolower(trim((string)($row['head_of_family'] ?? '')));
        $isHead = in_array($headRaw, ['1', 'yes', 'true', 'y'], true);
        $state['is_head'] = $isHead;

        if (!$isHead) {
            return $state;
        }

        $groupKey = hhv_build_group_key($row, $residentId);
        $state['group_key'] = $groupKey;

        if ($groupKey === '') {
            $state['decision_status'] = 'Pending';
            $state['can_manage_members'] = false;
            $state['pending'] = true;
            $state['message'] = 'Head of family verification is still pending. You can add household members after approval.';
            return $state;
        }

        $tableCheck = $conn->query("SHOW TABLES LIKE 'householdheadverificationtbl'");
        $hasVerificationTable = $tableCheck instanceof mysqli_result && $tableCheck->num_rows > 0;
        if ($tableCheck instanceof mysqli_result) {
            $tableCheck->close();
        }

        if (!$hasVerificationTable) {
            $state['decision_status'] = 'Pending';
            $state['can_manage_members'] = false;
            $state['pending'] = true;
            $state['message'] = 'Head of family verification is still pending. You can add household members after approval.';
            return $state;
        }

        $decisionStmt = $conn->prepare("
            SELECT selected_resident_id, decision_status
            FROM householdheadverificationtbl
            WHERE group_key = ?
            LIMIT 1
        ");
        if (!$decisionStmt) {
            $state['can_manage_members'] = false;
            $state['message'] = 'Unable to validate head of family verification.';
            return $state;
        }

        $decisionStmt->bind_param('s', $groupKey);
        $decisionStmt->execute();
        $decision = $decisionStmt->get_result()->fetch_assoc();
        $decisionStmt->close();

        $decisionStatus = trim((string)($decision['decision_status'] ?? 'Pending'));
        $selectedResidentId = trim((string)($decision['selected_resident_id'] ?? ''));

        $state['decision_status'] = $decisionStatus;
        $state['selected_resident_id'] = $selectedResidentId;

        if (strcasecmp($decisionStatus, 'Approved') === 0 && $selectedResidentId === $residentId) {
            $state['approved'] = true;
            $state['can_manage_members'] = true;
            return $state;
        }

        $state['can_manage_members'] = false;
        if (strcasecmp($decisionStatus, 'Declined') === 0) {
            $state['message'] = 'Head of family verification was declined. Household members cannot be added until approval is granted.';
            return $state;
        }

        $state['pending'] = true;
        $state['message'] = 'Head of family verification is still pending. You can add household members after approval.';
        return $state;
    }
}
