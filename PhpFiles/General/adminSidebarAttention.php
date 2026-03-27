<?php
declare(strict_types=1);

if (!function_exists('sbatt_default_counts')) {
    function sbatt_default_counts(): array
    {
        return [
            'appointments_tracker' => 0,
            'appointments' => 0,
            'resident_tracker' => 0,
            'edit_requests' => 0,
            'sector_membership_verification' => 0,
            'resident_profiling' => 0,
            'head_of_family_verification' => 0,
            'household_member_verification' => 0,
            'household_profiling' => 0,
            'certificate_issuance' => 0,
            'clearance_issuance' => 0,
            'id_issuance_tracker' => 0,
            'id_issuance' => 0,
            'finance_payment_tracker' => 0,
            'finance_fee_management' => 0,
            'finance_transactions' => 0,
            'blotter_review_queue' => 0,
            'blotter_tools' => 0,
            'complaint_tracker' => 0,
            'complaint_tools' => 0,
            'content_change_request' => 0,
            'content_management' => 0,
            'user_management' => 0,
        ];
    }
}

if (!function_exists('sbatt_table_exists')) {
    function sbatt_table_exists(mysqli $conn, string $tableName): bool
    {
        static $cache = [];

        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $cacheKey = strtolower($tableName);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $safe = $conn->real_escape_string($tableName);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        $cache[$cacheKey] = $exists;

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return $exists;
    }
}

if (!function_exists('sbatt_column_exists')) {
    function sbatt_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        static $cache = [];

        $tableName = trim($tableName);
        $columnName = trim($columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        $cacheKey = strtolower($tableName . '|' . $columnName);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (!sbatt_table_exists($conn, $tableName)) {
            $cache[$cacheKey] = false;
            return false;
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]+/', '', $tableName) ?? '';
        $safeColumn = $conn->real_escape_string($columnName);
        if ($safeTable === '') {
            $cache[$cacheKey] = false;
            return false;
        }

        $res = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        $cache[$cacheKey] = $exists;

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return $exists;
    }
}

if (!function_exists('sbatt_scalar_count')) {
    function sbatt_scalar_count(mysqli $conn, string $sql): int
    {
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        $row = $res->fetch_assoc();
        $res->free();

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('sbatt_normalize_simple')) {
    function sbatt_normalize_simple($value): string
    {
        $value = strtolower(trim((string)$value));
        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }
}

if (!function_exists('sbatt_normalize_phase')) {
    function sbatt_normalize_phase($value): string
    {
        $value = sbatt_normalize_simple($value);
        return preg_replace('/^(phase|ph)/', '', $value) ?? '';
    }
}

if (!function_exists('sbatt_normalize_subdivision')) {
    function sbatt_normalize_subdivision($value): string
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/\bsubdivision\b/i', '', $value) ?? $value;
        $value = preg_replace('/\bsubd\.?\b/i', '', $value) ?? $value;
        return sbatt_normalize_simple($value);
    }
}

if (!function_exists('sbatt_normalize_street')) {
    function sbatt_normalize_street($value): string
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/\bstreet\b/i', '', $value) ?? $value;
        $value = preg_replace('/\bst\.?\b/i', '', $value) ?? $value;
        return sbatt_normalize_simple($value);
    }
}

