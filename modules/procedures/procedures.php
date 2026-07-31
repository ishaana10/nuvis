<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db = NuDatabase::getInstance();
$procedures = $db->fetchAll("SELECT * FROM nu_procedures ORDER BY procedure_updated_at DESC");
?>

<style>
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
.nb-ace-theme-btn, .nb-ace-action-btn {
  font-size:10px; padding:2px 8px; border-radius:4px; cursor:pointer;
  border:1px solid rgba(255,255,255,.15); background:none; color:rgba(255,255,255,.5);
  transition:all .15s;
}
.nb-ace-theme-btn:hover, .nb-ace-action-btn:hover { background:rgba(255,255,255,.08); color:#fff; }
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
</style>

<div class="nu-procedures-module">

  <!-- ── HEADER ─────────────────────────────────────────────────────── -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h2 style="font-size:20px;font-weight:600;margin:0;">Custom PHP Functions</h2>
      <p style="color:var(--text-secondary);font-size:13px;margin:2px 0 0;">Create and manage reusable PHP functions accessible from form custom PHP and client-side JS.</p>
    </div>
    <button class="nu-btn nu-btn-primary" onclick="Procedures.openNew()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Function
    </button>
  </div>

  <!-- ── TABS ── -->
  <div class="nb-tabs" style="margin-bottom: 16px;">
    <button type="button" class="nb-tab active" id="procTabList" onclick="Procedures.switchModuleTab('list')">Functions List</button>
    <button type="button" class="nb-tab" id="procTabGuide" onclick="Procedures.switchModuleTab('guide')">Developer Usage Guide</button>
  </div>

  <!-- ── LIST PANEL ── -->
  <div id="procListPanel">
    <div style="margin-bottom: 16px;">
      <input type="text" id="procSearchInput" placeholder="Search functions..." oninput="Procedures.filterList(this.value)" class="nu-input" style="max-width: 320px; font-size: 13px; padding: 6px 12px;">
    </div>

    <div class="nu-grid" id="procCards" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;">
      <?php if (empty($procedures)): ?>
      <div class="nu-card" style="grid-column:1/-1;text-align:center;padding:48px 24px;" id="procEmptyCard">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-tertiary);margin:0 auto 12px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
        <p style="color:var(--text-secondary);font-weight:500;">No custom PHP functions found</p>
        <p style="color:var(--text-tertiary);font-size:13px;margin-top:4px;">Click "New Function" to build your first reusable PHP procedure.</p>
      </div>
      <?php else: ?>
      <?php foreach ($procedures as $p): ?>
      <div class="nu-card proc-card" data-id="<?php echo $p['procedure_id']; ?>" data-name="<?php echo htmlspecialchars($p['procedure_name']); ?>" data-code="<?php echo htmlspecialchars($p['procedure_code']); ?>" data-desc="<?php echo htmlspecialchars((string)$p['procedure_description']); ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
          <div style="flex:1;min-width:0;">
            <h4 style="font-weight:600;font-size:14px;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($p['procedure_name']); ?></h4>
            <span class="nu-badge nu-badge-secondary" style="font-size:11px;margin-top:3px;display:inline-block;font-family:monospace;"><?php echo htmlspecialchars($p['procedure_code']); ?></span>
          </div>
          <span class="nu-badge <?php echo $p['procedure_active'] ? 'nu-badge-success' : 'nu-badge-muted'; ?>" style="margin-left:8px;flex-shrink:0;"><?php echo $p['procedure_active'] ? 'Active' : 'Inactive'; ?></span>
        </div>
        <p style="color:var(--text-secondary);font-size:12px;margin-bottom:12px;height:36px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
          <?php echo htmlspecialchars($p['procedure_description'] ?: 'No description provided.'); ?>
        </p>
        <div style="display:flex;gap:6px;">
          <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="Procedures.edit(<?php echo $p['procedure_id']; ?>)">Edit Function</button>
          <button class="nu-btn nu-btn-danger nu-btn-sm" onclick="Procedures.delete(<?php echo $p['procedure_id']; ?>, '<?php echo htmlspecialchars($p['procedure_name'], ENT_QUOTES); ?>')">Delete</button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── DEVELOPER USAGE GUIDE TAB ── -->
  <div id="procGuidePanel" style="display:none;">
    <div class="nu-card" style="padding: 24px;">
      <h3 style="font-size: 16px; font-weight: 600; margin: 0 0 16px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Developer Usage Guide &amp; Integration API</h3>
      <p style="color:var(--text-secondary); font-size:13px; line-height: 1.5; margin-bottom: 16px;">
        Custom PHP Functions are stored centrally in the database. They can be executed both server-side inside any Custom PHP blocks (Forms, Workflows, Queries, Reports) and asynchronously from the client-side via JavaScript.
      </p>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- PHP Guide -->
        <div>
          <h4 style="font-weight: 600; font-size:14px; margin-bottom: 8px; color: var(--color-primary);">1. Call from Form Custom PHP</h4>
          <p style="font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px;">Use either the global wrapper function or the static helper class. Parameters are passed inside an associative array:</p>
          <pre style="background: #1e1e2e; color: #f8f8f2; padding: 12px; border-radius: 8px; font-size: 11px; overflow-x: auto; line-height: 1.4;"><code>// Option A: Global wrapper helper
