<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db = NuDatabase::getInstance();
$allRoles = $db->fetchAll("SELECT role_code, role_name FROM nu_roles ORDER BY role_name");
$allReports = $db->fetchAll("SELECT report_id, report_code, report_name FROM nu_reports WHERE report_active = 1 ORDER BY report_name");

$currentUser = $auth->getCurrentUser();
$userRole = strtolower($currentUser['usr_role'] ?? '');
$isAdmin = ($userRole === 'globeadmin' || $userRole === 'admin');
?>

<div id="dash-app">

  <!-- ── TOOLBAR ─────────────────────────────────────────────────────── -->
  <div class="dash-toolbar">
    <div class="dash-toolbar-left">
      <h2 class="dash-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
        Report Dashboards
      </h2>
      <span id="dashBadge" class="dash-badge">0</span>
    </div>
    <div class="dash-toolbar-right">
      <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="dashLoadList()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Refresh
      </button>
      <?php if ($isAdmin): ?>
      <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="dashOpenBuilder(null)">
        + New Dashboard
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- LIST VIEW -->
  <div id="dashListView">
    <div id="dashTable" class="dash-table-wrap"></div>
  </div>

  <!-- VIEWER VIEW (Run Mode) -->
  <div id="dashViewer" style="display:none;">
    <div class="dash-builder-header">
      <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="dashShowList()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back
      </button>
      <h3 id="dashViewerTitle" style="margin:0;font-size:var(--text-base);font-weight:600;">Report Viewer</h3>
    </div>

    <!-- Interactive Filter Panel (Matches image.png structure) -->
    <div id="dashFilterPanel" class="dash-filter-panel-bar">
      <!-- Mapped dynamic filter inputs will render here -->
      <div id="dashDynamicFilters" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;"></div>

      <!-- Report Selector -->
      <div class="dash-filter-item">
        <select class="nu-input dash-select-report" id="dashSelectReport" onchange="dashOnReportSelected()">
          <option value="">Select Report</option>
        </select>
      </div>

      <!-- Action Button -->
      <button class="nu-btn nu-btn-primary" id="dashFilterBtn" onclick="dashRunFilterReport()">
        Filter Report
      </button>
    </div>

    <!-- Status / Header Bar -->
    <div id="dashReportStatusHeader" class="dash-report-status-header" style="display:none;">
      <span class="dash-status-dot"></span>
      <span id="dashStatusReportName" style="font-weight:600;">Revenue Report</span>
    </div>

    <!-- Output Pane -->
    <div id="dashViewerOutput" class="dash-viewer-output-pane">
      <div class="dash-empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-faint);"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        <p>Configure filters, choose a report, and click <strong>Filter Report</strong> to view results.</p>
      </div>
    </div>
  </div>

  <!-- BUILDER VIEW (Configure Mode) -->
  <div id="dashBuilderView" style="display:none;">
    <div class="dash-builder-header">
      <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="dashShowList()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back
      </button>
      <h3 id="dashBuilderTitle" style="margin:0;font-size:var(--text-base);font-weight:600;">New Dashboard</h3>
      <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="dashSave()">Save Dashboard</button>
    </div>

    <div class="dash-builder-body">
      <!-- LEFT: config -->
      <div class="dash-builder-config">
        <input type="hidden" id="dashId">

        <div class="dash-field-group">
          <label>Dashboard Name <span style="color:var(--color-error)">*</span></label>
          <input type="text" class="nu-input" id="dashName" placeholder="Executive KPI Dashboard">
        </div>

        <div class="dash-field-row">
          <div class="dash-field-group">
            <label>Dashboard Code</label>
            <input type="text" class="nu-input" id="dashCode" placeholder="auto">
          </div>
          <div class="dash-field-group">
            <label>Active</label>
            <div style="padding-top:8px;">
              <input type="checkbox" id="dashActive" checked style="width:18px;height:18px;">
            </div>
          </div>
        </div>

        <div class="dash-field-group">
          <label>Dashboard Description</label>
          <input type="text" class="nu-input" id="dashDescription" placeholder="Optional description of this dashboard">
        </div>

        <!-- Dashboard Access Roles -->
        <div class="dash-field-group">
          <label>Allowed User Roles <span style="font-weight:normal;color:var(--color-text-faint);">(None = All Roles)</span></label>
          <div class="dash-roles-checklist" id="dashRolesChecklist">
            <?php foreach ($allRoles as $role): ?>
              <label class="dash-role-checkbox-label">
                <input type="checkbox" data-role-code="<?php echo htmlspecialchars($role['role_code']); ?>">
                <?php echo htmlspecialchars($role['role_name']); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Unified Filters -->
        <div class="dash-field-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <label>Unified Filters <span style="color:var(--color-text-faint);font-weight:400;">(optional)</span></label>
            <button class="nu-btn nu-btn-ghost" style="font-size:11px;padding:2px 6px;" onclick="dashAddFilterRow()">+ Add Filter</button>
          </div>
          <div id="dashFiltersConfigList" style="display:flex;flex-direction:column;gap:10px;"></div>
        </div>

      </div>

      <!-- RIGHT: reports selection -->
      <div class="dash-builder-reports-pane">
        <div class="dash-preview-header">
          <span style="font-size:var(--text-sm);font-weight:600;">Reports in Dashboard</span>
          <span class="dash-hint">Configure which reports are included and their individual access controls</span>
        </div>
        <div style="padding:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <label style="font-size:var(--text-xs);font-weight:600;color:var(--color-text-muted);text-transform:uppercase;">Select Reports to Include</label>
          </div>
          <div id="dashReportsConfigList" class="dash-reports-config-container">
            <?php if (empty($allReports)): ?>
              <p style="color:var(--color-text-faint);font-size:13px;padding:20px 0;">No active reports found. Please create a report first.</p>
            <?php else: ?>
              <?php foreach ($allReports as $rep): ?>
                <div class="dash-report-config-card" data-report-id="<?php echo $rep['report_id']; ?>">
                  <div class="dash-report-config-card-header">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                      <input type="checkbox" class="dash-report-include-cb" onchange="dashToggleReportConfig(this)">
                      <?php echo htmlspecialchars($rep['report_name']); ?>
                    </label>
                    <span class="dash-hint" style="font-family:monospace;"><?php echo htmlspecialchars($rep['report_code']); ?></span>
                  </div>
                  <div class="dash-report-config-card-roles" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid var(--color-divider);">
                    <label style="font-size:11px;font-weight:600;color:var(--color-text-muted);display:block;margin-bottom:4px;">RESTRICTED TO ROLES (Leave empty for inherited access):</label>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                      <?php foreach ($allRoles as $role): ?>
                        <label style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:normal;">
                          <input type="checkbox" class="dash-report-role-cb" data-role-code="<?php echo htmlspecialchars($role['role_code']); ?>">
                          <?php echo htmlspecialchars($role['role_name']); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
