<?php
$reportDepartmentLabels = [
    'certificate_issuance' => 'Certificate Issuance',
    'clearance_issuance' => 'Clearance Issuance',
    'financial' => 'Financial & Collections',
    'residents' => 'Residents',
    'blotter' => 'Blotter & Cases',
    'complaints' => 'Complaints & Grievances',
];
$departmentLabel = $reportDepartmentLabels[$module] ?? 'Department';
$categoryUrl = $baseUrl . '?module=' . rawurlencode($module);
$settingsUrl = $categoryUrl . '&report=signatory_settings';

$conn->query("CREATE TABLE IF NOT EXISTS report_signatory_settings (
    report_module VARCHAR(64) NOT NULL PRIMARY KEY,
    signatory_one_name VARCHAR(180) NULL,
    signatory_one_position VARCHAR(180) NULL,
    signatory_two_name VARCHAR(180) NULL,
    signatory_two_position VARCHAR(180) NULL,
    updated_by VARCHAR(64) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

foreach ([
    'signatory_one_name' => 'VARCHAR(180) NULL',
    'signatory_one_position' => 'VARCHAR(180) NULL',
    'signatory_two_name' => 'VARCHAR(180) NULL',
    'signatory_two_position' => 'VARCHAR(180) NULL',
] as $column => $definition) {
    if (!rp_column_exists($conn, 'report_signatory_settings', $column)) {
        $conn->query("ALTER TABLE report_signatory_settings ADD COLUMN {$column} {$definition}");
    }
}

$values = [
    'signatory_one_name' => '',
    'signatory_one_position' => 'Prepared by',
    'signatory_two_name' => '',
    'signatory_two_position' => 'Punong Barangay',
];
$load = $conn->prepare('SELECT signatory_one_name, signatory_one_position, signatory_two_name, signatory_two_position FROM report_signatory_settings WHERE report_module=? LIMIT 1');
if ($load) {
    $load->bind_param('s', $module);
    $load->execute();
    $row = $load->get_result()->fetch_assoc();
    if ($row) {
        foreach ($values as $key => $fallback) {
            $values[$key] = trim((string)($row[$key] ?? ''));
        }
    }
    $load->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (in_array('', $values, true)) {
        $settingsError = 'Enter the name and position of both signatories.';
    } else {
        $save = $conn->prepare("INSERT INTO report_signatory_settings
            (report_module, signatory_one_name, signatory_one_position, signatory_two_name, signatory_two_position, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                signatory_one_name=VALUES(signatory_one_name),
                signatory_one_position=VALUES(signatory_one_position),
                signatory_two_name=VALUES(signatory_two_name),
                signatory_two_position=VALUES(signatory_two_position),
                updated_by=VALUES(updated_by)");
        if ($save) {
            $updatedBy = (string)($_SESSION['user_id'] ?? '');
            $save->bind_param('ssssss', $module, $values['signatory_one_name'], $values['signatory_one_position'], $values['signatory_two_name'], $values['signatory_two_position'], $updatedBy);
            if ($save->execute()) {
                $save->close();
                header('Location: ' . $settingsUrl . '&saved=1');
                exit;
            }
            $save->close();
        }
        $settingsError = 'Unable to save the report signatories. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($departmentLabel) ?> Report Signatories</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <style>
    .rss-main{min-width:0;overflow-x:hidden}.rss-title{font-family:'Charis SIL Bold',Georgia,serif;color:#de710c}.rss-panel{border:1px solid #dee2e6;border-radius:1rem;background:#fff;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);padding:1.25rem}.rss-panel-head{padding-bottom:.9rem;margin-bottom:1.25rem;border-bottom:1px solid #edf0f3}.rss-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.rss-card{border:1px solid #e4e8ed;border-radius:.8rem;background:#fffaf4;padding:1rem}.rss-card-title{font-size:1rem;font-weight:700;color:#212529}.rss-back{color:#6c757d;text-decoration:none;font-weight:600;font-size:.9rem}.rss-back:hover{color:#de710c}@media(max-width:767.98px){.rss-grid{grid-template-columns:1fr}}@media(max-width:575.98px){.rss-panel{padding:.85rem}}
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="rss-main flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Report Signatory Settings</h2>
      <a class="rss-back" href="<?= htmlspecialchars($categoryUrl) ?>"><i class="fas fa-arrow-left me-2"></i>Back to report options</a>
    </div>
    <hr><br>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Report signatories saved successfully.</div><?php endif; ?>
    <?php if (!empty($settingsError)): ?><div class="alert alert-danger"><?= htmlspecialchars($settingsError) ?></div><?php endif; ?>
    <section class="rss-panel">
      <div class="rss-panel-head"><h2 class="h6 fw-bold mb-1">Generated Report Signatories</h2><p class="text-muted small mb-0">These two names and positions appear in the signature area at the bottom of every generated report in this category.</p></div>
      <form method="POST" action="<?= htmlspecialchars($settingsUrl) ?>">
        <?= csrfTokenField() ?>
        <div class="rss-grid">
          <?php foreach ([1 => ['one', 'Signatory 1'], 2 => ['two', 'Signatory 2']] as [$key, $label]): ?>
          <fieldset class="rss-card">
            <legend class="rss-card-title mb-3"><?= htmlspecialchars($label) ?></legend>
            <div class="mb-3"><label class="form-label fw-semibold" for="signatory_<?= $key ?>_name">Name</label><input class="form-control" id="signatory_<?= $key ?>_name" name="signatory_<?= $key ?>_name" maxlength="180" value="<?= htmlspecialchars($values['signatory_' . $key . '_name']) ?>" required></div>
            <div><label class="form-label fw-semibold" for="signatory_<?= $key ?>_position">Position</label><input class="form-control" id="signatory_<?= $key ?>_position" name="signatory_<?= $key ?>_position" maxlength="180" value="<?= htmlspecialchars($values['signatory_' . $key . '_position']) ?>" required></div>
          </fieldset>
          <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($categoryUrl) ?>">Cancel</a><button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Save Signatories</button></div>
      </form>
    </section>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