$res = nu_run_procedure('calculate_tax', [
  'subtotal' => 150.00,
  'tax_rate' => 0.15
]);

if ($res['success']) {
  $tax = $res['data']['tax_amount'] ?? 0;
  $output = $res['output']; // printed output / echoes
} else {
  $error = $res['error'];
}

// Option B: Static class helper
$res = NuProcedure::run('my_function', $params);</code></pre>
        </div>

        <!-- JS Guide -->
        <div>
          <h4 style="font-weight: 600; font-size:14px; margin-bottom: 8px; color: var(--color-primary);">2. Call from Custom JavaScript</h4>
          <p style="font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px;">Run functions asynchronously from any Form Event or Custom JS button via global JS helpers:</p>
          <pre style="background: #1e1e2e; color: #f8f8f2; padding: 12px; border-radius: 8px; font-size: 11px; overflow-x: auto; line-height: 1.4;"><code>// Option A: callPHP helper
callPHP('calculate_tax', {subtotal: 150.00, tax_rate: 0.15}, function(res) {
  if (res.success) {
    console.log('Result data:', res.data);
    console.log('Echo output:', res.output);
  } else {
    console.error('Error:', res.error);
  }
});

// Option B: runProcedure helper (alias)
runProcedure('my_function', {id: 123}, function(res) {
  // ...
});</code></pre>
        </div>

        <!-- Writing Procedures Guide -->
        <div style="grid-column: 1/-1; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px;">
          <h4 style="font-weight: 600; font-size:14px; margin-bottom: 8px; color: var(--color-primary);">3. Inside Your Custom PHP Function Code</h4>
          <p style="font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px;">When writing your function code, the following sandbox variables are pre-populated and accessible:</p>
          <ul style="font-size: 12px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px; padding-left: 20px; list-style-type: disc;">
            <li><code>$_proc_params</code> : Associative array of passed parameters.</li>
            <li><code>$_proc_db</code> : Safe instance of <code>NuDatabase</code> connection wrapper.</li>
            <li><code>$_proc_auth</code> : Active <code>NuAuth</code> security context.</li>
            <li><code>$_proc_result</code> : Assign your structured return payload (array/object/string) to this variable to deliver it back to the caller.</li>
          </ul>
          <pre style="background: #1e1e2e; color: #f8f8f2; padding: 12px; border-radius: 8px; font-size: 11px; overflow-x: auto; line-height: 1.4;"><code>&lt;?php
// Example: Centralized tax calculation function
$subtotal = $_proc_params['subtotal'] ?? 0;
$rate     = $_proc_params['tax_rate'] ?? 0.1;

$taxAmount = $subtotal * $rate;

// Print/echo values to catch them in output
echo "Calculating tax for subtotal: " . $subtotal;

