<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Audit.php';
require_once __DIR__ . '/../core/PdfGenerator.php';
require_once __DIR__ . '/../core/EmailService.php';

header('Content-Type: application/json');

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = NuDatabase::getInstance();

// Self-healing DB migrations
try {
    $db->query("CREATE TABLE IF NOT EXISTS `nu_word_certificates` (
      `cert_id` INT AUTO_INCREMENT PRIMARY KEY,
      `cert_title` VARCHAR(255) NOT NULL,
      `cert_form_code` VARCHAR(255) NULL,
      `cert_file_id` INT NULL,
      `cert_html_template` LONGTEXT NULL,
      `cert_button_label` VARCHAR(255) NULL,
      `cert_output_name_template` VARCHAR(255) NULL,
      `cert_created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `cert_created_by` VARCHAR(36) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Idempotent column additions
    try {
        $db->query("ALTER TABLE `nu_word_certificates` ADD COLUMN `cert_button_label` VARCHAR(255) NULL");
    } catch (\Throwable $t) {}
    try {
        $db->query("ALTER TABLE `nu_word_certificates` ADD COLUMN `cert_output_name_template` VARCHAR(255) NULL");
    } catch (\Throwable $t) {}
} catch (\Throwable $e) {
    // Ignore if table already exists or permission issues
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list_templates':
        $formCode = $_GET['form_code'] ?? '';
        try {
            if ($formCode !== '') {
                // Return explicitly associated certificates OR global ones
                $templates = $db->fetchAll(
                    "SELECT c.*, f.file_name, f.file_original_name
                     FROM nu_word_certificates c
                     LEFT JOIN nu_files f ON c.cert_file_id = f.file_id
                     WHERE c.cert_form_code = :form_code
                        OR c.cert_form_code IS NULL
                        OR c.cert_form_code = ''
                        OR c.cert_form_code = 'global'
                     ORDER BY c.cert_title ASC",
                    [':form_code' => $formCode]
                );
            } else {
                // Return all for admin/listing
                $templates = $db->fetchAll(
                    "SELECT c.*, f.file_name, f.file_original_name
                     FROM nu_word_certificates c
                     LEFT JOIN nu_files f ON c.cert_file_id = f.file_id
                     ORDER BY c.cert_title ASC"
                );
            }
            echo json_encode(['success' => true, 'templates' => $templates]);
        } catch (\Throwable $t) {
            echo json_encode(['success' => false, 'error' => $t->getMessage()]);
        }
        break;

    case 'save_template':
        // Restrict to globeadmin/admin roles
        $role = strtolower((string)($_SESSION['nu_role'] ?? ''));
        if ($role !== 'globeadmin' && $role !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $certId = isset($input['cert_id']) ? (int)$input['cert_id'] : 0;
        $title = $input['cert_title'] ?? '';
        $formCode = $input['cert_form_code'] ?? '';
        $fileId = isset($input['cert_file_id']) && $input['cert_file_id'] !== '' ? (int)$input['cert_file_id'] : null;
        $htmlTemplate = $input['cert_html_template'] ?? '';
        $buttonLabel = $input['cert_button_label'] ?? '';
        $outputNameTemplate = $input['cert_output_name_template'] ?? '';

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Certificate Title is required']);
            exit;
        }

        try {
            $data = [
                'cert_title'                => $title,
                'cert_form_code'            => $formCode,
                'cert_file_id'              => $fileId,
                'cert_html_template'        => $htmlTemplate,
                'cert_button_label'         => $buttonLabel,
                'cert_output_name_template' => $outputNameTemplate,
                'cert_created_by'           => $_SESSION['nu_user_id'] ?? null
            ];

            if ($certId > 0) {
                $db->update('nu_word_certificates', $data, 'cert_id = :id', [':id' => $certId]);
                $id = $certId;
            } else {
                $id = $db->insert('nu_word_certificates', $data);
            }

            $audit = new NuAudit();
            $audit->log('word_cert_save', 'nu_word_certificates', $id);

            echo json_encode(['success' => true, 'cert_id' => $id]);
        } catch (\Throwable $t) {
            echo json_encode(['success' => false, 'error' => $t->getMessage()]);
        }
        break;

    case 'delete_template':
        // Restrict to globeadmin/admin roles
        $role = strtolower((string)($_SESSION['nu_role'] ?? ''));
        if ($role !== 'globeadmin' && $role !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid Certificate ID']);
            exit;
        }

        try {
            $db->delete('nu_word_certificates', 'cert_id = :id', [':id' => $id]);
            $audit = new NuAudit();
            $audit->log('word_cert_delete', 'nu_word_certificates', $id);
            echo json_encode(['success' => true]);
        } catch (\Throwable $t) {
            echo json_encode(['success' => false, 'error' => $t->getMessage()]);
        }
        break;

    case 'generate':
    case 'send_email':
        $certId = isset($_GET['cert_id']) ? (int)$_GET['cert_id'] : 0;
        $recordId = $_GET['record_id'] ?? '';
        $formCode = $_GET['form_code'] ?? '';
        $format = $_GET['format'] ?? 'pdf'; // docx or pdf

        if ($certId <= 0 || empty($recordId) || empty($formCode)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameter cert_id, record_id, or form_code']);
            exit;
        }

        try {
            // Load template
            $template = $db->fetchOne(
                "SELECT c.*, f.file_name, f.file_original_name, f.file_path
                 FROM nu_word_certificates c
                 LEFT JOIN nu_files f ON c.cert_file_id = f.file_id
                 WHERE c.cert_id = :id",
                [':id' => $certId]
            );

            if (!$template) {
                throw new \Exception('Certificate template not found');
            }

            // Load form layout to identify table name
            $form = $db->fetchOne("SELECT * FROM nu_forms WHERE form_code = ? AND form_active = 1", [$formCode]);
            if (!$form || !$form['form_table']) {
                throw new \Exception('Associated form table not found');
            }

            $table = $form['form_table'];
            // Safely resolve primary key
            $pk = 'id';
            try {
                if (function_exists('nu_get_pk')) {
                    $pk = nu_get_pk($table);
                }
            } catch (\Throwable $ignored) {}

            $record = $db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pk}` = ?", [$recordId]);
            if (!$record) {
                throw new \Exception("Record not found in table {$table} with ID {$recordId}");
            }

            // Prepare replacements variables mapping
            $replacements = [];
            foreach ($record as $key => $val) {
                $replacements[$key] = $val ?? '';

                // Add helper checkbox placeholders:
                // If the value is truthy (e.g. 1, '1', true, 'yes', 'on'), use ☑ / Yes. Otherwise ☐ / No.
                $isTruthy = ($val === 1 || $val === '1' || $val === true || strtolower((string)$val) === 'yes' || strtolower((string)$val) === 'on');
                $replacements[$key . '_box'] = $isTruthy ? '☑' : '☐';
                $replacements[$key . '_yesno'] = $isTruthy ? 'Yes' : 'No';
            }

            // Standard metadata variables
            $replacements['current_date']  = date('Y-m-d');
            $replacements['current_time']  = date('H:i:s');
            $replacements['current_user']  = $_SESSION['nu_username'] ?? 'User';
            $replacements['cert_title']    = $template['cert_title'];
            $replacements['company_name']  = $_SESSION['nu_user_meta']['company_name'] ?? 'nuvis Inc.';

            // Custom output name construction
            $nameTemplate = $template['cert_output_name_template'] ?? '';
            if (empty(trim($nameTemplate))) {
                $rawBaseName = $template['cert_title'] . '_' . $recordId;
            } else {
                $rawBaseName = $nameTemplate;
                foreach ($replacements as $key => $val) {
                    $rawBaseName = str_replace('{{' . $key . '}}', (string)$val, $rawBaseName);
                    $rawBaseName = str_replace('{' . $key . '}', (string)$val, $rawBaseName);
                }
            }
            $cleanBaseName = preg_replace('/[^a-zA-Z0-9_ -]/', '_', $rawBaseName);
            $cleanBaseName = trim($cleanBaseName);
            if (empty($cleanBaseName)) {
                $cleanBaseName = 'certificate_' . $recordId;
            }

            if ($format === 'docx') {
                if (empty($template['file_path']) || !file_exists($template['file_path'])) {
                    throw new \Exception('Word template .docx file not uploaded or missing on disk');
                }

                // Copy original to temporary directory
                $tempDir = __DIR__ . '/../sessions/temp_certs/';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $tempFile = $tempDir . uniqid('cert_') . '.docx';
                copy($template['file_path'], $tempFile);

                // Run ZipArchive to manipulate word/document.xml
                $zip = new ZipArchive();
                if ($zip->open($tempFile) === ZipArchive::CHECKCONS) {
                    // Check consistency
                }
                if ($zip->open($tempFile) === true) {
                    $xmlContent = $zip->getFromName('word/document.xml');
                    if ($xmlContent) {
                        // Strip internal formatting XML tags from inside curly braces
                        $xmlContent = preg_replace_callback('/\{\{[^{}]*\}\}/', function($m) {
                            return strip_tags($m[0]);
                        }, $xmlContent);
                        $xmlContent = preg_replace_callback('/\{[^{}]*\}/', function($m) {
                            return strip_tags($m[0]);
                        }, $xmlContent);

                        // Safe replacements
                        foreach ($replacements as $key => $val) {
                            $escapedVal = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                            $xmlContent = str_replace('{{' . $key . '}}', $escapedVal, $xmlContent);
                            $xmlContent = str_replace('{' . $key . '}', $escapedVal, $xmlContent);
                        }

                        $zip->addFromString('word/document.xml', $xmlContent);
                    }
                    $zip->close();
                } else {
                    throw new \Exception('Failed to open Word .docx archive structure');
                }

                $fileData = file_get_contents($tempFile);
                $outFilename = $cleanBaseName . '.docx';
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                // Clean up temporary file
                @unlink($tempFile);
            } else {
                // PDF mode
                $html = $template['cert_html_template'] ?? '';
                if (empty(trim($html))) {
                    if (!empty($template['file_path']) && file_exists($template['file_path'])) {
                        // High-fidelity same-layout conversion from Docx XML directly to PDF HTML!
                        $zip = new ZipArchive();
                        if ($zip->open($template['file_path']) === true) {
                            $xmlContent = $zip->getFromName('word/document.xml');
                            if ($xmlContent) {
                                $html = convertDocxXmlToHtml($xmlContent, $replacements);
                            }
                            $zip->close();
                        }
                    }

                    // Fallback to table if docx extraction failed or doesn't exist
                    if (empty(trim($html))) {
                        // Generate a beautiful, custom dynamic table listing all the fields and values of the active record!
                        $html = '<div style="font-family: Helvetica, Arial, sans-serif; padding: 20px; color: #333;">';
                        $html .= '<h1 style="color: #4f6bed; border-bottom: 2px solid #4f6bed; padding-bottom: 8px; font-size: 24px; font-weight: bold;">' . htmlspecialchars($template['cert_title']) . '</h1>';
                        $html .= '<p style="color: #666; font-size: 12px; margin-bottom: 20px;">Generated on: ' . date('Y-m-d H:i') . '</p>';
                        $html .= '<table cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 10px;">';

                        $rowIdx = 0;
                        foreach ($record as $key => $val) {
                            if (in_array($key, ['password', 'pwd_hash', 'deleted_at'], true)) continue;

                            $label = ucwords(str_replace('_', ' ', $key));
                            $bgColor = ($rowIdx % 2 === 0) ? '#f9f9f9' : '#ffffff';

                            // Treat checkbox / yes-no friendly value formatting for readability
                            $displayVal = (string)($val ?? '-');
                            if ($val === 1 || $val === '1' || $val === true) {
                                $displayVal = '☑ Yes';
                            } elseif ($val === 0 || $val === '0' || $val === false) {
                                $displayVal = '☐ No';
                            }

                            $html .= '<tr style="background-color: ' . $bgColor . ';">';
                            $html .= '<td style="width: 30%; font-weight: bold; border-bottom: 1px solid #eee; font-size: 12px; color: #555;">' . htmlspecialchars($label) . '</td>';
                            $html .= '<td style="width: 70%; border-bottom: 1px solid #eee; font-size: 12px; color: #111;">' . htmlspecialchars($displayVal) . '</td>';
                            $html .= '</tr>';

                            $rowIdx++;
                        }

                        $html .= '</table>';
                        $html .= '<div style="margin-top: 40px; text-align: center; color: #888; font-size: 11px;">';
                        $html .= 'nuvis Certificates System — Verified Secure';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                } else {
                    // Render placeholders in HTML
                    foreach ($replacements as $key => $val) {
                        $html = str_replace('{{' . $key . '}}', htmlspecialchars((string)($val ?? '')), $html);
                    }
                }

                // Wrap or generate using PdfGenerator wrapper structure
                $reportMock = [
                    'report_name'         => $template['cert_title'],
                    'report_code'         => 'certificate',
                    'report_pdf_template' => $html
                ];

                $fileData = NuPdfGenerator::generate($reportMock, [$record], [
                    'orientation'  => 'P',
                    'format'       => 'A4',
                    'company_name' => $replacements['company_name']
                ]);

                $outFilename = $cleanBaseName . '.pdf';
                $mime = 'application/pdf';
            }

            if ($action === 'send_email') {
                $recipient = $_GET['email'] ?? '';
                $subject = $_GET['subject'] ?? '';
                $body = $_GET['body'] ?? '';

                if (empty($recipient)) {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $recipient = $input['email'] ?? '';
                    $subject = $input['subject'] ?? '';
                    $body = $input['body'] ?? '';
                }

                if (empty($recipient)) {
                    throw new \Exception('Recipient Email is required');
                }
                if (empty($subject)) {
                    $subject = "Certificate: " . $template['cert_title'];
                }
                if (empty($body)) {
                    $body = "<p>Please find attached your Certificate: <strong>" . htmlspecialchars($template['cert_title']) . "</strong>.</p>";
                }

                $emailService = new EmailService();
                $attachments = [
                    [
                        'filename' => $outFilename,
                        'mimetype' => $mime,
                        'data'     => $fileData
                    ]
                ];

                $result = $emailService->send($recipient, $subject, $body, ['attachments' => $attachments]);
                echo json_encode($result);
            } else {
                // Stream download response
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="' . $outFilename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . strlen($fileData));
                echo $fileData;
            }
        } catch (\Throwable $t) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $t->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}

/**
 * Cleanly translates MS Word paragraphs, runs, alignments, and styling tags directly into high-fidelity inline-styled HTML blocks.
 */
function convertDocxXmlToHtml(string $xmlContent, array $replacements): string {
    // 1. Clean up and heal split placeholders inside XML
    $xmlContent = preg_replace_callback('/\{\{[^{}]*\}\}/', function($m) {
        return strip_tags($m[0]);
    }, $xmlContent);
    $xmlContent = preg_replace_callback('/\{[^{}]*\}/', function($m) {
        return strip_tags($m[0]);
    }, $xmlContent);

    // 2. Load into DOM
    $dom = new DOMDocument();
    @$dom->loadXML($xmlContent);

    $body = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'body')->item(0);
    if (!$body) return '';

    return parseNodeChildren($body, $replacements);
}