#dash-app {
  display:flex;
  flex-direction:column;
  height:100%;
  font-family:var(--font-body,sans-serif);
}
.dash-toolbar {
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:12px 20px;
  border-bottom:1px solid var(--color-border);
  background:var(--color-surface);
  flex-shrink:0;
}
.dash-toolbar-left { display:flex;align-items:center;gap:10px; }
.dash-toolbar-right { display:flex;align-items:center;gap:8px; }
.dash-title {
  margin:0;
  font-size:var(--text-base);
  font-weight:600;
  display:flex;
  align-items:center;
  gap:6px;
}
.dash-badge {
  background:var(--color-primary);
  color:var(--color-text-inverse,#fff);
  border-radius:var(--radius-full);
  font-size:11px;
  padding:1px 7px;
  font-weight:600;
}
#dashListView { flex:1;overflow:auto;padding:16px 20px; }
.dash-table-wrap {
  background:var(--color-surface);
  border:1px solid var(--color-border);
  border-radius:var(--radius-lg);
  overflow:hidden;
}
.dash-list-table { width:100%;border-collapse:collapse; }
.dash-list-table th {
  background:var(--color-bg);
  padding:10px 16px;
  text-align:left;
  font-size:var(--text-xs);
  font-weight:600;
  color:var(--color-text-muted);
  text-transform:uppercase;
  letter-spacing:0.04em;
  border-bottom:1px solid var(--color-border);
}
.dash-list-table td {
  padding:11px 16px;
  font-size:var(--text-sm);
  border-bottom:1px solid var(--color-divider);
  vertical-align:middle;
}
.dash-list-table tr:last-child td { border-bottom:none; }
.dash-list-table tr:hover td { background:var(--color-surface-offset); }

/* Viewer Filter Bar Styling - Matches image.png perfectly */
.dash-filter-panel-bar {
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
  padding:14px 20px;
  background:var(--color-bg);
  border-bottom:1px solid var(--color-border);
  flex-shrink:0;
}
.dash-filter-item {
  display:flex;
  align-items:center;
}
.dash-filter-item .nu-input {
  height:40px;
  padding:0 14px;
  font-size:14px;
  font-weight:500;
  border:1px solid #ced4da;
  border-radius:6px;
  background:#fff;
  min-width:140px;
  transition:border-color 0.15s;
}
.dash-filter-item .nu-input:focus {
  border-color:#3b82f6;
  outline:none;
}
.dash-select-report {
  min-width:180px;
}

/* Status indicator */
.dash-report-status-header {
  display:flex;
  align-items:center;
  gap:8px;
  padding:8px 24px;
  background:var(--color-surface-offset);
  border-bottom:1px solid var(--color-border);
  font-size:12px;
  color:var(--color-text-muted);
}
.dash-status-dot {
  width:8px;
  height:8px;
  border-radius:50%;
  background:#10b981;
}

#dashBuilderView, #dashViewer {
  display:flex;
  flex-direction:column;
  flex:1;
  overflow:hidden;
}
.dash-builder-header {
  display:flex;
  align-items:center;
  gap:12px;
  padding:10px 16px;
  border-bottom:1px solid var(--color-border);
  background:var(--color-surface);
  flex-shrink:0;
}
.dash-builder-header h3 { flex:1; }
.dash-builder-body {
  display:grid;
  grid-template-columns:440px 1fr;
  flex:1;
  overflow:hidden;
}
.dash-builder-config {
  border-right:1px solid var(--color-border);
  overflow-y:auto;
  padding:18px;
  display:flex;
  flex-direction:column;
  gap:16px;
}
.dash-builder-reports-pane {
  display:flex;
  flex-direction:column;
  overflow-y:auto;
  background:var(--color-bg);
}
.dash-preview-header {
  display:flex;
  flex-direction:column;
  padding:12px 18px;
  border-bottom:1px solid var(--color-border);
  background:var(--color-surface);
  flex-shrink:0;
}
.dash-field-group { display:flex;flex-direction:column;gap:5px; }
.dash-field-group > label {
  font-size:var(--text-xs);
  font-weight:600;
  color:var(--color-text-muted);
  text-transform:uppercase;
  letter-spacing:0.04em;
}
.dash-field-row { display:grid;grid-template-columns:1fr 80px;gap:12px; }
.dash-hint { font-size:var(--text-xs);color:var(--color-text-faint);margin-top:3px; }

