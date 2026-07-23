<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

class NuPdfGenerator {
    /**
     * Generate a PDF file as string.
     *
     * @param array $report The report configuration array.
     * @param array $dataRows The rows of data returned from the report SQL query.
     * @param array $params Custom or override parameters (e.g., orientation, format, margins, logo).
     * @return string PDF binary string.
     */
    public static function generate(array $report, array $dataRows, array $params = []): string {
        $orientation = $params['orientation'] ?? 'P'; // P or L
        $format = $params['format'] ?? 'A4'; // A4, Letter, etc.
        $margins = $params['margins'] ?? ['top' => 15, 'bottom' => 15, 'left' => 15, 'right' => 15];

        // Create new TCPDF document
        $pdf = new TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('nuvis');
        $pdf->SetAuthor((isset($_SESSION) && isset($_SESSION['user_name'])) ? $_SESSION['user_name'] : 'nuvis');
        $pdf->SetTitle($report['report_name'] ?? 'Report PDF');
        $pdf->SetSubject($report['report_name'] ?? 'Report Export');

        // Header and Footer overrides if needed
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        // Set margins
        $pdf->SetMargins($margins['left'] ?? 15, $margins['top'] ?? 15, $margins['right'] ?? 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, $margins['bottom'] ?? 15);

        // Set default font subsetting mode
        $pdf->setFontSubsetting(true);

        // Set font
        $pdf->SetFont('helvetica', '', 10, '', true);

        // Add a page
        $pdf->AddPage();

        // Template logic
        $template = $report['report_pdf_template'] ?? '';
        if (empty(trim($template))) {
            $template = self::getDefaultTemplate($report);
        }

        $html = self::renderTemplate($template, $report, $dataRows, $params);

        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Return PDF as string
        return $pdf->Output('', 'S');
    }

    /**
     * Render the HTML template with the report data, resolving loops and placeholders.
     */
    public static function renderTemplate(string $template, array $report, array $data, array $params = []): string {
        // First resolve simple system/report meta variables
        $variables = [
            'report_name'   => htmlspecialchars($report['report_name'] ?? ''),
            'report_code'   => htmlspecialchars($report['report_code'] ?? ''),
            'current_date'  => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'company_name'  => htmlspecialchars($params['company_name'] ?? ((isset($_SESSION) && isset($_SESSION['company_name'])) ? $_SESSION['company_name'] : 'nuvis Inc.')),
        ];

        foreach ($variables as $key => $val) {
            $template = str_replace('{{' . $key . '}}', (string)$val, $template);
        }

        // Handle Loops: {{loop}} ... {{column_name}} ... {{/loop}}
        // We'll search for loop blocks and repeat the content inside for each row of data
        $pattern = '/\{\{loop\}\}(.*?)\{\{\/loop\}\}/is';
        $template = preg_replace_callback($pattern, function($matches) use ($data) {
            $loopContent = $matches[1];
            $output = '';
            foreach ($data as $row) {
                $rowStr = $loopContent;
                // Replace all {{column_name}} placeholders
                foreach ($row as $colName => $colValue) {
                    $rowStr = str_replace('{{' . $colName . '}}', htmlspecialchars((string)($colValue ?? '')), $rowStr);
                }
                $output .= $rowStr;
            }
            return $output;
        }, $template);

        // Finally, replace any remaining non-looped single record variables if data exists (using first row as default context)
        if (!empty($data)) {
            $firstRow = $data[0];
            foreach ($firstRow as $colName => $colValue) {
                $template = str_replace('{{' . $colName . '}}', htmlspecialchars((string)($colValue ?? '')), $template);
            }
        }

        return $template;
    }

