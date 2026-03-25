<?php
declare(strict_types=1);

if (!function_exists('dr_generate_invoice_pdf')) {

    function dr_invoice_autoload(): bool
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }
        $candidates = [
            __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
            }
        }
        $loaded = class_exists('FPDF');
        return $loaded;
    }

    /**
     * Generate an Official Receipt / Invoice PDF for a paid document request.
     *
     * @param array  $requestData  Row data from documentrequesttbl (may include overrides for amount/or_number).
     * @param string $baseDir      Absolute path to the project root (no trailing slash).
     * @return string|null         Relative web path like /UnifiedFileAttachment/Invoices/invoice_XXXX.pdf, or null on failure.
     */
    function dr_generate_invoice_pdf(array $requestData, string $baseDir): ?string
    {
        if (!dr_invoice_autoload()) {
            error_log('[invoiceGenerator] FPDF class not available.');
            return null;
        }

        $requestId    = trim((string)($requestData['request_id'] ?? ''));
        $residentName = trim((string)($requestData['resident_name'] ?? ''));
        $documentType = trim((string)($requestData['document_type'] ?? 'Document Request'));
        $purpose      = trim((string)($requestData['purpose'] ?? ''));
        $orNumber     = trim((string)($requestData['or_number'] ?? ''));
        $certNumber   = trim((string)($requestData['certificate_number'] ?? ''));
        $amount       = isset($requestData['amount']) ? (float)$requestData['amount'] : 0.0;
        $paymentMethod = trim((string)($requestData['payment_method'] ?? ''));

        // Only generate for paid requests.
        if ($amount <= 0.0 || $orNumber === '') {
            return null;
        }

        // Determine the payment date.
        $paymentDateRaw = trim((string)($requestData['finance_decision_at']
            ?? $requestData['payment_submitted_at']
            ?? $requestData['payment_timestamp']
            ?? ''));
        $paymentTs   = $paymentDateRaw !== '' ? @strtotime($paymentDateRaw) : false;
        $paymentDate = $paymentTs !== false ? date('F j, Y', $paymentTs) : date('F j, Y');

        // Humanise payment method.
        $methodLabel = match (strtolower($paymentMethod)) {
            'barangay'           => 'Walk-in (Barangay Hall)',
            'gcash'              => 'GCash',
            'online', 'e-payment' => 'Online / E-Payment',
            default              => ucfirst($paymentMethod !== '' ? $paymentMethod : 'Cash'),
        };

        // Output directory.
        $invoiceDir = $baseDir . '/UnifiedFileAttachment/Invoices';
        if (!is_dir($invoiceDir)) {
            @mkdir($invoiceDir, 0755, true);
        }

        $safeId      = preg_replace('/[^A-Za-z0-9_-]/', '', $requestId);
        $relPath     = '/UnifiedFileAttachment/Invoices/invoice_' . $safeId . '.pdf';
        $absPath     = $baseDir . $relPath;

        try {
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(20, 15, 20);
            $pdf->SetAutoPageBreak(true, 20);

            // ── Logo ──────────────────────────────────────────────────────────
            $logoPath = $baseDir . '/Images/San_Jose_LOGO.jpg';
            if (is_file($logoPath)) {
                $pdf->Image($logoPath, 20, 12, 18, 18);
            }

            // ── Barangay header ───────────────────────────────────────────────
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetXY(42, 13);
            $pdf->Cell(0, 5, 'BARANGAY SAN JOSE', 0, 1, 'L');

            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(42);
            $pdf->Cell(0, 4, 'Montalban (Rodriguez), Rizal', 0, 1, 'L');

            $pdf->SetX(42);
            $pdf->Cell(0, 4, 'barangaysanjose-montalban.com', 0, 1, 'L');

            // ── "OFFICIAL RECEIPT" badge ──────────────────────────────────────
            $pdf->SetY(14);
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->SetTextColor(180, 80, 10);
            $pdf->Cell(0, 8, 'OFFICIAL RECEIPT', 0, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);

            // ── Divider ───────────────────────────────────────────────────────
            $pdf->SetY(34);
            $pdf->SetDrawColor(222, 113, 12);
            $pdf->SetLineWidth(0.6);
            $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
            $pdf->Ln(4);

            // ── Receipt metadata table ────────────────────────────────────────
            $labelW = 42;
            $valueW = 126;
            $rowH   = 6;

            $pdf->SetFont('Arial', '', 9);
            $pdf->SetFillColor(250, 247, 243);

            $metaRows = [
                ['OR No.',         $orNumber],
                ['Date',           $paymentDate],
                ['Received from',  $residentName !== '' ? $residentName : '-'],
                ['Request ID',     $requestId],
            ];

            foreach ($metaRows as $i => [$label, $value]) {
                $fill = ($i % 2 === 0);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell($labelW, $rowH, $label . ':', 0, 0, 'L', $fill);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell($valueW, $rowH, $value, 0, 1, 'L', $fill);
            }

            $pdf->Ln(4);

            // ── Line items header ─────────────────────────────────────────────
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(222, 113, 12);
            $pdf->SetTextColor(255, 255, 255);
            $descW   = 130;
            $amountW = 38;
            $pdf->Cell($descW, 7, 'DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell($amountW, 7, 'AMOUNT', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            // ── Line item row ─────────────────────────────────────────────────
            $pdf->SetFont('Arial', '', 9);
            $description = $documentType;
            if ($purpose !== '') {
                $description .= ' - ' . $purpose;
            }
            // Truncate description if too long for the cell.
            if (mb_strlen($description) > 75) {
                $description = mb_substr($description, 0, 72) . '...';
            }
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($descW, 7, $description, 1, 0, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell($amountW, 7, 'PHP ' . number_format($amount, 2), 1, 1, 'R', true);

            // ── Total row ─────────────────────────────────────────────────────
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($descW, 7, 'TOTAL AMOUNT RECEIVED', 1, 0, 'R', true);
            $pdf->Cell($amountW, 7, 'PHP ' . number_format($amount, 2), 1, 1, 'R', true);

            $pdf->Ln(5);

            // ── Payment details ───────────────────────────────────────────────
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($labelW, $rowH, 'Payment Method:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell($valueW, $rowH, $methodLabel, 0, 1, 'L');

            if ($certNumber !== '') {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell($labelW, $rowH, 'Certificate No.:', 0, 0, 'L');
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell($valueW, $rowH, $certNumber, 0, 1, 'L');
            }

            $pdf->Ln(8);

            // ── Divider ───────────────────────────────────────────────────────
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.3);
            $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
            $pdf->Ln(5);

            // ── Footer ────────────────────────────────────────────────────────
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(0, 4,
                'Thank you for your payment. Please keep this receipt for your records.',
                0, 'C'
            );
            $pdf->Ln(2);
            $pdf->MultiCell(0, 4,
                'This is a system-generated official receipt. No signature required.',
                0, 'C'
            );
            $pdf->SetTextColor(0, 0, 0);

            $pdf->Output('F', $absPath);

            return is_file($absPath) ? $relPath : null;

        } catch (Throwable $e) {
            error_log('[invoiceGenerator] PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }
}
