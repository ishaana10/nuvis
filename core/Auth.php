<?php
// nuBuilder Next - Authentication & Session Management

require_once 'Audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class NuAuth {
    private $db;
    private $config;

    public function __construct() {
        global $nuConfig;
        $this->config = $nuConfig;
        $this->db = NuDatabase::getInstance();
    }

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

    public function logout() {
        if (isset($_SESSION['nu_user_id'])) {
            $this->logAudit('logout', 'nu_users', $_SESSION['nu_user_id']);
        }
        session_destroy();
        return true;
    }

    public function checkAuth() {
        if (empty($_SESSION['nu_user_id'])) return false;
        if (time() - $_SESSION['nu_last_activity'] > $this->config['sessionTimeout']) {
            $this->logout();
            return false;
        }
        $_SESSION['nu_last_activity'] = time();
        return true;
    }

    public function getCurrentUser() {
        if (!$this->checkAuth()) return null;
        return $this->db->fetchOne(
            "SELECT * FROM nu_users WHERE usr_id = :id",
            [':id' => $_SESSION['nu_user_id']]
        );
    }

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
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $code = ((ord($hm[$offset]) & 0x7F) << 24) |
                ((ord($hm[$offset + 1]) & 0xFF) << 16) |
                ((ord($hm[$offset + 2]) & 0xFF) << 8) |
                (ord($hm[$offset + 3]) & 0xFF);
        return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
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
?>