    /**
     * Standard Invoice Starter Layout
     */
    public static function getInvoiceTemplate(): string {
        return '<style>
            .invoice-box { font-family: \'Helvetica Neue\', \'Helvetica\', Helvetica, Arial, sans-serif; color: #555; }
            .invoice-title { font-size: 24px; font-weight: bold; color: #333; }
            .header-table, .items-table { width: 100%; border-collapse: collapse; }
            .items-table th { background-color: #f5f5f5; font-weight: bold; border-bottom: 2px solid #ddd; padding: 8px; text-align: left; }
            .items-table td { border-bottom: 1px solid #eee; padding: 8px; }
            .total-row td { font-weight: bold; border-top: 2px solid #ddd; padding-top: 10px; }
        </style>
        <div class="invoice-box">
            <table class="header-table">
                <tr>
                    <td>
                        <span class="invoice-title">INVOICE</span><br/>
                        <strong>Company:</strong> {{company_name}}<br/>
                        <strong>Date:</strong> {{current_date}}<br/>
                        <strong>Report:</strong> {{report_name}} ({{report_code}})
                    </td>
                    <td style="text-align: right;">
                        <strong>Billing To:</strong><br/>
                        Valued Client<br/>
                        Total Records: {{total_records}}
                    </td>
                </tr>
            </table>
            <br/><br/>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Station</th>
                        <th>Month</th>
                        <th>Year</th>
                        <th style="text-align: right;">Transactions</th>
                        <th style="text-align: right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    {{loop}}
                    <tr>
                        <td>{{station}}</td>
                        <td>{{month}}</td>
                        <td>{{year}}</td>
                        <td style="text-align: right;">{{transactions}}</td>
                        <td style="text-align: right;">{{revenue}}</td>
                    </tr>
                    {{/loop}}
                </tbody>
            </table>
        </div>';
    }

    /**
     * Standard Receipt Starter Layout
     */
    public static function getReceiptTemplate(): string {
        return '<style>
            .receipt-box { font-family: Helvetica, Arial, sans-serif; color: #333; padding: 10px; }
            .receipt-header { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 10px; margin-bottom: 15px; }
            .receipt-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
            .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .items-table th { border-bottom: 1px dashed #ccc; padding: 5px; font-weight: bold; text-align: left; }
            .items-table td { padding: 6px 5px; font-size: 11px; }
            .dashed-line { border-top: 1px dashed #ccc; margin-top: 10px; margin-bottom: 10px; }
            .footer-msg { text-align: center; font-size: 11px; color: #777; margin-top: 20px; }
        </style>
        <div class="receipt-box">
            <div class="receipt-header">
                <span class="receipt-title">RECEIPT</span><br/>
                <strong>{{company_name}}</strong><br/>
                <span>Date: {{current_date}}</span><br/>
                <span>Txn Count: {{total_records}}</span>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description / Item</th>
                        <th style="text-align: right;">Amt</th>
                    </tr>
                </thead>
                <tbody>
                    {{loop}}
                    <tr>
                        <td>Station: {{station}} - {{month}} {{year}} ({{transactions}} txns)</td>
                        <td style="text-align: right;">${{revenue}}</td>
                    </tr>
                    {{/loop}}
                </tbody>
            </table>

            <div class="dashed-line"></div>
            <div class="footer-msg">
                Thank you for your business!<br/>
                For support, contact support@{{report_code}}.local
            </div>
        </div>';
    }

    /**
     * Standard Certificate Starter Layout
     */
    public static function getCertificateTemplate(): string {
        return '<style>
            .certificate-container { border: 15px double #bf9000; padding: 30px; text-align: center; font-family: Georgia, serif; background-color: #fff; }
            .cert-title { font-size: 34px; font-weight: bold; color: #111; font-family: \'Times New Roman\', Times, serif; }
            .cert-subtitle { font-size: 18px; font-style: italic; color: #666; margin-top: 10px; }
            .cert-recipient { font-size: 28px; font-weight: bold; color: #bf9000; margin: 20px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; display: inline-block; }
            .cert-text { font-size: 14px; line-height: 1.6; color: #333; max-width: 500px; margin: 0 auto; }
            .cert-footer { margin-top: 40px; width: 100%; }
        </style>
        <div class="certificate-container">
            <br/><br/>
            <div class="cert-title">CERTIFICATE OF REVENUE</div>
            <div class="cert-subtitle">PROUDLY PRESENTED TO</div>
            <br/>
            <div class="cert-recipient">{{company_name}}</div>
            <br/>
            <div class="cert-text">
                This is to officially certify that the dynamic report <strong>{{report_name}} ({{report_code}})</strong> has been successfully executed on {{current_date}} and generated a total of <strong>{{total_records}} active entries</strong> for auditing.
            </div>
            <br/><br/>
            <table class="cert-footer">
                <tr>
                    <td style="text-align: left; width: 50%; font-size: 12px; font-style: italic;">
                        Date of Issue: {{current_date}}<br/>
                        Database Verified
                    </td>
                    <td style="text-align: right; width: 50%; font-size: 12px;">
                        _______________________<br/>
                        Authorized Officer
                    </td>
                </tr>
            </table>
        </div>';
    }

    /**
     * Fallback Default Template
     */
    public static function getDefaultTemplate(array $report): string {
        return '<h2>' . htmlspecialchars($report['report_name'] ?? 'Report') . '</h2>
        <p>Generated on: {{current_date}}</p>
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>Data Item</th>
                </tr>
            </thead>
            <tbody>
                {{loop}}
                <tr>
                    <td>{{station}} {{month}} {{year}} - {{revenue}}</td>
                </tr>
                {{/loop}}
            </tbody>
        </table>';
    }
}
