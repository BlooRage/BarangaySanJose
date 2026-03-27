<?php

if (!function_exists('resident_sector_sync_normalize')) {
    function resident_sector_sync_normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z]/', '', $normalized);

        $map = [
            'pwd' => 'pwd',
            'seniorcitizen' => 'seniorcitizen',
            'student' => 'student',
            'indigenouspeople' => 'indigenouspeople',
            'indigenousperson' => 'indigenouspeople',
            'singleparent' => 'singleparent',
        ];

        return $map[$normalized] ?? $normalized;
    }
}

if (!function_exists('resident_sector_sync_parse_csv')) {
    function resident_sector_sync_parse_csv(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, static function ($item) {
            $clean = strtolower(trim((string)$item));
            return $clean !== '' && $clean !== 'none' && $clean !== 'n/a';
        }));

        $seen = [];
        $output = [];
        foreach ($parts as $part) {
            $norm = resident_sector_sync_normalize($part);
            $dedupeKey = $norm !== '' ? $norm : strtolower($part);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $output[] = $part;
        }

        return $output;
    }
}

if (!function_exists('resident_sector_sync_contains')) {
    function resident_sector_sync_contains(array $sectors, string $label): bool
    {
        $target = resident_sector_sync_normalize($label);
        foreach ($sectors as $sector) {
            if (resident_sector_sync_normalize((string)$sector) === $target) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('resident_sector_sync_append_label')) {
    function resident_sector_sync_append_label(string $currentCsv, string $label): string
    {
        $sectors = resident_sector_sync_parse_csv($currentCsv);
        if (!resident_sector_sync_contains($sectors, $label)) {
            $sectors[] = $label;
        }
        return implode(', ', $sectors);
    }
}

if (!function_exists('resident_sector_sync_calculate_age')) {
    function resident_sector_sync_calculate_age(?string $birthdate): ?int
    {
        $birthdate = trim((string)$birthdate);
        if ($birthdate === '') {
            return null;
        }

        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        if (!$dob) {
            try {
                $dob = new DateTimeImmutable($birthdate);
            } catch (Throwable $e) {
                return null;
            }
        }

        $today = new DateTimeImmutable('today');
        if ($dob > $today) {
            return null;
        }

        return $dob->diff($today)->y;
    }
}

if (!function_exists('resident_sector_sync_first_status_id')) {
    function resident_sector_sync_first_status_id(mysqli $conn, array $names, array $types = []): ?int
    {
        $names = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $names), static fn($v) => $v !== ''));
        $types = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $types), static fn($v) => $v !== ''));

        foreach ($names as $name) {
            if (!empty($types)) {
                foreach ($types as $type) {
                    $stmt = $conn->prepare("
                        SELECT status_id
                        FROM statuslookuptbl
                        WHERE status_name = ? AND status_type = ?
                        LIMIT 1
                    ");
                    if (!$stmt) {
                        continue;
                    }
                    $stmt->bind_param('ss', $name, $type);
                    $stmt->execute();
                    $stmt->bind_result($statusId);
                    $found = $stmt->fetch();
                    $stmt->close();
                    if ($found) {
                        return (int)$statusId;
                    }
                }
                continue;
            }

            $stmt = $conn->prepare("
                SELECT status_id
                FROM statuslookuptbl
                WHERE status_name = ?
                LIMIT 1
            ");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->bind_result($statusId);
            $found = $stmt->fetch();
            $stmt->close();
            if ($found) {
                return (int)$statusId;
            }
        }

        return null;
    }
}

