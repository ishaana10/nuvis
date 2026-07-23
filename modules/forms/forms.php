<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';
// $auth, $nuConfig available. Session open under 'nu5sess'.

$db    = NuDatabase::getInstance();
$forms = $db->fetchAll("SELECT * FROM nu_forms WHERE form_active = 1 ORDER BY form_id DESC");

// Fetch all tables in the current DB for the "existing table" dropdown
$pdo         = $db->getPdo();
$tablesStmt  = $pdo->query('SHOW TABLES');
$allTables   = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
// Filter out system tables
$userTables  = array_values(array_filter($allTables, fn($t) => !in_array($t, [
    'nu_users','nu_roles','nu_permissions','nu_role_permissions',
    'nu_api_tokens','nu_api_usage','nu_audit_log','nu_files',
    'nu_forms','nu_reports','nu_queries','nu_menus'
], true)));

// Fetch all roles for the condition builder
$roles = $db->fetchAll("SELECT role_code, role_name FROM nu_roles ORDER BY role_name");

// Group forms by type
$formsByType = ['main' => [], 'subform' => [], 'popup' => [], 'report' => []];
foreach ($forms as $f) {
    $t = $f['form_type'] ?? 'main';
    if (!isset($formsByType[$t])) $formsByType[$t] = [];
    $formsByType[$t][] = $f;
}
?>

<style>
/* ── Form Builder Styles ─────────────────────────────────────── */
.nb-builder-wrap { display:flex; gap:16px; height:calc(100vh - 300px); min-height:500px; }

/* Toolbox */
.nb-toolbox {
  width:160px; flex-shrink:0; display:flex; flex-direction:column;
  overflow-y:auto; padding-right:4px;
}
.nb-toolbox::-webkit-scrollbar { width:4px; }
.nb-toolbox::-webkit-scrollbar-thumb { background:var(--border-color); border-radius:4px; }
.nb-toolbox-title { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:8px; flex-shrink:0; }
.nb-tools-group { margin-bottom:10px; }
.nb-tools-group-label { font-size:9px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--text-tertiary); margin-bottom:4px; padding-left:2px; }
.nb-tool {
  display:flex; align-items:center; gap:6px; padding:5px 8px;
  border-radius:6px; cursor:grab; font-size:11px; font-weight:500;
  color:var(--text-primary); border:1px solid var(--border-color);
  background:var(--bg-surface); margin-bottom:3px;
  transition:background .15s, border-color .15s, box-shadow .15s;
  user-select:none;
}
.nb-tool:hover { background:var(--bg-elevated); border-color:var(--color-primary); box-shadow:0 0 0 2px color-mix(in oklch,var(--color-primary) 15%,transparent); }
.nb-tool svg { flex-shrink:0; color:var(--text-secondary); }
.nb-tool.dragging { opacity:.4; }

/* Canvas wrapper */
.nb-canvas-wrap { flex:1; display:flex; flex-direction:column; min-width:0; }
.nb-canvas-topbar {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:8px; gap:8px;
}
.nb-canvas-title { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-secondary); }
.nb-canvas-topbar-actions { display:flex; gap:6px; align-items:center; }

/* Canvas scroll area */
.nb-canvas {
  flex:1; overflow-y:auto; border:2px dashed var(--border-color);
  border-radius:10px; padding:10px; background:var(--bg-elevated);
  transition:border-color .2s; position:relative;
}
.nb-canvas::-webkit-scrollbar { width:5px; }
.nb-canvas::-webkit-scrollbar-thumb { background:var(--border-color); border-radius:4px; }
.nb-canvas.drag-over { border-color:var(--color-primary); background:color-mix(in oklch,var(--color-primary) 4%,var(--bg-elevated)); }
.nb-canvas-empty {
  text-align:center; padding:60px 20px; color:var(--text-tertiary);
  font-size:13px; pointer-events:none; position:absolute;
  top:50%; left:50%; transform:translate(-50%,-50%); width:80%;
}

/* ── ROW ── */
.nb-row {
  border:1px solid var(--border-color); border-radius:8px;
  margin-bottom:8px; background:var(--bg-surface);
  transition:border-color .15s;
}
.nb-row:hover { border-color:color-mix(in oklch,var(--color-primary) 40%,var(--border-color)); }
.nb-row.drag-row-over { border-color:var(--color-primary); border-style:dashed; background:color-mix(in oklch,var(--color-primary) 4%,var(--bg-surface)); }
.nb-row-header {
  display:flex; align-items:center; gap:6px; padding:5px 8px;
  border-bottom:1px solid var(--border-color); cursor:default;
  background:color-mix(in oklch,var(--bg-elevated) 60%,var(--bg-surface));
  border-radius:7px 7px 0 0;
}
.nb-row-drag { cursor:grab; color:var(--text-tertiary); font-size:15px; line-height:1; user-select:none; padding:0 2px; }
.nb-row-drag:active { cursor:grabbing; }
.nb-row-label { font-size:10px; color:var(--text-tertiary); font-weight:600; letter-spacing:.05em; text-transform:uppercase; flex:1; }
.nb-row-actions { display:flex; gap:4px; }
.nb-row-btn {
  padding:2px 7px; border-radius:5px; font-size:10px; font-weight:500;
  border:1px solid var(--border-color); background:none; cursor:pointer;
  color:var(--text-secondary); transition:all .15s;
}
.nb-row-btn:hover { background:var(--bg-elevated); border-color:var(--color-primary); color:var(--color-primary); }
.nb-row-btn.del:hover { background:#fee; border-color:#e55; color:#c33; }

/* Row body: 12-col grid */
.nb-row-body {
  display:grid;
  grid-template-columns:repeat(12,1fr);
  gap:6px;
  padding:8px;
  min-height:52px;
  position:relative;
}
.nb-row-body.drag-col-over::after {
  content:'';
  position:absolute; inset:4px;
  border:2px dashed var(--color-primary);
  border-radius:6px; pointer-events:none;
  background:color-mix(in oklch,var(--color-primary) 5%,transparent);
}

/* Drop zone when row is empty */
.nb-row-drop-hint {
  grid-column:1/-1; display:flex; align-items:center; justify-content:center;
  color:var(--text-tertiary); font-size:11px; padding:8px;
  border:1px dashed var(--border-color); border-radius:6px;
  pointer-events:none;
}

/* ── FIELD CARD inside a row ── */
.nb-cfield {
  border:1px solid var(--border-color); border-radius:7px;
  background:var(--bg-surface);
  transition:border-color .15s, box-shadow .15s;
  min-width:0; overflow:hidden;
  /* col-span set dynamically via style */
}
.nb-cfield:hover { border-color:var(--color-primary); box-shadow:0 0 0 2px color-mix(in oklch,var(--color-primary) 12%,transparent); }
.nb-cfield.drag-source { opacity:.3; }
.nb-cfield-header {
  display:flex; align-items:center; gap:5px; padding:6px 8px;
  cursor:pointer; user-select:none;
}
.nb-cfield-drag { cursor:grab; color:var(--text-tertiary); font-size:14px; flex-shrink:0; }
.nb-cfield-drag:active { cursor:grabbing; }
.nb-cfield-type-badge {
  font-size:9px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
  padding:1px 6px; border-radius:20px; flex-shrink:0;
  background:color-mix(in oklch,var(--color-primary) 12%,transparent);
  color:var(--color-primary);
}
.nb-cfield-label { flex:1; font-size:12px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.nb-cfield-span-badge {
  font-size:9px; font-weight:600; color:var(--text-tertiary);
  background:var(--bg-elevated); border:1px solid var(--border-color);
  padding:1px 5px; border-radius:4px; flex-shrink:0;
}
.nb-cfield-actions { display:flex; gap:3px; flex-shrink:0; }
.nb-cfield-btn {
  padding:2px 6px; border-radius:5px; font-size:10px; font-weight:500;
  border:1px solid var(--border-color); background:none; cursor:pointer;
  color:var(--text-secondary); transition:all .15s;
}
.nb-cfield-btn:hover { background:var(--bg-elevated); border-color:var(--color-primary); color:var(--color-primary); }
.nb-cfield-btn.del:hover { background:#fee; border-color:#e55; color:#c33; }

/* Field expand/collapse body */
.nb-cfield-body { display:none; padding:10px; border-top:1px solid var(--border-color); }
.nb-cfield-body.open { display:block; }

/* Column span bar */
.nb-span-bar {
  display:flex; align-items:center; gap:4px;
  padding:6px 10px; border-bottom:1px solid var(--border-color);
  background:color-mix(in oklch,var(--bg-elevated) 70%,var(--bg-surface));
  flex-wrap:wrap;
}
.nb-span-bar-label { font-size:10px; color:var(--text-tertiary); font-weight:600; letter-spacing:.04em; text-transform:uppercase; margin-right:4px; flex-shrink:0; }
.nb-span-btn {
  width:22px; height:22px; border-radius:4px; border:1px solid var(--border-color);
  font-size:10px; font-weight:600; cursor:pointer;
  background:var(--bg-surface); color:var(--text-secondary);
  display:flex; align-items:center; justify-content:center;
  transition:all .15s; flex-shrink:0;
}
.nb-span-btn:hover { border-color:var(--color-primary); color:var(--color-primary); background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface)); }
.nb-span-btn.active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; }
.nb-span-preview {
  margin-left:auto; font-size:10px; color:var(--text-tertiary);
  padding:2px 8px; background:var(--bg-elevated); border-radius:4px;
  border:1px solid var(--border-color); flex-shrink:0;
}

/* Inline label+input grid inside field body */
.nb-fp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:8px; }
.nb-fp { display:flex; flex-direction:column; gap:3px; }
.nb-fp label { font-size:10px; font-weight:600; color:var(--text-secondary); }
.nb-fp input,.nb-fp select,.nb-fp textarea { font-size:11px; }
.nb-fp-full { grid-column:1/-1; }
.nb-fp-check { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:500; color:var(--text-primary); }

/* Row drop-between placeholder */
.nb-row-placeholder {
  height:4px; border-radius:4px;
  background:var(--color-primary);
  margin:2px 0; opacity:0; transition:opacity .15s;
  pointer-events:none;
}
.nb-row-placeholder.visible { opacity:1; }

/* Add row button row */
.nb-add-row-btn {
  width:100%; padding:7px; border-radius:7px;
  border:1.5px dashed var(--border-color); background:none; cursor:pointer;
  font-size:11px; font-weight:600; color:var(--text-tertiary);
  transition:all .15s; margin-top:4px; display:flex; align-items:center; justify-content:center; gap:5px;
}
.nb-add-row-btn:hover { border-color:var(--color-primary); color:var(--color-primary); background:color-mix(in oklch,var(--color-primary) 4%,transparent); }

/* Form-level tabs */
.nb-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border-color); margin-bottom:16px; }
.nb-tab {
  padding:7px 14px; border-radius:8px 8px 0 0; font-size:12px; font-weight:600;
  cursor:pointer; border:1px solid transparent; border-bottom:none;
  color:var(--text-secondary); background:none; transition:all .15s;
  position:relative; top:1px;
}
.nb-tab:hover { color:var(--text-primary); background:var(--bg-elevated); }
.nb-tab.active { color:var(--color-primary); background:var(--bg-surface); border-color:var(--border-color); border-bottom-color:var(--bg-surface); }
.nb-tab-panel { display:none; }
.nb-tab-panel.active { display:block; }