if (!function_exists('sbatt_document_type_key')) {
    function sbatt_document_type_key(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}

if (!function_exists('sbatt_resolve_document_type')) {
    function sbatt_resolve_document_type(array $row): string
    {
        $documentType = trim((string)($row['document_type'] ?? ''));
        if ($documentType !== '') {
            return $documentType;
        }

        $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        foreach (['document_type', 'request_document_type', 'document'] as $key) {
            $candidate = trim((string)($payload[$key] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('sbatt_is_barangay_id_document')) {
    function sbatt_is_barangay_id_document(string $documentType): bool
    {
        $doc = strtolower(trim($documentType));
        if ($doc === '') {
            return false;
        }

        $token = sbatt_document_type_key($doc);
        return in_array($token, ['barangayid', 'applicationforbarangayid'], true)
            || strpos($doc, 'barangay id') !== false;
    }
}

if (!function_exists('sbatt_is_clearance_document')) {
    function sbatt_is_clearance_document(string $documentType): bool
    {
        $doc = strtolower(trim($documentType));
        if ($doc === '') {
            return false;
        }

        if (strpos($doc, 'clearance') !== false) {
            return true;
        }

        $token = sbatt_document_type_key($doc);
        $clearanceTokens = [
            'businesspermit',
            'businessclearance',
            'barangaybusinessclearance',
            'electricalpermit',
            'electricpermit',
            'waterpermit',
            'residentialpermit',
            'residentialbuildingpermit',
            'commercialpermit',
            'commercialbuildingpermit',
            'tricyclepermit',
            'tricycleclearance',
        ];

        foreach ($clearanceTokens as $clearanceToken) {
            if (strpos($token, $clearanceToken) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sbatt_is_certificate_document')) {
    function sbatt_is_certificate_document(string $documentType): bool
    {
        $doc = strtolower(trim($documentType));
        if ($doc === '') {
            return false;
        }

        if (strpos($doc, 'certificate') !== false) {
            return true;
        }

        $token = sbatt_document_type_key($doc);
        $certificateTokens = [
            'cohabitation',
            'certificateofcohabitation',
            'certificateofrelationshipforjailvisitation',
            'certificateforjailvisitation',
            'jailvisitation',
            'conjugalvisit',
            'certificateofindigency',
            'indigency',
            'firsttimejobseekercertificate',
            'certificateforfirsttimejobseeker',
            'firsttimejobseeker',
            'certificateofidentity',
            'identity',
            'certificateofresidency',
            'certificateofresidence',
            'residency',
            'certificateofgoodmoral',
            'goodmoral',
        ];

        return in_array($token, $certificateTokens, true);
    }
}

if (!function_exists('sbatt_is_pending_document_stage')) {
    function sbatt_is_pending_document_stage(string $stage): bool
    {
        $stage = strtolower(trim($stage));
        if ($stage === '') {
            return false;
        }

        return in_array($stage, [
            'submitted',
            'for_interview',
            'for_inspection',
            'fee_tagging',
            'for_payment',
            'payment_submitted',
        ], true) || strpos($stage, 'pending') !== false;
    }
}

if (!function_exists('sbatt_is_release_document_stage')) {
    function sbatt_is_release_document_stage(string $stage): bool
    {
        $stage = strtolower(trim($stage));
        if ($stage === '') {
            return false;
        }

        return $stage === 'ready_for_claim' || strpos($stage, 'release') !== false;
    }
}

if (!function_exists('sbatt_extract_marker_from_remarks')) {
    function sbatt_extract_marker_from_remarks(string $remarks): string
    {
        $remarks = trim($remarks);
        if ($remarks === '') {
            return '';
        }

        $parts = explode(';', $remarks);
        return trim((string)($parts[0] ?? ''));
    }
}

if (!function_exists('sbatt_extract_sector_key')) {
    function sbatt_extract_sector_key(string $marker): string
    {
        $marker = trim($marker);
        if ($marker === '' || stripos($marker, 'sector:') !== 0) {
            return '';
        }

        $raw = trim(substr($marker, strlen('sector:')));
        if ($raw === '') {
            return '';
        }

        $parts = explode(':', $raw, 2);
        return trim((string)($parts[0] ?? ''));
    }
}

if (!function_exists('sbatt_extract_sector_side')) {
    function sbatt_extract_sector_side(string $marker): string
    {
        $marker = trim($marker);
        if ($marker === '' || stripos($marker, 'sector:') !== 0) {
            return '';
        }

        $raw = trim(substr($marker, strlen('sector:')));
        $parts = explode(':', $raw, 3);
        $side = strtolower(trim((string)($parts[1] ?? '')));
        return in_array($side, ['front', 'back'], true) ? $side : '';
    }
}

if (!function_exists('sbatt_count_pending_appointments')) {
    function sbatt_count_pending_appointments(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'appointmentstbl')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM appointmentstbl a
            LEFT JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
            WHERE
                COALESCE(TRIM(LOWER(s.status_name)), '') = ''
                OR (
                    LOWER(COALESCE(s.status_name, '')) NOT LIKE '%approve%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%resched%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%complete%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%done%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%deny%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%denied%'
                    AND LOWER(COALESCE(s.status_name, '')) NOT LIKE '%reject%'
                )
        ");
    }
}

if (!function_exists('sbatt_count_pending_residents')) {
    function sbatt_count_pending_residents(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'residentinformationtbl')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM residentinformationtbl r
            LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id_resident
            WHERE
                REPLACE(LOWER(COALESCE(s.status_name, '')), ' ', '') IN ('pendingverification', 'pendingreview')
                OR LOWER(COALESCE(s.status_name, '')) LIKE 'pending%review%'
        ");
    }
}

if (!function_exists('sbatt_count_pending_edit_requests')) {
    function sbatt_count_pending_edit_requests(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'resident_edit_requesttbl')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM resident_edit_requesttbl r
            LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id
            WHERE REPLACE(LOWER(COALESCE(s.status_name, '')), ' ', '') = 'pendingrequest'
        ");
    }
}

if (!function_exists('sbatt_count_pending_sector_membership')) {
    function sbatt_count_pending_sector_membership(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'unifiedfileattachmenttbl')) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT
                uf.attachment_id,
                uf.source_id AS resident_id,
                uf.remarks,
                COALESCE(s.status_name, 'PendingReview') AS verify_status
            FROM unifiedfileattachmenttbl uf
            LEFT JOIN statuslookuptbl s ON s.status_id = uf.status_id_verify
            WHERE uf.source_type = 'ResidentProfiling'
              AND uf.remarks LIKE 'sector:%'
            ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        ");
        if (!$stmt) {
            return 0;
        }

        $stmt->execute();
        $res = $stmt->get_result();

        $groups = [];
        while ($row = $res->fetch_assoc()) {
            $residentId = trim((string)($row['resident_id'] ?? ''));
            if ($residentId === '') {
                continue;
            }

            $marker = sbatt_extract_marker_from_remarks((string)($row['remarks'] ?? ''));
            if ($marker === '' || stripos($marker, 'sector:') !== 0) {
                continue;
            }

            $sectorKey = strtolower(sbatt_extract_sector_key($marker));
            if ($sectorKey === '') {
                continue;
            }

            $side = sbatt_extract_sector_side(strtolower($marker));
            $slot = $side !== '' ? $side : 'single';
            $groupKey = $residentId . '|' . $sectorKey;

            if (isset($groups[$groupKey][$slot])) {
                continue;
            }

            $groups[$groupKey][$slot] = strtolower(trim((string)($row['verify_status'] ?? 'PendingReview')));
        }

        $stmt->close();

        $pendingCount = 0;
        foreach ($groups as $documents) {
            $hasAny = false;
            $anyRejected = false;
            $anyPending = false;
            $allVerified = true;

            foreach ($documents as $status) {
                $hasAny = true;
                if ($status === 'rejected') {
                    $anyRejected = true;
                }
                if ($status === 'pendingreview') {
                    $anyPending = true;
                }
                if ($status !== 'verified') {
                    $allVerified = false;
                }
            }

            $groupStatus = 'PendingReview';
            if ($hasAny && $anyRejected) {
                $groupStatus = 'Rejected';
            } elseif ($hasAny && !$anyPending && $allVerified) {
                $groupStatus = 'Verified';
            }

            if ($groupStatus === 'PendingReview') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }
}

if (!function_exists('sbatt_count_pending_head_of_family')) {
    function sbatt_count_pending_head_of_family(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'residentinformationtbl')) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT
                r.resident_id,
                a.street_number AS house_number,
                a.street_name,
                a.phase_number,
                a.subdivision,
                a.area_number
            FROM residentinformationtbl r
            LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
            LEFT JOIN residentaddresstbl a
                ON a.address_id = (
                    SELECT a2.address_id
                    FROM residentaddresstbl a2
                    WHERE a2.resident_id = r.resident_id
                    ORDER BY a2.address_id DESC
                    LIMIT 1
                )
            WHERE r.head_of_family = 1
              AND (s.status_name <> 'Archived' OR s.status_name IS NULL)
            ORDER BY r.resident_id DESC
        ");
        if (!$stmt) {
            return 0;
        }

        $stmt->execute();
        $res = $stmt->get_result();

        $groupKeys = [];
        while ($row = $res->fetch_assoc()) {
            $groupKey = implode('|', [
                sbatt_normalize_simple($row['house_number'] ?? ''),
                sbatt_normalize_street($row['street_name'] ?? ''),
                sbatt_normalize_phase($row['phase_number'] ?? ''),
                sbatt_normalize_subdivision($row['subdivision'] ?? ''),
                sbatt_normalize_simple($row['area_number'] ?? ''),
            ]);

            if (trim($groupKey, '|') === '') {
                $groupKey = 'unknown|' . trim((string)($row['resident_id'] ?? ''));
            }

            $groupKeys[$groupKey] = true;
        }
        $stmt->close();

        if ($groupKeys === []) {
            return 0;
        }

        $decisionMap = [];
        if (sbatt_table_exists($conn, 'householdheadverificationtbl')) {
            $res = $conn->query("
                SELECT group_key, decision_status
                FROM householdheadverificationtbl
            ");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $groupKey = trim((string)($row['group_key'] ?? ''));
                    if ($groupKey === '') {
                        continue;
                    }
                    $decisionMap[$groupKey] = strtolower(trim((string)($row['decision_status'] ?? 'Pending')));
                }
                $res->free();
            }
        }

        $pendingCount = 0;
        foreach (array_keys($groupKeys) as $groupKey) {
            $decision = $decisionMap[$groupKey] ?? 'pending';
            if ($decision === '' || $decision === 'pending') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }
}

if (!function_exists('sbatt_count_pending_household_member_verification')) {
    function sbatt_count_pending_household_member_verification(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'householdmemberverificationtbl') || !sbatt_column_exists($conn, 'householdmemberverificationtbl', 'status')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM householdmemberverificationtbl
            WHERE status = 'PendingReview'
        ");
    }
}

if (!function_exists('sbatt_document_workflow_counts')) {
    function sbatt_document_workflow_counts(mysqli $conn): array
    {
        $counts = [
            'certificate_issuance' => 0,
            'clearance_issuance' => 0,
            'id_issuance_tracker' => 0,
            'finance_payment_tracker' => 0,
        ];

        if (!sbatt_table_exists($conn, 'documentrequesttbl')) {
            return $counts;
        }

        $selectCols = ['stage'];
        if (sbatt_column_exists($conn, 'documentrequesttbl', 'document_type')) {
            $selectCols[] = 'document_type';
        }
        if (sbatt_column_exists($conn, 'documentrequesttbl', 'request_details')) {
            $selectCols[] = 'request_details';
        }

        $whereParts = [];
        if (sbatt_column_exists($conn, 'documentrequesttbl', 'stage')) {
            $whereParts[] = "LOWER(COALESCE(stage, '')) IN ('submitted', 'for_interview', 'for_inspection', 'fee_tagging', 'for_payment', 'payment_submitted', 'ready_for_claim')";
            $whereParts[] = "LOWER(COALESCE(stage, '')) LIKE '%pending%'";
            $whereParts[] = "LOWER(COALESCE(stage, '')) LIKE '%release%'";
        }

        $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM documentrequesttbl';
        if ($whereParts !== []) {
            $sql .= ' WHERE ' . implode(' OR ', $whereParts);
        }

        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return $counts;
        }

        while ($row = $res->fetch_assoc()) {
            $stage = strtolower(trim((string)($row['stage'] ?? '')));
            if ($stage === '') {
                continue;
            }

            $isPendingStage = sbatt_is_pending_document_stage($stage);
            $isReleaseStage = sbatt_is_release_document_stage($stage);
            $isAttentionStage = $isPendingStage || $isReleaseStage;
            if (!$isAttentionStage) {
                continue;
            }

            $documentType = sbatt_resolve_document_type($row);
            if (sbatt_is_barangay_id_document($documentType)) {
                $counts['id_issuance_tracker']++;
            } elseif (sbatt_is_clearance_document($documentType)) {
                $counts['clearance_issuance']++;
            } elseif (sbatt_is_certificate_document($documentType)) {
                $counts['certificate_issuance']++;
            }

            if ($stage === 'payment_submitted' || strpos($stage, 'pendingpaymentverification') !== false) {
                $counts['finance_payment_tracker']++;
            }
        }
        $res->free();

        return $counts;
    }
}