if (!function_exists('resident_sector_sync_upsert_status')) {
    function resident_sector_sync_upsert_status(mysqli $conn, string $residentId, string $sectorKey, ?int $statusId): void
    {
        if ($residentId === '' || $sectorKey === '' || $statusId === null || $statusId <= 0) {
            return;
        }

        $remarks = 'Auto-synced from birthdate';
        $timestamp = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("
            INSERT INTO residentsectormembershiptbl
                (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)
            VALUES
                (?, ?, ?, NULL, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
                sector_status_id = VALUES(sector_status_id),
                latest_attachment_id = COALESCE(latest_attachment_id, VALUES(latest_attachment_id)),
                remarks = VALUES(remarks),
                upload_timestamp = VALUES(upload_timestamp),
                last_update_user_id = NULL,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('ssiss', $residentId, $sectorKey, $statusId, $remarks, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('resident_sync_auto_senior_citizen')) {
    function resident_sync_auto_senior_citizen(mysqli $conn, string $residentId, ?array $residentRow = null): array
    {
        $residentId = trim($residentId);
        if ($residentId === '') {
            return [
                'changed' => false,
                'eligible' => false,
                'age' => null,
                'sector_membership' => '',
            ];
        }

        $row = $residentRow;
        if (!is_array($row)) {
            $stmt = $conn->prepare("
                SELECT resident_id, birthdate, sector_membership
                FROM residentinformationtbl
                WHERE resident_id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                return [
                    'changed' => false,
                    'eligible' => false,
                    'age' => null,
                    'sector_membership' => '',
                ];
            }
            $stmt->bind_param('s', $residentId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!is_array($row) || empty($row)) {
            return [
                'changed' => false,
                'eligible' => false,
                'age' => null,
                'sector_membership' => '',
            ];
        }

        if (function_exists('pii_decrypt_resident_row')) {
            $decrypted = pii_decrypt_resident_row($row);
            if (is_array($decrypted)) {
                $row = $decrypted;
            }
        }

        $birthdate = (string)($row['birthdate'] ?? '');
        $currentSectorMembership = trim((string)($row['sector_membership'] ?? ''));
        $age = resident_sector_sync_calculate_age($birthdate);
        $isEligible = $age !== null && $age >= 60;

        if (!$isEligible) {
            return [
                'changed' => false,
                'eligible' => false,
                'age' => $age,
                'sector_membership' => $currentSectorMembership,
            ];
        }

        $updatedSectorMembership = resident_sector_sync_append_label($currentSectorMembership, 'Senior Citizen');
        $changed = trim($updatedSectorMembership) !== trim($currentSectorMembership);

        if ($changed) {
            $stmtUpdate = $conn->prepare("
                UPDATE residentinformationtbl
                SET sector_membership = ?
                WHERE resident_id = ?
                LIMIT 1
            ");
            if ($stmtUpdate) {
                $stmtUpdate->bind_param('ss', $updatedSectorMembership, $residentId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }

        $verifiedStatusId = resident_sector_sync_first_status_id(
            $conn,
            ['Verified', 'Approved', 'VerifiedResident', 'Completed'],
            ['SectorMembership', 'ResidentDocumentProfiling', 'Resident']
        );
        resident_sector_sync_upsert_status($conn, $residentId, 'SeniorCitizen', $verifiedStatusId);

        return [
            'changed' => $changed,
            'eligible' => true,
            'age' => $age,
            'sector_membership' => $changed ? $updatedSectorMembership : $currentSectorMembership,
        ];
    }
}

if (!function_exists('resident_sync_auto_senior_citizen_for_all')) {
    function resident_sync_auto_senior_citizen_for_all(mysqli $conn): array
    {
        $summary = [
            'scanned' => 0,
            'eligible' => 0,
            'changed' => 0,
        ];

        $result = $conn->query("
            SELECT resident_id, birthdate, sector_membership
            FROM residentinformationtbl
        ");
        if (!$result) {
            return $summary;
        }

        while ($row = $result->fetch_assoc()) {
            $summary['scanned']++;
            $residentId = trim((string)($row['resident_id'] ?? ''));
            if ($residentId === '') {
                continue;
            }

            $sync = resident_sync_auto_senior_citizen($conn, $residentId, $row);
            if (!empty($sync['eligible'])) {
                $summary['eligible']++;
            }
            if (!empty($sync['changed'])) {
                $summary['changed']++;
            }
        }
        $result->free();

        return $summary;
    }
}