// Return structured return data
$_proc_result = [
  'tax_amount' => $taxAmount,
  'total'      => $subtotal + $taxAmount
];
</code></pre>
        </div>
      </div>
    </div>
  </div>

  <!-- ── BUILDER PANEL ──────────────────────────────────────────────── -->
  <div id="procBuilderPanel" style="display:none;margin-top:24px;">
    <div class="nu-card" style="padding: 24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 class="nu-card-title" id="procBuilderTitle">New Custom Function</h3>
        <div style="display:flex;gap:8px;">
          <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="Procedures.close()">Cancel</button>
          <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="Procedures.save()">💾 Save Function</button>
        </div>
      </div>

      <input type="hidden" id="procEditId">

      <!-- Meta fields -->
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;margin-bottom:16px;">
        <div class="nu-field" style="margin:0;">
          <label>Function Name <span style="color:var(--color-danger)">*</span></label>
          <input type="text" class="nu-input" id="procName" placeholder="e.g. Calculate Tax" oninput="Procedures.autoCode()">
        </div>
        <div class="nu-field" style="margin:0;">
          <label>Function Code (Slug) <span style="color:var(--color-danger)">*</span></label>
          <input type="text" class="nu-input" id="procCode" placeholder="e.g. calculate_tax">
        </div>
        <div class="nu-field" style="margin:0;">
          <label>Active</label>
          <div style="padding-top:8px;"><input type="checkbox" id="procActive" checked style="width:16px;height:16px;"></div>
        </div>
      </div>

      <div class="nu-field" style="margin-bottom:16px;">
        <label>Description</label>
        <input type="text" class="nu-input" id="procDescription" placeholder="Explain what this centralized function does">
      </div>

      <!-- Ace Editor PHP Code -->
      <div style="margin-bottom: 20px;">
        <label class="nu-label" style="margin-bottom:6px;display:block;">PHP Implementation Code</label>
        <div class="nb-ace-wrap">
          <div class="nb-ace-topbar">
            <span class="nb-ace-lang-badge php">PHP</span>
            <span class="nb-ace-hint">Ctrl+Space autocomplete · Ctrl+Z undo · drag handle to resize</span>
            <button type="button" class="nb-ace-action-btn" onclick="nbAce.beautify('aceProcedurePhp')" title="Beautify Code">✨ beautify</button>
            <button type="button" class="nb-ace-action-btn" onclick="nbAce.openFullView('aceProcedurePhp')" title="Fullscreen Edit">↕ full view</button>
            <button type="button" class="nb-ace-action-btn" onclick="nbAce.openInTab('aceProcedurePhp')" title="Open in New Tab">⧉ new tab</button>
            <button type="button" class="nb-ace-theme-btn" onclick="nbAce.toggleTheme('aceProcedurePhp')">☀ theme</button>
          </div>
          <div id="aceProcedurePhp" class="nb-ace-editor" style="height:250px;"></div>
          <div class="nb-ace-resize-handle" data-ace="aceProcedurePhp"></div>
        </div>
        <textarea id="procPhp" class="nb-ace-hidden"></textarea>
      </div>

      <!-- ── TEST RUN SANDBOX AREA ── -->
      <div style="border-top:1.5px dashed var(--border-color); padding-top:20px; margin-top:20px;">
        <h4 style="font-size:14px; font-weight:600; margin:0 0 12px 0; display:flex; align-items:center; gap:6px;">
          ⚡ Test Execution Sandbox
        </h4>
        <p style="color:var(--text-tertiary); font-size:11px; margin-bottom:12px;">Mock input parameters to run the function right now. Enter values as a JSON object (e.g. <code>{"subtotal": 100, "tax_rate": 0.1}</code>).</p>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; align-items:start;">
          <!-- Mock input -->
          <div>
            <label class="nu-label" style="font-size:11px; font-weight:600; margin-bottom:4px; display:block;">Mock Params (JSON Object)</label>
            <textarea id="procTestParams" class="nu-input" rows="4" style="font-family:monospace; font-size:12px;" placeholder='{&#10;  "subtotal": 150.00,&#10;  "tax_rate": 0.15&#10;}'></textarea>
            <button type="button" class="nu-btn nu-btn-primary nu-btn-sm" style="margin-top:10px; width:100%; font-weight: 600;" onclick="Procedures.testRun()">
              ⚡ Execute Function
            </button>
          </div>

          <!-- Sandbox results -->
          <div>
            <label class="nu-label" style="font-size:11px; font-weight:600; margin-bottom:4px; display:block;">Execution Feedback Output</label>
            <div id="procTestFeedback" style="background:#1e1e2e; color:#a6accd; border-radius:8px; padding:12px; font-family:monospace; font-size:12px; height:132px; overflow-y:auto; border:1px solid #2b2b3a;">
              <span style="color:#676e95; font-style:italic;">Run function to view echoes, errors and structured return data...</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<script>
