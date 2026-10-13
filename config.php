<?php
declare(strict_types=1);
// nuvis - Master Configuration

define('NU_VERSION', '5.0.0');
define('NU_BUILD_DATE', '2026-05-27');
if (!defined('NU_ROOT')) define('NU_ROOT', __DIR__);

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

// ─── HTTP Security Headers ───────────────────────────────────────────────────
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://code.jquery.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob: https:; connect-src 'self' https:; frame-ancestors 'self';");
}

// ─── Redis ────────────────────────────────────────────────────────────────────
$nuConfig['redis'] = [
    'enabled'     => false, // Set true in config.local.php or env when active
    'scheme'      => 'unix',
    'path'        => '/home/ictfjcom/.redis/redis.sock',
    'host'        => '127.0.0.1',
    'port'        => 6379,
    'password'    => null,
    'database'    => 0,
    'prefix'      => 'nuvis:',
    'timeout'     => 2.5,
    'default_ttl' => 300,
];

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
$nuConfig['userHeaderFormat'] = '{name} | {location}';

// ─── API ──────────────────────────────────────────────────────────────────────
$nuConfig['apiRateLimit'] = 1000;
$nuConfig['apiKeyHeader'] = 'X-API-Key';
$nuConfig['enableWebhookDemo'] = true;
$nuConfig['enableGitUpdater'] = true;
$nuConfig['allowApiKeyQueryParam'] = false;

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
    ini_set('session.cookie_secure',    !empty($nuConfig['sessionCookieSecure']) ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime',   (string)($nuConfig['sessionTimeout'] ?? 3600));
    // Derive cookie_path from baseUrl so it works on any install path
    $_cookiePath = rtrim(parse_url($nuConfig['baseUrl'] ?? '/nbv5u/m/', PHP_URL_PATH) ?: '/nbv5u/m/', '/') . '/';
    ini_set('session.cookie_path', $_cookiePath);
    unset($_cookiePath);
    session_name('nu5sess');

    // Register Redis session handler if Redis is enabled and extension exists
    if (!empty($nuConfig['redis']['enabled']) && extension_loaded('redis')) {
        try {
            require_once NU_ROOT . '/core/Cache.php';
            require_once NU_ROOT . '/core/RedisSessionHandler.php';
            $cache = NuCache::getInstance();
            if ($cache->isEnabled() && $cache->client()) {
                $handler = new NuRedisSessionHandler(
                    $cache->client(),
                    ($nuConfig['redis']['prefix'] ?? 'nuvis:') . 'sess:',
                    (int)($nuConfig['sessionTimeout'] ?? 1800)
                );
                session_set_save_handler($handler, true);
            }
        } catch (\Throwable $e) {
            error_log('[SessionBootstrap] Redis sessions fallback: ' . $e->getMessage());
        }
    }

    session_start();
}

if (!function_exists('h')) {
    function h($string, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
        return htmlspecialchars((string)$string, $flags, $encoding);
    }
}
