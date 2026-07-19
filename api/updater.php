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

/**
 * Safely fetches the Git configurations from database settings with fallbacks.
 */
function get_git_config(NuDatabase $db): array {
    // Ensure table exists
    $db->query("CREATE TABLE IF NOT EXISTS `nu_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $settings = [
        'git_path' => 'git',
        'git_repo_dir' => realpath(__DIR__ . '/..') ?: '/app',
        'update_branch' => 'main'
    ];

    foreach ($settings as $key => $default) {
        $row = $db->fetchOne("SELECT setting_value FROM nu_settings WHERE setting_key = ?", [$key]);
        if ($row !== null) {
            $settings[$key] = trim((string)$row['setting_value']);
        }
    }

    // Double check fallsbacks in case DB stored empty strings
    if (empty($settings['git_path'])) {
        $settings['git_path'] = 'git';
    }
    if (empty($settings['git_repo_dir'])) {
        $settings['git_repo_dir'] = realpath(__DIR__ . '/..') ?: '/app';
    }
    if (empty($settings['update_branch'])) {
        $settings['update_branch'] = 'main';
    }

    return $settings;
}

$action = (string)($_GET['action'] ?? '');
try {
    switch ($action) {
        case 'git_status':
            $db = NuDatabase::getInstance();
            $settings = get_git_config($db);

            $git_path = $settings['git_path'];
            $git_repo_dir = $settings['git_repo_dir'];
            $selectedBranch = $settings['update_branch'];

            // Construct git prefix command using -C and safe.directory
            $gitCmdPrefix = escapeshellarg($git_path) . " -C " . escapeshellarg($git_repo_dir) . " -c safe.directory=* ";

            $status = shell_exec($gitCmdPrefix . 'status 2>&1');
            $branch = shell_exec($gitCmdPrefix . 'rev-parse --abbrev-ref HEAD 2>&1');

            // Dynamically query remote/local branches using branch -a
            $branchesOutput = shell_exec($gitCmdPrefix . "branch -a 2>&1");
            $remoteBranches = [];
            if ($branchesOutput && strpos($branchesOutput, 'fatal:') === false) {
                $lines = explode("\n", $branchesOutput);
                foreach ($lines as $line) {
                    $line = trim($line, "* \t\r\n");
                    if (!$line) continue;
                    // Filter HEAD pointer and extract branch name
                    if (strpos($line, 'remotes/origin/HEAD') !== false) continue;
                    if (strpos($line, 'remotes/origin/') === 0) {
                        $b = substr($line, 15);
                    } elseif (strpos($line, 'origin/') === 0) {
                        $b = substr($line, 7);
                    } else {
                        $b = $line;
                    }
                    if ($b && !in_array($b, $remoteBranches)) {
                        $remoteBranches[] = $b;
                    }
                }
            }

            if (empty($remoteBranches)) {
                $remoteBranches = [$selectedBranch];
            }

            echo json_encode([
                'success' => true,
                'status'  => trim((string)$status),
                'branch'  => trim((string)$branch),
                'version' => NU_VERSION,
                'selected_branch' => $selectedBranch,
                'remote_branches' => $remoteBranches,
                'git_path' => $git_path,
                'git_repo_dir' => $git_repo_dir
            ]);
            break;

        case 'save_branch':
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true);
            $newBranch = trim((string)($body['branch'] ?? 'main'));
            if (!$newBranch) {
                $newBranch = 'main';
            }

            $db = NuDatabase::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS `nu_settings` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $db->query("INSERT INTO nu_settings (setting_key, setting_value) VALUES ('update_branch', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$newBranch, $newBranch]);
            echo json_encode(['success' => true]);
            break;

        case 'save_git_settings':
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true);
            $gitPath = trim((string)($body['git_path'] ?? 'git'));
            $gitRepoDir = trim((string)($body['git_repo_dir'] ?? ''));
            $updateBranch = trim((string)($body['update_branch'] ?? 'main'));

            if (!$gitPath) {
                $gitPath = 'git';
            }

            // Check for spaces/clone commands to prevent misconfiguration errors
            if (preg_match('/\s+/', $gitPath) || stripos($gitPath, 'clone') !== false || stripos($gitPath, 'gh ') !== false) {
                echo json_encode([
                    'success' => false,
                    'error' => "Invalid Git Executable Path: Please enter only 'git' or the absolute path to the git binary (e.g. '/usr/bin/git'). Do NOT enter clone or checkout commands."
                ]);
                break;
            }

            if (!$gitRepoDir) {
                $gitRepoDir = realpath(__DIR__ . '/..') ?: '/app';
            }
            if (!$updateBranch) {
                $updateBranch = 'main';
            }

            $db = NuDatabase::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS `nu_settings` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->query("INSERT INTO nu_settings (setting_key, setting_value) VALUES ('git_path', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$gitPath, $gitPath]);
            $db->query("INSERT INTO nu_settings (setting_key, setting_value) VALUES ('git_repo_dir', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$gitRepoDir, $gitRepoDir]);
            $db->query("INSERT INTO nu_settings (setting_key, setting_value) VALUES ('update_branch', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$updateBranch, $updateBranch]);

            echo json_encode(['success' => true]);
            break;

        case 'test_git_settings':
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true);
            $gitPath = trim((string)($body['git_path'] ?? ''));
            $gitRepoDir = trim((string)($body['git_repo_dir'] ?? ''));

            if (!$gitPath) {
                echo json_encode(['success' => false, 'error' => 'Git Executable Path cannot be empty.']);
                break;
            }

            // Reject commands containing multiple words or clone arguments to prevent common configuration mistakes
            if (preg_match('/\s+/', $gitPath) || stripos($gitPath, 'clone') !== false || stripos($gitPath, 'gh ') !== false) {
                echo json_encode([
                    'success' => false,
                    'error' => "Invalid Git Executable Path: Please enter only 'git' or the absolute path to the git binary (e.g. '/usr/bin/git'). Do NOT enter clone or checkout commands here."
                ]);
                break;
            }

            if (!$gitRepoDir) {
                echo json_encode(['success' => false, 'error' => 'Git Repository Root Directory cannot be empty.']);
                break;
            }

            if (!is_dir($gitRepoDir)) {
                echo json_encode(['success' => false, 'error' => "The directory '{$gitRepoDir}' does not exist or is not accessible."]);
                break;
            }

            if (!is_dir(rtrim($gitRepoDir, '/') . '/.git')) {
                echo json_encode([
                    'success' => false,
                    'error' => "The directory '{$gitRepoDir}' exists, but it does not appear to be a git repository (no '.git' directory found)."
                ]);
                break;
            }

            // Verify the Git binary is executable and works by running --version
            $gitEscaped = escapeshellarg($gitPath);
            $versionOutput = shell_exec("{$gitEscaped} --version 2>&1");
            if (!$versionOutput || stripos($versionOutput, 'version') === false) {
                echo json_encode([
                    'success' => false,
                    'error' => "Failed to run Git with path '{$gitPath}'. Error details: " . trim((string)$versionOutput)
                ]);
                break;
            }

            // Run a quick status test using the custom prefix
            $gitCmdPrefix = $gitEscaped . " -C " . escapeshellarg($gitRepoDir) . " -c safe.directory=* ";
            $statusOutput = shell_exec($gitCmdPrefix . 'status 2>&1');
            if (stripos($statusOutput, 'fatal:') !== false) {
                echo json_encode([
                    'success' => false,
                    'error' => "Git executable is working, but repository check failed. Git output: " . trim((string)$statusOutput)
                ]);
                break;
            }

            echo json_encode([
                'success' => true,
                'message' => "Connection successful!\nGit version: " . trim((string)$versionOutput) . "\nRepository status: OK"
            ]);
            break;

        case 'git_fetch':
            $db = NuDatabase::getInstance();
            $settings = get_git_config($db);
            $gitCmdPrefix = escapeshellarg($settings['git_path']) . " -C " . escapeshellarg($settings['git_repo_dir']) . " -c safe.directory=* ";

            $output = shell_exec($gitCmdPrefix . 'fetch origin 2>&1');
            echo json_encode(['success' => true, 'output' => trim((string)$output)]);
            break;

        case 'git_pull':
            $db = NuDatabase::getInstance();
            $settings = get_git_config($db);
            $git_path = $settings['git_path'];
            $git_repo_dir = $settings['git_repo_dir'];
            $selectedBranch = $settings['update_branch'];

            $gitCmdPrefix = escapeshellarg($git_path) . " -C " . escapeshellarg($git_repo_dir) . " -c safe.directory=* ";
            $selectedBranchEscaped = escapeshellarg($selectedBranch);

            // Switch branch and pull
            shell_exec($gitCmdPrefix . "checkout {$selectedBranchEscaped} 2>&1");
            $output = shell_exec($gitCmdPrefix . "pull origin {$selectedBranchEscaped} 2>&1");
            echo json_encode(['success' => true, 'output' => trim((string)$output), 'pulled_branch' => $selectedBranch]);
            break;

        case 'git_log':
            $db = NuDatabase::getInstance();
            $settings = get_git_config($db);
            $gitCmdPrefix = escapeshellarg($settings['git_path']) . " -C " . escapeshellarg($settings['git_repo_dir']) . " -c safe.directory=* ";

            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 10)));
            $output = shell_exec($gitCmdPrefix . "log -n $limit --pretty=format:'%h|%an|%ar|%s' 2>&1");
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