if (!function_exists('sbatt_count_pending_fee_requests')) {
    function sbatt_count_pending_fee_requests(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'clearancefeetypetbl') || !sbatt_column_exists($conn, 'clearancefeetypetbl', 'status')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM clearancefeetypetbl
            WHERE LOWER(COALESCE(status, '')) = 'pending'
        ");
    }
}

if (!function_exists('sbatt_count_pending_blotter_reviews')) {
    function sbatt_count_pending_blotter_reviews(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'blotterrequeststbl')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM blotterrequeststbl br
            LEFT JOIN statuslookuptbl s ON s.status_id = br.request_status_id
            WHERE COALESCE(TRIM(LOWER(s.status_name)), 'pending') = 'pending'
        ");
    }
}

if (!function_exists('sbatt_count_pending_complaints')) {
    function sbatt_count_pending_complaints(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'casereportstbl') || !sbatt_table_exists($conn, 'complaintstbl')) {
            return 0;
        }

        $sql = "
            SELECT
                c.case_id,
                COALESCE(s.status_name, 'Pending') AS status_name,
                COALESCE(ct.escalated_to_blotter, 0) AS escalated_to_blotter,
                br.request_status_name
            FROM casereportstbl c
            INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
            LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
            LEFT JOIN (
                SELECT
                    br1.complaint_case_id,
                    COALESCE(s1.status_name, 'Pending') AS request_status_name,
                    br1.requested_at,
                    br1.request_id
                FROM blotterrequeststbl br1
                LEFT JOIN statuslookuptbl s1 ON s1.status_id = br1.request_status_id
            ) br ON br.complaint_case_id = c.case_id
            WHERE c.report_type = 'Complaint'
            ORDER BY c.case_id ASC, br.requested_at DESC, br.request_id DESC
        ";

        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        $latestRows = [];
        while ($row = $res->fetch_assoc()) {
            $caseId = trim((string)($row['case_id'] ?? ''));
            if ($caseId === '' || isset($latestRows[$caseId])) {
                continue;
            }
            $latestRows[$caseId] = $row;
        }
        $res->free();

        $pendingCount = 0;
        foreach ($latestRows as $row) {
            $statusName = strtolower(trim((string)($row['status_name'] ?? 'Pending')));
            $requestStatus = strtolower(trim((string)($row['request_status_name'] ?? '')));
            $statusKey = 'pending';

            if ((int)($row['escalated_to_blotter'] ?? 0) === 1) {
                $statusKey = 'escalated';
            } elseif (in_array($requestStatus, ['pending', 'approved'], true)) {
                $statusKey = 'escalated';
            } elseif (strpos($statusName, 'resolved') !== false) {
                $statusKey = 'resolved';
            } elseif (strpos($statusName, 'drop') !== false) {
                $statusKey = 'dropped';
            } elseif (strpos($statusName, 'endorse') !== false) {
                $statusKey = 'escalated';
            }

            if ($statusKey === 'pending') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }
}

