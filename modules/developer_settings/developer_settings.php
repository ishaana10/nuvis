<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

// Restricted to globeadmin only
$currentUser = $auth->getCurrentUser();
if (!$currentUser || strtolower((string)$currentUser['usr_role']) !== 'globeadmin') {
    echo '<div style="padding:24px;border:2px solid var(--color-error);background:var(--color-error-highlight);border-radius:8px;"><h3>Access Denied</h3><p>Only globeadmin developers can manage developer settings.</p></div>';
    exit;
}
?>
<div class="nu-card nu-dev-settings-module">
    <div class="nu-card-header">
        <h3 class="nu-card-title">Developer &amp; System Settings</h3>
        <p style="color:var(--text-muted); font-size:13px; margin:4px 0 0 0;">Configure custom application branding, logos, and custom global hash variables (e.g. tax rates) that can be embedded securely inside SQL queries.</p>
    </div>

    <!-- Application Brand Section -->
    <div class="nu-dev-section">
        <h4 class="nu-section-title">Application Branding</h4>
        <div class="nu-grid-2">
            <div class="nu-field">
                <label for="devAppName" class="nu-label">Application Name</label>
                <input id="devAppName" type="text" class="nu-input" placeholder="e.g. My Custom Low-Code App">
                <span class="nu-field-desc">Used in browser tabs, navigation title, and login cards.</span>
            </div>

            <div class="nu-field">
                <label class="nu-label">Application Logo</label>
                <div class="logo-preview-container" style="display:flex; align-items:center; gap:16px; margin-bottom:8px;">
                    <div id="devLogoPreview" style="width:64px; height:64px; border:1px solid var(--border-color); border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; overflow:hidden; background:var(--bg-elevated);">
                        <span style="color:var(--text-muted); font-size:12px;">No Logo</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <input id="devLogoUrl" type="text" class="nu-input nu-input-sm" style="width:240px;" placeholder="Logo URL or relative path" readonly>
                        <div style="display:flex; gap:8px;">
                            <label class="nu-btn nu-btn-ghost nu-btn-sm" style="cursor:pointer; margin:0;">
                                ⬆ Upload Logo File
                                <input type="file" id="devLogoFile" style="display:none;" accept="image/*">
                            </label>
                            <button id="devLogoRemoveBtn" class="nu-btn nu-btn-danger nu-btn-sm" style="display:none;" onclick="DevSettings.removeLogo()">Remove Logo</button>
                        </div>
                    </div>
                </div>
                <span class="nu-field-desc">Recommended: Transparent PNG or SVG under 500kb. Replaces default logo icon.</span>
            </div>
        </div>
    </div>

    <!-- Dynamic Settings / Global Hashes Section -->
    <div class="nu-dev-section" style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 24px;">
        <h4 class="nu-section-title">System Settings &amp; Global Hashes</h4>
        <p style="color:var(--text-muted); font-size:12px; margin-bottom:12px; line-height:1.4;">
            Define dynamic key-value properties. Checking the <strong>Global Hash</strong> checkbox makes the key available globally in SQL queries as <code>##fieldname##</code> (similar to user fields configurations).
        </p>

        <div style="overflow-x:auto;">
            <table class="nu-table" style="width:100%; border-collapse:collapse;" id="devHashesTable">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-color); background:var(--table-head-bg);">
                        <th style="padding:10px; text-align:left; font-size:12px; font-weight:600;">Field Label</th>
                        <th style="padding:10px; text-align:left; font-size:12px; font-weight:600;">Field Key (Unique Name)</th>
                        <th style="padding:10px; text-align:left; font-size:12px; font-weight:600;">Value</th>
                        <th style="padding:10px; text-align:center; font-size:12px; font-weight:600; width:120px;">Global Hash?</th>
                        <th style="padding:10px; text-align:center; font-size:12px; font-weight:600; width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="devHashesBody">
                    <!-- Dynamic rows injected here -->
                </tbody>
            </table>
        </div>

        <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" style="margin-top:12px;" onclick="DevSettings.addRow()">
            ➕ Add Custom Field
        </button>
    </div>

    <!-- Save Controls -->
    <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" class="nu-btn nu-btn-ghost" onclick="DevSettings.loadSettings()">Discard Changes</button>
        <button type="button" class="nu-btn nu-btn-primary" onclick="DevSettings.saveSettings()">💾 Save Settings</button>
    </div>
