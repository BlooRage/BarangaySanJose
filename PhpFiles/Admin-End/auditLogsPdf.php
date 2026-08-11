<?php
declare(strict_types=1);

require_once __DIR__ . '/auditLogsSupport.php';

$auditLogsPdfAutoloadCandidates = [
    __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
    __DIR__ . '/../PhpOffice/vendor/autoload.php',
];
foreach ($auditLogsPdfAutoloadCandidates as $auditLogsPdfAutoload) {
    if (is_file($auditLogsPdfAutoload)) {
        require_once $auditLogsPdfAutoload;
    }
}

if (!class_exists('FPDF')) {
    throw new RuntimeException('PDF export support is unavailable.');
}

function audit_logs_pdf_text(string $value): string
{
    $value = str_replace(["\0", "\r\n", "\r", '₱'], ['', "\n", "\n", 'PHP '], $value);
    $value = preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', ' ', $value) ?? $value;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }
    }
    return preg_replace('/[^\x20-\x7E\n]/', '?', $value) ?? $value;
}

function audit_logs_pdf_truncate(string $value, int $limit = 240): string
{
    // Keep each table cell bounded even when an audit detail contains
    // pretty-printed JSON or attacker-controlled line breaks.
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length <= $limit) {
        return $value;
    }

    $short = function_exists('mb_substr')
        ? mb_substr($value, 0, max(1, $limit - 1), 'UTF-8')
        : substr($value, 0, max(1, $limit - 1));
    return rtrim($short) . '…';
}

function audit_logs_pdf_widths(array $columns, float $availableWidth): array
{
    $weights = [
        'audit_id' => 23,
        'timestamp' => 31,
        'user_id' => 20,
        'name' => 34,
        'role_access' => 22,
        'action_type' => 31,
        'module_affected' => 29,
        'target' => 33,
        'field_changed' => 27,
        'old_value' => 40,
        'new_value' => 40,
        'remarks' => 42,
    ];

    $totalWeight = 0.0;
    foreach ($columns as $column) {
        $totalWeight += (float)($weights[$column] ?? 25);
    }
    if ($totalWeight <= 0) {
        return [];
    }

    $widths = [];
    $allocated = 0.0;
    $lastIndex = count($columns) - 1;
    foreach ($columns as $index => $column) {
        $width = $index === $lastIndex
            ? $availableWidth - $allocated
            : round($availableWidth * ((float)($weights[$column] ?? 25) / $totalWeight), 2);
        $widths[] = max(12.0, $width);
        $allocated += $widths[array_key_last($widths)];
    }

    $widthTotal = array_sum($widths);
    if ($widthTotal > $availableWidth && $widthTotal > 0) {
        $scale = $availableWidth / $widthTotal;
        foreach ($widths as $index => $width) {
            $widths[$index] = $width * $scale;
        }
    }
    return $widths;
}

class AuditLogsPdfDocument extends FPDF
{
    private array $columns = [];
    private array $columnWidths = [];
    private array $columnLabels = [];
    private string $filterSummary = '';
    private string $generatedSummary = '';
    private string $logoPath = '';
    private int $rowNumber = 0;

    public function configure(
        array $columns,
        array $columnWidths,
        string $filterSummary,
        string $generatedSummary,
        string $logoPath
    ): void {
        $catalog = audit_logs_export_column_catalog();
        $this->columns = $columns;
        $this->columnWidths = $columnWidths;
        $this->columnLabels = array_map(
            static fn(string $column): string => $catalog[$column] ?? $column,
            $columns
        );
        $this->filterSummary = $filterSummary;
        $this->generatedSummary = $generatedSummary;
        $this->logoPath = $logoPath;
    }

    public function Header()
    {
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            $this->Image($this->logoPath, 10, 7.5, 14, 14);
        }

