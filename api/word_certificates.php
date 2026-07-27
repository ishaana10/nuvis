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
                    $html = NuPdfGenerator::getCertificateTemplate();
                }

                // Render placeholders in HTML
                foreach ($replacements as $key => $val) {
                    $html = str_replace('{{' . $key . '}}', htmlspecialchars((string)($val ?? '')), $html);
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
?>
