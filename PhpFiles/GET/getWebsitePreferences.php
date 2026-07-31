<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';

header('Content-Type: application/json; charset=utf-8');
$settings = wms_load_settings(isset($conn) && $conn instanceof mysqli ? $conn : null);
echo json_encode([
    'success' => true,
    'language' => (string)($settings['default_language'] ?? 'en'),
    'font_scale' => (string)($settings['default_font_scale'] ?? '100'),
    'high_contrast' => !empty($settings['high_contrast']),
    'reduced_motion' => !empty($settings['reduced_motion']),
]);
