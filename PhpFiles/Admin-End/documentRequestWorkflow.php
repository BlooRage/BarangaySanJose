<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], true);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

// NOTE: avoid expensive schema/backfill maintenance on hot request paths.
// Run maintenance manually when needed:
//   /PhpFiles/Admin-End/documentRequestWorkflow.php?action=maintenance_run
if ($action === 'maintenance_run') {
    dr_ensure_table($conn);
    dr_ensure_general_fees_table($conn);
    $syncedFinance = dr_backfill_missing_finance_transactions($conn, 2000);
    $prunedFree = dr_prune_free_document_finance_transactions($conn, 5000);
    $syncedIssuance = dr_backfill_missing_issuance_requests($conn, 5000);
    dr_respond_json(200, [
        'success' => true,
        'maintenance' => [
            'finance_backfilled' => $syncedFinance,
            'free_pruned' => $prunedFree,
            'issuance_backfilled' => $syncedIssuance,
        ],
    ]);
}

if ($action === 'optimize_indexes') {
    dra_ensure_list_hotpath_indexes($conn);
    dr_respond_json(200, ['success' => true, 'message' => 'List indexes checked/applied.']);
}

$currentUserId = (string)($_SESSION['user_id'] ?? '');

function dra_is_finance_user(mysqli $conn, string $userId): bool {
    $userId = trim($userId);
    if ($userId === '' || !dr_table_exists($conn, 'officialinformationtbl')) {
        return false;
    }
    $hasPositionAccess = dr_column_exists($conn, 'officialinformationtbl', 'position_access');
    $sql = $hasPositionAccess
        ? "SELECT role_access, position_access, department FROM officialinformationtbl WHERE user_id = ? LIMIT 1"
        : "SELECT role_access, NULL AS position_access, department FROM officialinformationtbl WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    $role = strtolower(trim((string)($row['role_access'] ?? '')));
    $position = strtolower(trim((string)($row['position_access'] ?? '')));
    $department = strtolower(trim((string)($row['department'] ?? '')));

    return (
        strpos($department, 'finance') !== false
        || strpos($position, 'cashier') !== false
        || strpos($position, 'finance') !== false
        || ($role === 'employee' && strpos($department, 'finance') !== false)
    );
}

function dra_send_notification_deferred(mysqli $conn, array $request, string $subject, string $message): void {
    register_shutdown_function(static function () use ($conn, $request, $subject, $message): void {
        // Flush response first when supported, then send notifications outside request hot path.
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            dr_send_notification($conn, $request, $subject, $message);
        } catch (Throwable $e) {
            error_log('[documentRequestWorkflow][notification] deferred send failed: ' . $e->getMessage());
        }
    });
}

function dra_strip_unreplaced_docx_qr_placeholder(string $docxDiskPath): void {
    if (!is_file($docxDiskPath) || !class_exists('ZipArchive')) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxDiskPath) !== true) {
        return;
    }

    $xmlParts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = (string)$zip->getNameIndex($i);
        if ($entryName !== '' && preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $entryName)) {
            $xmlParts[] = $entryName;
        }
    }

    foreach ($xmlParts as $xmlPart) {
        $xml = $zip->getFromName($xmlPart);
        if (!is_string($xml) || $xml === '') {
            continue;
        }

        $updatedXml = str_replace('${QR_IMAGE}', '', $xml);
        $preserveLetterhead = stripos($xmlPart, 'header') !== false
            && (
                stripos($xml, 'REPUBLIKA NG PILIPINAS') !== false
                || stripos($xml, 'BARANGAY SAN JOSE') !== false
            );
        $updatedXml = preg_replace(
            '/<w:r\b[^>]*>.*?<w:t[^>]*>\$<\/w:t>.*?<\/w:r>\s*<w:r\b[^>]*>.*?<w:t[^>]*>\{QR_IMAGE\}<\/w:t>.*?<\/w:r>/s',
            '',
            $updatedXml
        );
        $updatedXml = preg_replace(
            '/<w:p\b[^>]*>\s*(?:<w:pPr\b[^>]*>.*?<\/w:pPr>\s*)?<w:r\b[^>]*>\s*(?:<w:rPr\b[^>]*>.*?<\/w:rPr>\s*)?<w:pict\b[^>]*>\s*<v:rect\b[^>]*\/>\s*<\/w:pict>\s*<\/w:r>\s*<\/w:p>/s',
            '',
            $updatedXml
        );
        $updatedXml = preg_replace(
            '/<mc:AlternateContent\b[^>]*>.*?Text Box 1.*?<w:t>\$<\/w:t>.*?<w:t>\{QR_IMAGE\}<\/w:t>.*?<\/mc:AlternateContent>/s',
            '',
            $updatedXml
        );
        $updatedXml = preg_replace(
            '/<w:pict\b[^>]*>.*?<v:shape\b[^>]*id="Text Box 1"[^>]*>.*?<w:t>\$<\/w:t>.*?<w:t>\{QR_IMAGE\}<\/w:t>.*?<\/v:shape>.*?<\/w:pict>/s',
            '',
            $updatedXml
        );
        if (!$preserveLetterhead) {
            $updatedXml = preg_replace(
                '/<w:pict\b[^>]*>\s*<v:rect\b[^>]*\bo:hr="t"[^>]*\/>\s*<\/w:pict>/s',
                '',
                $updatedXml
            );
            $updatedXml = preg_replace(
                '/<w:r\b[^>]*>\s*(?:<w:rPr\b[^>]*>.*?<\/w:rPr>\s*)?<w:pict\b[^>]*>\s*<v:rect\b[^>]*\bo:hr="t"[^>]*\/>\s*<\/w:pict>\s*<\/w:r>/s',
                '',
                $updatedXml
            );
            $updatedXml = preg_replace(
                '/<w:p\b[^>]*>\s*(?:<w:pPr\b[^>]*>.*?<\/w:pPr>\s*)?<w:r\b[^>]*>\s*(?:<w:rPr\b[^>]*>.*?<\/w:rPr>\s*)?<w:pict\b[^>]*>\s*<v:rect\b[^>]*\bo:hr="t"[^>]*\/>\s*<\/w:pict>\s*<\/w:r>\s*<\/w:p>/s',
                '',
                $updatedXml
            );
        }

        if (is_string($updatedXml) && $updatedXml !== $xml) {
            $zip->addFromString($xmlPart, $updatedXml);
        }
    }

    $zip->close();
}

function dra_restore_template_headers(string $templatePath, string $docxDiskPath): void {
    if (!is_file($templatePath) || !is_file($docxDiskPath) || !class_exists('ZipArchive')) {
        return;
    }

    $templateZip = new ZipArchive();
    if ($templateZip->open($templatePath) !== true) {
        return;
    }

    $docxZip = new ZipArchive();
    if ($docxZip->open($docxDiskPath) !== true) {
        $templateZip->close();
        return;
    }

    try {
        $templateDocumentXml = $templateZip->getFromName('word/document.xml');
        $templateRelsXml = $templateZip->getFromName('word/_rels/document.xml.rels');
        $docxDocumentXml = $docxZip->getFromName('word/document.xml');
        $docxRelsXml = $docxZip->getFromName('word/_rels/document.xml.rels');

        if (!is_string($templateDocumentXml) || !is_string($templateRelsXml) || !is_string($docxDocumentXml) || !is_string($docxRelsXml)) {
            return;
        }

        $templateDocument = new DOMDocument();
        $templateRelationships = new DOMDocument();
        $docxDocument = new DOMDocument();
        $docxRelationships = new DOMDocument();

        if (
            !$templateDocument->loadXML($templateDocumentXml)
            || !$templateRelationships->loadXML($templateRelsXml)
            || !$docxDocument->loadXML($docxDocumentXml)
            || !$docxRelationships->loadXML($docxRelsXml)
        ) {
            return;
        }

        $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $relsNamespace = 'http://schemas.openxmlformats.org/package/2006/relationships';

        $templateDocumentXpath = new DOMXPath($templateDocument);
        $templateDocumentXpath->registerNamespace('w', $wordNamespace);
        $templateRelationshipsXpath = new DOMXPath($templateRelationships);
        $templateRelationshipsXpath->registerNamespace('rels', $relsNamespace);
        $docxDocumentXpath = new DOMXPath($docxDocument);
        $docxDocumentXpath->registerNamespace('w', $wordNamespace);
        $docxRelationshipsXpath = new DOMXPath($docxRelationships);
        $docxRelationshipsXpath->registerNamespace('rels', $relsNamespace);

        $templateHeaderReferences = [];
        foreach ($templateDocumentXpath->query('//w:sectPr/w:headerReference') ?: [] as $headerReference) {
            if (!$headerReference instanceof DOMElement) {
                continue;
            }
            $templateHeaderReferences[] = [
                'type' => $headerReference->getAttributeNS($wordNamespace, 'type'),
                'rid' => $headerReference->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id'),
            ];
        }
        if ($templateHeaderReferences === []) {
            return;
        }

        $templateHeaderRelationships = [];
        $templateHeaderParts = [];
        $templateHeaderRelsParts = [];
        $templateHeaderMediaParts = [];
        foreach ($templateRelationshipsXpath->query('/rels:Relationships/rels:Relationship[contains(@Type, "/header")]') ?: [] as $relationshipNode) {
            if (!$relationshipNode instanceof DOMElement) {
                continue;
            }
            $id = $relationshipNode->getAttribute('Id');
            $target = $relationshipNode->getAttribute('Target');
            if ($id === '' || $target === '') {
                continue;
            }
            $templateHeaderRelationships[$id] = $target;
            $targetPart = 'word/' . ltrim($target, '/');
            $templateHeaderParts[] = $targetPart;
            $targetXml = $templateZip->getFromName($targetPart);
            if (is_string($targetXml) && $targetXml !== '') {
                $docxZip->deleteName($targetPart);
                $docxZip->addFromString($targetPart, $targetXml);
            }

            $targetRelsPart = 'word/_rels/' . basename($target) . '.rels';
            $templateHeaderRelsParts[] = $targetRelsPart;
            $targetRelsXml = $templateZip->getFromName($targetRelsPart);
            if (is_string($targetRelsXml) && $targetRelsXml !== '') {
                $docxZip->deleteName($targetRelsPart);
                $docxZip->addFromString($targetRelsPart, $targetRelsXml);

                $headerRelsDocument = new DOMDocument();
                if ($headerRelsDocument->loadXML($targetRelsXml)) {
                    $headerRelsXpath = new DOMXPath($headerRelsDocument);
                    $headerRelsXpath->registerNamespace('rels', $relsNamespace);
                    foreach ($headerRelsXpath->query('/rels:Relationships/rels:Relationship') ?: [] as $headerAssetNode) {
                        if (!$headerAssetNode instanceof DOMElement) {
                            continue;
                        }
                        $assetTarget = $headerAssetNode->getAttribute('Target');
                        if ($assetTarget === '' || preg_match('#^(?:https?:|/)#i', $assetTarget)) {
                            continue;
                        }
                        $assetPart = 'word/' . ltrim($assetTarget, '/');
                        $assetContents = $templateZip->getFromName($assetPart);
                        if (is_string($assetContents) && $assetContents !== '') {
                            $templateHeaderMediaParts[] = $assetPart;
                            $docxZip->deleteName($assetPart);
                            $docxZip->addFromString($assetPart, $assetContents);
                        }
                    }
                }
            }
        }

        if ($templateHeaderRelationships === []) {
            return;
        }

        foreach (array_unique($templateHeaderParts) as $headerPart) {
            $headerXml = $templateZip->getFromName($headerPart);
            if (is_string($headerXml) && $headerXml !== '') {
                $docxZip->deleteName($headerPart);
                $docxZip->addFromString($headerPart, $headerXml);
            }
        }
        foreach (array_unique($templateHeaderRelsParts) as $headerRelsPart) {
            $headerRelsXml = $templateZip->getFromName($headerRelsPart);
            if (is_string($headerRelsXml) && $headerRelsXml !== '') {
                $docxZip->deleteName($headerRelsPart);
                $docxZip->addFromString($headerRelsPart, $headerRelsXml);
            }
        }
        foreach (array_unique($templateHeaderMediaParts) as $mediaPart) {
            $mediaContents = $templateZip->getFromName($mediaPart);
            if (is_string($mediaContents) && $mediaContents !== '') {
                $docxZip->deleteName($mediaPart);
                $docxZip->addFromString($mediaPart, $mediaContents);
            }
        }

        foreach ($docxDocumentXpath->query('//w:sectPr') ?: [] as $sectionProperties) {
            if (!$sectionProperties instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array($sectionProperties->childNodes) as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->namespaceURI === $wordNamespace && $childNode->localName === 'headerReference') {
                    $sectionProperties->removeChild($childNode);
                }
            }

            $insertBefore = null;
            foreach ($sectionProperties->childNodes as $childNode) {
                if ($childNode instanceof DOMElement) {
                    $insertBefore = $childNode;
                    break;
                }
            }

            foreach ($templateHeaderReferences as $headerReference) {
                $newHeaderReference = $docxDocument->createElementNS($wordNamespace, 'w:headerReference');
                if ($headerReference['type'] !== '') {
                    $newHeaderReference->setAttributeNS($wordNamespace, 'w:type', $headerReference['type']);
                }
                $newHeaderReference->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $headerReference['rid']);
                if ($insertBefore instanceof DOMNode) {
                    $sectionProperties->insertBefore($newHeaderReference, $insertBefore);
                } else {
                    $sectionProperties->appendChild($newHeaderReference);
                }
            }
        }

        foreach ($docxRelationshipsXpath->query('/rels:Relationships/rels:Relationship[contains(@Type, "/header")]') ?: [] as $relationshipNode) {
            if ($relationshipNode instanceof DOMElement && $relationshipNode->parentNode instanceof DOMNode) {
                $relationshipNode->parentNode->removeChild($relationshipNode);
            }
        }

        $relationshipsRoot = $docxRelationships->documentElement;
        if (!$relationshipsRoot instanceof DOMElement) {
            return;
        }

        foreach ($templateRelationshipsXpath->query('/rels:Relationships/rels:Relationship[contains(@Type, "/header")]') ?: [] as $relationshipNode) {
            if (!$relationshipNode instanceof DOMElement) {
                continue;
            }
            $relationshipsRoot->appendChild($docxRelationships->importNode($relationshipNode, true));
        }

        $docxZip->addFromString('word/document.xml', $docxDocument->saveXML());
        $docxZip->addFromString('word/_rels/document.xml.rels', $docxRelationships->saveXML());
    } finally {
        $docxZip->close();
        $templateZip->close();
    }
}

function dra_force_docx_placeholder_replacements(string $docxDiskPath, array $replacements): void {
    if (!is_file($docxDiskPath) || !class_exists('ZipArchive') || empty($replacements)) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxDiskPath) !== true) {
        return;
    }

    for ($i = 0; $i < $zip->numFiles; $i += 1) {
        $entryName = (string)$zip->getNameIndex($i);
        if ($entryName === '' || !preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $entryName)) {
            continue;
        }

        $xml = $zip->getFromName($entryName);
        if (!is_string($xml) || $xml === '') {
            continue;
        }

        $updatedXml = $xml;
        foreach ($replacements as $key => $value) {
            $placeholder = '${' . trim((string)$key) . '}';
            $stringValue = trim((string)$value);
            $escapedValue = '';
            if ($stringValue !== '') {
                $parts = preg_split("/\r\n|\n|\r/", $stringValue) ?: [$stringValue];
                $escapedParts = array_map(
                    static fn($part) => htmlspecialchars((string)$part, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
                    $parts
                );
                $escapedValue = implode('</w:t><w:br/><w:t>', $escapedParts);
            }
            $updatedXml = str_replace($placeholder, $escapedValue, $updatedXml);
        }

        if ($updatedXml !== $xml) {
            $zip->addFromString($entryName, $updatedXml);
        }
    }

    $zip->close();
}

function dra_extract_docx_media_asset(string $templatePath, string $mediaName): ?string {
    $templateReal = realpath($templatePath);
    if ($templateReal === false || !is_file($templateReal) || !class_exists('ZipArchive')) {
        return null;
    }

    $tmpDir = dirname(__DIR__, 2) . '/UnifiedFileAttachment/IssuedDocuments/Tmp/template_assets';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0775, true);
    }
    if (!is_dir($tmpDir)) {
        return null;
    }

    $extension = strtolower(pathinfo($mediaName, PATHINFO_EXTENSION));
    $cachePath = $tmpDir . '/' . sha1($templateReal . '|' . $mediaName) . ($extension !== '' ? ('.' . $extension) : '');
    if (is_file($cachePath) && filesize($cachePath) > 0) {
        return $cachePath;
    }

    $zip = new ZipArchive();
    if ($zip->open($templateReal) !== true) {
        return null;
    }
    $assetContents = $zip->getFromName('word/media/' . ltrim($mediaName, '/'));
    $zip->close();
    if (!is_string($assetContents) || $assetContents === '') {
        return null;
    }
    if (@file_put_contents($cachePath, $assetContents) === false) {
        return null;
    }
    return is_file($cachePath) ? $cachePath : null;
}

function dra_ensure_list_hotpath_indexes(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (dr_table_exists($conn, 'documentrequesttbl')) {
        $idxNames = [];
        $res = $conn->query("SHOW INDEX FROM documentrequesttbl");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idxNames[(string)($row['Key_name'] ?? '')] = true;
            }
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') && !isset($idxNames['idx_docreq_submitted_at'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_submitted_at (submitted_at)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'stage')
            && dr_column_exists($conn, 'documentrequesttbl', 'submitted_at')
            && !isset($idxNames['idx_docreq_stage_submitted_at'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_submitted_at (stage, submitted_at)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp') && !isset($idxNames['idx_docreq_request_timestamp'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_request_timestamp (request_timestamp)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'stage')
            && dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
            && !isset($idxNames['idx_docreq_stage_request_timestamp'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_request_timestamp (stage, request_timestamp)");
        }
    }

    if (dr_table_exists($conn, 'financetransactiontbl')) {
        $hasRequestIndex = false;
        $res = $conn->query("SHOW INDEX FROM financetransactiontbl");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                if (strcasecmp((string)($row['Column_name'] ?? ''), 'request_id') === 0) {
                    $hasRequestIndex = true;
                    break;
                }
            }
        }
        if (!$hasRequestIndex && dr_column_exists($conn, 'financetransactiontbl', 'request_id')) {
            $conn->query("ALTER TABLE financetransactiontbl ADD INDEX idx_transaction_request_id (request_id)");
        }
    }
}

function dra_get_fee_map_for_document_types(mysqli $conn, array $documentTypes): array {
    $out = [];
    $names = [];
    foreach ($documentTypes as $docType) {
        $doc = trim((string)$docType);
        if ($doc === '' || !dr_is_issuance_document_type($doc)) {
            continue;
        }
        $names[$doc] = true;
    }
    if (!$names || !dr_table_exists($conn, 'documenttypelookuptbl') || !dr_table_exists($conn, 'generalfeestbl')) {
        return $out;
    }

    $nameList = array_keys($names);
    $placeholders = implode(',', array_fill(0, count($nameList), '?'));
    $sql = "
        SELECT dt.document_type_name, gf.amount
        FROM documenttypelookuptbl dt
        LEFT JOIN generalfeestbl gf ON gf.document_type_id = dt.document_type_id
        WHERE dt.document_type_name IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $types = str_repeat('s', count($nameList));
    $bindArgs = [$types];
    foreach ($nameList as $i => $value) {
        $bindArgs[] = &$nameList[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $name = trim((string)($row['document_type_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $amount = $row['amount'];
        $out[$name] = ($amount === null) ? null : (float)$amount;
    }
    $stmt->close();
    return $out;
}

function dra_select_or_null(mysqli $conn, string $table, string $column, string $alias): string {
    if (dr_column_exists($conn, $table, $column)) {
        return "d.{$column} AS {$alias}";
    }
    return "NULL AS {$alias}";
}

function dra_fetch_request_for_modal_fast(mysqli $conn, string $requestId): ?array {
    $requestId = trim($requestId);
    if ($requestId === '') {
        return null;
    }

    $baseSelects = [
        "d.request_id AS request_id",
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_user_id', 'resident_user_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_id', 'resident_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_name', 'resident_name'),
        dra_select_or_null($conn, 'documentrequesttbl', 'document_type', 'document_type'),
        dra_select_or_null($conn, 'documentrequesttbl', 'purpose', 'purpose'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_remarks', 'status_remarks'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_reason', 'status_reason'),
        dra_select_or_null($conn, 'documentrequesttbl', 'stage', 'stage'),
        dra_select_or_null($conn, 'documentrequesttbl', 'submitted_at', 'submitted_at'),
        dra_select_or_null($conn, 'documentrequesttbl', 'request_timestamp', 'request_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'certificate_number', 'certificate_number'),
        dra_select_or_null($conn, 'documentrequesttbl', 'verification_code', 'verification_code'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_reviewed_by', 'user_id_official_reviewed_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_released_by', 'user_id_official_released_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'review_timestamp', 'review_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'release_timestamp', 'release_timestamp'),
        dr_column_exists($conn, 'documentrequesttbl', 'request_details') ? "d.request_details AS request_details" : "NULL AS request_details",
    ];
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
        $baseSelects[] = "d.status_id_request AS status_id_request";
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        $baseSelects[] = "d.status_id AS status_id";
    }

    $extraSelects = [];
    $extraJoins = [];
    if (dr_table_exists($conn, 'issuancerequesttbl')) {
        $extraSelects[] = "i.certificate_type AS _issuance_certificate_type";
        $extraSelects[] = "i.certificate_number AS _issuance_certificate_number";
        $extraSelects[] = "i.verification_code AS _issuance_verification_code";
        $extraJoins[] = "LEFT JOIN issuancerequesttbl i ON i.request_id = d.request_id";
    }
    if (dr_table_exists($conn, 'officialinformationtbl')) {
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oir.firstname, ''), NULLIF(oir.middlename, ''), NULLIF(oir.lastname, ''), NULLIF(oir.suffix, ''))) AS _reviewed_by_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oil.firstname, ''), NULLIF(oil.middlename, ''), NULLIF(oil.lastname, ''), NULLIF(oil.suffix, ''))) AS _released_by_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oip.firstname, ''), NULLIF(oip.middlename, ''), NULLIF(oip.lastname, ''), NULLIF(oip.suffix, ''))) AS _personnel_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oif.firstname, ''), NULLIF(oif.middlename, ''), NULLIF(oif.lastname, ''), NULLIF(oif.suffix, ''))) AS _finance_user_name";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oir ON oir.user_id = d.user_id_official_reviewed_by";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oil ON oil.user_id = d.user_id_official_released_by";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oip ON oip.user_id = d.personnel_user_id";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oif ON oif.user_id = f.user_id_employee_process";
    }

    $sql = "
        SELECT
            " . implode(",\n            ", $baseSelects) . ",
            f.transaction_amount AS _tx_amount,
            f.payment_method AS _tx_payment_method,
            f.payment_proof_path AS _tx_payment_proof_path,
            f.transaction_details AS _tx_transaction_details,
            f.or_number AS _tx_or_number,
            f.transaction_status_id AS _tx_status_id,
            s.status_name AS _tx_status_name,
            f.payment_deadline AS _tx_payment_deadline,
            f.payment_timestamp AS _tx_payment_timestamp,
            f.finance_decision_at AS _tx_finance_decision_at,
            f.user_id_employee_process AS _tx_finance_user_id
            " . ($extraSelects ? ",\n            " . implode(",\n            ", $extraSelects) : "") . "
        FROM documentrequesttbl d
        LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
        LEFT JOIN statuslookuptbl s ON s.status_id = f.transaction_status_id
        " . ($extraJoins ? "\n        " . implode("\n        ", $extraJoins) : "") . "
        WHERE d.request_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }

    $docType = trim((string)($row['document_type'] ?? ''));
    if ($docType === '') {
        $docType = trim((string)($row['_issuance_certificate_type'] ?? ''));
        if ($docType !== '') {
            $row['document_type'] = $docType;
        }
    }
    if (trim((string)($row['certificate_number'] ?? '')) === '') {
        $issuedCertNo = trim((string)($row['_issuance_certificate_number'] ?? ''));
        if ($issuedCertNo !== '') {
            $row['certificate_number'] = $issuedCertNo;
        }
    }
    if (trim((string)($row['verification_code'] ?? '')) === '') {
        $issuedVc = trim((string)($row['_issuance_verification_code'] ?? ''));
        if ($issuedVc !== '') {
            $row['verification_code'] = $issuedVc;
        }
    }
    if (trim((string)($row['submitted_at'] ?? '')) === '' && trim((string)($row['request_timestamp'] ?? '')) !== '') {
        $row['submitted_at'] = (string)$row['request_timestamp'];
    }

    $row['reviewed_by'] = trim((string)($row['_reviewed_by_name'] ?? ''));
    $row['released_by'] = trim((string)($row['_released_by_name'] ?? ''));
    $row['personnel_name'] = trim((string)($row['_personnel_name'] ?? ''));
    $row['finance_user_name'] = trim((string)($row['_finance_user_name'] ?? ''));
    $row['amount'] = isset($row['_tx_amount']) ? (float)$row['_tx_amount'] : null;
    $row['payment_method'] = (string)($row['_tx_payment_method'] ?? '');
    $row['payment_proof_path'] = (string)($row['_tx_payment_proof_path'] ?? '');
    $row['or_number'] = (string)($row['_tx_or_number'] ?? '');
    $row['payment_status_id'] = isset($row['_tx_status_id']) ? (int)$row['_tx_status_id'] : 0;
    $row['payment_status_name'] = (string)($row['_tx_status_name'] ?? '');
    $row['payment_deadline'] = (string)($row['_tx_payment_deadline'] ?? '');
    $row['payment_submitted_at'] = (string)($row['_tx_payment_timestamp'] ?? '');
    $row['finance_decision_at'] = (string)($row['_tx_finance_decision_at'] ?? '');
    $row['finance_user_id'] = (string)($row['_tx_finance_user_id'] ?? '');

    $txDetails = (string)($row['_tx_transaction_details'] ?? '');
    if ($txDetails !== '') {
        $decoded = json_decode($txDetails, true);
        if (is_array($decoded)) {
            $ref = trim((string)($decoded['reference'] ?? ''));
            if ($ref !== '') {
                $row['payment_reference'] = $ref;
            }
            if (trim((string)($row['purpose'] ?? '')) === '') {
                $purposeFromTx = trim((string)($decoded['purpose'] ?? ''));
                if ($purposeFromTx !== '') {
                    $row['purpose'] = $purposeFromTx;
                }
            }
        } elseif (preg_match('/\bReference:\s*(.+)$/mi', $txDetails, $m)) {
            $row['payment_reference'] = trim((string)($m[1] ?? ''));
        }
    }

    $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    if (trim((string)($row['purpose'] ?? '')) === '') {
        $payloadPurpose = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
        if ($payloadPurpose !== '') {
            $row['purpose'] = $payloadPurpose;
        }
    }
    if (trim((string)($row['resident_name'] ?? '')) === '') {
        $payloadResidentName = trim((string)($payload['resident_name'] ?? ''));
        if ($payloadResidentName !== '') {
            $row['resident_name'] = $payloadResidentName;
        }
    }

    unset(
        $row['_issuance_certificate_type'],
        $row['_issuance_certificate_number'],
        $row['_issuance_verification_code'],
        $row['_reviewed_by_name'],
        $row['_released_by_name'],
        $row['_personnel_name'],
        $row['_finance_user_name'],
        $row['_tx_amount'],
        $row['_tx_payment_method'],
        $row['_tx_payment_proof_path'],
        $row['_tx_transaction_details'],
        $row['_tx_or_number'],
        $row['_tx_status_id'],
        $row['_tx_status_name'],
        $row['_tx_payment_deadline'],
        $row['_tx_payment_timestamp'],
        $row['_tx_finance_decision_at'],
        $row['_tx_finance_user_id']
    );

    return $row;
}

