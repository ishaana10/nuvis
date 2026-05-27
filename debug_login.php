<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Read BEFORE config.php touches anything
$before = [
    'session.cookie_secure'   => ini_get('session.cookie_secure'),
    'session.cookie_samesite' => ini_get('session.cookie_samesite'),
    'session.cookie_path'     => ini_get('session.cookie_path'),
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.save_path'       => ini_get('session.save_path'),
    'session.name'            => ini_get('session.name'),
    'session.use_strict_mode' => ini_get('session.use_strict_mode'),
    'session.use_only_cookies'=> ini_get('session.use_only_cookies'),
];

// Check for .user.ini files
$userIniPaths = [
    __DIR__ . '/.user.ini',
    dirname(__DIR__) . '/.user.ini',
    dirname(dirname(__DIR__)) . '/.user.ini',
];

require_once __DIR__ . '/config.php';

$after = [
    'session.cookie_secure'   => ini_get('session.cookie_secure'),
    'session.cookie_samesite' => ini_get('session.cookie_samesite'),
    'session.cookie_path'     => ini_get('session.cookie_path'),
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.save_path'       => ini_get('session.save_path'),
    'session.name'            => ini_get('session.name'),
];
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Debug v4</title>
<style>body{font-family:monospace;background:#111;color:#eee;padding:20px;font-size:13px;line-height:1.8;}
.ok{color:#4caf50;}.err{color:#f55;}.warn{color:#ff0;}.info{color:#4af;}
table{border-collapse:collapse;width:100%;margin-bottom:20px;}
td,th{border:1px solid #333;padding:6px 12px;text-align:left;}
th{background:#1e2a3a;color:#4af;}
tr:nth-child(even){background:#1a1a2e;}
.diff{background:#3a1a1a;color:#f55;}
button{padding:10px 20px;background:#4f8cff;color:#fff;border:0;cursor:pointer;font-size:14px;border-radius:4px;margin:4px;}
</style></head>
<body>
<h2>&#128269; Debug v4 - PHP Session Config Inspector</h2>

<h3 style="color:#4af">1. PHP Session Settings (BEFORE vs AFTER config.php)</h3>
<table>
<tr><th>Setting</th><th>Before config.php</th><th>After config.php</th><th>Status</th></tr>
<?php foreach ($before as $k => $v):
    $a = $after[$k] ?? $v;
    $changed = $a !== $v;
?>
<tr class="<?= $changed ? '' : '' ?>">
    <td><?= $k ?></td>
    <td><?= htmlspecialchars($v === '' ? '(empty)' : $v) ?></td>
    <td><?= htmlspecialchars($a === '' ? '(empty)' : $a) ?></td>
    <td><?php
        if ($k === 'session.cookie_secure' && $a === '1') echo '<span class="ok">OK (HTTPS)</span>';
        elseif ($k === 'session.cookie_secure' && $a === '0') echo '<span class="warn">OFF - cookie sent on HTTP too</span>';
        elseif ($k === 'session.cookie_samesite' && $a === 'Lax') echo '<span class="ok">OK - Lax allows POST redirect</span>';
        elseif ($k === 'session.cookie_samesite' && $a === 'Strict') echo '<span class="err">STRICT - blocks POST redirect!</span>';
        elseif ($k === 'session.cookie_samesite' && $a === '') echo '<span class="warn">(empty) - server default</span>';
        elseif ($changed) echo '<span class="ok">Changed by config.php</span>';
        else echo '<span class="warn">Unchanged</span>';
    ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3 style="color:#4af">2. .user.ini Files Found</h3>
<table>
<tr><th>Path</th><th>Exists</th><th>Contents</th></tr>
<?php foreach ($userIniPaths as $p): ?>
<tr>
    <td><?= htmlspecialchars($p) ?></td>
    <td><?= file_exists($p) ? '<span class="err">YES</span>' : '<span class="ok">No</span>' ?></td>
    <td><?= file_exists($p) ? '<pre>' . htmlspecialchars(file_get_contents($p)) . '</pre>' : '-' ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3 style="color:#4af">3. Current Session State</h3>
<table>
<tr><th>Key</th><th>Value</th></tr>
<tr><td>session_name()</td><td><?= session_name() ?></td></tr>
<tr><td>session_id()</td><td><?= session_id() ?></td></tr>
<tr><td>session_status()</td><td><?= session_status() ?> (2=active)</td></tr>
<tr><td>$_SESSION</td><td><?= htmlspecialchars(json_encode($_SESSION)) ?></td></tr>
<tr><td>Cookies sent by browser</td><td><?= htmlspecialchars(json_encode($_COOKIE)) ?></td></tr>
</table>

<h3 style="color:#4af">4. Session File on Disk</h3>
<?php
$savePath = session_save_path() ?: '/tmp';
$sessFile = rtrim($savePath, '/') . '/sess_' . session_id();
echo '<table><tr><th>Item</th><th>Value</th></tr>';
echo '<tr><td>Expected file</td><td>' . htmlspecialchars($sessFile) . '</td></tr>';
echo '<tr><td>File exists</td><td>' . (file_exists($sessFile) ? '<span class="ok">YES</span>' : '<span class="err">NO</span>') . '</td></tr>';
if (file_exists($sessFile)) {
    echo '<tr><td>File size</td><td>' . filesize($sessFile) . ' bytes</td></tr>';
    echo '<tr><td>File contents</td><td>' . htmlspecialchars(file_get_contents($sessFile)) . '</td></tr>';
}
echo '</table>';
?>

<h3 style="color:#4af">5. Live Login Test (no redirect)</h3>
<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
$auth = NuAuth::getInstance();

if (isset($_POST['do_login'])) {
    $r = $auth->login('globeadmin', 'password');
    echo '<p class="ok">login() = ' . htmlspecialchars(json_encode($r)) . '</p>';
    echo '<p>SESSION after login: ' . htmlspecialchars(json_encode($_SESSION)) . '</p>';
    session_write_close();
    // Re-read session file from disk
    $sessFile2 = rtrim(session_save_path() ?: '/tmp', '/') . '/sess_' . session_id();
    echo '<p>Session file on disk after write_close: ';
    echo file_exists($sessFile2) ? '<span class="ok">EXISTS - ' . htmlspecialchars(file_get_contents($sessFile2)) . '</span>' : '<span class="err">NOT FOUND - /tmp not writable!</span>';
    echo '</p>';
}

$loggedIn = $auth->checkAuth();
echo '<p>checkAuth() now = ' . ($loggedIn ? '<span class="ok">TRUE - logged in</span>' : '<span class="err">FALSE - not logged in</span>') . '</p>';
?>

<form method="post">
    <button type="submit" name="do_login" value="1">&#9654; Run login() + check session file on disk</button>
</form>

<p style="color:#666;margin-top:30px;">&#9888; DELETE debug_login.php after fixing!</p>
</body></html>
