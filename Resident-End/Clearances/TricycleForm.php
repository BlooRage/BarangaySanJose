<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $residentSegmentPos = strpos($scriptName, '/Resident-End/');
    $baseUrl = '';
    if ($residentSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $residentSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}
?>
<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";
require_once __DIR__ . "/../../PhpFiles/General/documentRequestWorkflow.php";

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];
$configuredPurposeOptions = dms_document_purpose_options($conn, 'Barangay Clearance for Tricycle Permit');

$ownerLastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerFirstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerMiddleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerSuffix = htmlspecialchars((string)($residentinformationtbl['suffix'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerPhone = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerUnitNumberRaw = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$ownerHouseNumberRaw = trim((string)($residentaddresstbl['street_number'] ?? ''));
$ownerStreetNameRaw = trim((string)($residentaddresstbl['street_name'] ?? ''));
$ownerPhaseNumberRaw = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$ownerSubdivisionRaw = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$ownerAreaNumberRaw = trim((string)($residentaddresstbl['area_number'] ?? ''));

$ownerStreetNameHasBlock = $ownerStreetNameRaw !== '' && stripos($ownerStreetNameRaw, 'block') !== false;
$ownerStreetNumberHasLot = $ownerHouseNumberRaw !== '' && stripos($ownerHouseNumberRaw, 'lot') !== false;
$ownerIsLotBlockSystem = $ownerStreetNameHasBlock || $ownerStreetNumberHasLot;

$ownerStreetLabel = $ownerStreetNameRaw;
if ($ownerStreetLabel !== '' && stripos($ownerStreetLabel, 'street') === false && !$ownerStreetNameHasBlock) {
    $ownerStreetLabel .= ' Street';
}

$ownerSubdivisionLabel = $ownerSubdivisionRaw !== '' ? $ownerSubdivisionRaw . ' Subdivision' : '';

$ownerFullAddressParts = [];
if ($ownerIsLotBlockSystem) {
    $ownerLotNumber = trim((string)preg_replace('/^lot\s*/i', '', $ownerHouseNumberRaw));
    $ownerBlockNumber = trim((string)preg_replace('/^(block|blk)\s*/i', '', $ownerStreetNameRaw));
    $ownerPhaseValue = trim((string)preg_replace('/^phase\s*/i', '', $ownerPhaseNumberRaw));

    $ownerLotLabel = $ownerLotNumber !== '' ? 'Lot ' . $ownerLotNumber : $ownerHouseNumberRaw;
    $ownerBlockLabel = $ownerBlockNumber !== '' ? 'Blk ' . $ownerBlockNumber : $ownerStreetNameRaw;
    $ownerPhaseLabel = $ownerPhaseValue !== '' ? 'Phase ' . $ownerPhaseValue : ($ownerPhaseNumberRaw !== '' ? $ownerPhaseNumberRaw : '');

    $ownerFullAddressParts = array_filter([
        $ownerLotLabel,
        $ownerBlockLabel,
        $ownerPhaseLabel,
        $ownerSubdivisionLabel,
        'San Jose',
        'Rodriguez',
        'Rizal'
    ], fn($part) => $part !== '');
} else {
    if ($ownerUnitNumberRaw !== '') {
        $ownerFullAddressParts = array_filter([
            'Unit ' . $ownerUnitNumberRaw,
            $ownerStreetLabel,
            $ownerSubdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    } else {
        $ownerStreetLine = trim(implode(' ', array_filter([$ownerHouseNumberRaw, $ownerStreetLabel], fn($part) => $part !== '')));
        $ownerFullAddressParts = array_filter([
            $ownerStreetLine,
            $ownerSubdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    }
}

$ownerFullAddress = htmlspecialchars(implode(', ', $ownerFullAddressParts), ENT_QUOTES, 'UTF-8');

function tcTableExists(mysqli $conn, string $table): bool {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $tableEsc = $conn->real_escape_string($tableSafe);
    $result = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function tcColumnExists(mysqli $conn, string $table, string $column): bool {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $columnEsc = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$columnEsc}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function tcHistoryVehicleKey(array $record): string {
    $plateNumber = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)($record['plate_number'] ?? '')));
    if ($plateNumber !== '') {
        return 'plate:' . $plateNumber;
    }

    $chassisNumber = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)($record['chassis_number'] ?? '')));
    if ($chassisNumber !== '') {
        return 'chassis:' . $chassisNumber;
    }

    $bodyNumber = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)($record['body_number'] ?? '')));
    if ($bodyNumber !== null && $bodyNumber !== '') {
        return 'body:' . $bodyNumber;
    }

    $motorNumber = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)($record['motor_number'] ?? '')));
    if ($motorNumber !== null && $motorNumber !== '') {
        return 'motor:' . $motorNumber;
    }

    return '';
}

