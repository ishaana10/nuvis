<?php
declare(strict_types=1);
/**
 * NuAuth - Authentication, Session Management & Permission Engine
 */

require_once __DIR__ . '/Audit.php';

class NuAuth {
    private $db;
    private $config;
    private static $instance = null;

    public function __construct() {
        global $nuConfig;
        $this->config = $nuConfig;
        $this->db = NuDatabase::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // --- Login ---
    public function login($username, $password, $otp = null) {
        $user = $this->db->fetchOne(
            "SELECT * FROM nu_users WHERE usr_username = :u AND usr_active = 1",
            [':u' => $username]
        );

        if (!$user) return ['success' => false, 'message' => 'Invalid credentials'];

        // Check lockout
        if ($user['usr_failed_attempts'] >= $this->config['maxLoginAttempts']) {
            $lastAttempt = strtotime($user['usr_last_attempt']);
            if (time() - $lastAttempt < $this->config['lockoutDuration']) {
                return ['success' => false, 'message' => 'Account locked. Try again later.'];
            }
            $this->resetAttempts($user['usr_id']);
        }

        // Verify password
        if (!password_verify($password, $user['usr_password'])) {
            $this->incrementAttempts($user['usr_id']);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // 2FA check
        if ($this->config['enable2FA'] && $user['usr_2fa_secret']) {
            if (!$otp || !$this->verifyTOTP($user['usr_2fa_secret'], $otp)) {
                return ['success' => false, 'message' => 'Invalid or missing 2FA code', 'requires2FA' => true];
            }
        }

        // Success
        $this->resetAttempts($user['usr_id']);
        $this->createSession($user);
        $this->logAudit('login', 'nu_users', $user['usr_id']);

        return ['success' => true, 'user' => $this->sanitizeUser($user)];
    }

    // --- Logout ---
    public function logout() {
        if (isset($_SESSION['nu_user_id'])) {
            $this->logAudit('logout', 'nu_users', $_SESSION['nu_user_id']);
        }
        session_destroy();
        return true;
    }

    // --- Session Check ---
    public function checkAuth() {
        if (empty($_SESSION['nu_user_id'])) return false;
        if (time() - $_SESSION['nu_last_activity'] > $this->config['sessionTimeout']) {
            $this->logout();
            return false;
        }
        $_SESSION['nu_last_activity'] = time();
        return true;
    }

    public function isLoggedIn() {
        return $this->checkAuth();
    }

    // --- Current User ---
    public function getCurrentUser() {
        if (!$this->checkAuth()) return null;
        return $this->db->fetchOne(
            "SELECT * FROM nu_users WHERE usr_id = :id",
            [':id' => $_SESSION['nu_user_id']]
        );
    }

    // --- CSRF ---
    public function getCsrfToken() {
        if (empty($_SESSION['nu_csrf'])) {
            $_SESSION['nu_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['nu_csrf'];
    }

    public function verifyCsrf($token) {
        return isset($_SESSION['nu_csrf']) && hash_equals($_SESSION['nu_csrf'], $token);
    }

    // --- Permissions ---
    public function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        if ($user['usr_role'] === 'globeadmin') return true;

        $perms = $this->db->fetchAll(
            "SELECT p.perm_code FROM nu_permissions p
             JOIN nu_role_permissions rp ON p.perm_id = rp.rp_perm_id
             JOIN nu_roles r ON rp.rp_role_id = r.role_id
             WHERE r.role_code = :role",
            [':role' => $user['usr_role']]
        );

        $permCodes = array_column($perms, 'perm_code');
        return in_array($permission, $permCodes);
    }

    public function requireAuth() {
        if (!$this->checkAuth()) {
            if ($this->isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            header('Location: ../index.php');
            exit;
        }
    }

    public function requirePermission($permission) {
        $this->requireAuth();
        if (!$this->hasPermission($permission)) {
            if ($this->isApiRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
            http_response_code(403);
            exit('Access denied.');
        }
    }

    // --- Private Helpers ---
    private function createSession($user) {
        session_regenerate_id(true);
        $_SESSION['nu_user_id']       = $user['usr_id'];
        $_SESSION['nu_username']      = $user['usr_username'];
        $_SESSION['nu_role']          = $user['usr_role'];
        $_SESSION['nu_last_activity'] = time();
        $_SESSION['nu_csrf']          = bin2hex(random_bytes(32));
    }

    private function incrementAttempts($userId) {
        $this->db->query(
            "UPDATE nu_users SET usr_failed_attempts = usr_failed_attempts + 1, usr_last_attempt = NOW() WHERE usr_id = :id",
            [':id' => $userId]
        );
    }

    private function resetAttempts($userId) {
        $this->db->query(
            "UPDATE nu_users SET usr_failed_attempts = 0, usr_last_attempt = NULL WHERE usr_id = :id",
            [':id' => $userId]
        );
    }

    private function logAudit($action, $table, $recordId) {
        if (!$this->config['enableAuditTrail']) return;
        $audit = new NuAudit();
        $audit->log($action, $table, $recordId);
    }

    private function sanitizeUser($user) {
        unset($user['usr_password']);
        unset($user['usr_2fa_secret']);
        return $user;
    }

    private function isApiRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            strpos($uri, '/api/') !== false
        );
    }

    private function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    // --- TOTP 2FA ---
    private function verifyTOTP($secret, $otp) {
        $timeSlice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $calculated = $this->generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($calculated, $otp)) return true;
        }
        return false;
    }

    private function generateTOTP($secret, $timeSlice) {
        $secret = base32_decode($secret);
        $time   = pack('N*', 0) . pack('N*', $timeSlice);
        $hm     = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $code   = ((ord($hm[$offset])   & 0x7F) << 24) |
                  ((ord($hm[$offset+1]) & 0xFF) << 16) |
                  ((ord($hm[$offset+2]) & 0xFF) <<  8) |
                   (ord($hm[$offset+3]) & 0xFF);
        return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
    }

    private function __clone() {}
    public function __wakeup() { throw new RuntimeException('Cannot unserialize NuAuth.'); }
}

if (!class_exists('Auth')) {
    class_alias('NuAuth', 'Auth');
}

function base32_decode($input) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(str_replace('=', '', $input));
    $output = '';
    $buffer = 0;
    $bufferSize = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($map, $input[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bufferSize += 5;
        if ($bufferSize >= 8) {
            $bufferSize -= 8;
            $output .= chr(($buffer >> $bufferSize) & 0xFF);
        }
    }
    return $output;
}
