<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db = NuDatabase::getInstance();

// Retrieve initial API Keys
$apiKeys = $db->fetchAll("SELECT * FROM nu_api_tokens ORDER BY token_created_at DESC LIMIT 50");
?>

<div class="nu-api-manager-container" style="padding: 12px 0;">

    <!-- Tab Navigation -->
    <div class="nu-tabs" style="display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color, #e2e8f0); padding-bottom: 8px;">
        <button class="nu-tab-btn active" onclick="switchApiTab('endpointsTab', this)" style="background: none; border: none; font-size: 15px; font-weight: 600; padding: 6px 16px; cursor: pointer; color: var(--accent, #4f6bed); border-bottom: 2px solid var(--accent, #4f6bed); margin-bottom: -10px; transition: all 0.2s;">
            API Endpoints Designer
        </button>
        <button class="nu-tab-btn" onclick="switchApiTab('tokensTab', this)" style="background: none; border: none; font-size: 15px; font-weight: 500; padding: 6px 16px; cursor: pointer; color: var(--text-muted, #718096); transition: all 0.2s;">
            Authorized Access Keys
        </button>
        <button class="nu-tab-btn" onclick="switchApiTab('logsTab', this); fetchTrafficLogs();" style="background: none; border: none; font-size: 15px; font-weight: 500; padding: 6px 16px; cursor: pointer; color: var(--text-muted, #718096); transition: all 0.2s;">
            Traffic & Execution Logs
        </button>
    </div>

    <!-- 1. Endpoints Tab -->
    <div id="endpointsTab" class="nu-api-tab">
        <div class="nu-card">
            <div class="nu-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="nu-card-title" style="margin: 0; font-size: 18px; font-weight: 600;">Declared API Routes</h3>
                <button class="nu-btn nu-btn-primary" onclick="openEndpointModal()">+ Create API Route</button>
            </div>
            <div class="nu-table-wrap" style="overflow-x: auto;">
                <table class="nu-table" style="width: 100%; border-collapse: collapse;" id="endpointsTable">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px 12px;">Route Name</th>
                            <th style="padding: 10px 12px;">Method & Route Path</th>
                            <th style="padding: 10px 12px;">Target Map</th>
                            <th style="padding: 10px 12px;">Status</th>
                            <th style="padding: 10px 12px;">Created At</th>
                            <th style="padding: 10px 12px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="endpointsTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">Loading endpoints...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. Tokens Tab -->
    <div id="tokensTab" class="nu-api-tab" style="display: none;">
        <div class="nu-card">
            <div class="nu-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="nu-card-title" style="margin: 0; font-size: 18px; font-weight: 600;">Authorized API Tokens</h3>
                <button class="nu-btn nu-btn-secondary" onclick="NuApp.loadModule('integrations')" style="background: var(--bg-secondary);">Configure in Integrations Module &rarr;</button>
            </div>
            <div class="nu-table-wrap" style="overflow-x: auto;">
                <table class="nu-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px 12px;">Token Name</th>
                            <th style="padding: 10px 12px;">Bearer Token Key</th>
                            <th style="padding: 10px 12px;">Scope Account</th>
                            <th style="padding: 10px 12px;">Created Date</th>
                            <th style="padding: 10px 12px;">Expiration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $key): ?>
                        <tr style="border-bottom: 1px solid var(--border-color, #edf2f7);">
                            <td style="padding: 12px;">
                                <strong><?php echo htmlspecialchars($key['token_name'] ?? 'REST Key'); ?></strong>
                            </td>
                            <td style="padding: 12px;">
                                <code style="background: var(--bg-secondary, #edf2f7); padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                                    <?php echo substr(htmlspecialchars($key['token_key']), 0, 16); ?>...
                                </code>
                            </td>
                            <td style="padding: 12px;">
                                <span class="nu-badge" style="background: #f0fdfa; color: #0d9488; font-weight: 600; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    User ID: <?php echo htmlspecialchars((string)($key['token_user_id'] ?? '-')); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: var(--text-secondary); font-size: 13px;">
                                <?php echo date('M j, Y H:i', strtotime($key['token_created_at'])); ?>
                            </td>
                            <td style="padding: 12px; color: var(--text-secondary); font-size: 13px;">
                                <?php
                                if (empty($key['token_expires_at'])) {
                                    echo '<span style="color:#059669; font-weight:600;">Never Expires</span>';
                                } else {
                                    $expired = strtotime($key['token_expires_at']) < time();
                                    $col = $expired ? '#dc2626' : 'var(--text)';
                                    $suff = $expired ? ' (Expired)' : '';
                                    echo '<span style="color:' . $col . '">' . date('M j, Y', strtotime($key['token_expires_at'])) . $suff . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($apiKeys)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted); font-style: italic;">No active REST API tokens generated. Create a key in the Integrations module to access endpoints.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Traffic Logs Tab -->
    <div id="logsTab" class="nu-api-tab" style="display: none;">
        <div class="nu-card">
            <div class="nu-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="nu-card-title" style="margin: 0; font-size: 18px; font-weight: 600;">Execution & Performance logs</h3>
                <button class="nu-btn nu-btn-danger" onclick="clearTrafficLogs()" style="background: #fef2f2; color: #dc2626; border-color: #fca5a5;">Clear Logs</button>
            </div>
            <div class="nu-table-wrap" style="overflow-x: auto;">
                <table class="nu-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                            <th style="padding: 10px 12px;">Method & Route</th>
                            <th style="padding: 10px 12px;">Access Token / User ID</th>
                            <th style="padding: 10px 12px;">Response Code</th>
                            <th style="padding: 10px 12px;">Duration (ms)</th>
                            <th style="padding: 10px 12px;">Executed At</th>
                            <th style="padding: 10px 12px; text-align: right;">Inspect</th>
                        </tr>
                    </thead>
                    <tbody id="trafficLogsBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">Loading logs...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ════════════════════════ MODAL: API ENDPOINT CONFIG ════════════════════════ -->