function tcHistoryLabel(array $record, string $submittedAt): string {
    $primary = trim((string)($record['plate_number'] ?? ''));
    if ($primary !== '') {
        $primary = 'Plate ' . $primary;
    } else {
        $bodyNumber = trim((string)($record['body_number'] ?? ''));
        if ($bodyNumber !== '') {
            $primary = 'Body No. ' . $bodyNumber;
        } else {
            $primary = trim((string)($record['vehicle_make'] ?? 'Tricycle record'));
        }
    }

    $parts = [];
    $franchisee = trim((string)($record['franchisee'] ?? ''));
    if ($franchisee !== '') {
        $parts[] = $franchisee;
    }
    $applicationType = trim((string)($record['application_type'] ?? ''));
    if ($applicationType !== '') {
        $parts[] = $applicationType;
    }
    if ($submittedAt !== '') {
        try {
            $parts[] = (new DateTime($submittedAt))->format('M j, Y');
        } catch (Throwable $e) {
            // Ignore date formatting errors and keep the label readable.
        }
    }

    if ($parts) {
        $primary .= ' - ' . implode(' | ', $parts);
    }

    return $primary;
}

function tcNormalizeTricycleHistory(array $payload, string $requestId, string $submittedAt): ?array {
    $record = [
        'request_id' => $requestId,
        'application_type' => trim((string)($payload['application_type'] ?? '')),
        'franchisee' => trim((string)($payload['franchisee'] ?? $payload['vehicle_franchise'] ?? $payload['_preview_franchisee'] ?? '')),
        'location_of_toda_poda' => trim((string)($payload['location_of_toda_poda'] ?? $payload['location'] ?? $payload['_preview_toda_poda_location'] ?? '')),
        'vehicle_make' => trim((string)($payload['vehicle_make'] ?? $payload['type_of_vehicle'] ?? $payload['_preview_vehicle_type'] ?? '')),
        'plate_number' => trim((string)($payload['plate_number'] ?? $payload['vehicle_plate_number'] ?? $payload['_preview_plate_number'] ?? '')),
        'body_number' => trim((string)($payload['body_number'] ?? $payload['_preview_body_number'] ?? '')),
        'chassis_number' => trim((string)($payload['chassis_number'] ?? '')),
        'motor_number' => trim((string)($payload['motor_number'] ?? '')),
        'or_number' => trim((string)($payload['or_number'] ?? '')),
        'cr_number' => trim((string)($payload['cr_number'] ?? $payload['registration_number'] ?? $payload['_preview_registration_number'] ?? '')),
        'vehicle_named_to_owner' => strtolower(trim((string)($payload['vehicle_named_to_owner'] ?? ''))),
    ];

    if (
        $record['franchisee'] === '' &&
        $record['vehicle_make'] === '' &&
        $record['plate_number'] === '' &&
        $record['body_number'] === '' &&
        $record['chassis_number'] === '' &&
        $record['motor_number'] === ''
    ) {
        return null;
    }

    $record['label'] = tcHistoryLabel($record, $submittedAt);
    return $record;
}

