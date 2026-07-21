<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db     = NuDatabase::getInstance();

// ── AUTO-MIGRATE: ensure all recurrence columns exist ───────────────────────
$autoMigrations = [
    "ALTER TABLE nu_calendar_events ADD COLUMN event_rrule TEXT DEFAULT NULL",
    "ALTER TABLE nu_calendar_events ADD COLUMN event_exdate TEXT DEFAULT NULL",
    "ALTER TABLE nu_calendar_events ADD COLUMN event_recurrence_id INT DEFAULT NULL",
    "ALTER TABLE nu_calendar_events ADD COLUMN event_original_start DATETIME DEFAULT NULL",
    "ALTER TABLE nu_calendar_events ADD COLUMN event_category VARCHAR(50) DEFAULT 'personal'",
    "ALTER TABLE nu_calendar_events MODIFY COLUMN event_type VARCHAR(32) DEFAULT 'meeting'",
    "ALTER TABLE nu_calendar_events ADD INDEX idx_recurrence (event_recurrence_id)"
];
foreach ($autoMigrations as $sql) {
    try { $db->query($sql); } catch (Throwable $ignored) {}
}
?>

<!-- ── Include rrule.js for standard RFC 5545 Recurrence expansion ── -->
<script src="https://cdn.jsdelivr.net/npm/rrule@2.8.1/dist/es5/rrule.min.js"></script>

<style>
/* ── CALENDAR DASHBOARD DESIGN SYSTEM ── */
.nu-calendar-container {
    display: flex;
    gap: 24px;
    height: calc(100vh - 120px);
    min-height: 600px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
}

/* Sidebar Styling */
.nu-cal-sidebar {
    width: 280px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 20px;
    background: var(--bg-elevated, #111a2e);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 20px;
    overflow-y: auto;
}

/* Mini Calendar */
.nu-mini-cal {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 16px;
}
.nu-mini-cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 10px;
}
.nu-mini-cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    text-align: center;
    font-size: 11px;
}
.nu-mini-cal-day-label {
    font-weight: 600;
    color: var(--text-tertiary);
    padding-bottom: 4px;
}
.nu-mini-cal-day {
    padding: 5px 0;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.2s;
}
.nu-mini-cal-day:hover {
    background: var(--bg-hover);
}
.nu-mini-cal-day.active {
    background: var(--accent, #0ea5e9);
    color: #fff;
    font-weight: 600;
}
.nu-mini-cal-day.muted {
    color: var(--text-tertiary);
    opacity: 0.5;
}

/* Main Calendar Area */
.nu-cal-main {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg-elevated, #111a2e);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

/* Header/Top Bar */
.nu-cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-subtle, #1e293b);
    flex-wrap: wrap;
    gap: 12px;
}
.nu-cal-nav-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.nu-cal-title {
    font-size: 18px;
    font-weight: 700;
    min-width: 150px;
}
.nu-cal-view-selector {
    display: flex;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: 2px;
    border: 1px solid var(--border-color);
}
.nu-cal-view-btn {
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.nu-cal-view-btn.active {
    background: var(--accent);
    color: #fff;
    box-shadow: var(--shadow-sm);
}

/* Search Bar */
.nu-cal-search-wrap {
    position: relative;
    width: 200px;
}
.nu-cal-search-input {
    width: 100%;
    padding: 6px 12px 6px 32px;
    font-size: 13px;
}
.nu-cal-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
}

/* Dynamic Grid Container */
.nu-cal-body {
    flex-grow: 1;
    overflow-y: auto;
    position: relative;
    background: var(--bg-secondary);
}

/* MONTH VIEW GRID */
.nu-month-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(100px, 1fr);
    gap: 1px;
    background: var(--border-color);
    height: 100%;
}
.nu-month-cell {
    background: var(--bg-elevated);
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
    min-height: 100px;
    transition: background 0.2s;
}
.nu-month-cell:hover {
    background: var(--bg-subtle);
}
.nu-month-day-num {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary);
    align-self: flex-start;
    margin-bottom: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.nu-month-cell.today .nu-month-day-num {
    background: var(--accent);
    color: #fff;
}
.nu-month-cell.other-month {
    opacity: 0.4;
}

/* WEEK / DAY VIEW GRID */
.nu-time-grid {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 600px;
}
.nu-time-grid-header {
    display: grid;
    grid-template-columns: 60px repeat(var(--cols, 7), 1fr);
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-subtle);
    position: sticky;
    top: 0;
    z-index: 10;
}
.nu-time-grid-header-cell {
    padding: 10px 4px;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    border-left: 1px solid var(--border-color);
    color: var(--text-secondary);
}
.nu-time-grid-header-cell.today {
    color: var(--accent);
    background: rgba(14, 165, 233, 0.05);
}
.nu-time-grid-content {
    display: flex;
    flex-direction: column;
    position: relative;
}
.nu-time-row {
    display: grid;
    grid-template-columns: 60px repeat(var(--cols, 7), 1fr);
    height: 60px;
    border-bottom: 1px solid var(--border-color);
}
.nu-time-label {
    font-size: 11px;
    color: var(--text-tertiary);
    text-align: right;
    padding-right: 8px;
    line-height: 20px;
    border-right: 1px solid var(--border-color);
    background: var(--bg-subtle);
}
.nu-time-slot-cell {
    border-left: 1px solid var(--border-color);
    position: relative;
    background: var(--bg-elevated);
    transition: background 0.15s;
}
.nu-time-slot-cell:hover {
    background: var(--bg-subtle);
}

/* Events Cards */
.nu-event-pill {
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: transform 0.15s, box-shadow 0.15s;
    user-select: none;
    position: relative;
}
.nu-event-pill:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}
.nu-event-pill.past {
    opacity: 0.65;
}
.nu-event-pill-time {
    font-weight: 700;
    font-size: 9px;
    opacity: 0.85;
}

/* Timeline View Cards position */
.nu-event-absolute {
    position: absolute;
    left: 4px;
    right: 4px;
    z-index: 5;
    white-space: normal;
    overflow: hidden;
    border-left: 3px solid rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}
.nu-event-resize-handle {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 6px;
    cursor: ns-resize;
    background: rgba(0,0,0,0.1);
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
}

/* Agenda List View */
.nu-agenda-list {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 800px;
    margin: 0 auto;
}
.nu-agenda-day-group {
    background: var(--bg-elevated);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.nu-agenda-day-header {
    background: var(--bg-subtle);
    padding: 12px 16px;
    font-weight: 600;
    font-size: 14px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
}
.nu-agenda-item {
    display: flex;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    align-items: center;
    gap: 16px;
    transition: background 0.2s;
    cursor: pointer;
}
.nu-agenda-item:last-child {
    border-bottom: none;
}
.nu-agenda-item:hover {
    background: var(--bg-subtle);
}
.nu-agenda-item-time {
    font-weight: 600;
    font-size: 12px;
    color: var(--text-secondary);
    min-width: 120px;
}
.nu-agenda-item-details {
    flex-grow: 1;
}
.nu-agenda-item-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 2px;
}
.nu-agenda-item-desc {
    font-size: 12px;
    color: var(--text-tertiary);
}

/* Modals & Dialogs */
.nu-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
    animation: fadeIn 0.15s ease;
}
.nu-modal {
    background: var(--bg-elevated, #111a2e);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 580px;
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}
.nu-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}
.nu-modal-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}
.nu-modal-close {
    background: transparent;
    border: none;
    color: var(--text-tertiary);
    cursor: pointer;
}
.nu-modal-close:hover {
    color: var(--text-primary);
}
.nu-modal-tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-subtle);
    padding: 0 16px;
}
.nu-modal-tab-btn {
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}
.nu-modal-tab-btn.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.nu-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex-grow: 1;
}
.nu-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
    background: var(--bg-subtle);
}

/* Category Badges & Elements */
.nu-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
}
.nu-cat-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.nu-cat-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    cursor: pointer;
}

