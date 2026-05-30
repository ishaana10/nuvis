<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db     = NuDatabase::getInstance();
$events = $db->fetchAll(
    "SELECT * FROM nu_calendar_events WHERE event_user_id = :user OR event_user_id IS NULL ORDER BY event_start",
    [':user' => $_SESSION['nu_user_id']]
);
?>

<div class="nu-calendar">
    <div class="nu-card" style="margin-bottom:24px;">
        <div class="nu-card-header">
            <h3 class="nu-card-title">Calendar &amp; Scheduler</h3>
            <button class="nu-btn nu-btn-primary" onclick="openEventModal()">+ New Event</button>
        </div>
        <div id="calendarView" style="background:var(--bg-elevated);border-radius:var(--radius-lg);padding:20px;min-height:400px;">
            <div class="nu-calendar-grid" style="display:grid;grid-template-columns:repeat(7, 1fr);gap:8px;">
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Sun</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Mon</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Tue</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Wed</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Thu</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Fri</div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:8px;">Sat</div>
                <?php
                $today     = new DateTime();
                $firstDay  = new DateTime($today->format('Y-m-01'));
                $daysInMonth = (int)$today->format('t');
                $startOffset = (int)$firstDay->format('w');
                for ($i = 0; $i < $startOffset; $i++) {
                    echo '<div style="min-height:80px;background:var(--bg-secondary);border-radius:var(--radius-sm);"></div>';
                }
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $isToday   = $day == (int)$today->format('j');
                    $cellStyle = $isToday ? 'background:var(--accent);color:#fff;' : 'background:var(--bg-secondary);';
                    echo '<div style="min-hei