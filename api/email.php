<?php
/**
 * api/email.php
 * REST API endpoint for email operations:
 *   POST  action=send            - Send email (direct or via template)
 *   POST  action=test            - Send test email to verify SMTP config
 *   GET   action=templates       - List all email templates
 *   POST  action=save_template   - Create or update a template
 *   POST  action=delete_template - Delete a template
 *   GET   action=logs            - Paginated email send log
 *   GET   action=get_settings    - Retrieve DB email settings
 *   POST  action=save_settings   - Persist email settings to DB
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/EmailService.php';
require_once __DIR__ . '/../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$db     = NuDatabase::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (empty($input)) $input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

// Ensure email tables exist
try {
    $db->fetchAll("SELECT 1 FROM nu_email_settings LIMIT 1");
} catch (Throwable $t) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `nu_email_settings` (
              `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
              `setting_value` TEXT NOT NULL,
              `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $db->exec("
            INSERT IGNORE INTO `nu_email_settings` (`setting_key`, `setting_value`) VALUES
              ('driver',        'mail'),
              ('smtp_host',     ''),
              ('smtp_port',     '587'),
              ('smtp_secure',   'tls'),
              ('smtp_auth',     '1'),
              ('smtp_username', ''),
              ('smtp_password', ''),
              ('from_email',    'noreply@example.com'),
              ('from_name',     'nub5-dev');
        ");
    } catch (Throwable $t2) {}
}

try {
    $db->fetchAll("SELECT 1 FROM nu_email_templates LIMIT 1");
} catch (Throwable $t) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `nu_email_templates` (
              `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `name`        VARCHAR(150) NOT NULL,
              `slug`        VARCHAR(100) NOT NULL UNIQUE,
              `description` TEXT,
              `subject`     VARCHAR(255) NOT NULL,
              `body`        LONGTEXT NOT NULL,
              `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
              `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
              `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $t2) {}
}

try {
    $db->fetchAll("SELECT 1 FROM nu_email_log LIMIT 1");
} catch (Throwable $t) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `nu_email_log` (
              `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `recipient`     VARCHAR(500) NOT NULL,
              `subject`       VARCHAR(255) NOT NULL,
              `status`        ENUM('SENT','FAIL') NOT NULL DEFAULT 'SENT',
              `error_message` VARCHAR(1000) DEFAULT NULL,
              `template_slug` VARCHAR(100) DEFAULT NULL,
              `sent_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_status`   (`status`),
              INDEX `idx_sent_at`  (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $t2) {}
}

try {
    switch ($action) {

        // ------------------------------------------------------------------
        case 'send':
            $to      = $input['to']      ?? '';
            $subject = $input['subject'] ?? '';
            $body    = $input['body']    ?? '';
            $tplSlug = $input['template_slug'] ?? '';
            $vars    = $input['variables'] ?? [];
            $options = [
                'cc'        => $input['cc']  ?? [],
                'bcc'       => $input['bcc'] ?? [],
                'reply_to'  => $input['reply_to'] ?? '',
            ];

            if (!$to) throw new \InvalidArgumentException('Recipient (to) is required.');

            if ($tplSlug) {
                $rendered = EmailService::renderTemplate($tplSlug, $vars);
                if (!$rendered) throw new \RuntimeException("Template '{$tplSlug}' not found or inactive.");
                $subject = $rendered['subject'];
                $body    = $rendered['body'];
            }

            if (!$subject || !$body) throw new \InvalidArgumentException('Subject and body are required.');

            $svc    = new EmailService();
            $result = $svc->send($to, $subject, $body, $options);
            echo json_encode($result);
            break;

        // ------------------------------------------------------------------
        case 'test':
            $currentUser = $auth->getCurrentUser();
            $to  = $input['to'] ?? ($currentUser['usr_email'] ?? '');
            if (!$to) throw new \InvalidArgumentException('Recipient email required.');
            $svc    = new EmailService();
            $result = $svc->send($to, 'nub5-dev Email Test', '<h2 style="font-family:sans-serif">Email system working ✓</h2><p style="font-family:sans-serif">Your nub5-dev email configuration is correct.</p>');
            echo json_encode($result);
            break;

        // ------------------------------------------------------------------
        case 'templates':
            $templates = $db->fetchAll("SELECT * FROM nu_email_templates ORDER BY name ASC");
            echo json_encode(['success' => true, 'data' => $templates]);
            break;

        // ------------------------------------------------------------------
        case 'save_template':
            $id          = (int)($input['id'] ?? 0);
            $name        = trim((string)($input['name'] ?? ''));
            $slug        = trim((string)($input['slug'] ?? ''));
            $subject     = trim((string)($input['subject'] ?? ''));
            $body        = trim((string)($input['body'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $active      = (int)($input['is_active'] ?? 1);

            if (!$name || !$slug || !$subject || !$body)
                throw new \InvalidArgumentException('name, slug, subject, and body are required.');

            if ($id > 0) {
                $db->query(
                    "UPDATE nu_email_templates SET name=?, slug=?, subject=?, body=?, description=?, is_active=?, updated_at=NOW() WHERE id=?",
                    [$name, $slug, $subject, $body, $description, $active, $id]
                );
                echo json_encode(['success' => true, 'message' => 'Template updated.']);
            } else {
                $db->query(
                    "INSERT INTO nu_email_templates (name, slug, subject, body, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$name, $slug, $subject, $body, $description, $active]
                );
                echo json_encode(['success' => true, 'message' => 'Template created.']);
            }
            break;

        // ------------------------------------------------------------------
        case 'delete_template':
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new \InvalidArgumentException('Template id required.');
            $db->query("DELETE FROM nu_email_templates WHERE id=?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Template deleted.']);
            break;

        // ------------------------------------------------------------------
        case 'logs':
            $limit  = min((int)($_GET['limit']  ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $logs   = $db->fetchAll("SELECT * FROM nu_email_log ORDER BY sent_at DESC LIMIT ? OFFSET ?", [$limit, $offset]);
            $countRow = $db->fetchOne("SELECT COUNT(*) AS c FROM nu_email_log");
            $total  = (int)($countRow['c'] ?? 0);
            echo json_encode(['success' => true, 'data' => $logs, 'total' => $total]);
            break;

        // ------------------------------------------------------------------
        case 'get_settings':
            $rows   = $db->fetchAll("SELECT setting_key, setting_value FROM nu_email_settings");
            $config = [];
            foreach ($rows as $row) {
                $config[$row['setting_key']] = $row['setting_value'];
            }
            echo json_encode(['success' => true, 'data' => $config]);
            break;

        // ------------------------------------------------------------------
        case 'save_settings':
            $settings = $input['settings'] ?? [];
            if (empty($settings)) throw new \InvalidArgumentException('No settings provided.');

            foreach ($settings as $setting) {
                $key   = trim((string)($setting['key']   ?? ''));
                $value = trim((string)($setting['value'] ?? ''));
                if (!$key) continue;
                // UPSERT
                $db->query("
                    INSERT INTO nu_email_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()
                ", [$key, $value, $value]);
            }
            echo json_encode(['success' => true, 'message' => 'Settings saved.']);
            break;

        // ------------------------------------------------------------------
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
