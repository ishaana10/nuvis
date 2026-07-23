<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PdfGenerator.php';
require_once __DIR__ . '/EmailService.php';

echo "=== NUVIS PDF GENERATOR AND EMAIL SERVICE ATTACHMENT UNIT TESTS ===\n\n";

// Test 1: NuPdfGenerator static helper templates
echo "Test 1: Starter Templates Retrieval... ";
$invoiceTpl = NuPdfGenerator::getInvoiceTemplate();
$receiptTpl = NuPdfGenerator::getReceiptTemplate();
$certTpl    = NuPdfGenerator::getCertificateTemplate();

if (empty($invoiceTpl) || empty($receiptTpl) || empty($certTpl)) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED ✓\n";

// Test 2: NuPdfGenerator template rendering (loops + variables)
echo "Test 2: Template Rendering (Loops & Placeholders)... ";
$report = [
    'report_name' => 'Monthly Sales',
    'report_code' => 'monthly_sales',
    'report_pdf_template' => '<h2>{{report_name}}</h2><ul>{{loop}}<li>{{station}} ({{month}}): {{revenue}}</li>{{/loop}}</ul>'
];
$data = [
    ['station' => 'STN_NORTH', 'month' => 'July', 'revenue' => '1000'],
    ['station' => 'STN_SOUTH', 'month' => 'August', 'revenue' => '2000']
];
$rendered = NuPdfGenerator::renderTemplate($report['report_pdf_template'], $report, $data, ['company_name' => 'Acme Corp']);

$expected = '<h2>Monthly Sales</h2><ul><li>STN_NORTH (July): 1000</li><li>STN_SOUTH (August): 2000</li></ul>';
if (trim($rendered) !== $expected) {
    echo "FAILED (Got: " . trim($rendered) . ")\n";
    exit(1);
}
echo "PASSED ✓\n";

// Test 3: PDF Binary Compilation (TCPDF Integration)
echo "Test 3: PDF Binary Compilation (TCPDF)... ";
$pdfBinary = NuPdfGenerator::generate($report, $data, [
    'orientation' => 'P',
    'format' => 'A4',
    'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
    'company_name' => 'Acme Corp'
]);

// A valid PDF must start with %PDF- header
if (strpos($pdfBinary, '%PDF-') !== 0) {
    echo "FAILED (Invalid PDF format header)\n";
    exit(1);
}
echo "PASSED ✓ (Generated PDF size: " . strlen($pdfBinary) . " bytes)\n";

// Test 4: EmailService Attachments Support
echo "Test 4: EmailService Attachment MIME Multipart compilation... ";
$emailService = new EmailService([
    'driver' => 'mail',
    'from_email' => 'test@nubuilder.local',
    'from_name' => 'Test sender'
]);

// Since send() triggers php mail() which is mocked/silent, we can verify the buildMultipartBody protected helper
$reflector = new ReflectionClass('EmailService');
$method = $reflector->getMethod('buildMultipartBody');
$method->setAccessible(true);

$options = [
    'attachments' => [
        [
            'data' => 'dummy-pdf-content',
            'filename' => 'report.pdf',
            'mimetype' => 'application/pdf'
        ]
    ]
];

$parts = $method->invoke($emailService, '<h1>Test Body</h1>', 'Test Plain text body', $options);

if (strpos($parts['contentType'], 'multipart/mixed') === false) {
    echo "FAILED (Content-Type is not multipart/mixed)\n";
    exit(1);
}

if (strpos($parts['body'], base64_encode('dummy-pdf-content')) === false && strpos($parts['body'], chunk_split(base64_encode('dummy-pdf-content'))) === false) {
    echo "FAILED (Attachment data not found in multipart body)\n";
    exit(1);
}

if (strpos($parts['body'], 'Content-Disposition: attachment; filename="report.pdf"') === false) {
    echo "FAILED (Content-Disposition header is missing or incorrect)\n";
    exit(1);
}
echo "PASSED ✓\n";

echo "\nALL TESTS PASSED SUCCESSFULLY! ✓\n";