(function() {

// Clean up existing Procedures namespace to prevent collision on reload
if (window.Procedures && typeof window.Procedures._destroy === 'function') {
  window.Procedures._destroy();
}

window.Procedures = (function() {
  var _activeTab = 'list';
  var $ = function(id) { return document.getElementById(id); };

  function toast(msg, type) {
    if (window.NuApp && typeof NuApp.toast === 'function') {
      NuApp.toast(msg, type);
    } else {
      console.log('[Procedures]', type, msg);
    }
  }

  function filterList(query) {
    query = (query || '').toLowerCase().trim();
    var cards = document.querySelectorAll('#procCards .proc-card');
    var visibleCount = 0;

    cards.forEach(function(card) {
      var name = (card.dataset.name || '').toLowerCase();
      var code = (card.dataset.code || '').toLowerCase();
      var desc = (card.dataset.desc || '').toLowerCase();
      if (name.includes(query) || code.includes(query) || desc.includes(query)) {
        card.style.display = '';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    var empty = $('procEmptyCard');
    if (empty) {
      empty.style.display = (visibleCount === 0 && cards.length > 0) ? '' : 'none';
    }
  }

  function switchModuleTab(tabId) {
    _activeTab = tabId;
    var listTab = $('procTabList');
    var guideTab = $('procTabGuide');

    if (listTab) listTab.classList.toggle('active', tabId === 'list');
    if (guideTab) guideTab.classList.toggle('active', tabId === 'guide');

    var listPanel = $('procListPanel');
    var guidePanel = $('procGuidePanel');

    if (listPanel) listPanel.style.display = tabId === 'list' ? 'block' : 'none';
    if (guidePanel) guidePanel.style.display = tabId === 'guide' ? 'block' : 'none';

    // Ensure we hide builder when looking at guide
    if (tabId === 'guide') {
      close();
    }
  }

  function openNew() {
    resetForm();
    $('procBuilderTitle').textContent = 'New Custom Function';
    $('procBuilderPanel').style.display = 'block';
    $('procBuilderPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (window.nbAce) {
      nbAce.setValue('aceProcedurePhp', '<' + '?' + 'php\n// Write your PHP code here.\n// $_proc_params contains input arguments.\n// Return results in $_proc_result.\n\n');
      nbAce.resizeAll();
    }
  }

  async function edit(id) {
    try {
      var res = await fetch('api/procedures.php?action=get&id=' + id, { credentials: 'same-origin' });
      var data = await res.json();
      if (!data.success) {
        toast(data.error || 'Failed to load function', 'error');
        return;
      }
      var p = data.procedure;
      resetForm();
      $('procEditId').value = p.procedure_id;
      $('procName').value = p.procedure_name;
      $('procCode').value = p.procedure_code;
      $('procDescription').value = p.procedure_description || '';
      $('procActive').checked = p.procedure_active == 1;

      $('procBuilderTitle').textContent = 'Edit Custom Function';
      $('procBuilderPanel').style.display = 'block';
      $('procBuilderPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });

      if (window.nbAce) {
        nbAce.setValue('aceProcedurePhp', p.procedure_php || '');
        nbAce.resizeAll();
      }
    } catch (e) {
      console.error(e);
      toast('Network error loading function', 'error');
    }
  }

  function resetForm() {
    ['procEditId','procName','procCode','procDescription'].forEach(function(id) {
      var el = $(id);
      if (el) el.value = '';
    });
    if ($('procActive')) $('procActive').checked = true;
    if ($('procPhp')) $('procPhp').value = '';
    if (window.nbAce) nbAce.setValue('aceProcedurePhp', '');
    var feedback = $('procTestFeedback');
    if (feedback) {
      feedback.innerHTML = '<span style="color:#676e95; font-style:italic;">Run function to view echoes, errors and structured return data...</span>';
    }
  }

  function autoCode() {
    if ($('procEditId') && $('procEditId').value) return;
    var name = $('procName') ? $('procName').value : '';
    if ($('procCode')) {
      $('procCode').value = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }
  }

  async function save() {
    var name = $('procName') ? $('procName').value.trim() : '';
    var code = $('procCode') ? $('procCode').value.trim() : '';
    if (window.nbAce) nbAce.syncAll();
    var php  = $('procPhp') ? $('procPhp').value.trim() : '';

    if (!name) { toast('Function Name is required', 'error'); return; }
    if (!code) { toast('Function Code (slug) is required', 'error'); return; }

    var payload = {
      procedure_id: $('procEditId') ? ($('procEditId').value || null) : null,
      procedure_name: name,
      procedure_code: code,
      procedure_description: $('procDescription') ? $('procDescription').value.trim() : '',
      procedure_php: php,
      procedure_active: ($('procActive') && $('procActive').checked) ? 1 : 0
    };

    try {
      var res = await fetch('api/procedures.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      var data = await res.json();
      if (data.success) {
        toast('Function saved successfully!', 'success');
        close();
        setTimeout(function() {
          if (window.NuApp && typeof NuApp.loadModule === 'function') {
            NuApp.loadModule('procedures');
          } else {
            location.reload();
          }
        }, 500);
      } else {
        toast(data.error || 'Failed to save', 'error');
      }
    } catch (e) {
      console.error(e);
      toast('Network error during save', 'error');
    }
  }

  async function del(id, name) {
    if (!confirm('Are you sure you want to permanently delete custom function "' + name + '"?')) return;
    try {
      var res = await fetch('api/procedures.php?action=delete&id=' + id, { credentials: 'same-origin' });
      var data = await res.json();
      if (data.success) {
        toast('Function deleted successfully', 'success');
        setTimeout(function() {
          if (window.NuApp && typeof NuApp.loadModule === 'function') {
            NuApp.loadModule('procedures');
          } else {
            location.reload();
          }
        }, 500);
      } else {
        toast(data.error || 'Failed to delete', 'error');
      }
    } catch (e) {
      console.error(e);
      toast('Network error during delete', 'error');
    }
  }

  async function testRun() {
    var feedback = $('procTestFeedback');
    if (!feedback) return;

    if (window.nbAce) nbAce.syncAll();
    var phpCode = $('procPhp') ? $('procPhp').value : '';
    var mockParams = $('procTestParams') ? $('procTestParams').value.trim() : '{}';

    feedback.innerHTML = '<span style="color:#4f6bed; font-weight:600;">⌛ Running implementation...</span>';

    try {
      var res = await fetch('api/procedures.php?action=test_run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          procedure_php: phpCode,
          params: mockParams
        }),
        credentials: 'same-origin'
      });
      var data = await res.json();

      feedback.innerHTML = '';
      if (data.success) {
        var preStr = '';
        if (data.output) {
          preStr += '<span style="color:#22c55e; font-weight:700;">[Printed Output / Echoes]</span><br>' + String(data.output) + '<br><br>';
        }
        preStr += '<span style="color:#00e5ff; font-weight:700;">[Returned $_proc_result Data]</span><br>';
        if (data.data !== null && data.data !== undefined) {
          preStr += JSON.stringify(data.data, null, 2);
        } else {
          preStr += '<span style="color:#676e95; font-style:italic;">(NULL / empty payload returned)</span>';
        }
        feedback.innerHTML = preStr;
      } else {
        feedback.innerHTML = '<span style="color:#ef4444; font-weight:700;">❌ Execution Error:</span><br>' + String(data.error || 'Unknown runtime error');
      }
    } catch (e) {
      console.error(e);
      feedback.innerHTML = '<span style="color:#ef4444; font-weight:700;">❌ Network Error:</span><br>' + e.message;
    }
  }

  function close() {
    if ($('procBuilderPanel')) $('procBuilderPanel').style.display = 'none';
    resetForm();
  }

  function _destroy() {
    window.Procedures = null;
  }

  // Mount Ace editors
  setTimeout(function() {
    if (window.ace && window.nbAce) {
      nbAce.init('aceProcedurePhp', 'procPhp', 'php');
    }
  }, 100);

  return {
    openNew: openNew,
    edit: edit,
    save: save,
    delete: del,
    close: close,
    autoCode: autoCode,
    filterList: filterList,
    switchModuleTab: switchModuleTab,
    testRun: testRun,
    _destroy: _destroy
  };
})();

})();
</script>