        $this->SetXY(28, 8);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 5, audit_logs_pdf_text('Barangay San Jose'), 0, 1, 'L');
        $this->SetX(28);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(222, 113, 12);
        $this->Cell(0, 5, 'AUDIT LOGS', 0, 1, 'L');

        $this->SetXY($this->GetPageWidth() - 112, 8);
        $this->SetFont('Helvetica', '', 6.8);
        $this->SetTextColor(90, 98, 108);
        $this->MultiCell(102, 3.6, audit_logs_pdf_text($this->generatedSummary), 0, 'R');

        $this->SetDrawColor(222, 113, 12);
        $this->SetLineWidth(0.45);
        $this->Line(10, 24, $this->GetPageWidth() - 10, 24);

        $this->SetXY(10, 26.2);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(73, 80, 87);
        $this->MultiCell(
            $this->GetPageWidth() - 20,
            3.8,
            audit_logs_pdf_text('Filters: ' . $this->filterSummary),
            0,
            'L'
        );
        $this->Ln(1.4);
        $this->renderTableHeader();
    }

    public function Footer()
    {
        $this->SetY(-9);
        $this->SetDrawColor(210, 214, 220);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->SetY(-7.5);
        $this->SetFont('Helvetica', 'I', 6.5);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 4, 'Confidential - SuperAdmin audit export', 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    private function renderTableHeader(): void
    {
        $this->SetFillColor(222, 113, 12);
        $this->SetDrawColor(194, 91, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', count($this->columns) > 9 ? 5.5 : 6.5);
        foreach ($this->columnLabels as $index => $label) {
            $this->Cell($this->columnWidths[$index], 7, audit_logs_pdf_text($label), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    private function numberOfLines(float $width, string $text): int
    {
        $characterWidths = $this->CurrentFont['cw'];
        if ($width === 0.0) {
            $width = $this->GetPageWidth() - $this->rMargin - $this->x;
        }
        $maxWidth = ($width - 1.8) * 1000 / $this->FontSize;
        $text = str_replace("\r", '', $text);
        $length = strlen($text);
        if ($length > 0 && $text[$length - 1] === "\n") {
            $length--;
        }

        $separator = -1;
        $start = 0;
        $index = 0;
        $lineWidth = 0;
        $lines = 1;
        while ($index < $length) {
            $character = $text[$index];
            if ($character === "\n") {
                $index++;
                $separator = -1;
                $start = $index;
                $lineWidth = 0;
                $lines++;
                continue;
            }
            if ($character === ' ') {
                $separator = $index;
            }
            $lineWidth += $characterWidths[$character] ?? 600;
            if ($lineWidth > $maxWidth) {
                if ($separator === -1) {
                    if ($index === $start) {
                        $index++;
                    }
                } else {
                    $index = $separator + 1;
                }
                $separator = -1;
                $start = $index;
                $lineWidth = 0;
                $lines++;
            } else {
                $index++;
            }
        }
        return $lines;
    }

    public function addAuditRow(array $row): void
    {
        $fontSize = count($this->columns) > 9 ? 5.2 : 6.2;
        $lineHeight = count($this->columns) > 9 ? 3.0 : 3.4;
        $this->SetFont('Helvetica', '', $fontSize);

        $values = [];
        $maxLines = 1;
        foreach ($this->columns as $index => $column) {
            $raw = audit_logs_column_value($row, $column);
            $limit = in_array($column, ['old_value', 'new_value', 'remarks'], true) ? 240 : 140;
            $value = audit_logs_pdf_text(audit_logs_pdf_truncate($raw, $limit));
            $values[] = $value;
            $maxLines = max($maxLines, $this->numberOfLines(max(6, $this->columnWidths[$index] - 1.4), $value));
        }

        $rowHeight = max(6.2, ($maxLines * $lineHeight) + 2.0);
        if ($this->GetY() + $rowHeight > $this->PageBreakTrigger) {
            $this->AddPage();
            $this->SetFont('Helvetica', '', $fontSize);
        }

        $this->rowNumber++;
        $fill = $this->rowNumber % 2 === 0;
        $this->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $this->SetDrawColor(218, 222, 226);
        $this->SetTextColor(33, 37, 41);
        $startX = $this->GetX();
        $startY = $this->GetY();

        foreach ($values as $index => $value) {
            $width = $this->columnWidths[$index];
            $cellX = $this->GetX();
            $this->Rect($cellX, $startY, $width, $rowHeight, $fill ? 'DF' : 'D');
            $this->SetXY($cellX + 0.7, $startY + 1.0);
            $this->MultiCell(max(1, $width - 1.4), $lineHeight, $value, 0, 'L');
            $this->SetXY($cellX + $width, $startY);
        }
        $this->SetXY($startX, $startY + $rowHeight);
    }
}

function audit_logs_build_pdf(
    array $rows,
    array $columns,
    array $filterSummary,
    string $generatedBy
): string {
    $pdf = new AuditLogsPdfDocument('L', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->SetCompression(true);
    $pdf->AliasNbPages();

    $availableWidth = $pdf->GetPageWidth() - 20;
    $widths = audit_logs_pdf_widths($columns, $availableWidth);
    $generatedSummary = sprintf(
        "Generated by: %s\nGenerated at: %s\nRecords: %s",
        $generatedBy,
        date('F j, Y g:i A'),
        number_format(count($rows))
    );
    if (array_intersect(['old_value', 'new_value', 'remarks'], $columns) !== []) {
        $generatedSummary .= "\nLong detail values are shortened for PDF layout";
    }
    $pdf->configure(
        $columns,
        $widths,
        implode(' | ', $filterSummary),
        $generatedSummary,
        __DIR__ . '/../../Images/San_Jose_LOGO.jpg'
    );
    $pdf->SetTitle(audit_logs_pdf_text('Barangay San Jose Audit Logs'));
    $pdf->SetAuthor(audit_logs_pdf_text($generatedBy));
    $pdf->AddPage();

    if ($rows === []) {
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 12, 'No audit-log records matched the selected filters.', 1, 1, 'C');
    } else {
        foreach ($rows as $row) {
            $pdf->addAuditRow($row);
        }
    }

    return (string)$pdf->Output('S');
}