if (!function_exists('sbatt_count_pending_content_requests')) {
    function sbatt_count_pending_content_requests(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'websitecontentrequeststbl') || !sbatt_column_exists($conn, 'websitecontentrequeststbl', 'status')) {
            return 0;
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM websitecontentrequeststbl
            WHERE LOWER(COALESCE(status, '')) = 'pending'
        ");
    }
}

if (!function_exists('sbatt_count_pending_user_verification')) {
    function sbatt_count_pending_user_verification(mysqli $conn): int
    {
        if (!sbatt_table_exists($conn, 'useraccountstbl')) {
            return 0;
        }

        $hasEmailVerify = sbatt_column_exists($conn, 'useraccountstbl', 'email_verify');
        $hasPhoneVerify = sbatt_column_exists($conn, 'useraccountstbl', 'phoneNum_verify');
        if (!$hasEmailVerify && !$hasPhoneVerify) {
            return 0;
        }

        $conditions = [];
        if ($hasEmailVerify) {
            $conditions[] = 'COALESCE(email_verify, 0) <> 1';
        }
        if ($hasPhoneVerify) {
            $conditions[] = 'COALESCE(phoneNum_verify, 0) <> 1';
        }

        return sbatt_scalar_count($conn, "
            SELECT COUNT(*) AS total
            FROM useraccountstbl
            WHERE " . implode(' OR ', $conditions) . "
        ");
    }
}

if (!function_exists('sbatt_build_counts')) {
    function sbatt_build_counts(mysqli $conn): array
    {
        $counts = sbatt_default_counts();

        $counts['appointments_tracker'] = sbatt_count_pending_appointments($conn);
        $counts['resident_tracker'] = sbatt_count_pending_residents($conn);
        $counts['edit_requests'] = sbatt_count_pending_edit_requests($conn);
        $counts['sector_membership_verification'] = sbatt_count_pending_sector_membership($conn);
        $counts['head_of_family_verification'] = sbatt_count_pending_head_of_family($conn);
        $counts['household_member_verification'] = sbatt_count_pending_household_member_verification($conn);

        $documentCounts = sbatt_document_workflow_counts($conn);
        foreach ($documentCounts as $key => $value) {
            $counts[$key] = (int)$value;
        }

        $counts['finance_fee_management'] = sbatt_count_pending_fee_requests($conn);
        $counts['blotter_review_queue'] = sbatt_count_pending_blotter_reviews($conn);
        $counts['complaint_tracker'] = sbatt_count_pending_complaints($conn);
        $counts['content_change_request'] = sbatt_count_pending_content_requests($conn);
        $counts['user_management'] = sbatt_count_pending_user_verification($conn);

        $counts['appointments'] = $counts['appointments_tracker'];
        $counts['resident_profiling'] = $counts['resident_tracker'] + $counts['edit_requests'] + $counts['sector_membership_verification'];
        $counts['household_profiling'] = $counts['head_of_family_verification'] + $counts['household_member_verification'];
        $counts['id_issuance'] = $counts['id_issuance_tracker'];
        $counts['finance_transactions'] = $counts['finance_payment_tracker'] + $counts['finance_fee_management'];
        $counts['blotter_tools'] = $counts['blotter_review_queue'];
        $counts['complaint_tools'] = $counts['complaint_tracker'];
        $counts['content_management'] = $counts['content_change_request'];

        return $counts;
    }
}

if (!function_exists('sbatt_get_counts')) {
    function sbatt_get_counts(mysqli $conn, int $ttlSeconds = 45): array
    {
        $defaults = sbatt_default_counts();
        $ttlSeconds = max(5, min(300, $ttlSeconds));

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cacheKey = 'admin_sidebar_attention_counts_v1';
        $now = time();
        $cached = $_SESSION[$cacheKey] ?? null;

        if (is_array($cached)) {
            $expiresAt = (int)($cached['expires_at'] ?? 0);
            $counts = $cached['counts'] ?? [];
            if ($expiresAt >= $now && is_array($counts)) {
                return array_merge($defaults, $counts);
            }
        }

        try {
            $counts = sbatt_build_counts($conn);
        } catch (Throwable $e) {
            $counts = $defaults;
        }

        $_SESSION[$cacheKey] = [
            'expires_at' => $now + $ttlSeconds,
            'counts' => $counts,
        ];

        return array_merge($defaults, $counts);
    }
}