function dra_strip_legacy_base(string $publicPath): string {
    $publicPath = trim($publicPath);
    if ($publicPath === '') {
        return '';
    }

    // Normalize slashes to support Windows-style stored paths.
    $publicPath = str_replace('\\', '/', $publicPath);

    // If a full URL is stored, keep only its path portion.
    if (preg_match('/^https?:\/\//i', $publicPath)) {
        $urlPath = parse_url($publicPath, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            $publicPath = $urlPath;
        }
    }

    // If an absolute filesystem path is stored, strip project root prefix.
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot !== false) {
        $projectRootNorm = str_replace('\\', '/', rtrim($projectRoot, '/'));
        if (strpos($publicPath, $projectRootNorm) === 0) {
            return substr($publicPath, strlen($projectRootNorm));
        }
    }

    $base = rtrim((string)appRootPath(), '/');
    if ($base !== '' && strpos($publicPath, $base) === 0) {
        return substr($publicPath, strlen($base));
    }
    $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
    if ($projectBase !== '' && strpos($publicPath, '/' . $projectBase) === 0) {
        return substr($publicPath, strlen('/' . $projectBase));
    }
    return $publicPath;
}

function dra_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function dra_format_full_name_from_payload(array $payload): string {
    $last = trim((string)($payload['last_name'] ?? $payload['lastname'] ?? ''));
    $first = trim((string)($payload['first_name'] ?? $payload['firstname'] ?? ''));
    $middle = trim((string)($payload['middle_name'] ?? $payload['middlename'] ?? ''));
    $mi = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';
    $parts = array_filter([$first, $mi, $last], fn($x) => trim((string)$x) !== '');
    return trim(implode(' ', $parts));
}

function dra_strip_area_from_address(string $address): string {
    $value = trim($address);
    if ($value === '') {
        return '';
    }

    // Remove ", Area X" or "Area X," fragments while keeping the rest intact.
    $value = preg_replace('/\s*,\s*Area\s+[A-Za-z0-9-]+\s*(?=,|$)/i', '', $value) ?? $value;
    $value = preg_replace('/(^|,\s*)Area\s+[A-Za-z0-9-]+\s*,\s*/i', '$1', $value) ?? $value;
    $value = preg_replace('/\s*,\s*San\s+Jose\s*,\s*Rodriguez\s*,\s*Rizal\s*$/i', '', $value) ?? $value;
    $value = preg_replace('/\s*,\s*Barangay\s+San\s+Jose\s*,\s*Rodriguez(?:\s*\(Montalban\))?\s*,\s*Rizal\s*$/i', '', $value) ?? $value;
    $value = preg_replace('/\s*,\s*Barangay\s+San\s+Jose\s*,\s*Montalban\s*,\s*Rizal\s*$/i', '', $value) ?? $value;
    $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;
    $value = trim($value, " \t\n\r\0\x0B,");

    return $value;
}

function dra_compose_barangay_address(string $address, string $locality = 'Barangay San Jose, Rodriguez, Rizal'): string {
    $suffix = trim($locality);
    $clean = dra_strip_area_from_address($address);
    $clean = trim((string)(preg_replace('/\s+/u', ' ', $clean) ?? $clean), " \t\n\r\0\x0B,");
    if ($clean === '' || $clean === '-') {
        return $suffix !== '' ? $suffix : '-';
    }
    return $suffix !== '' ? ($clean . ', ' . $suffix) : $clean;
}

function dra_join_address_parts(array $parts): string {
    $clean = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') {
            $clean[] = $value;
        }
    }
    return trim((string)(preg_replace('/\s+/', ' ', implode(', ', $clean)) ?? implode(', ', $clean)));
}

