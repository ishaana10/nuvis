<?php
declare(strict_types=1);
// nuvis - Master Configuration

define('NU_VERSION', '5.0.0');
define('NU_BUILD_DATE', '2026-05-27');
define('NU_ROOT', __DIR__);

// ─── Database ─────────────────────────────────────────────────────────────────
if (!isset($nuConfig)) $nuConfig = [];
$nuConfig['dbHost']     = getenv('NU_DB_HOST')    ?: 'localhost';
$nuConfig['dbName']     = getenv('NU_DB_NAME')    ?: 'your_db_name';
$nuConfig['dbUser']     = getenv('NU_DB_USER')    ?: 'your_db_user';
$nuConfig['dbPassword'] = getenv('NU_DB_PASS')    ?: 'your_db_password';
$nuConfig['dbCharset']  = 'utf8mb4';
$nuConfig['dbPort']     = (int)(getenv('NU_DB_PORT') ?: 3306);

// ─── Security ─────────────────────────────────────────────────────────────────
$nuConfig['sessionTimeout']        = 3600;
$nuConfig['maxLoginAttempts']      = 5;
$nuConfig['lockoutDuration']       = 900;
$nuConfig['csrfTokenName']         = 'nu_csrf';
$nuConfig['sessionCookieSecure']   = true;
$nuConfig['sessionCookieHttpOnly'] = true;
$nuConfig['sessionCookieSameSite'] = 'Lax';
$nuConfig['passwordMinLength']     = 10;

// ─── Features ─────────────────────────────────────────────────────────────────
$nuConfig['enable2FA']         = false;
$nuConfig['enableAPI']         = true;
$nuConfig['enableAuditTrail']  = true;
$nuConfig['enableFileUploads'] = true;
$nuConfig['maxUploadSize']     = 10485760;
$nuConfig['allowedFileTypes']  = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','csv'];

// ─── Paths ────────────────────────────────────────────────────────────────────
$nuConfig['baseUrl']    = rtrim(getenv('NU_BASE_URL') ?: '/nbv5u/m/', '/') . '/';
$nuConfig['uploadPath'] = NU_ROOT . '/uploads/';
$nuConfig['logPath']    = NU_ROOT . '/logs/';

// ─── Display ──────────────────────────────────────────────────────────────────
$nuConfig['siteTitle'] = 'nuvis';
$nuConfig['theme']     = 'auto';

// ─── API ──────────────────────────────────────────────────────────────────────
$nuConfig['apiRateLimit'] = 1000;
$nuConfig['apiKeyHeader'] = 'X-API-Key';

// ─── PHP Error Handling ───────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// ─── Load Local Overrides BEFORE session_start ────────────────────────────────
// config.local.php can override dbHost, dbName, dbPassword, baseUrl,
// sessionCookieSecure etc. It MUST be loaded before the session block below.
$_localOverride = NU_ROOT . '/config.local.php';
if (is_file($_localOverride)) {
    require $_localOverride;
}
unset($_localOverride);

// ─── Load Environment-Specific Config Overrides ───────────────────────────────
// Automatically loads config.production.php, config.staging.php etc. depending on NU_ENV
$_env = getenv('NU_ENV') ?: 'development';
$_envFile = NU_ROOT . '/config.' . $_env . '.php';
if (is_file($_envFile)) {
    require $_envFile;
}
unset($_env, $_envFile);

// ─── Session Hardening + Start ────────────────────────────────────────────────
// DO NOT call session_save_path() with a custom directory on shared hosts.
// cPanel/A2Hosting may not grant Apache write access to the app /sessions/ dir,
// causing session_start() to silently fail and every request gets an empty session.
// PHP's system default (/tmp or the host-configured path) is always writable.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Lax');
    ini_set('session.cookie_secure',    $nuConfig['sessionCookieSecure'] ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime',   (string)$nuConfig['sessionTimeout']);
    // Derive cookie_path from baseUrl so it works on any install path
    $_cookiePath = rtrim(parse_url($nuConfig['baseUrl'], PHP_URL_PATH) ?: '/nbv5u/m/', '/') . '/';
    ini_set('session.cookie_path', $_cookiePath);
    unset($_cookiePath);
    session_name('nu5sess');
    session_start();
}