/* Form card meta */
.nu-form-meta { font-size:12px; color:var(--text-tertiary); }

/* ── Compact meta row inside form list cards ── */
.nu-form-meta-row {
  display:flex; flex-wrap:wrap; align-items:center;
  gap:4px 12px; margin-bottom:10px;
}
.nu-form-meta-row .nu-form-meta {
  display:flex; align-items:center; gap:4px; margin-bottom:0;
  white-space:nowrap;
}
.nu-form-meta-row .nu-meta-sep {
  color:var(--border-color); font-size:10px; user-select:none;
}

/* ── Forms list grid — multi-column compact cards ── */
#formsGrid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
  gap:12px;
}
#formsGrid .nu-card {
  padding:14px 16px;
  display:flex;
  flex-direction:column;
}
#formsGrid .nu-card-header {
  margin-bottom:8px;
}
#formsGrid .nu-card > div:last-child {
  margin-top:auto;
}

/* Drag ghost */
.nb-drag-ghost {
  position:fixed; pointer-events:none; z-index:9999;
  background:var(--bg-surface); border:1px solid var(--color-primary);
  border-radius:8px; padding:8px 14px; font-size:12px; opacity:.85;
  box-shadow:0 4px 20px rgba(0,0,0,.15);
}

/* Save/cancel bar */
.nb-save-bar {
  display:flex !important;
  justify-content:flex-end;
  align-items:center;
  gap:8px;
  margin-top:20px;
  padding-top:16px;
  border-top:1px solid var(--border-color);
}

/* Browse display mode cards */
.nb-display-modes { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.nb-display-mode-card {
  flex:1; min-width:140px; border:2px solid var(--border-color);
  border-radius:10px; padding:14px 12px; cursor:pointer;
  background:var(--bg-surface); transition:all .15s; text-align:center;
}
.nb-display-mode-card:hover { border-color:var(--color-primary); background:var(--bg-elevated); }
.nb-display-mode-card.selected {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface));
}
.nb-display-mode-card input[type=radio] { display:none; }
.nb-display-mode-icon { font-size:22px; margin-bottom:6px; }
.nb-display-mode-label { font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:3px; }
.nb-display-mode-desc { font-size:11px; color:var(--text-tertiary); line-height:1.4; }

/* PK type toggle */
.nb-pk-cards { display:flex; gap:10px; }
.nb-pk-card {
  flex:1; border:2px solid var(--border-color); border-radius:10px;
  padding:12px 14px; cursor:pointer; background:var(--bg-surface);
  transition:all .15s;
}
.nb-pk-card:hover { border-color:var(--color-primary); background:var(--bg-elevated); }
.nb-pk-card.selected {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface));
}
.nb-pk-card input[type=radio] { display:none; }
.nb-pk-card-title { font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:3px; }
.nb-pk-card-code  { font-size:11px; font-family:monospace; color:var(--color-primary); margin-bottom:4px; }
.nb-pk-card-desc  { font-size:11px; color:var(--text-tertiary); line-height:1.4; }

/* Table mode toggle */
.nb-tmode-cards { display:flex; gap:10px; margin-bottom:12px; }
.nb-tmode-card {
  flex:1; border:2px solid var(--border-color); border-radius:10px;
  padding:12px 14px; cursor:pointer; background:var(--bg-surface);
  transition:all .15s;
}
.nb-tmode-card:hover { border-color:var(--color-primary); background:var(--bg-elevated); }
.nb-tmode-card.selected {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface));
}
.nb-tmode-card input[type=radio] { display:none; }
.nb-tmode-card-title { font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:3px; }
.nb-tmode-card-desc  { font-size:11px; color:var(--text-tertiary); line-height:1.4; }

/* Form type selector */
.nb-ftype-cards { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:0; }
.nb-ftype-card {
  flex:1; min-width:120px; border:2px solid var(--border-color); border-radius:10px;
  padding:12px 10px; cursor:pointer; background:var(--bg-surface);
  transition:all .15s; text-align:center;
}
.nb-ftype-card:hover { border-color:var(--color-primary); background:var(--bg-elevated); }
.nb-ftype-card.selected {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface));
}
.nb-ftype-card input[type=radio] { display:none; }
.nb-ftype-icon  { font-size:20px; margin-bottom:4px; }
.nb-ftype-label { font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:2px; }
.nb-ftype-desc  { font-size:10px; color:var(--text-tertiary); line-height:1.3; }

