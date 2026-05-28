<?php
/**
 * session_probe.php  —  DELETE after debugging
 * Bypasses ALL app code. Tests raw PHP session read/write in isolation.
 * Visit: https://ictfjcom.com/nbv5u/m/session_probe.php
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$action = $_GET['a'] ?? 'status';

// ── Apply SAME settings as config.php ──────────────────────────────────
ini_set('session.use_strict_mode',  '1');
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  'Lax');
ini_set('session.cookie_secure',    '1');   // site is HTTPS
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime',   '3600');
ini_set('session.cookie_path',      '/nbv5u/m/');
session_name('nu5sess');
session_start();

header('Content-Type: text/plain; charset=utf-8');

$sid    = session_id();
$spath  = session_save_path() ?: ini_get('session.save_path') ?: '/tmp';
$sfile  = rtrim($spath, '/') . '/sess_' . $sid;
$cookie = $_COOKIE['nu5sess'] ?? '(none)';

echo "=== SESSION PROBE ===", PHP_EOL;
echo "PHP version  : ", PHP_VERSION, PHP_EOL;
echo "SAPI         : ", PHP_SAPI, PHP_EOL;
echo "session_id   : ", $sid, PHP_EOL;
echo "save_path    : ", $spath, PHP_EOL;
echo "session_file : ", $sfile, PHP_EOL;
echo "file_exists  : ", (file_exists($sfile) ? 'YES (' . filesize($sfile) . ' bytes)' : 'NO'), PHP_EOL;
echo "cookie sent  : ", $cookie, PHP_EOL;
echo "cookie_path  : ", ini_get('session.cookie_path'), PHP_EOL;
echo "cookie_secure: ", ini_get('session.cookie_secure'), PHP_EOL;
echo "_SESSION now : ", json_encode($_SESSION), PHP_EOL;
echo PHP_EOL;

if ($action === 'write') {
    $_SESSION['probe_test'] = 'written_at_' . time();
    session_write_close();
    $size = file_exists($sfile) ? filesize($sfile) : -1;
    echo "WRITE: set probe_test, called session_write_close()", PHP_EOL;
    echo "file size after write : ", $size, " bytes", PHP_EOL;
    echo "If size > 0: session write works.  Refresh ?a=status to read back.", PHP_EOL;
} elseif ($action === 'read') {
    echo "READ: probe_test = ", ($_SESSION['probe_test'] ?? '(missing - write first)'), PHP_EOL;
    echo "If missing after write: session cookie not sent, or save_path mismatch.", PHP_EOL;
} elseif ($action === 'clear') {
    session_destroy();
    echo "Session destroyed.", PHP_EOL;
} else {
    echo "ACTIONS:", PHP_EOL;
    echo "  ?a=write  — write to session + flush", PHP_EOL;
    echo "  ?a=read   — read back (open new tab, proves cookie round-trip)", PHP_EOL;
    echo "  ?a=clear  — destroy session", PHP_EOL;
    echo PHP_EOL;
    echo "WRITABLE PATHS TEST:", PHP_EOL;
    $paths = [
        '/tmp',
        sys_get_temp_dir(),
        __DIR__ . '/sessions',
    ];
    foreach ($paths as $p) {
        $w = is_writable($p) ? 'WRITABLE' : 'NOT writable';
        echo "  $p : $w", PHP_EOL;
    }
    echo PHP_EOL;
    echo "COOKIES from browser:", PHP_EOL;
    foreach ($_COOKIE as $k => $v) {
        echo "  $k = $v", PHP_EOL;
    }
}