function parseNodeChildren($parentNode, array $replacements): string {
    $html = '';
    foreach ($parentNode->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;

        $localName = $node->localName;
        if ($localName === 'p') {
            $html .= parseParagraphNode($node, $replacements);
        } elseif ($localName === 'tbl') {
            $html .= parseTableNode($node, $replacements);
        } else {
            // Recursively parse any other containers to ensure we do not miss nested nodes
            $html .= parseNodeChildren($node, $replacements);
        }
    }
    return $html;
}

function parseParagraphNode($pNode, array $replacements): string {
    $align = 'left';
    $jcNodes = $pNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'jc');
    if ($jcNodes->length > 0) {
        $val = $jcNodes->item(0)->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
        if (in_array($val, ['center', 'right', 'justify'])) {
            $align = $val;
        }
    }

    $pStyle = 'text-align: ' . $align . '; margin-bottom: 8px; font-size: 13px;';
    $pContent = '';

    $runs = $pNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'r');
    foreach ($runs as $r) {
        $isBold = $r->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'b')->length > 0;
        $isItalic = $r->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'i')->length > 0;
        $isUnderline = $r->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'u')->length > 0;

        $textNodes = $r->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
        $runText = '';
        foreach ($textNodes as $t) {
            $runText .= $t->nodeValue;
        }

        if ($runText === '') continue;

        // Replaces placeholders inside this text run
        foreach ($replacements as $key => $val) {
            $runText = str_replace('{{' . $key . '}}', (string)$val, $runText);
            $runText = str_replace('{' . $key . '}', (string)$val, $runText);
        }

        // Format HTML tags
        $formatted = htmlspecialchars($runText, ENT_QUOTES, 'UTF-8');
        if ($isBold)      $formatted = '<strong>' . $formatted . '</strong>';
        if ($isItalic)    $formatted = '<em>' . $formatted . '</em>';
        if ($isUnderline) $formatted = '<u>' . $formatted . '</u>';

        $pContent .= $formatted;
    }

    if ($pContent !== '') {
        return '<p style="' . $pStyle . '">' . $pContent . '</p>';
    }
    return '';
}