function tcFetchTricycleRenewalHistory(mysqli $conn, string $residentUserId): array {
    if ($residentUserId === '' || !tcTableExists($conn, 'documentrequesttbl')) {
        return [];
    }

    $dateColumn = '';
    foreach (['submitted_at', 'request_timestamp', 'created_at', 'updated_at'] as $candidate) {
        if (tcColumnExists($conn, 'documentrequesttbl', $candidate)) {
            $dateColumn = $candidate;
            break;
        }
    }
    $dateSelect = $dateColumn !== '' ? "d.{$dateColumn} AS submitted_at" : "'' AS submitted_at";

    $docTypes = [
        'barangay clearance for tricycle permit',
        'clearance for tricycle permit',
        'tricycle permit',
        'tricycle clearance',
        'for tricycle permit',
    ];

    $sql = "
        SELECT d.request_id, d.request_details, {$dateSelect}
        FROM documentrequesttbl d
        WHERE d.resident_user_id = ?
          AND LOWER(COALESCE(d.document_type, '')) IN (?, ?, ?, ?, ?)
    ";
    $types = 'ssssss';
    $params = [$residentUserId, $docTypes[0], $docTypes[1], $docTypes[2], $docTypes[3], $docTypes[4]];

    $canFilterByTransactions = tcTableExists($conn, 'residenttransactiontbl')
        && tcColumnExists($conn, 'residenttransactiontbl', 'resident_user_id')
        && tcColumnExists($conn, 'residenttransactiontbl', 'source_type')
        && tcColumnExists($conn, 'residenttransactiontbl', 'source_id');
    if ($canFilterByTransactions) {
        $sql .= "
          AND EXISTS (
              SELECT 1
              FROM residenttransactiontbl t
              WHERE t.resident_user_id = ?
                AND t.source_type = 'DOCUMENT_REQUEST'
                AND t.source_id = d.request_id
          )
        ";
        $types .= 's';
        $params[] = $residentUserId;
    }

    if ($dateColumn !== '') {
        $sql .= " ORDER BY d.{$dateColumn} DESC, d.request_id DESC";
    } else {
        $sql .= " ORDER BY d.request_id DESC";
    }
    $sql .= " LIMIT 50";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $bindValues = [$types];
    foreach ($params as $index => $value) {
        $bindValues[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $recordsByVehicle = [];
    while ($row = $result ? $result->fetch_assoc() : null) {
        $payload = function_exists('dr_decode_request_payload')
            ? dr_decode_request_payload($row)
            : json_decode((string)($row['request_details'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }

        $record = tcNormalizeTricycleHistory(
            $payload,
            (string)($row['request_id'] ?? ''),
            trim((string)($row['submitted_at'] ?? ''))
        );
        if (!$record) {
            continue;
        }

        $vehicleKey = tcHistoryVehicleKey($record);
        if ($vehicleKey === '') {
            $vehicleKey = 'request:' . (string)($record['request_id'] ?? '');
        }
        if (isset($recordsByVehicle[$vehicleKey])) {
            continue;
        }

        $recordsByVehicle[$vehicleKey] = $record;
    }
    $stmt->close();

    return array_values($recordsByVehicle);
}

$tricycleRenewalHistory = tcFetchTricycleRenewalHistory($conn, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Application for Barangay Clearance for Tricycle Permit - Barangay San Jose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        body {
            background: #fffdfb;
        }
        #div-mainDisplay {
            background: #ffffff !important;
        }
        #div-mainDisplay .form-title,
        #div-mainDisplay .form-subtitle,
        #div-mainDisplay .back-link {
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
        }
        #div-mainDisplay .page-form {
            max-width: 1300px;
            margin: 0 auto;
            padding-bottom: 48px;
        }
        h1 {
            font-size: 2.8rem !important;
            font-weight: 700;
        }
        h2.section-title,
        h3.section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 32px;
            margin-bottom: 24px;
        }
        .vehicle-row--tricycle-top {
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr) minmax(0, .9fr);
            align-items: end;
        }
        #todaPodaLocation.location-prefilled-display[disabled] {
            background-color: #ffffff !important;
            color: #1f2937 !important;
            border-color: #a8a7a7 !important;
            cursor: default !important;
            font-weight: 600;
        }
        #todaPodaLocation.location-prefilled-display[disabled]::placeholder {
            color: transparent;
        }
        @media (max-width: 768px) {
            .vehicle-row--tricycle-top {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .vehicle-row--tricycle-top {
                grid-template-columns: 1fr;
            }
        }
    </style></head>
<body>

<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
        
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Clearances/ClearancesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Barangay Clearance for Tricycle Permit</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form class="page-form" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="document_type" value="Barangay Clearance for Tricycle Permit">
                <input type="hidden" name="redirect" value="1">
                <input type="hidden" name="request_purpose" id="tricycleRequestPurpose" value="Tricycle Permit - New Application">
                <div class="mb-3"><label class="form-label fw-semibold">Request purpose</label><select name="purpose" class="form-select" required><option value="">Select purpose</option><?php foreach ($configuredPurposeOptions as $purposeOption): ?><option value="<?= htmlspecialchars((string)$purposeOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$purposeOption, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>

                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_last_name" required readonly value="<?php echo $ownerLastName; ?>"></div>
                    <div class="input-stack"><label class="top-label">First Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_first_name" required readonly value="<?php echo $ownerFirstName; ?>"></div>
                    <div class="input-stack"><label class="top-label">Middle Name </label><input type="text" name="applicant_middle_name" readonly value="<?php echo $ownerMiddleName; ?>"></div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <select name="applicant_suffix_display" class="text-bg-light" disabled>
                            <option value="" <?php echo ($ownerSuffix === '') ? 'selected' : ''; ?>>None</option>
                            <option value="Jr." <?php echo ($ownerSuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($ownerSuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                            <option value="III" <?php echo ($ownerSuffix === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($ownerSuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                        <input type="hidden" name="applicant_suffix" value="<?php echo htmlspecialchars($ownerSuffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-stack tablet-full">
                        <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                        <input type="text" name="applicant_contact_number" value="<?php echo $ownerPhone; ?>" readonly required>
                    </div>
                    <div class="input-stack mb-3 span-3">
                        <label class="top-label" for="applicant_full_address">Address <span class="required-asterisk">*</span></label>
                        <input type="text" id="applicant_full_address" name="applicant_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
                        <input type="hidden" id="applicantUnitNumber" value="<?php echo htmlspecialchars($ownerUnitNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="applicantStreetNumber" value="<?php echo htmlspecialchars($ownerHouseNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="applicantStreetName" value="<?php echo htmlspecialchars($ownerStreetNameRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="applicantSubdivision" value="<?php echo htmlspecialchars($ownerSubdivisionRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="applicantFullAddress" value="<?php echo $ownerFullAddress; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="d-flex align-items-center justify-content-start gap-3 flex-wrap app-type-row">
                            <p class="if-building-note mb-0">APPLICATION TYPE:</p>
                            <select id="applicationTypeSelect" name="application_type" class="form-select app-type-select" required>
                                <option value="">Select application type</option>
                                <option value="New">New Application</option>
                                <option value="Renewal">Renewal</option>
                            </select>
                        </div>
                    </div>
                </div>
                <h2 class="section-title text-center text-dark">Vehicle Information</h2>
                <div id="renewalTricycleHistoryRow" class="form-row d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="renewalTricycleHistorySelect">Previous Tricycle Transaction</label>
                            <select
                                id="renewalTricycleHistorySelect"
                                name="renewal_source_request_id"
                                class="form-select"
                                <?= empty($tricycleRenewalHistory) ? 'disabled' : '' ?>
                            >
                                <option value="">
                                    <?= empty($tricycleRenewalHistory)
                                        ? 'No previous tricycle permit transactions found'
                                        : 'Select the same vehicle from your previous transactions' ?>
                                </option>
                                <?php foreach ($tricycleRenewalHistory as $historyRecord): ?>
                                    <option value="<?= htmlspecialchars((string)($historyRecord['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($historyRecord['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-muted small mb-0">Select the same vehicle to auto-fill this renewal form.</p>
                        </div>
                    </div>
                </div>
                <div class="form-row vehicle-row--tricycle-top">
                    <div class="input-stack">
                        <label class="top-label" for="franchiseeSelect">Franchisee <span class="required-asterisk">*</span></label>
                        <select id="franchiseeSelect" name="franchisee" class="form-select" required>
                            <option value="">Select</option>
                            <option value="Private - FAMILY USE">Private - FAMILY USE</option>
                            <option value="Private - DELIVERY USE">Private - DELIVERY USE</option>
                            <option value="SJ1 - NEW ROTODA">SJ1 - NEW ROTODA</option>
                            <option value="SJ2 - SUBTODA">SJ2 - SUBTODA</option>
                            <option value="SJ3 - BAGONG BUHAY TODA">SJ3 - BAGONG BUHAY TODA</option>
                            <option value="SJ4 - KV1 TODA">SJ4 - KV1 TODA</option>
                            <option value="SJ5 - UPLAND TODA">SJ5 - UPLAND TODA</option>
                            <option value="SUBPODA">SUBPODA</option>
                            <option value="OTHERS">OTHERS</option>
                        </select>
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="todaPodaLocation">Location of TODA / PODA</label>
                        <input type="text" id="todaPodaLocation" class="location-prefilled-display" disabled>
                        <input type="hidden" id="todaPodaLocationValue" name="location_of_toda_poda" value="">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="vehicleMakeSelect">Make <span class="required-asterisk">*</span></label>
                        <select id="vehicleMakeSelect" name="vehicle_make" class="form-select" required>
                            <option value="">Select make</option>
                            <option value="Rusi">Rusi</option>
                            <option value="Yamaha">Yamaha</option>
                            <option value="Kawasaki">Kawasaki</option>
                            <option value="Honda">Honda</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>
                <div id="vehicleMakeOtherRow" class="form-row d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="vehicleMakeOther">Other Make <span class="required-asterisk">*</span></label>
                            <input type="text" id="vehicleMakeOther" name="vehicle_make_other" value="" placeholder="Please specify make" disabled>
                        </div>
                    </div>
                </div>
                <div id="otherTodaPodaLocationRow" class="form-row d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="otherTodaPodaLocation">Other TODA / PODA Location <span class="required-asterisk">*</span></label>
                            <input type="text" id="otherTodaPodaLocation" value="" disabled>
                            <div id="otherTodaPodaLocationError" class="text-danger small d-none">Location is required when franchisee is Others.</div>
                        </div>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">Plate Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="plateNumber" name="plate_number" pattern="[A-Za-z0-9]{1,7}" title="Up to 7 letters and numbers" placeholder="Up to 7 letters and numbers" maxlength="7" required>
                        <div id="plateNumberError" class="text-danger small d-none">Plate number must be up to 7 letters and numbers.</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Body Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="bodyNumber" name="body_number" pattern="[A-Za-z0-9]{1,8}" title="Up to 8 letters and numbers" placeholder="Up to 8 letters and numbers" maxlength="8" required>
                        <div id="bodyNumberError" class="text-danger small d-none">Body number must be up to 8 letters and numbers.</div>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">Chassis Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="chassisNumber" name="chassis_number" pattern="[A-Za-z0-9]{4}-[A-Za-z0-9]{11}" title="Format like ABCD-12345678901" placeholder="XXXX-XXXXXXXXXXX" maxlength="16" required>
                        <div id="chassisNumberError" class="text-danger small d-none">Chassis number must follow the format XXXX-XXXXXXXXXXX.</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Motor Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="motorNumber" name="motor_number" pattern="[A-Za-z0-9]{1,20}" title="Up to 20 letters and numbers" placeholder="Up to 20 letters and numbers" maxlength="20" required>
                        <div id="motorNumberError" class="text-danger small d-none">Motor number must be up to 20 letters and numbers.</div>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">O.R. Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="orNumber" name="or_number" pattern="[A-Za-z0-9]{1,20}" title="Up to 20 letters and numbers" placeholder="Up to 20 letters and numbers" maxlength="20" required>
                        <div id="orNumberError" class="text-danger small d-none">O.R. number must be up to 20 letters and numbers.</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">C.R. Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="crNumber" name="cr_number" pattern="[A-Za-z0-9]{1,20}" title="Up to 20 letters and numbers" placeholder="Up to 20 letters and numbers" maxlength="20" required>
                        <div id="crNumberError" class="text-danger small d-none">C.R. number must be up to 20 letters and numbers.</div>
                    </div>
                </div>
                <div id="documentUploadSection" class="d-none">
                    <h2 class="section-title text-center text-dark">Document Upload</h2>
                    <p class="form-subtitle">Accepted: PDF, JPG, JPEG, PNG</p>
                    <p id="renewalUploadGuidance" class="text-muted small mb-3 d-none">For renewal, only the updated O.R. of the vehicle is required. The other uploads are optional.</p>
                    <div class="form-row">
                        <div class="full-width">
                            <div class="d-flex align-items-center justify-content-start gap-3 app-type-row">
                                <p class="if-building-note mb-0">Is the vehicle named after the owner?</p>
                                <div class="check-item">
                                    <input type="radio" id="vehicleNamedYes" name="vehicle_named_to_owner" value="yes" class="clearance-radio">
                                    <label class="app-type-label" for="vehicleNamedYes">Yes</label>
                                </div>
                                <div class="check-item">
                                    <input type="radio" id="vehicleNamedNo" name="vehicle_named_to_owner" value="no" class="clearance-radio">
                                    <label class="app-type-label" for="vehicleNamedNo">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row two-col-row">
                        <div class="input-stack">
                            <label class="top-label" for="orVehicleFile"><span id="orVehicleLabelText">O.R. of the Vehicle</span> <span id="orVehicleRequiredMark" class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="orVehicleDropzone" for="orVehicleFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag file here or click to upload</span>
                                <small>Accepted: PDF, JPG, JPEG, PNG</small>
                            </label>
                            <input type="file" id="orVehicleFile" name="or_vehicle_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="orVehicleSelectedFile" class="selected-files small text-muted mt-2"></div>
                        </div>
                        <div class="input-stack">
                            <label class="top-label" for="crVehicleFile">C.R. of the Vehicle <span id="crVehicleRequiredMark" class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="crVehicleDropzone" for="crVehicleFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag file here or click to upload</span>
                                <small>Accepted: PDF, JPG, JPEG, PNG</small>
                            </label>
                            <input type="file" id="crVehicleFile" name="cr_vehicle_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="crVehicleSelectedFile" class="selected-files small text-muted mt-2"></div>
                        </div>
                    </div>
                    <div class="form-row two-col-row">
                        <div class="input-stack">
                            <label class="top-label" for="todaPodaCertFile">TODA / PODA Certification <span id="todaPodaRequiredMark" class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="todaPodaCertDropzone" for="todaPodaCertFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag file here or click to upload</span>
                                <small>Accepted: PDF, JPG, JPEG, PNG</small>
                            </label>
                            <input type="file" id="todaPodaCertFile" name="toda_poda_cert_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="todaPodaCertSelectedFile" class="selected-files small text-muted mt-2"></div>
                        </div>
                        <div class="input-stack">
                            <label class="top-label" for="authorizationVehicleFile">Authorization of Vehicle <span class="text-muted">(if applicable)</span></label>
                            <label class="upload-dropzone" id="authorizationVehicleDropzone" for="authorizationVehicleFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag file here or click to upload</span>
                                <small>Accepted: PDF, JPG, JPEG, PNG</small>
                            </label>
                            <input type="file" id="authorizationVehicleFile" name="authorization_vehicle_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="authorizationVehicleSelectedFile" class="selected-files small text-muted mt-2"></div>
                        </div>
                    </div>
                    <div id="deedOfSaleRow" class="form-row d-none">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label" for="deedOfSaleFile">Notarized Deed of Sale <span id="deedOfSaleRequiredMark" class="required-asterisk">*</span></label>
                                <label class="upload-dropzone" id="deedOfSaleDropzone" for="deedOfSaleFile">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Drag file here or click to upload</span>
                                    <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                </label>
                                <input type="file" id="deedOfSaleFile" name="deed_of_sale_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                <div id="deedOfSaleSelectedFile" class="selected-files small text-muted mt-2"></div>
                                <p class="text-muted small mt-2 mb-0">Upload the notarized deed of sale when the vehicle is not named after the owner.</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-12 d-none" id="lastYearClearanceCol">
                                    <label class="top-label" for="lastYearClearanceFile">Barangay Clearance from Previous Year <span id="lastYearClearanceRequiredMark" class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" id="lastYearClearanceDropzone" for="lastYearClearanceFile">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span>Drag file here or click to upload</span>
                                        <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                    </label>
                                    <input type="file" id="lastYearClearanceFile" name="last_year_clearance_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    <div id="lastYearClearanceSelectedFile" class="selected-files small text-muted mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="agreement-row">
                    <div class="agreement-text check-item">
                        <input type="checkbox" id="agreement" required>
                        <label for="agreement">I hereby certify that the above information is true and correct to the best of my knowledge and belief.</label>
                    </div>
                    <button type="submit" class="submit-btn">SUBMIT</button>
                </div>

            </form>
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script id="tricycleRenewalHistoryData" type="application/json"><?= htmlspecialchars((string)json_encode($tricycleRenewalHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') ?></script>
    <script src="../../JS-Script-Files/Resident-End/formValidationHighlight.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Clearances/tricycleClearanceScript.js"></script>
</body>
</html>