/* Recurrence UI Builder */
.nu-recurrence-summary {
    background: rgba(14, 165, 233, 0.08);
    border-left: 3px solid var(--accent);
    padding: 12px;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 12px;
}
.nu-recurrence-grid-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-top: 8px;
}
.nu-recurrence-day-btn {
    padding: 6px 0;
    text-align: center;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    font-size: 11px;
    cursor: pointer;
    font-weight: 500;
}
.nu-recurrence-day-btn.active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Key Shortcuts Section */
.nu-kbd {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 1px 5px;
    font-size: 10px;
    font-family: monospace;
    color: var(--text-secondary);
    box-shadow: 0 1px 0 rgba(0,0,0,0.2);
}
</style>

<div class="nu-calendar-container">
    <!-- LEFT SIDEBAR -->
    <div class="nu-cal-sidebar">
        <button class="nu-btn nu-btn-primary nu-btn-block" onclick="NuCalendarApp.openNewEvent()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Event
        </button>

        <!-- Mini Calendar Navigation -->
        <div class="nu-mini-cal">
            <div class="nu-mini-cal-header">
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="NuCalendarApp.navigateMini(-1)" style="padding:4px 8px;">&lt;</button>
                <span id="miniCalTitle">Month Year</span>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="NuCalendarApp.navigateMini(1)" style="padding:4px 8px;">&gt;</button>
            </div>
            <div class="nu-mini-cal-grid" id="miniCalGrid"></div>
        </div>

        <!-- My Calendars Toggles -->
        <div>
            <h4 style="font-size:12px;text-transform:uppercase;color:var(--text-tertiary);margin:0 0 8px;">My Calendars</h4>
            <div class="nu-cat-toggle" onclick="NuCalendarApp.toggleCategoryFilter('personal')">
                <span class="nu-cat-badge"><span class="nu-cat-dot" style="background:#0ea5e9;"></span>Personal</span>
                <input type="checkbox" id="cat_personal" checked onclick="event.stopPropagation(); NuCalendarApp.toggleCategoryFilter('personal')">
            </div>
            <div class="nu-cat-toggle" onclick="NuCalendarApp.toggleCategoryFilter('work')">
                <span class="nu-cat-badge"><span class="nu-cat-dot" style="background:#10b981;"></span>Work</span>
                <input type="checkbox" id="cat_work" checked onclick="event.stopPropagation(); NuCalendarApp.toggleCategoryFilter('work')">
            </div>
            <div class="nu-cat-toggle" onclick="NuCalendarApp.toggleCategoryFilter('shared')">
                <span class="nu-cat-badge"><span class="nu-cat-dot" style="background:#8b5cf6;"></span>Shared</span>
                <input type="checkbox" id="cat_shared" checked onclick="event.stopPropagation(); NuCalendarApp.toggleCategoryFilter('shared')">
            </div>
        </div>

        <!-- Quick Jumps -->
        <div>
            <h4 style="font-size:12px;text-transform:uppercase;color:var(--text-tertiary);margin:0 0 8px;">Quick Jumps</h4>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <button class="nu-btn nu-btn-ghost nu-btn-sm nu-btn-block" style="text-align:left;justify-content:flex-start;" onclick="NuCalendarApp.jumpToToday()">Today</button>
                <button class="nu-btn nu-btn-ghost nu-btn-sm nu-btn-block" style="text-align:left;justify-content:flex-start;" onclick="NuCalendarApp.jumpNext7Days()">Next 7 Days</button>
            </div>
        </div>

        <!-- Shortcuts Guide -->
        <div style="font-size:11px;color:var(--text-tertiary);margin-top:auto;border-top:1px solid var(--border-color);padding-top:12px;">
            <div style="font-weight:600;margin-bottom:6px;">Keyboard Shortcuts</div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Prev View</span><kbd class="nu-kbd">P</kbd></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Next View</span><kbd class="nu-kbd">N</kbd></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Today</span><kbd class="nu-kbd">T</kbd></div>
            <div style="display:flex;justify-content:space-between;"><span>New Event</span><kbd class="nu-kbd">C</kbd></div>
        </div>
    </div>

    <!-- MAIN CALENDAR CONTAINER -->
    <div class="nu-cal-main">
        <!-- TOP BAR / ACTION HEADER -->
        <div class="nu-cal-header">
            <div class="nu-cal-nav-group">
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="NuCalendarApp.navigate(-1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="NuCalendarApp.jumpToToday()">Today</button>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="NuCalendarApp.navigate(1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <div class="nu-cal-title" id="calendarTitle">August 2026</div>
            </div>

            <!-- View Swapping -->
            <div class="nu-cal-view-selector">
                <button class="nu-cal-view-btn active" id="view_month" onclick="NuCalendarApp.switchView('month')">Month</button>
                <button class="nu-cal-view-btn" id="view_week" onclick="NuCalendarApp.switchView('week')">Week</button>
                <button class="nu-cal-view-btn" id="view_workweek" onclick="NuCalendarApp.switchView('workweek')">Work Week</button>
                <button class="nu-cal-view-btn" id="view_day" onclick="NuCalendarApp.switchView('day')">Day</button>
                <button class="nu-cal-view-btn" id="view_agenda" onclick="NuCalendarApp.switchView('agenda')">Agenda</button>
            </div>

            <!-- Live Search Bar -->
            <div class="nu-cal-search-wrap">
                <svg class="nu-cal-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="nu-input nu-cal-search-input" id="calSearchInput" placeholder="Search events..." oninput="NuCalendarApp.searchEvents(this.value)">
            </div>
        </div>

        <!-- MAIN GRID / DYNAMIC RENDER LAYER -->
        <div class="nu-cal-body" id="calendarGridBody"></div>
    </div>
</div>

<!-- EVENT CREATION / EDITING MODAL (WITH TABS & RECURRENCE BUILDER) -->
<div class="nu-modal-overlay" id="calendarEventModal">
    <div class="nu-modal">
        <div class="nu-modal-header">
            <h3 class="nu-modal-title" id="calendarModalTitle">Event</h3>
            <button class="nu-modal-close" onclick="NuCalendarApp.closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Dual Modal Navigation Tabs -->
        <div class="nu-modal-tabs">
            <button class="nu-modal-tab-btn active" id="tab_details" onclick="NuCalendarApp.switchModalTab('details')">Details</button>
            <button class="nu-modal-tab-btn" id="tab_recurrence" onclick="NuCalendarApp.switchModalTab('recurrence')">Recurrence</button>
            <button class="nu-modal-tab-btn" id="tab_attendees" onclick="NuCalendarApp.switchModalTab('attendees')">Attendees & Reminders</button>
        </div>

        <div class="nu-modal-body">
            <!-- TAB 1: DETAILS -->
            <div id="modal_pane_details">
                <input type="hidden" id="event_id" value="">
                <input type="hidden" id="event_recurrence_id" value="">
                <input type="hidden" id="event_original_start" value="">

                <div class="nu-field">
                    <label>Event Title *</label>
                    <input type="text" class="nu-input" id="event_title" placeholder="Project Sync Meeting">
                </div>

                <div class="nu-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="nu-field">
                        <label>Start Date & Time *</label>
                        <input type="datetime-local" class="nu-input" id="event_start" onchange="NuCalendarApp.onStartChange()">
                    </div>
                    <div class="nu-field">
                        <label>End Date & Time</label>
                        <input type="datetime-local" class="nu-input" id="event_end">
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <input type="checkbox" id="event_all_day" onchange="NuCalendarApp.toggleAllDay()">
                    <label for="event_all_day" style="font-size:13px;font-weight:500;margin:0;cursor:pointer;">All-Day Event</label>
                </div>

                <div class="nu-field">
                    <label>Description</label>
                    <textarea class="nu-input" id="event_description" rows="3" placeholder="Discuss sprint plans and priorities..."></textarea>
                </div>

                <div class="nu-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="nu-field">
                        <label>Category</label>
                        <select class="nu-input" id="event_category">
                            <option value="personal">Personal</option>
                            <option value="work">Work</option>
                            <option value="shared">Shared</option>
                        </select>
                    </div>
                    <div class="nu-field">
                        <label>Event Type</label>
                        <select class="nu-input" id="event_type">
                            <option value="meeting">Meeting</option>
                            <option value="task">Task</option>
                            <option value="reminder">Reminder</option>
                            <option value="deadline">Deadline</option>
                        </select>
                    </div>
                </div>

                <div class="nu-field">
                    <label>Theme Color</label>
                    <input type="color" class="nu-input" id="event_color" value="#0ea5e9" style="height:40px;padding:4px;">
                </div>
            </div>

            <!-- TAB 2: RECURRENCE -->
            <div id="modal_pane_recurrence" style="display:none;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <input type="checkbox" id="is_recurring" onchange="NuCalendarApp.toggleRecurrenceSection()">
                    <label for="is_recurring" style="font-size:13px;font-weight:600;margin:0;cursor:pointer;">Repeat this event</label>
                </div>

                <div id="recurrence_config_section" style="display:none;border-top:1px solid var(--border-color);padding-top:16px;">
                    <div class="nu-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div class="nu-field" style="margin-bottom:0;">
                            <label>Frequency</label>
                            <select class="nu-input" id="recur_freq" onchange="NuCalendarApp.onRecurrenceRuleChange()">
                                <option value="DAILY">Daily</option>
                                <option value="WEEKLY">Weekly</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="YEARLY">Yearly</option>
                            </select>
                        </div>
                        <div class="nu-field" style="margin-bottom:0;">
                            <label>Interval</label>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:12px;">Every</span>
                                <input type="number" class="nu-input" id="recur_interval" value="1" min="1" style="width:70px;" onchange="NuCalendarApp.onRecurrenceRuleChange()">
                                <span id="recur_interval_unit" style="font-size:12px;">week(s)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly specific select days checklist -->
                    <div id="recur_weekly_days" class="nu-field" style="display:none;">
                        <label>Repeat on</label>
                        <div class="nu-recurrence-grid-days">
                            <button type="button" class="nu-recurrence-day-btn" data-day="SU" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Sun</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="MO" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Mon</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="TU" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Tue</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="WE" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Wed</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="TH" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Thu</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="FR" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Fri</button>
                            <button type="button" class="nu-recurrence-day-btn" data-day="SA" onclick="NuCalendarApp.toggleRecurrenceDay(this)">Sat</button>
                        </div>
                    </div>

                    <!-- End Conditions -->
                    <div class="nu-field">
                        <label>Ends</label>
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal;color:var(--text-primary);margin:0;">
                                <input type="radio" name="recur_end" value="never" checked onchange="NuCalendarApp.onRecurrenceRuleChange()"> Never
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal;color:var(--text-primary);margin:0;">
                                <input type="radio" name="recur_end" value="count" onchange="NuCalendarApp.onRecurrenceRuleChange()"> After
                                <input type="number" id="recur_count" class="nu-input" value="10" min="1" style="width:70px;padding:4px 8px;margin:0 4px;" onchange="NuCalendarApp.onRecurrenceRuleChange()"> occurrences
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal;color:var(--text-primary);margin:0;">
                                <input type="radio" name="recur_end" value="until" onchange="NuCalendarApp.onRecurrenceRuleChange()"> On
                                <input type="date" id="recur_until" class="nu-input" style="width:140px;padding:4px 8px;margin-left:4px;" onchange="NuCalendarApp.onRecurrenceRuleChange()">
                            </label>
                        </div>
                    </div>

                    <!-- Recurrence Summary & live Next 5 Occurrences list -->
                    <div class="nu-recurrence-summary">
                        <div style="font-weight:600;margin-bottom:4px;">Rule Summary:</div>
                        <div id="recurrenceSummaryText">No recurrence pattern defined yet.</div>
                        <div style="font-weight:600;margin-top:8px;margin-bottom:4px;">Next 5 Occurrences:</div>
                        <ul id="recurrencePreviewList" style="margin:0;padding-left:18px;font-size:11px;color:var(--text-secondary);"></ul>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ATTENDEES & REMINDERS -->
            <div id="modal_pane_attendees" style="display:none;">
                <div class="nu-field">
                    <label>RSVP / Attendees</label>
                    <input type="text" class="nu-input" placeholder="Enter emails separated by comma..." style="margin-bottom:8px;">
                    <p style="font-size:11px;color:var(--text-tertiary);">Separate emails with commas to invite team members and guests.</p>
                </div>

                <div class="nu-field">
                    <label>Reminders & Notifications</label>
                    <select class="nu-input">
                        <option value="none">No Reminder</option>
                        <option value="15">15 Minutes before</option>
                        <option value="30" selected>30 Minutes before</option>
                        <option value="60">1 Hour before</option>
                        <option value="1440">1 Day before</option>
                    </select>
                </div>

                <div class="nu-field">
                    <label>Location / Call Link</label>
                    <input type="text" class="nu-input" placeholder="Google Meet, Zoom, or Meeting room name...">
                </div>
            </div>
        </div>

        <div class="nu-modal-footer">
            <button class="nu-btn nu-btn-danger" id="btnDeleteEvent" onclick="NuCalendarApp.onDeleteClick()" style="margin-right:auto;display:none;">Delete</button>
            <button class="nu-btn nu-btn-ghost" onclick="NuCalendarApp.closeModal()">Cancel</button>
            <button class="nu-btn nu-btn-primary" onclick="NuCalendarApp.onSaveClick()">Save</button>
        </div>
    </div>
</div>

<!-- DETACH CHOICE / CONFIRMATION DIALOG OVERLAY -->
<div class="nu-modal-overlay" id="detachChoiceModal" style="z-index: 1050;">
    <div class="nu-modal" style="max-width:400px;">
        <div class="nu-modal-header">
            <h3 class="nu-modal-title">Recurring Event Options</h3>
        </div>
        <div class="nu-modal-body" style="padding:20px;text-align:center;">
            <p id="detachChoicePrompt">This is a recurring event. Which occurrence would you like to edit?</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px;">
                <button class="nu-btn nu-btn-primary" onclick="NuCalendarApp.resolveDetachAction('instance')">Only This Occurrence</button>
                <button class="nu-btn nu-btn-ghost" onclick="NuCalendarApp.resolveDetachAction('series')" style="border-color:var(--primary);color:var(--primary);">The Entire Series</button>
                <button class="nu-btn nu-btn-ghost" onclick="NuCalendarApp.closeDetachModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- DETACH DELETION CHOICE DIALOG OVERLAY -->
<div class="nu-modal-overlay" id="detachDeleteChoiceModal" style="z-index: 1050;">
    <div class="nu-modal" style="max-width:400px;">
        <div class="nu-modal-header">
            <h3 class="nu-modal-title">Delete Recurring Event</h3>
        </div>
        <div class="nu-modal-body" style="padding:20px;text-align:center;">
            <p>This is a recurring event. Which occurrence would you like to delete?</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px;">
                <button class="nu-btn nu-btn-danger" onclick="NuCalendarApp.resolveDetachDelete('instance')">Only This Occurrence</button>
                <button class="nu-btn nu-btn-danger" onclick="NuCalendarApp.resolveDetachDelete('series')" style="background:#b91c1c;">The Entire Series</button>
                <button class="nu-btn nu-btn-ghost" onclick="NuCalendarApp.closeDetachDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>


<script>
if (!window.NuCalendarApp) {
window.NuCalendarApp = (function() {
    // Current Application States
    let currentDate = new Date();
    let currentMiniDate = new Date(currentDate);
    let currentView = 'month'; // 'month', 'week', 'workweek', 'day', 'agenda'
    let rawEvents = [];
    let expandedEvents = [];
    let searchFilter = '';
    let categoryFilters = { personal: true, work: true, shared: true };

    let currentActionType = null; // 'edit' or 'delete'
    let pendingEventContext = null; // holds details of currently targeted event instance

    // Initialize application
    function init() {
        // Set dynamic cols CSS property
        setColsProperty(7);
        // Bind key keyboard listeners
        bindKeyboardShortcuts();
        // Load initial events from DB
        fetchEvents();
        // Setup initial title and mini calendar
        renderMiniCalendar();
    }

    function setColsProperty(val) {
        document.documentElement.style.setProperty('--cols', val.toString());
    }

    // Keyboard Shortcuts
    function bindKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ignore if user is typing inside an input
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                return;
            }
            const key = e.key.toUpperCase();
            if (key === 'P') {
                navigate(-1);
            } else if (key === 'N') {
                navigate(1);
            } else if (key === 'T') {
                jumpToToday();
            } else if (key === 'C') {
                openNewEvent();
            }
        });
    }

    // Load events from REST API
    async function fetchEvents() {
        try {
            const res = await fetch('api/calendar.php?action=list');
            const data = await res.json();
            if (data.success) {
                rawEvents = data.events;
                expandAndRender();
            } else {
                showToast("Failed to fetch events: " + (data.error || "Unknown error"), "error");
            }
        } catch (e) {
            console.error("Error loading events:", e);
            showToast("Server communication error", "error");
        }
    }

    // Real-Time search filtering
    function searchEvents(query) {
        searchFilter = query.toLowerCase().trim();
        expandAndRender();
    }

    // Category Filter toggles
    function toggleCategoryFilter(category) {
        categoryFilters[category] = !categoryFilters[category];
        const chk = document.getElementById('cat_' + category);
        if (chk) chk.checked = categoryFilters[category];
        expandAndRender();
    }

    function showToast(message, type = "success") {
        let container = document.querySelector('.nu-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'nu-toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'nu-toast' + (type === 'error' ? ' error' : '');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Navigate Calendar Timeline
    function navigate(direction) {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() + direction);
        } else if (currentView === 'week' || currentView === 'workweek') {
            currentDate.setDate(currentDate.getDate() + (direction * 7));
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() + direction);
        } else if (currentView === 'agenda') {
            currentDate.setDate(currentDate.getDate() + (direction * 7));
        }
        currentMiniDate = new Date(currentDate);
        renderMiniCalendar();
        expandAndRender();
    }

    function jumpToToday() {
        currentDate = new Date();
        currentMiniDate = new Date(currentDate);
        renderMiniCalendar();
        expandAndRender();
    }

    function jumpNext7Days() {
        currentView = 'agenda';
        currentDate = new Date();
        const btns = document.querySelectorAll('.nu-cal-view-btn');
        btns.forEach(b => b.classList.remove('active'));
        document.getElementById('view_agenda').classList.add('active');
        expandAndRender();
    }

    function switchView(view) {
        currentView = view;
        const btns = document.querySelectorAll('.nu-cal-view-btn');
        btns.forEach(b => b.classList.remove('active'));
        document.getElementById('view_' + view).classList.add('active');

        if (view === 'workweek') {
            setColsProperty(5);
        } else if (view === 'week') {
            setColsProperty(7);
        } else if (view === 'day') {
            setColsProperty(1);
        }

        expandAndRender();
    }

    // Mini Calendar Grid rendering and navigation
    function navigateMini(direction) {
        currentMiniDate.setMonth(currentMiniDate.getMonth() + direction);
        renderMiniCalendar();
    }

    function renderMiniCalendar() {
        const miniGrid = document.getElementById('miniCalGrid');
        const miniTitle = document.getElementById('miniCalTitle');
        if (!miniGrid) return;

        const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        miniTitle.textContent = months[currentMiniDate.getMonth()] + " " + currentMiniDate.getFullYear();

        miniGrid.innerHTML = '';

        const days = ["S", "M", "T", "W", "T", "F", "S"];
        days.forEach(d => {
            const label = document.createElement('div');
            label.className = 'nu-mini-cal-day-label';
            label.textContent = d;
            miniGrid.appendChild(label);
        });

        const tempDate = new Date(currentMiniDate.getFullYear(), currentMiniDate.getMonth(), 1);
        const startOffset = tempDate.getDay();
        const daysInMonth = new Date(currentMiniDate.getFullYear(), currentMiniDate.getMonth() + 1, 0).getDate();

        // Previous month padding days
        const prevMonthDays = new Date(currentMiniDate.getFullYear(), currentMiniDate.getMonth(), 0).getDate();
        for (let i = startOffset - 1; i >= 0; i--) {
            const dCell = document.createElement('div');
            dCell.className = 'nu-mini-cal-day muted';
            dCell.textContent = (prevMonthDays - i).toString();
            miniGrid.appendChild(dCell);
        }

        // Active Month Days
        for (let d = 1; d <= daysInMonth; d++) {
            const dCell = document.createElement('div');
            dCell.className = 'nu-mini-cal-day';
            dCell.textContent = d.toString();

            const isSameDay = d === currentDate.getDate() &&
                currentMiniDate.getMonth() === currentDate.getMonth() &&
                currentMiniDate.getFullYear() === currentDate.getFullYear();

            if (isSameDay) {
                dCell.classList.add('active');
            }

            dCell.onclick = function() {
                currentDate = new Date(currentMiniDate.getFullYear(), currentMiniDate.getMonth(), d);
                renderMiniCalendar();
                expandAndRender();
            };

            miniGrid.appendChild(dCell);
        }
    }

    // Recurrence Pattern Expander using rrule.js
    function expandAndRender() {
        const startLimit = getStartLimitDate();
        const endLimit = getEndLimitDate();

        expandedEvents = [];

        // Group into master events and exception events
        const masters = rawEvents.filter(e => !e.event_recurrence_id);
        const exceptions = rawEvents.filter(e => e.event_recurrence_id);

        masters.forEach(ev => {
            // Expand standard or recurring event
            const list = expandEvent(ev, startLimit, endLimit);
            expandedEvents.push(...list);
        });

        // Overlay detached exceptions directly
        exceptions.forEach(ex => {
            // Exceptions replace original slots. The original dates have been appended to master exdate list,
            // so we can simply render this exception as a standalone event if it's within range
            const exStart = new Date(ex.event_start.replace(' ', 'T'));
            const exEnd = ex.event_end ? new Date(ex.event_end.replace(' ', 'T')) : new Date(exStart.getTime() + 3600000);

            if (exStart >= startLimit && exStart <= endLimit) {
                expandedEvents.push({
                    id: ex.event_id,
                    masterId: ex.event_recurrence_id,
                    title: ex.event_title,
                    description: ex.event_description,
                    start: exStart,
                    end: exEnd,
                    type: ex.event_type,
                    color: ex.event_color,
                    category: ex.event_category,
                    is_recurring: false,
                    is_exception: true,
                    original_start: ex.event_original_start,
                    master: ex
                });
            }
        });

        // Filter by category and search queries
        expandedEvents = expandedEvents.filter(ev => {
            if (!categoryFilters[ev.category]) return false;
            if (searchFilter) {
                const titleMatch = ev.title.toLowerCase().includes(searchFilter);
                const descMatch = (ev.description || '').toLowerCase().includes(searchFilter);
                return titleMatch || descMatch;
            }
            return true;
        });

        renderView();
    }

    function getStartLimitDate() {
        const date = new Date(currentDate);
        if (currentView === 'month') {
            return new Date(date.getFullYear(), date.getMonth(), 1);
        } else if (currentView === 'week' || currentView === 'workweek') {
            const day = date.getDay();
            const diff = date.getDate() - day;
            return new Date(date.setDate(diff));
        } else if (currentView === 'day') {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate());
        }
        return new Date(date.setDate(date.getDate() - 1));
    }

    function getEndLimitDate() {
        const date = new Date(currentDate);
        if (currentView === 'month') {
            return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59);
        } else if (currentView === 'week' || currentView === 'workweek') {
            const day = date.getDay();
            const diff = date.getDate() - day + 6;
            return new Date(date.setDate(diff));
        } else if (currentView === 'day') {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59);
        }
        return new Date(date.setDate(date.getDate() + 30));
    }

    function expandEvent(event, startLimit, endLimit) {
        if (!event.event_rrule) {
            const s = new Date(event.event_start.replace(' ', 'T'));
            const e = event.event_end ? new Date(event.event_end.replace(' ', 'T')) : new Date(s.getTime() + 3600000);
            return [{
                id: event.event_id,
                title: event.event_title,
                description: event.event_description,
                start: s,
                end: e,
                type: event.event_type,
                color: event.event_color,
                category: event.event_category,
                is_recurring: false,
                master: event
            }];
        }

        // It is recurring!
        try {
            if (!window.rrule || !window.rrule.RRule) {
                // Fallback if rrule is not loaded
                const s = new Date(event.event_start.replace(' ', 'T'));
                const e = event.event_end ? new Date(event.event_end.replace(' ', 'T')) : new Date(s.getTime() + 3600000);
                return [{
                    id: event.event_id,
                    title: event.event_title + " (Recurrence Offline)",
                    description: event.event_description,
                    start: s,
                    end: e,
                    type: event.event_type,
                    color: event.event_color,
                    category: event.event_category,
                    is_recurring: true,
                    master: event
                }];
            }

            const dtstart = new Date(event.event_start.replace(' ', 'T'));
            const ruleOptions = window.rrule.RRule.parseString(event.event_rrule);
            ruleOptions.dtstart = dtstart;

            const rule = new window.rrule.RRule(ruleOptions);
            const occurrences = rule.between(startLimit, endLimit, true);

            const exdates = event.event_exdate ? event.event_exdate.split(',').map(d => {
                // Parse normalized string exdates
                return new Date(d.trim().replace(' ', 'T')).toDateString();
            }) : [];

            const duration = (event.event_end ? new Date(event.event_end.replace(' ', 'T')).getTime() : dtstart.getTime() + 3600000) - dtstart.getTime();

            const list = [];
            occurrences.forEach(occ => {
                if (exdates.includes(occ.toDateString())) {
                    return; // skip excluded occurrences
                }

                const occStart = new Date(occ);
                const occEnd = new Date(occStart.getTime() + duration);

                list.push({
                    id: event.event_id + '_' + occStart.getTime(),
                    masterId: event.event_id,
                    title: event.event_title,
                    description: event.event_description,
                    start: occStart,
                    end: occEnd,
                    type: event.event_type,
                    color: event.event_color,
                    category: event.event_category,
                    is_recurring: true,
                    rrule: event.event_rrule,
                    original_start: formatIso(occStart),
                    master: event
                });
            });

            return list;
        } catch (err) {
            console.error("Failed to parse RRULE:", event.event_rrule, err);
            return [];
        }
    }

    // Main Render Views router
    function renderView() {
        const body = document.getElementById('calendarGridBody');
        const title = document.getElementById('calendarTitle');
        if (!body) return;

        body.innerHTML = '';

        const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        if (currentView === 'month') {
            title.textContent = months[currentDate.getMonth()] + " " + currentDate.getFullYear();
            renderMonthView(body);
        } else if (currentView === 'week' || currentView === 'workweek') {
            const startOfW = getStartLimitDate();
            const endOfW = getEndLimitDate();
            title.textContent = months[startOfW.getMonth()] + " " + startOfW.getDate() + " - " +
                                months[endOfW.getMonth()] + " " + endOfW.getDate() + ", " + endOfW.getFullYear();
            renderWeekView(body);
        } else if (currentView === 'day') {
            title.textContent = months[currentDate.getMonth()] + " " + currentDate.getDate() + ", " + currentDate.getFullYear();
            renderDayView(body);
        } else if (currentView === 'agenda') {
            title.textContent = "Agenda Listing";
            renderAgendaView(body);
        }
    }

    // MONTH VIEW BUILDER
    function renderMonthView(container) {
        const grid = document.createElement('div');
        grid.className = 'nu-month-grid';
        container.appendChild(grid);

        // Header days labels
        const labels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        labels.forEach(l => {
            const hCell = document.createElement('div');
            hCell.style.cssText = 'font-weight:600;font-size:11px;text-transform:uppercase;color:var(--text-tertiary);text-align:center;padding:12px;background:var(--bg-subtle);border-bottom:1px solid var(--border-color);';
            hCell.textContent = l;
            grid.appendChild(hCell);
        });

        const tempDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const startOffset = tempDate.getDay();
        const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();

        // Prev month padding cells
        const prevMonthDays = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0).getDate();
        for (let i = startOffset - 1; i >= 0; i--) {
            const dayNum = prevMonthDays - i;
            const cell = document.createElement('div');
            cell.className = 'nu-month-cell other-month';
            cell.innerHTML = `<div class="nu-month-day-num">${dayNum}</div>`;
            grid.appendChild(cell);
        }

        const now = new Date();

        // Active days cells
        for (let d = 1; d <= daysInMonth; d++) {
            const cellDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), d);
            const isToday = d === now.getDate() && currentDate.getMonth() === now.getMonth() && currentDate.getFullYear() === now.getFullYear();

            const cell = document.createElement('div');
            cell.className = 'nu-month-cell' + (isToday ? ' today' : '');
            cell.innerHTML = `<div class="nu-month-day-num">${d}</div>`;

            // Double click to add event on this date
            cell.ondblclick = function(e) {
                if (e.target === cell || e.target.classList.contains('nu-month-day-num')) {
                    const startVal = formatIsoDateTimeLocal(new Date(cellDate.setHours(9, 0)));
                    openNewEvent(startVal);
                }
            };

            // Retrieve events on this day
            const dayEvents = expandedEvents.filter(ev => {
                return ev.start.getDate() === d &&
                    ev.start.getMonth() === cellDate.getMonth() &&
                    ev.start.getFullYear() === cellDate.getFullYear();
            });

            // Display pills
            const maxVisible = 4;
            const visibleEvents = dayEvents.slice(0, maxVisible);
            visibleEvents.forEach(ev => {
                const pill = document.createElement('div');
                pill.className = 'nu-event-pill';
                if (ev.start < now) pill.classList.add('past');
                pill.style.background = ev.color;

                // Content with icon if recurring
                const icon = (ev.is_recurring) ? '↻ ' : '';
                pill.innerHTML = `${icon} <span class="nu-event-pill-time">${formatTime(ev.start)}</span> <span>${escapeHtml(ev.title)}</span>`;
                pill.onclick = (e) => {
                    e.stopPropagation();
                    openEditEvent(ev);
                };
                cell.appendChild(pill);
            });

            if (dayEvents.length > maxVisible) {
                const overflow = document.createElement('div');
                overflow.style.cssText = 'font-size:10px;font-weight:600;color:var(--accent);cursor:pointer;margin-top:2px;padding-left:4px;';
                overflow.textContent = `+${dayEvents.length - maxVisible} more`;
                overflow.onclick = function(e) {
                    e.stopPropagation();
                    currentDate = cellDate;
                    switchView('day');
                };
                cell.appendChild(overflow);
            }

            grid.appendChild(cell);
        }
    }

    // WEEK / DAY TIMELINE GRID BUILDER
    function renderWeekView(container) {
        const startOfWeek = getStartLimitDate();
        const numCols = currentView === 'workweek' ? 5 : 7;

        const wrapper = document.createElement('div');
        wrapper.className = 'nu-time-grid';
        container.appendChild(wrapper);

        // Header Labels
        const header = document.createElement('div');
        header.className = 'nu-time-grid-header';
        wrapper.appendChild(header);

        // Corner block
        const corner = document.createElement('div');
        corner.style.cssText = 'background:var(--bg-subtle);';
        header.appendChild(corner);

        const activeDays = [];
        const now = new Date();

        for (let i = 0; i < numCols; i++) {
            const cellDate = new Date(startOfWeek);
            cellDate.setDate(startOfWeek.getDate() + i + (currentView === 'workweek' ? 1 : 0)); // Skip Sunday if Work Week
            activeDays.push(cellDate);

            const isToday = cellDate.getDate() === now.getDate() && cellDate.getMonth() === now.getMonth() && cellDate.getFullYear() === now.getFullYear();

            const hCell = document.createElement('div');
            hCell.className = 'nu-time-grid-header-cell' + (isToday ? ' today' : '');
            const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            hCell.innerHTML = `<strong>${dayNames[cellDate.getDay()]}</strong><br><span style="font-size:11px;opacity:0.8;">${cellDate.getMonth() + 1}/${cellDate.getDate()}</span>`;
            header.appendChild(hCell);
        }

        // Timeline hours slots container
        const scrollArea = document.createElement('div');
        scrollArea.style.cssText = 'flex-grow:1;overflow-y:auto;position:relative;';
        wrapper.appendChild(scrollArea);

        const content = document.createElement('div');
        content.className = 'nu-time-grid-content';
        scrollArea.appendChild(content);

        // Render 24 hour blocks
        for (let h = 0; h < 24; h++) {
            const row = document.createElement('div');
            row.className = 'nu-time-row';
            content.appendChild(row);

            // Left Hour Label
            const hourLabel = document.createElement('div');
            hourLabel.className = 'nu-time-label';
            const displayH = h === 0 ? '12 AM' : h < 12 ? `${h} AM` : h === 12 ? '12 PM' : `${h - 12} PM`;
            hourLabel.textContent = displayH;
            row.appendChild(hourLabel);

            // Columns slots
            for (let c = 0; c < numCols; c++) {
                const cellDate = activeDays[c];
                const slot = document.createElement('div');
                slot.className = 'nu-time-slot-cell';

                // Allow clicking hour slot to add
                slot.onclick = function(e) {
                    if (e.target === slot) {
                        const startVal = formatIsoDateTimeLocal(new Date(cellDate.getFullYear(), cellDate.getMonth(), cellDate.getDate(), h, 0));
                        openNewEvent(startVal);
                    }
                };

                row.appendChild(slot);
            }
        }

        // Absolute Event Pills Overlay on timeline
        activeDays.forEach((cellDate, colIdx) => {
            const dayEvents = expandedEvents.filter(ev => {
                return ev.start.getDate() === cellDate.getDate() &&
                    ev.start.getMonth() === cellDate.getMonth() &&
                    ev.start.getFullYear() === cellDate.getFullYear();
            });

            dayEvents.forEach(ev => {
                const sHour = ev.start.getHours();
                const sMin = ev.start.getMinutes();
                const durationHrs = (ev.end - ev.start) / 3600000;

                const topPx = (sHour * 60) + sMin;
                const heightPx = Math.max(durationHrs * 60, 24); // minimum 24px

                const pill = document.createElement('div');
                pill.className = 'nu-event-pill nu-event-absolute';
                if (ev.start < now) pill.classList.add('past');
                pill.style.background = ev.color;

                // Position calculations
                const colWidthPercent = 100 / numCols;
                const leftPos = (colIdx * colWidthPercent);
                pill.style.top = `${topPx}px`;
                pill.style.height = `${heightPx}px`;
                pill.style.left = `calc(${leftPos}% + 60px + 4px)`;
                pill.style.width = `calc(${colWidthPercent}% - 8px)`;

                // Content
                const icon = ev.is_recurring ? '↻ ' : '';
                pill.innerHTML = `
                    <div style="font-weight:700;font-size:9px;margin-bottom:2px;">${formatTime(ev.start)} - ${formatTime(ev.end)}</div>
                    <div style="font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${icon}${escapeHtml(ev.title)}</div>
                    ${heightPx > 45 ? `<div style="font-size:10px;opacity:0.85;margin-top:2px;">${escapeHtml(ev.description || '')}</div>` : ''}
                `;

                // Edit on click
                pill.onclick = (e) => {
                    e.stopPropagation();
                    openEditEvent(ev);
                };

                content.appendChild(pill);
            });
        });

        // Current time-marker line if today is displayed
        const todayIdx = activeDays.findIndex(d => d.getDate() === now.getDate() && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear());
        if (todayIdx !== -1) {
            const timeMarker = document.createElement('div');
            timeMarker.style.cssText = `position:absolute;height:2px;background:#ef4444;left:calc(${todayIdx * (100 / numCols)}% + 60px);width:${100 / numCols}%;z-index:9;pointer-events:none;`;
            const curTop = (now.getHours() * 60) + now.getMinutes();
            timeMarker.style.top = `${curTop}px`;

            const dot = document.createElement('div');
            dot.style.cssText = 'width:6px;height:6px;border-radius:50%;background:#ef4444;position:absolute;left:-3px;top:-2px;';
            timeMarker.appendChild(dot);
            content.appendChild(timeMarker);
        }
    }

    // DAY TIMELINE GRID BUILDER (Simplified Week view with 1 column)
    function renderDayView(container) {
        setColsProperty(1);
        renderWeekView(container);
    }

    // AGENDA LIST VIEW BUILDER
    function renderAgendaView(container) {
        const agendaContainer = document.createElement('div');
        agendaContainer.className = 'nu-agenda-list';
        container.appendChild(agendaContainer);

        const sortedEvents = [...expandedEvents].sort((a, b) => a.start - b.start);

        if (sortedEvents.length === 0) {
            agendaContainer.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-tertiary);">No upcoming events matches these filters.</div>';
            return;
        }

        // Group by Date key
        const groups = {};
        sortedEvents.forEach(ev => {
            const dKey = ev.start.toDateString();
            if (!groups[dKey]) groups[dKey] = [];
            groups[dKey].push(ev);
        });

        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        Object.keys(groups).forEach(dStr => {
            const dObj = new Date(dStr);
            const group = document.createElement('div');
            group.className = 'nu-agenda-day-group';

            const header = document.createElement('div');
            header.className = 'nu-agenda-day-header';
            header.innerHTML = `<span>${dayNames[dObj.getDay()]}, ${months[dObj.getMonth()]} ${dObj.getDate()}</span> <span>${dObj.getFullYear()}</span>`;
            group.appendChild(header);

            groups[dStr].forEach(ev => {
                const item = document.createElement('div');
                item.className = 'nu-agenda-item';

                const timeStr = `${formatTime(ev.start)} - ${formatTime(ev.end)}`;
                const icon = ev.is_recurring ? '↻ ' : '';

                item.innerHTML = `
                    <div class="nu-agenda-item-time" style="border-left:4px solid ${ev.color};padding-left:10px;">${timeStr}</div>
                    <div class="nu-agenda-item-details">
                        <div class="nu-agenda-item-title">${icon}${escapeHtml(ev.title)}</div>
                        <div class="nu-agenda-item-desc">${escapeHtml(ev.description || 'No description.')}</div>
                    </div>
                `;

                item.onclick = () => openEditEvent(ev);
                group.appendChild(item);
            });

            agendaContainer.appendChild(group);
        });
    }

    // Modal Opening logic
    function openNewEvent(presetStart) {
        resetModalForm();
        document.getElementById('calendarModalTitle').textContent = 'New Event';
        document.getElementById('btnDeleteEvent').style.display = 'none';

        // Set times
        const now = presetStart ? new Date(presetStart) : new Date();
        const startVal = presetStart || formatIsoDateTimeLocal(now);

        const nextHour = new Date(now.getTime() + 3600000);
        const endVal = formatIsoDateTimeLocal(nextHour);

        document.getElementById('event_start').value = startVal;
        document.getElementById('event_end').value = endVal;

        // Show Details tab first
        switchModalTab('details');

        document.getElementById('calendarEventModal').style.display = 'flex';
    }

    function openEditEvent(ev) {
        resetModalForm();
        document.getElementById('calendarModalTitle').textContent = 'Edit Event';
        document.getElementById('btnDeleteEvent').style.display = 'block';

        // Load details
        document.getElementById('event_id').value = ev.masterId || ev.id;
        document.getElementById('event_title').value = ev.title;
        document.getElementById('event_description').value = ev.description || '';
        document.getElementById('event_start').value = formatIsoDateTimeLocal(ev.start);
        document.getElementById('event_end').value = formatIsoDateTimeLocal(ev.end);
        document.getElementById('event_category').value = ev.category;
        document.getElementById('event_type').value = ev.type || 'meeting';
        document.getElementById('event_color').value = ev.color;

        // Detached occurrence exceptions metadata
        if (ev.is_recurring) {
            document.getElementById('event_recurrence_id').value = ev.masterId;
            document.getElementById('event_original_start').value = ev.original_start;
        }

        // Set recurrence options from master
        const master = ev.master;
        if (master && master.event_rrule) {
            document.getElementById('is_recurring').checked = true;
            document.getElementById('recurrence_config_section').style.display = 'block';
            parseRruleToForm(master.event_rrule);
        } else {
            document.getElementById('is_recurring').checked = false;
            document.getElementById('recurrence_config_section').style.display = 'none';
        }

        switchModalTab('details');
        document.getElementById('calendarEventModal').style.display = 'flex';
    }

    function resetModalForm() {
        document.getElementById('event_id').value = '';
        document.getElementById('event_recurrence_id').value = '';
        document.getElementById('event_original_start').value = '';
        document.getElementById('event_title').value = '';
        document.getElementById('event_description').value = '';
        document.getElementById('event_category').value = 'personal';
        document.getElementById('event_type').value = 'meeting';
        document.getElementById('event_color').value = '#0ea5e9';
        document.getElementById('event_all_day').checked = false;

        document.getElementById('is_recurring').checked = false;
        document.getElementById('recurrence_config_section').style.display = 'none';
        document.getElementById('recur_freq').value = 'WEEKLY';
        document.getElementById('recur_interval').value = '1';

        // Clear day selection button states
        const dayBtns = document.querySelectorAll('.nu-recurrence-day-btn');
        dayBtns.forEach(b => b.classList.remove('active'));

        // Reset radio button End conditions
        const radios = document.getElementsByName('recur_end');
        radios.forEach(r => r.checked = r.value === 'never');
        document.getElementById('recur_count').value = '10';
        document.getElementById('recur_until').value = '';
    }

    function closeModal() {
        document.getElementById('calendarEventModal').style.display = 'none';
    }

    // Modal navigation Tabs switching
    function switchModalTab(tab) {
        const btns = document.querySelectorAll('.nu-modal-tab-btn');
        btns.forEach(b => b.classList.remove('active'));
        document.getElementById('tab_' + tab).classList.add('active');

        // Hide all panes
        document.getElementById('modal_pane_details').style.display = 'none';
        document.getElementById('modal_pane_recurrence').style.display = 'none';
        document.getElementById('modal_pane_attendees').style.display = 'none';

        // Show selected pane
        document.getElementById('modal_pane_' + tab).style.display = 'block';

        if (tab === 'recurrence') {
            onRecurrenceRuleChange();
        }
    }

    function toggleAllDay() {
        const isAllDay = document.getElementById('event_all_day').checked;
        const start = document.getElementById('event_start');
        const end = document.getElementById('event_end');
        if (isAllDay) {
            // strip times
            if (start.value) start.value = start.value.split('T')[0] + 'T00:00';
            if (end.value) end.value = end.value.split('T')[0] + 'T23:59';
        }
    }

    function onStartChange() {
        // Automatically sync end duration to match start shift
        onRecurrenceRuleChange();
    }

    function toggleRecurrenceSection() {
        const checked = document.getElementById('is_recurring').checked;
        document.getElementById('recurrence_config_section').style.display = checked ? 'block' : 'none';
        if (checked) {
            onRecurrenceRuleChange();
        }
    }

    function toggleRecurrenceDay(btn) {
        btn.classList.toggle('active');
        onRecurrenceRuleChange();
    }

    // Live Recurrence Engine Summary Text & Next 5 preview instances
    function onRecurrenceRuleChange() {
        if (!document.getElementById('is_recurring').checked) return;

        const summaryText = document.getElementById('recurrenceSummaryText');
        const previewList = document.getElementById('recurrencePreviewList');
        if (!summaryText || !previewList) return;

        const rruleStr = generateRruleString();
        const startVal = document.getElementById('event_start').value;

        if (!startVal) {
            summaryText.textContent = "Please select a start date first.";
            previewList.innerHTML = '';
            return;
        }

        try {
            if (!window.rrule || !window.rrule.RRule) {
                summaryText.textContent = rruleStr;
                previewList.innerHTML = '<li>Library offline. Saving this rule matches standard standard RFC 5545 specifications</li>';
                return;
            }

            const dtstart = new Date(startVal.replace(' ', 'T'));
            const options = window.rrule.RRule.parseString(rruleStr);
            options.dtstart = dtstart;

            const rule = new window.rrule.RRule(options);

            // Text representation description
            summaryText.textContent = rule.toText();

            // Next 5 dates
            const occurrences = rule.all(function(occ, i) { return i < 5; });
            previewList.innerHTML = '';
            occurrences.forEach(occ => {
                const li = document.createElement('li');
                li.textContent = occ.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                previewList.appendChild(li);
            });
        } catch (e) {
            summaryText.textContent = "Invalid recurrence pattern combination.";
            previewList.innerHTML = '';
        }
    }

    // Convert form options to standard RFC 5545 RRULE string
    function generateRruleString() {
        const freq = document.getElementById('recur_freq').value;
        const interval = parseInt(document.getElementById('recur_interval').value) || 1;

        let parts = [`FREQ=${freq}`, `INTERVAL=${interval}`];

        // Weekly days
        if (freq === 'WEEKLY') {
            const activeDays = [];
            const dayBtns = document.querySelectorAll('.nu-recurrence-day-btn.active');
            dayBtns.forEach(b => activeDays.push(b.getAttribute('data-day')));
            if (activeDays.length > 0) {
                parts.push(`BYDAY=${activeDays.join(',')}`);
            }
        }

        // End limits
        const endType = document.querySelector('input[name="recur_end"]:checked').value;
        if (endType === 'count') {
            const count = parseInt(document.getElementById('recur_count').value) || 10;
            parts.push(`COUNT=${count}`);
        } else if (endType === 'until') {
            const untilVal = document.getElementById('recur_until').value;
            if (untilVal) {
                // RRULE expects UTC string format for UNTIL: e.g. YYYYMMDDTHHMMSSZ
                const untilDate = new Date(untilVal + 'T23:59:59');
                const utcStr = untilDate.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
                parts.push(`UNTIL=${utcStr}`);
            }
        }

        return parts.join(';');
    }

    // Parse existing RRULE string to populates form controls
    function parseRruleToForm(rruleStr) {
        if (!rruleStr) return;

        const options = {};
        const pairs = rruleStr.split(';');
        pairs.forEach(p => {
            const [k, v] = p.split('=');
            if (k && v) options[k] = v;
        });

        if (options.FREQ) document.getElementById('recur_freq').value = options.FREQ;
        if (options.INTERVAL) document.getElementById('recur_interval').value = options.INTERVAL;

        // Weekly days
        const dayBtns = document.querySelectorAll('.nu-recurrence-day-btn');
        dayBtns.forEach(b => b.classList.remove('active'));

        if (options.BYDAY) {
            const days = options.BYDAY.split(',');
            days.forEach(d => {
                const btn = document.querySelector(`.nu-recurrence-day-btn[data-day="${d}"]`);
                if (btn) btn.classList.add('active');
            });
        }

        // End limits
        const radios = document.getElementsByName('recur_end');
        radios.forEach(r => r.checked = false);

        if (options.COUNT) {
            document.querySelector('input[name="recur_end"][value="count"]').checked = true;
            document.getElementById('recur_count').value = options.COUNT;
        } else if (options.UNTIL) {
            document.querySelector('input[name="recur_end"][value="until"]').checked = true;
            // Parse standard UTC format YYYYMMDDTHHMMSSZ back to YYYY-MM-DD
            const m = options.UNTIL.match(/^(\d{4})(\d{2})(\d{2})/);
            if (m) {
                document.getElementById('recur_until').value = `${m[1]}-${m[2]}-${m[3]}`;
            }
        } else {
            document.querySelector('input[name="recur_end"][value="never"]').checked = true;
        }
    }

    // On SAVE Click handler
    function onSaveClick() {
        const id = document.getElementById('event_id').value;
        const recId = document.getElementById('event_recurrence_id').value;
        const isRecurChecked = document.getElementById('is_recurring').checked;

        // Check if editing a recurring instance
        if (id && recId) {
            // Show detach choice confirmation modal
            currentActionType = 'edit';
            pendingEventContext = {
                id: id,
                recId: recId,
                title: document.getElementById('event_title').value,
                start: document.getElementById('event_start').value,
                end: document.getElementById('event_end').value,
                description: document.getElementById('event_description').value,
                category: document.getElementById('event_category').value,
                type: document.getElementById('event_type').value,
                color: document.getElementById('event_color').value,
                original_start: document.getElementById('event_original_start').value
            };
            document.getElementById('calendarEventModal').style.display = 'none';
            document.getElementById('detachChoiceModal').style.display = 'flex';
        } else {
            // Standard saving process
            saveEventDirectly();
        }
    }

    // Perform exact direct API Save operations
    async function saveEventDirectly() {
        const id = document.getElementById('event_id').value;
        const title = document.getElementById('event_title').value;
        const start = document.getElementById('event_start').value;
        const end = document.getElementById('event_end').value;
        const description = document.getElementById('event_description').value;
        const category = document.getElementById('event_category').value;
        const type = document.getElementById('event_type').value;
        const color = document.getElementById('event_color').value;

        const isRecur = document.getElementById('is_recurring').checked;
        const rruleStr = isRecur ? generateRruleString() : null;

        const payload = {
            event_id: id ? parseInt(id) : null,
            event_title: title,
            event_start: start,
            event_end: end || null,
            event_description: description,
            event_category: category,
            event_type: type,
            event_color: color,
            event_rrule: rruleStr,
            event_exdate: null
        };

        try {
            const res = await fetch('api/calendar.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                showToast("Event saved successfully");
                closeModal();
                fetchEvents();
            } else {
                showToast("Save error: " + (data.error || "Unknown"), "error");
            }
        } catch (e) {
            console.error(e);
            showToast("Server network error", "error");
        }
    }

    // Resolve "This occurrence" vs "All series" action
    async function resolveDetachAction(choice) {
        if (choice === 'series') {
            // Edit the master series direct
            document.getElementById('detachChoiceModal').style.display = 'none';
            document.getElementById('event_id').value = pendingEventContext.id;
            document.getElementById('event_recurrence_id').value = '';
            document.getElementById('event_original_start').value = '';
            document.getElementById('calendarEventModal').style.display = 'flex';
            saveEventDirectly();
        } else if (choice === 'instance') {
            // Save detached exception record
            document.getElementById('detachChoiceModal').style.display = 'none';
            try {
                const payload = {
                    event_recurrence_id: parseInt(pendingEventContext.recId),
                    event_original_start: pendingEventContext.original_start,
                    event_title: pendingEventContext.title,
                    event_start: pendingEventContext.start,
                    event_end: pendingEventContext.end,
                    event_description: pendingEventContext.description,
                    event_category: pendingEventContext.category,
                    event_type: pendingEventContext.type,
                    event_color: pendingEventContext.color
                };

                const res = await fetch('api/calendar.php?action=detach_save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Single occurrence modified successfully");
                    fetchEvents();
                } else {
                    showToast("Modification error: " + (data.error || "Unknown"), "error");
                }
            } catch (e) {
                console.error(e);
                showToast("Server network error", "error");
            }
        }
    }

    function closeDetachModal() {
        document.getElementById('detachChoiceModal').style.display = 'none';
        document.getElementById('calendarEventModal').style.display = 'flex';
    }

    // Deletion Flow
    function onDeleteClick() {
        const id = document.getElementById('event_id').value;
        const recId = document.getElementById('event_recurrence_id').value;
        const origStart = document.getElementById('event_original_start').value;

        if (recId) {
            // Recurring Event
            document.getElementById('calendarEventModal').style.display = 'none';
            document.getElementById('detachDeleteChoiceModal').style.display = 'flex';
            pendingEventContext = { id: id, recId: recId, original_start: origStart };
        } else {
            // Normal event delete
            if (confirm("Are you sure you want to delete this event?")) {
                deleteEventDirectly(id);
            }
        }
    }

    async function deleteEventDirectly(eventId) {
        try {
            const res = await fetch(`api/calendar.php?action=delete&id=${eventId}`, {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success) {
                showToast("Event deleted successfully");
                closeModal();
                fetchEvents();
            } else {
                showToast("Delete error: " + (data.error || "Unknown"), "error");
            }
        } catch (e) {
            console.error(e);
            showToast("Server network error", "error");
        }
    }

    async function resolveDetachDelete(choice) {
        document.getElementById('detachDeleteChoiceModal').style.display = 'none';

        if (choice === 'series') {
            // Delete entire master series
            deleteEventDirectly(pendingEventContext.recId || pendingEventContext.id);
        } else if (choice === 'instance') {
            // If it's a detached occurrence instance that was edited, just delete its record
            const id = parseInt(pendingEventContext.id);
            const recId = parseInt(pendingEventContext.recId);

            if (id && id !== recId) {
                deleteEventDirectly(id);
                return;
            }

            // Exclude single virtual recurrence
            try {
                const payload = {
                    master_id: recId || id,
                    instance_start: pendingEventContext.original_start
                };

                const res = await fetch('api/calendar.php?action=delete_instance', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Single occurrence excluded");
                    fetchEvents();
                } else {
                    showToast("Exclusion error: " + (data.error || "Unknown"), "error");
                }
            } catch (e) {
                console.error(e);
                showToast("Server network error", "error");
            }
        }
    }

    function closeDetachDeleteModal() {
        document.getElementById('detachDeleteChoiceModal').style.display = 'none';
        document.getElementById('calendarEventModal').style.display = 'flex';
    }


    // Format Utilities
    function formatTime(date) {
        let hours = date.getHours();
        let minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // key zero to 12
        minutes = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutes + ' ' + ampm;
    }

    function formatIso(date) {
        // YYYY-MM-DD HH:MM:SS
        const pad = (num) => num.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function formatIsoDateTimeLocal(date) {
        // YYYY-MM-DDTHH:MM
        const pad = (num) => num.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function getEventById(id) {
        const numId = parseInt(id);
        const match = expandedEvents.find(e => (e.masterId || e.id) === numId || e.id === id);
        if (match) return match;
        const rawMatch = rawEvents.find(e => e.event_id === numId);
        if (rawMatch) {
            return {
                id: rawMatch.event_id,
                title: rawMatch.event_title,
                description: rawMatch.event_description,
                start: new Date(rawMatch.event_start.replace(' ', 'T')),
                end: rawMatch.event_end ? new Date(rawMatch.event_end.replace(' ', 'T')) : new Date(new Date(rawMatch.event_start.replace(' ', 'T')).getTime() + 3600000),
                type: rawMatch.event_type,
                color: rawMatch.event_color,
                category: rawMatch.event_category,
                is_recurring: !!rawMatch.event_rrule,
                master: rawMatch
            };
        }
        return null;
    }

    function _cleanup() {
        window.NuCalendarApp = null;
    }

    return {
        init: init,
        openNewEvent: openNewEvent,
        openEditEvent: openEditEvent,
        getEventById: getEventById,
        closeModal: closeModal,
        switchModalTab: switchModalTab,
        toggleAllDay: toggleAllDay,
        onStartChange: onStartChange,
        toggleRecurrenceSection: toggleRecurrenceSection,
        toggleRecurrenceDay: toggleRecurrenceDay,
        onRecurrenceRuleChange: onRecurrenceRuleChange,
        onSaveClick: onSaveClick,
        resolveDetachAction: resolveDetachAction,
        closeDetachModal: closeDetachModal,
        onDeleteClick: onDeleteClick,
        resolveDetachDelete: resolveDetachDelete,
        closeDetachDeleteModal: closeDetachDeleteModal,
        navigate: navigate,
        jumpToToday: jumpToToday,
        jumpNext7Days: jumpNext7Days,
        switchView: switchView,
        navigateMini: navigateMini,
        toggleCategoryFilter: toggleCategoryFilter,
        searchEvents: searchEvents,
        _cleanup: _cleanup
    };
})();
}

// Boot calendar application
NuCalendarApp.init();

// ── Backwards-compatibility aliases for legacy calendar scaffold click actions ──
window.openEventModal = function() {
    if (window.NuCalendarApp) window.NuCalendarApp.openNewEvent();
};
window.closeEventModal = function() {
    if (window.NuCalendarApp) window.NuCalendarApp.closeModal();
};
window.saveEvent = function() {
    if (window.NuCalendarApp) window.NuCalendarApp.onSaveClick();
};
window.viewEvent = function(id) {
    if (window.NuCalendarApp) {
        const ev = window.NuCalendarApp.getEventById(id);
        if (ev) window.NuCalendarApp.openEditEvent(ev);
    }
};
window.editEvent = function(id) {
    if (window.NuCalendarApp) {
        const ev = window.NuCalendarApp.getEventById(id);
        if (ev) window.NuCalendarApp.openEditEvent(ev);
    }
};
window.deleteEvent = function(id) {
    if (window.NuCalendarApp) {
        const ev = window.NuCalendarApp.getEventById(id);
        if (ev) {
            window.NuCalendarApp.openEditEvent(ev);
            window.NuCalendarApp.onDeleteClick();
        }
    }
};
</script>