<div class="nu-modal-overlay" id="endpointModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
    <div class="nu-modal" style="background: var(--card-bg, #fff); border-radius: 12px; width: 100%; max-width: 720px; padding: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;">

        <div class="nu-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            <h3 class="nu-modal-title" id="endpointModalTitle" style="margin: 0; font-size: 18px; font-weight: 600;">Add API Route</h3>
            <button class="nu-modal-close" onclick="closeEndpointModal()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="nu-modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" id="epId">

            <div style="display: flex; gap: 12px; width: 100%;">
                <div style="flex: 2;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Endpoint Name <span style="color:red">*</span></label>
                    <input type="text" class="nu-input" id="epName" placeholder="e.g. Sync Customers List" style="width: 100%;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">HTTP Method <span style="color:red">*</span></label>
                    <select class="nu-input" id="epMethod" style="width: 100%;">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                        <option value="ALL">ALL (Any method)</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">API Route Path <span style="color:red">*</span></label>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-family: monospace; font-size: 13px; background: var(--bg-secondary, #edf2f7); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color);">api/gateway.php</span>
                    <input type="text" class="nu-input" id="epRoute" placeholder="/v1/customers" style="width: 100%; font-family: monospace;">
                </div>
                <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">Dynamic trailing IDs can be matched for GET, PUT, and DELETE methods (e.g., requesting <code>api/gateway.php/v1/customers/123</code> matches <code>/v1/customers</code> and parses <code>123</code> as the ID).</small>
            </div>

            <div style="display: flex; gap: 12px; width: 100%;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Endpoint Type <span style="color:red">*</span></label>
                    <select class="nu-input" id="epType" style="width: 100%;" onchange="onEndpointTypeChange()">
                        <option value="form">Form Integration</option>
                        <option value="report">Report Fetcher / PDF Generator</option>
                        <option value="dashboard">Dashboard Widget Metrics</option>
                        <option value="custom">Custom PHP/SQL Script</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Target Component <span style="color:red">*</span></label>
                    <select class="nu-input" id="epTarget" style="width: 100%;">
                        <option value="">-- Choose target --</option>
                    </select>
                </div>
            </div>

            <!-- Custom Script Configuration Section -->
            <div id="customConfigSection" style="display: none; flex-direction: column; gap: 12px; background: var(--bg-secondary, #edf2f7); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="display: flex; gap: 12px;">
                    <button class="nu-btn nu-btn-sm" type="button" onclick="switchCustomScriptTab('php')" id="customPhpTabBtn" style="font-weight: 600;">Custom PHP Script</button>
                    <button class="nu-btn nu-btn-sm" type="button" onclick="switchCustomScriptTab('sql')" id="customSqlTabBtn">Custom SQL Query</button>
                </div>

                <div id="customPhpContainer">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Sandbox PHP Code</label>
                    <textarea class="nu-input" id="epCustomPhp" rows="8" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder='// Variables available: $db (NuDatabase connection), $request (array with method, payload, headers), $user (active token user details)
