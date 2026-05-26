<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bootError = '';
$loginError = '';
$logoutDone = false;
$isLoggedIn = false;
$currentUser = null;
$auth = null;

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function asset_exists(string $relativePath): bool {
    $full = __DIR__ . '/' . ltrim($relativePath, '/');
    return is_file($full);
}

try {
    require_once __DIR__ . '/config.php';

    if (asset_exists('core/Database.php')) {
        require_once __DIR__ . '/core/Database.php';
    }

    if (asset_exists('core/Auth.php')) {
        require_once __DIR__ . '/core/Auth.php';
    }

    if (class_exists('Auth')) {
        if (method_exists('Auth', 'getInstance')) {
            $auth = Auth::getInstance();
        } else {
            $auth = new Auth();
        }
    }
} catch (Throwable $e) {
    $bootError = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    try {
        if ($auth && method_exists($auth, 'logout')) {
            $auth->logout();
        } else {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $loginError = 'Logout failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $loginError = 'Username and password are required.';
    } else {
        try {
            if ($auth && method_exists($auth, 'login')) {
                $result = $auth->login($username, $password);

                if (is_array($result)) {
                    if (!empty($result['success'])) {
                        header('Location: index.php');
                        exit;
                    }
                    $loginError = (string)($result['message'] ?? 'Login failed');
                } elseif ($result === true) {
                    header('Location: index.php');
                    exit;
                } else {
                    $loginError = 'Login failed';
                }
            } else {
                if ($username === 'globeadmin' && $password === 'password') {
                    $_SESSION['user'] = [
                        'username' => $username,
                        'name' => 'Administrator',
                        'role' => 'admin'
                    ];
                    header('Location: index.php');
                    exit;
                } else {
                    $loginError = 'Invalid username or password.';
                }
            }
        } catch (Throwable $e) {
            $loginError = 'Login error: ' . $e->getMessage();
        }
    }
}

try {
    if ($auth && method_exists($auth, 'checkAuth')) {
        $isLoggedIn = (bool)$auth->checkAuth();
    } elseif ($auth && method_exists($auth, 'isLoggedIn')) {
        $isLoggedIn = (bool)$auth->isLoggedIn();
    } else {
        $isLoggedIn = !empty($_SESSION['user']);
    }

    if ($auth && method_exists($auth, 'getCurrentUser')) {
        $currentUser = $auth->getCurrentUser();
    } elseif (!empty($_SESSION['user'])) {
        $currentUser = $_SESSION['user'];
    }
} catch (Throwable $e) {
    $bootError = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    $isLoggedIn = false;
    $currentUser = null;
}

$userDisplay = 'User';
if (is_array($currentUser)) {
    $userDisplay = $currentUser['name'] ?? $currentUser['username'] ?? 'User';
} elseif (is_object($currentUser)) {
    $userDisplay = $currentUser->name ?? $currentUser->username ?? 'User';
}

$mainJsPath = null;
$candidateJs = [
    'nubuilder-next.js',
    'assets/js/nubuilder-next.js',
    'js/nubuilder-next.js'
];
foreach ($candidateJs as $candidate) {
    if (asset_exists($candidate)) {
        $mainJsPath = $candidate;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NuBuilder 5</title>
    <style>
        :root {
            --bg: #0b1020;
            --panel: #11182d;
            --panel-2: #18223d;
            --text: #e8edf7;
            --muted: #9eb0d1;
            --primary: #4f8cff;
            --primary-2: #7aa7ff;
            --border: #263252;
            --danger: #e05d5d;
            --success: #2fa36b;
            --shadow: 0 20px 50px rgba(0,0,0,.35);
            --radius: 16px;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Inter, Arial, sans-serif; background: linear-gradient(180deg, #0a0f1d 0%, #10182d 100%); color: var(--text); }
        a { color: inherit; text-decoration: none; }

        .page {
            min-height: 100vh;
        }

        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            background: rgba(17, 24, 45, 0.96);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 28px;
        }

        .brand {
            margin-bottom: 20px;
        }

        .brand h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .brand p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            line-height: 1.45;
        }

        .alert-danger {
            background: rgba(224, 93, 93, 0.12);
            border: 1px solid rgba(224, 93, 93, 0.35);
            color: #ffd0d0;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.10);
            border: 1px solid rgba(255, 193, 7, 0.30);
            color: #ffe9a8;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: var(--muted);
        }

        .field input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #0d1426;
            color: var(--text);
            outline: none;
        }

        .field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 140, 255, 0.14);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 10px;
            padding: 12px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
            color: white;
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .login-note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .nu-app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 260px 1fr;
        }

        .sidebar {
            background: rgba(10, 15, 29, 0.88);
            border-right: 1px solid var(--border);
            padding: 20px;
        }

        .sidebar h2 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .sidebar p {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav a {
            display: block;
            padding: 12px 14px;
            border-radius: 10px;
            color: var(--text);
            background: transparent;
            border: 1px solid transparent;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(79, 140, 255, 0.12);
            border-color: rgba(79, 140, 255, 0.25);
        }

        .main {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: rgba(17, 24, 45, 0.65);
            backdrop-filter: blur(8px);
        }

        .topbar h1 {
            margin: 0;
            font-size: 22px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-chip {
            padding: 10px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 14px;
        }

        .content {
            padding: 24px;
        }

        .welcome {
            background: rgba(17, 24, 45, 0.95);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .welcome h3 {
            margin-top: 0;
            font-size: 24px;
        }

        .welcome p {
            color: var(--muted);
            line-height: 1.6;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .quick-card {
            display: block;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
        }

        .quick-card strong {
            display: block;
            margin-bottom: 6px;
        }

        .quick-card span {
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .nu-app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <?php if (!$isLoggedIn): ?>
        <div class="login-wrap">
            <div class="login-card">
                <div class="brand">
                    <h1>NuBuilder 5</h1>
                    <p>Modern low-code platform login.</p>
                </div>

                <?php if ($bootError !== ''): ?>
                    <div class="alert alert-warning">
                        Bootstrap warning: <?= h($bootError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-danger">
                        <?= h($loginError) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php" autocomplete="off">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" value="" autocomplete="username">
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" value="" autocomplete="current-password">
                    </div>

                    <button class="btn btn-primary" type="submit" name="login_submit" value="1">Sign in</button>
                </form>

                <div class="login-note">
                    Default: globeadmin / password
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="nu-app">
            <aside class="sidebar">
                <h2>NuBuilder 5</h2>
                <p>Low-code app platform</p>

                <nav class="nav">
                    <a href="#dashboard" class="nu-nav-item active" data-module="dashboard">Dashboard</a>
                    <a href="#forms" class="nu-nav-item" data-module="forms">Forms</a>
                    <a href="#reports" class="nu-nav-item" data-module="reports">Reports</a>
                    <a href="#queries" class="nu-nav-item" data-module="queries">Queries</a>
                    <a href="#workflows" class="nu-nav-item" data-module="workflows">Workflows</a>
                    <a href="#settings" class="nu-nav-item" data-module="settings">Settings</a>
                </nav>
            </aside>

            <main class="main">
                <div class="topbar">
                    <h1 id="pageTitle">Dashboard</h1>

                    <div class="topbar-right">
                        <div class="user-chip"><?= h($userDisplay) ?></div>
                        <button id="themeToggle" class="btn btn-ghost" type="button">Theme</button>
                        <form method="post" action="index.php" style="margin:0;">
                            <button class="btn btn-ghost" type="submit" name="logout" value="1">Logout</button>
                        </form>
                    </div>
                </div>

                <div class="content">
                    <div id="contentArea">
                        <div class="welcome">
                            <h3>Welcome</h3>
                            <p>Your application shell is now rendering safely. If a module fails after this, the issue is in the module file or JavaScript, not the login page bootstrap.</p>

                            <div class="quick-links">
                                <a class="quick-card" href="#forms">
                                    <strong>Forms</strong>
                                    <span>Build and preview forms.</span>
                                </a>
                                <a class="quick-card" href="#reports">
                                    <strong>Reports</strong>
                                    <span>Run SQL-based reports.</span>
                                </a>
                                <a class="quick-card" href="#settings">
                                    <strong>Settings</strong>
                                    <span>Adjust platform behavior.</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    <?php endif; ?>
</div>

<?php if ($isLoggedIn && $mainJsPath): ?>
<script src="<?= h($mainJsPath) ?>"></script>
<?php endif; ?>
</body>
</html>