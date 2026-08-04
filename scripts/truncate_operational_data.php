<?php
declare(strict_types=1);

/**
 * Clear operational data while preserving the sole SuperAdmin access account.
 * Usage: php scripts/truncate_operational_data.php --execute
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!in_array('--execute', $argv, true)) {
    fwrite(STDERR, "Refusing to make changes without --execute.\n");
    exit(2);
}

require_once __DIR__ . '/../PhpFiles/General/connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$preservedUserId = '202602S00001';
$account = $conn->query(
    "SELECT user_id, role_access FROM useraccountstbl WHERE user_id = '202602S00001' LIMIT 1"
)->fetch_assoc();

if (!$account || $account['role_access'] !== 'SuperAdmin') {
    throw new RuntimeException('Preserved SuperAdmin account 202602S00001 was not found; cleanup aborted.');
}

// These are schema/reference/configuration tables required for a usable clean system.
$preservedTables = [
    'appointmentsettingstbl',
    'areamastertbl',
    'barangaycounciltbl',
    'clearancefeetypetbl',
    'documentclearancesettingstbl',
    'documentissuancesettingstbl',
    'documentmoduleconfigtbl',
    'documentmodulesettingstbl',
    'documenttypelookuptbl',
    'generalfeestbl',
    'governmentjurisdictiontbl',
    'governmentofficialdropdowntbl',
    'governmentpositiontbl',
    'officialaccessrolepermissiontbl',
    'officialaccessroleprofiletbl',
    'officialseataccessprofiletbl',
    'officialseatmodulepermissionstbl',
    'officialtransitionsettingstbl',
    'personnelroleaccessprofiletbl',
    'personnelrolemodulepermissionstbl',
    'report_signatory_settings',
    'statuslookuptbl',
    'websitecontenttbl',
    'websitesettingstbl',
];

$specialTables = ['useraccountstbl', 'officialinformationtbl', 'officialprofileworkflowtbl'];
$tables = [];
$result = $conn->query(
    "SELECT TABLE_NAME
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
      ORDER BY TABLE_NAME"
);
while ($row = $result->fetch_assoc()) {
    $table = (string)$row['TABLE_NAME'];
    if (!in_array($table, $preservedTables, true) && !in_array($table, $specialTables, true)) {
        $tables[] = $table;
    }
}

$conn->query('SET FOREIGN_KEY_CHECKS = 0');
try {
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', $table);
        $conn->query("TRUNCATE TABLE `{$safeTable}`");
    }

    // TRUNCATE cannot preserve selected rows, so these tables use targeted deletes.
    $stmt = $conn->prepare('DELETE FROM officialprofileworkflowtbl WHERE user_id IS NULL OR user_id <> ?');
    $stmt->bind_param('s', $preservedUserId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM officialinformationtbl WHERE user_id IS NULL OR user_id <> ?');
    $stmt->bind_param('s', $preservedUserId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM useraccountstbl WHERE user_id <> ?');
    $stmt->bind_param('s', $preservedUserId);
    $stmt->execute();
    $stmt->close();

    // Seats remain as configuration, but must not point at removed officials.
    $conn->query("UPDATE barangaycounciltbl
                     SET current_official_id = NULL
                   WHERE current_official_id IS NOT NULL
                     AND current_official_id <> '0220260001'");
} finally {
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
}

$verification = $conn->query(
    "SELECT user_id, role_access, status_id_account
       FROM useraccountstbl
      ORDER BY user_id"
)->fetch_all(MYSQLI_ASSOC);

if (count($verification) !== 1 || $verification[0]['user_id'] !== $preservedUserId) {
    throw new RuntimeException('Post-cleanup account verification failed.');
}

echo 'Operational tables truncated: ' . count($tables) . PHP_EOL;
echo 'Preserved account: ' . $verification[0]['user_id'] . ' (' . $verification[0]['role_access'] . ')' . PHP_EOL;
echo "Cleanup completed successfully.\n";
