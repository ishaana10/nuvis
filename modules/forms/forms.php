<?php
require_once '../../config.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';

$auth = new NuAuth();
if (!$auth->checkAuth()) exit('Unauthorized');

$db = NuDatabase::getInstance();
$forms = $db->fetchAll("SELECT * FROM nu_forms WHERE form_active = 1 ORDER BY form_id DESC");
?>

<div class="nu-forms">
    <div class="nu-card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 class="nu-card-title">Forms</h3>
        <?php if ($auth->hasPermission('forms', 'create')): ?>
        <button class="nu-btn nu-btn-primary" onclick="openFormBuilder()">+ New Form</button>
        <?php endif; ?>
    </div>

    <div class="nu-grid">
        <?php foreach ($forms as $f): ?>
        <div class="nu-card">
            <div class="nu-card-header">
                <h4 class="nu-card-title"><?php echo htmlspecialchars($f['form_name']); ?></h4>
                <span class="nu-badge"><?php echo htmlspecialchars($f['form_code']); ?></span>
            </div>

            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px;">
                Table: <?php echo $f['form_table'] ? htmlspecialchars($f['form_table']) : '<em>None</em>'; ?>
            </p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="previewForm('<?php echo htmlspecialchars($f['form_code']); ?>')">Preview</button>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="editForm('<?php echo (int)$f['form_id']; ?>')">Edit</button>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="browseForm('<?php echo htmlspecialchars($f['form_code']); ?>')">Browse</button>
                <button class="nu-btn nu-btn-danger nu-btn-sm" onclick="deleteForm(<?php echo (int)$f['form_id']; ?>, '<?php echo htmlspecialchars($f['form_name'], ENT_QUOTES); ?>')">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($forms)): ?>
        <div class="nu-card" style="grid-column:1/-1;text-align:center;padding:40px;">
            <p style="color:var(--text-tertiary);">No forms yet. Click "New Form" to create one.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Form Builder -->
<div class="nu-card" id="formBuilderCard" style="display:none;margin-top:24px;">
    <div class="nu-card-header">
        <h3 class="nu-card-title" id="builderTitle">New Form</h3>
        <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="document.getElementById('formBuilderCard').style.display='none'">Close</button>
    </div>

    <div style="display:flex;gap:16px;margin-bottom:16px;">
        <div class="nu-field" style="flex:1;">
            <label>Form Name</label>
            <input type="text" class="nu-input" id="builderFormName" placeholder="Customer Order Form">
        </div>

        <div class="nu-field" style="flex:1;">
            <label>Database Table (optional)</label>
            <input type="text" class="nu-input" id="builderFormTable" placeholder="customers">
        </div>
    </div>

    <div class="nu-builder-section" style="margin-bottom:16px;padding:16px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-elevated);">
        <h4 style="margin:0 0 12px 0;">Browse Options</h4>

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <input type="checkbox" id="formBrowseSearchEnabled">
            Enable search
        </label>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            <div class="nu-field">
                <label>Search placeholder</label>
                <input type="text" id="formBrowseSearchPlaceholder" class="nu-input" placeholder="Search records...">
            </div>

            <div class="nu-field">
                <label>Search fields</label>
                <input type="text" id="formBrowseSearchFields" class="nu-input" placeholder="name,email,status">
            </div>

            <div class="nu-field">
                <label>Page size</label>
                <input type="number" id="formBrowsePageSize" class="nu-input" value="20" min="1">
            </div>

            <div class="nu-field">
                <label>Default sort</label>
                <input type="text" id="formBrowseDefaultSort" class="nu-input" placeholder="created_at DESC">
            </div>
        </div>

        <div class="nu-field" style="margin-top:12px;">
            <label>Browse SQL</label>
            <textarea id="formBrowseSql" class="nu-input" rows="5" placeholder="SELECT ..."></textarea>
        </div>

        <div class="nu-field" style="margin-top:12px;">
            <label>Browse Columns JSON</label>
            <textarea id="formBrowseColumns" class="nu-input" rows="6" placeholder='[{"name":"status","label":"Status"}]'></textarea>
        </div>
    </div>

    <div style="display:flex;gap:16px;">
        <div style="width:200px;flex-shrink:0;">
            <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;color:var(--text-secondary);">Field Types</h4>
            <div class="nu-builder-tools">
                <div class="nu-builder-tool" draggable="true" data-type="text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"></line><line x1="4" y1="15" x2="14" y2="15"></line></svg> Text</div>
                <div class="nu-builder-tool" draggable="true" data-type="email"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Email</div>
                <div class="nu-builder-tool" draggable="true" data-type="number"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"></line></svg> Number</div>
                <div class="nu-builder-tool" draggable="true" data-type="date"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Date</div>
                <div class="nu-builder-tool" draggable="true" data-type="select"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg> Select</div>
                <div class="nu-builder-tool" draggable="true" data-type="textarea"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"></line><line x1="4" y1="15" x2="20" y2="15"></line><line x1="4" y1="4" x2="20" y2="4"></line><line x1="4" y1="20" x2="20" y2="20"></line></svg> Textarea</div>
                <div class="nu-builder-tool" draggable="true" data-type="checkbox"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg> Checkbox</div>
                <div class="nu-builder-tool" draggable="true" data-type="lookup"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Lookup</div>
            </div>
        </div>

        <div style="flex:1;">
            <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;color:var(--text-secondary);">Form Canvas</h4>
            <div class="nu-builder-canvas" id="formCanvas">
                <p style="color:var(--text-tertiary);text-align:center;padding:40px;">Drag fields here</p>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
        <input type="hidden" id="editFormId" value="">
        <button class="nu-btn nu-btn-ghost" onclick="document.getElementById('formBuilderCard').style.display='none'">Cancel</button>
        <button class="nu-btn nu-btn-primary" onclick="saveForm()">Save Form</button>
    </div>
</div>