.dash-roles-checklist {
  display:flex;
  flex-direction:column;
  gap:6px;
  background:#fff;
  border:1px solid var(--color-border);
  border-radius:6px;
  padding:10px;
  max-height:140px;
  overflow-y:auto;
}
.dash-role-checkbox-label {
  display:flex;
  align-items:center;
  gap:6px;
  font-size:13px;
  font-weight:normal;
  cursor:pointer;
}

/* Config rows */
.dash-filter-config-row {
  background:#fff;
  border:1px solid var(--color-border);
  border-radius:8px;
  padding:10px;
  display:flex;
  flex-direction:column;
  gap:8px;
}
.dash-filter-config-row-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.dash-remove-btn {
  color:var(--color-text-faint);
  cursor:pointer;
  padding:4px;
  background:none;
  border:none;
  line-height:1;
}
.dash-remove-btn:hover { color:var(--color-error); }

/* Reports checklist in builder */
.dash-reports-config-container {
  display:flex;
  flex-direction:column;
  gap:10px;
}
.dash-report-config-card {
  background:#fff;
  border:1px solid var(--color-border);
  border-radius:8px;
  padding:12px;
  transition:all 150ms;
}
.dash-report-config-card-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.dash-viewer-output-pane {
  flex:1;
  overflow:auto;
  padding:20px;
  background:#f9fafb;
}

.dash-empty-state {
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:12px;
  padding:80px 20px;
  color:var(--color-text-muted);
  text-align:center;
}
.dash-empty-state p { font-size:var(--text-sm);max-width:320px; }

.dash-row-actions { display:flex;gap:6px;opacity:0;transition:opacity 150ms; }
.dash-list-table tr:hover .dash-row-actions { opacity:1; }

.dash-output-wrap {
  background:#fff;
  border:1px solid var(--color-border);
  border-radius:10px;
  overflow:auto;
}
</style>