// Return values: populate $response variable as array

$response = [
    "success" => true,
    "user_scope" => $user["usr_username"],
    "message" => "Hello, custom script executed!"
];'></textarea>
                </div>

                <div id="customSqlContainer" style="display: none;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Custom SQL Query</label>
                    <textarea class="nu-input" id="epCustomSql" rows="4" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder='SELECT id, name, created_at FROM your_table WHERE status = :status LIMIT 50'></textarea>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">Only SELECT statements are allowed. Named parameter variables (e.g. <code>:status</code>) are dynamically bound from query params.</small>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="epActive" checked style="cursor: pointer;">
                <label for="epActive" style="font-size: 13px; font-weight: 500; cursor: pointer;">Enable this API Route</label>
            </div>

        </div>

        <div class="nu-modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 16px;">
            <button class="nu-btn nu-btn-secondary" onclick="closeEndpointModal()" style="background: none; border-color: var(--border-color);">Cancel</button>
            <button class="nu-btn nu-btn-primary" onclick="saveEndpoint()">Save Endpoint</button>
        </div>
    </div>
</div>

<!-- ════════════════════════ MODAL: INSPECT TRAFFIC LOG ════════════════════════ -->
<div class="nu-modal-overlay" id="logInspectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1060; align-items: center; justify-content: center; padding: 20px;">
    <div class="nu-modal" style="background: var(--card-bg, #fff); border-radius: 12px; width: 100%; max-width: 680px; padding: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;">

        <div class="nu-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            <h3 class="nu-modal-title" style="margin: 0; font-size: 16px; font-weight: 600;">Inspect API Payload</h3>
            <button class="nu-modal-close" onclick="closeLogInspectModal()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="nu-modal-body" style="display: flex; flex-direction: column; gap: 14px;">
            <div>
                <strong style="font-size: 13px; display: block; margin-bottom: 6px; color: var(--text-secondary);">Request Payload</strong>
                <pre id="inspectRequest" style="background: #0f172a; color: #38bdf8; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; overflow-x: auto; max-height: 180px;"></pre>
            </div>
            <div>
                <strong style="font-size: 13px; display: block; margin-bottom: 6px; color: var(--text-secondary);">Response Payload</strong>
                <pre id="inspectResponse" style="background: #0f172a; color: #34d399; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; overflow-x: auto; max-height: 240px;"></pre>
            </div>
        </div>

        <div class="nu-modal-footer" style="display: flex; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 16px;">
            <button class="nu-btn nu-btn-primary" onclick="closeLogInspectModal()">Done</button>
        </div>
    </div>
</div>

<!-- ════════════════════════ CLIENT-SIDE SCRIPTS ════════════════════════ -->
<script>
// Tab Switching
function switchApiTab(tabId, btn) {
    document.querySelectorAll('.nu-api-tab').forEach(t => t.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';

    document.querySelectorAll('.nu-tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--text-muted, #718096)';
        b.style.borderBottom = 'none';
        b.style.fontWeight = '500';
    });

    btn.classList.add('active');
    btn.style.color = 'var(--accent, #4f6bed)';
    btn.style.borderBottom = '2px solid var(--accent, #4f6bed)';
    btn.style.fontWeight = '600';
}