</div>

<style>
.nu-dev-settings-module {
    padding: 24px;
    background: var(--card-bg, #fff);
    border-radius: var(--radius-lg, 12px);
    border: 1px solid var(--border-color, #ddd);
}
.nu-dev-section {
    margin-bottom: 16px;
}
.nu-section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text, #111);
    margin: 0 0 12px 0;
}
.nu-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
}
.nu-field-desc {
    font-size: 11px;
    color: var(--text-muted, #777);
    margin-top: 4px;
    display: block;
}
</style>

<script>
var DevSettings = (function() {
    var API = 'api/developer_settings.php';
    var rows = [];

    async function loadSettings() {
        try {
            var res = await fetch(API + '?action=get', { credentials: 'same-origin' });
            var json = await res.json();
            if (!json.success) {
                if (window.NuApp) NuApp.toast(json.error || 'Failed to load settings', 'error');
                return;
            }

            var settings = json.settings || {};
            document.getElementById('devAppName').value = settings.app_name || '';
            document.getElementById('devLogoUrl').value = settings.app_logo || '';
            updateLogoPreview(settings.app_logo || '');

            rows = settings.system_fields_def || [];
            renderRows();
        } catch (e) {
            console.error('[DevSettings] failed to load', e);
            if (window.NuApp) NuApp.toast('Error connecting to backend API', 'error');
        }
    }

    function updateLogoPreview(path) {
        var preview = document.getElementById('devLogoPreview');
        var removeBtn = document.getElementById('devLogoRemoveBtn');
        if (path && path.trim() !== '') {
            preview.innerHTML = '<img src="' + path + '" style="max-width:100%; max-height:100%; object-fit:contain;">';
            if (removeBtn) removeBtn.style.display = 'block';
        } else {
            preview.innerHTML = '<span style="color:var(--text-muted); font-size:12px;">No Logo</span>';
            if (removeBtn) removeBtn.style.display = 'none';
        }
    }

    function removeLogo() {
        document.getElementById('devLogoUrl').value = '';
        updateLogoPreview('');
        if (window.NuApp) NuApp.toast('Logo reference removed from draft (click save to persist)');
    }

    function addRow(label, key, val, isGlobal) {
        rows.push({
            label: label || '',
            key: key || '',
            value: val || '',
            global: isGlobal !== undefined ? !!isGlobal : true
        });
        renderRows();
    }

    function removeRow(index) {
        rows.splice(index, 1);
        renderRows();
    }

    function updateRowProp(index, prop, value) {
        if (rows[index]) {
            rows[index][prop] = value;
        }
    }

    function renderRows() {
        var tbody = document.getElementById('devHashesBody');
        tbody.innerHTML = '';

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="padding:20px; text-align:center; color:var(--text-muted); font-style:italic;">No custom dynamic settings or hashes configured yet.</td></tr>';
            return;
        }

        rows.forEach(function(r, idx) {
            var tr = document.createElement('tr');
            tr.style.cssText = 'border-bottom:1px solid var(--border-color);';

            // Label
            var tdLabel = document.createElement('td');
            tdLabel.style.padding = '8px 10px';
            var inputLabel = document.createElement('input');
            inputLabel.type = 'text';
            inputLabel.className = 'nu-input nu-input-sm';
            inputLabel.value = r.label || '';
            inputLabel.placeholder = 'e.g. Standard Tax Rate';
            inputLabel.addEventListener('input', function() {
                updateRowProp(idx, 'label', this.value);
            });
            tdLabel.appendChild(inputLabel);
            tr.appendChild(tdLabel);

            // Key
            var tdKey = document.createElement('td');
            tdKey.style.padding = '8px 10px';
            var inputKey = document.createElement('input');
            inputKey.type = 'text';
            inputKey.className = 'nu-input nu-input-sm';
            inputKey.value = r.key || '';
            inputKey.placeholder = 'e.g. tax_rate';
            inputKey.addEventListener('input', function() {
                // Sanitize key as dynamic hash format
                var sanitized = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                this.value = sanitized;
                updateRowProp(idx, 'key', sanitized);
            });
            tdKey.appendChild(inputKey);
            tr.appendChild(tdKey);

            // Value
            var tdValue = document.createElement('td');
            tdValue.style.padding = '8px 10px';
            var inputValue = document.createElement('input');
            inputValue.type = 'text';
            inputValue.className = 'nu-input nu-input-sm';
            inputValue.value = r.value || '';
            inputValue.placeholder = 'e.g. 0.15';
            inputValue.addEventListener('input', function() {
                updateRowProp(idx, 'value', this.value);
            });
            tdValue.appendChild(inputValue);
            tr.appendChild(tdValue);

            // Global Hash checkbox
            var tdGlobal = document.createElement('td');
            tdGlobal.style.cssText = 'padding:8px 10px; text-align:center;';
            var checkGlobal = document.createElement('input');
            checkGlobal.type = 'checkbox';
            checkGlobal.checked = r.global !== false;
            checkGlobal.addEventListener('change', function() {
                updateRowProp(idx, 'global', this.checked);
            });
            tdGlobal.appendChild(checkGlobal);
            tr.appendChild(tdGlobal);

            // Actions (delete)
            var tdActions = document.createElement('td');
            tdActions.style.cssText = 'padding:8px 10px; text-align:center;';
            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'nu-btn nu-btn-danger nu-btn-sm';
            delBtn.textContent = '✕';
            delBtn.style.padding = '2px 6px';
            delBtn.addEventListener('click', function() {
                removeRow(idx);
            });
            tdActions.appendChild(delBtn);
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    }

    async function saveSettings() {
        // Validation: key must be filled & unique
        var keys = {};
        for (var i = 0; i < rows.length; i++) {
            var k = (rows[i].key || '').trim();
            if (k === '') {
                if (window.NuApp) NuApp.toast('All dynamic fields must have a valid Field Key', 'error');
                return;
            }
            if (keys[k]) {
                if (window.NuApp) NuApp.toast('Duplicate Field Key: "' + k + '" is not allowed', 'error');
                return;
            }
            keys[k] = true;
        }

        var payload = {
            app_name: document.getElementById('devAppName').value,
            app_logo: document.getElementById('devLogoUrl').value,
            system_fields_def: rows
        };

        try {
            var res = await fetch(API + '?action=save', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.success) {
                if (window.NuApp) {
                    NuApp.toast('Settings saved successfully! Page will reload to apply branding.');
                }
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                if (window.NuApp) NuApp.toast(json.error || 'Failed to save settings', 'error');
            }
        } catch (e) {
            console.error('[DevSettings] failed to save', e);
            if (window.NuApp) NuApp.toast('Error saving settings to server', 'error');
        }
    }

    // Set up file upload listener
    function bindFileUpload() {
        var fileInput = document.getElementById('devLogoFile');
        if (!fileInput) return;

        fileInput.addEventListener('change', async function() {
            if (!fileInput.files || fileInput.files.length === 0) return;
            var file = fileInput.files[0];

            var formData = new FormData();
            formData.append('logo_file', file);

            if (window.NuApp) NuApp.toast('Uploading logo...');

            try {
                var res = await fetch(API + '?action=upload_logo', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                var json = await res.json();
                if (json.success) {
                    document.getElementById('devLogoUrl').value = json.logo_url;
                    updateLogoPreview(json.logo_url);
                    if (window.NuApp) NuApp.toast('Logo uploaded! (Draft updated, save settings to make it active)');
                } else {
                    if (window.NuApp) NuApp.toast(json.error || 'Failed to upload logo', 'error');
                }
            } catch (e) {
                console.error('[DevSettings] upload logo failed', e);
                if (window.NuApp) NuApp.toast('Logo upload failed', 'error');
            }

            // Clear file input value to allow re-upload of same file name
            fileInput.value = '';
        });
    }

    function init() {
        loadSettings();
        bindFileUpload();
    }

    return {
        init: init,
        addRow: addRow,
        removeLogo: removeLogo,
        loadSettings: loadSettings,
        saveSettings: saveSettings
    };
})();

// Bootstrap setting page module
DevSettings.init();
</script>