function parseTableNode($tblNode, array $replacements): string {
    $html = '<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #111;">';

    $rows = $tblNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tr');
    foreach ($rows as $row) {
        $html .= '<tr>';

        $cells = [];
        foreach ($row->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'tc') {
                $cells[] = $child;
            }
        }

        foreach ($cells as $cell) {
            // Read cell background shading
            $bgColorAttr = '';
            $shdNodes = $cell->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'shd');
            if ($shdNodes->length > 0) {
                $fill = $shdNodes->item(0)->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'fill');
                if ($fill && $fill !== 'auto') {
                    $bgColorAttr = ' background-color: #' . $fill . ';';
                }
            }

            // Read cell gridSpan (colspan)
            $colspanAttr = '';
            $spanNodes = $cell->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'gridSpan');
            if ($spanNodes->length > 0) {
                $spanVal = $spanNodes->item(0)->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
                if ($spanVal) {
                    $colspanAttr = ' colspan="' . intval($spanVal) . '"';
                }
            }

            $cellStyle = 'border: 1px solid #111;' . $bgColorAttr;
            $html .= '<td' . $colspanAttr . ' style="' . $cellStyle . '">';

            foreach ($cell->childNodes as $tcChild) {
                if ($tcChild->nodeType === XML_ELEMENT_NODE && $tcChild->localName === 'p') {
                    $html .= parseParagraphNode($tcChild, $replacements);
                } elseif ($tcChild->nodeType === XML_ELEMENT_NODE && $tcChild->localName === 'tbl') {
                    $html .= parseTableNode($tcChild, $replacements);
                }
            }

            $html .= '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}
?>
