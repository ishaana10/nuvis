<?php
declare(strict_types=1);
/**
 * api/updater.php — Application Update & Configuration Manager
 * Admin-only tool for Git operations and config editing.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

header('Content-Type: application/json');

// ── Auth + admin gate ──────────────────────────────────────────────────
$auth = NuAuth::getInstance();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$user = $auth->getCurrentUser();
$role = strtolower((string)($user['usr_role'] ?? ''));
if ($role !== 'globeadmin' && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = (string)($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'git_status':
            $status = shell_exec('git status 2>&1');
            $branch = shell_exec('git rev-parse --abbrev-ref HEAD 2>&1');
            echo json_encode([
                'success' => true,
                'status'  => trim((string)$status),
                'branch'  => trim((string)$branch),
                'version' => NU_VERSION
            ]);
            break;

        case 'git_fetch':
            $output = shell_exec('git fetch origin 2>&1');
            echo json_encode(['success' => true, 'output' => trim((string)$output)]);
            break;

        case 'git_pull':
            // Pulling into main branch as requested
            $output = shell_exec('git pull origin main 2>&1');
            echo json_encode(['success' => true, 'output' => trim((string)$output)]);
            break;

        case 'git_log':
            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 10)));
            $output = shell_exec("git log -n $limit --pretty=format:'%h|%an|%ar|%s' 2>&1");
            $lines  = explode("\n", trim((string)$output));
            $commits = [];
            foreach ($lines as $line) {
                if (!$line) continue;
                $parts = explode('|', $line, 4);
                $commits[] = [
                    'hash'   => $parts[0] ?? '',
                    'author' => $parts[1] ?? '',
                    'date'   => $parts[2] ?? '',
                    'msg'    => $parts[3] ?? ''
                ];
            }
            echo json_encode(['success' => true, 'commits' => $commits]);
            break;

        case 'config_read':
            $path = __DIR__ . '/../config.local.php';
            $content = file_exists($path) ? file_get_contents($path) : "<?php\n// Local configuration\n";
            echo json_encode(['success' => true, 'content' => $content, 'writable' => is_writable(file_exists($path) ? $path : dirname($path))]);
            break;

        case 'config_write':
            $path = __DIR__ . '/../config.local.php';
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true);
            $content = (string)($body['content'] ?? '');
            if (strpos($content, '<?php') !== 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid PHP file: must start with <?php']);
                break;
            }
            // Backup
            if (file_exists($path)) {
                copy($path, $path . '.bak.' . date('YmdHis'));
            }
            $res = file_put_contents($path, $content);
            if ($res === false) {
                echo json_encode(['success' => false, 'error' => 'Failed to write config.local.php. Check permissions.']);
            } else {
                echo json_encode(['success' => true, 'bytes' => $res]);
            }
            break;

        case 'ping':
            echo json_encode(['success' => true, 'message' => 'pong']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
