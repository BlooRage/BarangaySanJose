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

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$streetNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$areaNumber = trim((string)($residentaddresstbl['area_number'] ?? ''));
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
$governmentPositionOptions = [];
$governmentOfficials = [];
$governmentOfficialGroups = [
    ['id' => 'president', 'name' => 'President'],
    ['id' => 'vice_president', 'name' => 'Vice President'],
    ['id' => 'senate', 'name' => 'Senate'],
    ['id' => 'rizal_officials', 'name' => 'Rizal Officials'],
    ['id' => 'municipal_officials', 'name' => 'Rodriguez Municipal Officials'],
];

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS governmentofficialdropdowntbl (
            government_official_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            official_name VARCHAR(255) NOT NULL,
            position_name VARCHAR(255) NOT NULL,
            jurisdiction_location VARCHAR(255) NOT NULL,
            group_key VARCHAR(100) NOT NULL DEFAULT 'municipal_officials',
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_government_official_dropdown (official_name, position_name, jurisdiction_location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $seedOfficials = [
        ['PRESIDENT FERDINAND "BONGBONG" MARCOS JR.', 'President of the Philippines', 'Republic of the Philippines', 'president', 1],
        ['VICE PRESIDENT SARA Z. DUTERTE', 'Vice President of the Philippines', 'Republic of the Philippines', 'vice_president', 2],
        ['SENATE PRESIDENT FRANCIS "CHIZ" ESCUDERO', 'Senate President', 'Senate of the Philippines', 'senate', 3],
        ['HON. GENERAL RONNIE S. EVANGELISTA (RET.)', 'Mayor', 'Rodriguez, Rizal', 'municipal_officials', 4],
    ];
    $stmtSeedOfficial = $conn->prepare("
        INSERT INTO governmentofficialdropdowntbl (official_name, position_name, jurisdiction_location, group_key, display_order, is_active)
        SELECT ?, ?, ?, ?, ?, 1
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1
            FROM governmentofficialdropdowntbl
            WHERE official_name = ?
              AND position_name = ?
              AND jurisdiction_location = ?
            LIMIT 1
        )
    ");
    if ($stmtSeedOfficial) {
        foreach ($seedOfficials as [$name, $positionName, $locationName, $groupKey, $order]) {
            $stmtSeedOfficial->bind_param('ssssisss', $name, $positionName, $locationName, $groupKey, $order, $name, $positionName, $locationName);
            $stmtSeedOfficial->execute();
        }
        $stmtSeedOfficial->close();
    }

    $positionRes = $conn->query("
        SELECT DISTINCT position_name
        FROM governmentofficialdropdowntbl
        WHERE is_active = 1
        ORDER BY position_name ASC
    ");
    if ($positionRes instanceof mysqli_result) {
        while ($row = $positionRes->fetch_assoc()) {
            $governmentPositionOptions[] = (string)($row['position_name'] ?? '');
        }
        $positionRes->free();
    }

    $officialRes = $conn->query("
        SELECT government_official_id, official_name, position_name, jurisdiction_location, group_key
        FROM governmentofficialdropdowntbl
        WHERE is_active = 1
        ORDER BY display_order ASC, official_name ASC
    ");
    if ($officialRes instanceof mysqli_result) {
        while ($row = $officialRes->fetch_assoc()) {
            $governmentOfficials[] = [
                'id' => (string)($row['government_official_id'] ?? ''),
                'name' => (string)($row['official_name'] ?? ''),
                'position_name' => (string)($row['position_name'] ?? ''),
                'jurisdiction_location' => (string)($row['jurisdiction_location'] ?? ''),
                'group_key' => (string)($row['group_key'] ?? ''),
            ];
        }
        $officialRes->free();
    }
}

$streetNameHasBlock = $streetName !== '' && stripos($streetName, 'block') !== false;
$streetNumberHasLot = $streetNumber !== '' && stripos($streetNumber, 'lot') !== false;
$isLotBlockSystem = $streetNameHasBlock || $streetNumberHasLot;

$streetLabel = $streetName;
if ($streetLabel !== '' && stripos($streetLabel, 'street') === false && !$streetNameHasBlock) {
    $streetLabel .= ' Street';
}

$subdivisionLabel = $subdivision !== '' ? $subdivision . ' Subdivision' : '';

$fullAddressParts = [];
if ($isLotBlockSystem) {
    $lotNumber = trim((string)preg_replace('/^lot\\s*/i', '', $streetNumber));
    $blockNumber = trim((string)preg_replace('/^(block|blk)\\s*/i', '', $streetName));
    $phaseValue = trim((string)preg_replace('/^phase\\s*/i', '', $phaseNumber));

    $lotLabel = $lotNumber !== '' ? 'Lot ' . $lotNumber : $streetNumber;
    $blockLabel = $blockNumber !== '' ? 'Blk ' . $blockNumber : $streetName;
    $phaseLabel = $phaseValue !== '' ? 'Phase ' . $phaseValue : ($phaseNumber !== '' ? $phaseNumber : '');

    $fullAddressParts = array_filter([
        $lotLabel,
        $blockLabel,
        $phaseLabel,
        $subdivisionLabel,
        'San Jose',
        'Rodriguez',
        'Rizal'
    ], fn($part) => $part !== '');
} else {
    if ($unitNumber !== '') {
        $fullAddressParts = array_filter([
            'Unit ' . $unitNumber,
            $streetLabel,
            $subdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    } else {
        $streetLine = trim(implode(' ', array_filter([$streetNumber, $streetLabel], fn($part) => $part !== '')));
        $fullAddressParts = array_filter([
            $streetLine,
            $subdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    }
}

$fullAddress = htmlspecialchars(implode(', ', $fullAddressParts), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Indigency Application</title>
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
        #div-mainDisplay .indigency-form {
            max-width: 1300px;
            margin: 0 auto;
            padding-bottom: 48px;
        }
        h1{
            font-size: 2.8rem !important;
            font-weight: 700;
        }
        h2.section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 32px;
            margin-bottom: 24px;
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
                    <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                        <a href="<?= htmlspecialchars(appUrl('Resident-End/Certificates/CertificatesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                            <i class="bi bi-arrow-left-short fs-3"></i>
                        </a>
                        <h1 class="form-title m-0">Indigency</h1>
                    </div>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form id="indigencyRequestForm" class="indigency-form" method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php">
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="document_type" value="indigency">
                        <input type="hidden" name="redirect" value="1">

                        <h2 class="section-title text-center text-dark">Personal Information</h2>


                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                            </div>
                            
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <input type="text" class="text-bg-light" readonly value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" value="<?php echo $phoneNumber; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Request for <span class="required-asterisk">*</span></label>
                                <select name="purpose" required>
                                    <option value="">Select Purpose</option>
                                    <option value="Scholarship">Scholarship</option>
                                    <option value="Employment">Employment</option>
                                    <option value="Financial Assistance">Financial Assistance</option>
                                    <option value="Medical Assistance">Medical Assistance</option>
                                    <option value="Educational Assistance">Educational Assistance</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">To be submitted to <span class="required-asterisk">*</span></label>
                                <select name="submission_target_type" id="submissionTargetType" required onchange="window.toggleIndigencyRecipientRows && window.toggleIndigencyRecipientRows(this.value)">
                                    <option value="">Select recipient type</option>
                                    <option value="government_official">Government Official</option>
                                    <option value="institution">Institution</option>
                                </select>
                                <input type="hidden" name="request_officer" id="requestOfficerFinal">
                                <input type="hidden" name="government_office" id="governmentOfficeFinal">
                                <input type="hidden" name="government_position" id="governmentPositionFinal">
                                <input type="hidden" name="government_official" id="governmentOfficialFinal">
                            </div>
                        </div>

                        <div class="form-row two-col-row d-none" id="governmentOfficialRow">
                            <div>
                                <label class="top-label" for="governmentPositionSelect">Government Office <span class="required-asterisk">*</span></label>
                                <select name="government_position_group" id="governmentPositionSelect" onchange="window.filterIndigencyGovernmentOfficials && window.filterIndigencyGovernmentOfficials(this.value)">
                                    <option value="">Select office group</option>
                                    <?php foreach ($governmentOfficialGroups as $group): ?>
                                        <option value="<?= htmlspecialchars((string)$group['id'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string)$group['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__">Other</option>
                                </select>
                                <input type="text" name="government_position_other" id="governmentPositionOther" class="d-none mt-2" placeholder="Enter office group">
                            </div>
                            <div>
                                <label class="top-label" for="governmentPositionDetail">Government Position <span class="required-asterisk">*</span></label>
                                <select name="government_position_detail" id="governmentPositionDetail">
                                    <option value="">Select position</option>
                                    <?php foreach ($governmentPositionOptions as $positionOption): ?>
                                        <option value="<?= htmlspecialchars($positionOption, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($positionOption, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="full-width">
                                <label class="top-label" for="governmentOfficialSelect">Government Official <span class="required-asterisk">*</span></label>
                                <select name="government_official_id" id="governmentOfficialSelect" disabled onchange="window.toggleIndigencyGovernmentOfficialOther && window.toggleIndigencyGovernmentOfficialOther(this.value)">
                                    <option value="">Select official</option>
                                    <?php foreach ($governmentOfficials as $official):
                                        $positionName = trim((string)($official['position_name'] ?? ''));
                                        $jurisdictionName = trim((string)($official['jurisdiction_location'] ?? ''));
                                        $category = trim((string)($official['group_key'] ?? 'municipal_officials'));
                                        $label = implode(' - ', array_filter([
                                            (string)($official['name'] ?? ''),
                                            $positionName,
                                            $jurisdictionName,
                                        ], static fn($value) => trim((string)$value) !== ''));
                                    ?>
                                        <option value="<?= htmlspecialchars((string)$official['id'], ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__">Other</option>
                                </select>
                                <input type="text" name="government_official_other" id="governmentOfficialOther" class="d-none mt-2" placeholder="Enter official name">
                                <div class="small text-muted mt-2" id="governmentOfficialEmptyState">Choose an office group first.</div>
                            </div>
                        </div>

                        <div class="form-row form-row--triple d-none" id="institutionRow">
                            <div>
                                <label class="top-label" for="institutionName">Institution Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="institution_name" id="institutionName" placeholder="Enter institution name">
                            </div>
                            <div>
                                <label class="top-label" for="institutionPerson">Person to address</label>
                                <input type="text" name="institution_person" id="institutionPerson" placeholder="Optional">
                            </div>
                            <div>
                                <label class="top-label" for="institutionPosition">Position</label>
                                <input type="text" name="institution_position" id="institutionPosition" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Full Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address" readonly value="<?php echo $fullAddress; ?>">
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementIndigency">
                                <input type="checkbox" id="agreementIndigency" required>
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>

                            <button type="submit" class="submit-btn" disabled>SUBMIT</button>
                        </div>

                    </form>
        </main>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.INDIGENCY_GOVERNMENT_DIRECTORY = <?= json_encode([
            'officials' => $governmentOfficials,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/Certificates/indigencyFormScript.js?v=20260307-14"></script>
    <script>
        window.toggleIndigencyRecipientRows = function (value) {
            var governmentRow = document.getElementById('governmentOfficialRow');
            var institutionRow = document.getElementById('institutionRow');
            if (governmentRow) {
                governmentRow.classList.toggle('d-none', value !== 'government_official');
            }
            if (institutionRow) {
                institutionRow.classList.toggle('d-none', value !== 'institution');
            }
        };
        window.filterIndigencyGovernmentOfficials = function (groupValue) {
            var officialSelect = document.getElementById('governmentOfficialSelect');
            var officialOther = document.getElementById('governmentOfficialOther');
            var officeOther = document.getElementById('governmentPositionOther');
            var positionDetail = document.getElementById('governmentPositionDetail');
            var emptyState = document.getElementById('governmentOfficialEmptyState');
            if (!officialSelect) return;

            var normalized = String(groupValue || '').trim();
            if (officeOther) {
                var isOtherOffice = normalized === '__other__';
                officeOther.classList.toggle('d-none', !isOtherOffice);
                officeOther.required = isOtherOffice;
                if (!isOtherOffice) {
                    officeOther.value = '';
                }
            }
            var hasVisible = false;
            Array.prototype.forEach.call(officialSelect.options, function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                var value = String(option.value || '').trim();
                if (value === '__other__') {
                    option.hidden = !normalized;
                    if (normalized) hasVisible = true;
                    return;
                }
                var category = String(option.getAttribute('data-category') || '').trim();
                var visible = !!normalized && category === normalized;
                option.hidden = !visible;
                if (visible) hasVisible = true;
            });

            officialSelect.disabled = !normalized;
            if (!normalized) {
                officialSelect.value = '';
                if (officialOther) {
                    officialOther.classList.add('d-none');
                    officialOther.required = false;
                    officialOther.value = '';
                }
                if (positionDetail) {
                    positionDetail.value = '';
                }
                if (emptyState) {
                    emptyState.textContent = 'Choose an office group first.';
                    emptyState.classList.remove('d-none');
                }
                return;
            }

            if (normalized === '__other__' && officialOther) {
                officialSelect.value = '__other__';
                officialOther.classList.remove('d-none');
                officialOther.required = true;
                if (positionDetail) {
                    positionDetail.value = '';
                }
            }

            if (officialSelect.value && officialSelect.selectedOptions.length && officialSelect.selectedOptions[0].hidden) {
                officialSelect.value = '';
            }
            if (window.toggleIndigencyGovernmentOfficialOther) {
                window.toggleIndigencyGovernmentOfficialOther(officialSelect.value);
            }
            if (emptyState) {
                emptyState.textContent = hasVisible ? '' : 'No official is configured for that office group yet.';
                emptyState.classList.toggle('d-none', hasVisible);
            }
        };
        window.toggleIndigencyGovernmentOfficialOther = function (value) {
            var officialOther = document.getElementById('governmentOfficialOther');
            if (!officialOther) return;
            var isOther = String(value || '').trim() === '__other__';
            officialOther.classList.toggle('d-none', !isOther);
            officialOther.required = isOther;
            if (!isOther) {
                officialOther.value = '';
            }
        };
        window.addEventListener('DOMContentLoaded', function () {
            var select = document.getElementById('submissionTargetType');
            var governmentGroup = document.getElementById('governmentPositionSelect');
            var governmentOfficial = document.getElementById('governmentOfficialSelect');
            if (select && window.toggleIndigencyRecipientRows) {
                window.toggleIndigencyRecipientRows(select.value);
            }
            if (governmentGroup && window.filterIndigencyGovernmentOfficials) {
                window.filterIndigencyGovernmentOfficials(governmentGroup.value);
            }
            if (governmentOfficial && window.toggleIndigencyGovernmentOfficialOther) {
                window.toggleIndigencyGovernmentOfficialOther(governmentOfficial.value);
            }
        });
    </script>
</body>

</html>