function dra_build_cohabitant_address(array $payload, string $fallback = ''): string {
    $direct = trim((string)($payload['cohabitant_full_address'] ?? $payload['cohabitant_full_address_display'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    $system = strtolower(trim((string)($payload['cohabitant_address_system'] ?? '')));
    if ($system === 'lot_block') {
        return dra_join_address_parts([
            trim((string)($payload['cohabitant_unit_number_lot'] ?? '')) !== '' ? 'Unit ' . trim((string)($payload['cohabitant_unit_number_lot'] ?? '')) : '',
            trim((string)($payload['cohabitant_lot_number'] ?? '')) !== '' ? 'Lot ' . trim((string)($payload['cohabitant_lot_number'] ?? '')) : '',
            trim((string)($payload['cohabitant_block_number'] ?? '')) !== '' ? 'Blk ' . trim((string)($payload['cohabitant_block_number'] ?? '')) : '',
            trim((string)($payload['cohabitant_phase_number'] ?? '')) !== '' ? 'Phase ' . trim((string)($payload['cohabitant_phase_number'] ?? '')) : '',
            (string)($payload['cohabitant_subdivision_lot'] ?? $payload['cohabitant_subdivision'] ?? ''),
            (string)($payload['cohabitant_barangay'] ?? ''),
            (string)($payload['cohabitant_city'] ?? ''),
            (string)($payload['cohabitant_province'] ?? ''),
        ]);
    }
    if ($system === 'house') {
        return dra_join_address_parts([
            trim((string)($payload['cohabitant_unit_number'] ?? '')) !== '' ? 'Unit ' . trim((string)($payload['cohabitant_unit_number'] ?? '')) : '',
            trim(implode(' ', array_filter([
                trim((string)($payload['cohabitant_house_number'] ?? '')),
                trim((string)($payload['cohabitant_street_name'] ?? '')),
            ], static fn($v) => $v !== ''))),
            (string)($payload['cohabitant_subdivision'] ?? ''),
            (string)($payload['cohabitant_barangay'] ?? ''),
            (string)($payload['cohabitant_city'] ?? ''),
            (string)($payload['cohabitant_province'] ?? ''),
        ]);
    }
    return $fallback;
}

function dra_build_cohabitation_address(array $payload, string $fallback = ''): string {
    $direct = trim((string)($payload['cohabitation_full_address'] ?? $payload['cohabitation_full_address_display'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    $system = strtolower(trim((string)($payload['cohabitation_address_system'] ?? '')));
    if ($system === 'lot_block') {
        return dra_join_address_parts([
            trim((string)($payload['cohabitation_unit_number_lot'] ?? '')) !== '' ? 'Unit ' . trim((string)($payload['cohabitation_unit_number_lot'] ?? '')) : '',
            trim((string)($payload['cohabitation_lot_number'] ?? '')) !== '' ? 'Lot ' . trim((string)($payload['cohabitation_lot_number'] ?? '')) : '',
            trim((string)($payload['cohabitation_block_number'] ?? '')) !== '' ? 'Blk ' . trim((string)($payload['cohabitation_block_number'] ?? '')) : '',
            trim((string)($payload['cohabitation_phase_number'] ?? '')) !== '' ? 'Phase ' . trim((string)($payload['cohabitation_phase_number'] ?? '')) : '',
            (string)($payload['cohabitation_subdivision_lot'] ?? $payload['cohabitation_subdivision'] ?? ''),
            (string)($payload['cohabitation_barangay'] ?? ''),
            (string)($payload['cohabitation_municipality'] ?? $payload['cohabitation_city'] ?? ''),
            (string)($payload['cohabitation_province'] ?? ''),
        ]);
    }
    if ($system === 'house') {
        return dra_join_address_parts([
            trim((string)($payload['cohabitation_unit_number'] ?? '')) !== '' ? 'Unit ' . trim((string)($payload['cohabitation_unit_number'] ?? '')) : '',
            trim(implode(' ', array_filter([
                trim((string)($payload['cohabitation_house_number'] ?? '')),
                trim((string)($payload['cohabitation_street_name'] ?? '')),
            ], static fn($v) => $v !== ''))),
            (string)($payload['cohabitation_subdivision'] ?? ''),
            (string)($payload['cohabitation_barangay'] ?? ''),
            (string)($payload['cohabitation_municipality'] ?? $payload['cohabitation_city'] ?? ''),
            (string)($payload['cohabitation_province'] ?? ''),
        ]);
    }
    return $fallback;
}

function dra_general_clearance_purpose_from_document_type(string $documentType): string {
    $token = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($documentType)));
    if (strpos($token, 'electricalpermit') !== false) {
        return 'ELECTRICAL PERMIT';
    }
    if (strpos($token, 'waterpermit') !== false) {
        return 'WATER PERMIT';
    }
    if (strpos($token, 'residentialbuildingpermit') !== false) {
        return 'RESIDENTIAL BUILDING PERMIT';
    }
    if (strpos($token, 'commercialbuildingpermit') !== false) {
        return 'COMMERCIAL BUILDING PERMIT';
    }
    return '';
}

function dra_build_general_clearance_location(array $payload, string $fallback = ''): string {
    $direct = trim((string)($payload['location'] ?? $payload['lot_full_address'] ?? $payload['project_location'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $sameAddress = strtolower(trim((string)($payload['lot_same_address'] ?? '')));
    $applicantAddress = trim((string)($payload['applicant_full_address'] ?? $payload['full_address'] ?? $payload['full_address_display'] ?? ''));
    if (in_array($sameAddress, ['1', 'true', 'yes', 'on'], true)) {
        return $applicantAddress !== '' ? $applicantAddress : $fallback;
    }

    $system = strtolower(trim((string)($payload['lot_address_system'] ?? '')));
    if ($system === 'lot_block') {
        return dra_join_address_parts([
            trim((string)($payload['lot_number'] ?? '')) !== '' ? 'Lot ' . trim((string)($payload['lot_number'] ?? '')) : '',
            trim((string)($payload['block_number'] ?? '')) !== '' ? 'Blk ' . trim((string)($payload['block_number'] ?? '')) : '',
            trim((string)($payload['lot_phase_number'] ?? '')) !== '' ? 'Phase ' . trim((string)($payload['lot_phase_number'] ?? '')) : '',
            (string)($payload['lot_subdivision'] ?? ''),
            (string)($payload['lot_barangay'] ?? ''),
            (string)($payload['lot_city'] ?? ''),
            (string)($payload['lot_province'] ?? ''),
        ]);
    }
    if ($system === 'house') {
        return dra_join_address_parts([
            trim((string)($payload['lot_unit_number'] ?? '')) !== '' ? 'Unit ' . trim((string)($payload['lot_unit_number'] ?? '')) : '',
            trim(implode(' ', array_filter([
                trim((string)($payload['lot_street_number'] ?? '')),
                trim((string)($payload['lot_street_name'] ?? '')),
            ], static fn($v) => $v !== ''))),
            (string)($payload['lot_subdivision'] ?? ''),
            (string)($payload['lot_barangay'] ?? ''),
            (string)($payload['lot_city'] ?? ''),
            (string)($payload['lot_province'] ?? ''),
        ]);
    }

    if ($applicantAddress !== '') {
        return $applicantAddress;
    }
    return $fallback;
}

function dra_public_base_url(): string {
    return appBaseUrl();
}

function dra_qr_verify_url(string $requestId, string $verificationCode): string {
    $vc = $verificationCode !== '' ? $verificationCode : $requestId;
    return rtrim(dra_public_base_url(), '/')
        . '/Guest-End/TransactionInformation.html?request_id='
        . rawurlencode($requestId)
        . '&vc=' . rawurlencode($vc);
}

function dra_humanize_document_type(string $docType): string {
    $text = trim($docType);
    if ($text === '') {
        return 'Document';
    }
    $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text) ?? $text;
    $text = str_replace(['_', '-'], ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

function dra_parse_residency_duration_text(string $raw): ?array {
    $text = trim($raw);
    if ($text === '') {
        return null;
    }
    $years = null;
    $months = null;
    if (preg_match('/(\d+)\s*year/i', $text, $m)) {
        $years = max(0, (int)$m[1]);
    }
    if (preg_match('/(\d+)\s*month/i', $text, $m)) {
        $months = max(0, (int)$m[1]);
    }
    if ($years === null && $months === null) {
        return null;
    }
    return ['years' => $years ?? 0, 'months' => $months ?? 0];
}

function dra_parse_residency_start_ym(string $raw): ?array {
    $value = trim($raw);
    if ($value === '') {
        return null;
    }
    $start = DateTime::createFromFormat('Y-m', $value);
    if (!($start instanceof DateTime)) {
        return null;
    }
    $start->setDate((int)$start->format('Y'), (int)$start->format('m'), 1);
    $currentMonth = new DateTime('first day of this month');
    if ($start > $currentMonth) {
        return null;
    }
    $diff = $start->diff($currentMonth);
    return ['years' => max(0, (int)$diff->y), 'months' => max(0, (int)$diff->m)];
}

function dra_duration_total_months(array $duration): int {
    $years = max(0, (int)($duration['years'] ?? 0));
    $months = max(0, (int)($duration['months'] ?? 0));
    return ($years * 12) + $months;
}

function dra_pick_longer_residency_duration(array $a, array $b): array {
    return dra_duration_total_months($a) >= dra_duration_total_months($b) ? $a : $b;
}

function dra_request_notice(array $requestRow, string $requestId, string $suffix): string {
    $docType = dra_humanize_document_type((string)($requestRow['document_type'] ?? ''));
    $rid = trim($requestId) !== '' ? trim($requestId) : trim((string)($requestRow['request_id'] ?? ''));
    if ($rid === '') {
        return 'Your ' . $docType . ' Request has been ' . $suffix;
    }
    return 'Your ' . $docType . ' Request #' . $rid . ' has been ' . $suffix;
}

function dra_is_first_time_job_seeker(array $requestRow): bool {
    $docType = strtolower(trim((string)($requestRow['document_type'] ?? '')));
    if ($docType === '') {
        return false;
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '', $docType);
    return is_string($normalized) && strpos($normalized, 'firsttimejobseeker') !== false;
}

function dra_normalize_business_approval_type(string $value): string {
    $token = strtolower(trim($value));
    if ($token === '') {
        return '';
    }
    $token = (string)(preg_replace('/[^a-z0-9]+/', '_', $token) ?? $token);
    $token = trim($token, '_');
    if ($token === 'not_banned' || strpos($token, 'not_among_those_business') !== false) {
        return 'not_banned';
    }
    if ($token === 'no_objection' || strpos($token, 'interposes_no_objection') !== false) {
        return 'no_objection';
    }
    if ($token === 'temporary_clearance' || strpos($token, 'temporary_barangay_clearance') !== false) {
        return 'temporary_clearance';
    }
    return '';
}

function dra_decode_request_payload(array $requestRow): array {
    $raw = (string)($requestRow['request_details'] ?? $requestRow['payload_json'] ?? '{}');
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function dra_apply_preview_edits(mysqli $conn, string $requestId, array &$requestRow, array $edited): void {
    if ($requestId === '' || empty($edited)) {
        return;
    }

    $payload = dra_decode_request_payload($requestRow);

    $purpose = trim((string)($edited['purpose'] ?? ''));
    $requestOfficerLine1 = trim((string)($edited['requestOfficerLine1'] ?? ''));
    $requestOfficerLine2 = trim((string)($edited['requestOfficerLine2'] ?? ''));
    $requestOfficerLine3 = trim((string)($edited['requestOfficerLine3'] ?? ''));
    $requestOfficer = trim((string)($edited['requestOfficer'] ?? ''));
    $businessName = trim((string)($edited['businessName'] ?? ''));
    $fullAddress = trim((string)($edited['fullAddress'] ?? ''));
    $fullName = trim((string)($edited['fullName'] ?? ''));
    $cohabitantName = trim((string)($edited['cohabitantName'] ?? ''));
    $cohabitantRelationship = trim((string)($edited['cohabitantRelationship'] ?? ''));
    $detentionFacility = trim((string)($edited['detentionFacility'] ?? ''));
    $businessApprovalType = dra_normalize_business_approval_type((string)($edited['businessApprovalType'] ?? ''));
    $plateNumber = trim((string)($edited['plateNumber'] ?? ''));
    $cohabitationDuration = trim((string)($edited['cohabitationDuration'] ?? ''));
    $cohabitationStartDate = trim((string)($edited['cohabitationStartDate'] ?? ''));

    if ($requestOfficerLine1 !== '' || $requestOfficerLine2 !== '' || $requestOfficerLine3 !== '') {
        $payload['request_officer_line1'] = $requestOfficerLine1;
        $payload['request_officer_line2'] = $requestOfficerLine2;
        $payload['request_officer_line3'] = $requestOfficerLine3;
        $requestOfficer = implode(' - ', array_values(array_filter([
            $requestOfficerLine1,
            $requestOfficerLine2,
            $requestOfficerLine3,
        ], static fn($value) => trim((string)$value) !== '')));
    }

    if ($purpose !== '') {
        $payload['request_purpose'] = $purpose;
        $payload['purpose'] = $purpose;
        if (dr_column_exists($conn, 'documentrequesttbl', 'purpose')) {
            $stmtPurpose = $conn->prepare("UPDATE documentrequesttbl SET purpose = ? WHERE request_id = ? LIMIT 1");
            if ($stmtPurpose) {
                $stmtPurpose->bind_param('ss', $purpose, $requestId);
                $stmtPurpose->execute();
                $stmtPurpose->close();
            }
        }
        $requestRow['purpose'] = $purpose;
    }
    if ($requestOfficer !== '') {
        $payload['request_officer'] = $requestOfficer;
    }
    if ($businessName !== '') {
        $payload['business_name'] = $businessName;
    }
    if ($fullAddress !== '') {
        $payload['full_address'] = $fullAddress;
    }
    if ($fullName !== '') {
        $payload['_preview_full_name'] = $fullName;
    }
    if ($cohabitantName !== '') {
        $payload['cohabitant_full_name'] = $cohabitantName;
    }
    if ($cohabitantRelationship !== '') {
        $payload['cohabitant_relationship'] = $cohabitantRelationship;
    }
    if ($detentionFacility !== '') {
        $payload['_preview_detention_facility'] = $detentionFacility;
    }
    if ($businessApprovalType !== '') {
        $payload['_preview_business_approval_type'] = $businessApprovalType;
    }
    if ($plateNumber !== '') {
        $payload['_preview_plate_number'] = strtoupper($plateNumber);
    }
    if ($cohabitationDuration !== '') {
        $payload['cohabitation_duration'] = $cohabitationDuration;
    }
    if ($cohabitationStartDate !== '') {
        $payload['cohabitation_start_date'] = $cohabitationStartDate;
    }

    $encoded = dr_safe_json($payload);
    if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
        $stmt = $conn->prepare("UPDATE documentrequesttbl SET request_details = ? WHERE request_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $encoded, $requestId);
            $stmt->execute();
            $stmt->close();
            $requestRow['request_details'] = $encoded;
        }
    }
}

function dra_overlay_preview_edits(array &$requestRow, array $edited): void {
    if (empty($edited)) {
        return;
    }

    $payload = dra_decode_request_payload($requestRow);

    $purpose = trim((string)($edited['purpose'] ?? ''));
    $requestOfficerLine1 = trim((string)($edited['requestOfficerLine1'] ?? ''));
    $requestOfficerLine2 = trim((string)($edited['requestOfficerLine2'] ?? ''));
    $requestOfficerLine3 = trim((string)($edited['requestOfficerLine3'] ?? ''));
    $requestOfficer = trim((string)($edited['requestOfficer'] ?? ''));
    $businessName = trim((string)($edited['businessName'] ?? ''));
    $fullAddress = trim((string)($edited['fullAddress'] ?? ''));
    $fullName = trim((string)($edited['fullName'] ?? ''));
    $yearsResidency = trim((string)($edited['yearsResidency'] ?? ''));
    $monthsResidency = trim((string)($edited['monthsResidency'] ?? ''));
    $birthdate = trim((string)($edited['birthdate'] ?? $edited['childBirthdate'] ?? ''));
    $birthplace = trim((string)($edited['birthplace'] ?? $edited['childBirthplace'] ?? ''));
    $location = trim((string)($edited['location'] ?? ''));
    $remarks = trim((string)($edited['remarks'] ?? ''));
    $fatherName = trim((string)($edited['fatherName'] ?? ''));
    $motherName = trim((string)($edited['motherName'] ?? ''));
    $cohabitantName = trim((string)($edited['cohabitantName'] ?? ''));
    $cohabitantRelationship = trim((string)($edited['cohabitantRelationship'] ?? ''));
    $detentionFacility = trim((string)($edited['detentionFacility'] ?? ''));
    $businessApprovalType = dra_normalize_business_approval_type((string)($edited['businessApprovalType'] ?? ''));
    $plateNumber = trim((string)($edited['plateNumber'] ?? ''));
    $cohabitationDuration = trim((string)($edited['cohabitationDuration'] ?? ''));
    $cohabitationStartDate = trim((string)($edited['cohabitationStartDate'] ?? ''));
    $educationalAttainment = trim((string)($edited['educationalAttainment'] ?? ''));
    $jobstartBeneficiary = trim((string)($edited['jobstartBeneficiary'] ?? ''));

    if ($requestOfficerLine1 !== '' || $requestOfficerLine2 !== '' || $requestOfficerLine3 !== '') {
        $payload['request_officer_line1'] = $requestOfficerLine1;
        $payload['request_officer_line2'] = $requestOfficerLine2;
        $payload['request_officer_line3'] = $requestOfficerLine3;
        $requestOfficer = implode(' - ', array_values(array_filter([
            $requestOfficerLine1,
            $requestOfficerLine2,
            $requestOfficerLine3,
        ], static fn($value) => trim((string)$value) !== '')));
    }

    if ($purpose !== '') {
        $payload['request_purpose'] = $purpose;
        $payload['purpose'] = $purpose;
        $requestRow['purpose'] = $purpose;
    }
    if ($requestOfficer !== '') {
        $payload['request_officer'] = $requestOfficer;
    }
    if ($businessName !== '') {
        $payload['business_name'] = $businessName;
    }
    if ($fullAddress !== '') {
        $payload['full_address'] = $fullAddress;
    }
    if ($fullName !== '') {
        $payload['_preview_full_name'] = $fullName;
    }
    if ($yearsResidency !== '') {
        $payload['years_of_residency'] = $yearsResidency;
    }
    if ($monthsResidency !== '') {
        $payload['months_of_residency'] = $monthsResidency;
    }
    if ($birthdate !== '') {
        $payload['birthdate'] = $birthdate;
        $payload['date_of_birth'] = $birthdate;
        $payload['child_dob'] = $birthdate;
    }
    if ($birthplace !== '') {
        $payload['birthplace'] = $birthplace;
        $payload['place_of_birth'] = $birthplace;
        $payload['child_birthplace'] = $birthplace;
    }
    if ($location !== '') {
        $payload['location'] = $location;
    }
    if ($remarks !== '') {
        $payload['remarks'] = $remarks;
    }
    if ($fatherName !== '') {
        $payload['father_full_name'] = $fatherName;
    }
    if ($motherName !== '') {
        $payload['mother_full_name'] = $motherName;
    }
    if ($cohabitantName !== '') {
        $payload['cohabitant_full_name'] = $cohabitantName;
    }
    if ($cohabitantRelationship !== '') {
        $payload['cohabitant_relationship'] = $cohabitantRelationship;
    }
    if ($detentionFacility !== '') {
        $payload['_preview_detention_facility'] = $detentionFacility;
    }
    if ($businessApprovalType !== '') {
        $payload['_preview_business_approval_type'] = $businessApprovalType;
    }
    if ($plateNumber !== '') {
        $payload['_preview_plate_number'] = strtoupper($plateNumber);
    }
    if ($cohabitationDuration !== '') {
        $payload['cohabitation_duration'] = $cohabitationDuration;
    }
    if ($cohabitationStartDate !== '') {
        $payload['cohabitation_start_date'] = $cohabitationStartDate;
    }
    if ($educationalAttainment !== '') {
        $payload['educational_attainment'] = $educationalAttainment;
    }
    if ($jobstartBeneficiary !== '') {
        $payload['jobstart_beneficiary'] = $jobstartBeneficiary;
    }

    $requestRow['request_details'] = dr_safe_json($payload);
}

function dra_generate_issued_document(array $requestRow): ?string {
    global $conn;

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }
    $outDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/Generated';
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
    }
    $qrDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/QR';
    if (!is_dir($qrDir)) {
        @mkdir($qrDir, 0775, true);
    }

    $requestId = trim((string)($requestRow['request_id'] ?? ''));
    if ($requestId === '') {
        return null;
    }
    $previewMode = !empty($requestRow['_preview_mode']);
    $preferDocxOutput = !empty($requestRow['_prefer_docx_output']);
    $docType = trim((string)($requestRow['document_type'] ?? 'Certificate'));
    $purpose = trim((string)($requestRow['purpose'] ?? ''));
    $stripTemplateTokens = static function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = (string)(preg_replace('/\$\{[A-Z0-9_]+\}/', ' ', $value) ?? $value);
        $value = (string)(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return trim($value);
    };
    $purpose = $stripTemplateTokens($purpose);
    $issuedDateRaw = '';
    foreach ([
        (string)($requestRow['release_timestamp'] ?? ''),
        (string)($requestRow['completed_at'] ?? ''),
        (string)($requestRow['ready_at'] ?? ''),
        (string)($requestRow['submitted_at'] ?? ''),
        (string)($requestRow['request_timestamp'] ?? ''),
    ] as $candidateIssuedDate) {
        $candidateIssuedDate = trim($candidateIssuedDate);
        if ($candidateIssuedDate !== '') {
            $issuedDateRaw = $candidateIssuedDate;
            break;
        }
    }
    try {
        $issuedDateObj = $issuedDateRaw !== '' ? new DateTime($issuedDateRaw) : new DateTime();
    } catch (Throwable $ignored) {
        $issuedDateObj = new DateTime();
    }
    $issuedAt = $issuedDateObj->format('F j, Y');
    $day = (int)$issuedDateObj->format('j');
    $monthUpper = strtoupper($issuedDateObj->format('F'));
    $yearNum = $issuedDateObj->format('Y');
    $v = $day % 100;
    $suffix = ($v >= 11 && $v <= 13) ? 'th' : (($day % 10 === 1) ? 'st' : (($day % 10 === 2) ? 'nd' : (($day % 10 === 3) ? 'rd' : 'th')));
    $issuedAsDocx = $day . $suffix . ' day of ' . $monthUpper . ' ' . $yearNum;
    $payload = dra_decode_request_payload($requestRow);

    $fullName = trim((string)($payload['_preview_full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = dra_format_full_name_from_payload($payload);
    }
    if ($fullName === '') {
        $fullName = trim((string)($requestRow['resident_id'] ?? 'Resident'));
    }
    $fullName = $stripTemplateTokens($fullName);
    $applicantResidenceAddress = trim((string)($payload['full_address'] ?? $payload['full_address_display'] ?? ''));
    if ($applicantResidenceAddress === '') {
        $applicantResidenceAddress = 'Barangay San Jose, Rodriguez, Rizal';
    }
    $applicantResidenceAddress = $stripTemplateTokens($applicantResidenceAddress);
    $applicantAddressWithBarangay = dra_compose_barangay_address($applicantResidenceAddress);
    $cohabitantResidenceAddress = $stripTemplateTokens(dra_build_cohabitant_address($payload, $applicantResidenceAddress));
    $cohabitationResidenceAddress = $stripTemplateTokens(dra_build_cohabitation_address($payload, $applicantResidenceAddress));
    $address = trim((string)($payload['full_address'] ?? 'Barangay San Jose, Rodriguez, Rizal'));
    $address = dra_strip_area_from_address($address);
    if ($address === '') {
        $address = 'Barangay San Jose, Rodriguez, Rizal';
    }
    $address = $stripTemplateTokens($address);
    $addressWithBarangay = dra_compose_barangay_address($address);
    $certNo = trim((string)($requestRow['certificate_number'] ?? ''));
    $orNo = trim((string)($requestRow['or_number'] ?? ''));
    if ($orNo !== '') {
        $orNo = trim((string)(preg_replace('/\$\{[A-Z0-9_]+\}/', '', $orNo) ?? $orNo));
        if (preg_match('/^\s*OR_NUMBER\s*$/i', $orNo)) {
            $orNo = '';
        }
    }

    $verificationCode = trim((string)($requestRow['verification_code'] ?? ''));
    $currentStage = strtolower(trim((string)($requestRow['stage'] ?? '')));
    $defaultFee = dr_get_fee_amount_for_document_type($conn, $docType);
    $isFreeDocument = ($defaultFee !== null && (float)$defaultFee <= 0.0);
    $qrEligibleStages = $isFreeDocument
        ? [
            strtolower((string)DR_STAGE_READY_FOR_CLAIM),
            strtolower((string)DR_STAGE_COMPLETED),
        ]
        : [
            strtolower((string)DR_STAGE_PAYMENT_VERIFIED),
            strtolower((string)DR_STAGE_READY_FOR_CLAIM),
            strtolower((string)DR_STAGE_COMPLETED),
        ];
    $allowQr = (
        !$previewMode
        && $verificationCode !== ''
        && in_array($currentStage, $qrEligibleStages, true)
    );
    $verifyUrl = $allowQr ? dra_qr_verify_url($requestId, $verificationCode) : '';
    $qrFile = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $qrDiskPath = $qrDir . '/' . $qrFile;
    $qrPublicPath = $allowQr ? ('/UnifiedFileAttachment/IssuedDocuments/QR/' . $qrFile) : '';

    if ($allowQr) {
        $qrApi = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($verifyUrl);
        $ctx = stream_context_create([
            'http' => ['timeout' => 6, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $qrContent = @file_get_contents($qrApi, false, $ctx);
        if ($qrContent !== false && strlen($qrContent) > 500) {
            @file_put_contents($qrDiskPath, $qrContent);
        } else {
            // Fallback QR placeholder if external API is unreachable.
            if (function_exists('imagecreatetruecolor')) {
                $img = imagecreatetruecolor(220, 220);
                $white = imagecolorallocate($img, 255, 255, 255);
                $black = imagecolorallocate($img, 0, 0, 0);
                imagefilledrectangle($img, 0, 0, 220, 220, $white);
                imagerectangle($img, 0, 0, 219, 219, $black);
                imagestring($img, 4, 78, 90, 'QR', $black);
                imagestring($img, 2, 12, 198, substr($verificationCode !== '' ? $verificationCode : $requestId, 0, 28), $black);
                imagepng($img, $qrDiskPath);
                imagedestroy($img);
            }
        }
    }

    $cohabitationVariant = strtolower(trim((string)($payload['cohabitation_variant'] ?? '')));
    $docTypeNorm = strtolower(trim($docType));
    $docTypeToken = preg_replace('/[^a-z0-9]+/', '', $docTypeNorm);
    $isIndigency = strpos($docTypeNorm, 'indigency') !== false;
    $isGoodMoral = (strpos($docTypeNorm, 'goodmoral') !== false) || (strpos($docTypeNorm, 'good moral') !== false);
    $isResidency = strpos($docTypeNorm, 'residency') !== false;
    $isCohabitation = strpos($docTypeNorm, 'cohabitation') !== false;
    $isFirstTimeJobSeeker = strpos(preg_replace('/[^a-z0-9]+/', '', $docTypeNorm), 'firsttimejobseeker') !== false;
    $isBusinessPermitClearance = in_array($docTypeToken, [
        'barangayclearanceforbusinesspermit',
        'barangaybusinessclearance',
        'businessclearance',
        'clearanceforbusinesspermit',
    ], true);
    $isTricyclePermitClearance = in_array($docTypeToken, [
        'barangayclearancefortricyclepermit',
        'clearancefortricyclepermit',
        'tricyclepermit',
        'tricycleclearance',
        'fortricyclepermit',
    ], true);
    $generalPermitPurpose = dra_general_clearance_purpose_from_document_type($docType);
    $isGeneralPermitClearance = ($generalPermitPurpose !== '');
    $isRelationshipJailVisit = $isCohabitation && in_array($cohabitationVariant, ['relationship_jail_visit', 'conjugal_visit'], true);

    // DOCX template workflow removed: force all issuance through pure-PHP renderer below.
    $isTemplateBasedCertificate = false;

    // Indigency/Good Moral/Residency: generate from .docx template only.
    if ($isTemplateBasedCertificate) {
        if ($isIndigency) {
            $templateFile = 'Certificate of Indigency.docx';
        } elseif ($isGoodMoral) {
            $templateFile = 'Certificate of Good Moral.docx';
        } elseif ($isCohabitation) {
            $cohabitationChildrenCount = max(0, min(5, (int)trim((string)($payload['cohabitation_children_count'] ?? '0'))));
            $templateFile = $cohabitationChildrenCount > 0
                ? 'CertificateOfCohabitationWithChild.docx'
                : 'CertificateOfCohabitationNoChild.docx';
        } else {
            $residencyTemplates = ['general certification.docx', 'GeneralCertification.docx'];
            $templateFile = 'GeneralCertification.docx';
            foreach ($residencyTemplates as $candidateTemplate) {
                if (is_file($baseDir . '/Resident-End/Certificates/DocumentIssuance/' . $candidateTemplate)) {
                    $templateFile = $candidateTemplate;
                    break;
                }
            }
        }
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/' . $templateFile;
        if (!is_file($templatePath)) {
            error_log('[dra_generate_issued_document][docx_template] missing template: ' . $templatePath);
            return null;
        }

        if (!class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
            $phpWordAutoloads = [
                $baseDir . '/PhpFiles/PhpOffice/vendor/autoload.php',
                $baseDir . '/vendor/autoload.php',
            ];
            foreach ($phpWordAutoloads as $autoloadPath) {
                if (is_file($autoloadPath)) {
                    require_once $autoloadPath;
                    if (class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
                        break;
                    }
                }
            }
        }

        if (!class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
            error_log('[dra_generate_issued_document][docx_template] PhpWord TemplateProcessor unavailable');
            return null;
        }

        // PhpWord needs a writable temp dir for unpacking .docx templates.
        $phpWordTempDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/Tmp';
        if (!is_dir($phpWordTempDir)) {
            @mkdir($phpWordTempDir, 0777, true);
        }
        @chmod($phpWordTempDir, 0777);
        if (is_dir($phpWordTempDir) && is_writable($phpWordTempDir) && class_exists('\PhpOffice\PhpWord\Settings')) {
            @putenv('TMPDIR=' . $phpWordTempDir);
            @putenv('TMP=' . $phpWordTempDir);
            @putenv('TEMP=' . $phpWordTempDir);
            @ini_set('sys_temp_dir', $phpWordTempDir);
            \PhpOffice\PhpWord\Settings::setTempDir($phpWordTempDir);
        }

        try {
            $template = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            $normalizeText = static function (string $value): string {
                $v = trim(preg_replace('/\s+/u', ' ', $value));
                return $v === '' ? '-' : strtoupper($v);
            };
            $normalizeOptionalText = static function (string $value): string {
                $v = trim(preg_replace('/\s+/u', ' ', $value));
                return strtoupper($v);
            };
            $normalizePlainText = static function (string $value, string $fallback = ''): string {
                $v = trim(preg_replace('/\s+/u', ' ', $value));
                return $v === '' ? $fallback : $v;
            };
            $formatMiddleInitial = static function (string $value): string {
                $v = trim($value);
                if ($v === '') {
                    return '';
                }
                $firstChar = mb_substr($v, 0, 1, 'UTF-8');
                return $firstChar !== '' ? ($firstChar . '.') : '';
            };
            $formatDisplayName = static function (string $first, string $middle, string $last, string $suffix = '') use ($normalizePlainText, $formatMiddleInitial): string {
                $parts = array_filter([
                    $normalizePlainText($first),
                    $formatMiddleInitial($middle),
                    $normalizePlainText($last),
                    $normalizePlainText($suffix),
                ], static fn($value) => trim((string)$value) !== '');
                return implode(' ', $parts);
            };
            $formatDisplayDate = static function (string $value): string {
                $raw = trim($value);
                if ($raw === '') {
                    return '';
                }
                try {
                    return (new DateTime($raw))->format('F j, Y');
                } catch (Throwable $ignored) {
                    return $raw;
                }
            };
            $formatDisplayMonthYear = static function (string $value): string {
                $raw = trim($value);
                if ($raw === '') {
                    return '';
                }
                if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                    try {
                        return (new DateTime($raw . '-01'))->format('F Y');
                    } catch (Throwable $ignored) {
                        return $raw;
                    }
                }
                return $raw;
            };

            $requestOfficer = trim((string)($payload['request_officer'] ?? ''));
            $requestPurpose = trim((string)($payload['request_purpose'] ?? $purpose));
            if ($requestPurpose === '') {
                $requestPurpose = $purpose !== '' ? $purpose : 'PURPOSE';
            }
            $submissionTargetType = trim((string)($payload['submission_target_type'] ?? ''));
            $institutionName = trim((string)($payload['institution_name'] ?? ''));
            $institutionPerson = trim((string)($payload['institution_person'] ?? ''));
            $institutionPosition = trim((string)($payload['institution_position'] ?? ''));
            $requestOfficerLine1 = trim((string)($payload['request_officer_line1'] ?? ''));
            $requestOfficerLine2 = trim((string)($payload['request_officer_line2'] ?? ''));
            $requestOfficerLine3 = trim((string)($payload['request_officer_line3'] ?? ''));
            $governmentOffice = trim((string)($payload['government_office'] ?? ''));
            if ($governmentOffice === '') {
                $governmentOffice = trim((string)($payload['government_position_group'] ?? ''));
            }
            if ($governmentOffice === '__other__') {
                $governmentOffice = trim((string)($payload['government_position_other'] ?? ''));
            }
            $governmentPosition = trim((string)($payload['government_position'] ?? ''));
            if ($governmentPosition === '') {
                $governmentPosition = trim((string)($payload['government_position_detail'] ?? ''));
            }
            $governmentOfficial = trim((string)($payload['government_official'] ?? ''));
            if ($governmentOfficial === '') {
                $governmentOfficial = trim((string)($payload['government_official_other'] ?? ''));
            }

            $formattedRequestOfficer = $requestOfficer;
            if ($requestOfficerLine1 !== '' || $requestOfficerLine2 !== '' || $requestOfficerLine3 !== '') {
                $formattedRequestOfficer = implode(' - ', array_filter([$requestOfficerLine1, $requestOfficerLine2, $requestOfficerLine3], static fn($value) => trim((string)$value) !== ''));
            } elseif ($submissionTargetType === 'institution') {
                $attentionParts = array_values(array_filter([$institutionPerson, $institutionPosition], static fn($value) => trim((string)$value) !== ''));
                $requestOfficerLine1 = $institutionName;
                $requestOfficerLine2 = !empty($attentionParts) ? 'ATTN: ' . implode(', ', $attentionParts) : '';
                $formattedRequestOfficer = implode(' - ', array_filter([$requestOfficerLine1, $requestOfficerLine2], static fn($value) => trim((string)$value) !== ''));
            } elseif ($submissionTargetType === 'government_official') {
                if ($governmentOfficial === '' && $requestOfficer !== '') {
                    $governmentOfficial = $requestOfficer;
                }
                $requestOfficerLine1 = $governmentOfficial;
                $requestOfficerLine2 = $governmentPosition;
                $requestOfficerLine3 = $governmentOffice;
                $formattedRequestOfficer = implode(' - ', array_filter([$requestOfficerLine1, $requestOfficerLine2, $requestOfficerLine3], static fn($value) => trim((string)$value) !== ''));
            }
            if ($formattedRequestOfficer !== '') {
                $requestOfficer = $formattedRequestOfficer;
            }
            if ($requestOfficerLine1 === '' && $requestOfficer !== '') {
                $requestOfficerParts = array_values(array_filter(array_map('trim', preg_split('/\s*-\s*/', $requestOfficer) ?: []), static fn($value) => $value !== ''));
                $requestOfficerLine1 = (string)($requestOfficerParts[0] ?? '');
                $requestOfficerLine2 = (string)($requestOfficerParts[1] ?? '');
                $requestOfficerLine3 = (string)($requestOfficerParts[2] ?? '');
            }
            $yearsResidency = trim((string)($payload['years_of_residency'] ?? ''));
            $monthsResidency = trim((string)($payload['months_of_residency'] ?? ''));
            $birthdateValue = trim((string)($payload['birthdate'] ?? $payload['date_of_birth'] ?? $payload['child_dob'] ?? $payload['birthDate'] ?? ''));
            $birthplaceValue = trim((string)($payload['birthplace'] ?? $payload['place_of_birth'] ?? $payload['child_birthplace'] ?? ''));
            $remarksValue = trim((string)($payload['remarks'] ?? $requestRow['status_remarks'] ?? $requestRow['status_reason'] ?? ''));
            $cohabitationDurationValue = trim((string)($payload['cohabitation_duration'] ?? ''));
            $cohabitationChildrenCount = max(0, min(5, (int)trim((string)($payload['cohabitation_children_count'] ?? '0'))));
            $cohabitationChildNames = [];
            $cohabitationChildAges = [];
            $residentFirstName = trim((string)($payload['first_name'] ?? $payload['firstname'] ?? ''));
            $residentMiddleName = trim((string)($payload['middle_name'] ?? $payload['middlename'] ?? ''));
            $residentLastName = trim((string)($payload['last_name'] ?? $payload['lastname'] ?? ''));
            $residentSuffix = trim((string)($payload['suffix_name'] ?? $payload['suffix'] ?? ''));
            $cohabitantFirstName = trim((string)($payload['cohabitant_first'] ?? ''));
            $cohabitantMiddleName = trim((string)($payload['cohabitant_middle'] ?? ''));
            $cohabitantLastName = trim((string)($payload['cohabitant_last'] ?? ''));
            $cohabitantSuffix = trim((string)($payload['cohabitant_suffix'] ?? ''));
            $cohabitantBirthdateValue = trim((string)($payload['cohabitant_birthdate'] ?? $payload['cohabitant_dob'] ?? $payload['partner_birthdate'] ?? $payload['partner_dob'] ?? ''));
            if ($isCohabitation && ($birthdateValue === '' || $residentFirstName === '' || $residentLastName === '')) {
                $residentUserId = trim((string)($requestRow['resident_user_id'] ?? ''));
                if ($residentUserId !== '') {
                    $residentInfoStmt = $conn->prepare("
                        SELECT firstname, middlename, lastname, suffix, birthdate
                        FROM residentinformationtbl
                        WHERE user_id = ?
                        LIMIT 1
                    ");
                    if ($residentInfoStmt) {
                        $residentInfoStmt->bind_param('s', $residentUserId);
                        $residentInfoStmt->execute();
                        $residentInfo = $residentInfoStmt->get_result()->fetch_assoc() ?: [];
                        $residentInfoStmt->close();
                        if ($residentFirstName === '') {
                            $residentFirstName = trim((string)($residentInfo['firstname'] ?? ''));
                        }
                        if ($residentMiddleName === '') {
                            $residentMiddleName = trim((string)($residentInfo['middlename'] ?? ''));
                        }
                        if ($residentLastName === '') {
                            $residentLastName = trim((string)($residentInfo['lastname'] ?? ''));
                        }
                        if ($residentSuffix === '') {
                            $residentSuffix = trim((string)($residentInfo['suffix'] ?? ''));
                        }
                        if ($birthdateValue === '') {
                            $birthdateValue = trim((string)($residentInfo['birthdate'] ?? ''));
                        }
                    }
                }
            }
            for ($childIndex = 1; $childIndex <= 5; $childIndex += 1) {
                $cohabitationChildNames[$childIndex] = trim((string)($payload['cohabitation_child_' . $childIndex . '_name'] ?? ''));
                $cohabitationChildAges[$childIndex] = trim((string)($payload['cohabitation_child_' . $childIndex . '_age'] ?? ''));
            }
            $ageValue = trim((string)($payload['age'] ?? ''));
            if ($ageValue === '' && $birthdateValue !== '') {
                try {
                    $birthDateObj = new DateTime($birthdateValue);
                    $ageValue = (string)$birthDateObj->diff(new DateTime())->y;
                } catch (Throwable $ignored) {
                    $ageValue = '';
                }
            }
            $residentNameValue = $formatDisplayName($residentFirstName, $residentMiddleName, $residentLastName, $residentSuffix);
            if ($residentNameValue === '') {
                $residentNameValue = $normalizePlainText($fullName);
            }
            $cohabitantNameValue = $formatDisplayName($cohabitantFirstName, $cohabitantMiddleName, $cohabitantLastName, $cohabitantSuffix);
            $cohabitantAgeValue = trim((string)($payload['cohabitant_age'] ?? ''));
            if ($cohabitantAgeValue === '' && $cohabitantBirthdateValue !== '') {
                try {
                    $cohabitantBirthDateObj = new DateTime($cohabitantBirthdateValue);
                    $cohabitantAgeValue = (string)$cohabitantBirthDateObj->diff(new DateTime())->y;
                } catch (Throwable $ignored) {
                    $cohabitantAgeValue = '';
                }
            }
            $birthdateDisplayValue = $formatDisplayDate($birthdateValue);
            $cohabitantBirthdateDisplayValue = $formatDisplayDate($cohabitantBirthdateValue);
            $cohabitationStartRaw = trim((string)($payload['cohabitation_start_date'] ?? ''));
            $cohabitationStartRaw = trim((string)(preg_replace('/\s*\([^()]*\)\s*$/', '', $cohabitationStartRaw) ?? $cohabitationStartRaw));
            $cohabitationSinceValue = $formatDisplayMonthYear($cohabitationStartRaw);
            $childrenListValue = [];
            for ($childIndex = 1; $childIndex <= 5; $childIndex += 1) {
                $childName = $normalizePlainText((string)($cohabitationChildNames[$childIndex] ?? ''));
                $childAge = $normalizePlainText((string)($cohabitationChildAges[$childIndex] ?? ''));
                if ($childName === '' && $childAge === '') {
                    continue;
                }
                $childrenListValue[] = trim($childName . ($childAge !== '' ? ', ' . $childAge . ' y/o' : ''));
            }
            $issuedDateObj = new DateTime();
            $issuedDateWord = $issuedDateObj->format('F j, Y');
            $day = (int)$issuedDateObj->format('j');
            $monthUpper = strtoupper($issuedDateObj->format('F'));
            $yearNum = $issuedDateObj->format('Y');
            $v = $day % 100;
            $suffix = ($v >= 11 && $v <= 13) ? 'th' : (($day % 10 === 1) ? 'st' : (($day % 10 === 2) ? 'nd' : (($day % 10 === 3) ? 'rd' : 'th')));
            $issuedAsDocx = $day . $suffix . ' day of ' . $monthUpper . ' ' . $yearNum;

            $cacheSignature = sha1(dr_safe_json([
                'cache_version' => 32,
                'preview' => $previewMode ? 1 : 0,
                'request_id' => $requestId,
                'document_type' => $docType,
                'purpose' => $purpose,
                'payload' => $payload,
                'full_name' => $fullName,
                'address' => $address,
                'request_officer' => $requestOfficer,
                'request_purpose' => $requestPurpose,
                'years_of_residency' => $yearsResidency,
                'months_of_residency' => $monthsResidency,
                'birthdate' => $birthdateValue,
                'birthplace' => $birthplaceValue,
                'remarks' => $remarksValue,
                'certificate_number' => $certNo,
                'or_number' => $orNo,
                'verification_code' => $verificationCode,
                'updated_at' => (string)($requestRow['updated_at'] ?? ''),
                'submitted_at' => (string)($requestRow['submitted_at'] ?? ''),
                'review_timestamp' => (string)($requestRow['review_timestamp'] ?? ''),
                'release_timestamp' => (string)($requestRow['release_timestamp'] ?? ''),
                'template_file' => $templateFile,
                'template_mtime' => @filemtime($templatePath) ?: 0,
            ]));
            $fileStem = ($previewMode ? 'preview_' : 'issued_')
                . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId)
                . '_' . substr($cacheSignature, 0, 12);
            $cachedPdfDiskPath = $outDir . '/' . $fileStem . '.pdf';
            $cachedDocxDiskPath = $outDir . '/' . $fileStem . '.docx';
            if ($preferDocxOutput && is_file($cachedDocxDiskPath) && filesize($cachedDocxDiskPath) > 0) {
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . basename($cachedDocxDiskPath);
            }
            if (is_file($cachedPdfDiskPath) && filesize($cachedPdfDiskPath) > 0) {
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . basename($cachedPdfDiskPath);
            }

            $template->setValue('REQUEST_ID', $normalizeText($requestId));
            $template->setValue('FULL_NAME', $normalizeText($fullName));
            $template->setValue('ADDRESS', $normalizeText($address));
            $template->setValue('PURPOSE', $normalizeText($requestPurpose));
            $template->setValue('REQUEST_OFFICER', $normalizeText($requestOfficer));
            $template->setValue('REQUEST_OFFICER_LINE1', $normalizeOptionalText($requestOfficerLine1));
            $template->setValue('REQUEST_OFFICER_LINE2', $normalizeOptionalText($requestOfficerLine2));
            $template->setValue('REQUEST_OFFICER_LINE3', $normalizeOptionalText($requestOfficerLine3));
            $template->setValue('SUBMISSION_TARGET_TYPE', $normalizeOptionalText($submissionTargetType));
            $template->setValue('INSTITUTION_NAME', $normalizeOptionalText($institutionName));
            $template->setValue('INSTITUTION_PERSON', $normalizeOptionalText($institutionPerson));
            $template->setValue('INSTITUTION_POSITION', $normalizeOptionalText($institutionPosition));
            $template->setValue('GOVERNMENT_OFFICE', $normalizeOptionalText($governmentOffice));
            $template->setValue('GOVERNMENT_POSITION', $normalizeOptionalText($governmentPosition));
            $template->setValue('GOVERNMENT_OFFICIAL', $normalizeOptionalText($governmentOfficial));
            $template->setValue('ISSUED_AT', $normalizeText($issuedDateWord));
            $template->setValue('ISSUED_DATE_WORD', $normalizeText($issuedAsDocx));
            $template->setValue('CERTIFICATE_NUMBER', $normalizeText($certNo));
            $template->setValue('OR_NUMBER', $normalizeText($orNo));
            $template->setValue('VERIFICATION_CODE', $normalizeText($verificationCode));
            $template->setValue('VERIFY_URL', $normalizeText($verifyUrl));
            $template->setValue('QR_PUBLIC_PATH', $normalizeText($qrPublicPath));
            $template->setValue('YEARS_OF_RESIDENCY', $normalizeText($yearsResidency));
            $template->setValue('MONTHS_OF_RESIDENCY', $normalizeText($monthsResidency));
            $template->setValue('Birthdate', $normalizeOptionalText($birthdateValue));
            $template->setValue('BIRTHDATE', $normalizePlainText($birthdateDisplayValue));
            $template->setValue('Birthplace', $normalizeOptionalText($birthplaceValue));
            $template->setValue('BIRTHPLACE', $normalizeOptionalText($birthplaceValue));
            $template->setValue('REMARKS', $normalizeOptionalText($remarksValue));
            $template->setValue('NAME', $normalizePlainText($residentNameValue));
            $template->setValue('AGE', $normalizePlainText($ageValue));
            $template->setValue('PARTNER_NAME', $normalizePlainText($cohabitantNameValue));
            $template->setValue('PARTNER_AGE', $normalizePlainText($cohabitantAgeValue));
            $template->setValue('PARTNER_BIRTHDATE', $normalizePlainText($cohabitantBirthdateDisplayValue));
            $template->setValue('COHABITATION_DURATION', $normalizePlainText($cohabitationDurationValue !== '' ? $cohabitationDurationValue : $cohabitationSinceValue));
            $template->setValue('COHABITATION_SINCE', $normalizePlainText($cohabitationSinceValue));
            $template->setValue('COHABIRATION_DURATION', $normalizePlainText($cohabitationSinceValue));
            $template->setValue('ISSUED_ON', $normalizeText($issuedDateWord));
            $template->setValue('CHILDREN_LIST', implode("\n", $childrenListValue));
            for ($childIndex = 1; $childIndex <= 5; $childIndex += 1) {
                $template->setValue('CHILD_' . $childIndex . '_NAME', $normalizePlainText($cohabitationChildNames[$childIndex] ?? ''));
                $template->setValue('CHILD_' . $childIndex . '_AGE', $normalizePlainText($cohabitationChildAges[$childIndex] ?? ''));
            }

            $docxName = $fileStem . '.docx';
            $docxDiskPath = $outDir . '/' . $docxName;
            $template->saveAs($docxDiskPath);
            dra_restore_template_headers($templatePath, $docxDiskPath);
            dra_strip_unreplaced_docx_qr_placeholder($docxDiskPath);

            if (!is_file($docxDiskPath) || filesize($docxDiskPath) <= 0) {
                error_log('[dra_generate_issued_document][docx_template] generated docx is missing/empty');
                return null;
            }

            if ($preferDocxOutput) {
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . basename($docxDiskPath);
            }

            // DOCX-only workflow: keep output in DOCX format and do not run any server-side converter.
            return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . basename($docxDiskPath);
        } catch (Throwable $e) {
            error_log('[dra_generate_issued_document][docx_template] ' . $e->getMessage());
            return null;
        }
    }

    if (!class_exists('FPDF')) {
        $fpdfPaths = [
            __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($fpdfPaths as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                if (class_exists('FPDF')) {
                    break;
                }
            }
        }
    }
    if (!class_exists('FPDF')) {
        return null;
    }

    $renderRevisionTag = 'r20260312y';
    $fileName = 'issued_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '_' . $renderRevisionTag . '_' . date('YmdHis') . '.pdf';
    $diskPath = $outDir . '/' . $fileName;

    if ($isBusinessPermitClearance || $isGeneralPermitClearance || $isTricyclePermitClearance || $isRelationshipJailVisit) {
        if (!class_exists('\\setasign\\Fpdi\\Fpdi')) {
            $autoloadPaths = [
                __DIR__ . '/../../PhpFiles/PhpOffice/vendor/autoload.php',
                __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
                __DIR__ . '/../../vendor/autoload.php',
            ];
            foreach ($autoloadPaths as $autoloadPath) {
                if (is_file($autoloadPath)) {
                    require_once $autoloadPath;
                    if (class_exists('\\setasign\\Fpdi\\Fpdi')) {
                        break;
                    }
                }
            }
        }
    }

    if ($isGeneralPermitClearance) {
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/GeneralClearance.pdf';
        if (class_exists('\\setasign\\Fpdi\\Fpdi') && is_file($templatePath)) {
            try {
                $displayAddress = strtoupper($stripTemplateTokens(dra_strip_area_from_address($address !== '' ? $address : $applicantResidenceAddress)));
                if ($displayAddress === '') {
                    $displayAddress = '-';
                }
                $generalLocation = strtoupper($stripTemplateTokens(dra_build_general_clearance_location($payload, $applicantResidenceAddress)));
                if ($generalLocation === '') {
                    $generalLocation = '-';
                }
                $remarksText = strtoupper($stripTemplateTokens((string)($payload['remarks'] ?? $payload['ownership_type'] ?? '')));
                if ($remarksText === '') {
                    $remarksText = '-';
                }
                $purposeText = strtoupper($stripTemplateTokens($generalPermitPurpose));
                if ($purposeText === '') {
                    $purposeText = strtoupper($stripTemplateTokens((string)($payload['request_purpose'] ?? $purpose)));
                }
                if ($purposeText === '') {
                    $purposeText = 'PURPOSE';
                }
                $clearanceNumber = strtoupper($stripTemplateTokens($certNo !== '' ? $certNo : $requestId));
                $orNumberText = strtoupper($stripTemplateTokens($orNo));
                $amountNumeric = null;
                if (isset($requestRow['amount']) && $requestRow['amount'] !== null && $requestRow['amount'] !== '') {
                    $amountNumeric = (float)$requestRow['amount'];
                } elseif ($defaultFee !== null) {
                    $amountNumeric = (float)$defaultFee;
                }
                $amountText = $amountNumeric === null ? '' : number_format($amountNumeric, 2, '.', ',');

                $pdf = new \setasign\Fpdi\Fpdi();
                $pageCount = $pdf->setSourceFile($templatePath);
                if ($pageCount <= 0) {
                    throw new RuntimeException('Template PDF has no readable pages.');
                }
                $tpl = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($tpl);
                $pageWidth = (float)($size['width'] ?? 215.9);
                $pageHeight = (float)($size['height'] ?? 279.4);
                $orientation = $pageWidth > $pageHeight ? 'L' : 'P';
                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($tpl);
                $pdf->SetAutoPageBreak(false);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetTextColor(0, 0, 0);

                $normalizeTop = static function (float $ratio) use ($pageHeight): float {
                    return $ratio * $pageHeight;
                };
                $ocrTop = static function (float $originY, float $height) use ($normalizeTop): float {
                    return $normalizeTop(1.0 - $originY - $height);
                };
                $fillBox = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    float $padX = 1.3,
                    float $padY = 0.7
                ): void {
                    $pdfInstance->Rect(
                        max(0.0, $x - $padX),
                        max(0.0, $y - $padY),
                        max(1.0, $w + ($padX * 2)),
                        max(1.0, $h + ($padY * 2)),
                        'F'
                    );
                };
                $writeFittedCell = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    string $text,
                    string $style = '',
                    float $fontSize = 9.2,
                    float $minFontSize = 6.8,
                    string $align = 'L'
                ): void {
                    $clean = trim((string)(preg_replace('/\s+/u', ' ', $text) ?? $text));
                    if ($clean === '') {
                        return;
                    }
                    for ($size = $fontSize; $size >= $minFontSize; $size -= 0.2) {
                        $pdfInstance->SetFont('Arial', $style, $size);
                        if ($pdfInstance->GetStringWidth($clean) <= $w) {
                            break;
                        }
                    }
                    $pdfInstance->SetFont('Arial', $style, max($minFontSize, $size));
                    $pdfInstance->SetXY($x, $y);
                    $pdfInstance->Cell($w, $h, $clean, 0, 0, $align);
                };

                $valueColumnX = 82.5;
                $valueColumnW = $pageWidth - $valueColumnX - 16.0;
                $valueRows = [
                    ['y' => $ocrTop(0.6504794085013843, 0.02316908972603926), 'text' => strtoupper($fullName !== '' ? $fullName : 'RESIDENT'), 'font' => 9.1],
                    ['y' => $ocrTop(0.6335714282970502, 0.023571428571428577), 'text' => $displayAddress, 'font' => 8.8],
                    ['y' => $ocrTop(0.598571428541357, 0.01642857142857146), 'text' => $generalLocation, 'font' => 8.8],
                    ['y' => $ocrTop(0.5799418605137163, 0.014534883499145446), 'text' => $remarksText, 'font' => 8.8],
                    ['y' => $ocrTop(0.5594079238776235, 0.020719037055969336), 'text' => $purposeText, 'font' => 8.8],
                ];
                foreach ($valueRows as $rowValue) {
                    $y = (float)$rowValue['y'];
                    $fillBox($pdf, $valueColumnX, $y, $valueColumnW, 5.0, 1.2, 0.6);
                    $writeFittedCell($pdf, $valueColumnX, $y, $valueColumnW, 4.8, (string)$rowValue['text'], 'B', (float)$rowValue['font'], 6.6, 'L');
                }

                $issuedBlockX = 23.0;
                $issuedBlockY = $ocrTop(0.46214285744152395, 0.01928571428571424);
                $issuedBlockW = $pageWidth - 46.0;
                $issuedBlockH = 12.2;
                $fillBox($pdf, $issuedBlockX, $issuedBlockY, $issuedBlockW, $issuedBlockH, 2.0, 0.9);
                $pdf->SetFont('Arial', '', 8.8);
                $pdf->SetXY($issuedBlockX, $issuedBlockY);
                $pdf->MultiCell(
                    $issuedBlockW,
                    4.2,
                    'Issued this ' . $issuedAsDocx . ' at the office of the Punong Barangay, Barangay' . "\n" . 'San Jose, Montalban, Rizal',
                    0,
                    'C'
                );

                $metaRows = [
                    ['x' => 43.0, 'w' => 68.0, 'y' => $ocrTop(0.40552325622606245, 0.018895348140171575), 'text' => $clearanceNumber, 'font' => 8.8],
                    ['x' => 42.0, 'w' => 38.0, 'y' => $ocrTop(0.34842425230413754, 0.019721262795584615), 'text' => $amountText, 'font' => 8.8],
                    ['x' => 45.5, 'w' => 40.0, 'y' => $ocrTop(0.32928909936228945, 0.021654359272548218), 'text' => $orNumberText, 'font' => 8.8],
                ];
                foreach ($metaRows as $metaRow) {
                    $y = (float)$metaRow['y'];
                    $x = (float)$metaRow['x'];
                    $w = (float)$metaRow['w'];
                    $fillBox($pdf, $x, $y, $w, 5.0, 1.2, 0.6);
                    $writeFittedCell($pdf, $x, $y, $w, 4.8, (string)$metaRow['text'], 'B', (float)$metaRow['font'], 6.6, 'L');
                }

                if (is_file($qrDiskPath)) {
                    $qrSize = 20.0;
                    $pdf->Image($qrDiskPath, ($pageWidth - $qrSize) / 2, $pageHeight - 55.0, $qrSize, $qrSize);
                }

                $pdf->Output('F', $diskPath);
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
            } catch (Throwable $e) {
                error_log('[dra_generate_issued_document][general_clearance_fpdi] ' . $e->getMessage());
            }
        }
    }

    if ($isTricyclePermitClearance) {
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/TricycleClearance.pdf';
        if (class_exists('\\setasign\\Fpdi\\Fpdi') && is_file($templatePath)) {
            try {
                $franchisee = strtoupper($stripTemplateTokens((string)($payload['_preview_franchisee'] ?? $payload['franchisee'] ?? $payload['vehicle_franchise'] ?? '')));
                $vehicleType = strtoupper($stripTemplateTokens((string)($payload['_preview_vehicle_type'] ?? $payload['vehicle_make'] ?? $payload['type_of_vehicle'] ?? '')));
                if ($vehicleType === '') {
                    $vehicleType = 'TRICYCLE';
                }
                $registrationNumber = strtoupper($stripTemplateTokens((string)($payload['_preview_registration_number'] ?? $payload['cr_number'] ?? $payload['registration_number'] ?? '')));
                if ($registrationNumber === '') {
                    $registrationNumber = strtoupper($stripTemplateTokens((string)($payload['or_number'] ?? '')));
                }
                $plateNumber = strtoupper($stripTemplateTokens((string)($payload['_preview_plate_number'] ?? $payload['plate_number'] ?? $payload['vehicle_plate_number'] ?? '')));
                $bodyNumber = strtoupper($stripTemplateTokens((string)($payload['_preview_body_number'] ?? $payload['body_number'] ?? '')));
                $clearanceNumber = strtoupper($stripTemplateTokens($certNo !== '' ? $certNo : $requestId));
                $receiptNumber = strtoupper($stripTemplateTokens($orNo));
                $displayName = strtoupper($fullName !== '' ? $fullName : 'RESIDENT');
                $displayAddress = strtoupper($stripTemplateTokens($address !== '' ? $address : dra_strip_area_from_address($applicantResidenceAddress)));
                if ($displayAddress === '') {
                    $displayAddress = '-';
                }
                $amountNumeric = null;
                if (isset($requestRow['amount']) && $requestRow['amount'] !== null && $requestRow['amount'] !== '') {
                    $amountNumeric = (float)$requestRow['amount'];
                } elseif ($defaultFee !== null) {
                    $amountNumeric = (float)$defaultFee;
                }
                $amountText = $amountNumeric === null ? '' : number_format($amountNumeric, 2, '.', ',');

                $pdf = new \setasign\Fpdi\Fpdi();
                $pageCount = $pdf->setSourceFile($templatePath);
                if ($pageCount <= 0) {
                    throw new RuntimeException('Template PDF has no readable pages.');
                }
                $tpl = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($tpl);
                $pageWidth = (float)($size['width'] ?? 215.9);
                $pageHeight = (float)($size['height'] ?? 279.4);
                $orientation = $pageWidth > $pageHeight ? 'L' : 'P';
                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($tpl);
                $pdf->SetAutoPageBreak(false);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetTextColor(0, 0, 0);

                $normalizeTop = static function (float $ratio) use ($pageHeight): float {
                    return $ratio * $pageHeight;
                };
                $ocrTop = static function (float $originY, float $height) use ($normalizeTop): float {
                    return $normalizeTop(1.0 - $originY - $height);
                };
                $fillBox = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    float $padX = 1.3,
                    float $padY = 0.7
                ): void {
                    $pdfInstance->Rect(
                        max(0.0, $x - $padX),
                        max(0.0, $y - $padY),
                        max(1.0, $w + ($padX * 2)),
                        max(1.0, $h + ($padY * 2)),
                        'F'
                    );
                };
                $writeFittedCell = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    string $text,
                    string $style = '',
                    float $fontSize = 9.2,
                    float $minFontSize = 6.8,
                    string $align = 'L'
                ): void {
                    $clean = trim((string)(preg_replace('/\s+/u', ' ', $text) ?? $text));
                    if ($clean === '') {
                        return;
                    }
                    for ($size = $fontSize; $size >= $minFontSize; $size -= 0.2) {
                        $pdfInstance->SetFont('Arial', $style, $size);
                        if ($pdfInstance->GetStringWidth($clean) <= $w) {
                            break;
                        }
                    }
                    $pdfInstance->SetFont('Arial', $style, max($minFontSize, $size));
                    $pdfInstance->SetXY($x, $y);
                    $pdfInstance->Cell($w, $h, $clean, 0, 0, $align);
                };

                $valueColumnX = 84.5;
                $valueColumnW = $pageWidth - $valueColumnX - 19.0;
                $valueRows = [
                    ['y' => $ocrTop(0.6342979310969139, 0.019737470944722446), 'text' => $displayName, 'font' => 9.4],
                    ['y' => $ocrTop(0.6146032120973017, 0.022460242907206274), 'text' => $displayAddress, 'font' => 9.1],
                    ['y' => $ocrTop(0.5799999999000001, 0.016666666666666607), 'text' => $franchisee !== '' ? $franchisee : '-', 'font' => 8.9],
                    ['y' => $ocrTop(0.5616666667380952, 0.01666666666666672), 'text' => $vehicleType, 'font' => 9.0],
                    ['y' => $ocrTop(0.5400000001805556, 0.021666666666666612), 'text' => $registrationNumber !== '' ? $registrationNumber : '-', 'font' => 9.0],
                    ['y' => $ocrTop(0.52166666685, 0.018333333333333313), 'text' => $plateNumber !== '' ? $plateNumber : '-', 'font' => 9.0],
                    ['y' => $ocrTop(0.5016666664375, 0.018333333333333313), 'text' => $bodyNumber !== '' ? $bodyNumber : '-', 'font' => 9.0],
                ];
                foreach ($valueRows as $rowValue) {
                    $y = (float)$rowValue['y'];
                    $fillBox($pdf, $valueColumnX, $y, $valueColumnW, 5.2, 1.2, 0.6);
                    $writeFittedCell($pdf, $valueColumnX, $y, $valueColumnW, 5.0, (string)$rowValue['text'], '', (float)$rowValue['font'], 6.8, 'L');
                }

                $issuedBlockX = 25.0;
                $issuedBlockY = $ocrTop(0.4066666666166666, 0.018333333333333313);
                $issuedBlockW = $pageWidth - 50.0;
                $issuedBlockH = 13.5;
                $fillBox($pdf, $issuedBlockX, $issuedBlockY, $issuedBlockW, $issuedBlockH, 2.0, 0.9);
                $pdf->SetFont('Arial', '', 8.8);
                $pdf->SetXY($issuedBlockX, $issuedBlockY);
                $pdf->MultiCell(
                    $issuedBlockW,
                    4.3,
                    'Issued this ' . $issuedAsDocx . ' at the office of the Punong Barangay, Barangay' . "\n" . 'San Jose, Montalban, Rizal',
                    0,
                    'C'
                );

                $metaValueX = 57.0;
                $metaValueW = 78.0;
                $metaRows = [
                    ['y' => $ocrTop(0.3499999998833333, 0.021666666666666723), 'text' => $clearanceNumber !== '' ? $clearanceNumber : $requestId, 'font' => 9.0],
                    ['y' => $ocrTop(0.3316666665000001, 0.019999999999999907), 'text' => $receiptNumber, 'font' => 9.0],
                    ['y' => $ocrTop(0.3132073218646825, 0.020252022743225018), 'text' => $amountText, 'font' => 9.0],
                ];
                foreach ($metaRows as $metaRow) {
                    $y = (float)$metaRow['y'];
                    $fillBox($pdf, $metaValueX, $y, $metaValueW, 5.2, 1.2, 0.6);
                    $writeFittedCell($pdf, $metaValueX, $y, $metaValueW, 5.0, (string)$metaRow['text'], '', (float)$metaRow['font'], 6.8, 'L');
                }

                if (is_file($qrDiskPath)) {
                    $qrSize = 20.0;
                    $pdf->Image($qrDiskPath, ($pageWidth - $qrSize) / 2, $pageHeight - 49.0, $qrSize, $qrSize);
                }

                $pdf->Output('F', $diskPath);
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
            } catch (Throwable $e) {
                error_log('[dra_generate_issued_document][tricycle_clearance_fpdi] ' . $e->getMessage());
            }
        }
    }

    if ($isBusinessPermitClearance) {
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/ClearanceForBusinessPermit.pdf';
        if (class_exists('\\setasign\\Fpdi\\Fpdi') && is_file($templatePath)) {
            try {
                $businessName = $stripTemplateTokens((string)($payload['business_name'] ?? ''));
                $businessApprovalType = dra_normalize_business_approval_type((string)($payload['_preview_business_approval_type'] ?? $payload['business_approval_type'] ?? ''));
                $plateNumber = strtoupper($stripTemplateTokens((string)($payload['_preview_plate_number'] ?? $payload['plate_number'] ?? $payload['business_plate_number'] ?? '')));
                $businessAddress = $stripTemplateTokens((string)($payload['business_full_address'] ?? $payload['location'] ?? ''));
                if ($businessAddress === '') {
                    $businessAddress = $applicantResidenceAddress;
                }
                $operatorName = $fullName !== '' ? $fullName : 'RESIDENT';
                $operatorAddress = $applicantAddressWithBarangay !== '' ? $applicantAddressWithBarangay : $applicantResidenceAddress;
                $amountNumeric = null;
                if (isset($requestRow['amount']) && $requestRow['amount'] !== null && $requestRow['amount'] !== '') {
                    $amountNumeric = (float)$requestRow['amount'];
                } elseif ($defaultFee !== null) {
                    $amountNumeric = (float)$defaultFee;
                }
                $amountText = $amountNumeric === null ? '' : number_format($amountNumeric, 2, '.', ',');

                $pdf = new \setasign\Fpdi\Fpdi();
                $pageCount = $pdf->setSourceFile($templatePath);
                if ($pageCount <= 0) {
                    throw new RuntimeException('Template PDF has no readable pages.');
                }
                $tpl = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($tpl);
                $pageWidth = (float)($size['width'] ?? 215.9);
                $pageHeight = (float)($size['height'] ?? 355.6);
                $orientation = $pageWidth > $pageHeight ? 'L' : 'P';
                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($tpl);
                $pdf->SetAutoPageBreak(false);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetTextColor(0, 0, 0);

                $normalizeTop = static function (float $value) use ($pageHeight): float {
                    return $value * $pageHeight;
                };
                $fillBox = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    float $padX = 1.5,
                    float $padY = 0.9
                ): void {
                    $pdfInstance->Rect(
                        max(0.0, $x - $padX),
                        max(0.0, $y - $padY),
                        max(1.0, $w + ($padX * 2)),
                        max(1.0, $h + ($padY * 2)),
                        'F'
                    );
                };
                $writeFittedCell = static function (
                    \setasign\Fpdi\Fpdi $pdfInstance,
                    float $x,
                    float $y,
                    float $w,
                    float $h,
                    string $text,
                    string $style = '',
                    float $fontSize = 10.0,
                    float $minFontSize = 7.0,
                    string $align = 'L'
                ): void {
                    $clean = trim((string)(preg_replace('/\s+/u', ' ', $text) ?? $text));
                    if ($clean === '') {
                        return;
                    }
                    for ($size = $fontSize; $size >= $minFontSize; $size -= 0.2) {
                        $pdfInstance->SetFont('Arial', $style, $size);
                        if ($pdfInstance->GetStringWidth($clean) <= $w) {
                            break;
                        }
                    }
                    $pdfInstance->SetFont('Arial', $style, max($minFontSize, $size));
                    $pdfInstance->SetXY($x, $y);
                    $pdfInstance->Cell($w, $h, $clean, 0, 0, $align);
                };

                $bodyLeft = 32.0;
                $bodyWidth = $pageWidth - ($bodyLeft * 2);
                $bodyLineHeight = 5.8;
                $bodyValues = [
                    [$normalizeTop(0.2575), strtoupper($businessName !== '' ? $businessName : '-')],
                    [$normalizeTop(0.2912), strtoupper($businessAddress !== '' ? $businessAddress : '-')],
                    [$normalizeTop(0.3245), strtoupper($operatorName !== '' ? $operatorName : '-')],
                    [$normalizeTop(0.3580), strtoupper($operatorAddress !== '' ? $operatorAddress : '-')],
                ];
                foreach ($bodyValues as [$topY, $value]) {
                    $fillBox($pdf, $bodyLeft, $topY, $bodyWidth, 6.4);
                    $writeFittedCell($pdf, $bodyLeft, $topY, $bodyWidth, $bodyLineHeight, (string)$value, 'B', 10.2, 7.2, 'C');
                }

                $approvalMarkers = [
                    ['key' => 'not_banned', 'lineY' => $normalizeTop(0.5030)],
                    ['key' => 'no_objection', 'lineY' => $normalizeTop(0.5287)],
                    ['key' => 'temporary_clearance', 'lineY' => $normalizeTop(0.5545)],
                ];
                foreach ($approvalMarkers as $approvalMarker) {
                    $lineY = (float)$approvalMarker['lineY'];
                    $pdf->Rect(19.5, $lineY - 7.0, 17.2, 13.0, 'F');
                    $pdf->SetDrawColor(0, 0, 0);
                    $pdf->SetLineWidth(0.65);
                    $pdf->Line(21.0, $lineY + 0.2, 25.4, $lineY + 0.2);
                    $pdf->Line(30.1, $lineY + 0.2, 34.5, $lineY + 0.2);
                    if ($businessApprovalType === $approvalMarker['key']) {
                        $pdf->SetFont('ZapfDingbats', '', 16.0);
                        $pdf->SetXY(24.9, $lineY - 6.0);
                        $pdf->Cell(6.0, 6.0, chr(51), 0, 0, 'C');
                    }
                    $pdf->SetLineWidth(0.2);
                }

                // The source permit PDF already contains a literal ${ISSUED_DATE_WORD} footer,
                // so the generated copy needs to overwrite that footer in place.
                $issuedBlockX = 26.0;
                $issuedMaskY = $normalizeTop(0.6170);
                $issuedBlockY = $normalizeTop(0.6350);
                $issuedBlockW = $pageWidth - 52.0;
                $issuedBlockH = 19.0;
                $pdf->Rect(24.0, $issuedMaskY, $issuedBlockW + 4.0, $issuedBlockH, 'F');
                $pdf->SetFont('Arial', '', 9.0);
                $pdf->SetXY($issuedBlockX, $issuedBlockY);
                $pdf->MultiCell(
                    $issuedBlockW,
                    4.2,
                    'Issued this ' . $issuedAsDocx . ' at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal.',
                    0,
                    'C'
                );

                $metaBlockX = 19.0;
                $metaBlockY = $normalizeTop(0.8290);
                $metaBlockW = 92.0;
                $metaBlockH = 27.5;
                $fillBox($pdf, $metaBlockX, $metaBlockY, $metaBlockW, $metaBlockH, 2.0, 1.0);
                $metaRows = [
                    ['label' => 'O.R No.', 'value' => $orNo],
                    ['label' => 'Amount', 'value' => $amountText],
                    ['label' => 'Plate No.', 'value' => $plateNumber],
                    ['label' => 'Date Issued', 'value' => $issuedAt],
                    ['label' => 'Place Issued', 'value' => 'Barangay San Jose'],
                ];
                $metaY = $metaBlockY + 0.5;
                foreach ($metaRows as $rowMeta) {
                    $pdf->SetFont('Arial', '', 9.5);
                    $pdf->SetXY($metaBlockX + 2.0, $metaY);
                    $pdf->Cell(28.0, 5.2, $rowMeta['label'], 0, 0, 'L');
                    $pdf->Cell(4.0, 5.2, ':', 0, 0, 'C');
                    $writeFittedCell($pdf, $metaBlockX + 34.5, $metaY, 50.0, 5.2, (string)$rowMeta['value'], '', 9.5, 7.2, 'L');
                    $metaY += 5.0;
                }

                if (is_file($qrDiskPath)) {
                    $qrSize = 22.0;
                    $qrRightMargin = 8.5;
                    $qrBottomMargin = 12.0;
                    $pdf->Image(
                        $qrDiskPath,
                        $pageWidth - $qrSize - $qrRightMargin,
                        $pageHeight - $qrSize - $qrBottomMargin,
                        $qrSize,
                        $qrSize
                    );
                }

                $pdf->Output('F', $diskPath);
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
            } catch (Throwable $e) {
                error_log('[dra_generate_issued_document][business_clearance_fpdi] ' . $e->getMessage());
            }
        }
    }

    if ($isRelationshipJailVisit) {
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/CertificateForJailVisitation.pdf';
        if (class_exists('\\setasign\\Fpdi\\Fpdi') && is_file($templatePath)) {
            try {
                $detainedRelationship = $stripTemplateTokens((string)($payload['cohabitant_relationship'] ?? ''));
                $detainedName = $stripTemplateTokens((string)($payload['cohabitant_full_name'] ?? ''));
                if ($detainedName === '') {
                    $detainedName = trim(implode(' ', array_filter([
                        $stripTemplateTokens((string)($payload['cohabitant_first'] ?? '')),
                        $stripTemplateTokens((string)($payload['cohabitant_middle'] ?? '')) !== ''
                            ? strtoupper(substr($stripTemplateTokens((string)($payload['cohabitant_middle'] ?? '')), 0, 1)) . '.'
                            : '',
                        $stripTemplateTokens((string)($payload['cohabitant_last'] ?? '')),
                        $stripTemplateTokens((string)($payload['cohabitant_suffix'] ?? '')),
                    ], static fn($v) => trim((string)$v) !== '')));
                }
                $detentionFacility = $stripTemplateTokens((string)($payload['_preview_detention_facility'] ?? $payload['detention_facility'] ?? ''));
                if (strcasecmp($detentionFacility, 'Other') === 0) {
                    $detentionFacility = $stripTemplateTokens((string)($payload['detention_facility_other'] ?? ''));
                }

                $pdf = new \setasign\Fpdi\Fpdi();
                $pageCount = $pdf->setSourceFile($templatePath);
                if ($pageCount <= 0) {
                    throw new RuntimeException('Template PDF has no readable pages.');
                }
                $tpl = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($tpl);
                $pageWidth = (float)($size['width'] ?? 216.0);
                $pageHeight = (float)($size['height'] ?? 279.0);
                $orientation = $pageWidth > $pageHeight ? 'L' : 'P';
                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($tpl);
                $pdf->SetAutoPageBreak(false);

                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect(18.0, 70.0, 180.0, 30.0, 'F');
                $pdf->Rect(30.0, 101.0, 154.0, 8.0, 'F');
                $pdf->Rect(18.0, 112.0, 180.0, 14.0, 'F');

                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', '', 9.5);
                $pdf->SetXY(28.0, 73.0);
                $pdf->MultiCell(
                    160.0,
                    4.8,
                    '      This is to certify ' . ($fullName !== '' ? $fullName : '-') . ', resident of ' . ($applicantResidenceAddress !== '' ? $applicantResidenceAddress : '-') . ' is personally known to be as a person of GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.',
                    0,
                    'J'
                );

                $pdf->SetXY(31.0, 101.5);
                $pdf->MultiCell(
                    152.0,
                    4.8,
                    '      Moreover, this certifies that the subject person is the ' . ($detainedRelationship !== '' ? strtoupper($detainedRelationship) : '-') . ' of DETAINED ' . ($detainedName !== '' ? strtoupper($detainedName) : '-') . ' and presently at the ' . ($detentionFacility !== '' ? strtoupper($detentionFacility) : '-') . '.',
                    0,
                    'J'
                );

                $pdf->SetXY(28.0, 114.5);
                $pdf->MultiCell(
                    160.0,
                    4.8,
                    '      Issued this ' . $issuedAsDocx . ' at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal.',
                    0,
                    'L'
                );

                if (is_file($qrDiskPath)) {
                    $pdf->Image($qrDiskPath, 186.0, 252.0, 20.0, 20.0);
                }

                $pdf->Output('F', $diskPath);
                return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
            } catch (Throwable $e) {
                error_log('[dra_generate_issued_document][jail_fpdi_fallback] ' . $e->getMessage());
            }
        }
    }

    // Use short bond paper (8.5x11) instead of A4.
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetMargins(18, 16, 18);
    $pdf->AddPage();
    // Keep generated output to a single page.
    $pdf->SetAutoPageBreak(false);

    $leftLogo = $baseDir . '/Images/San_Jose_LOGO.jpg';
    if (is_file($leftLogo)) {
        $pdf->Image($leftLogo, 18, 14, 26, 26);
    }
    $rightLogo = $baseDir . '/Images/Montalban_Logo.png';
    if (is_file($rightLogo)) {
        $pdf->Image($rightLogo, 168, 14, 26, 26);
    }
    $isSpecialCertificate = $isIndigency || $isGoodMoral || $isResidency || $isCohabitation || $isFirstTimeJobSeeker;
    $fontFace = 'Times';
    $indigencyFont = 'Arial';

    $pdf->SetFont($fontFace, 'B', 11);
    $pdf->Cell(0, 5, 'REPUBLIKA NG PILIPINAS', 0, 1, 'C');
    $pdf->SetFont($fontFace, '', 10);
    $pdf->Cell(0, 5, 'LALAWIGAN NG RIZAL', 0, 1, 'C');
    $pdf->Cell(0, 5, 'BAYAN NG RODRIGUEZ', 0, 1, 'C');
    $pdf->Ln(1);
    $pdf->SetFont($fontFace, 'B', 16);
    $pdf->Cell(0, 7, 'BARANGAY SAN JOSE', 0, 1, 'C');
    if ($isSpecialCertificate) {
        $pdf->Ln(1);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(4);
        if ($isIndigency) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'CERTIFICATE OF INDIGENCY', 0, 1, 'C');
            $pdf->Ln(4);
        } elseif ($isResidency) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'CERTIFICATE OF RESIDENCY', 0, 1, 'C');
            $pdf->Ln(4);
        } elseif ($isRelationshipJailVisit) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'BARANGAY CERTIFICATION', 0, 1, 'C');
            $pdf->Ln(4);
        } elseif ($isCohabitation) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'CERTIFICATE OF COHABITATION', 0, 1, 'C');
            $pdf->Ln(4);
        } elseif ($isFirstTimeJobSeeker) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'BARANGAY CERTIFICATION', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 9);
            $pdf->Cell(0, 5, '(First Time Jobseekers Act-RA 11261)', 0, 1, 'C');
            $pdf->Ln(3);
        } else {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 7, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 6, 'BARANGAY CERTIFICATION', 0, 1, 'C');
            $pdf->Ln(4);
        }
    } else {
        $pdf->SetFont($fontFace, 'B', 12);
        $pdf->Cell(0, 6, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(8);
        $pdf->SetFont($fontFace, 'B', 14);
        $pdf->Cell(0, 8, strtoupper($docType), 0, 1, 'C');
        $pdf->Ln(6);
    }

    $pdf->SetFont($fontFace, '', 12);
    $writeRichParagraph = function (
        array $segments,
        float $lineHeight,
        float $leftMargin,
        float $indent,
        string $fontFamily,
        float $fontSize,
        float $restoreLeftMargin = 18.0,
        bool $justify = false
    ) use ($pdf): void {
        $tokens = [];
        foreach ($segments as $segment) {
            $text = (string)($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $bold = !empty($segment['bold']);
            $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (!is_array($parts)) {
                $parts = [$text];
            }
            foreach ($parts as $part) {
                $tokens[] = ['text' => $part, 'bold' => $bold];
            }
        }
        if (empty($tokens)) {
            return;
        }

        $contentWidth = $pdf->GetPageWidth() - $leftMargin - $restoreLeftMargin;
        $firstLineWidth = max(1.0, $contentWidth - $indent);

        $measureToken = function (array $token) use ($pdf, $fontFamily, $fontSize): float {
            $pdf->SetFont($fontFamily, !empty($token['bold']) ? 'B' : '', $fontSize);
            return $pdf->GetStringWidth((string)($token['text'] ?? ''));
        };

        $lines = [];
        $line = [];
        $lineWidth = 0.0;
        $lineMax = $firstLineWidth;

        foreach ($tokens as $token) {
            $text = (string)($token['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $width = $measureToken($token);

            if (!empty($line) && ($lineWidth + $width) > $lineMax) {
                while (!empty($line) && preg_match('/^\s+$/u', (string)$line[count($line) - 1]['text'])) {
                    $removed = array_pop($line);
                    if ($removed !== null) {
                        $lineWidth -= $measureToken($removed);
                    }
                }
                if (!empty($line)) {
                    $lines[] = $line;
                }
                $line = [];
                $lineWidth = 0.0;
                $lineMax = $contentWidth;
                if (preg_match('/^\s+$/u', $text)) {
                    continue;
                }
            }

            $line[] = $token;
            $lineWidth += $width;
        }
        if (!empty($line)) {
            $lines[] = $line;
        }

        $y = $pdf->GetY();
        foreach ($lines as $lineIndex => $lineTokens) {
            $x = $leftMargin + ($lineIndex === 0 ? $indent : 0.0);
            $pdf->SetXY($x, $y);
            $targetWidth = $lineIndex === 0 ? $firstLineWidth : $contentWidth;
            $lineWidthActual = 0.0;
            $spaceTokenIndexes = [];
            foreach ($lineTokens as $tokenIndex => $token) {
                $text = (string)($token['text'] ?? '');
                $width = $measureToken($token);
                $lineWidthActual += $width;
                if (preg_match('/^\s+$/u', $text)) {
                    $spaceTokenIndexes[] = $tokenIndex;
                }
            }
            $shouldJustify = !empty($justify)
                && $lineIndex < (count($lines) - 1)
                && count($spaceTokenIndexes) > 0
                && $lineWidthActual < $targetWidth;
            $extraPerSpace = $shouldJustify ? (($targetWidth - $lineWidthActual) / count($spaceTokenIndexes)) : 0.0;
            foreach ($lineTokens as $token) {
                $text = (string)($token['text'] ?? '');
                $pdf->SetFont($fontFamily, !empty($token['bold']) ? 'B' : '', $fontSize);
                $w = $pdf->GetStringWidth($text);
                if ($shouldJustify && preg_match('/^\s+$/u', $text)) {
                    $w += $extraPerSpace;
                }
                $pdf->Cell($w, $lineHeight, $text, 0, 0, 'L');
            }
            $y += $lineHeight;
        }

        $pdf->SetLeftMargin($restoreLeftMargin);
        $pdf->SetX($restoreLeftMargin);
        $pdf->SetY($y);
    };
    $writeIndentedParagraph = function (
        string $text,
        float $lineHeight,
        float $leftMargin,
        float $indent,
        string $fontFamily,
        float $fontSize,
        float $restoreLeftMargin = 18.0,
        bool $justify = false
    ) use ($writeRichParagraph): void {
        $writeRichParagraph(
            [['text' => $text, 'bold' => false]],
            $lineHeight,
            $leftMargin,
            $indent,
            $fontFamily,
            $fontSize,
            $restoreLeftMargin,
            $justify
        );
    };
    if ($isSpecialCertificate) {
        $fixedQrSize = 20.0;
        $fixedQrX = 186.0;
        $fixedQrY = 252.0;
        if (!$isFirstTimeJobSeeker && is_file($qrDiskPath)) {
            // Keep QR pinned to the same visible bottom-right position across generated special certificates.
            $pdf->Image($qrDiskPath, $fixedQrX, $fixedQrY, $fixedQrSize, $fixedQrSize);
        }
        $normalizeTemplateValue = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            $value = (string)(preg_replace('/\$\{[A-Z0-9_]+\}/', '', $value) ?? $value);
            $collapsed = preg_replace('/\s+/u', ' ', $value);
            return trim((string)$collapsed);
        };
        $formatDisplayMonthYear = static function (string $value): string {
            $raw = trim($value);
            if ($raw === '') {
                return '';
            }
            if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                try {
                    return (new DateTime($raw . '-01'))->format('F Y');
                } catch (Throwable $ignored) {
                    return $raw;
                }
            }
            return $raw;
        };
        $formatDisplayDate = static function (string $value): string {
            $raw = trim($value);
            if ($raw === '') {
                return '';
            }
            try {
                return (new DateTime($raw))->format('F d, Y');
            } catch (Throwable $ignored) {
                return $raw;
            }
        };
        $requestOfficer = $normalizeTemplateValue((string)($payload['request_officer'] ?? ''));
        $requestOfficerLine1 = $normalizeTemplateValue((string)($payload['request_officer_line1'] ?? ''));
        $requestOfficerLine2 = $normalizeTemplateValue((string)($payload['request_officer_line2'] ?? ''));
        $requestOfficerLine3 = $normalizeTemplateValue((string)($payload['request_officer_line3'] ?? ''));
        $requestPurpose = $normalizeTemplateValue((string)($payload['request_purpose'] ?? $purpose));
        if ($requestPurpose === '') {
            $requestPurpose = 'PURPOSE';
        }
        $birthdateValue = $normalizeTemplateValue((string)($payload['birthdate'] ?? $payload['date_of_birth'] ?? $payload['child_dob'] ?? $payload['birthDate'] ?? ''));
        $birthplaceValue = $normalizeTemplateValue((string)($payload['birthplace'] ?? $payload['place_of_birth'] ?? ''));
        $remarksValue = $normalizeTemplateValue((string)($payload['remarks'] ?? ''));
        $cohabitationChildrenCount = max(0, (int)trim((string)($payload['cohabitation_children_count'] ?? '0')));
        $cohabitationHasChildren = $cohabitationChildrenCount > 0;
        $cohabitantBirthdateValue = $normalizeTemplateValue((string)($payload['cohabitant_birthdate'] ?? $payload['cohabitant_dob'] ?? $payload['partner_birthdate'] ?? $payload['partner_dob'] ?? ''));
        if ($isCohabitation && $birthdateValue === '') {
            $residentUserId = trim((string)($requestRow['resident_user_id'] ?? ''));
            if ($residentUserId !== '') {
                $residentInfoStmt = $conn->prepare("
                    SELECT birthdate
                    FROM residentinformationtbl
                    WHERE user_id = ?
                    LIMIT 1
                ");
                if ($residentInfoStmt) {
                    $residentInfoStmt->bind_param('s', $residentUserId);
                    $residentInfoStmt->execute();
                    $residentInfo = $residentInfoStmt->get_result()->fetch_assoc() ?: [];
                    $residentInfoStmt->close();
                    $birthdateValue = $normalizeTemplateValue((string)($residentInfo['birthdate'] ?? $birthdateValue));
                }
            }
        }
        $cohabitantName = $normalizeTemplateValue((string)($payload['cohabitant_full_name'] ?? ''));
        if ($cohabitantName === '') {
            $cohabitantFirst = $normalizeTemplateValue((string)($payload['cohabitant_first'] ?? ''));
            $cohabitantMiddle = $normalizeTemplateValue((string)($payload['cohabitant_middle'] ?? ''));
            $cohabitantLast = $normalizeTemplateValue((string)($payload['cohabitant_last'] ?? ''));
            $cohabitantSuffix = $normalizeTemplateValue((string)($payload['cohabitant_suffix'] ?? ''));
            $cohabitantMi = $cohabitantMiddle !== '' ? strtoupper(substr($cohabitantMiddle, 0, 1)) . '.' : '';
            $cohabitantName = trim(implode(' ', array_filter([$cohabitantFirst, $cohabitantMi, $cohabitantLast, $cohabitantSuffix], static fn($v) => trim((string)$v) !== '')));
        }
        $cohabitantRelationshipValue = $normalizeTemplateValue((string)($payload['cohabitant_relationship'] ?? ''));
        $detentionFacilityValue = $normalizeTemplateValue((string)($payload['_preview_detention_facility'] ?? $payload['detention_facility'] ?? ''));
        if (strcasecmp($detentionFacilityValue, 'Other') === 0) {
            $detentionFacilityValue = $normalizeTemplateValue((string)($payload['detention_facility_other'] ?? ''));
        }
        $stripTrailingParenthetical = static function (string $value): string {
            return trim((string)preg_replace('/\s*\([^()]*\)\s*$/', '', trim($value)));
        };
        $cohabitationDurationValue = $stripTrailingParenthetical($normalizeTemplateValue((string)($payload['cohabitation_duration'] ?? '')));
        $cohabitationStartRaw = $stripTrailingParenthetical((string)($payload['cohabitation_start_date'] ?? ''));
        if ($cohabitationDurationValue === '') {
            $cohabitationDurationValue = $formatDisplayMonthYear($cohabitationStartRaw);
        }
        $cohabitationSinceValue = $formatDisplayMonthYear($cohabitationStartRaw);
        if ($cohabitationSinceValue === '') {
            $cohabitationSinceValue = $cohabitationDurationValue;
        }
        $birthdateDisplayValue = $formatDisplayDate($birthdateValue);
        $cohabitantBirthdateDisplayValue = $formatDisplayDate($cohabitantBirthdateValue);
        $cohabitationDurationFromMonths = static function (int $monthsTotal): string {
            if ($monthsTotal < 0) {
                $monthsTotal = 0;
            }
            $years = intdiv($monthsTotal, 12);
            $months = $monthsTotal % 12;
            $parts = [];
            if ($years > 0) {
                $parts[] = $years . ' ' . ($years === 1 ? 'year' : 'years');
            }
            if ($months > 0 || empty($parts)) {
                $parts[] = $months . ' ' . ($months === 1 ? 'month' : 'months');
            }
            return implode(' and ', $parts);
        };
        $cohabitationSinceDisplayValue = $cohabitationSinceValue;
        $cohabitationDurationMonths = max(0, (int)trim((string)($payload['cohabitation_duration_value'] ?? '0')));
        if ($cohabitationDurationMonths <= 0) {
            $durText = strtolower($stripTrailingParenthetical($cohabitationDurationValue));
            $durYears = 0;
            $durMonths = 0;
            if (preg_match('/(\d+)\s*year/', $durText, $m)) {
                $durYears = max(0, (int)$m[1]);
            }
            if (preg_match('/(\d+)\s*month/', $durText, $m)) {
                $durMonths = max(0, (int)$m[1]);
            }
            $cohabitationDurationMonths = ($durYears * 12) + $durMonths;
        }
        $cohabitationDurationPretty = $cohabitationDurationFromMonths($cohabitationDurationMonths);
        if ($cohabitationSinceValue !== '') {
            $cohabitationSinceDisplayValue = $cohabitationSinceValue . ' (' . $cohabitationDurationPretty . ')';
        } elseif ($cohabitationDurationValue !== '') {
            $cohabitationSinceDisplayValue = $stripTrailingParenthetical($cohabitationDurationValue);
        }
        $deriveAge = static function (string $rawDate): string {
            $rawDate = trim($rawDate);
            if ($rawDate === '') {
                return '';
            }
            try {
                $dob = new DateTime($rawDate);
                return (string)((new DateTime())->diff($dob)->y);
            } catch (Throwable $ignored) {
                return '';
            }
        };
        $extractAgeValue = static function (string $raw) use ($normalizeTemplateValue): string {
            $clean = $normalizeTemplateValue($raw);
            if ($clean === '') {
                return '';
            }
            if (preg_match('/(\d{1,3})/', $clean, $match)) {
                return (string)max(0, (int)$match[1]);
            }
            return '';
        };
        $ageValue = $extractAgeValue((string)($payload['age'] ?? ''));
        if ($ageValue === '') {
            $ageValue = $deriveAge($birthdateValue);
        }
        $cohabitantAgeValue = $extractAgeValue((string)($payload['cohabitant_age'] ?? $payload['partner_age'] ?? ''));
        if ($cohabitantAgeValue === '') {
            $cohabitantAgeValue = $deriveAge($cohabitantBirthdateValue);
        }
        $submissionTargetType = $normalizeTemplateValue((string)($payload['submission_target_type'] ?? ''));
        $institutionName = $normalizeTemplateValue((string)($payload['institution_name'] ?? ''));
        $institutionPerson = $normalizeTemplateValue((string)($payload['institution_person'] ?? ''));
        $institutionPosition = $normalizeTemplateValue((string)($payload['institution_position'] ?? ''));
        $governmentOffice = $normalizeTemplateValue((string)($payload['government_office'] ?? ''));
        if ($governmentOffice === '') {
            $governmentOffice = $normalizeTemplateValue((string)($payload['government_position_group'] ?? ''));
        }
        if ($governmentOffice === '__other__') {
            $governmentOffice = $normalizeTemplateValue((string)($payload['government_position_other'] ?? ''));
        }
        $governmentPosition = $normalizeTemplateValue((string)($payload['government_position'] ?? ''));
        if ($governmentPosition === '') {
            $governmentPosition = $normalizeTemplateValue((string)($payload['government_position_detail'] ?? ''));
        }
        $governmentOfficial = $normalizeTemplateValue((string)($payload['government_official'] ?? ''));
        if ($governmentOfficial === '') {
            $governmentOfficial = $normalizeTemplateValue((string)($payload['government_official_other'] ?? ''));
        }
        if ($submissionTargetType === 'institution') {
            $institutionParts = [$institutionName];
            $attentionParts = array_values(array_filter([$institutionPerson, $institutionPosition], static fn($value) => trim((string)$value) !== ''));
            if (!empty($attentionParts)) {
                $institutionParts[] = implode(', ', $attentionParts);
            }
            $requestOfficer = implode(' - ', array_filter($institutionParts, static fn($value) => trim((string)$value) !== ''));
            if ($requestOfficerLine1 === '') {
                $requestOfficerLine1 = trim((string)$institutionName);
            }
            if ($requestOfficerLine2 === '' && !empty($attentionParts)) {
                $requestOfficerLine2 = trim((string)implode(', ', $attentionParts));
            }
        } elseif ($submissionTargetType === 'government_official') {
            if ($governmentOfficial === '' && $requestOfficer !== '') {
                $governmentOfficial = $requestOfficer;
            }
            $requestOfficer = implode(' - ', array_filter([$governmentOfficial, $governmentPosition, $governmentOffice], static fn($value) => trim((string)$value) !== ''));
            if ($requestOfficerLine1 === '') {
                $requestOfficerLine1 = trim((string)$governmentOfficial);
            }
            if ($requestOfficerLine2 === '') {
                $requestOfficerLine2 = trim((string)$governmentPosition);
            }
            if ($requestOfficerLine3 === '') {
                $requestOfficerLine3 = trim((string)$governmentOffice);
            }
        }
        $yearsResidency = trim((string)($payload['years_of_residency'] ?? ''));
        $monthsResidency = trim((string)($payload['months_of_residency'] ?? ''));
        if ($isResidency) {
            $requestResidentId = trim((string)($requestRow['resident_id'] ?? ''));
            $profile = dra_resident_profile_snapshot($conn, $requestResidentId, $requestResidentId);
            $fromStartYm = dra_parse_residency_start_ym((string)($profile['barangay_residency'] ?? ''));
            $fromText = dra_parse_residency_duration_text((string)($profile['residency_duration'] ?? ''));

            $profileDuration = null;
            if (is_array($fromStartYm) && is_array($fromText)) {
                $profileDuration = dra_pick_longer_residency_duration($fromStartYm, $fromText);
            } elseif (is_array($fromStartYm)) {
                $profileDuration = $fromStartYm;
            } elseif (is_array($fromText)) {
                $profileDuration = $fromText;
            }

            if (is_array($profileDuration)) {
                $payloadDuration = [
                    'years' => (int)($yearsResidency !== '' ? $yearsResidency : 0),
                    'months' => (int)($monthsResidency !== '' ? $monthsResidency : 0),
                ];
                $bestDuration = dra_pick_longer_residency_duration($payloadDuration, $profileDuration);
                $totalMonths = dra_duration_total_months($bestDuration);
                $yearsResidency = (string)max(0, intdiv($totalMonths, 12));
                $monthsResidency = (string)max(0, $totalMonths % 12);
            }
        }
        $residencyParts = [];
        if ($yearsResidency !== '') {
            $residencyParts[] = $yearsResidency . ' year(s)';
        }
        if ($monthsResidency !== '') {
            $residencyParts[] = $monthsResidency . ' month(s)';
        }
        $residencyDurationText = !empty($residencyParts) ? implode(' and ', $residencyParts) : 'a stated period';
        $issuedDateObj = new DateTime();
        $day = (int)$issuedDateObj->format('j');
        $monthUpper = strtoupper($issuedDateObj->format('F'));
        $yearNum = $issuedDateObj->format('Y');
        $v = $day % 100;
        $suffix = ($v >= 11 && $v <= 13) ? 'th' : (($day % 10 === 1) ? 'st' : (($day % 10 === 2) ? 'nd' : (($day % 10 === 3) ? 'rd' : 'th')));
        $issuedAsDocx = $day . $suffix . ' day of ' . $monthUpper . ' ' . $yearNum;
        $firstTimeJobSeekerHonorific = 'MR./MS.';
        $firstTimeJobSeekerResidencySince = '';
        $firstTimeJobSeekerSignedDate = 'Date';
        if ($isFirstTimeJobSeeker) {
            $sexValue = strtolower(trim((string)($payload['sex'] ?? $payload['gender'] ?? '')));
            if ($sexValue !== '') {
                $firstTimeJobSeekerHonorific = str_starts_with($sexValue, 'm') ? 'MR.' : (str_starts_with($sexValue, 'f') ? 'MS.' : 'MR./MS.');
            }

            $residentUserId = trim((string)($requestRow['resident_user_id'] ?? ''));
            $residentId = trim((string)($requestRow['resident_id'] ?? ''));
            $residentProfile = dra_resident_profile_snapshot($conn, $residentUserId, $residentId);
            $residencyStartRaw = trim((string)($payload['barangay_residency'] ?? $residentProfile['barangay_residency'] ?? ''));
            $residencyDurationRaw = trim((string)($payload['residency_duration'] ?? $residentProfile['residency_duration'] ?? ''));
            $durationFromPayload = [
                'years' => max(0, (int)($yearsResidency !== '' ? $yearsResidency : 0)),
                'months' => max(0, (int)($monthsResidency !== '' ? $monthsResidency : 0)),
            ];
            $durationFromStart = dra_parse_residency_start_ym($residencyStartRaw);
            $durationFromText = dra_parse_residency_duration_text($residencyDurationRaw);
            $bestDuration = $durationFromPayload;
            if (is_array($durationFromStart) && is_array($durationFromText)) {
                $bestDuration = dra_pick_longer_residency_duration(dra_pick_longer_residency_duration($durationFromPayload, $durationFromStart), $durationFromText);
            } elseif (is_array($durationFromStart)) {
                $bestDuration = dra_pick_longer_residency_duration($durationFromPayload, $durationFromStart);
            } elseif (is_array($durationFromText)) {
                $bestDuration = dra_pick_longer_residency_duration($durationFromPayload, $durationFromText);
            }
            $durationYears = max(0, (int)($bestDuration['years'] ?? 0));
            $durationMonths = max(0, (int)($bestDuration['months'] ?? 0));
            $durationPrettyParts = [];
            if ($durationYears > 0) {
                $durationPrettyParts[] = $durationYears . ' ' . ($durationYears === 1 ? 'year' : 'years');
            }
            if ($durationMonths > 0 || empty($durationPrettyParts)) {
                $durationPrettyParts[] = $durationMonths . ' ' . ($durationMonths === 1 ? 'month' : 'months');
            }
            $durationPretty = implode(' and ', $durationPrettyParts);
            $residencyStartDisplay = $formatDisplayMonthYear($residencyStartRaw);
            if ($residencyStartDisplay === '') {
                try {
                    $inferredStart = new DateTime('first day of this month');
                    $monthsToSubtract = ($durationYears * 12) + $durationMonths;
                    if ($monthsToSubtract > 0) {
                        $inferredStart->modify('-' . $monthsToSubtract . ' months');
                    }
                    $residencyStartDisplay = $inferredStart->format('F Y');
                } catch (Throwable $ignored) {
                    $residencyStartDisplay = '';
                }
            }
            $firstTimeJobSeekerResidencySince = $residencyStartDisplay !== ''
                ? $residencyStartDisplay . ' (' . $durationPretty . ')'
                : $durationPretty;
            $signedDateRaw = trim((string)($requestRow['completed_at'] ?? $requestRow['release_timestamp'] ?? $requestRow['ready_at'] ?? ''));
            if ($signedDateRaw !== '') {
                try {
                    $firstTimeJobSeekerSignedDate = (new DateTime($signedDateRaw))->format('F j, Y');
                } catch (Throwable $ignored) {
                    $firstTimeJobSeekerSignedDate = $signedDateRaw;
                }
            }
        }

        if ($isIndigency) {
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY(22, 78);
            $pdf->Cell(14, 7, 'TO', 0, 0, 'L');
            $pdf->Cell(4, 7, ':', 0, 0, 'C');
            $line1 = trim((string)$requestOfficerLine1);
            $line2 = trim((string)$requestOfficerLine2);
            $line3 = trim((string)$requestOfficerLine3);
            if ($line1 === '' && $requestOfficer !== '') {
                $parts = array_values(array_filter(array_map('trim', preg_split('/\s*-\s*/', $requestOfficer) ?: []), static fn($v) => $v !== ''));
                $line1 = (string)($parts[0] ?? '');
                $line2 = (string)($parts[1] ?? '');
                $line3 = (string)($parts[2] ?? '');
            }
            if ($line1 === '' && $line2 === '' && $line3 === '') {
                $pdf->Line(39, 84, 106, 84);
                $pdf->Line(39, 90, 106, 90);
                $pdf->Line(39, 96, 106, 96);
                $pdf->SetY(96);
            } else {
                $pdf->SetFont($indigencyFont, 'B', 11);
                $offLines = [$line1, $line2, $line3];
                foreach ($offLines as $line) {
                    $pdf->SetX(39);
                    if ($line === '') {
                        $pdf->Cell(0, 7, '', 0, 1, 'L');
                    } else {
                        $pdf->Cell(0, 7, strtoupper($line), 0, 1, 'L');
                    }
                }
            }
        } else {
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetX(18);
            $pdf->Cell(0, 7, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');
        }
        $pdf->Ln(7);
        $pdf->SetFont($indigencyFont, '', 12);
        if ($isIndigency) {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify that ', 'bold' => false],
                    ['text' => $fullName, 'bold' => true],
                    ['text' => ', resident of ' . $address, 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $writeRichParagraph(
                [
                    ['text' => 'Barangay San Jose, Rodriguez, Rizal', 'bold' => true],
                    ['text' => ' belongs to the one of the indigent families of this Barangay. The Income of this family is barely enough to meet their day-to-day needs.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isResidency) {
            $writeIndentedParagraph(
                'This is to certify that the person whose name appears here on has requested a Barangay Clearance from this office and the information are listed below:',
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $pdf->Ln(3);

            $leftX = 34.0;
            $colonX = 76.0;
            $valueX = 79.0;
            $lineH = 6.2;

            $row = static function (string $label, string $value, bool $valueBold = true) use ($pdf, $indigencyFont, $leftX, $colonX, $valueX, $lineH): void {
                $rawLines = preg_split('/\R/u', (string)$value) ?: [];
                $normalizedLines = [];
                foreach ($rawLines as $line) {
                    $normalizedLines[] = trim((string)(preg_replace('/\s+/u', ' ', (string)$line) ?? (string)$line));
                }
                $value = trim(implode("\n", array_filter($normalizedLines, static fn($line) => $line !== '')));
                if ($value === '') {
                    $value = '-';
                }
                $pageRight = 198.0;
                $valueWidth = max(40.0, $pageRight - $valueX);
                $startY = $pdf->GetY();
                $pdf->SetFont($indigencyFont, 'B', 12);
                $pdf->SetXY($leftX, $startY);
                $pdf->Cell($colonX - $leftX - 1.0, $lineH, $label, 0, 0, 'L');
                $pdf->SetXY($colonX, $startY);
                $pdf->Cell(3.0, $lineH, ':', 0, 0, 'L');
                $pdf->SetFont($indigencyFont, $valueBold ? 'B' : '', 12);
                $pdf->SetXY($valueX, $startY);
                $pdf->MultiCell($valueWidth, $lineH, $value, 0, 'L');
                $endY = max($pdf->GetY(), $startY + $lineH);
                $pdf->SetY($endY);
            };

            $row('Name', $fullName !== '' ? $fullName : '-');
            $row('Address', $address !== '' ? $address : '-');
            $row('', 'BARANGAY SAN JOSE, MONTALBAN, RIZAL');
            $row('Birthday', $birthdateValue !== '' ? $birthdateValue : '-');
            $row('Birthplace', $birthplaceValue !== '' ? $birthplaceValue : '-');
            $row('Remarks', $remarksValue !== '' ? $remarksValue : '-', true);
            $row('Purpose', $requestPurpose !== '' ? $requestPurpose : '-', true);
            $pdf->Ln(4);
        } elseif ($isRelationshipJailVisit) {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify ', 'bold' => false],
                    ['text' => $fullName !== '' ? $fullName : '-', 'bold' => true],
                    ['text' => ', resident of ', 'bold' => false],
                    ['text' => $applicantAddressWithBarangay !== '' ? $applicantAddressWithBarangay : 'Barangay San Jose, Rodriguez, Rizal', 'bold' => true],
                    ['text' => ' is personally known to be as a person of ', 'bold' => false],
                    ['text' => 'GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.', 'bold' => true],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12,
                18.0,
                true
            );
        } elseif ($isCohabitation && !$cohabitationHasChildren) {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify that ', 'bold' => false],
                    ['text' => $fullName !== '' ? $fullName : '-', 'bold' => true],
                    ['text' => ', ' . ($ageValue !== '' ? $ageValue : '-') . ' y/o a resident of ', 'bold' => false],
                    ['text' => $applicantResidenceAddress !== '' ? $applicantResidenceAddress : '-', 'bold' => true],
                    ['text' => ' and ', 'bold' => false],
                    ['text' => $cohabitantName !== '' ? $cohabitantName : '-', 'bold' => true],
                    ['text' => ', ' . ($cohabitantAgeValue !== '' ? $cohabitantAgeValue : '-') . ' y/o a resident of ', 'bold' => false],
                    ['text' => $cohabitantResidenceAddress !== '' ? $cohabitantResidenceAddress : '-', 'bold' => true],
                    ['text' => '.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $writeRichParagraph(
                [
                    ['text' => 'This further certifies that they are both living together since ', 'bold' => false],
                    ['text' => $cohabitationSinceDisplayValue !== '' ? $cohabitationSinceDisplayValue : '-', 'bold' => true],
                    ['text' => ' up to present on ', 'bold' => false],
                    ['text' => $cohabitationResidenceAddress !== '' ? $cohabitationResidenceAddress : '-', 'bold' => true],
                    ['text' => '.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isCohabitation && $cohabitationHasChildren) {
            $writeIndentedParagraph(
                'This is to certify that the person whose name appears here on has requested a Barangay Certification from this office and the information are listed below:',
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $pdf->Ln(3);

            $leftX = 34.0;
            $colonX = 76.0;
            $valueX = 79.0;
            $lineH = 6.2;

            $row = static function (string $label, string $value, bool $valueBold = true) use ($pdf, $indigencyFont, $leftX, $colonX, $valueX, $lineH): void {
                $lines = preg_split('/\R/u', (string)$value) ?: [(string)$value];
                $lines = array_map(static function (string $line): string {
                    $line = (string)(preg_replace('/\s+/u', ' ', $line) ?? $line);
                    return trim($line);
                }, $lines);
                $value = trim(implode("\n", $lines));
                if ($value === '') {
                    $value = '-';
                }
                $pageRight = 198.0;
                $valueWidth = max(40.0, $pageRight - $valueX);
                $startY = $pdf->GetY();
                $pdf->SetFont($indigencyFont, 'B', 12);
                $pdf->SetXY($leftX, $startY);
                $pdf->Cell($colonX - $leftX - 1.0, $lineH, $label, 0, 0, 'L');
                $pdf->SetXY($colonX, $startY);
                $pdf->Cell(3.0, $lineH, ':', 0, 0, 'L');
                $pdf->SetFont($indigencyFont, $valueBold ? 'B' : '', 12);
                $pdf->SetXY($valueX, $startY);
                $pdf->MultiCell($valueWidth, $lineH, $value, 0, 'L');
                $endY = max($pdf->GetY(), $startY + $lineH);
                $pdf->SetY($endY);
            };

            $children = [];
            for ($i = 1; $i <= 5; $i += 1) {
                $childName = trim((string)($payload['cohabitation_child_' . $i . '_name'] ?? ''));
                $childAge = trim((string)($payload['cohabitation_child_' . $i . '_age'] ?? ''));
                if ($childName === '' && $childAge === '') {
                    continue;
                }
                $childName = trim((string)(preg_replace('/\s+/u', ' ', $childName) ?? $childName));
                $children[] = trim($childName . ($childAge !== '' ? ', ' . $childAge . ' y/o' : ''));
            }

            $nameValue = ($fullName !== '' ? $fullName : '-')
                . ', ' . ($ageValue !== '' ? $ageValue : '-') . ' y/o'
                . "\n"
                . ($cohabitantName !== '' ? $cohabitantName : '-')
                . ', ' . ($cohabitantAgeValue !== '' ? $cohabitantAgeValue : '-') . ' y/o';
            $row('Name', $nameValue, true);
            $row('Address', ($address !== '' ? $address : '-'), true);
            $row('', 'BARANGAY SAN JOSE, MONTALBAN, RIZAL', true);
            $row('Remarks', $remarksValue !== '' ? $remarksValue : '-', true);
            $purposeLine = 'COHABITATION SINCE ' . ($cohabitationSinceValue !== '' ? $cohabitationSinceValue : '-');
            $row('Purpose', $purposeLine, true);
            $row('Name of Children', !empty($children) ? implode('; ', $children) : '-', false);
            $pdf->Ln(4);
        } elseif ($isFirstTimeJobSeeker) {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify ', 'bold' => false],
                    ['text' => $firstTimeJobSeekerHonorific . ' ' . ($fullName !== '' ? $fullName : '-'), 'bold' => true],
                    ['text' => ', resident of ', 'bold' => false],
                    ['text' => $addressWithBarangay !== '' ? $addressWithBarangay : 'Barangay San Jose, Rodriguez, Rizal', 'bold' => true],
                    ['text' => ' since ', 'bold' => false],
                    ['text' => $firstTimeJobSeekerResidencySince !== '' ? $firstTimeJobSeekerResidencySince : '-', 'bold' => true],
                    ['text' => ' is a qualified availlee of RA 11261 or the First Time Jobseekers Act 2019.', 'bold' => false],
                ],
                6.2,
                18,
                10,
                $indigencyFont,
                11.5
            );
            $pdf->Ln(2);
            $writeIndentedParagraph(
                'I further certify that the holder/bearer was informed of his/her rights, including the duties and responsibilities accorded by RA 11261 through the Oath of Undertaking he/she has signed and executed in the presence of our Barangay Official.',
                6.2,
                18,
                10,
                $indigencyFont,
                11.5
            );
        } else {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify that ', 'bold' => false],
                    ['text' => $fullName, 'bold' => true],
                    ['text' => ', resident of ', 'bold' => false],
                    ['text' => $addressWithBarangay !== '' ? $addressWithBarangay : 'Barangay San Jose, Rodriguez, Rizal', 'bold' => true],
                    ['text' => ' is personally known to be as a person of ', 'bold' => false],
                    ['text' => 'GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.', 'bold' => true],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        }
        $pdf->Ln(4);
        if ($isIndigency) {
            $writeRichParagraph(
                [
                    ['text' => 'This certification is being issued upon the request of the above subject in person in connection with his/her application for ', 'bold' => false],
                    ['text' => $requestPurpose, 'bold' => true],
                    ['text' => ' purposes only.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isResidency) {
            $writeRichParagraph(
                [
                    ['text' => 'This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 - 2019', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isRelationshipJailVisit) {
            $writeRichParagraph(
                [
                    ['text' => 'Moreover, this certifies that the subject person is the ', 'bold' => false],
                    ['text' => $cohabitantRelationshipValue !== '' ? strtoupper($cohabitantRelationshipValue) : '-', 'bold' => true],
                    ['text' => ' of DETAINED ', 'bold' => false],
                    ['text' => $cohabitantName !== '' ? strtoupper($cohabitantName) : '-', 'bold' => true],
                    ['text' => ' and presently at the ', 'bold' => false],
                    ['text' => $detentionFacilityValue !== '' ? strtoupper($detentionFacilityValue) : '-', 'bold' => true],
                    ['text' => '.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12,
                18.0,
                true
            );
            $pdf->Ln(4);
            $writeIndentedParagraph(
                'This certification is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 - 2019.',
                7,
                18,
                10,
                $indigencyFont,
                12,
                18.0,
                true
            );
        } elseif ($isCohabitation && !$cohabitationHasChildren) {
            $writeIndentedParagraph(
                'This certification is being issued upon the request of both parties for whatever legal purpose it may serve them.',
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isCohabitation && $cohabitationHasChildren) {
            $writeRichParagraph(
                [
                    ['text' => 'This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 - 2019', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } elseif ($isFirstTimeJobSeeker) {
            // No extra paragraph here; FTJS proceeds directly to issued line.
        } else {
            $writeIndentedParagraph(
                'This further certifies that he/she is not a member, nor has joined a subversive society organization against the government.',
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $pdf->Ln(4);
            $writeRichParagraph(
                [
                    ['text' => 'This certification is being issued upon the request of the above-named person to be used for his/her application for ', 'bold' => false],
                    ['text' => $requestPurpose, 'bold' => true],
                    ['text' => ' purposes only.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        }
        $pdf->Ln(4);
        $writeRichParagraph(
            [
                ['text' => 'Issued this ', 'bold' => false],
                ['text' => $issuedAsDocx, 'bold' => true],
                ['text' => ', at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal', 'bold' => false],
            ],
            7,
            18,
            10,
            $indigencyFont,
            12,
            18.0,
            $isRelationshipJailVisit
        );

        if ($isGoodMoral) {
            $metaY = 186.0;
            $labelX = 18.0;
            $labelW = 34.0;
            $lineX1 = 52.0;
            $lineX2 = 70.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'L');
            }

        } elseif ($isResidency) {
            $metaY = 196.0;
            $labelX = 18.0;
            $labelW = 34.0;
            $lineX1 = 52.0;
            $lineX2 = 70.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'L');
            }
        } elseif ($isRelationshipJailVisit) {
            $metaY = min(max($pdf->GetY() + 4.0, 170.0), 186.0);
            $labelX = 18.0;
            $labelW = 34.0;
            $lineX1 = 52.0;
            $lineX2 = 70.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'L');
            }
        } elseif ($isCohabitation && !$cohabitationHasChildren) {
            $metaY = 196.0;
            $labelX = 18.0;
            $labelW = 34.0;
            $lineX1 = 52.0;
            $lineX2 = 70.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'L');
            }
        } elseif ($isCohabitation && $cohabitationHasChildren) {
            $metaY = 196.0;
            $labelX = 18.0;
            $labelW = 34.0;
            $lineX1 = 52.0;
            $lineX2 = 70.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 7;
            $pdf->SetXY($labelX, $metaY);
            $pdf->Cell($labelW, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'L');
            }
        }

        if ($isFirstTimeJobSeeker) {
            if (is_file($qrDiskPath)) {
                $pdf->Image($qrDiskPath, $fixedQrX, $fixedQrY, $fixedQrSize, $fixedQrSize);
            }

            $signBaseY = 168.0;
            $signX = 126.0;
            $signW = 46.0;
            $pdf->Line($signX, $signBaseY, $signX + $signW, $signBaseY);
            $pdf->SetFont($indigencyFont, 'B', 10);
            $pdf->SetXY($signX, $signBaseY + 1.5);
            $pdf->Cell($signW, 5, 'HON. GLENN S. EVANGELISTA', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'I', 9);
            $pdf->SetXY($signX, $signBaseY + 6.5);
            $pdf->Cell($signW, 4.5, 'Punong Barangay', 0, 1, 'C');
            $pdf->Line($signX + 8, $signBaseY + 17, $signX + $signW - 8, $signBaseY + 17);
            $pdf->SetFont($indigencyFont, 'B', 9);
            $pdf->SetXY($signX, $signBaseY + 18.5);
            $pdf->Cell($signW, 4, $firstTimeJobSeekerSignedDate, 0, 1, 'C');

            $pdf->SetFont($indigencyFont, 'B', 9);
            $pdf->SetXY($signX, $signBaseY + 24);
            $pdf->Cell($signW, 4, 'Witnesses by:', 0, 1, 'C');

            $witnessBaseY = $signBaseY + 34;
            $pdf->Line($signX, $witnessBaseY, $signX + $signW, $witnessBaseY);
            $pdf->SetFont($indigencyFont, 'B', 10);
            $pdf->SetXY($signX, $witnessBaseY + 1.5);
            $pdf->Cell($signW, 5, 'MINERVA D. QUITA', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'I', 9);
            $pdf->SetXY($signX, $witnessBaseY + 6.5);
            $pdf->Cell($signW, 4.5, 'Barangay Secretary', 0, 1, 'C');
            $pdf->Line($signX + 8, $witnessBaseY + 17, $signX + $signW - 8, $witnessBaseY + 17);
            $pdf->SetFont($indigencyFont, 'B', 9);
            $pdf->SetXY($signX, $witnessBaseY + 18.5);
            $pdf->Cell($signW, 4, $firstTimeJobSeekerSignedDate, 0, 1, 'C');

            $pdf->SetFont($indigencyFont, 'I', 8);
            $pdf->SetXY(46, 249);
            $pdf->MultiCell(100, 4, "This certification is only valid for 1 year from the issuance.\nCheck the qr code to verify the authenticity of this document.", 0, 'C');
        } else {
            // Issued by + signatory blocks aligned to the same baseline.
            $signBaseY = ($isGoodMoral || $isResidency || $isCohabitation) ? 230.0 : 214.0;
            $issuedByY = $signBaseY + 9;
            $issuedByTitleY = $signBaseY + 15;
            $footerNoteY = 258.0;
            if ($isCohabitation && !$cohabitationHasChildren) {
                $signBaseY = 232.0;
                $issuedByY = 240.0;
                $issuedByTitleY = 246.0;
                $footerNoteY = 260.0;
            } elseif ($isCohabitation && $cohabitationHasChildren) {
                $signBaseY = 234.0;
                $issuedByY = 242.0;
                $issuedByTitleY = 248.0;
                $footerNoteY = 262.0;
            }

            // Issued by block (lower-left)
            $pdf->SetFont($indigencyFont, '', 11);
            $pdf->SetXY(26, $issuedByY);
            $pdf->Cell(20, 6, 'Issued by:', 0, 0, 'L');
            $pdf->SetFont($indigencyFont, 'B', 11);
            $pdf->Cell(54, 6, 'MINERVA D. QUITA', 0, 1, 'L');
            $pdf->SetFont($indigencyFont, 'I', 11);
            $pdf->SetXY(46, $issuedByTitleY);
            $pdf->Cell(44, 6, 'Barangay Secretary', 0, 1, 'C');

            // Punong Barangay signature block (lower-right)
            $pdf->Line(124, $signBaseY, 194, $signBaseY);
            $pdf->SetFont($indigencyFont, 'B', 11);
            $pdf->SetXY(124, $signBaseY + 2);
            $pdf->Cell(70, 6, 'HON. GLENN S. EVANGELISTA', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'I', 11);
            $pdf->SetXY(124, $signBaseY + 8);
            $pdf->Cell(70, 6, 'Punong Barangay', 0, 1, 'C');

            // Footer note in a centered constrained width area.
            $pdf->SetFont($indigencyFont, 'I', 8);
            $pdf->SetXY(46, $footerNoteY);
            $pdf->SetFont($indigencyFont, 'I', 8);
            $pdf->MultiCell(118, 4, "This certificate is valid for Forty-five (45) days from the date of issue, check the\nQR code to verify the authenticity of this document.", 0, 'C');
        }

        $pdf->Output('F', $diskPath);
        return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
    } else {
        if (is_file($qrDiskPath)) {
            $fixedQrSize = 20.0;
            $pdf->Image($qrDiskPath, 186.0, 252.0, $fixedQrSize, $fixedQrSize);
        }
        $writeIndentedParagraph(
            'This is to certify that ' . $fullName . ' is a bona fide resident of ' . $address . '.',
            7,
            18,
            10,
            $fontFace,
            12
        );
        $pdf->Ln(2);
        $writeIndentedParagraph(
            'This certification is issued upon request for ' . ($purpose !== '' ? $purpose : 'legal purpose') . '.',
            7,
            18,
            10,
            $fontFace,
            12
        );
        $pdf->Ln(2);
        $writeIndentedParagraph(
            'Issued this ' . $issuedAt . ' at Barangay San Jose, Rodriguez, Rizal.',
            7,
            18,
            10,
            $fontFace,
            12
        );

        $pdf->Ln(10);
        $pdf->SetFont($fontFace, '', 10);
        $pdf->Cell(0, 6, 'Request ID: ' . $requestId, 0, 1, 'L');
        if ($certNo !== '') {
            $pdf->Cell(0, 6, 'Certificate No: ' . $certNo, 0, 1, 'L');
        }
        if ($orNo !== '') {
            $pdf->Cell(0, 6, 'OR No: ' . $orNo, 0, 1, 'L');
        }
        $pdf->Cell(0, 6, 'Verify via QR or: ' . $verifyUrl, 0, 1, 'L');
    }

    $pdf->SetY(250);
    $pdf->Line(18, 250, 88, 250);
    $pdf->SetFont($fontFace, 'B', 11);
    $pdf->Cell(70, 7, 'HON. GLENN S. EVANGELISTA', 0, 1, 'L');
    $pdf->SetFont($fontFace, '', 11);
    $pdf->Cell(70, 6, 'Punong Barangay', 0, 1, 'L');

    $pdf->Output('F', $diskPath);

    return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
}

function dra_generate_issued_document_safe(array $requestRow): ?string {
    $bufferLevel = ob_get_level();
    ob_start();
    try {
        return dra_generate_issued_document($requestRow);
    } catch (Throwable $e) {
        error_log('[dra_generate_issued_document_safe] ' . $e->getMessage());
        return null;
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
}

function dra_convert_docx_to_pdf(string $docxDiskPath, string $outDir): ?string {
    error_log('[dra_convert_docx_to_pdf] Disabled: LibreOffice workflow removed (DOCX-only mode).');
    return null;
}

function dra_convert_docx_to_html(string $docxDiskPath, string $outDir): ?string {
    error_log('[dra_convert_docx_to_html] Disabled: LibreOffice workflow removed (DOCX-only mode).');
    return null;
}

function dra_convert_docx_to_preview_image(string $docxDiskPath, string $outDir): ?string {
    error_log('[dra_convert_docx_to_preview_image] Disabled: LibreOffice workflow removed (DOCX-only mode).');
    return null;
}

function dra_cleanup_docx_preview_html(string $html): string {
    $clean = $html;
    $clean = preg_replace(
        '#<p[^>]*>\s*<table[^>]*>\s*<col[^>]*/?>\s*<tr>\s*<td[^>]*border:\s*1\.00pt solid #000000[^>]*>\s*<p[^>]*>\s*<br/?>\s*</p>\s*</td>\s*</tr>\s*</table>\s*<br/?>\s*</p>#is',
        '',
        $clean
    ) ?? $clean;
    $clean = preg_replace('#<div title="header"><p[^>]*>\s*<br/?>\s*</p></div>#is', '', $clean) ?? $clean;
    return $clean;
}

function dra_stamp_qr_on_pdf(string $pdfDiskPath, string $qrDiskPath): bool {
    $pdfReal = realpath($pdfDiskPath);
    $qrReal = realpath($qrDiskPath);
    if ($pdfReal === false || $qrReal === false || !is_file($pdfReal) || !is_file($qrReal)) {
        return false;
    }

    if (!class_exists('\\setasign\\Fpdi\\Fpdi')) {
        $autoloadPaths = [
            __DIR__ . '/../../PhpFiles/PhpOffice/vendor/autoload.php',
            __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($autoloadPaths as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                if (class_exists('\\setasign\\Fpdi\\Fpdi')) {
                    break;
                }
            }
        }
    }

    if (!class_exists('\\setasign\\Fpdi\\Fpdi')) {
        error_log('[dra_stamp_qr_on_pdf] FPDI unavailable');
        return false;
    }

    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($pdfReal);
        if ($pageCount <= 0) {
            return false;
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);
            $pageWidth = (float)($size['width'] ?? 0);
            $pageHeight = (float)($size['height'] ?? 0);
            $orientation = $pageWidth > $pageHeight ? 'L' : 'P';

            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            $pdf->useTemplate($tpl);

            if ($pageNo === $pageCount) {
                $qrSize = min(24.0, max(16.0, min($pageWidth, $pageHeight) * 0.12));
                $margin = max(8.0, $qrSize * 0.45);
                $x = max(4.0, $pageWidth - $qrSize - $margin);
                $y = max(4.0, $pageHeight - $qrSize - $margin);
                $pdf->Image($qrReal, $x, $y, $qrSize, $qrSize);
            }
        }

        $tmpPath = dirname($pdfReal) . '/tmp_' . basename($pdfReal);
        $pdf->Output('F', $tmpPath);
        if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
            @unlink($tmpPath);
            return false;
        }
        if (!@rename($tmpPath, $pdfReal)) {
            @unlink($tmpPath);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        error_log('[dra_stamp_qr_on_pdf] ' . $e->getMessage());
        return false;
    }
}

function dra_docx_contains_text(string $docxDiskPath, string $needle): bool {
    $docxReal = realpath($docxDiskPath);
    if ($docxReal === false || !is_file($docxReal) || $needle === '') {
        return false;
    }

    try {
        $zip = new ZipArchive();
        if ($zip->open($docxReal) !== true) {
            return false;
        }

        $parts = [
            'word/document.xml',
            'word/header1.xml',
            'word/header2.xml',
            'word/header3.xml',
        ];
        $needleUpper = strtoupper($needle);
        foreach ($parts as $partName) {
            $xml = $zip->getFromName($partName);
            if (!is_string($xml) || $xml === '') {
                continue;
            }
            if (strpos(strtoupper($xml), $needleUpper) !== false) {
                $zip->close();
                return true;
            }
        }

        $zip->close();
    } catch (Throwable $e) {
        error_log('[dra_docx_contains_text] ' . $e->getMessage());
    }

    return false;
}

function dra_stamp_cohabitation_letterhead($pdf, float $pageWidth, float $pageHeight): void {
    $templatePath = __DIR__ . '/../../Resident-End/Certificates/DocumentIssuance/CertificateOfCohabitationWithChild.docx';
    $leftLogo = dra_extract_docx_media_asset($templatePath, 'image1.jpg');
    $rightLogo = dra_extract_docx_media_asset($templatePath, 'image2.png');

    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(10.0, 5.0, max(0.0, $pageWidth - 20.0), 43.0, 'F');

    if ($leftLogo !== null) {
        $pdf->Image($leftLogo, 18.0, 8.0, 18.0, 18.0);
    }
    if ($rightLogo !== null) {
        $pdf->Image($rightLogo, max(0.0, $pageWidth - 36.0), 8.0, 18.0, 18.0);
    }

    $pdf->SetTextColor(128, 128, 128);
    $pdf->SetFont('Times', 'B', 9);
    $pdf->SetXY(42.0, 8.0);
    $pdf->Cell(max(0.0, $pageWidth - 84.0), 4.0, 'REPUBLIKA NG PILIPINAS', 0, 2, 'C');
    $pdf->Cell(max(0.0, $pageWidth - 84.0), 4.0, 'LALAWIGAN NG RIZAL', 0, 2, 'C');
    $pdf->Cell(max(0.0, $pageWidth - 84.0), 4.0, 'BAYAN NG RODRIGUEZ', 0, 2, 'C');
    $pdf->Ln(0.5);
    $pdf->SetFont('Times', 'B', 17);
    $pdf->Cell(max(0.0, $pageWidth - 84.0), 6.0, 'BARANGAY SAN JOSE', 0, 1, 'C');
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.30);
    $pdf->Line(24.0, 46.0, max(24.0, $pageWidth - 24.0), 46.0);
    $pdf->SetTextColor(0, 0, 0);
}

function dra_finalize_template_pdf(string $pdfDiskPath, ?string $qrDiskPath = null, array $options = []): bool {
    $pdfReal = realpath($pdfDiskPath);
    if ($pdfReal === false || !is_file($pdfReal)) {
        return false;
    }

    $stampQr = array_key_exists('stamp_qr', $options) ? (bool)$options['stamp_qr'] : true;
    $coverWidth = isset($options['cover_width']) ? (float)$options['cover_width'] : 48.0;
    $coverHeight = isset($options['cover_height']) ? (float)$options['cover_height'] : 28.0;
    $coverMarginRight = isset($options['cover_margin_right']) ? (float)$options['cover_margin_right'] : 0.0;
    $coverMarginBottom = isset($options['cover_margin_bottom']) ? (float)$options['cover_margin_bottom'] : 24.0;
    $qrSize = isset($options['qr_size']) ? (float)$options['qr_size'] : 20.0;
    $qrMarginRight = isset($options['qr_margin_right']) ? (float)$options['qr_margin_right'] : 12.0;
    $qrMarginBottom = isset($options['qr_margin_bottom']) ? (float)$options['qr_margin_bottom'] : 12.0;
    $stampLetterhead = !empty($options['stamp_letterhead']);
    $letterheadType = strtolower(trim((string)($options['letterhead_type'] ?? '')));

    $qrReal = null;
    if ($stampQr && $qrDiskPath !== null && trim($qrDiskPath) !== '') {
        $resolvedQr = realpath($qrDiskPath);
        if ($resolvedQr !== false && is_file($resolvedQr)) {
            $qrReal = $resolvedQr;
        }
    }

    if (!class_exists('\\setasign\\Fpdi\\Fpdi')) {
        $autoloadPaths = [
            __DIR__ . '/../../PhpFiles/PhpOffice/vendor/autoload.php',
            __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($autoloadPaths as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                if (class_exists('\\setasign\\Fpdi\\Fpdi')) {
                    break;
                }
            }
        }
    }

    if (!class_exists('\\setasign\\Fpdi\\Fpdi')) {
        error_log('[dra_finalize_template_pdf] FPDI unavailable');
        return false;
    }

    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($pdfReal);
        if ($pageCount <= 0) {
            return false;
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);
            $pageWidth = (float)($size['width'] ?? 0);
            $pageHeight = (float)($size['height'] ?? 0);
            $orientation = $pageWidth > $pageHeight ? 'L' : 'P';

            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            if ($pageNo === 1 && $stampLetterhead && $letterheadType === 'cohabitation') {
                $bodyTopOffset = 38.0;
                $pdf->useTemplate($tpl, 0.0, $bodyTopOffset, $pageWidth, max(1.0, $pageHeight - $bodyTopOffset));
            } else {
                $pdf->useTemplate($tpl);
            }

            if ($pageNo === 1 && $stampLetterhead && $letterheadType === 'cohabitation') {
                dra_stamp_cohabitation_letterhead($pdf, $pageWidth, $pageHeight);
            }

            if ($pageNo === $pageCount) {
                // LibreOffice keeps painting a stale QR placeholder rectangle in the
                // lower-right of the converted PDF even after the DOCX object is removed.
                // Cover that artifact here, then draw the actual QR only when enabled.
                $coverX = max(0.0, $pageWidth - $coverWidth - $coverMarginRight);
                $coverY = max(0.0, $pageHeight - $coverHeight - $coverMarginBottom);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($coverX, $coverY, $coverWidth, $coverHeight, 'F');

                if ($qrReal !== null) {
                    $qrX = max(0.0, $pageWidth - $qrSize - $qrMarginRight);
                    $qrY = max(0.0, $pageHeight - $qrSize - $qrMarginBottom);
                    $pdf->Image($qrReal, $qrX, $qrY, $qrSize, $qrSize);
                }
            }
        }

        $tmpPath = dirname($pdfReal) . '/tmp_' . basename($pdfReal);
        $pdf->Output('F', $tmpPath);
        if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
            @unlink($tmpPath);
            return false;
        }
        if (!@rename($tmpPath, $pdfReal)) {
            @unlink($tmpPath);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        error_log('[dra_finalize_template_pdf] ' . $e->getMessage());
        return false;
    }
}

function dra_issued_document_diagnostics(string $baseDir, array $row): string
{
    $issues = [];

    $generatedDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/Generated';
    if (!is_dir($generatedDir)) {
        $issues[] = 'Missing generated output directory.';
    } elseif (!is_writable($generatedDir)) {
        $issues[] = 'Generated output directory is not writable.';
    }

    $qrDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/QR';
    if (!is_dir($qrDir)) {
        $issues[] = 'Missing QR output directory.';
    } elseif (!is_writable($qrDir)) {
        $issues[] = 'QR output directory is not writable.';
    }

    $fpdfAutoloads = [
        $baseDir . '/composer-email-handler/vendor/autoload.php',
        $baseDir . '/vendor/autoload.php',
    ];
    $hasFpdfAutoload = false;
    foreach ($fpdfAutoloads as $autoloadPath) {
        if (is_file($autoloadPath)) {
            $hasFpdfAutoload = true;
            break;
        }
    }
    if (!$hasFpdfAutoload) {
        $issues[] = 'FPDF dependencies are missing. Run deploy_hostinger.sh.';
    }

    if (empty($issues)) {
        return 'Issued document generation failed unexpectedly. Check PHP error logs for [dra_generate_issued_document].';
    }

    return implode(' ', $issues);
}

function dra_backfill_payment_verified_to_ready(mysqli $conn): void {
    try {
        $legacyStage = DR_STAGE_PAYMENT_VERIFIED;
        $stmt = $conn->prepare("SELECT * FROM documentrequesttbl WHERE stage = ? ORDER BY request_id ASC");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $legacyStage);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        foreach ($rows as $row) {
            $requestId = (string)($row['request_id'] ?? '');
            if ($requestId === '') {
                continue;
            }
            $issuedPath = trim((string)($row['issued_file_path'] ?? ''));
            if ($issuedPath === '') {
                $issuedPath = dra_generate_issued_document_safe($row) ?? '';
            }
            if ($issuedPath === '') {
                continue;
            }
            $patch = [
                'ready_at' => dr_now(),
                'issued_file_path' => $issuedPath,
            ];
            dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);
        }
    } catch (Throwable $e) {
        // best-effort migration only
    }
}

function dra_save_upload(array $file, string $folder): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $orig = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        return null;
    }

    return '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $name;
}

function dra_resident_profile_snapshot(mysqli $conn, string $residentUserId, string $residentId): array {
    static $cache = [];

    $cacheKey = trim($residentUserId) . '|' . trim($residentId);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $empty = [
        'resident_id' => trim($residentId),
        'resident_user_id' => trim($residentUserId),
        'last_name' => '',
        'first_name' => '',
        'middle_name' => '',
        'suffix' => '',
        'birthdate' => '',
        'age' => '',
        'sex' => '',
        'civil_status' => '',
        'religion' => '',
        'occupation' => '',
        'contact_number' => '',
        'full_address' => '',
        'barangay_residency' => '',
        'residency_duration' => '',
        'proof_residency_path' => '',
        'proof_residency_name' => '',
        'proof_residency_type' => '',
        'proof_residency_id_number' => '',
    ];

    $sql = "
        SELECT
            r.resident_id,
            r.user_id,
            r.lastname,
            r.firstname,
            r.middlename,
            r.suffix,
            r.birthdate,
            r.baranagayresidency,
            r.sex,
            r.civil_status,
            r.religion,
            r.occupation,
            r.occupation_detail,
            u.phone_number,
            (
                SELECT uf.file_path
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_path,
            (
                SELECT uf.file_name
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_name,
            (
                SELECT dt.document_type_name
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_type,
            (
                SELECT uf.id_number
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_id_number,
            a.unit_number,
            a.street_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number
            ,a.residency_duration
        FROM residentinformationtbl r
        LEFT JOIN useraccountstbl u ON u.user_id = r.user_id
        LEFT JOIN residentaddresstbl a
            ON a.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = r.resident_id
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
        WHERE (r.user_id = ? AND ? <> '')
           OR (r.resident_id = ? AND ? <> '')
        ORDER BY r.resident_id DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $cache[$cacheKey] = $empty;
        return $empty;
    }

    $stmt->bind_param('ssss', $residentUserId, $residentUserId, $residentId, $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        $cache[$cacheKey] = $empty;
        return $empty;
    }

    $birthdate = trim((string)($row['birthdate'] ?? ''));
    $age = '';
    if ($birthdate !== '') {
        try {
            $dob = new DateTime($birthdate);
            $age = (string)((new DateTime())->diff($dob)->y);
        } catch (Throwable $e) {
            $age = '';
        }
    }

    $unit = trim((string)($row['unit_number'] ?? ''));
    $streetNumber = trim((string)($row['street_number'] ?? ''));
    $streetName = trim((string)($row['street_name'] ?? ''));
    $phase = trim((string)($row['phase_number'] ?? ''));
    $subdivision = trim((string)($row['subdivision'] ?? ''));
    $area = trim((string)($row['area_number'] ?? ''));
    $areaNormalized = trim((string)(preg_replace('/^area\b\.?\s*/i', '', $area) ?? $area));
    $fullAddressParts = [];
    if ($unit !== '') $fullAddressParts[] = 'Unit ' . $unit;
    $streetLine = trim($streetNumber . ' ' . $streetName);
    if ($streetLine !== '') $fullAddressParts[] = $streetLine;
    if ($phase !== '') $fullAddressParts[] = 'Phase ' . $phase;
    if ($subdivision !== '') $fullAddressParts[] = $subdivision . ' Subdivision';
    if ($areaNormalized !== '') $fullAddressParts[] = 'Area ' . $areaNormalized;
    $fullAddressParts[] = 'San Jose';
    $fullAddressParts[] = 'Rodriguez';
    $fullAddressParts[] = 'Rizal';
    $fullAddress = implode(', ', array_values(array_filter($fullAddressParts, static fn($v) => trim((string)$v) !== '')));

    $occupationDetail = trim((string)($row['occupation_detail'] ?? ''));
    $occupation = ((int)($row['occupation'] ?? 0) === 1)
        ? ($occupationDetail !== '' ? $occupationDetail : 'Employed')
        : 'Unemployed';

    $profile = [
        'resident_id' => (string)($row['resident_id'] ?? ''),
        'resident_user_id' => (string)($row['user_id'] ?? ''),
        'last_name' => (string)($row['lastname'] ?? ''),
        'first_name' => (string)($row['firstname'] ?? ''),
        'middle_name' => (string)($row['middlename'] ?? ''),
        'suffix' => (string)($row['suffix'] ?? ''),
        'birthdate' => $birthdate,
        'age' => $age,
        'sex' => (string)($row['sex'] ?? ''),
        'civil_status' => (string)($row['civil_status'] ?? ''),
        'religion' => (string)($row['religion'] ?? ''),
        'occupation' => $occupation,
        'contact_number' => (string)($row['phone_number'] ?? ''),
        'full_address' => $fullAddress,
        'barangay_residency' => (string)($row['baranagayresidency'] ?? ''),
        'residency_duration' => (string)($row['residency_duration'] ?? ''),
        'proof_residency_path' => (string)($row['proof_residency_path'] ?? ''),
        'proof_residency_name' => (string)($row['proof_residency_name'] ?? ''),
        'proof_residency_type' => (string)($row['proof_residency_type'] ?? ''),
        'proof_residency_id_number' => (string)($row['proof_residency_id_number'] ?? ''),
    ];

    $cache[$cacheKey] = $profile;
    return $profile;
}

if ($action !== 'list') {
    dra_backfill_payment_verified_to_ready($conn);
}

if ($action === 'list') {
    $where = [];
    $types = '';
    $vals = [];
    $listContext = strtolower(trim((string)($_GET['list_context'] ?? '')));
    $isFinanceList = ($listContext === 'finance');
    $liteList = ((string)($_GET['lite'] ?? '0') === '1');
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 250)));

    $stageCol = dr_column_exists($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $stage = trim((string)($_GET['stage'] ?? ''));
    if ($stage !== '') {
        if ($stageCol !== null) {
            $where[] = 'd.' . $stageCol . ' = ?';
            $types .= 's';
            $vals[] = $stage;
        } else {
            $statusId = dr_find_request_status_id_by_stage($conn, $stage);
            $statusCol = dr_request_status_column($conn);
            if ($statusId !== null && $statusCol !== null) {
                $where[] = 'd.' . $statusCol . ' = ?';
                $types .= 'i';
                $vals[] = $statusId;
            }
        }
    }
    if ($isFinanceList) {
        // Do not hard-filter finance list by stage in SQL.
        // Some legacy rows rely on status_id_request/transaction status and may have stale/empty stage.
        // Filtering is handled client-side by status bucket to keep rows visible for finance action.
    }
    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        $parts = ['d.request_id LIKE ?'];
        $types .= 's';
        $vals[] = '%' . $search . '%';
        if (dr_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
            $parts[] = 'd.resident_id LIKE ?';
            $types .= 's';
            $vals[] = '%' . $search . '%';
        }
        $parts[] = 'd.request_details LIKE ?';
        $types .= 's';
        $vals[] = '%' . $search . '%';
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $extraSelects = [];
    $extraJoins = [];
    $hasIssuanceTable = (!$liteList) && dr_table_exists($conn, 'issuancerequesttbl');
    $hasResidentInfoTable = (!$liteList) && dr_table_exists($conn, 'residentinformationtbl');
    if ($hasIssuanceTable) {
        $extraSelects[] = "i.certificate_type AS _issuance_certificate_type";
        $extraSelects[] = "i.certificate_number AS _issuance_certificate_number";
        $extraSelects[] = "i.verification_code AS _issuance_verification_code";
        $extraJoins[] = "LEFT JOIN issuancerequesttbl i ON i.request_id = d.request_id";
    }
    if ($hasResidentInfoTable && !$isFinanceList) {
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(riu.firstname, ''), NULLIF(riu.middlename, ''), NULLIF(riu.lastname, ''), NULLIF(riu.suffix, ''))) AS _resident_name_by_user";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(rir.firstname, ''), NULLIF(rir.middlename, ''), NULLIF(rir.lastname, ''), NULLIF(rir.suffix, ''))) AS _resident_name_by_resident";
        $extraJoins[] = "LEFT JOIN residentinformationtbl riu ON riu.user_id = d.resident_user_id";
        $extraJoins[] = "LEFT JOIN residentinformationtbl rir ON rir.resident_id = d.resident_id";
    }
    if ((!$liteList) && dr_table_exists($conn, 'officialinformationtbl')) {
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oir.firstname, ''), NULLIF(oir.middlename, ''), NULLIF(oir.lastname, ''), NULLIF(oir.suffix, ''))) AS _reviewed_by_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oil.firstname, ''), NULLIF(oil.middlename, ''), NULLIF(oil.lastname, ''), NULLIF(oil.suffix, ''))) AS _released_by_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oip.firstname, ''), NULLIF(oip.middlename, ''), NULLIF(oip.lastname, ''), NULLIF(oip.suffix, ''))) AS _personnel_name";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(oif.firstname, ''), NULLIF(oif.middlename, ''), NULLIF(oif.lastname, ''), NULLIF(oif.suffix, ''))) AS _finance_user_name";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oir ON oir.user_id = d.user_id_official_reviewed_by";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oil ON oil.user_id = d.user_id_official_released_by";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oip ON oip.user_id = d.personnel_user_id";
        $extraJoins[] = "LEFT JOIN officialinformationtbl oif ON oif.user_id = f.user_id_employee_process";
    }

    $baseSelects = [
        "d.request_id AS request_id",
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_user_id', 'resident_user_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_id', 'resident_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_name', 'resident_name'),
        dra_select_or_null($conn, 'documentrequesttbl', 'document_type', 'document_type'),
        dra_select_or_null($conn, 'documentrequesttbl', 'purpose', 'purpose'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_remarks', 'status_remarks'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_reason', 'status_reason'),
        dra_select_or_null($conn, 'documentrequesttbl', 'stage', 'stage'),
        dra_select_or_null($conn, 'documentrequesttbl', 'submitted_at', 'submitted_at'),
        dra_select_or_null($conn, 'documentrequesttbl', 'request_timestamp', 'request_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'certificate_number', 'certificate_number'),
        dra_select_or_null($conn, 'documentrequesttbl', 'verification_code', 'verification_code'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_reviewed_by', 'user_id_official_reviewed_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_released_by', 'user_id_official_released_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'review_timestamp', 'review_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'release_timestamp', 'release_timestamp'),
    ];
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
        $baseSelects[] = "d.status_id_request AS status_id_request";
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        $baseSelects[] = "d.status_id AS status_id";
    }
    if (!$isFinanceList) {
        if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
            $baseSelects[] = "d.request_details AS request_details";
        } else {
            $baseSelects[] = "NULL AS request_details";
        }
    } else {
        $baseSelects[] = "NULL AS request_details";
    }

    $sql = "
        SELECT
            " . implode(",\n            ", $baseSelects) . ",
            f.transaction_amount AS _tx_amount,
            f.payment_method AS _tx_payment_method,
            f.payment_proof_path AS _tx_payment_proof_path,
            f.transaction_details AS _tx_transaction_details,
            f.or_number AS _tx_or_number,
            f.transaction_status_id AS _tx_status_id,
            s.status_name AS _tx_status_name,
            f.payment_deadline AS _tx_payment_deadline,
            f.payment_timestamp AS _tx_payment_timestamp,
            f.finance_decision_at AS _tx_finance_decision_at,
            f.user_id_employee_process AS _tx_finance_user_id
            " . ($extraSelects ? ",\n            " . implode(",\n            ", $extraSelects) : "") . "
        FROM documentrequesttbl d
        LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
        LEFT JOIN statuslookuptbl s ON s.status_id = f.transaction_status_id
        " . ($extraJoins ? "\n        " . implode("\n        ", $extraJoins) : "") . "
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $orderCol = dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') ? 'submitted_at' : 'request_timestamp';
    $sql .= ' ORDER BY d.' . $orderCol . ' DESC, d.request_id DESC LIMIT ' . $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query.']);
    }

    if ($types !== '') {
        $refs = [];
        foreach ($vals as $i => $v) {
            $refs[$i] = &$vals[$i];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
    $items = [];
    $feeByDocType = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $docType = trim((string)($row['document_type'] ?? ''));
        if ($docType === '') {
            $docType = trim((string)($row['_issuance_certificate_type'] ?? ''));
            if ($docType !== '') {
                $row['document_type'] = $docType;
            }
        }
        if (trim((string)($row['certificate_number'] ?? '')) === '') {
            $issuedCertNo = trim((string)($row['_issuance_certificate_number'] ?? ''));
            if ($issuedCertNo !== '') {
                $row['certificate_number'] = $issuedCertNo;
            }
        }
        if (trim((string)($row['verification_code'] ?? '')) === '') {
            $issuedVc = trim((string)($row['_issuance_verification_code'] ?? ''));
            if ($issuedVc !== '') {
                $row['verification_code'] = $issuedVc;
            }
        }
        if (trim((string)($row['submitted_at'] ?? '')) === '' && trim((string)($row['request_timestamp'] ?? '')) !== '') {
            $row['submitted_at'] = (string)$row['request_timestamp'];
        }
        $row['reviewed_by'] = trim((string)($row['_reviewed_by_name'] ?? ''));
        $row['released_by'] = trim((string)($row['_released_by_name'] ?? ''));
        $row['personnel_name'] = trim((string)($row['_personnel_name'] ?? ''));
        $row['finance_user_name'] = trim((string)($row['_finance_user_name'] ?? ''));

        // Populate finance data from joined columns (avoids per-row finance query).
        $row['amount'] = isset($row['_tx_amount']) ? (float)$row['_tx_amount'] : null;
        $row['payment_method'] = (string)($row['_tx_payment_method'] ?? '');
        $row['payment_proof_path'] = (string)($row['_tx_payment_proof_path'] ?? '');
        $row['or_number'] = (string)($row['_tx_or_number'] ?? '');
        $row['payment_status_id'] = isset($row['_tx_status_id']) ? (int)$row['_tx_status_id'] : 0;
        $row['payment_status_name'] = (string)($row['_tx_status_name'] ?? '');
        $row['payment_deadline'] = (string)($row['_tx_payment_deadline'] ?? '');
        $row['payment_submitted_at'] = (string)($row['_tx_payment_timestamp'] ?? '');
        $row['finance_decision_at'] = (string)($row['_tx_finance_decision_at'] ?? '');
        $row['finance_user_id'] = (string)($row['_tx_finance_user_id'] ?? '');
        $txDetails = (string)($row['_tx_transaction_details'] ?? '');
        if ((!$liteList || !$isFinanceList) && $txDetails !== '') {
            $decoded = json_decode($txDetails, true);
            if (is_array($decoded)) {
                $ref = trim((string)($decoded['reference'] ?? ''));
                if ($ref !== '') {
                    $row['payment_reference'] = $ref;
                }
                if (trim((string)($row['purpose'] ?? '')) === '') {
                    $purposeFromTx = trim((string)($decoded['purpose'] ?? ''));
                    if ($purposeFromTx !== '') {
                        $row['purpose'] = $purposeFromTx;
                    }
                }
            } elseif (preg_match('/\bReference:\s*(.+)$/mi', $txDetails, $m)) {
                $row['payment_reference'] = trim((string)($m[1] ?? ''));
            }
        }

        if (!$isFinanceList && trim((string)($row['resident_name'] ?? '')) === '') {
            $resolvedResidentName = trim((string)($row['_resident_name_by_user'] ?? ''));
            if ($resolvedResidentName === '') {
                $resolvedResidentName = trim((string)($row['_resident_name_by_resident'] ?? ''));
            }
            if ($resolvedResidentName !== '') {
                $row['resident_name'] = $resolvedResidentName;
                $row['full_name'] = $resolvedResidentName;
            }
        }

        if (trim((string)($row['stage'] ?? '')) === '') {
            dr_sync_stage_from_status_lookup($conn, $row);
        }
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $row['fee_amount'] = null;
        $row['_doc_type_for_fee'] = trim((string)($row['document_type'] ?? ''));
        if ($isFinanceList) {
            // Keep finance list response lean; detailed request payload is not needed on initial list render.
            $row['payload'] = [];
        } else {
            $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
            if (trim((string)($row['document_type'] ?? '')) === '') {
                $payloadDocType = trim((string)($row['payload']['document_type'] ?? ''));
                if ($payloadDocType !== '') {
                    $row['document_type'] = $payloadDocType;
                }
            }
            if (trim((string)($row['purpose'] ?? '')) === '') {
                $payloadPurpose = trim((string)($row['payload']['request_purpose'] ?? $row['payload']['purpose'] ?? ''));
                if ($payloadPurpose !== '') {
                    $row['purpose'] = $payloadPurpose;
                }
            }
            if (trim((string)($row['resident_name'] ?? '')) === '') {
                $payloadResidentName = trim((string)($row['payload']['resident_name'] ?? ''));
                if ($payloadResidentName !== '') {
                    $row['resident_name'] = $payloadResidentName;
                }
            }
        }
        // Keep list payload light to avoid per-row profile queries (major latency source).
        // Full resident profile is loaded on-demand from resident masterlist endpoint when needed.
        $row['resident_profile'] = [];
        unset(
            $row['_tx_amount'],
            $row['_tx_payment_method'],
            $row['_tx_payment_proof_path'],
            $row['_tx_transaction_details'],
            $row['_tx_or_number'],
            $row['_tx_status_id'],
            $row['_tx_status_name'],
            $row['_tx_payment_deadline'],
            $row['_tx_payment_timestamp'],
            $row['_tx_finance_decision_at'],
            $row['_tx_finance_user_id'],
            $row['_issuance_certificate_type'],
            $row['_issuance_certificate_number'],
            $row['_issuance_verification_code'],
            $row['_resident_name_by_user'],
            $row['_resident_name_by_resident']
        );
        $items[] = $row;
    }
    $stmt->close();

    if ($items && !$liteList) {
        $docTypesForFee = [];
        foreach ($items as $row) {
            $docTypeForFee = trim((string)($row['_doc_type_for_fee'] ?? ''));
            if ($docTypeForFee !== '') {
                $docTypesForFee[$docTypeForFee] = true;
            }
        }
        $feeByDocType = dra_get_fee_map_for_document_types($conn, array_keys($docTypesForFee));
        foreach ($items as &$row) {
            $docTypeForFee = trim((string)($row['_doc_type_for_fee'] ?? ''));
            if ($docTypeForFee !== '') {
                if (!array_key_exists($docTypeForFee, $feeByDocType)) {
                    $feeByDocType[$docTypeForFee] = dr_get_fee_amount_for_document_type($conn, $docTypeForFee);
                }
                $row['fee_amount'] = $feeByDocType[$docTypeForFee];
            } else {
                $row['fee_amount'] = null;
            }
            unset($row['_doc_type_for_fee']);
        }
        unset($row);
    }

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

$requestId = trim((string)($_POST['request_id'] ?? $_GET['request_id'] ?? ''));
if ($action === 'get_request') {
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }
    $row = dra_fetch_request_for_modal_fast($conn, $requestId);
    if (!$row) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
    $row['payload'] = is_array($payload) ? $payload : [];

    // Keep request modal fast. Full resident profile is loaded on demand by the
    // dedicated resident profile viewer instead of every modal open.
    $row['resident_profile'] = [];
    $row['stage_label'] = dr_stage_label((string)($row['stage'] ?? ''));
    $row['fee_amount'] = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));

    dr_respond_json(200, ['success' => true, 'item' => $row]);
}
if ($action === 'view_payment_proof') {
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    $publicPath = trim((string)($row['payment_proof_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }
    $relative = '/' . ltrim(dra_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_preview_issued') {
    if (dra_is_finance_user($conn, $currentUserId)) {
        http_response_code(403);
        exit('Finance users are not allowed to preview issued documents.');
    }
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    $editedPreview = [];
    $editedPreviewRaw = trim((string)($_POST['edited_preview'] ?? ''));
    if ($editedPreviewRaw !== '') {
        $decoded = json_decode($editedPreviewRaw, true);
        if (is_array($decoded)) {
            $editedPreview = $decoded;
        }
    }
    if (!empty($editedPreview)) {
        dra_overlay_preview_edits($row, $editedPreview);
    }

    // Render a template-based preview on demand (without forcing stage transition).
    $generated = (string)(dra_generate_issued_document_safe(array_merge((array)$row, [
        '_preview_mode' => 1,
    ])) ?? '');

    $publicPath = trim($generated);
    if ($publicPath === '') {
        $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    }
    if ($publicPath === '') {
        http_response_code(500);
        exit('Unable to generate preview document.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }
    $relative = '/' . ltrim(dra_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_preview_docx') {
    http_response_code(410);
    exit('DOCX preview endpoint removed. Use HTML/JS preview.');
    exit;
}

if ($action === 'view_preview_docx_html') {
    if (dra_is_finance_user($conn, $currentUserId)) {
        http_response_code(403);
        exit('Finance users are not allowed to preview issued documents.');
    }
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    http_response_code(501);
    exit('HTML conversion preview is disabled in DOCX-only mode.');
    exit;
}

if ($action === 'view_preview_docx_image') {
    if (dra_is_finance_user($conn, $currentUserId)) {
        http_response_code(403);
        exit('Finance users are not allowed to preview issued documents.');
    }
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    http_response_code(501);
    exit('Image conversion preview is disabled in DOCX-only mode.');
    exit;
}

if ($action === 'view_issued') {
    if (dra_is_finance_user($conn, $currentUserId)) {
        http_response_code(403);
        exit('Finance users are not allowed to view issued documents.');
    }
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }
    $stage = strtolower(trim((string)($row['stage'] ?? '')));
    if (!in_array($stage, [DR_STAGE_PAYMENT_VERIFIED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Issued document is not available for this request stage yet.');
    }
    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    $docTypeNorm = strtolower(trim((string)($row['document_type'] ?? '')));
    $docTypeToken = preg_replace('/[^a-z0-9]+/', '', $docTypeNorm);
    $isIndigency = strpos($docTypeNorm, 'indigency') !== false;
    $isGoodMoral = (strpos($docTypeNorm, 'goodmoral') !== false) || (strpos($docTypeNorm, 'good moral') !== false);
    $isResidency = strpos($docTypeNorm, 'residency') !== false;
    $isCohabitation = strpos($docTypeNorm, 'cohabitation') !== false;
    $isFirstTimeJobSeeker = strpos(preg_replace('/[^a-z0-9]+/', '', $docTypeNorm), 'firsttimejobseeker') !== false;
    $isBusinessPermitClearance = in_array($docTypeToken, [
        'barangayclearanceforbusinesspermit',
        'barangaybusinessclearance',
        'businessclearance',
        'clearanceforbusinesspermit',
    ], true);
    $isTricyclePermitClearance = in_array($docTypeToken, [
        'barangayclearancefortricyclepermit',
        'clearancefortricyclepermit',
        'tricyclepermit',
        'tricycleclearance',
        'fortricyclepermit',
    ], true);
    $isGeneralPermitClearance = (dra_general_clearance_purpose_from_document_type((string)($row['document_type'] ?? '')) !== '');
    $isTemplateBasedCertificate = $isIndigency || $isGoodMoral || $isResidency || $isCohabitation || $isFirstTimeJobSeeker || $isBusinessPermitClearance || $isGeneralPermitClearance || $isTricyclePermitClearance;
    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
    $verificationCode = trim((string)($row['verification_code'] ?? ''));
    $qrPublicPath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $qrDiskPath = $baseDir . $qrPublicPath;
    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
    $isFreeDocument = ($defaultFee !== null && (float)$defaultFee <= 0.0);
    $qrEligibleStages = $isFreeDocument
        ? [DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED]
        : [DR_STAGE_PAYMENT_VERIFIED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED];
    $shouldHaveQr = ($verificationCode !== '' && in_array($stage, $qrEligibleStages, true));
    $renderRevisionTag = 'r20260312y';
    $issuedBaseName = strtolower(basename((string)$publicPath));
    $isGeneratedIssuedPath = strpos((string)$publicPath, '/UnifiedFileAttachment/IssuedDocuments/Generated/') === 0;
    $isCurrentRenderRevision = ($issuedBaseName !== '' && strpos($issuedBaseName, strtolower($renderRevisionTag)) !== false);
    $mustRegenerate = ($publicPath === '')
        || ($isTemplateBasedCertificate && !$isGeneratedIssuedPath)
        || ($isTemplateBasedCertificate && !$isCurrentRenderRevision)
        || ($isTemplateBasedCertificate && !in_array($ext, ['pdf', 'docx'], true))
        || ($isTemplateBasedCertificate && $shouldHaveQr && !is_file($qrDiskPath));
    if ($mustRegenerate) {
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $generated = (string)(dra_generate_issued_document_safe(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ])) ?? '');
        if ($generated !== '') {
            $patch = [
                'issued_file_path' => $generated,
            ];
            if (trim((string)($row['verification_code'] ?? '')) === '') {
                $patch['verification_code'] = $verificationCode;
                $patch['qr_code_path'] = $qrPublicPath;
            }
            dr_update_stage($conn, $requestId, (string)($row['stage'] ?? ''), $patch);
            $publicPath = $generated;
        }
    }
    if ($publicPath === '') {
        http_response_code(500);
        exit(dra_issued_document_diagnostics($baseDir, (array)$row));
    }

    $relative = '/' . ltrim(dra_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('Issued file path exists but file is missing on disk. Regenerate the issued document from the request.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($requestId === '' && $action !== 'list') {
    dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
}

$row = $requestId !== '' ? dr_fetch_request($conn, $requestId) : null;
if ($requestId !== '' && !$row) {
    dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
}

if ($action === 'personnel_approve') {
    $editedPreview = [];
    $editedPreviewRaw = trim((string)($_POST['edited_preview'] ?? ''));
    if ($editedPreviewRaw !== '') {
        $decoded = json_decode($editedPreviewRaw, true);
        if (is_array($decoded)) {
            $editedPreview = $decoded;
        }
    }
    if (!empty($editedPreview)) {
        dra_apply_preview_edits($conn, $requestId, $row, $editedPreview);
        $row = dr_fetch_request($conn, $requestId) ?? $row;
    }

    $isFirstTimeJobSeeker = dra_is_first_time_job_seeker($row);
    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
    if ($isFirstTimeJobSeeker) {
        $defaultFee = 0.0;
    }
    $isFreeDocument = ($defaultFee !== null && (float)$defaultFee <= 0.0);
    $nextStage = $isFirstTimeJobSeeker
        ? DR_STAGE_FOR_INTERVIEW
        : ($isFreeDocument ? DR_STAGE_READY_FOR_CLAIM : DR_STAGE_FOR_PAYMENT);
    $patch = [
        'status_reason' => null,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
        'fee_amount' => $defaultFee,
    ];

    if ($isFreeDocument && !$isFirstTimeJobSeeker) {
        $verificationCode = trim((string)($row['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $qrCodePath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
        // Keep approval fast: defer heavy PDF/QR generation until view/release time.
        $patch['verification_code'] = $verificationCode;
        $patch['qr_code_path'] = $qrCodePath;
        $patch['ready_at'] = dr_now();
    }

    $updated = dr_update_stage($conn, $requestId, $nextStage, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to approve request.']);
    }

    if ($isFreeDocument && !$isFirstTimeJobSeeker) {
        $issuedPath = dra_generate_issued_document_safe($updated);
        if (is_string($issuedPath) && trim($issuedPath) !== '') {
            $updated = dr_update_stage($conn, $requestId, (string)($updated['stage'] ?? $nextStage), [
                'issued_file_path' => (string)$issuedPath,
            ]) ?? $updated;
        }
    }

    if ($isFirstTimeJobSeeker) {
        dra_send_notification_deferred(
            $conn,
            $updated,
            'First Time Job Seeker Interview Required',
            dra_request_notice(
                $updated,
                $requestId,
                'approved for interview. Please proceed to the barangay within 5 working days for the signing of the oath of undertaking and interview.'
            )
        );
    } else {
        dra_send_notification_deferred(
            $conn,
            $updated,
            $isFreeDocument ? 'Document Request Approved for Release' : 'Document Request Approved for Payment',
            $isFreeDocument
                ? dra_request_notice($updated, $requestId, 'approved and is now for release.')
                : dra_request_notice($updated, $requestId, 'approved and is now waiting for payment.')
        );
    }

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'personnel_reject') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Rejection reason is required.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_REJECTED, [
        'status_reason' => $reason,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to reject request.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Document Request Rejected',
        dra_request_notice($updated, $requestId, 'rejected. Reason: ' . $reason)
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'interview_pass') {
    if (!dra_is_first_time_job_seeker($row)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Interview approval is only available for First Time Job Seeker requests.']);
    }
    $currentStage = strtolower(trim((string)($row['stage'] ?? '')));
    if ($currentStage !== DR_STAGE_FOR_INTERVIEW) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not currently waiting for interview approval.']);
    }

    $verificationCode = trim((string)($row['verification_code'] ?? ''));
    if ($verificationCode === '') {
        $verificationCode = strtoupper(bin2hex(random_bytes(8)));
    }
    $qrCodePath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $issuedPath = dra_generate_issued_document_safe(array_merge((array)$row, [
        'verification_code' => $verificationCode,
        'fee_amount' => 0,
    ]));
    if ($issuedPath === null || trim((string)$issuedPath) === '') {
        dr_respond_json(500, ['success' => false, 'message' => 'Interview passed, but issued document generation failed.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, [
        'status_reason' => null,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
        'fee_amount' => 0,
        'ready_at' => dr_now(),
        'verification_code' => $verificationCode,
        'qr_code_path' => $qrCodePath,
        'issued_file_path' => (string)$issuedPath,
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark interview as passed.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'First Time Job Seeker Ready for Release',
        dra_request_notice($updated, $requestId, 'approved after interview and is now ready for release.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'interview_fail') {
    if (!dra_is_first_time_job_seeker($row)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Interview rejection is only available for First Time Job Seeker requests.']);
    }
    $currentStage = strtolower(trim((string)($row['stage'] ?? '')));
    if ($currentStage !== DR_STAGE_FOR_INTERVIEW) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not currently waiting for interview review.']);
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Interview failure reason is required.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_INTERVIEW_FAILED, [
        'status_reason' => $reason,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
        'fee_amount' => 0,
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark interview as failed.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'First Time Job Seeker Interview Failed',
        dra_request_notice($updated, $requestId, 'did not pass the interview. Reason: ' . $reason)
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'finance_verify') {
    $currentStage = strtolower(trim((string)($row['stage'] ?? '')));
    if (!in_array($currentStage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_SUBMITTED, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not eligible for finance verification.']);
    }
    $verifyMode = strtolower(trim((string)($_POST['verify_mode'] ?? '')));
    if (!in_array($verifyMode, ['walkin', 'gcash'], true)) {
        $verifyMode = '';
    }

    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $orNumber = trim((string)($_POST['or_number'] ?? ''));
    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
    $resolvedAmount = null;
    if ($defaultFee !== null) {
        // Finance amount is system-controlled from configured fee.
        $resolvedAmount = (float)$defaultFee;
    } elseif (isset($row['amount']) && $row['amount'] !== null && is_numeric((string)$row['amount'])) {
        $resolvedAmount = (float)$row['amount'];
    } elseif ($amountRaw !== '' && is_numeric($amountRaw)) {
        // Fallback only when fee is not configured.
        $resolvedAmount = (float)$amountRaw;
    }

    if ($resolvedAmount === null || $resolvedAmount < 0) {
        dr_respond_json(422, ['success' => false, 'message' => 'Valid amount is required.']);
    }
    if ($orNumber === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'OR number is required.']);
    }

    $certificateNumber = dr_make_certificate_number($orNumber);
    $verificationCode = strtoupper(bin2hex(random_bytes(8)));
    $qrCodePath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $issuedPath = dra_generate_issued_document_safe(array_merge((array)$row, [
        'or_number' => $orNumber,
        'certificate_number' => $certificateNumber,
        'verification_code' => $verificationCode,
    ]));
    if ($issuedPath === null || $issuedPath === '') {
        dr_respond_json(500, ['success' => false, 'message' => 'Payment verified, but issued document generation failed.']);
    }

    $patch = [
        'amount' => $resolvedAmount,
        'or_number' => $orNumber,
        'certificate_number' => $certificateNumber,
        'verification_code' => $verificationCode,
        'qr_code_path' => $qrCodePath,
        'status_reason' => null,
        'finance_user_id' => $currentUserId,
        'finance_decision_at' => dr_now(),
        'ready_at' => dr_now(),
    ];
    // Walk-in verification from for-payment/rejected states is treated as barangay payment.
    if ($verifyMode === 'walkin' || in_array($currentStage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        $patch['payment_method'] = 'barangay';
        $patch['payment_submitted_at'] = dr_now();
        $patch['payment_proof_path'] = null;
        $patch['payment_reference'] = null;
    } elseif ($verifyMode === 'gcash') {
        $patch['payment_method'] = 'gcash';
    }
    $patch['issued_file_path'] = (string)$issuedPath;

    // Payment verification immediately makes the document ready for claim/download.
    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to verify payment.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Payment Verified - Document Ready',
        dra_request_notice($updated, $requestId, 'payment verified. OR: ' . $orNumber . '. Certificate no: ' . $certificateNumber . '. The document is now for release.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'finance_reject') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Rejection reason is required.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_PAYMENT_REJECTED, [
        'status_reason' => $reason,
        'finance_user_id' => $currentUserId,
        'finance_decision_at' => dr_now(),
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to reject payment.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Payment Rejected',
        dra_request_notice($updated, $requestId, 'payment rejected. Reason: ' . $reason)
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_ready') {
    $verificationCode = trim((string)($row['verification_code'] ?? ''));
    if ($verificationCode === '') {
        $verificationCode = strtoupper(bin2hex(random_bytes(8)));
    }
    $issuedPath = dra_save_upload($_FILES['issued_file'] ?? [], 'IssuedDocuments');
    if ($issuedPath === null) {
        // Auto-generate issued document when manual upload is not provided.
        $issuedPath = dra_generate_issued_document_safe(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ]));
    }
    if ($issuedPath === null || $issuedPath === '') {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark ready without an issued document.']);
    }

    $patch = [
        'ready_at' => dr_now(),
        'verification_code' => $verificationCode,
        'qr_code_path' => '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png',
    ];
    $patch['issued_file_path'] = (string)$issuedPath;

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark request ready.']);
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Document Ready for Claim',
        dra_request_notice($updated, $requestId, 'prepared and is now for release.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_completed') {
    $patch = [
        'completed_at' => dr_now(),
    ];
    $issuedPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($issuedPath === '') {
        $verificationCode = trim((string)($row['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $generated = dra_generate_issued_document_safe(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ]));
        if (!empty($generated)) {
            $patch['issued_file_path'] = $generated;
            $patch['verification_code'] = $verificationCode;
            $patch['qr_code_path'] = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
        }
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_COMPLETED, $patch);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to complete request.']);
    }

    if (dra_is_first_time_job_seeker($updated)) {
        $regeneratedIssuedPath = dra_generate_issued_document_safe($updated);
        if (is_string($regeneratedIssuedPath) && trim($regeneratedIssuedPath) !== '') {
            $updated = dr_update_stage($conn, $requestId, DR_STAGE_COMPLETED, [
                'issued_file_path' => $regeneratedIssuedPath,
            ]) ?? $updated;
        }
    }

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Document Request Completed',
        dra_request_notice($updated, $requestId, 'completed and released.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
