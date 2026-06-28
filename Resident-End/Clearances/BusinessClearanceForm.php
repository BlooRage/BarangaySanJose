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
require_once __DIR__ . "/../../PhpFiles/General/uploadLimits.php";

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$ownerLastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerFirstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerMiddleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerSuffix = (string)($residentinformationtbl['suffix'] ?? '');
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
    $ownerLotNumber = trim((string)preg_replace('/^lot\\s*/i', '', $ownerHouseNumberRaw));
    $ownerBlockNumber = trim((string)preg_replace('/^(block|blk)\\s*/i', '', $ownerStreetNameRaw));
    $ownerPhaseValue = trim((string)preg_replace('/^phase\\s*/i', '', $ownerPhaseNumberRaw));

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

function bcTableExists(mysqli $conn, string $table): bool {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $tableEsc = $conn->real_escape_string($tableSafe);
    $result = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function bcColumnExists(mysqli $conn, string $table, string $column): bool {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $columnEsc = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$columnEsc}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function bcTruthy($value): bool {
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function bcHistoryLabel(string $businessName, string $submittedAt, string $applicationType): string {
    $label = trim($businessName);
    $parts = [];

    if ($submittedAt !== '') {
        try {
            $parts[] = (new DateTime($submittedAt))->format('M j, Y');
        } catch (Throwable $e) {
            // Ignore date formatting errors and keep the label readable.
        }
    }
    if ($applicationType !== '') {
        $parts[] = $applicationType;
    }

    if ($parts) {
        $label .= ' - ' . implode(' | ', $parts);
    }

    return $label;
}

function bcNormalizeBusinessHistory(array $payload, string $requestId, string $submittedAt): ?array {
    $businessName = trim((string)($payload['business_name'] ?? $payload['b_name'] ?? ''));
    if ($businessName === '') {
        return null;
    }

    $applicationType = trim((string)($payload['application_type'] ?? ''));
    $ownerType = trim((string)($payload['owner_type'] ?? ''));

    return [
        'request_id' => $requestId,
        'label' => bcHistoryLabel($businessName, $submittedAt, $applicationType),
        'business_name' => $businessName,
        'application_type' => $applicationType,
        'business_type' => trim((string)($payload['business_type'] ?? '')),
        'initial_operation_date' => trim((string)($payload['initial_operation_date'] ?? $payload['b_date'] ?? '')),
        'business_contact_number' => trim((string)($payload['business_contact_number'] ?? $payload['b_contact_1'] ?? '')),
        'owner_type' => $ownerType,
        'business_same_address' => bcTruthy($payload['business_same_address'] ?? ''),
        'business_address_system' => trim((string)($payload['business_address_system'] ?? '')),
        'business_unit_number' => trim((string)($payload['business_unit_number'] ?? '')),
        'business_street_number' => trim((string)($payload['business_street_number'] ?? $payload['business_house_number'] ?? '')),
        'business_street_name' => trim((string)($payload['business_street_name'] ?? '')),
        'business_subdivision' => trim((string)($payload['business_subdivision'] ?? '')),
        'business_lot_number' => trim((string)($payload['business_lot_number'] ?? '')),
        'business_block_number' => trim((string)($payload['business_block_number'] ?? '')),
        'business_phase_number' => trim((string)($payload['business_phase_number'] ?? '')),
        'business_subdivision_block' => trim((string)($payload['business_subdivision_block'] ?? '')),
        'business_barangay' => trim((string)($payload['business_barangay'] ?? 'San Jose')),
        'business_city' => trim((string)($payload['business_city'] ?? 'Rodriguez (Montalban)')),
        'business_province' => trim((string)($payload['business_province'] ?? 'Rizal')),
        'business_reg_type' => trim((string)($payload['renewal_business_reg_type'] ?? $payload['business_reg_type'] ?? '')),
        'proof_address_type' => trim((string)($payload['renewal_proof_address_type'] ?? $payload['proof_address_type'] ?? '')),
        'proof_address_number' => trim((string)($payload['renewal_proof_address_number'] ?? $payload['proof_address_number'] ?? '')),
        'renter_owner_last_name' => trim((string)($payload['ro_ln'] ?? '')),
        'renter_owner_first_name' => trim((string)($payload['ro_fn'] ?? '')),
        'renter_owner_middle_name' => trim((string)($payload['ro_mn'] ?? '')),
        'renter_owner_suffix' => trim((string)($payload['ro_sfx'] ?? '')),
    ];
}

function bcFetchBusinessRenewalHistory(mysqli $conn, string $residentUserId): array {
    if ($residentUserId === '' || !bcTableExists($conn, 'documentrequesttbl')) {
        return [];
    }

    $dateColumn = '';
    foreach (['submitted_at', 'request_timestamp', 'created_at', 'updated_at'] as $candidate) {
        if (bcColumnExists($conn, 'documentrequesttbl', $candidate)) {
            $dateColumn = $candidate;
            break;
        }
    }
    $dateSelect = $dateColumn !== '' ? "d.{$dateColumn} AS submitted_at" : "'' AS submitted_at";

    $docTypes = [
        'barangay clearance for business permit',
        'barangay business clearance',
        'business clearance',
    ];

    $sql = "
        SELECT d.request_id, d.request_details, {$dateSelect}
        FROM documentrequesttbl d
        WHERE d.resident_user_id = ?
          AND LOWER(COALESCE(d.document_type, '')) IN (?, ?, ?)
    ";
    $types = 'ssss';
    $params = [$residentUserId, $docTypes[0], $docTypes[1], $docTypes[2]];

    $canFilterByTransactions = bcTableExists($conn, 'residenttransactiontbl')
        && bcColumnExists($conn, 'residenttransactiontbl', 'resident_user_id')
        && bcColumnExists($conn, 'residenttransactiontbl', 'source_type')
        && bcColumnExists($conn, 'residenttransactiontbl', 'source_id');
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

    $recordsByBusiness = [];
    while ($row = $result ? $result->fetch_assoc() : null) {
        $payload = function_exists('dr_decode_request_payload')
            ? dr_decode_request_payload($row)
            : json_decode((string)($row['request_details'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }

        $record = bcNormalizeBusinessHistory(
            $payload,
            (string)($row['request_id'] ?? ''),
            trim((string)($row['submitted_at'] ?? ''))
        );
        if (!$record) {
            continue;
        }

        $businessKey = strtolower(preg_replace('/\s+/', ' ', trim((string)$record['business_name'])));
        if ($businessKey === '' || isset($recordsByBusiness[$businessKey])) {
            continue;
        }
        $recordsByBusiness[$businessKey] = $record;
    }
    $stmt->close();

    return array_values($recordsByBusiness);
}

$businessRenewalHistory = bcFetchBusinessRenewalHistory($conn, $userId);
$residentUploadLimitBytes = app_upload_limit_bytes('resident');
$residentUploadLimitLabel = app_upload_limit_label('resident');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Clearance - Barangay San Jose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        body {
            background: #e8e4e4;
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
        input[type="date"] {
            background-color: #ffffff !important;
            color: #212529;
        }
        input[type="date"]::-webkit-datetime-edit,
        input[type="date"]::-webkit-date-and-time-value {
            color: #212529;
        }
        .business-upload-group {
            display: flex;
            flex-direction: column;
            gap: 32px;
            margin-top: 8px;
        }
        .business-upload-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .business-upload-note {
            margin: 0;
            font-size: 0.95rem;
            color: #6c757d;
        }
        .business-attachment-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .business-attachment-stack > div {
            margin-bottom: 0 !important;
        }
        .business-upload-button {
            align-self: flex-start;
        }
        .business-upload-inline-field {
            width: 100%;
            max-width: none;
        }
    </style>
</head>
<body>
<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>
    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Clearances/ClearancesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Barangay Business Clearance</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form class="page-form" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" method="POST" enctype="multipart/form-data" data-upload-limit-bytes="<?= (int)$residentUploadLimitBytes ?>" data-upload-limit-label="<?= htmlspecialchars($residentUploadLimitLabel, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="document_type" value="Barangay Clearance for Business Permit">
                <input type="hidden" name="redirect" value="1">
                <input type="hidden" name="request_purpose" id="businessRequestPurpose" value="">
                
                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name <span class="required-asterisk">*</span></label><input type="text" name="o_ln" required value="<?php echo $ownerLastName; ?>" readonly></div>
                    <div class="input-stack"><label class="top-label">First Name <span class="required-asterisk">*</span></label><input type="text" name="o_fn" required value="<?php echo $ownerFirstName; ?>" readonly></div>
                    <div class="input-stack"><label class="top-label">Middle Name </label><input type="text" name="o_mn" value="<?php echo $ownerMiddleName; ?>" readonly></div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <select name="o_sfx" disabled>
                            <option value="" <?php echo ($ownerSuffix === '') ? 'selected' : ''; ?>>None</option>
                            <option value="Jr." <?php echo ($ownerSuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($ownerSuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                            <option value="III" <?php echo ($ownerSuffix === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($ownerSuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                        <input type="hidden" name="o_sfx" value="<?php echo htmlspecialchars((string)$ownerSuffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div id="ownerContactAddressRow" class="form-row">
                    <div class="input-stack tablet-full">
                        <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                        <input type="text" name="o_phone" value="<?php echo $ownerPhone; ?>" readonly>
                    </div>
                    <div class="input-stack mb-3 span-3">
                        <label class="top-label" for="owner_full_address">Address <span class="required-asterisk">*</span></label>
                        <input type="text" id="owner_full_address" name="owner_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
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
                <fieldset id="applicationTypeDependentFields" class="border-0 p-0 m-0 d-none" disabled>
                <h2 class="section-title text-center text-dark">Business Details</h2>
                <div id="renewalBusinessHistoryRow" class="form-row d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="renewalBusinessHistorySelect">Previous Business Transaction</label>
                            <select
                                id="renewalBusinessHistorySelect"
                                name="renewal_source_request_id"
                                class="form-select"
                                <?= empty($businessRenewalHistory) ? 'disabled' : '' ?>
                            >
                                <option value="">
                                    <?= empty($businessRenewalHistory)
                                        ? 'No previous business permit transactions found'
                                        : 'Select the same business from your previous transactions' ?>
                                </option>
                                <?php foreach ($businessRenewalHistory as $historyRecord): ?>
                                    <option value="<?= htmlspecialchars((string)($historyRecord['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($historyRecord['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="business-upload-note">Select a previous business permit transaction to auto-fill this renewal form.</p>
                        </div>
                    </div>
                </div>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label" for="businessName">Name of Business <span class="required-asterisk">*</span></label><input type="text" id="businessName" name="business_name" required></div></div></div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label check-item">
                            <input type="checkbox" id="businessSameAddress" name="business_same_address">
                            <span>Same address as applicant</span>
                        </label>
                    </div>
                </div>
                <div id="businessAddressSystemRow" class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="businessAddressSystem">Address System <span class="required-asterisk">*</span></label>
                            <select id="businessAddressSystem" name="business_address_system" class="form-select w-100">
                                <option value="">Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="businessFullAddressWrapper" class="form-row d-none">
                    <div class="full-width">
                        <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" id="businessFullAddressDisplay" readonly value="<?php echo $ownerFullAddress; ?>">
                    </div>
                </div>
                <div id="businessHouseSystemWrapper" class="form-row pt-0 d-none">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="business_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="business_unit_number" name="business_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_street_number">Street Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_street_number" name="business_street_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_street_name" name="business_street_name">
                            </div>
                        </div>
                    </div>
                </div>
                <div id="businessSubdivisionHouseRow" class="form-row pt-0 d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="business_subdivision">Subdivision</label>
                            <input type="text" id="business_subdivision" name="business_subdivision">
                        </div>
                    </div>
                </div>
                <div id="businessBlockSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="business_lot_number">Lot Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_lot_number" name="business_lot_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_block_number">Block Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_block_number" name="business_block_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_phase_number">Phase <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_phase_number" name="business_phase_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_subdivision_block">Subdivision</label>
                        <input type="text" id="business_subdivision_block" name="business_subdivision_block">
                    </div>
                </div>
                <div id="businessBarangayRow" class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="business_barangay">Barangay</label>
                                <input type="text" id="business_barangay" name="business_barangay" readonly value="San Jose">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_city">Municipality / City</label>
                                <input type="text" id="business_city" name="business_city" readonly value="Rodriguez (Montalban)">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_province">Province</label>
                                <input type="text" id="business_province" name="business_province" readonly value="Rizal">
                            </div>
                        </div>
                        <input type="hidden" id="businessFullAddress" name="business_full_address" value="">
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Date of Initial Operation <span class="required-asterisk">*</span></label>
                        <input
                            type="date"
                            class="form-control"
                            id="initialOperationDate"
                            name="initial_operation_date"
                            placeholder="Select date"
                            data-date-modal-style="calendar"
                            required
                        >
                    </div>
                    <div>
                        <label class="top-label">Contact Number</label>
                        <input type="text" id="business_contact_number" name="business_contact_number" inputmode="numeric" maxlength="11">
                        <div id="business_contact_number_error" class="text-danger small d-none">Invalid contact number</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Nature / Type of Business <span class="required-asterisk">*</span></label>
                        <input type="text" name="business_type" id="business_type" required placeholder="e.g. Retail Store, Food & Beverages, Services">
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Ownership <span class="required-asterisk">*</span></label>
                        <select name="owner_type" id="ownerTypeSelect" required>
                            <option value="">Select</option>
                            <option value="Owner">Owner</option>
                            <option value="Renter">Renter</option>
                        </select>
                    </div>
                </div>

                <div id="documentUploadSection" class="d-none">
                    <h2 class="section-title text-center text-dark">Document Upload</h2>
                    <p class="form-subtitle">Accepted: PDF, JPG, JPEG, PNG. Uploaded files are saved as PDF.</p>

                    <div id="documentUploadNew" class="business-upload-group">
                        <div class="business-upload-section">
                            <label class="top-label" for="businessRegType">Business Registration <span class="required-asterisk">*</span></label>
                            <select id="businessRegType" name="business_reg_type" class="form-select business-upload-inline-field">
                                <option value="">Select</option>
                                <option value="dti">DTI Certificate (For sole proprietors)</option>
                                <option value="sec">SEC Certificate (For corporations)</option>
                            </select>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="businessRegFile1">Upload Business Registration <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="businessRegAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="business-reg-1">
                                    <label class="top-label" for="businessRegFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="businessRegFile1" for="businessRegFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessRegFile1Prompt">Drag and drop business registration or click to upload</div>
                                        <small id="businessRegFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessRegFile1" name="business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="business-reg-2">
                                    <label class="top-label" for="businessRegFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="businessRegFile2" for="businessRegFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessRegFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="businessRegFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessRegFile2" name="business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="business-reg-3">
                                    <label class="top-label" for="businessRegFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="businessRegFile3" for="businessRegFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessRegFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="businessRegFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessRegFile3" name="business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addBusinessRegAttachmentBtn">Add Attachment</button>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="proofAddressType">Proof of Business Address <span class="required-asterisk">*</span></label>
                            <select id="proofAddressType" name="proof_address_type" class="form-select business-upload-inline-field">
                                <option value="">Select</option>
                                <option value="lease">Contract of Lease</option>
                                <option value="tct">Transfer Certificate of Title</option>
                                <option value="tax_declaration">Tax Declaration</option>
                            </select>
                        </div>

                        <div id="proofAddressNumberRow" class="business-upload-section d-none">
                            <div class="input-stack">
                                <label class="top-label" for="proofAddressNumber">Tax Declaration / Certificate Title ID <span class="required-asterisk">*</span></label>
                                <input type="text" id="proofAddressNumber" name="proof_address_number" class="form-control business-upload-inline-field" placeholder="Enter ID number">
                                <div id="proofAddressNumberError" class="text-danger small d-none">Invalid document number</div>
                            </div>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="proofAddressFile1">Upload Proof of Address <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="proofAddressAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="proof-address-1">
                                    <label class="top-label" for="proofAddressFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="proofAddressFile1" for="proofAddressFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="proofAddressFile1Prompt">Drag and drop proof of address or click to upload</div>
                                        <small id="proofAddressFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="proofAddressFile1" name="proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="proof-address-2">
                                    <label class="top-label" for="proofAddressFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="proofAddressFile2" for="proofAddressFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="proofAddressFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="proofAddressFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="proofAddressFile2" name="proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="proof-address-3">
                                    <label class="top-label" for="proofAddressFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="proofAddressFile3" for="proofAddressFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="proofAddressFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="proofAddressFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="proofAddressFile3" name="proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addProofAddressAttachmentBtn">Add Attachment</button>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="businessPhotoFile1">Picture of Establishment or Business <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="businessPhotoAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="business-photo-1">
                                    <label class="top-label" for="businessPhotoFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="businessPhotoFile1" for="businessPhotoFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessPhotoFile1Prompt">Drag and drop establishment photo or click to upload</div>
                                        <small id="businessPhotoFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessPhotoFile1" name="business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="business-photo-2">
                                    <label class="top-label" for="businessPhotoFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="businessPhotoFile2" for="businessPhotoFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessPhotoFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="businessPhotoFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessPhotoFile2" name="business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="business-photo-3">
                                    <label class="top-label" for="businessPhotoFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="businessPhotoFile3" for="businessPhotoFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="businessPhotoFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="businessPhotoFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="businessPhotoFile3" name="business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addBusinessPhotoAttachmentBtn">Add Attachment</button>
                        </div>
                    </div>

                    <div id="documentUploadRenewal" class="business-upload-group d-none">
                        <div class="business-upload-section">
                            <label class="top-label" for="renewalBusinessRegType">Updated Business Registration <span class="required-asterisk">*</span></label>
                            <select id="renewalBusinessRegType" name="renewal_business_reg_type" class="form-select business-upload-inline-field">
                                <option value="">Select</option>
                                <option value="dti">DTI Certificate (For sole proprietors)</option>
                                <option value="sec">SEC Certificate (For corporations)</option>
                            </select>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="renewalBusinessRegFile1">Upload Business Registration <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="renewalBusinessRegAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="renewal-business-reg-1">
                                    <label class="top-label" for="renewalBusinessRegFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessRegFile1" for="renewalBusinessRegFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessRegFile1Prompt">Drag and drop updated business registration or click to upload</div>
                                        <small id="renewalBusinessRegFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessRegFile1" name="renewal_business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-business-reg-2">
                                    <label class="top-label" for="renewalBusinessRegFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessRegFile2" for="renewalBusinessRegFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessRegFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalBusinessRegFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessRegFile2" name="renewal_business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-business-reg-3">
                                    <label class="top-label" for="renewalBusinessRegFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessRegFile3" for="renewalBusinessRegFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessRegFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalBusinessRegFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessRegFile3" name="renewal_business_reg_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addRenewalBusinessRegAttachmentBtn">Add Attachment</button>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="renewalProofAddressType">Updated Business Address <span class="required-asterisk">*</span></label>
                            <select id="renewalProofAddressType" name="renewal_proof_address_type" class="form-select business-upload-inline-field">
                                <option value="">Select</option>
                                <option value="lease">Contract of Lease</option>
                                <option value="tct">Transfer Certificate of Title</option>
                                <option value="tax_declaration">Tax Declaration</option>
                            </select>
                        </div>

                        <div id="renewalProofAddressNumberRow" class="business-upload-section d-none">
                            <div class="input-stack">
                                <label class="top-label" for="renewalProofAddressNumber">Updated Tax Declaration / Certificate Title ID <span class="required-asterisk">*</span></label>
                                <input type="text" id="renewalProofAddressNumber" name="renewal_proof_address_number" class="form-control business-upload-inline-field" placeholder="Enter ID number">
                                <div id="renewalProofAddressNumberError" class="text-danger small d-none">Invalid document number</div>
                            </div>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="renewalProofAddressFile1">Upload Proof of Address <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="renewalProofAddressAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="renewal-proof-address-1">
                                    <label class="top-label" for="renewalProofAddressFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="renewalProofAddressFile1" for="renewalProofAddressFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalProofAddressFile1Prompt">Drag and drop updated proof of address or click to upload</div>
                                        <small id="renewalProofAddressFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalProofAddressFile1" name="renewal_proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-proof-address-2">
                                    <label class="top-label" for="renewalProofAddressFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="renewalProofAddressFile2" for="renewalProofAddressFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalProofAddressFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalProofAddressFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalProofAddressFile2" name="renewal_proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-proof-address-3">
                                    <label class="top-label" for="renewalProofAddressFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="renewalProofAddressFile3" for="renewalProofAddressFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalProofAddressFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalProofAddressFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalProofAddressFile3" name="renewal_proof_address_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addRenewalProofAddressAttachmentBtn">Add Attachment</button>
                        </div>

                        <div class="business-upload-section">
                            <label class="top-label" for="renewalBusinessPhotoFile1">Updated Picture of Establishment or Business <span class="required-asterisk">*</span></label>
                            <p class="business-upload-note">Attach up to 3 files.</p>
                            <div id="renewalBusinessPhotoAttachmentRows" class="business-attachment-stack">
                                <div data-business-attachment-row="renewal-business-photo-1">
                                    <label class="top-label" for="renewalBusinessPhotoFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessPhotoFile1" for="renewalBusinessPhotoFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessPhotoFile1Prompt">Drag and drop updated establishment photo or click to upload</div>
                                        <small id="renewalBusinessPhotoFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessPhotoFile1" name="renewal_business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png">
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-business-photo-2">
                                    <label class="top-label" for="renewalBusinessPhotoFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessPhotoFile2" for="renewalBusinessPhotoFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessPhotoFile2Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalBusinessPhotoFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessPhotoFile2" name="renewal_business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                                <div class="d-none" data-business-attachment-row="renewal-business-photo-3">
                                    <label class="top-label" for="renewalBusinessPhotoFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="renewalBusinessPhotoFile3" for="renewalBusinessPhotoFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div id="renewalBusinessPhotoFile3Prompt">Drag and drop additional attachment or click to upload</div>
                                        <small id="renewalBusinessPhotoFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="renewalBusinessPhotoFile3" name="renewal_business_photo_files[]" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    </label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm business-upload-button" id="addRenewalBusinessPhotoAttachmentBtn">Add Attachment</button>
                        </div>
                    </div>
                </div>

                <div id="renterOwnerDetails" class="d-none">
                    <h2 class="section-title text-center text-dark">If Renter Occupant, Name of the Owner</h2>
                    <div class="form-row">
                        <div class="input-stack"><label class="top-label">Last Name<span class="required-asterisk">*</span></label><input type="text" name="ro_ln"></div>
                        <div class="input-stack"><label class="top-label">First Name<span class="required-asterisk">*</span></label><input type="text" name="ro_fn"></div>
                        <div class="input-stack"><label class="top-label">Middle Name</label><input type="text" name="ro_mn"></div>
                        <div class="input-stack"><label class="top-label">Suffix</label><select name="ro_sfx"><option value="">None</option><option value="Jr.">Jr.</option><option value="Sr.">Sr.</option><option value="III">III</option><option value="IV">IV</option></select></div>
                    </div>
                </div>

                <div class="agreement-row">
                    <div class="agreement-text check-item">
                        <input type="checkbox" id="agreement" required>
                        <label for="agreement">I hereby certify that the above information is true and correct to the best of my knowledge and belief.</label>
                    </div>
                    <button type="submit" class="submit-btn">SUBMIT</button>
                </div>
                </fieldset>
            </form>
    </main>
</div>
    <div class="modal fade" id="businessUploadValidationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="businessUploadValidationModalTitle">Upload Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-dark" id="businessUploadValidationModalMessage">Please choose a valid file.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script id="businessRenewalHistoryData" type="application/json"><?= htmlspecialchars((string)json_encode($businessRenewalHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') ?></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/formValidationHighlight.js"></script>
    <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Clearances/businessClearanceScript.js"></script>
</body>
</html>
