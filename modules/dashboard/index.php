<?php
declare(strict_types=1);
/**
 * modules/dashboard/index.php
 * ENTRY POINT — bootstraps once, then routes by role.
 *
 * globeadmin / admin  → dashboard.php
 * everyone else       → dashboard_user.php
 */
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';
// $auth, session, DB all ready after this single bootstrap call.

$_dRole = strtolower((string)($_SESSION['nu_role'] ?? ''));

if ($_dRole === 'globeadmin' || $_dRole === 'admin') {
    require __DIR__ . '/dashboard.php';
} else {
    require __DIR__ . '/dashboard_user.php';
}
