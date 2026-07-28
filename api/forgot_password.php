<?php
declare(strict_types=1);
/**
 * REST API for Password Resets
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/EmailService.php';

$db = NuDatabase::getInstance();
$auth = NuAuth::getInstance();

// Self-healing: Ensure nu_password_resets table exists
try {
    $tableExists = $db->getPdo()->query("SHOW TABLES LIKE 'nu_password_resets'")->fetch();
    if (!$tableExists) {
        $db->exec("CREATE TABLE `nu_password_resets` (
            `reset_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(50) NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token_hash` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        // Upgrade existing table's user_id column if it is an INT
        $colInfo = $db->fetchOne("SHOW COLUMNS FROM `nu_password_resets` LIKE 'user_id'");
        if ($colInfo && stripos($colInfo['Type'], 'varchar') === false) {
            $db->exec("ALTER TABLE `nu_password_resets` MODIFY `user_id` VARCHAR(50) NOT NULL");
        }
    }
} catch (Throwable $e) {
    error_log('[forgot_password.php self-healing] ' . $e->getMessage());
}

// ─── Helper: load active password policy ─────────────────────────────────────
function forgot_loadPolicy(NuDatabase $db): array {
    $row = $db->fetchOne("SELECT * FROM nu_password_policy WHERE policy_id = 1");
    return $row ?: [
        'policy_min_length'        => 8,
        'policy_require_uppercase' => 1,
        'policy_require_lowercase'  => 1,
        'policy_require_number'    => 1,
        'policy_require_special'   => 0,
        'policy_disallow_username' => 1,
        'policy_history_count'     => 5,
        'policy_expiry_days'       => 0,
        'policy_expiry_warning_days' => 7,
        'policy_force_change_on_first_login' => 1,
    ];
}

// ─── Helper: validate password against policy ────────────────────────────────
function forgot_validatePassword(string $password, array $policy, string $username = ''): array {
    $errors = [];
    if (strlen($password) < (int)$policy['policy_min_length']) {
        $errors[] = 'Password must be at least ' . $policy['policy_min_length'] . ' characters.';
    }
    if ($policy['policy_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }
    if ($policy['policy_require_lowercase'] && !preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter.';
    }
    if ($policy['policy_require_number'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number.';
    }
    if ($policy['policy_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character.';
    }
    if ($policy['policy_disallow_username'] && $username !== '' && stripos($password, $username) !== false) {
        $errors[] = 'Password must not contain your username.';
    }
    return $errors;
}

// ─── Helper: check password history ─────────────────────────────────────────
function forgot_isPasswordReused(NuDatabase $db, $userId, string $newPassword, int $historyCount): bool {
    if ($historyCount <= 0) return false;
    $rows = $db->fetchAll(
        "SELECT ph_hash FROM nu_password_history WHERE ph_user_id = :uid ORDER BY ph_created_at DESC LIMIT " . (int)$historyCount,
        [':uid' => $userId]
    );
    foreach ($rows as $r) {
        if (password_verify($newPassword, $r['ph_hash'])) return true;
    }
    return false;
}

// ─── Helper: record password history ─────────────────────────────────────────
function forgot_recordPasswordHistory(NuDatabase $db, $userId, string $hash, int $historyCount): void {
    if ($historyCount <= 0) return;
    $db->query(
        "INSERT INTO nu_password_history (ph_user_id, ph_hash) VALUES (:uid, :hash)",
        [':uid' => $userId, ':hash' => $hash]
    );
    // Prune old history beyond limit
    $lim = (int)max(20, $historyCount);
    $db->query(
        "DELETE FROM nu_password_history WHERE ph_user_id = :uid
         AND ph_id NOT IN (
             SELECT ph_id FROM (
                 SELECT ph_id FROM nu_password_history WHERE ph_user_id = :uid2
                 ORDER BY ph_created_at DESC LIMIT " . $lim . "
             ) AS t
         )",
         [':uid' => $userId, ':uid2' => $userId]
    );
}

// Only run the routing logic when hit directly
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'forgot_password.php') {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'send_reset_link':
            try {
                // Check if feature is enabled in developer settings
                $toggle = $db->fetchOne("SELECT setting_value FROM nu_system_settings WHERE setting_key = 'forgot_password_enabled'");
                $enabled = $toggle ? ($toggle['setting_value'] === '1') : true;
                if (!$enabled) {
                    echo json_encode(['success' => false, 'error' => 'Forgot password module is currently disabled.']);
                    exit;
                }

                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $identity = trim((string)($input['identity'] ?? ''));

                if ($identity === '') {
                    echo json_encode(['success' => false, 'error' => 'Please enter your username or email address.']);
                    exit;
                }

                // Find user by username or email
                $user = $db->fetchOne(
                    "SELECT * FROM nu_users WHERE (usr_username = :id OR usr_email = :id) AND usr_active = 1",
                    [':id' => $identity]
                );

                if (!$user) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Account not found or is currently inactive.'
                    ]);
                    exit;
                }

                $userEmail = trim((string)($user['usr_email'] ?? ''));
                if ($userEmail === '') {
                    echo json_encode([
                        'success' => false,
                        'error' => 'This account does not have a registered email address. Please contact an administrator to reset your password.'
                    ]);
                    exit;
                }

                // Generate cryptographically secure token
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                // Expiry is 1 hour from now
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                // Save reset token in DB
                $db->insert('nu_password_resets', [
                    'user_id'    => $user['usr_id'],
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                    'used'       => 0
                ]);

                // Construct Reset Link URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                // Get subdirectory path if exists
                $script = $_SERVER['SCRIPT_NAME'] ?? '/api/forgot_password.php'; // e.g. /nbv5u/m/api/forgot_password.php
                $subDir = dirname(dirname($script)); // e.g. /nbv5u/m
                $baseUrl = $protocol . $host . rtrim($subDir, '/') . '/';

                $resetUrl = $baseUrl . 'index.php?action=reset_password&token=' . urlencode($rawToken);

                // Send Email using robust EmailService
                $emailService = new EmailService();
                $subject = 'Reset Your Password - ' . ($nuConfig['siteTitle'] ?? 'nuvis');

                // HTML Body with Modern Styling
                $body = '
                <div style="font-family: \'Inter\', sans-serif, Arial; max-width: 600px; margin: 0 auto; padding: 40px 20px; background: #0f172a; color: #f8fafc; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <div style="text-align: center; margin-bottom: 32px;">
                        <h2 style="color: #4f6bed; margin: 0; font-size: 24px; font-weight: 700;">Password Reset Request</h2>
                        <p style="color: #94a3b8; font-size: 14px; margin-top: 8px;">For your account ' . htmlspecialchars($user['usr_username']) . '</p>
                    </div>
                    <div style="background: #1e293b; padding: 24px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #334155;">
                        <p style="font-size: 16px; line-height: 1.6; margin: 0 0 16px 0;">Hello,</p>
                        <p style="font-size: 14px; line-height: 1.6; color: #cbd5e1; margin: 0 0 24px 0;">You requested a password reset. Please click the button below to set a new password. This link is valid for <strong>1 hour</strong>.</p>
                        <div style="text-align: center; margin: 32px 0;">
                            <a href="' . htmlspecialchars($resetUrl) . '" target="_blank" style="background: #4f6bed; color: #ffffff; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block; transition: background 0.2s;">Reset Password</a>
                        </div>
                        <p style="font-size: 12px; line-height: 1.5; color: #64748b; margin: 24px 0 0 0;">If you did not request this reset, you can safely ignore this email; your password will remain unchanged.</p>
                    </div>
                    <div style="text-align: center; font-size: 11px; color: #475569;">
                        <p style="margin: 0;">This email was sent automatically. Please do not reply.</p>
                    </div>
                </div>';

                $result = $emailService->send($userEmail, $subject, $body);

                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'If a matching account is found, a secure password reset link will be sent to the registered email address.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to send reset email. Please contact support. Error: ' . $result['message']
                    ]);
                }

            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
            }
            break;

        case 'reset_password':
            try {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $rawToken = trim((string)($input['token'] ?? ''));
                $newPwd = trim((string)($input['new_password'] ?? ''));
                $confirmPwd = trim((string)($input['confirm_password'] ?? ''));

                if ($rawToken === '') {
                    echo json_encode(['success' => false, 'error' => 'Invalid or missing password reset token.']);
                    exit;
                }

                if ($newPwd === '' || $confirmPwd === '') {
                    echo json_encode(['success' => false, 'error' => 'Please fill in all password fields.']);
                    exit;
                }

                if ($newPwd !== $confirmPwd) {
                    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
                    exit;
                }

                // Find valid, non-expired, unused token
                $tokenHash = hash('sha256', $rawToken);
                $resetRecord = $db->fetchOne(
                    "SELECT * FROM nu_password_resets WHERE token_hash = :hash AND used = 0 AND expires_at > NOW() LIMIT 1",
                    [':hash' => $tokenHash]
                );

                if (!$resetRecord) {
                    echo json_encode(['success' => false, 'error' => 'The password reset token is invalid, has already been used, or has expired. Please request a new link.']);
                    exit;
                }

                $userId = $resetRecord['user_id'];
                $user = $db->fetchOne("SELECT * FROM nu_users WHERE usr_id = :id AND usr_active = 1", [':id' => $userId]);

                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Associated user account is inactive or was not found.']);
                    exit;
                }

                // Load password policy & validate new password strength
                $policy = forgot_loadPolicy($db);
                $errors = forgot_validatePassword($newPwd, $policy, $user['usr_username']);
                if ($errors) {
                    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
                    exit;
                }

                // Validate against password history
                if (forgot_isPasswordReused($db, $userId, $newPwd, (int)$policy['policy_history_count'])) {
                    echo json_encode(['success' => false, 'error' => 'You cannot reuse a recent password. Please choose a different one.']);
                    exit;
                }

                // Update user's password, force_change if policy requires first-login, and log to history
                $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
                forgot_recordPasswordHistory($db, $userId, $user['usr_password'], (int)$policy['policy_history_count']);

                $db->update('nu_users', [
                    'usr_password' => $newHash,
                    'usr_password_changed_at' => date('Y-m-d H:i:s'),
                    'usr_must_change_password' => 0
                ], "usr_id = :id", [':id' => $userId]);

                // Mark reset token as used
                $db->update('nu_password_resets', [
                    'used' => 1
                ], "reset_id = :rid", [':rid' => $resetRecord['reset_id']]);

                echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully! You can now sign in with your new password.']);

            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}