/* Form type badge in list */
.nb-type-badge {
  display:inline-block; font-size:10px; font-weight:700; letter-spacing:.04em;
  text-transform:uppercase; padding:2px 8px; border-radius:20px;
  vertical-align:middle;
}
.nb-type-badge.main    { background:color-mix(in oklch,var(--color-primary) 12%,transparent); color:var(--color-primary); }
.nb-type-badge.subform { background:color-mix(in oklch,#f59e0b 15%,transparent); color:#b45309; }
.nb-type-badge.popup   { background:color-mix(in oklch,#8b5cf6 15%,transparent); color:#6d28d9; }
.nb-type-badge.report  { background:color-mix(in oklch,#10b981 15%,transparent); color:#047857; }

/* Forms list filter tabs */
.nb-filter-tabs { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
.nb-filter-tab {
  padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600;
  border:1px solid var(--border-color); background:var(--bg-surface);
  cursor:pointer; transition:all .15s; color:var(--text-secondary);
}
.nb-filter-tab:hover { border-color:var(--color-primary); color:var(--color-primary); }
.nb-filter-tab.active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; }

/* ── Collapsible settings group ── */
.nb-settings-group {
  border:1px solid var(--border-color);
  border-radius:10px;
  margin-bottom:16px;
  overflow:hidden;
}
.nb-settings-group-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px; cursor:pointer;
  background:color-mix(in oklch,var(--bg-elevated) 70%,var(--bg-surface));
  user-select:none;
  transition:background .15s;
}
.nb-settings-group-header:hover {
  background:color-mix(in oklch,var(--color-primary) 6%,var(--bg-elevated));
}
.nb-settings-group-title {
  font-size:11px; font-weight:700; letter-spacing:.06em;
  text-transform:uppercase; color:var(--text-secondary);
  display:flex; align-items:center; gap:8px;
}
.nb-settings-group-summary {
  font-size:11px; color:var(--text-tertiary); font-weight:400;
  text-transform:none; letter-spacing:0; margin-left:4px;
}
.nb-settings-group-chevron {
  color:var(--text-tertiary); transition:transform .2s; flex-shrink:0;
}
.nb-settings-group-header.open .nb-settings-group-chevron {
  transform:rotate(180deg);
}
.nb-settings-group-body {
  display:none;
  padding:14px;
  border-top:1px solid var(--border-color);
  background:var(--bg-surface);
}
.nb-settings-group-body.open { display:block; }

/* ── Expandable row option (click-to-expand inside group) ── */
.nb-option-row {
  border:1px solid var(--border-color);
  border-radius:8px;
  margin-bottom:10px;
  overflow:hidden;
  background:var(--bg-surface);
}
.nb-option-row:last-child { margin-bottom:0; }
.nb-option-row-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:9px 12px; cursor:pointer;
  transition:background .15s;
}
.nb-option-row-header:hover {
  background:color-mix(in oklch,var(--color-primary) 5%,var(--bg-surface));
}
.nb-option-row-label {
  font-size:12px; font-weight:600; color:var(--text-primary);
  display:flex; align-items:center; gap:8px;
}
.nb-option-row-value {
  font-size:11px; color:var(--color-primary); font-weight:600;
  background:color-mix(in oklch,var(--color-primary) 10%,transparent);
  padding:2px 10px; border-radius:20px;
  display:flex; align-items:center; gap:5px;
}
.nb-option-row-chevron {
  color:var(--text-tertiary); transition:transform .2s; flex-shrink:0; margin-left:8px;
}
.nb-option-row-header.open .nb-option-row-chevron {
  transform:rotate(180deg);
}
.nb-option-row-body {
  display:none;
  padding:12px;
  border-top:1px solid var(--border-color);
  background:color-mix(in oklch,var(--bg-elevated) 50%,var(--bg-surface));
}
.nb-option-row-body.open { display:block; }

/* ── Ace Editor wrappers ── */
.nb-ace-wrap {
  position:relative;
  border:1px solid var(--border-color);
  border-radius:8px;
  overflow:hidden;
  background:#1e1e2e;
  display:flex;
  flex-direction:column;
}
.nb-ace-topbar {
  display:flex; align-items:center; justify-content:space-between;
  padding:5px 10px;
  background:#181825;
  border-bottom:1px solid rgba(255,255,255,.07);
  gap:8px;
  flex-shrink:0;
}
.nb-ace-lang-badge {
  font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
  padding:2px 8px; border-radius:20px; flex-shrink:0;
}
.nb-ace-lang-badge.js  { background:rgba(247,223,30,.15); color:#f7df1e; }
.nb-ace-lang-badge.php { background:rgba(119,123,180,.2);  color:#9b9fd4; }
.nb-ace-lang-badge.css { background:rgba(38,198,218,.15);  color:#26c6da; }
.nb-ace-hint {
  font-size:10px; color:rgba(255,255,255,.3); flex:1; text-align:right;
}
.nb-ace-theme-btn {
  font-size:10px; padding:2px 8px; border-radius:4px; cursor:pointer;
  border:1px solid rgba(255,255,255,.15); background:none; color:rgba(255,255,255,.5);
  transition:all .15s;
}
.nb-ace-theme-btn:hover { background:rgba(255,255,255,.08); color:#fff; }
.nb-ace-editor {
  width:100%;
  font-size:13px;
  line-height:1.6;
  flex-shrink:0;
}
/* ── Resize handle below each Ace editor ── */
.nb-ace-resize-handle {
  height:16px;
  background:#181825;
  border-top:1px solid rgba(255,255,255,.12);
  cursor:ns-resize;
  display:flex;
  align-items:center;
  justify-content:center;
  user-select:none;
  flex-shrink:0;
  font-size:11px;
  letter-spacing:2px;
  color:rgba(255,255,255,.45);
  line-height:1;
  transition:background .15s, color .15s;
}
.nb-ace-resize-handle::after {
  content: '— — —';
}
.nb-ace-resize-handle:hover {
  background:#1f1f30;
  color:rgba(255,255,255,.85);
}
/* Hidden textarea synced on save */
.nb-ace-hidden { display:none !important; }

/* ── GROUP container field ── */
.nb-cfield-group-body {
  padding:8px; border-top:1px solid var(--border-color);
  background:color-mix(in oklch,var(--color-primary) 3%,var(--bg-surface));
  min-height:52px; border-radius:0 0 7px 7px;
}
.nb-cfield-group-body.drag-col-over {
  outline:2px dashed var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 6%,var(--bg-surface));
}

/* ── TAB container field ── */
.nb-cfield-tab-nav {
  display:flex; gap:0; border-bottom:1px solid var(--border-color);
  padding:6px 8px 0; flex-wrap:wrap; align-items:flex-end;
}
.nb-cfield-tab-nav-item {
  padding:4px 12px; font-size:11px; font-weight:600; cursor:pointer;
  border:1px solid transparent; border-bottom:none; border-radius:6px 6px 0 0;
  color:var(--text-secondary); background:none; transition:all .15s;
  position:relative; top:1px;
}
.nb-cfield-tab-nav-item.active {
  color:var(--color-primary); background:var(--bg-surface);
  border-color:var(--border-color); border-bottom-color:var(--bg-surface);
}
.nb-cfield-tab-panel { display:none; padding:8px; min-height:52px; }
.nb-cfield-tab-panel.active { display:block; }
.nb-cfield-tab-panel.drag-col-over {
  outline:2px dashed var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 4%,var(--bg-surface));
}
.nb-cfield-tab-add-btn {
  font-size:11px; padding:3px 8px; border-radius:5px; cursor:pointer;
  border:1px dashed var(--border-color); background:none;
  color:var(--text-tertiary); transition:all .15s; margin-left:auto; align-self:center;
}
.nb-cfield-tab-add-btn:hover { border-color:var(--color-primary); color:var(--color-primary); }
</style>

<div class="nu-forms">

  <!-- ── Forms list ────────────────────────────────────────────── -->
  <div id="formsListSection">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 class="nu-card-title">Forms</h3>
      <?php if ($auth->hasPermission('forms','create')): ?>
      <button class="nu-btn nu-btn-primary" onclick="nbFormBuilder.open()">+ New Form</button>
      <?php endif; ?>
    </div>

    <!-- Filter tabs -->
    <div class="nb-filter-tabs" id="nbFilterTabs">
      <button class="nb-filter-tab active" data-filter="all"     onclick="nbFilterForms('all',this)">All (<?= count($forms) ?>)</button>
      <button class="nb-filter-tab"        data-filter="main"    onclick="nbFilterForms('main',this)">⊞ Main (<?= count($formsByType['main']) ?>)</button>
      <button class="nb-filter-tab"        data-filter="subform" onclick="nbFilterForms('subform',this)">⊟ Subforms (<?= count($formsByType['subform']) ?>)</button>
      <button class="nb-filter-tab"        data-filter="popup"   onclick="nbFilterForms('popup',this)">▣ Popups (<?= count($formsByType['popup']) ?>)</button>
      <button class="nb-filter-tab"        data-filter="report"  onclick="nbFilterForms('report',this)">📊 Reports (<?= count($formsByType['report']) ?>)</button>
    </div>

    <div class="nu-grid" id="formsGrid">
      <?php foreach ($forms as $f): ?>
      <?php
        $layout     = @json_decode($f['form_layout'] ?? '[]', true);
        $fieldCount = is_array($layout) ? count($layout) : 0;
        $formLabel  = htmlspecialchars($f['form_name'], ENT_QUOTES);
        $formCode   = htmlspecialchars($f['form_code'], ENT_QUOTES);
        $browseMode = $f['browse_display_mode'] ?? 'inline';
        $pkType     = $f['form_pk_type']   ?? 'autoincrement';
        $tMode      = $f['form_table_mode'] ?? 'new';
        $fType      = $f['form_type']       ?? 'main';
        $typeLabels = ['main'=>'Main','subform'=>'Subform','popup'=>'Popup','report'=>'Report'];
        $typeLabel  = $typeLabels[$fType] ?? ucfirst($fType);
        $isMain     = in_array($fType, ['main','popup']);
        $pkLabel    = $pkType === 'uuid' ? 'UUID' : 'Auto-int';
      ?>
      <div class="nu-card" data-form-type="<?= htmlspecialchars($fType, ENT_QUOTES) ?>">
        <div class="nu-card-header" style="gap:8px;flex-wrap:wrap;">
          <h4 class="nu-card-title" style="flex:1;"><?= htmlspecialchars($f['form_name']) ?></h4>
          <span class="nb-type-badge <?= htmlspecialchars($fType) ?>"><?= $typeLabel ?></span>
          <span class="nu-badge"><?= htmlspecialchars($f['form_code']) ?></span>
        </div>

        <!-- Compact inline meta row -->
        <div class="nu-form-meta-row">
          <span class="nu-form-meta">
            🗄 <?= $f['form_table'] ? '<code>'.htmlspecialchars($f['form_table']).'</code>' : '<em style="color:var(--text-tertiary)">no table</em>' ?>
          </span>
          <span class="nu-meta-sep">·</span>
          <span class="nu-form-meta">
            📋 <strong style="color:var(--text-primary);"><?= $fieldCount ?></strong> field<?= $fieldCount !== 1 ? 's' : '' ?>
          </span>
          <span class="nu-meta-sep">·</span>
          <span class="nu-form-meta">
            🔑 <span style="font-weight:600;color:var(--color-primary);"><?= $pkLabel ?></span>
          </span>
          <?php if ($isMain): ?>
          <span class="nu-meta-sep">·</span>
          <span class="nu-form-meta">
            👁 <span style="font-weight:600;color:var(--color-primary);"><?= htmlspecialchars(ucfirst($browseMode)) ?></span>
          </span>
          <?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($isMain): ?>
          <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="window.previewForm && previewForm('<?= $formCode ?>','<?= $formLabel ?>')">⊞ Preview</button>
          <button class="nu-btn nu-btn-ghost nu-btn-sm"   onclick="window.browseForm  && browseForm('<?= $formCode ?>',1,'','<?= $formLabel ?>','<?= htmlspecialchars($browseMode,ENT_QUOTES) ?>')">⊟ Browse</button>
          <?php endif; ?>
          <button class="nu-btn nu-btn-ghost nu-btn-sm"   onclick="nbFormBuilder.edit(<?= (int)$f['form_id'] ?>)">✎ Edit</button>
          <button class="nu-btn nu-btn-danger nu-btn-sm"  onclick="window.deleteForm && deleteForm(<?= (int)$f['form_id'] ?>,'<?= $formLabel ?>')">Delete</button>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($forms)): ?>
      <div class="nu-card" style="grid-column:1/-1;text-align:center;padding:48px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-tertiary);margin:0 auto 12px;"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
        <p style="color:var(--text-tertiary);margin-bottom:12px;">No forms yet. Click "New Form" to create one.</p>
        <button class="nu-btn nu-btn-primary" onclick="nbFormBuilder.open()">+ New Form</button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Form Builder ──────────────────────────────────────────── -->
  <div class="nu-card" id="formBuilderCard" style="display:none;margin-top:24px;">
    <input type="hidden" id="editFormId" value="">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 class="nu-card-title" id="builderTitle">New Form</h3>
      <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="nbFormBuilder.close()">✕ Cancel</button>
    </div>

    <div class="nb-settings-group" id="nbSettingsGroup">
      <div class="nb-settings-group-header" id="nbSettingsHeader" onclick="nbToggleSettingsGroup()">
        <div class="nb-settings-group-title">
          ⚙ Form Settings
          <span class="nb-settings-group-summary" id="nbSettingsSummary">click to configure</span>
        </div>
        <svg class="nb-settings-group-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
      </div>
      <div class="nb-settings-group-body" id="nbSettingsBody">

        <div class="nb-option-row" id="optRowFormType">
          <div class="nb-option-row-header" onclick="nbToggleOptionRow('optRowFormType')">
            <div class="nb-option-row-label">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
              Form Type
            </div>
            <div style="display:flex;align-items:center;">
              <span class="nb-option-row-value" id="optValFormType">⊞ Main Form</span>
              <svg class="nb-option-row-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </div>
          </div>
          <div class="nb-option-row-body" id="optBodyFormType">
            <div class="nb-ftype-cards" id="nbFormTypeCards">
              <label class="nb-ftype-card selected" onclick="nbFormBuilder.selectFormType('main',this)">
                <input type="radio" name="formType" id="formTypeMain" value="main" checked>
                <div class="nb-ftype-icon">⊞</div>
                <div class="nb-ftype-label">Main Form</div>
                <div class="nb-ftype-desc">Standalone — appears in menu &amp; navigation</div>
              </label>
              <label class="nb-ftype-card" onclick="nbFormBuilder.selectFormType('subform',this)">
                <input type="radio" name="formType" id="formTypeSubform" value="subform">
                <div class="nb-ftype-icon">⊟</div>
                <div class="nb-ftype-label">Subform</div>
                <div class="nb-ftype-desc">Embedded inside a main form, linked by FK</div>
              </label>
              <label class="nb-ftype-card" onclick="nbFormBuilder.selectFormType('popup',this)">
                <input type="radio" name="formType" id="formTypePopup" value="popup">
                <div class="nb-ftype-icon">▣</div>
                <div class="nb-ftype-label">Popup</div>
                <div class="nb-ftype-desc">Opens in a modal for lookup / record selection</div>
              </label>
              <label class="nb-ftype-card" onclick="nbFormBuilder.selectFormType('report',this)">
                <input type="radio" name="formType" id="formTypeReport" value="report">
                <div class="nb-ftype-icon">📊</div>
                <div class="nb-ftype-label">Report</div>
                <div class="nb-ftype-desc">Read-only display form, no save button</div>
              </label>
            </div>
          </div>
        </div>

        <div class="nb-option-row" id="optRowNameCode">
          <div class="nb-option-row-header" onclick="nbToggleOptionRow('optRowNameCode')">
            <div class="nb-option-row-label">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10"/></svg>
              Form Name &amp; Code
            </div>
            <div style="display:flex;align-items:center;">
              <span class="nb-option-row-value" id="optValNameCode" style="font-style:italic;opacity:.6;">not set</span>
              <svg class="nb-option-row-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </div>
          </div>
          <div class="nb-option-row-body" id="optBodyNameCode">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label class="nu-label">Form Name</label>
                <input type="text" id="builderFormName" class="nu-input" placeholder="e.g. Customer Registration"
                       oninput="nbUpdateNameCodeSummary()">
              </div>
              <div>
                <label class="nu-label">Form Code <span style="font-weight:400;color:var(--text-tertiary);">(auto-generated if blank)</span></label>
                <input type="text" id="builderFormCode" class="nu-input" placeholder="e.g. customer_registration"
                       oninput="nbUpdateNameCodeSummary()">
              </div>
            </div>
          </div>
        </div>

        <div class="nb-option-row" id="optRowTable">
          <div class="nb-option-row-header" onclick="nbToggleOptionRow('optRowTable')">
            <div class="nb-option-row-label">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
              Table
            </div>
            <div style="display:flex;align-items:center;">
              <span class="nb-option-row-value" id="optValTable">✦ Create new</span>
              <svg class="nb-option-row-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </div>
          </div>
          <div class="nb-option-row-body" id="optBodyTable">
            <div class="nb-tmode-cards" id="nbTableModeCards">
              <label class="nb-tmode-card selected" onclick="nbFormBuilder.selectTableMode('new',this)">
                <input type="radio" name="formTableMode" id="formTableModeNew" value="new" checked>
                <div class="nb-tmode-card-title">✦ Create new table</div>
                <div class="nb-tmode-card-desc">NuBuilder creates and manages the DB table automatically</div>
              </label>
              <label class="nb-tmode-card" onclick="nbFormBuilder.selectTableMode('existing',this)">
                <input type="radio" name="formTableMode" id="formTableModeExisting" value="existing">
                <div class="nb-tmode-card-title">⊞ Use existing table</div>
                <div class="nb-tmode-card-desc">Attach this form to a table that already exists in the database</div>
              </label>
            </div>
            <div id="newTableWrap">
              <label class="nu-label">DB Table Name</label>
              <input type="text" id="builderFormTable" class="nu-input" placeholder="e.g. customers">
            </div>
            <div id="existingTableWrap" style="display:none;">
              <label class="nu-label">Select Existing Table</label>
              <select id="builderFormTableExisting" class="nu-input" onchange="nbFormBuilder.onExistingTableChange(this.value)">
                <option value="">-- choose a table --</option>
                <?php foreach ($userTables as $tbl): ?>
                <option value="<?= htmlspecialchars($tbl, ENT_QUOTES) ?>"><?= htmlspecialchars($tbl) ?></option>
                <?php endforeach; ?>
              </select>
              <p style="font-size:11px;color:var(--text-tertiary);margin-top:6px;">NuBuilder will read this table's columns and <strong>not</strong> alter its structure unless you explicitly add new fields.</p>
              <div id="existingTableColsStatus" style="display:none;font-size:11px;margin-top:6px;padding:6px 10px;border-radius:6px;"></div>
            </div>
          </div>
        </div>

        <div class="nb-option-row" id="optRowPk">
          <div class="nb-option-row-header" onclick="nbToggleOptionRow('optRowPk')">
            <div class="nb-option-row-label">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="17" r="3"/><path d="M10.5 17H21M14 14l3 3-3 3"/></svg>
              Primary Key Type
            </div>
            <div style="display:flex;align-items:center;">
              <span class="nb-option-row-value" id="optValPk">Auto-increment INT</span>
              <svg class="nb-option-row-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </div>
          </div>
          <div class="nb-option-row-body" id="optBodyPk">
            <div class="nb-pk-cards" id="nbPkCards">
              <label class="nb-pk-card selected" onclick="nbFormBuilder.selectPkType('autoincrement',this)">
                <input type="radio" name="formPkType" id="formPkTypeAuto" value="autoincrement" checked>
                <div class="nb-pk-card-title">Auto-increment INT</div>
                <div class="nb-pk-card-code">id INT AUTO_INCREMENT</div>
                <div class="nb-pk-card-desc">Standard MySQL integer PK. Simple, fast, and compatible with everything.</div>
              </label>
              <label class="nb-pk-card" onclick="nbFormBuilder.selectPkType('uuid',this)">
                <input type="radio" name="formPkType" id="formPkTypeUuid" value="uuid">
                <div class="nb-pk-card-title">NuBuilder UUID</div>
                <div class="nb-pk-card-code">id VARCHAR(36) — nubuilder style</div>
                <div class="nb-pk-card-desc">Globally unique string ID. Best for migration, sync, and multi-system environments.</div>
              </label>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Tabs -->
    <div class="nb-tabs" id="nbTabsRow">
      <button type="button" class="nb-tab active" data-panel="panelFields"  onclick="nbFormBuilder.switchTab(this)">Fields</button>
      <button type="button" class="nb-tab"        data-panel="panelBrowse"  onclick="nbFormBuilder.switchTab(this)" id="browseTab">Browse</button>
      <button type="button" class="nb-tab"        data-panel="panelEvents"  onclick="nbFormBuilder.switchTab(this)">Events / JS</button>
      <button type="button" class="nb-tab"        data-panel="panelPhpCss"  onclick="nbFormBuilder.switchTab(this)">PHP / CSS</button>
      <button type="button" class="nb-tab"        data-panel="panelVersions" onclick="nbFormBuilder.switchTab(this)" id="versionsTab">Version History</button>
    </div>

    <!-- ── TAB: Fields ── -->
    <div class="nb-tab-panel active" id="panelFields">
      <div class="nb-builder-wrap">
        <div class="nb-toolbox">
          <div class="nb-toolbox-title">Field Types</div>
          <div class="nb-tools-group">
            <div class="nb-tools-group-label">Basic</div>
            <div class="nb-tool" data-type="text"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>Text</div>
            <div class="nb-tool" data-type="textarea" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>Textarea</div>
            <div class="nb-tool" data-type="number"   draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 8h2v8M15 8h2v8"/></svg>Number</div>
            <div class="nb-tool" data-type="email"    draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>Email</div>
            <div class="nb-tool" data-type="password" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Password</div>
            <div class="nb-tool" data-type="date"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Date</div>
            <div class="nb-tool" data-type="datetime" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4l2 2"/></svg>DateTime</div>
            <div class="nb-tool" data-type="time"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>Time</div>
            <div class="nb-tool" data-type="checkbox" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 12 2 2 4-4"/></svg>Checkbox</div>
            <div class="nb-tool" data-type="file"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>File</div>
          </div>
          <div class="nb-tools-group">
            <div class="nb-tools-group-label">Choice</div>
            <div class="nb-tool" data-type="select" draggable="true">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>Select
            </div>
            <div class="nb-tool" data-type="select2" draggable="true">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>Select2
            </div>
            <div class="nb-tool" data-type="radio" draggable="true">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/></svg>Radio
            </div>
            <div class="nb-tool" data-type="checkbox_group" draggable="true">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 12 2 2 4-4"/></svg>Checkbox Group
            </div>
          </div>
          <div class="nb-tools-group">
            <div class="nb-tools-group-label">Advanced</div>
            <div class="nb-tool" data-type="lookup"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>Lookup</div>
            <div class="nb-tool" data-type="subform"    draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>Subform</div>
            <div class="nb-tool" data-type="calculated" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h8M4 17h12"/></svg>Calc</div>
            <div class="nb-tool" data-type="range"      draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="12" r="2"/><path d="M2 12h4M10 12h12"/></svg>Range</div>
            <div class="nb-tool" data-type="color"      draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>Color</div>
           <div class="nb-tool" data-type="uploadbutton" draggable="true">Upload Button</div>
<div class="nb-tool" data-type="signaturepad" draggable="true">Signature Pad</div>
<div class="nb-tool" data-type="customnumber" draggable="true">Custom Number</div>
          </div>
          <div class="nb-tools-group">
            <div class="nb-tools-group-label">Layout</div>
            <div class="nb-tool" data-type="html"    draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l-5-6 5-6M15 6l5 6-5 6"/></svg>HTML</div>
            <div class="nb-tool" data-type="divider" draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>Divider</div>
            <div class="nb-tool" data-type="button"  draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="2"/></svg>Button</div>
            <div class="nb-tool" data-type="group"   draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg>Group</div>
            <div class="nb-tool" data-type="tab"     draggable="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M9 5v4M15 5v4"/></svg>Tab</div>
          </div>
          <div class="nb-tools-group">
            <div class="nb-tools-group-label">Reusable Blocks</div>
            <div class="nb-tool" data-type="block_address" draggable="true" style="background:var(--bg-offset);border-color:var(--color-primary);">Address Block</div>
            <div class="nb-tool" data-type="block_contact" draggable="true" style="background:var(--bg-offset);border-color:var(--color-primary);">Contact Info</div>
            <div class="nb-tool" data-type="block_billing" draggable="true" style="background:var(--bg-offset);border-color:var(--color-primary);">Billing Block</div>
          </div>
        </div>

        <!-- Canvas -->
        <div class="nb-canvas-wrap">
          <div class="nb-canvas-topbar">
            <span class="nb-canvas-title">Form Layout</span>
            <div class="nb-canvas-topbar-actions">
              <span style="font-size:10px;color:var(--text-tertiary);">Drag fields into rows · resize with column buttons</span>
              <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick="nbFormBuilder.addRow()" style="font-size:11px;padding:3px 10px;">+ Add Row</button>
            </div>
          </div>
          <div class="nb-canvas" id="formCanvas">
            <div class="nb-canvas-empty" id="canvasEmpty">&#x2B06; Drag a field here or click a field type · rows are created automatically</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB: Browse ── -->
    <div class="nb-tab-panel" id="panelBrowse">
      <div style="margin-bottom:12px;padding:10px 14px;background:color-mix(in oklch,#f59e0b 10%,var(--bg-surface));border:1px solid color-mix(in oklch,#f59e0b 30%,transparent);border-radius:8px;font-size:12px;color:#92400e;" id="browseNotApplicable" style="display:none;">
        ℹ️ Browse settings only apply to <strong>Main</strong> and <strong>Popup</strong> form types.
      </div>
      <div style="margin-bottom:20px;">
        <label class="nu-label" style="margin-bottom:10px;display:block;">Browse Display Mode</label>
        <div class="nb-display-modes" id="browseDisplayModes">
          <label class="nb-display-mode-card selected" onclick="nbFormBuilder.selectDisplayMode('inline',this)">
            <input type="radio" name="browseDisplayMode" id="browseDisplayModeInline" value="inline" checked>
            <div class="nb-display-mode-icon">▤</div>
            <div class="nb-display-mode-label">Inline</div>
            <div class="nb-display-mode-desc">Replaces content area with breadcrumb navigation</div>
          </label>
          <label class="nb-display-mode-card" onclick="nbFormBuilder.selectDisplayMode('modal',this)">
            <input type="radio" name="browseDisplayMode" id="browseDisplayModeModal" value="modal">
            <div class="nb-display-mode-icon">▣</div>
            <div class="nb-display-mode-label">Modal</div>
            <div class="nb-display-mode-desc">Opens browse in a resizable overlay modal</div>
          </label>
          <label class="nb-display-mode-card" onclick="nbFormBuilder.selectDisplayMode('fullpage',this)">
            <input type="radio" name="browseDisplayMode" id="browseDisplayModeFullpage" value="fullpage">
            <div class="nb-display-mode-icon">⛶</div>
            <div class="nb-display-mode-label">Full Page</div>
            <div class="nb-display-mode-desc">Hides sidebar, browse fills entire viewport</div>
          </label>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;">
          <label class="nu-label">Browse SQL <span style="font-weight:400;color:var(--text-tertiary);">(leave empty for auto SELECT *)</span></label>
          <textarea id="formBrowseSql" class="nu-input" rows="4" placeholder="SELECT id, name, email FROM customers WHERE active = 1"></textarea>
        </div>
        <div style="grid-column:1/-1;">
          <label class="nu-label" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span>Visual Browse Column Designer</span>
            <span style="font-weight:400;color:var(--text-tertiary);font-size:11px;">Select fields, set widths, custom formats, pinning, and conditional highlights</span>
          </label>
          <div id="visualBrowseDesigner" style="background:var(--bg-elevated);padding:14px;border:1px dashed var(--border-color);border-radius:10px;margin-bottom:12px;">
            <div id="browseColumnsList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;"></div>
            <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick="nbFormBuilder.addBrowseColumnDesignerRow()">+ Add Column</button>
          </div>
          <!-- Hidden fallbacks/proxies to preserve legacy backend actions -->
          <input type="hidden" id="formBrowseColumns">
          <textarea id="formBrowseLayout" style="display:none;"></textarea>
        </div>
        <div>
          <label class="nu-label">Page Size</label>
          <input type="number" id="formBrowsePageSize" class="nu-input" value="20" min="1" max="500">
        </div>
        <div>
          <label class="nu-label">Default Sort <span style="font-weight:400;color:var(--text-tertiary);">(field ASC/DESC)</span></label>
          <input type="text" id="formBrowseDefaultSort" class="nu-input" placeholder="created_at DESC">
        </div>
        <div style="grid-column:1/-1;">
          <label class="nb-fp-check" style="margin-bottom:8px;">
            <input type="checkbox" id="formBrowseSearchEnabled"> Enable search bar
          </label>
        </div>
        <div>
          <label class="nu-label">Search Placeholder</label>
          <input type="text" id="formBrowseSearchPlaceholder" class="nu-input" placeholder="Search records...">
        </div>
        <div style="grid-column:1/-1;">
          <label class="nu-label">Search Fields <span style="font-weight:400;color:var(--text-tertiary);">(comma-sep)</span></label>
          <input type="text" id="formBrowseSearchFields" class="nu-input" placeholder="name, email">
        </div>
        <div style="grid-column:1/-1;border-top:1px solid var(--border-color);padding-top:16px;margin-top:8px;">
          <label class="nu-label" style="font-size:14px;font-weight:600;margin-bottom:12px;display:block;">Role-Based Browse Conditions</label>
          <p style="font-size:12px;color:var(--text-tertiary);margin-bottom:12px;">Define custom WHERE clauses and columns based on the user's role. Use <code>##key##</code> to inject global user meta (e.g. <code>##location##</code>).</p>
          <div id="browseConditionsList" style="display:flex;flex-direction:column;gap:12px;"></div>
          <button type="button" class="nu-btn nu-btn-sm nu-btn-ghost" style="margin-top:12px;" onclick="nbFormBuilder.addBrowseCondition()">+ Add Condition</button>

          <script>
            // Store roles array in JS for dynamic rendering
            window.nuRolesList = <?php echo json_encode($roles); ?>;
          </script>
        </div>

        <div style="grid-column:1/-1;border-top:1px solid var(--border-color);padding-top:16px;margin-top:8px;">
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            Browse PHP
            <span style="font-weight:400;color:var(--text-tertiary);">— customize browse query (use <code>$nuSql</code>, <code>$nuWhere</code>, <code>$nuOrder</code>, <code>$nuParams</code>)</span>
          </label>
          <div class="nb-ace-wrap" id="wrapBrowsePhp">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge php">PHP</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceBrowsePhp')">☀ theme</button>
            </div>
            <div id="aceBrowsePhp" class="nb-ace-editor" style="height:140px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceBrowsePhp"></div>
          </div>
          <textarea id="formBrowsePhp" class="nb-ace-hidden"></textarea>
        </div>
      </div>
    </div>

    <!-- ── TAB: Events / JS ── -->
    <div class="nb-tab-panel" id="panelEvents">
      <div style="display:grid;gap:16px;">

        <div>
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            JS On Load
            <span style="font-weight:400;color:var(--text-tertiary);">— runs after form renders, use <code>nu.getValue()</code> etc.</span>
          </label>
          <div class="nb-ace-wrap" id="wrapCustomJs">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge js">JS</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceCustomJs')">☀ theme</button>
            </div>
            <div id="aceCustomJs" class="nb-ace-editor" style="height:180px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceCustomJs"></div>
          </div>
          <textarea id="formCustomJs" class="nb-ace-hidden"></textarea>
        </div>

        <div>
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            JS Before Save
            <span style="font-weight:400;color:var(--text-tertiary);">— return false to cancel</span>
          </label>
          <div class="nb-ace-wrap" id="wrapJsBeforeSave">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge js">JS</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceJsBeforeSave')">☀ theme</button>
            </div>
            <div id="aceJsBeforeSave" class="nb-ace-editor" style="height:140px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceJsBeforeSave"></div>
          </div>
          <textarea id="formJsBeforeSave" class="nb-ace-hidden"></textarea>
        </div>

        <div>
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            JS After Save
            <span style="font-weight:400;color:var(--text-tertiary);">— e.g. nu.toast('Saved!')</span>
          </label>
          <div class="nb-ace-wrap" id="wrapJsAfterSave">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge js">JS</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceJsAfterSave')">☀ theme</button>
            </div>
            <div id="aceJsAfterSave" class="nb-ace-editor" style="height:140px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceJsAfterSave"></div>
          </div>
          <textarea id="formJsAfterSave" class="nb-ace-hidden"></textarea>
        </div>

      </div>
    </div>

    <!-- ── TAB: PHP / CSS ── -->
    <div class="nb-tab-panel" id="panelPhpCss">
      <div style="display:grid;gap:16px;">

        <div>
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            Custom PHP
            <span style="font-weight:400;color:var(--text-tertiary);">— runs server-side before render</span>
          </label>
          <div class="nb-ace-wrap" id="wrapCustomPhp">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge php">PHP</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceCustomPhp')">☀ theme</button>
            </div>
            <div id="aceCustomPhp" class="nb-ace-editor" style="height:200px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceCustomPhp"></div>
          </div>
          <textarea id="formCustomPhp" class="nb-ace-hidden"></textarea>
        </div>

        <div>
          <label class="nu-label" style="margin-bottom:6px;display:block;">
            Custom CSS
            <span style="font-weight:400;color:var(--text-tertiary);">— scoped to this form</span>
          </label>
          <div class="nb-ace-wrap" id="wrapCustomCss">
            <div class="nb-ace-topbar">
              <span class="nb-ace-lang-badge css">CSS</span>
              <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
              <button class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceCustomCss')">☀ theme</button>
            </div>
            <div id="aceCustomCss" class="nb-ace-editor" style="height:180px;"></div>
            <div class="nb-ace-resize-handle" data-ace="aceCustomCss"></div>
          </div>
          <textarea id="formCustomCss" class="nb-ace-hidden"></textarea>
        </div>

      </div>
    </div>

    <!-- ── TAB: Version History ── -->
    <div class="nb-tab-panel" id="panelVersions">
      <div style="background:var(--bg-elevated);padding:16px;border-radius:10px;border:1px solid var(--border-color);">
        <h4 style="margin:0 0 12px 0;font-size:16px;font-weight:600;">Form Revision Log</h4>
        <p style="font-size:13px;color:var(--text-tertiary);margin-bottom:16px;">
          View historical saves of this form layout and settings. Restoring a version will load its layout, code, and browse properties.
        </p>
        <div class="nu-table-wrap">
          <table class="nu-table" style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="border-bottom:2px solid var(--border-color);background:var(--bg-subtle);">
                <th style="padding:10px;text-align:left;font-size:12px;font-weight:600;">Version ID</th>
                <th style="padding:10px;text-align:left;font-size:12px;font-weight:600;">Saved By</th>
                <th style="padding:10px;text-align:left;font-size:12px;font-weight:600;">Saved At</th>
                <th style="padding:10px;text-align:left;font-size:12px;font-weight:600;width:120px;">Actions</th>
              </tr>
            </thead>
            <tbody id="formVersionsTableBody">
              <tr>
                <td colspan="4" style="padding:20px;text-align:center;color:var(--text-tertiary);">No revisions logged yet. Save changes to create a version snapshot.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Save bar -->
    <div class="nb-save-bar">
      <button type="button" class="nu-btn nu-btn-ghost" onclick="nbFormBuilder.close()">Cancel</button>
      <button type="button" class="nu-btn nu-btn-primary" onclick="window.saveForm && saveForm()">&#x1F4BE; Save Form</button>
    </div>

  </div>

</div>

<script>
// ── One-time setup: nbAce manager + UI helpers ───────────────────────
if (!window._nbFormsModuleInit) {
  window._nbFormsModuleInit = true;

  // ══════════════════════════════════════════════════════════════════
  //  Ace Editor manager
  // ══════════════════════════════════════════════════════════════════
  window.nbAce = (function() {
    var _editors = {};
    var _darkTheme  = 'ace/theme/one_dark';
    var _lightTheme = 'ace/theme/chrome';

    function _isDarkMode() {
      var t = document.documentElement.getAttribute('data-theme');
      if (t === 'dark')  return true;
      if (t === 'light') return false;
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function _init(editorId, hiddenId, mode) {
      if (!window.ace) return null;
      ace.require('ace/ext/language_tools');

      var editor = ace.edit(editorId);
      editor.setOptions({
        mode:                        'ace/mode/' + mode,
        theme:                       _isDarkMode() ? _darkTheme : _lightTheme,
        fontSize:                    '13px',
        tabSize:                     2,
        useSoftTabs:                 true,
        showPrintMargin:             false,
        enableBasicAutocompletion:   true,
        enableLiveAutocompletion:    true,
        enableSnippets:              true,
        wrap:                        false,
        scrollPastEnd:               0.3,
        showLineNumbers:             true,
        showGutter:                  true,
        highlightActiveLine:         true,
        fontFamily:                  'ui-monospace, "Cascadia Code", "Fira Code", monospace',
      });

      var hidden = document.getElementById(hiddenId);
      editor.session.on('change', function() {
        if (hidden) hidden.value = editor.getValue();
      });

      _editors[editorId] = { editor: editor, hiddenId: hiddenId, dark: _isDarkMode() };

      if (hidden && hidden.value) {
        editor.setValue(hidden.value, -1);
      }

      return editor;
    }

    function _setValue(editorId, value) {
      var entry = _editors[editorId];
      if (entry) {
        entry.editor.setValue(value || '', -1);
        entry.editor.clearSelection();
        entry.editor.getSession().getUndoManager().reset();
        var h = document.getElementById(entry.hiddenId);
        if (h) h.value = value || '';
      } else {
        var attempts = 0;
        function _retry() {
          attempts++;
          var e2 = _editors[editorId];
          if (e2) {
            e2.editor.setValue(value || '', -1);
            e2.editor.clearSelection();
            e2.editor.getSession().getUndoManager().reset();
            var h2 = document.getElementById(e2.hiddenId);
            if (h2) h2.value = value || '';
          } else if (attempts < 40) {
            requestAnimationFrame(_retry);
          }
        }
        requestAnimationFrame(_retry);
      }
    }

    function _getValue(editorId) {
      var entry = _editors[editorId];
      return entry ? entry.editor.getValue() : '';
    }

    function _syncAll() {
      Object.keys(_editors).forEach(function(id) {
        var entry = _editors[id];
        var h = document.getElementById(entry.hiddenId);
        if (h) h.value = entry.editor.getValue();
      });
    }

    function _toggleTheme(editorId) {
      var entry = _editors[editorId];
      if (!entry) return;
      entry.dark = !entry.dark;
      entry.editor.setTheme(entry.dark ? _darkTheme : _lightTheme);
    }

    function _resize(editorId) {
      var entry = _editors[editorId];
      if (entry) entry.editor.resize();
    }

    function _resizeAll() {
      Object.keys(_editors).forEach(_resize);
    }

    return {
      init:        _init,
      setValue:    _setValue,
      getValue:    _getValue,
      syncAll:     _syncAll,
      toggleTheme: _toggleTheme,
      resize:      _resize,
      resizeAll:   _resizeAll,
    };
  })();

  // ── Mount all Ace editors ─────────────────────────────────────────
  setTimeout(function() {
    if (!window.ace) {
      console.warn('[nub5] Ace editor not loaded — falling back to plain textareas');
      ['formCustomJs','formJsBeforeSave','formJsAfterSave','formCustomPhp','formCustomCss'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) { el.classList.remove('nb-ace-hidden'); el.rows = 8; }
      });
      return;
    }
    ace.config.set('basePath', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.9/');
    nbAce.init('aceCustomJs',      'formCustomJs',      'javascript');
    nbAce.init('aceJsBeforeSave',  'formJsBeforeSave',  'javascript');
    nbAce.init('aceJsAfterSave',   'formJsAfterSave',   'javascript');
    nbAce.init('aceCustomPhp',     'formCustomPhp',     'php');
    nbAce.init('aceCustomCss',     'formCustomCss',     'css');
    nbAce.init('aceBrowsePhp',     'formBrowsePhp',     'php');
  }, 100);

  // ── Resize Ace when its parent tab becomes visible ────────────────
  document.addEventListener('click', function(e) {
    var tab = e.target.closest('.nb-tab');
    if (tab) setTimeout(function(){ nbAce.resizeAll(); }, 50);
  });

  // ── Drag-to-resize handles ────────────────────────────────────────
  document.addEventListener('mousedown', function(e) {
    var handle = e.target.closest('.nb-ace-resize-handle');
    if (!handle) return;
    e.preventDefault();

    var aceId     = handle.dataset.ace;
    var editorDiv = document.getElementById(aceId);
    if (!editorDiv) return;

    var startY = e.clientY;
    var startH = editorDiv.offsetHeight;
    var minH   = 80;

    function onMove(ev) {
      var newH = Math.max(minH, startH + (ev.clientY - startY));
      editorDiv.style.height = newH + 'px';
      nbAce.resize(aceId);
    }
    function onUp() {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      nbAce.resize(aceId);
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });

  // ── Form type filter ─────────────────────────────────────────────
  window.nbFilterForms = function(filter, btn) {
    document.querySelectorAll('.nb-filter-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.querySelectorAll('#formsGrid .nu-card[data-form-type]').forEach(card => {
      const show = filter === 'all' || card.dataset.formType === filter;
      card.style.display = show ? '' : 'none';
    });
  };

  window.nbToggleSettingsGroup = function() {
    const header = document.getElementById('nbSettingsHeader');
    const body   = document.getElementById('nbSettingsBody');
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
  };

  window.nbToggleOptionRow = function(rowId) {
    const row    = document.getElementById(rowId);
    const header = row.querySelector('.nb-option-row-header');
    const body   = row.querySelector('.nb-option-row-body');
    const isOpen = body.classList.contains('open');
    document.querySelectorAll('.nb-option-row-body.open').forEach(b => {
      b.classList.remove('open');
      b.previousElementSibling.classList.remove('open');
    });
    if (!isOpen) {
      body.classList.add('open');
      header.classList.add('open');
    }
  };

  window.nbUpdateSettingsSummary = function() {
    const name    = document.getElementById('builderFormName')?.value?.trim() || '';
    const ftype   = document.querySelector('input[name="formType"]:checked')?.value || 'main';
    const typeMap = { main:'Main', subform:'Subform', popup:'Popup', report:'Report' };
    const parts   = [];
    if (name)  parts.push(name);
    parts.push(typeMap[ftype] || ftype);
    const summary = document.getElementById('nbSettingsSummary');
    if (summary) summary.textContent = parts.join(' · ');
  };

  window.nbUpdateNameCodeSummary = function() {
    const name = document.getElementById('builderFormName')?.value?.trim() || '';
    const code = document.getElementById('builderFormCode')?.value?.trim() || '';
    const pill = document.getElementById('optValNameCode');
    if (pill) {
      if (name) {
        pill.textContent = code ? name + ' / ' + code : name;
        pill.style.opacity = '1';
        pill.style.fontStyle = 'normal';
      } else {
        pill.textContent = 'not set';
        pill.style.opacity = '0.6';
        pill.style.fontStyle = 'italic';
      }
    }
    window.nbUpdateSettingsSummary();
  };

  // ── Patch saveForm: flush Ace editors first ───────────────────────
  const _origSaveForm = window.saveForm;
  window.saveForm = function() {
    nbAce.syncAll();
    if (typeof _origSaveForm === 'function') return _origSaveForm.apply(this, arguments);
  };

  nbFormBuilder.selectFormType = function(type, card) {
    document.querySelectorAll('.nb-ftype-card').forEach(c => c.classList.remove('selected'));
    if (card) card.classList.add('selected');
    const radio = document.querySelector('input[name="formType"][value="'+type+'"]');
    if (radio) radio.checked = true;
    const typeLabels = { main:'⊞ Main Form', subform:'⊟ Subform', popup:'▣ Popup', report:'📊 Report' };
    const pill = document.getElementById('optValFormType');
    if (pill) pill.textContent = typeLabels[type] || type;
    const browseTabEl  = document.getElementById('browseTab');
    const browseNotice = document.getElementById('browseNotApplicable');
    const isBrowseable = type === 'main' || type === 'popup';
    if (browseTabEl)  browseTabEl.style.opacity  = isBrowseable ? '1' : '0.4';
    if (browseNotice) browseNotice.style.display  = isBrowseable ? 'none' : 'block';
    window.nbUpdateSettingsSummary();
  };

  const _origSelectTableMode = nbFormBuilder.selectTableMode;
  nbFormBuilder.selectTableMode = function(mode, card) {
    if (typeof _origSelectTableMode === 'function') _origSelectTableMode.call(nbFormBuilder, mode, card);
    const pill = document.getElementById('optValTable');
    if (pill) pill.textContent = mode === 'existing' ? '⊞ Use existing' : '✦ Create new';
  };

  const _origSelectPkType = nbFormBuilder.selectPkType;
  nbFormBuilder.selectPkType = function(type, card) {
    if (typeof _origSelectPkType === 'function') _origSelectPkType.call(nbFormBuilder, type, card);
    const pill = document.getElementById('optValPk');
    if (pill) pill.textContent = type === 'uuid' ? 'NuBuilder UUID' : 'Auto-increment INT';
  };

  // ── Load columns from an existing table and populate the canvas ───
  nbFormBuilder.onExistingTableChange = async function(tableName) {
    const tableInput = document.getElementById('builderFormTable');
    if (tableInput) tableInput.value = tableName;

    const statusEl = document.getElementById('existingTableColsStatus');
    if (!tableName) {
      if (statusEl) statusEl.style.display = 'none';
      return;
    }

    if (statusEl) {
      statusEl.style.display = 'block';
      statusEl.style.background = 'color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface))';
      statusEl.style.color = 'var(--text-secondary)';
      statusEl.style.border = '1px solid var(--border-color)';
      statusEl.textContent = '⏳ Loading columns from ' + tableName + '…';
    }

    let cols = [];
    try {
      const res  = await fetch('api/inspector.php?action=columns&table=' + encodeURIComponent(tableName));
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Unknown error');
      cols = data.columns;
    } catch (err) {
      console.error('[nub5] Failed to fetch columns:', err);
      if (statusEl) {
        statusEl.style.background = 'color-mix(in oklch,#ef4444 8%,var(--bg-surface))';
        statusEl.style.color = '#b91c1c';
        statusEl.style.border = '1px solid color-mix(in oklch,#ef4444 30%,transparent)';
        statusEl.textContent = '✗ Could not load columns: ' + err.message;
      }
      return;
    }

    const mapType = t => {
      t = (t || '').toLowerCase();
      if (/tinyint\(1\)/.test(t))                          return 'checkbox';
      if (/int/.test(t))                                    return 'number';
      if (/decimal|float|double|numeric/.test(t))          return 'number';
      if (t === 'date')                                     return 'date';
      if (/datetime|timestamp/.test(t))                    return 'datetime';
      if (/^time/.test(t))                                  return 'time';
      if (/text|blob|mediumtext|longtext/.test(t))         return 'textarea';
      return 'text';
    };

    const makeLabel = name => name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    const skip = new Set(['id','created_at','updated_at','created_by','updated_by','deleted_at']);
    const userCols = cols.filter(c => !skip.has(c.Field));

    if (typeof nbFormBuilder.clearCanvas === 'function') {
      nbFormBuilder.clearCanvas();
    }

    userCols.forEach(col => {
      if (typeof nbFormBuilder.addField === 'function') {
        nbFormBuilder.addField(
          mapType(col.Type),
          { name: col.Field, label: makeLabel(col.Field), col: 6 }
        );
      }
    });

    if (statusEl) {
      statusEl.style.background = 'color-mix(in oklch,#22c55e 8%,var(--bg-surface))';
      statusEl.style.color = '#15803d';
      statusEl.style.border = '1px solid color-mix(in oklch,#22c55e 30%,transparent)';
      statusEl.textContent = '✓ Loaded ' + userCols.length + ' field' + (userCols.length !== 1 ? 's' : '') + ' from ' + tableName;
    }

    const fieldsTab = document.querySelector('.nb-tab[data-panel="panelFields"]');
    if (fieldsTab) fieldsTab.click();
  };

  // ── Group + Tab canvas rendering helpers ─────────────────────────
if (!nbFormBuilder._groupTabPatched) {
  nbFormBuilder._groupTabPatched = true;

  var _origMakeFieldCard = nbFormBuilder._makeFieldCard;
  var _origMakeDefaultField = nbFormBuilder._makeDefaultField;

 nbFormBuilder._makeFieldCard = function(type, label, name, required, extra) {
  var field = (type && typeof type === 'object')
    ? type
    : {
        id: 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
        type: type,
        label: label || (type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Field'),
        name: name || ((type || 'field') + '_' + Math.random().toString(36).slice(2, 6)),
        required: !!required,
        col: (extra && extra.col) ? extra.col : 6
      };

  extra = extra || {};
  if (field.col != null && extra.col == null) extra.col = field.col;
  if (field.placeholder != null && extra.placeholder == null) extra.placeholder = field.placeholder;
  if (field.help_text != null && extra.help_text == null) extra.help_text = field.help_text;
  if (field.default_value != null && extra.default_value == null) extra.default_value = field.default_value;
  if (field.options != null && extra.options == null) extra.options = field.options;
  if (field.multiple != null && extra.multiple == null) extra.multiple = field.multiple;
  if (field.select2 != null && extra.select2 == null) extra.select2 = field.select2;
  if (field.allow_clear != null && extra.allow_clear == null) extra.allow_clear = field.allow_clear;
  if (field.select_type != null && extra.select_type == null) extra.select_type = field.select_type;
  if (field.options_source != null && extra.options_source == null) extra.options_source = field.options_source;

  if (field.button_text != null && extra.button_text == null) extra.button_text = field.button_text;
  if (field.accept != null && extra.accept == null) extra.accept = field.accept;
  if (field.max_files != null && extra.max_files == null) extra.max_files = field.max_files;
  if (field.max_file_size_mb != null && extra.max_file_size_mb == null) extra.max_file_size_mb = field.max_file_size_mb;
  if (field.storage_mode != null && extra.storage_mode == null) extra.storage_mode = field.storage_mode;
  if (field.upload_path != null && extra.upload_path == null) extra.upload_path = field.upload_path;
  if (field.allowed_extensions != null && extra.allowed_extensions == null) extra.allowed_extensions = field.allowed_extensions;
  if (field.preview != null && extra.preview == null) extra.preview = field.preview;

  if (field.canvas_width != null && extra.canvas_width == null) extra.canvas_width = field.canvas_width;
  if (field.canvas_height != null && extra.canvas_height == null) extra.canvas_height = field.canvas_height;
  if (field.background_color != null && extra.background_color == null) extra.background_color = field.background_color;
  if (field.pen_color != null && extra.pen_color == null) extra.pen_color = field.pen_color;
  if (field.pen_width != null && extra.pen_width == null) extra.pen_width = field.pen_width;
  if (field.export_format != null && extra.export_format == null) extra.export_format = field.export_format;
  if (field.clear_button != null && extra.clear_button == null) extra.clear_button = field.clear_button;

  if (field.format_type != null && extra.format_type == null) extra.format_type = field.format_type;
  if (field.decimals != null && extra.decimals == null) extra.decimals = field.decimals;
  if (field.thousand_separator != null && extra.thousand_separator == null) extra.thousand_separator = field.thousand_separator;
  if (field.decimal_separator != null && extra.decimal_separator == null) extra.decimal_separator = field.decimal_separator;
  if (field.prefix != null && extra.prefix == null) extra.prefix = field.prefix;
  if (field.suffix != null && extra.suffix == null) extra.suffix = field.suffix;
  if (field.allow_negative != null && extra.allow_negative == null) extra.allow_negative = field.allow_negative;
  if (field.min_value != null && extra.min_value == null) extra.min_value = field.min_value;
  if (field.max_value != null && extra.max_value == null) extra.max_value = field.max_value;
  if (field.step != null && extra.step == null) extra.step = field.step;

  try {
    console.log('[nb patch] _makeFieldCard start', {
      incomingType: type,
      resolvedType: field.type,
      label: field.label,
      name: field.name,
      field: field
    });
  } catch (e) {}

  var card = typeof _origMakeFieldCard === 'function'
    ? _origMakeFieldCard.call(
        nbFormBuilder,
        field.type,
        field.label,
        field.name,
        !!field.required,
        extra
      )
    : null;

  if (!card) {
    try { console.warn('[nb patch] original returned null', field); } catch (e) {}
    return card;
  }

  card.__nbFieldRef = field;

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function appendAdvancedFieldOptions() {
    var body = card.querySelector('.nb-cfield-body');
    if (!body) return;
    if (body.querySelector('.nb-adv-field-ext')) return;

    var blockHtml = '';

    if (field.type === 'uploadbutton') {
      blockHtml += `
        <div class="nb-adv-field-ext nb-fp-grid" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border-color);grid-column:1/-1;">
          <div class="nb-fp nb-fp-full" style="font-size:10px;font-weight:700;color:var(--text-tertiary);letter-spacing:.05em;text-transform:uppercase;">Upload Options</div>

          <div class="nb-fp">
            <label>Button Text</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="button_text" value="${esc(field.button_text || 'Upload')}">
          </div>

          <div class="nb-fp">
            <label>Accept</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="accept" value="${esc(field.accept || '')}" placeholder=".jpg,.png,.pdf">
          </div>

          <div class="nb-fp">
            <label>Max Files</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="max_files" value="${esc(field.max_files != null ? field.max_files : 1)}" min="1">
          </div>

          <div class="nb-fp">
            <label>Max Size MB</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="max_file_size_mb" value="${esc(field.max_file_size_mb != null ? field.max_file_size_mb : 5)}" min="1">
          </div>

          <div class="nb-fp">
            <label>Storage Mode</label>
            <select class="nu-input nb-adv-prop" data-prop="storage_mode">
              <option value="file" ${field.storage_mode === 'file' ? 'selected' : ''}>File path</option>
              <option value="filename" ${field.storage_mode === 'filename' ? 'selected' : ''}>Filename only</option>
              <option value="json" ${field.storage_mode === 'json' ? 'selected' : ''}>JSON metadata</option>
              <option value="base64" ${field.storage_mode === 'base64' ? 'selected' : ''}>Base64</option>
            </select>
          </div>

          <div class="nb-fp">
            <label>Upload Path</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="upload_path" value="${esc(field.upload_path || 'uploads/')}">
          </div>

          <div class="nb-fp nb-fp-full">
            <label>Allowed Extensions</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="allowed_extensions" value="${esc(field.allowed_extensions || '')}" placeholder="jpg,png,pdf,docx">
          </div>

          <label class="nb-fp-check nb-fp-full">
            <input type="checkbox" class="nb-adv-prop" data-prop="multiple" ${field.multiple ? 'checked' : ''}> Multiple files
          </label>

          <label class="nb-fp-check nb-fp-full">
            <input type="checkbox" class="nb-adv-prop" data-prop="preview" ${field.preview !== false ? 'checked' : ''}> Preview enabled
          </label>
        </div>
      `;
    }

    if (field.type === 'signaturepad' || field.type === 'picturecanvas') {
      blockHtml += `
        <div class="nb-adv-field-ext nb-fp-grid" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border-color);grid-column:1/-1;">
          <div class="nb-fp nb-fp-full" style="font-size:10px;font-weight:700;color:var(--text-tertiary);letter-spacing:.05em;text-transform:uppercase;">Canvas Options</div>

          <div class="nb-fp">
            <label>Canvas Width</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="canvas_width" value="${esc(field.canvas_width || 400)}" min="100">
          </div>

          <div class="nb-fp">
            <label>Canvas Height</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="canvas_height" value="${esc(field.canvas_height || 220)}" min="80">
          </div>

          <div class="nb-fp">
            <label>Background Color</label>
            <input type="color" class="nu-input nb-adv-prop" data-prop="background_color" value="${esc(field.background_color || '#ffffff')}">
          </div>

          <div class="nb-fp">
            <label>Pen Color</label>
            <input type="color" class="nu-input nb-adv-prop" data-prop="pen_color" value="${esc(field.pen_color || '#000000')}">
          </div>

          <div class="nb-fp">
            <label>Pen Width</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="pen_width" value="${esc(field.pen_width || 2)}" min="1" max="20">
          </div>

          <div class="nb-fp">
            <label>Export Format</label>
            <select class="nu-input nb-adv-prop" data-prop="export_format">
              <option value="png" ${field.export_format === 'png' ? 'selected' : ''}>PNG</option>
              <option value="jpeg" ${field.export_format === 'jpeg' ? 'selected' : ''}>JPEG</option>
            </select>
          </div>

          <div class="nb-fp">
            <label>Storage Mode</label>
            <select class="nu-input nb-adv-prop" data-prop="storage_mode">
              <option value="base64" ${field.storage_mode === 'base64' ? 'selected' : ''}>Base64</option>
              <option value="file" ${field.storage_mode === 'file' ? 'selected' : ''}>Saved file</option>
            </select>
          </div>

          <label class="nb-fp-check nb-fp-full">
            <input type="checkbox" class="nb-adv-prop" data-prop="clear_button" ${field.clear_button !== false ? 'checked' : ''}> Show clear button
          </label>
        </div>
      `;
    }

    if (field.type === 'customnumber') {
      blockHtml += `
        <div class="nb-adv-field-ext nb-fp-grid" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border-color);grid-column:1/-1;">
          <div class="nb-fp nb-fp-full" style="font-size:10px;font-weight:700;color:var(--text-tertiary);letter-spacing:.05em;text-transform:uppercase;">Number Format</div>

          <div class="nb-fp">
            <label>Format Type</label>
            <select class="nu-input nb-adv-prop" data-prop="format_type">
              <option value="number" ${field.format_type === 'number' ? 'selected' : ''}>Number</option>
              <option value="integer" ${field.format_type === 'integer' ? 'selected' : ''}>Integer</option>
              <option value="decimal" ${field.format_type === 'decimal' ? 'selected' : ''}>Decimal</option>
              <option value="currency" ${field.format_type === 'currency' ? 'selected' : ''}>Currency</option>
              <option value="percentage" ${field.format_type === 'percentage' ? 'selected' : ''}>Percentage</option>
            </select>
          </div>

          <div class="nb-fp">
            <label>Decimals</label>
            <input type="number" class="nu-input nb-adv-prop" data-prop="decimals" value="${esc(field.decimals != null ? field.decimals : 2)}" min="0" max="10">
          </div>

          <div class="nb-fp">
            <label>Thousand Separator</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="thousand_separator" value="${esc(field.thousand_separator != null ? field.thousand_separator : ',')}" maxlength="1">
          </div>

          <div class="nb-fp">
            <label>Decimal Separator</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="decimal_separator" value="${esc(field.decimal_separator != null ? field.decimal_separator : '.')}" maxlength="1">
          </div>

          <div class="nb-fp">
            <label>Prefix</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="prefix" value="${esc(field.prefix || '')}" placeholder="$">
          </div>

          <div class="nb-fp">
            <label>Suffix</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="suffix" value="${esc(field.suffix || '')}" placeholder="%">
          </div>

          <div class="nb-fp">
            <label>Min Value</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="min_value" value="${esc(field.min_value != null ? field.min_value : '')}">
          </div>

          <div class="nb-fp">
            <label>Max Value</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="max_value" value="${esc(field.max_value != null ? field.max_value : '')}">
          </div>

          <div class="nb-fp">
            <label>Step</label>
            <input type="text" class="nu-input nb-adv-prop" data-prop="step" value="${esc(field.step != null ? field.step : '1')}">
          </div>

          <label class="nb-fp-check nb-fp-full">
            <input type="checkbox" class="nb-adv-prop" data-prop="allow_negative" ${field.allow_negative !== false ? 'checked' : ''}> Allow negative
          </label>
        </div>
      `;
    }

    if (!blockHtml) return;

    var grid = body.querySelector('.nb-fp-grid');
    if (!grid) return;

    var wrap = document.createElement('div');
    wrap.innerHTML = blockHtml;
    while (wrap.firstChild) grid.appendChild(wrap.firstChild);

    body.querySelectorAll('.nb-adv-prop').forEach(function(el) {
      var prop = el.getAttribute('data-prop');
      if (!prop) return;

      var update = function() {
        var val;
        if (el.type === 'checkbox') {
          val = !!el.checked;
        } else if (el.type === 'number') {
          val = el.value === '' ? '' : Number(el.value);
        } else {
          val = el.value;
        }

        field[prop] = val;
        card.__nbFieldRef = field;

        var labelNode = card.querySelector('.nb-cfield-label');
        if (labelNode) labelNode.textContent = field.label || field.name || field.type || 'Field';

        try {
          console.log('[nb patch] field adv prop changed', {
            type: field.type,
            name: field.name,
            prop: prop,
            value: val,
            field: field
          });
        } catch (e) {}
      };

      el.addEventListener('input', update);
      el.addEventListener('change', update);
    });
  }

  appendAdvancedFieldOptions();

  if (field.type === 'group') {
    var oldGroupBody = card.querySelector('.nb-cfield-group-body');
    if (oldGroupBody) oldGroupBody.remove();

    var groupBody = document.createElement('div');
    groupBody.className = 'nb-cfield-group-body';
    groupBody.dataset.groupId = field.id || '';

    function renderGroupChildren() {
      groupBody.innerHTML = '';
      var children = Array.isArray(field.children) ? field.children : [];
      if (!children.length) {
        groupBody.innerHTML = '<div style="color:var(--text-tertiary);font-size:11px;text-align:center;padding:10px;">Drop fields here</div>';
        return;
      }
      children.forEach(function(child) {
        var cc = nbFormBuilder._makeFieldCard(child);
        if (cc) groupBody.appendChild(cc);
      });
    }

    renderGroupChildren();

    groupBody.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.stopPropagation();
      groupBody.classList.add('drag-col-over');
    });

    groupBody.addEventListener('dragleave', function(e) {
      if (!groupBody.contains(e.relatedTarget)) groupBody.classList.remove('drag-col-over');
    });

    groupBody.addEventListener('drop', function(e) {
      e.preventDefault();
      e.stopPropagation();
      groupBody.classList.remove('drag-col-over');

      var droppedType = e.dataTransfer.getData('nb-field-type');
      if (!droppedType || droppedType === 'group' || droppedType === 'tab') return;

      if (!Array.isArray(field.children)) field.children = [];
      field.children.push(nbFormBuilder._makeDefaultField(droppedType));
      renderGroupChildren();

      try { console.log('[nb patch] group drop', field); } catch (err) {}
    });

    card.appendChild(groupBody);
  }

  if (field.type === 'tab') {
    var oldTabNav = card.querySelector('.nb-cfield-tab-nav');
    if (oldTabNav) oldTabNav.remove();

    var oldTabPanes = card.querySelector('.nb-cfield-tab-panes');
    if (oldTabPanes) oldTabPanes.remove();

    if (!Array.isArray(field.tabs) || !field.tabs.length) {
      field.tabs = [{ label: 'Tab 1', rows: [{ fields: [] }] }];
    }

    field.tabs.forEach(function(tab) {
      if (!tab.label && tab.name) tab.label = tab.name;
      if (!Array.isArray(tab.rows)) {
        if (Array.isArray(tab.children)) {
          tab.rows = [{ fields: tab.children }];
        } else {
          tab.rows = [{ fields: [] }];
        }
      }
    });

    var tabNav = document.createElement('div');
    tabNav.className = 'nb-cfield-tab-nav';

    var tabPanes = document.createElement('div');
    tabPanes.className = 'nb-cfield-tab-panes';

    function renderTabs() {
      tabNav.innerHTML = '';
      tabPanes.innerHTML = '';

      field.tabs.forEach(function(tab, idx) {
        var navBtn = document.createElement('button');
        navBtn.type = 'button';
        navBtn.className = 'nb-cfield-tab-nav-item' + (idx === 0 ? ' active' : '');
        navBtn.textContent = tab.label || ('Tab ' + (idx + 1));
        navBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          tabNav.querySelectorAll('.nb-cfield-tab-nav-item').forEach(function(b) {
            b.classList.remove('active');
          });
          tabPanes.querySelectorAll('.nb-cfield-tab-panel').forEach(function(p) {
            p.classList.remove('active');
          });
          navBtn.classList.add('active');
          var panels = tabPanes.querySelectorAll('.nb-cfield-tab-panel');
          if (panels[idx]) panels[idx].classList.add('active');
        });
        tabNav.appendChild(navBtn);

        var panel = document.createElement('div');
        panel.className = 'nb-cfield-tab-panel' + (idx === 0 ? ' active' : '');

        var rows = Array.isArray(tab.rows) ? tab.rows : [];
        if (!rows.length) rows = tab.rows = [{ fields: [] }];
        var row = rows[0];
        if (!Array.isArray(row.fields)) row.fields = [];

        function renderPanelFields() {
          panel.innerHTML = '';
          if (!row.fields.length) {
            panel.innerHTML = '<div style="color:var(--text-tertiary);font-size:11px;text-align:center;padding:10px;">Drop fields here</div>';
            return;
          }
          row.fields.forEach(function(child) {
            var cc = nbFormBuilder._makeFieldCard(child);
            if (cc) panel.appendChild(cc);
          });
        }

        renderPanelFields();

        (function(panelEl, tabIdx, rowRef) {
          panelEl.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            panelEl.classList.add('drag-col-over');
          });

          panelEl.addEventListener('dragleave', function(e) {
            if (!panelEl.contains(e.relatedTarget)) panelEl.classList.remove('drag-col-over');
          });

          panelEl.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            panelEl.classList.remove('drag-col-over');

            var droppedType = e.dataTransfer.getData('nb-field-type');
            if (!droppedType || droppedType === 'group' || droppedType === 'tab') return;

            if (!Array.isArray(rowRef.fields)) rowRef.fields = [];
            rowRef.fields.push(nbFormBuilder._makeDefaultField(droppedType));
            renderPanelFields();

            try {
              console.log('[nb patch] tab drop', {
                tabIndex: tabIdx,
                field: field
              });
            } catch (err) {}
          });
        })(panel, idx, row);

        tabPanes.appendChild(panel);
      });

      var addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'nb-cfield-tab-add-btn';
      addBtn.textContent = '+ Tab';
      addBtn.addEventListener('click', function(e) {
        e.stopPropagation();

        field.tabs.push({
          label: 'Tab ' + (field.tabs.length + 1),
          rows: [{ fields: [] }]
        });

        var parent = card.parentElement;
        var next = card.nextSibling;
        if (parent) {
          parent.removeChild(card);
          var nc = nbFormBuilder._makeFieldCard(field);
          if (nc) parent.insertBefore(nc, next);
        }

        try { console.log('[nb patch] add tab', field); } catch (err) {}
      });

      tabNav.appendChild(addBtn);
    }

    renderTabs();
    card.appendChild(tabNav);
    card.appendChild(tabPanes);
  }

  return card;
};
nbFormBuilder._makeDefaultField = function(type) {
  if (typeof _origMakeDefaultField === 'function') {
    var made = _origMakeDefaultField.call(nbFormBuilder, type);
    if (made && typeof made === 'object') {
      if (type === 'uploadbutton') {
        made.type = 'uploadbutton';
        made.label = made.label || 'Upload Button';
        made.name = made.name || ('upload_' + Math.random().toString(36).slice(2, 6));
        made.col = made.col || 6;
        made.button_text = made.button_text || 'Upload';
        made.accept = made.accept || '';
        made.multiple = !!made.multiple;
        made.max_files = made.max_files != null ? made.max_files : 1;
        made.max_file_size_mb = made.max_file_size_mb != null ? made.max_file_size_mb : 5;
        made.storage_mode = made.storage_mode || 'file';
        made.upload_path = made.upload_path || 'uploads/';
        made.allowed_extensions = made.allowed_extensions || '';
        made.preview = made.preview !== false;
        made.hide_in_grid = made.hide_in_grid !== undefined ? made.hide_in_grid : true;
        made.required = !!made.required;
      } else if (type === 'signaturepad' || type === 'picturecanvas') {
        made.type = 'signaturepad';
        made.label = made.label || 'Signature Pad';
        made.name = made.name || ('signature_' + Math.random().toString(36).slice(2, 6));
        made.hide_in_grid = made.hide_in_grid !== undefined ? made.hide_in_grid : true;
        made.col = made.col || 6;
        made.canvas_width = made.canvas_width || 400;
        made.canvas_height = made.canvas_height || 220;
        made.background_color = made.background_color || '#ffffff';
        made.pen_color = made.pen_color || '#000000';
        made.pen_width = made.pen_width || 2;
        made.export_format = made.export_format || 'png';
        made.storage_mode = made.storage_mode || 'base64';
        made.clear_button = made.clear_button !== false;
        made.required = !!made.required;
      } else if (type === 'customnumber') {
        made.type = 'customnumber';
        made.label = made.label || 'Custom Number';
        made.name = made.name || ('number_' + Math.random().toString(36).slice(2, 6));
        made.col = made.col || 6;
        made.format_type = made.format_type || 'number';
        made.decimals = made.decimals != null ? made.decimals : 2;
        made.thousand_separator = made.thousand_separator != null ? made.thousand_separator : ',';
        made.decimal_separator = made.decimal_separator != null ? made.decimal_separator : '.';
        made.prefix = made.prefix || '';
        made.suffix = made.suffix || '';
        made.allow_negative = made.allow_negative !== false;
        made.min_value = made.min_value != null ? made.min_value : '';
        made.max_value = made.max_value != null ? made.max_value : '';
        made.step = made.step != null ? made.step : '1';
        made.hide_in_grid = made.hide_in_grid !== undefined ? made.hide_in_grid : false;
        made.required = !!made.required;
      }
      return made;
    }
  }

  var base = {
    id: 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
    type: type,
    label: type.charAt(0).toUpperCase() + type.slice(1) + ' Field',
    name: type + '_' + Math.random().toString(36).slice(2, 6),
    col: 6,
    required: false,
    help_text: '',
    placeholder: '',
    default_value: ''
  };

  if (type === 'uploadbutton') {
    base.type = 'uploadbutton';
    base.label = 'Upload Button';
    base.name = 'upload_' + Math.random().toString(36).slice(2, 6);
    base.button_text = 'Upload';
    base.accept = '';
    base.multiple = false;
    base.max_files = 1;
    base.max_file_size_mb = 5;
    base.storage_mode = 'file';
    base.upload_path = 'uploads/';
    base.allowed_extensions = '';
    base.preview = true;
    base.hide_in_grid = true;
    return base;
  }

  if (type === 'signaturepad' || type === 'picturecanvas') {
    base.type = 'signaturepad';
    base.label = 'Signature Pad';
    base.name = 'signature_' + Math.random().toString(36).slice(2, 6);
    base.hide_in_grid = true;
    base.canvas_width = 400;
    base.canvas_height = 220;
    base.background_color = '#ffffff';
    base.pen_color = '#000000';
    base.pen_width = 2;
    base.export_format = 'png';
    base.storage_mode = 'base64';
    base.clear_button = true;
    return base;
  }

  if (type === 'customnumber') {
    base.type = 'customnumber';
    base.label = 'Custom Number';
    base.name = 'number_' + Math.random().toString(36).slice(2, 6);
    base.format_type = 'number';
    base.decimals = 2;
    base.thousand_separator = ',';
    base.decimal_separator = '.';
    base.prefix = '';
    base.suffix = '';
    base.allow_negative = true;
    base.min_value = '';
    base.max_value = '';
    base.step = '1';
    base.hide_in_grid = false;
    return base;
  }

  return base;
};
}

} // end _nbFormsModuleInit guard

