<?php
// Standalone Calendar Test / Verification Page
require_once __DIR__ . '/config.php';
$_SESSION['nu_user_id'] = '1';
$_SESSION['nu_last_activity'] = time();
$_SESSION['nu_role'] = 'admin';

// Render Page Shell
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>nuvis - Interactive Calendar & Scheduler</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/nubuilder-next.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            background: var(--bg-primary);
            padding: 24px;
            margin: 0;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div style="max-width: 1400px; margin: 0 auto;">
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Calendar &amp; Scheduler Module
        </h2>
        <?php
        // Include the actual calendar module code
        include 'modules/calendar/calendar.php';
        ?>
    </div>
</body>
</html>
