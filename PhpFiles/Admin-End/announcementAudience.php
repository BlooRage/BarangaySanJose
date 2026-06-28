<?php

function ann_audience_parse_csv_values(?string $value): array
{
    $parts = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];

    return array_values(array_filter(array_map(static function ($item): string {
        return trim((string)$item);
    }, $parts), static function (string $item): bool {
        return $item !== '';
    }));
}

function ann_audience_normalize_group(string $group): string
{
    $group = strtolower(trim($group));
    if ($group === 'officials' || $group === 'official' || $group === 'admin' || $group === 'superadmin') {
        return 'official';
    }
    if ($group === 'employees' || $group === 'employee' || $group === 'personnel' || $group === 'personnels') {
        return 'personnel';
    }
    if ($group === 'residents' || $group === 'resident') {
        return 'resident';
    }

    return $group;
}

function ann_audience_normalize_area(?string $area): string
{
    $area = strtolower(trim((string)$area));
    if ($area === '') {
        return '';
    }

    $area = preg_replace('/\s+/', ' ', $area);
    if ($area === 'barangay wide' || $area === 'barangaywide') {
        return 'barangaywide';
    }

    if (preg_match('/^area\s*0*(\d+)\s*([a-z]?)$/', $area, $matches)) {
        $number = (string)((int)$matches[1]);
        $suffix = strtolower((string)($matches[2] ?? ''));
        return 'area' . $number . $suffix;
    }

    return preg_replace('/[^a-z0-9]+/', '', $area);
}

function ann_audience_unique_strings(array $values): array
{
    $clean = array_values(array_filter(array_map(static function ($value): string {
        return trim((string)$value);
    }, $values), static function (string $value): bool {
        return $value !== '';
    }));

    return array_values(array_unique($clean));
}

function ann_audience_config(array $announcement): array
{
    $scope = strtolower(trim((string)($announcement['audience_scope'] ?? 'all')));
    if (!in_array($scope, ['all', 'custom'], true)) {
        $scope = 'all';
    }

    $areas = ann_audience_unique_strings(
        isset($announcement['areas']) && is_array($announcement['areas'])
            ? (array)$announcement['areas']
            : ann_audience_parse_csv_values((string)($announcement['area'] ?? ''))
    );
    $roleGroups = ann_audience_unique_strings(
        isset($announcement['role_groups']) && is_array($announcement['role_groups'])
            ? (array)$announcement['role_groups']
            : ann_audience_parse_csv_values((string)($announcement['role_group'] ?? ''))
    );

    $normalizedAreas = array_values(array_unique(array_filter(array_map(
        static fn($area): string => ann_audience_normalize_area((string)$area),
        $areas
    ), static fn(string $area): bool => $area !== '')));
    $normalizedGroups = array_values(array_unique(array_filter(array_map(
        static fn($group): string => ann_audience_normalize_group((string)$group),
        $roleGroups
    ), static fn(string $group): bool => $group !== '')));

    return [
        'scope' => $scope,
        'areas' => $areas,
        'role_groups' => $roleGroups,
        'normalized_areas' => $normalizedAreas,
        'normalized_groups' => $normalizedGroups,
        'is_barangay_wide' => in_array('barangaywide', $normalizedAreas, true),
    ];
}

function ann_audience_build_label(string $scope, array $areas, array $roleGroups): string
{
    if ($scope !== 'custom') {
        return 'All Residents';
    }

    $parts = [];
    if ($areas) {
        $parts[] = implode(', ', $areas);
    }
    if ($roleGroups) {
        $parts[] = implode(', ', $roleGroups);
    }

    return $parts ? implode(', ', $parts) : 'Custom Audience';
}

function ann_audience_matches_viewer(array $announcement, array $viewer): bool
{
    $config = ann_audience_config($announcement);
    if ($config['scope'] !== 'custom') {
        return true;
    }

    $viewerArea = ann_audience_normalize_area((string)($viewer['area'] ?? ''));
    $viewerGroup = ann_audience_normalize_group((string)($viewer['group'] ?? ''));

    $areaAllowed = true;
    if ($config['normalized_areas'] && !$config['is_barangay_wide']) {
        $areaAllowed = $viewerArea !== '' && in_array($viewerArea, $config['normalized_areas'], true);
    }

    $groupAllowed = true;
    if ($config['normalized_groups']) {
        $groupAllowed = $viewerGroup !== '' && in_array($viewerGroup, $config['normalized_groups'], true);
    }

    return $areaAllowed && $groupAllowed;
}

function ann_audience_is_publicly_visible(array $announcement): bool
{
    $config = ann_audience_config($announcement);
    if ($config['scope'] !== 'custom') {
        return true;
    }

    if ($config['normalized_areas'] && !$config['is_barangay_wide']) {
        return false;
    }

    if ($config['normalized_groups'] === []) {
        return true;
    }

    return in_array('resident', $config['normalized_groups'], true);
}

function ann_audience_fetch_resident_context(mysqli $conn, string $userId): array
{
    if ($userId === '') {
        return ['group' => 'resident', 'area' => ''];
    }

    $stmt = $conn->prepare("
        SELECT a.area_number
        FROM residentinformationtbl r
        LEFT JOIN residentaddresstbl a ON a.resident_id = r.resident_id
          AND a.address_id = (
            SELECT MAX(a2.address_id)
            FROM residentaddresstbl a2
            WHERE a2.resident_id = r.resident_id
          )
        WHERE r.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return ['group' => 'resident', 'area' => ''];
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return [
        'group' => 'resident',
        'area' => trim((string)($row['area_number'] ?? '')),
    ];
}

function ann_audience_fetch_staff_context(mysqli $conn, string $userId, string $fallbackRole = 'Official'): array
{
    $fallbackGroup = ann_audience_normalize_group($fallbackRole);
    if (!in_array($fallbackGroup, ['official', 'personnel'], true)) {
        $fallbackGroup = 'official';
    }

    if ($userId === '') {
        return ['group' => $fallbackGroup, 'area' => ''];
    }

    $stmt = $conn->prepare("
        SELECT
            u.role_access AS account_role_access,
            oi.role_access AS info_role_access,
            oi.area_number
        FROM useraccountstbl u
        LEFT JOIN officialinformationtbl oi ON oi.user_id = u.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return ['group' => $fallbackGroup, 'area' => ''];
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $group = ann_audience_normalize_group((string)($row['info_role_access'] ?? ''));
    if (!in_array($group, ['official', 'personnel'], true)) {
        $group = ann_audience_normalize_group((string)($row['account_role_access'] ?? ''));
    }
    if (!in_array($group, ['official', 'personnel'], true)) {
        $group = $fallbackGroup;
    }

    return [
        'group' => $group,
        'area' => trim((string)($row['area_number'] ?? '')),
    ];
}
