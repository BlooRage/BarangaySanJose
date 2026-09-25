<?php
// Run with: php tests/barangayIdLayoutRemovalTest.php (no database required).
declare(strict_types=1);
require_once __DIR__ . '/../PhpFiles/General/documentModuleSettings.php';

$default = dms_normalize_barangay_id_layout([]);
if (!in_array('back_signature', array_column($default['fields'], 'id'), true)) {
    throw new RuntimeException('New layouts must include the default signature.');
}
$edited = $default;
$edited['fields'] = array_values(array_filter($edited['fields'], static function (array $field): bool {
    return $field['id'] !== 'back_signature';
}));
$saved = dms_normalize_barangay_id_layout($edited);
$reloaded = dms_normalize_barangay_id_layout(json_decode(json_encode($saved), true));
if ($saved !== $reloaded || count($reloaded['fields']) !== count($edited['fields'])
    || in_array('back_signature', array_column($reloaded['fields'], 'id'), true)) {
    throw new RuntimeException('Deleted signatures must stay removed after save and reload.');
}
if (dms_normalize_barangay_id_layout(['fields' => []])['fields'] !== []) {
    throw new RuntimeException('Deleting the last field must preserve an empty layout.');
}
echo "Barangay ID layout removal checks passed.\n";