// Global caching for component lists (Forms, Reports, Widgets)
let apiTargetsCache = null;
let currentEndpointsList = [];
let customScriptTab = 'php';

// Fetch Endpoints
async function fetchEndpoints() {
    const tbody = document.getElementById('endpointsTableBody');
    if (!tbody) return;

    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=list', { credentials: 'same-origin' });
        if (res.success) {
            currentEndpointsList = res.endpoints;
            if (res.endpoints.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted); font-style: italic;">No custom API endpoints registered. Click "Create API Route" to get started!</td>
                    </tr>`;
                return;
            }

            tbody.innerHTML = '';
            res.endpoints.forEach(ep => {
                const tr = document.createElement('tr');
                tr.style.cssText = 'border-bottom: 1px solid var(--border-color, #edf2f7); transition: background 0.15s;';
                tr.onmouseenter = () => tr.style.background = 'var(--bg-secondary, #f7fafc)';
                tr.onmouseleave = () => tr.style.background = 'none';

                const statusColor = ep.endpoint_active == 1 ? '#dcfce7; color: #166534;' : '#fee2e2; color: #991b1b;';
                const statusLabel = ep.endpoint_active == 1 ? 'Active' : 'Inactive';

                // Method label color
                let methodBadgeBg = '#e0f2fe; color: #0369a1;'; // default GET (light blue)
                if (ep.endpoint_method === 'POST') methodBadgeBg = '#dcfce7; color: #166534;'; // light green
                if (ep.endpoint_method === 'PUT') methodBadgeBg = '#fef3c7; color: #78350f;'; // light orange/yellow
                if (ep.endpoint_method === 'DELETE') methodBadgeBg = '#fee2e2; color: #991b1b;'; // light red
                if (ep.endpoint_method === 'ALL') methodBadgeBg = '#f3e8ff; color: #581c87;'; // purple

                tr.innerHTML = `
                    <td style="padding: 12px;"><strong>${escapeHtmlLocal(ep.endpoint_name)}</strong></td>
                    <td style="padding: 12px; font-family: monospace;">
                        <span class="nu-badge" style="border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: 700; ${methodBadgeBg} margin-right: 6px;">${ep.endpoint_method}</span>
                        <code>${escapeHtmlLocal(ep.endpoint_route)}</code>
                    </td>
                    <td style="padding: 12px; font-size: 13px;">
                        <span style="text-transform: capitalize; font-weight: 600; color: var(--accent);">${ep.endpoint_type}</span>: <code>${escapeHtmlLocal(ep.endpoint_target)}</code>
                    </td>
                    <td style="padding: 12px;">
                        <span style="border-radius: 12px; padding: 4px 10px; font-size: 12px; font-weight: 600; display: inline-block; background: ${statusColor}">
                            ${statusLabel}
                        </span>
                    </td>
                    <td style="padding: 12px; color: var(--text-secondary); font-size: 13px;">
                        ${ep.endpoint_created_at ? ep.endpoint_created_at.substring(0, 16) : '-'}
                    </td>
                    <td style="padding: 12px; text-align: right; white-space: nowrap;">
                        <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="editEndpoint(${ep.endpoint_id})" style="margin-right: 4px;">Edit</button>
                        <button class="nu-btn nu-btn-secondary nu-btn-sm" onclick="testEndpointRoute('${ep.endpoint_route}')" style="margin-right: 4px; background: #f0fdf4; color: #16a34a; border-color: #bbf7d0;">Verify</button>
                        <button class="nu-btn nu-btn-danger nu-btn-sm" onclick="deleteEndpoint(${ep.endpoint_id})">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load endpoints: ${escapeHtmlLocal(e.message)}</td></tr>`;
    }
}

// Fetch Component Targets
async function fetchComponentTargets() {
    if (apiTargetsCache) return apiTargetsCache;
    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=targets', { credentials: 'same-origin' });
        if (res.success) {
            apiTargetsCache = res;
            return res;
        }
    } catch (e) {
        console.error('Failed to load targets dropdown lists', e);
    }
    return null;
}

// Endpoint Type Dropdown Change
async function onEndpointTypeChange(selectedValue = null) {
    const type = document.getElementById('epType').value;
    const targetSelect = document.getElementById('epTarget');
    targetSelect.innerHTML = '<option value="">-- Loading targets... --</option>';

    const targets = await fetchComponentTargets();
    if (!targets) {
        targetSelect.innerHTML = '<option value="">Error loading list</option>';
        return;
    }

    targetSelect.innerHTML = '<option value="">-- Choose target --</option>';

    let list = [];
    if (type === 'form') list = targets.forms;
    if (type === 'report') list = targets.reports;
    if (type === 'dashboard') list = targets.widgets;

    if (type === 'custom') {
        document.getElementById('customConfigSection').style.display = 'flex';
        targetSelect.innerHTML = '<option value="custom_executor" selected>Custom Executor Module</option>';
        targetSelect.disabled = true;
    } else {
        document.getElementById('customConfigSection').style.display = 'none';
        targetSelect.disabled = false;

        list.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.code;
            opt.textContent = `${item.name} (${item.code})`;
            if (selectedValue && item.code === selectedValue) {
                opt.selected = true;
            }
            targetSelect.appendChild(opt);
        });
    }
}

function switchCustomScriptTab(tab) {
    customScriptTab = tab;
    if (tab === 'php') {
        document.getElementById('customPhpContainer').style.display = 'block';
        document.getElementById('customSqlContainer').style.display = 'none';
        document.getElementById('customPhpTabBtn').style.fontWeight = '600';
        document.getElementById('customSqlTabBtn').style.fontWeight = 'normal';
    } else {
        document.getElementById('customPhpContainer').style.display = 'none';
        document.getElementById('customSqlContainer').style.display = 'block';
        document.getElementById('customPhpTabBtn').style.fontWeight = 'normal';
        document.getElementById('customSqlTabBtn').style.fontWeight = '600';
    }
}

// Open Endpoint Modal
async function openEndpointModal() {
    document.getElementById('epId').value = '';
    document.getElementById('epName').value = '';
    document.getElementById('epRoute').value = '';
    document.getElementById('epMethod').value = 'GET';
    document.getElementById('epType').value = 'form';
    document.getElementById('epActive').checked = true;
    document.getElementById('epCustomPhp').value = '';
    document.getElementById('epCustomSql').value = '';

    document.getElementById('endpointModalTitle').textContent = 'Add Custom API Route';
    document.getElementById('customConfigSection').style.display = 'none';

    await onEndpointTypeChange();
    document.getElementById('endpointModal').style.display = 'flex';
}

// Edit Endpoint
async function editEndpoint(id) {
    const ep = currentEndpointsList.find(x => x.endpoint_id == id);
    if (!ep) return;

    document.getElementById('epId').value = ep.endpoint_id;
    document.getElementById('epName').value = ep.endpoint_name;
    document.getElementById('epRoute').value = ep.endpoint_route;
    document.getElementById('epMethod').value = ep.endpoint_method;
    document.getElementById('epType').value = ep.endpoint_type;
    document.getElementById('epActive').checked = parseInt(ep.endpoint_active) === 1;

    document.getElementById('endpointModalTitle').textContent = 'Edit API Route';

    // Parse custom configuration script
    try {
        const config = JSON.parse(ep.endpoint_config || '{}');
        document.getElementById('epCustomPhp').value = config.php_script || '';
        document.getElementById('epCustomSql').value = config.sql_query || '';
        if (config.php_script) {
            switchCustomScriptTab('php');
        } else if (config.sql_query) {
            switchCustomScriptTab('sql');
        }
    } catch (e) {
        document.getElementById('epCustomPhp').value = '';
        document.getElementById('epCustomSql').value = '';
    }

    await onEndpointTypeChange(ep.endpoint_target);
    document.getElementById('endpointModal').style.display = 'flex';
}

function closeEndpointModal() {
    document.getElementById('endpointModal').style.display = 'none';
}

// Save Endpoint
async function saveEndpoint() {
    const id = document.getElementById('epId').value;
    const name = document.getElementById('epName').value.trim();
    const route = document.getElementById('epRoute').value.trim();
    const method = document.getElementById('epMethod').value;
    const type = document.getElementById('epType').value;
    const target = document.getElementById('epTarget').value;
    const active = document.getElementById('epActive').checked ? 1 : 0;

    if (name === '' || route === '' || target === '') {
        NuApp.toast('All fields are required!', 'error');
        return;
    }

    // Capture custom configs
    const config = {
        php_script: document.getElementById('epCustomPhp').value,
        sql_query: document.getElementById('epCustomSql').value
    };

    const payload = {
        endpoint_id: id ? parseInt(id) : null,
        endpoint_name: name,
        endpoint_route: route,
        endpoint_method: method,
        endpoint_type: type,
        endpoint_target: target,
        endpoint_config: JSON.stringify(config),
        endpoint_active: active
    };

    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=save', {
            method: 'POST',
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        });

        if (res.success) {
            NuApp.toast(res.message || 'API endpoint saved successfully!');
            closeEndpointModal();
            fetchEndpoints();
        } else {
            NuApp.toast(res.error || 'Failed to save endpoint', 'error');
        }
    } catch (e) {
        NuApp.toast('Error: ' + e.message, 'error');
    }
}

// Delete Endpoint
async function deleteEndpoint(id) {
    if (!confirm('Are you completely sure you want to delete this API Route permanently? External systems utilizing it will lose connection immediately.')) return;
    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=delete&id=' + id, { credentials: 'same-origin' });
        if (res.success) {
            NuApp.toast(res.message || 'API route deleted successfully');
            fetchEndpoints();
        } else {
            NuApp.toast(res.error || 'Failed to delete route', 'error');
        }
    } catch (e) {
        NuApp.toast(e.message, 'error');
    }
}

// Verify endpoint URL mapping
function testEndpointRoute(route) {
    const origin = window.location.origin;
    const path = window.location.pathname.replace('index.php', '').replace(/\/$/, '');
    const fullUrl = `${origin}${path}/api/gateway.php${route}`;

    // Prompt user to copy the verified live url endpoint
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;';

    const box = document.createElement('div');
    box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;width:92%;max-width:540px;box-shadow:0 10px 25px rgba(0,0,0,0.15);';

    box.innerHTML = `
        <h4 style="margin:0 0 12px; font-size:16px; font-weight:600;">⚡ Verify Endpoint Connection</h4>
        <p style="font-size:13px; line-height:1.5; color:var(--text-secondary); margin-bottom:12px;">This is the canonical live REST URL endpoint mapped for external integrations:</p>
        <div style="display:flex; gap:6px; align-items:center; margin-bottom:14px;">
            <input type="text" value="${fullUrl}" readonly id="copiedGateUrl" style="flex:1; font-family:monospace; font-size:12px; padding:8px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-secondary);">
            <button class="nu-btn" onclick="copyVerifyEndpointUrl()" style="background:#4f6bed; color:#fff; border:none; font-weight:600; padding:8px 14px;">Copy</button>
        </div>
        <p style="font-size:12px; color:var(--text-muted); line-height:1.4;"><strong>How to authorize:</strong> Inject the Bearer Access Key either inside <code>X-API-KEY</code> headers, <code>Authorization: Bearer [key]</code> headers, or <code>api_key</code> query parameters when making requests.</p>
        <div style="text-align:right; margin-top:20px;">
            <button class="nu-btn nu-btn-secondary" onclick="this.closest('div[style*=\"z-index:2000\"]').remove()">Done</button>
        </div>
    `;

    overlay.appendChild(box);
    document.body.appendChild(overlay);
}

function copyVerifyEndpointUrl() {
    const input = document.getElementById('copiedGateUrl');
    input.select();
    try {
        document.execCommand('copy');
        NuApp.toast('Endpoint URL copied to clipboard!');
    } catch (e) {
        NuApp.toast('Failed to copy. Please highlight and copy manually.', 'warning');
    }
}


// ── TRAFFIC LOGS ─────────────────────────────────────────────────────────────

let currentLogs = [];

async function fetchTrafficLogs() {
    const tbody = document.getElementById('trafficLogsBody');
    if (!tbody) return;

    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=logs', { credentials: 'same-origin' });
        if (res.success) {
            currentLogs = res.logs;
            if (res.logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted); font-style: italic;">No traffic logs captured yet. Execute an API request via your keys to see execution statistics.</td>
                    </tr>`;
                return;
            }

            tbody.innerHTML = '';
            res.logs.forEach((log, index) => {
                const tr = document.createElement('tr');
                tr.style.cssText = 'border-bottom: 1px solid var(--border-color, #edf2f7);';

                // Status Badge
                let statusBg = '#fee2e2; color: #991b1b;'; // red
                if (log.log_response_code >= 200 && log.log_response_code < 300) {
                    statusBg = '#dcfce7; color: #166534;'; // green
                }

                tr.innerHTML = `
                    <td style="padding: 12px; font-family: monospace;">
                        <span class="nu-badge" style="background:#e2e8f0; color:#1e293b; border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: 700; margin-right:6px;">${log.log_method}</span>
                        <code>${escapeHtmlLocal(log.log_route)}</code>
                    </td>
                    <td style="padding: 12px; font-size: 13px;">
                        <strong>${escapeHtmlLocal(log.log_token_name || 'API Client')}</strong><br>
                        <span style="font-size: 11px; color: var(--text-muted);">User scope: ${escapeHtmlLocal(log.log_user_id || 'N/A')}</span>
                    </td>
                    <td style="padding: 12px;">
                        <span style="border-radius: 12px; padding: 4px 10px; font-size: 12px; font-weight: 700; display: inline-block; background: ${statusBg}">
                            ${log.log_response_code}
                        </span>
                    </td>
                    <td style="padding: 12px; font-variant-numeric: tabular-nums; font-size: 13px; font-weight: 600;">
                        ${parseFloat(log.log_duration || 0).toFixed(1)} ms
                    </td>
                    <td style="padding: 12px; color: var(--text-secondary); font-size: 13px;">
                        ${log.log_created_at}
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="inspectLog(${index})">&#x1F50D; Inspect</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load traffic logs: ${escapeHtmlLocal(e.message)}</td></tr>`;
    }
}

async function clearTrafficLogs() {
    if (!confirm('Clear all captured traffic and execution logs from database?')) return;
    try {
        const res = await NuApp.apiJson('api/api_manager.php?action=clear_logs', {credentials: 'same-origin'});
        if (res.success) {
            NuApp.toast('Logs cleared');
            fetchTrafficLogs();
        }
    } catch (e) {
        NuApp.toast('Failed to clear: ' + e.message, 'error');
    }
}

function inspectLog(index) {
    const log = currentLogs[index];
    if (!log) return;

    let reqObj = {};
    let respObj = {};

    try { reqObj = JSON.parse(log.log_request_payload || '{}'); } catch (e) { reqObj = log.log_request_payload; }
    try { respObj = JSON.parse(log.log_response_payload || '{}'); } catch (e) { respObj = log.log_response_payload; }

    document.getElementById('inspectRequest').textContent = JSON.stringify(reqObj, null, 2);
    document.getElementById('inspectResponse').textContent = JSON.stringify(respObj, null, 2);

    document.getElementById('logInspectModal').style.display = 'flex';
}

function closeLogInspectModal() {
    document.getElementById('logInspectModal').style.display = 'none';
}


// Local Helper to escape HTML tags safely
function escapeHtmlLocal(str) {
    if (typeof str !== 'string') return String(str);
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Initial Bootstrapping
setTimeout(() => {
    fetchEndpoints();
}, 200);
</script>