<script>
(function () {

  // Guard: tear down previous instance before attaching new one
  if (window.Dash && typeof window.Dash._destroy === 'function') {
    window.Dash._destroy();
  }

  // ── toast helper ────────────────────────────────────────────────────
  function toast(msg, type) {
    type = type || 'info';
    if (window.NuApp && typeof NuApp.toast === 'function') { NuApp.toast(msg, type); return; }
    if (typeof nuToast === 'function') { nuToast(msg, type); return; }
    console.log('[DASH]', type, msg);
  }

  var API = 'api/report_dashboards.php';

  // State variables
  var _dashboards      = [];
  var _activeDashboard = null;
  var _currentFilters  = [];
  var _reportsInDash   = [];

  // Defer run until SPA layout is ready
  setTimeout(function () {
    dashLoadList();
  }, 0);

  // ── list all dashboards ─────────────────────────────────────────────
  window.dashLoadList = async function () {
    try {
      var res = await fetch(API + '?action=list', { credentials: 'same-origin' });
      var j   = await res.json();
      var rows = j.data || [];
      _dashboards = rows;

      document.getElementById('dashBadge').textContent = rows.length;

      var tbody = rows.length
        ? rows.map(function (r) {
            return '<tr>' +
              '<td><code style="background:var(--color-surface-offset);padding:2px 6px;border-radius:var(--radius-sm);font-size:12px;">' + esc(r.dashboard_code) + '</code></td>' +
              '<td style="font-weight:500;">' + esc(r.dashboard_name) + '</td>' +
              '<td style="color:var(--color-text-muted);font-size:13px;">' + esc(r.dashboard_description || '—') + '</td>' +
              '<td>' + (r.dashboard_active == 1 ? '<span class="nu-status nu-status-active">Active</span>' : '<span class="nu-status">Inactive</span>') + '</td>' +
              '<td><div class="dash-row-actions">' +
                '<button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="dashOpenViewer(' + r.dashboard_id + ')">&#9654; View Dashboard</button>' +
                <?php if ($isAdmin): ?>
                '<button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="dashOpenBuilder(' + r.dashboard_id + ')">Edit</button>' +
                '<button class="nu-btn nu-btn-ghost nu-btn-sm" style="color:var(--color-error);" onclick="dashDelete(' + r.dashboard_id + ')">Del</button>' +
                <?php endif; ?>
              '</div></td>' +
            '</tr>';
          }).join('')
        : '<tr><td colspan="5" style="padding:48px;text-align:center;color:var(--color-text-muted);">No report dashboards defined yet.</td></tr>';

      document.getElementById('dashTable').innerHTML =
        '<table class="dash-list-table"><thead><tr>' +
        '<th>Code</th><th>Name</th><th>Description</th><th>Status</th><th>Actions</th>' +
        '</tr></thead><tbody>' + tbody + '</tbody></table>';
    } catch (e) {
      document.getElementById('dashTable').innerHTML =
        '<p style="padding:20px;color:var(--color-error);">Error loading dashboards: ' + e.message + '</p>';
    }
  };

  // ── navigation ───────────────────────────────────────────────────
  window.dashShowList = function () {
    document.getElementById('dashListView').style.display    = '';
    document.getElementById('dashBuilderView').style.display = 'none';
    document.getElementById('dashViewer').style.display      = 'none';
    dashLoadList();
  };

  // ── open dashboard viewer (run mode) ──────────────────────────────────
  window.dashOpenViewer = async function (id) {
    try {
      var res = await fetch(API + '?action=get&id=' + id, { credentials: 'same-origin' });
      var j   = await res.json();
      if (!j.success) throw new Error(j.error);

      var r = j.data;
      _activeDashboard = r;
      _currentFilters  = r.dashboard_filters || [];
      _reportsInDash   = r.dashboard_reports || [];

      // Set viewer headers
      document.getElementById('dashViewerTitle').textContent = r.dashboard_name;
      document.getElementById('dashViewerOutput').innerHTML =
        '<div class="dash-empty-state"><p>Choose a report and click <strong>Filter Report</strong> to render.</p></div>';
      document.getElementById('dashReportStatusHeader').style.display = 'none';

      // Render Dynamic Filters
      var filterWrap = document.getElementById('dashDynamicFilters');
      filterWrap.innerHTML = '';

      // Mapped array of promises to dynamically load lookup filters
      var promises = _currentFilters.map(async function (f) {
        var wrap = document.createElement('div');
        wrap.className = 'dash-filter-item';

        if (f.type === 'month') {
          var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
          // Render month selector with exact placeholder from image (or preselect July for match)
          var currentMonth = new Date().toLocaleString('en-US', { month: 'long' });
          var monthOpts = months.map(function (m) {
            return '<option value="' + m + '"' + (m === 'July' ? ' selected' : '') + '>' + m + '</option>';
          }).join('');
          wrap.innerHTML = '<select class="nu-input" id="df_' + esc(f.field) + '">' +
            '<option value="">' + esc(f.label || 'Select Month') + '</option>' +
            monthOpts + '</select>';
        } else if (f.type === 'year') {
          var years = [];
          var cy = new Date().getFullYear();
          for (var y = cy + 2; y >= cy - 5; y--) { years.push(y); }
          var yearOpts = years.map(function (yr) {
            return '<option value="' + yr + '">' + yr + '</option>';
          }).join('');
          wrap.innerHTML = '<select class="nu-input" id="df_' + esc(f.field) + '">' +
            '<option value="">' + esc(f.label || 'Select Year') + '</option>' +
            yearOpts + '</select>';
        } else if (f.type === 'lookup') {
          wrap.innerHTML = '<select class="nu-input" id="df_' + esc(f.field) + '"><option value="">' + esc(f.label || 'Select Options') + ' (Loading…)</option></select>';
          // Load options from dynamic lookup API
          setTimeout(async function () {
            try {
              var lres = await fetch(API + '?action=lookup_options&table=' + encodeURIComponent(f.lookup_table) + '&val_col=' + encodeURIComponent(f.lookup_val_col) + '&lbl_col=' + encodeURIComponent(f.lookup_lbl_col), { credentials: 'same-origin' });
              var lj   = await lres.json();
              if (lj.success) {
                var sel = document.getElementById('df_' + f.field);
                if (sel) {
                  sel.innerHTML = '<option value="">' + esc(f.label || 'Select Options') + '</option>' +
                    (lj.data || []).map(function (opt) {
                      return '<option value="' + esc(opt.value) + '">' + esc(opt.label) + '</option>';
                    }).join('');
                }
              }
            } catch (e) {
               console.error('[DASH] lookup fetch failed:', e);
            }
          }, 0);
        } else {
          // Standard text
          wrap.innerHTML = '<input type="text" class="nu-input" id="df_' + esc(f.field) + '" placeholder="' + esc(f.label || 'Enter text') + '">';
        }

        filterWrap.appendChild(wrap);
      });

      await Promise.all(promises);

      // Render Report Selector dropdown
      var rSel = document.getElementById('dashSelectReport');
      rSel.innerHTML = '<option value="">Select Report</option>' +
        _reportsInDash.map(function (rep) {
          return '<option value="' + rep.report_id + '">' + esc(rep.report_name) + '</option>';
        }).join('');

      // Show View
      document.getElementById('dashListView').style.display    = 'none';
      document.getElementById('dashBuilderView').style.display = 'none';
      document.getElementById('dashViewer').style.display      = '';

    } catch (e) {
      toast('Failed to load Dashboard: ' + e.message, 'error');
    }
  };

  // ── when a report is selected in the viewer ──────────────────────────
  window.dashOnReportSelected = function () {
    var rSel = document.getElementById('dashSelectReport');
    var val  = rSel.value;
    if (!val) {
      document.getElementById('dashReportStatusHeader').style.display = 'none';
      return;
    }
    var selectedText = rSel.options[rSel.selectedIndex].text;
    document.getElementById('dashStatusReportName').textContent = selectedText;
    document.getElementById('dashReportStatusHeader').style.display = '';
  };

  // ── execute report filtering (run view) ──────────────────────────────
  window.dashRunFilterReport = async function () {
    var rSel     = document.getElementById('dashSelectReport');
    var reportId = rSel.value;
    if (!reportId) {
       toast('Please select a report first', 'warning');
       return;
    }

    var outPane = document.getElementById('dashViewerOutput');
    outPane.innerHTML = '<div class="dash-empty-state"><p>Loading results…</p></div>';

    // Construct request query params
    var params = new URLSearchParams({
      action:       'run_report',
      dashboard_id: _activeDashboard.dashboard_id,
      report_id:    reportId
    });

    // Append unified filter input values
    _currentFilters.forEach(function (f) {
      var inp = document.getElementById('df_' + f.field);
      if (inp && inp.value.trim() !== '') {
        params.append(f.field, inp.value.trim());
      }
    });

    try {
      var res = await fetch(API + '?' + params.toString(), { credentials: 'same-origin' });
      var j   = await res.json();
      if (!j.success) throw new Error(j.error);

      // Render table
      var data = j.data || [];
      var cols = j.columns || [];

      if (!data.length) {
        outPane.innerHTML = '<div class="dash-empty-state"><p>No rows returned for the selected filters.</p></div>';
        return;
      }

      var th  = cols.map(function (c) { return '<th>' + esc(c.label || c.field) + '</th>'; }).join('');
      var trs = data.map(function (row) {
        return '<tr>' + cols.map(function (c) {
          var val = row[c.field];
          return '<td>' + esc(val !== null && val !== undefined ? val : '') + '</td>';
        }).join('') + '</tr>';
      }).join('');

      outPane.innerHTML = '<div class="dash-output-wrap">' +
        '<table class="nu-table"><thead><tr>' + th + '</tr></thead><tbody>' + trs + '</tbody></table>' +
        '</div>';

    } catch (e) {
      outPane.innerHTML = '<p style="color:var(--color-error);padding:16px;">Error: ' + esc(e.message) + '</p>';
    }
  };

  // ── open builder (new / edit dashboard) ─────────────────────────────────
  window.dashOpenBuilder = async function (id) {
    dashResetBuilder();
    if (id) {
      try {
        var res = await fetch(API + '?action=get&id=' + id, { credentials: 'same-origin' });
        var j   = await res.json();
        if (!j.success) throw new Error(j.error);
        var r = j.data;

        document.getElementById('dashId').value          = r.dashboard_id;
        document.getElementById('dashName').value        = r.dashboard_name;
        document.getElementById('dashCode').value        = r.dashboard_code;
        document.getElementById('dashDescription').value = r.dashboard_description || '';
        document.getElementById('dashActive').checked    = r.dashboard_active == 1;

        // Restore dashboard access roles checkboxes
        var dashRoles = r.dashboard_role_access || [];
        document.querySelectorAll('#dashRolesChecklist input[type="checkbox"]').forEach(function (cb) {
          cb.checked = dashRoles.includes(cb.dataset.roleCode);
        });

        // Restore unified filters UI
        (r.dashboard_filters || []).forEach(function (f) {
          dashAddFilterRow(f.field, f.label, f.type, f.operator, f.lookup_table, f.lookup_val_col, f.lookup_lbl_col);
        });

        // Restore reports checkboxes
        var includedReportsMap = {};
        (r.dashboard_reports || []).forEach(function (rep) {
          includedReportsMap[rep.report_id] = rep;
        });

        document.querySelectorAll('.dash-report-config-card').forEach(function (card) {
          var repId = card.dataset.reportId;
          var match = includedReportsMap[repId];
          if (match) {
            var incCb = card.querySelector('.dash-report-include-cb');
            incCb.checked = true;
            dashToggleReportConfig(incCb);

            // Set role checkboxes for this report
            var allowedRoles = match.allowed_roles || [];
            card.querySelectorAll('.dash-report-role-cb').forEach(function (roleCb) {
              roleCb.checked = allowedRoles.includes(roleCb.dataset.roleCode);
            });
          }
        });

        document.getElementById('dashBuilderTitle').textContent = 'Edit Dashboard: ' + r.dashboard_name;
      } catch (e) {
        toast('Failed to load Dashboard config: ' + e.message, 'error');
        return;
      }
    }

    document.getElementById('dashListView').style.display    = 'none';
    document.getElementById('dashViewer').style.display      = 'none';
    document.getElementById('dashBuilderView').style.display = '';
  };

  function dashResetBuilder() {
    document.getElementById('dashId').value          = '';
    document.getElementById('dashName').value        = '';
    document.getElementById('dashCode').value        = '';
    document.getElementById('dashDescription').value = '';
    document.getElementById('dashActive').checked    = true;
    document.getElementById('dashBuilderTitle').textContent = 'New Dashboard';

    // Clear role checkboxes
    document.querySelectorAll('#dashRolesChecklist input[type="checkbox"]').forEach(function (cb) {
      cb.checked = false;
    });

    // Clear filters
    document.getElementById('dashFiltersConfigList').innerHTML = '';

    // Clear report cards
    document.querySelectorAll('.dash-report-config-card').forEach(function (card) {
      card.querySelector('.dash-report-include-cb').checked = false;
      card.querySelector('.dash-report-config-card-roles').style.display = 'none';
      card.querySelectorAll('.dash-report-role-cb').forEach(function (rcb) {
        rcb.checked = false;
      });
    });
  }

  // ── add unified filter row (builder mode) ──────────────────────────────
  var _filterRowIdx = 0;
  window.dashAddFilterRow = function (field, label, type, op, lookupTable, lookupValCol, lookupLblCol) {
    _filterRowIdx++;
    var rowId = 'dfr_' + _filterRowIdx;

    field        = field        || '';
    label        = label        || '';
    type         = type         || 'month';
    op           = op           || '=';
    lookupTable  = lookupTable  || '';
    lookupValCol = lookupValCol || '';
    lookupLblCol = lookupLblCol || '';

    var div = document.createElement('div');
    div.className = 'dash-filter-config-row';
    div.id = rowId;

    div.innerHTML =
      '<div class="dash-filter-config-row-header">' +
        '<span style="font-size:11px;font-weight:600;color:var(--color-primary);">Filter #' + _filterRowIdx + '</span>' +
        '<button class="dash-remove-btn" onclick="document.getElementById(\'' + rowId + '\').remove()" title="Remove">✕</button>' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">' +
        '<div class="dash-field-group">' +
          '<label style="font-size:11px;">Parameter / Field Name</label>' +
          '<input type="text" class="nu-input dfr-field" placeholder="e.g. month" value="' + esc(field) + '">' +
        '</div>' +
        '<div class="dash-field-group">' +
          '<label style="font-size:11px;">Display Label</label>' +
          '<input type="text" class="nu-input dfr-label" placeholder="e.g. Select Month" value="' + esc(label) + '">' +
        '</div>' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">' +
        '<div class="dash-field-group">' +
          '<label style="font-size:11px;">Input Type</label>' +
          '<select class="nu-input dfr-type" onchange="dashOnFilterTypeChanged(\'' + rowId + '\', this.value)">' +
            '<option value="month"'  + (type === 'month'  ? ' selected' : '') + '>Month Picker</option>' +
            '<option value="year"'   + (type === 'year'   ? ' selected' : '') + '>Year Picker</option>' +
            '<option value="lookup"' + (type === 'lookup' ? ' selected' : '') + '>Lookup Dropdown</option>' +
            '<option value="text"'   + (type === 'text'   ? ' selected' : '') + '>Plain Text Input</option>' +
          '</select>' +
        '</div>' +
        '<div class="dash-field-group">' +
          '<label style="font-size:11px;">Match Operator</label>' +
          '<select class="nu-input dfr-op">' +
            '<option value="="'    + (op === '='    ? ' selected' : '') + '>= exact match</option>' +
            '<option value="LIKE"' + (op === 'LIKE' ? ' selected' : '') + '>LIKE match (contains)</option>' +
          '</select>' +
        '</div>' +
      '</div>' +
      '<div class="dash-lookup-subconfig" id="' + rowId + '_lookup_sub" style="display:' + (type === 'lookup' ? 'block' : 'none') + ';border-top:1px dashed var(--color-border);padding-top:8px;margin-top:4px;">' +
        '<label style="font-size:11px;font-weight:600;color:var(--color-text-muted);display:block;margin-bottom:4px;">LOOKUP SOURCE CONFIGURATION:</label>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">' +
          '<select class="nu-input dfr-lookup-table" id="' + rowId + '_tbl" onchange="dashOnLookupTableChanged(\'' + rowId + '\', this.value)">' +
            '<option value="">-- select table --</option>' +
          '</select>' +
          '<select class="nu-input dfr-lookup-val" id="' + rowId + '_val">' +
            '<option value="">-- value field --</option>' +
          '</select>' +
          '<select class="nu-input dfr-lookup-lbl" id="' + rowId + '_lbl">' +
            '<option value="">-- label field --</option>' +
          '</select>' +
        '</div>' +
      '</div>';

    document.getElementById('dashFiltersConfigList').appendChild(div);

    // Populate tables dropdown if Lookup
    dashLoadTablesDropdown(rowId, lookupTable, lookupValCol, lookupLblCol);
  };

  // ── show/hide lookup fields based on filter type selection ─────────────
  window.dashOnFilterTypeChanged = function (rowId, val) {
    var sub = document.getElementById(rowId + '_lookup_sub');
    if (sub) {
      sub.style.display = val === 'lookup' ? 'block' : 'none';
    }
  };

  // ── fetch and load tables options ──────────────────────────────────────────
  async function dashLoadTablesDropdown(rowId, selectedTable, selectedValCol, selectedLblCol) {
    var tblSel = document.getElementById(rowId + '_tbl');
    if (!tblSel) return;
    try {
      var res  = await fetch(API + '?action=tables', { credentials: 'same-origin' });
      var j    = await res.json();
      var list = j.data || [];

      tblSel.innerHTML = '<option value="">-- select table --</option>' +
        list.map(function (tbl) {
          return '<option value="' + tbl + '"' + (tbl === selectedTable ? ' selected' : '') + '>' + tbl + '</option>';
        }).join('');

      if (selectedTable) {
        await dashLoadColumnsDropdown(rowId, selectedTable, selectedValCol, selectedLblCol);
      }
    } catch(e) {
      console.error('[DASH] failed to load lookup tables list:', e);
    }
  }

  window.dashOnLookupTableChanged = function (rowId, val) {
    dashLoadColumnsDropdown(rowId, val, '', '');
  };

  async function dashLoadColumnsDropdown(rowId, table, selectedValCol, selectedLblCol) {
    var valSel = document.getElementById(rowId + '_val');
    var lblSel = document.getElementById(rowId + '_lbl');
    if (!valSel || !lblSel || !table) return;

    try {
      var res  = await fetch(API + '?action=columns&table=' + encodeURIComponent(table), { credentials: 'same-origin' });
      var j    = await res.json();
      var list = j.data || [];

      var optionsHtml = list.map(function (col) {
        return '<option value="' + col + '">' + col + '</option>';
      }).join('');

      valSel.innerHTML = '<option value="">-- value field --</option>' + optionsHtml;
      lblSel.innerHTML = '<option value="">-- label field --</option>' + optionsHtml;

      if (selectedValCol) valSel.value = selectedValCol;
      if (selectedLblCol) lblSel.value = selectedLblCol;
    } catch (e) {
      console.error('[DASH] failed to load columns:', e);
    }
  }

  // ── toggle report config role checkboxes ─────────────────────────────
  window.dashToggleReportConfig = function (cb) {
    var card  = cb.closest('.dash-report-config-card');
    var roles = card.querySelector('.dash-report-config-card-roles');
    if (cb.checked) {
      roles.style.display = 'block';
    } else {
      roles.style.display = 'none';
      // Clear roles
      card.querySelectorAll('.dash-report-role-cb').forEach(function (rcb) {
        rcb.checked = false;
      });
    }
  };

  // ── save dashboard definition ─────────────────────────────────────────
  window.dashSave = async function () {
    var name   = document.getElementById('dashName').value.trim();
    var code   = document.getElementById('dashCode').value.trim();
    var desc   = document.getElementById('dashDescription').value.trim();
    var active = document.getElementById('dashActive').checked ? 1 : 0;

    if (!name) { toast('Dashboard name is required', 'error'); return; }

    // Dashboard-level allowed roles
    var allowedRoles = [];
    document.querySelectorAll('#dashRolesChecklist input[type="checkbox"]:checked').forEach(function (cb) {
      allowedRoles.push(cb.dataset.roleCode);
    });

    // Assemble Unified Filters Config
    var filters = [];
    var filterRows = document.querySelectorAll('#dashFiltersConfigList .dash-filter-config-row');
    for (var i = 0; i < filterRows.length; i++) {
      var row = filterRows[i];
      var fieldVal = row.querySelector('.dfr-field').value.trim();
      var labelVal = row.querySelector('.dfr-label').value.trim();
      var typeVal  = row.querySelector('.dfr-type').value;
      var opVal    = row.querySelector('.dfr-op').value;

      if (!fieldVal) {
        toast('Please enter the field name for all filter rows', 'error');
        return;
      }

      var fObj = {
        field:    fieldVal,
        label:    labelVal || fieldVal,
        type:     typeVal,
        operator: opVal
      };

      if (typeVal === 'lookup') {
        var tbl = row.querySelector('.dfr-lookup-table').value;
        var val = row.querySelector('.dfr-lookup-val').value;
        var lbl = row.querySelector('.dfr-lookup-lbl').value;

        if (!tbl || !val || !lbl) {
          toast('Lookup filter configuration is incomplete for filter: ' + fieldVal, 'error');
          return;
        }

        fObj.lookup_table   = tbl;
        fObj.lookup_val_col = val;
        fObj.lookup_lbl_col = lbl;
      }

      filters.push(fObj);
    }

    // Assemble Reports selection
    var reports = [];
    document.querySelectorAll('.dash-report-config-card').forEach(function (card) {
      var incCb = card.querySelector('.dash-report-include-cb');
      if (incCb.checked) {
        var repId   = parseInt(card.dataset.reportId);
        var repName = card.querySelector('.dash-report-include-cb').parentElement.textContent.trim();

        // Allowed roles for this specific report
        var repRoles = [];
        card.querySelectorAll('.dash-report-role-cb:checked').forEach(function (rcb) {
          repRoles.push(rcb.dataset.roleCode);
        });

        reports.push({
          report_id:     repId,
          report_name:   repName,
          allowed_roles: repRoles
        });
      }
    });

    var payload = {
      dashboard_id:          document.getElementById('dashId').value || 0,
      dashboard_name:        name,
      dashboard_code:        code,
      dashboard_description: desc,
      dashboard_active:      active,
      dashboard_role_access: allowedRoles,
      dashboard_filters:     filters,
      dashboard_reports:     reports
    };

    try {
      var res = await fetch(API + '?action=save', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      var j = await res.json();
      if (!j.success) throw new Error(j.error);

      toast('Dashboard saved successfully', 'success');
      setTimeout(function () {
        dashShowList();
      }, 300);

    } catch (e) {
      toast('Save failed: ' + e.message, 'error');
    }
  };

  // ── delete dashboard ─────────────────────────────────────────────────
  window.dashDelete = async function (id) {
    var d = _dashboards.find(function (x) { return x.dashboard_id == id; });
    var name = d ? d.dashboard_name : ('ID: ' + id);
    if (!confirm('Are you sure you want to delete dashboard "' + name + '"?')) return;
    try {
      var res = await fetch(API + '?action=delete', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      });
      var j = await res.json();
      if (!j.success) throw new Error(j.error);

      toast('Dashboard deleted', 'success');
      dashLoadList();
    } catch (e) {
      toast('Delete failed: ' + e.message, 'error');
    }
  };

  // ── utility html escaper ───────────────────────────────────────────
  function esc(s) {
    return String(s !== null && s !== undefined ? s : '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── SPA-safe teardown/destroy guard ─────────────────────────────────
  window.Dash = {
    _destroy: function () {
      window.Dash = null;
    }
  };

})();
</script>