// ── open/edit patches run EVERY module load (idempotent via _nbAcePatchVersion) ──
(function _applyNbAcePatches() {
  var STAMP = '_nbAcePatchV2';

  // ── open: clear Ace on new form ──────────────────────────────────
  if (!nbFormBuilder.open[STAMP]) {
    var _rawOpen = nbFormBuilder.open;
    nbFormBuilder.open = function() {
      if (typeof _rawOpen === 'function') _rawOpen.call(nbFormBuilder);
      requestAnimationFrame(function() {
        ['aceCustomJs','aceJsBeforeSave','aceJsAfterSave','aceCustomPhp','aceCustomCss'].forEach(function(id) {
          if (window.nbAce) nbAce.setValue(id, '');
        });
        if (window.nbAce) nbAce.resizeAll();
      });
    };
    nbFormBuilder.open[STAMP] = true;
  }

  // ── edit: restore Ace values after open()'s RAF clear ────────────
  if (!nbFormBuilder.edit[STAMP]) {
    var _rawEdit = nbFormBuilder.edit;
    nbFormBuilder.edit = async function(formId) {
      if (typeof _rawEdit === 'function') await _rawEdit.call(nbFormBuilder, formId);
      var aceMap = {
        aceCustomJs:     'formCustomJs',
        aceJsBeforeSave: 'formJsBeforeSave',
        aceJsAfterSave:  'formJsAfterSave',
        aceCustomPhp:    'formCustomPhp',
        aceCustomCss:    'formCustomCss',
        aceBrowsePhp:    'formBrowsePhp',
      };
      var snapshot = {};
      Object.keys(aceMap).forEach(function(aceId) {
        var hidden = document.getElementById(aceMap[aceId]);
        snapshot[aceId] = hidden ? (hidden.value || '') : '';
      });
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          Object.keys(snapshot).forEach(function(aceId) {
            if (window.nbAce) nbAce.setValue(aceId, snapshot[aceId]);
          });
          if (window.nbAce) nbAce.resizeAll();
        });
      });
    };
    nbFormBuilder.edit[STAMP] = true;
  }
})();
</script>
