<?php
require_once __DIR__ . '/../PhpFiles/General/connection.php';
require_once __DIR__ . '/../PhpFiles/General/residentSeniorCitizenSync.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$summary = resident_sync_auto_senior_citizen_for_all($conn);

fwrite(STDOUT, json_encode([
    'success' => true,
    'scanned' => (int)($summary['scanned'] ?? 0),
    'eligible' => (int)($summary['eligible'] ?? 0),
    'changed' => (int)($summary['changed'] ?? 0),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
