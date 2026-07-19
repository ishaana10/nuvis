<?php
/**
 * modules/updater/updater.php
 * Admin UI: Git updates and configuration manager.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../core/module_bootstrap.php';
?>

<style>
.nu-upd-wrap { display:flex; flex-direction:column; gap:20px; }
.nu-upd-tabs { display:flex; gap:6px; border-bottom:1px solid var(--border-color,#e2e8f0); padding-bottom:0; }
.nu-upd-tab { padding:10px 20px; border:none; background:none; cursor:pointer; font-size:14px; font-weight:600; color:var(--text-muted,#64748b); border-bottom:3px solid transparent; transition:.2s; border-radius:4px 4px 0 0; }
.nu-upd-tab:hover { background:rgba(0,0,0,.04); color:var(--text-main); }
.nu-upd-tab.active { color:var(--primary,#0ea5e9); border-bottom-color:var(--primary,#0ea5e9); background:rgba(14,165,233,.06); }

.nu-upd-panel { display:none; }
.nu-upd-panel.active { display:block; }

.nu-upd-stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px; }
.nu-upd-stat-card { background:var(--bg-elevated,#fff); border:1px solid var(--border-color,#e2e8f0); border-radius:10px; padding:16px; }
.nu-upd-stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted,#64748b); margin-bottom:4px; }
.nu-upd-stat-value { font-size:18px; font-weight:700; color:var(--text-main); }

.nu-upd-console { background:#1e1e2e; color:#c9d1d9; font-family:monospace; font-size:13px; padding:16px; border-radius:8px; overflow:auto; max-height:400px; white-space:pre-wrap; border:1px solid #313244; }
</style>

<div class="nu-upd-wrap">
    <div class="nu-module-header">
        <h2 class="nu-module-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px">
                <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
            </svg>
            System Updater & Config
        </h2>
        <p style="color:var(--text-muted);font-size:13px;margin-top:4px">Manage application updates from Git and local configuration.</p>
    </div>

    <div class="nu-upd-tabs">
        <button class="nu-upd-tab active" onclick="nuUpdTab(this,'upd-dash')">Dashboard</button>
        <button class="nu-upd-tab" onclick="nuUpdTab(this,'upd-updates')">Updates</button>
        <button class="nu-upd-tab" onclick="nuUpdTab(this,'upd-history')">History</button>
        <button class="nu-upd-tab" onclick="nuUpdTab(this,'upd-config')">Configuration</button>
    </div>

    <!-- Dashboard -->
    <div class="nu-upd-panel active" id="upd-dash">
        <div class="nu-upd-stat-grid">
            <div class="nu-upd-stat-card">
                <div class="nu-upd-stat-label">Version</div>
                <div class="nu-upd-stat-value" id="upd-stat-version">...</div>
            </div>
            <div class="nu-upd-stat-card">
                <div class="nu-upd-stat-label">Branch</div>
                <div class="nu-upd-stat-value" id="upd-stat-branch">...</div>
            </div>
            <div class="nu-upd-stat-card">
                <div class="nu-upd-stat-label">Git Status</div>
                <div class="nu-upd-stat-value" id="upd-stat-git" style="font-size:14px">Checking...</div>
            </div>
        </div>

        <div class="nu-card">
            <div class="nu-card-header"><h3 class="nu-card-title">System Status</h3></div>
            <div class="nu-card-body">
                <div class="nu-upd-console" id="upd-status-console">Loading status...</div>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" style="margin-top:10px" onclick="nuUpdGetStatus()">&#x21BB; Refresh Status</button>
            </div>
        </div>
    </div>

    <!-- Updates -->
    <div class="nu-upd-panel" id="upd-updates">
        <div class="nu-card">
            <div class="nu-card-header"><h3 class="nu-card-title">Git Update</h3></div>
            <div class="nu-card-body">
                <p style="margin-bottom:16px;font-size:14px">Fetch changes from the remote repository and pull them into the selected branch.</p>

                <div style="display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px;">
                    <div class="nu-field" style="width: 250px;">
                        <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px;">Update Branch</label>
                        <select id="updaterBranchSelect" class="nu-input">
                            <option value="main">Loading branches...</option>
                        </select>
                    </div>
                    <button class="nu-btn nu-btn-ghost" onclick="nuUpdSaveBranchPref()">Save Preference</button>
                    <button class="nu-btn nu-btn-primary" onclick="nuUpdGitFetch()">Fetch origin</button>
                    <button class="nu-btn nu-btn-success" onclick="nuUpdGitPull()">1-Click Pull Update</button>
                </div>

                <div class="nu-upd-console" id="upd-git-console">Console output will appear here...</div>
            </div>
        </div>

        <div class="nu-card" style="margin-top: 20px;">
            <div class="nu-card-header"><h3 class="nu-card-title">Git Connection Configuration</h3></div>
            <div class="nu-card-body">
                <p style="margin-bottom:16px;font-size:14px">Configure custom paths and repository locations for Git commands.</p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="nu-field">
                        <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px;">Git Executable Path</label>
                        <input type="text" id="git_path" class="nu-input" placeholder="e.g. git or /usr/bin/git">
                    </div>
                    <div class="nu-field">
                        <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px;">Git Repository Root Directory</label>
                        <input type="text" id="git_repo_dir" class="nu-input" placeholder="e.g. /app or /var/www/nbv5">
                    </div>
                </div>

                <button class="nu-btn nu-btn-primary" onclick="nuUpdSaveGitSettings()">Save Connection Settings</button>
            </div>
        </div>
    </div>

    <!-- History -->
    <div class="nu-upd-panel" id="upd-history">
        <div class="nu-card">
            <div class="nu-card-header"><h3 class="nu-card-title">Commit History</h3></div>
            <div class="nu-card-body">
                <div id="upd-history-list">Loading commits...</div>
            </div>
        </div>
    </div>

    <!-- Configuration -->
    <div class="nu-upd-panel" id="upd-config">
        <div class="nu-card">
            <div class="nu-card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3 class="nu-card-title">config.local.php</h3>
                <button class="nu-btn nu-btn-primary nu-btn-sm" id="upd-config-save" onclick="nuUpdConfigSave()">Save Changes</button>
            </div>
            <div class="nu-card-body">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Edit your local environment settings. A backup is created automatically before each save.</p>
                <div id="upd-config-editor" style="height:400px;border:1px solid var(--border-color,#e2e8f0);border-radius:8px"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var _api = 'api/updater.php';
    var _editor = null;

    window.nuUpdTab = function(btn, panelId) {
        document.querySelectorAll('.nu-upd-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nu-upd-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(panelId).classList.add('active');

        if (panelId === 'upd-history') nuUpdGetHistory();
        if (panelId === 'upd-config')  nuUpdConfigRead();
    };

    window.nuUpdGetStatus = function() {
        fetch(_api + '?action=git_status')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                document.getElementById('upd-stat-version').textContent = d.version;
                document.getElementById('upd-stat-branch').textContent = d.branch;
                document.getElementById('upd-stat-git').textContent = d.status.includes('nothing to commit') ? 'Up to date' : 'Modified';
                document.getElementById('upd-status-console').textContent = d.status;

                // Load connection settings values
                if (document.getElementById('git_path')) {
                    document.getElementById('git_path').value = d.git_path || 'git';
                }
                if (document.getElementById('git_repo_dir')) {
                    document.getElementById('git_repo_dir').value = d.git_repo_dir || '';
                }

                const sel = document.getElementById('updaterBranchSelect');
                if (d.remote_branches) {
                    sel.innerHTML = '';
                    d.remote_branches.forEach(b => {
                        let opt = document.createElement('option');
                        opt.value = b;
                        opt.textContent = b;
                        if (b === d.selected_branch) opt.selected = true;
                        sel.appendChild(opt);
                    });
                }
            });
    };

    window.nuUpdSaveBranchPref = function() {
        const branch = document.getElementById('updaterBranchSelect').value;
        if (!branch) return;
        fetch(_api + '?action=save_branch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch: branch })
        }).then(r => r.json()).then(res => {
            if (res.success) {
                alert('Branch preference saved.');
            } else {
                alert('Error saving branch: ' + res.error);
            }
        });
    };

    window.nuUpdSaveGitSettings = function() {
        const gitPath = document.getElementById('git_path').value;
        const gitRepoDir = document.getElementById('git_repo_dir').value;
        const branch = document.getElementById('updaterBranchSelect').value || 'main';

        fetch(_api + '?action=save_git_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                git_path: gitPath,
                git_repo_dir: gitRepoDir,
                update_branch: branch
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Git Connection Settings saved successfully.');
                nuUpdGetStatus();
            } else {
                alert('Error saving settings: ' + res.error);
            }
        });
    };

    window.nuUpdGitFetch = function() {
        var console = document.getElementById('upd-git-console');
        console.textContent = 'Fetching changes from origin...';
        fetch(_api + '?action=git_fetch')
            .then(r => r.json())
            .then(d => {
                console.textContent = d.output || 'No output from fetch.';
                nuUpdGetStatus();
            });
    };

    window.nuUpdGitPull = function() {
        if (!confirm('This will pull the latest changes from the selected branch. Continue?')) return;
        var console = document.getElementById('upd-git-console');
        console.textContent = 'Pulling from selected branch...';
        fetch(_api + '?action=git_pull')
            .then(r => r.json())
            .then(d => {
                console.textContent = d.output || 'No output from pull.';
                if (d.pulled_branch) {
                    console.textContent += '\n\nUpdate finished successfully on branch: ' + d.pulled_branch;
                }
                nuUpdGetStatus();
            });
    };

    window.nuUpdGetHistory = function() {
        var list = document.getElementById('upd-history-list');
        list.textContent = 'Loading...';
        fetch(_api + '?action=git_log')
            .then(r => r.json())
            .then(d => {
                if (!d.success) { list.textContent = d.error; return; }
                var html = '<table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;border-bottom:2px solid var(--border-color,#e2e8f0)">';
                html += '<th style="padding:10px">Hash</th><th style="padding:10px">Author</th><th style="padding:10px">Date</th><th style="padding:10px">Message</th></tr></thead><tbody>';
                d.commits.forEach(c => {
                    html += '<tr style="border-bottom:1px solid var(--border-color,#e2e8f0)">';
                    html += '<td style="padding:10px"><code style="background:var(--bg-subtle,#f8fafc);padding:2px 6px;border-radius:4px">'+c.hash+'</code></td>';
                    html += '<td style="padding:10px">'+c.author+'</td>';
                    html += '<td style="padding:10px;white-space:nowrap">'+c.date+'</td>';
                    html += '<td style="padding:10px">'+c.msg+'</td></tr>';
                });
                html += '</tbody></table>';
                list.innerHTML = html;
            });
    };

    window.nuUpdConfigRead = function() {
        fetch(_api + '?action=config_read')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                if (!_editor && window.ace) {
                    _editor = ace.edit('upd-config-editor');
                    _editor.setTheme('ace/theme/one_dark');
                    _editor.session.setMode('ace/mode/php');
                    _editor.setOptions({ fontSize: '13px' });
                }
                if (_editor) {
                    _editor.setValue(d.content, -1);
                } else {
                    document.getElementById('upd-config-editor').innerHTML = '<textarea id="upd-config-ta" style="width:100%;height:100%;border:none;padding:12px;font-family:monospace">'+d.content+'</textarea>';
                }
            });
    };

    window.nuUpdConfigSave = function() {
        var content = _editor ? _editor.getValue() : document.getElementById('upd-config-ta').value;
        if (!confirm('Save changes to config.local.php?')) return;

        var btn = document.getElementById('upd-config-save');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch(_api + '?action=config_write', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content: content })
        })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
            if (d.success) {
                alert('Configuration saved successfully.');
            } else {
                alert('Error: ' + d.error);
            }
        });
    };

    nuUpdGetStatus();
})();
</script>
