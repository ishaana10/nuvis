<?php
declare(strict_types=1);

/**
 * modules/ai/ai.php - Nuvis AI Agent Management Builder & Activity Dashboard
 */

require_once __DIR__ . '/../../core/Database.php';
$db = NuDatabase::getInstance();
$geminiKeyRow = $db->fetchOne("SELECT setting_value FROM nu_system_settings WHERE setting_key = 'gemini_api_key'");
$geminiApiKey = $geminiKeyRow['setting_value'] ?? '';
?>

<style>
.nu-ai-wrap { padding: 20px; font-family: inherit; }
.nu-ai-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.nu-ai-title { font-size: 20px; font-weight: 700; color: var(--text-color, #1e293b); margin: 0; }
.nu-ai-sub { font-size: 13px; color: #64748b; margin-top: 4px; }
.nu-ai-btn { background: #0284c7; color: #fff; border: none; padding: 7px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
.nu-ai-btn-outline { background: transparent; color: #334155; border: 1px solid #cbd5e1; padding: 7px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; margin-left: 6px; }
.nu-ai-btn:hover { background: #0369a1; }
.nu-ai-btn-outline:hover { background: #f1f5f9; }

/* Tabs */
.nu-ai-nav { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; gap: 8px; }
.nu-ai-nav-item { padding: 10px 18px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.nu-ai-nav-item.active { color: #0284c7; border-bottom-color: #0284c7; }

/* Tab Panes */
.nu-ai-pane { display: none; }
.nu-ai-pane.active { display: block; }

/* Cards & Tables */
.nu-ai-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
.nu-ai-card-header { padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 14px; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
.nu-ai-card-body { padding: 18px; }

.nu-ai-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
.nu-ai-table th { background: #f8fafc; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; }
.nu-ai-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.nu-ai-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.nu-badge-active { background: #dcfce7; color: #166534; }
.nu-badge-inactive { background: #f1f5f9; color: #64748b; }
.nu-badge-info { background: #e0f2fe; color: #0369a1; }

/* Grid Layout for Playground */
.nu-ai-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; }
.nu-ai-form-group { margin-bottom: 14px; }
.nu-ai-form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.nu-ai-input, .nu-ai-select, .nu-ai-textarea { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.nu-ai-textarea { resize: vertical; }

/* Modals */
.nu-ai-modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); z-index: 10000; display: none; align-items: center; justify-content: center; }
.nu-ai-modal-overlay.open { display: flex; }
.nu-ai-modal { background: #fff; border-radius: 10px; width: 680px; max-width: 92vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
.nu-ai-modal-header { padding: 14px 20px; background: #0f172a; color: #fff; font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
.nu-ai-modal-close { background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; }
.nu-ai-modal-body { padding: 20px; }
.nu-ai-modal-footer { padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }

.nu-tools-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; }
.nu-tool-item { font-size: 12px; display: flex; align-items: center; gap: 6px; color: #334155; }
</style>

<div class="nu-ai-wrap">
    <div class="nu-ai-header">
        <div>
            <h3 class="nu-ai-title"><i class="fas fa-robot text-primary mr-2"></i>AI Agents Builder & Studio</h3>
            <div class="nu-ai-sub">Configure autonomous agent definitions, prompt systems, tools, memory, and monitor execution traces.</div>
        </div>
        <div>
            <button class="nu-ai-btn" onclick="nuAiStudio.openAgentModal()"><i class="fas fa-plus"></i> New Agent</button>
            <button class="nu-ai-btn-outline" onclick="nuAiStudio.openSettingsModal()"><i class="fas fa-key"></i> Gemini API Key</button>
            <button class="nu-ai-btn-outline" onclick="nuAiStudio.switchTab('runs')"><i class="fas fa-history"></i> Activity Logs</button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="nu-ai-nav">
        <div class="nu-ai-nav-item active" id="nav-agents" onclick="nuAiStudio.switchTab('agents')"><i class="fas fa-cubes"></i> Agent Definitions</div>
        <div class="nu-ai-nav-item" id="nav-runs" onclick="nuAiStudio.switchTab('runs')"><i class="fas fa-tasks"></i> Execution Runs & Traces</div>
        <div class="nu-ai-nav-item" id="nav-playground" onclick="nuAiStudio.switchTab('playground')"><i class="fas fa-vial"></i> Test Playground</div>
    </div>

    <!-- Tab 1: Agent Definitions -->
    <div class="nu-ai-pane active" id="pane-agents">
        <div class="nu-ai-card">
            <table class="nu-ai-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Model</th>
                        <th>Tools Enabled</th>
                        <th>Memory Type</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="nuAgentsTableBody">
                    <tr><td colspan="8" style="text-align:center; padding: 30px; color: #64748b;">Loading agent definitions...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 2: Execution Runs -->
    <div class="nu-ai-pane" id="pane-runs">
        <div class="nu-ai-card">
            <table class="nu-ai-table">
                <thead>
                    <tr>
                        <th>Run ID</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Prompt Input</th>
                        <th>Tokens</th>
                        <th>Started</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="nuRunsTableBody">
                    <tr><td colspan="7" style="text-align:center; padding: 30px; color: #64748b;">Click tab to load activity logs.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Test Playground -->
    <div class="nu-ai-pane" id="pane-playground">
        <div class="nu-ai-grid">
            <div class="nu-ai-card">
                <div class="nu-ai-card-header">Select Agent & Input</div>
                <div class="nu-ai-card-body">
                    <div class="nu-ai-form-group">
                        <label>Select Agent</label>
                        <select class="nu-ai-select" id="pgAgentSelect">
                            <option value="">-- Select Agent --</option>
                        </select>
                    </div>
                    <div class="nu-ai-form-group">
                        <label>User Instruction / Prompt</label>
                        <textarea class="nu-ai-textarea" id="pgPromptInput" rows="5" placeholder="e.g. Query demo customer requests and summarize their statuses."></textarea>
                    </div>
                    <button class="nu-ai-btn" style="width:100%; justify-content:center;" onclick="nuAiStudio.runPlayground()"><i class="fas fa-paper-plane"></i> Run Execution</button>
                </div>
            </div>
            <div class="nu-ai-card">
                <div class="nu-ai-card-header">
                    <span>Agent Response & Reasoning Trace</span>
                    <span class="nu-ai-badge nu-badge-inactive" id="pgStatusBadge">Idle</span>
                </div>
                <div class="nu-ai-card-body" style="background:#f8fafc; min-height: 280px; font-family: monospace; white-space: pre-wrap; word-break: break-word;" id="pgOutputArea">
Select an agent, enter a prompt, and click Run Execution.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Agent Definition Editor -->
<div class="nu-ai-modal-overlay" id="agentModalOverlay">
    <div class="nu-ai-modal">
        <div class="nu-ai-modal-header">
            <span id="agentModalTitle">Configure AI Agent</span>
            <button class="nu-ai-modal-close" onclick="nuAiStudio.closeAgentModal()">&times;</button>
        </div>
        <div class="nu-ai-modal-body">
            <input type="hidden" id="agent_id" value="0">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="nu-ai-form-group">
                    <label>Agent Name *</label>
                    <input type="text" class="nu-ai-input" id="agent_name" required placeholder="e.g. Customer Service Agent">
                </div>
                <div class="nu-ai-form-group">
                    <label>Agent Code *</label>
                    <input type="text" class="nu-ai-input" id="agent_code" required placeholder="e.g. customer_service_agent">
                </div>
            </div>

            <div class="nu-ai-form-group">
                <label>System Prompt (Instructions)</label>
                <textarea class="nu-ai-textarea" id="agent_system_prompt" rows="4" placeholder="Describe the agent role, capabilities, boundaries, and expected formatting..."></textarea>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="nu-ai-form-group">
                    <label>Model</label>
                    <select class="nu-ai-select" id="agent_model">
                        <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                        <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                        <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                    </select>
                </div>
                <div class="nu-ai-form-group">
                    <label>Memory Strategy</label>
                    <select class="nu-ai-select" id="agent_memory_type">
                        <option value="conversation">Conversation Window</option>
                        <option value="entity">Entity / Context Facts</option>
                        <option value="none">Stateless / None</option>
                    </select>
                </div>
                <div class="nu-ai-form-group">
                    <label>Max Tokens</label>
                    <input type="number" class="nu-ai-input" id="agent_max_tokens" value="2000">
                </div>
            </div>

            <div class="nu-ai-form-group">
                <label>Available Tools (Capabilities)</label>
                <div class="nu-tools-grid">
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="query_records"> query_records</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="get_record"> get_record</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="create_record"> create_record</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="update_record"> update_record</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="run_procedure"> run_procedure</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="send_email"> send_email</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="call_webhook"> call_webhook</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="start_workflow"> start_workflow</label>
                    <label class="nu-tool-item"><input type="checkbox" class="tool-cb" value="advance_workflow"> advance_workflow</label>
                </div>
            </div>

            <div class="nu-ai-form-group">
                <label style="display:flex; align-items:center; gap:6px;">
                    <input type="checkbox" id="agent_active" checked> Agent Active
                </label>
            </div>
        </div>
        <div class="nu-ai-modal-footer">
            <button class="nu-ai-btn-outline" onclick="nuAiStudio.closeAgentModal()">Cancel</button>
            <button class="nu-ai-btn" onclick="nuAiStudio.saveAgent()"><i class="fas fa-save"></i> Save Agent Definition</button>
        </div>
    </div>
</div>

<!-- Modal: Gemini Settings -->
<div class="nu-ai-modal-overlay" id="settingsModalOverlay">
    <div class="nu-ai-modal" style="width: 480px;">
        <div class="nu-ai-modal-header">
            <span>Gemini API Key Configuration</span>
            <button class="nu-ai-modal-close" onclick="nuAiStudio.closeSettingsModal()">&times;</button>
        </div>
        <div class="nu-ai-modal-body">
            <div class="nu-ai-form-group">
                <label>Google Gemini API Key</label>
                <input type="password" class="nu-ai-input" id="gemini_api_key" value="<?= htmlspecialchars($geminiApiKey) ?>" placeholder="AIzaSy...">
                <div class="nu-ai-sub">Provided API keys are securely saved in `nu_system_settings`.</div>
            </div>
        </div>
        <div class="nu-ai-modal-footer">
            <button class="nu-ai-btn-outline" onclick="nuAiStudio.closeSettingsModal()">Cancel</button>
            <button class="nu-ai-btn" onclick="nuAiStudio.saveSettings()"><i class="fas fa-check"></i> Save Key</button>
        </div>
    </div>
</div>

<!-- Modal: Run Details Trace -->
<div class="nu-ai-modal-overlay" id="runDetailsModalOverlay">
    <div class="nu-ai-modal">
        <div class="nu-ai-modal-header">
            <span>Execution Trace & Step Audit</span>
            <button class="nu-ai-modal-close" onclick="nuAiStudio.closeRunDetailsModal()">&times;</button>
        </div>
        <div class="nu-ai-modal-body" id="runDetailsBody">
            Loading details...
        </div>
    </div>
</div>

<script>
window.nuAiStudio = {
    agents: [],

    init: function() {
        this.loadAgents();
    },

    switchTab: function(tabName) {
        document.querySelectorAll('.nu-ai-nav-item').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nu-ai-pane').forEach(el => el.classList.remove('active'));

        const nav = document.getElementById('nav-' + tabName);
        const pane = document.getElementById('pane-' + tabName);
        if (nav && pane) {
            nav.classList.add('active');
            pane.classList.add('active');
        }

        if (tabName === 'runs') {
            this.loadRuns();
        }
    },

    loadAgents: function() {
        fetch('api/agent.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.agents = data.data || [];
                    this.renderAgents();
                    this.populatePlaygroundSelect();
                }
            });
    },

    renderAgents: function() {
        const tbody = document.getElementById('nuAgentsTableBody');
        if (!this.agents || this.agents.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px; color: #64748b;">No agents configured yet. Click "New Agent" to create one.</td></tr>';
            return;
        }

        tbody.innerHTML = this.agents.map(a => `
            <tr>
                <td>${a.agent_id}</td>
                <td><code>${a.agent_code}</code></td>
                <td style="font-weight:600;">${a.agent_name}</td>
                <td><span class="nu-ai-badge nu-badge-info">${a.agent_model || 'gemini-1.5-flash'}</span></td>
                <td>${(a.agent_tools || []).map(t => `<span class="nu-ai-badge nu-badge-inactive" style="margin-right:4px;">${t}</span>`).join('')}</td>
                <td>${a.agent_memory_type || 'conversation'}</td>
                <td>${a.agent_active == 1 ? '<span class="nu-ai-badge nu-badge-active">Active</span>' : '<span class="nu-ai-badge nu-badge-inactive">Inactive</span>'}</td>
                <td style="text-align:right;">
                    <button class="nu-ai-btn-outline" style="padding:4px 8px;" onclick="nuAiStudio.editAgent(${a.agent_id})"><i class="fas fa-edit"></i></button>
                    <button class="nu-ai-btn-outline" style="padding:4px 8px; color:#e11d48;" onclick="nuAiStudio.deleteAgent(${a.agent_id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    },

    populatePlaygroundSelect: function() {
        const sel = document.getElementById('pgAgentSelect');
        sel.innerHTML = '<option value="">-- Select Agent --</option>' +
            this.agents.map(a => `<option value="${a.agent_id}">${a.agent_name} (${a.agent_code})</option>`).join('');
    },

    openAgentModal: function() {
        document.getElementById('agent_id').value = '0';
        document.getElementById('agent_name').value = '';
        document.getElementById('agent_code').value = '';
        document.getElementById('agent_system_prompt').value = '';
        document.getElementById('agent_model').value = 'gemini-1.5-flash';
        document.getElementById('agent_memory_type').value = 'conversation';
        document.getElementById('agent_max_tokens').value = '2000';
        document.getElementById('agent_active').checked = true;
        document.querySelectorAll('.tool-cb').forEach(cb => cb.checked = true);
        document.getElementById('agentModalTitle').innerText = 'Create New AI Agent';
        document.getElementById('agentModalOverlay').classList.add('open');
    },

    closeAgentModal: function() {
        document.getElementById('agentModalOverlay').classList.remove('open');
    },

    editAgent: function(id) {
        const agent = this.agents.find(a => a.agent_id == id);
        if (!agent) return;

        document.getElementById('agent_id').value = agent.agent_id;
        document.getElementById('agent_name').value = agent.agent_name;
        document.getElementById('agent_code').value = agent.agent_code;
        document.getElementById('agent_system_prompt').value = agent.agent_system_prompt;
        document.getElementById('agent_model').value = agent.agent_model || 'gemini-1.5-flash';
        document.getElementById('agent_memory_type').value = agent.agent_memory_type || 'conversation';
        document.getElementById('agent_max_tokens').value = agent.agent_max_tokens || 2000;
        document.getElementById('agent_active').checked = agent.agent_active == 1;

        const tools = agent.agent_tools || [];
        document.querySelectorAll('.tool-cb').forEach(cb => {
            cb.checked = tools.includes(cb.value);
        });

        document.getElementById('agentModalTitle').innerText = 'Edit AI Agent #' + agent.agent_id;
        document.getElementById('agentModalOverlay').classList.add('open');
    },

    saveAgent: function() {
        const id = document.getElementById('agent_id').value;
        const name = document.getElementById('agent_name').value;
        const code = document.getElementById('agent_code').value;

        if (!name || !code) {
            alert('Agent Name and Code are required');
            return;
        }

        const selectedTools = [];
        document.querySelectorAll('.tool-cb:checked').forEach(cb => selectedTools.push(cb.value));

        const payload = {
            agent_id: id,
            agent_name: name,
            agent_code: code,
            agent_system_prompt: document.getElementById('agent_system_prompt').value,
            agent_model: document.getElementById('agent_model').value,
            agent_memory_type: document.getElementById('agent_memory_type').value,
            agent_max_tokens: document.getElementById('agent_max_tokens').value,
            agent_active: document.getElementById('agent_active').checked ? 1 : 0,
            agent_tools: selectedTools
        };

        fetch('api/agent.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.closeAgentModal();
                this.loadAgents();
            } else {
                alert('Error saving agent: ' + (data.error || 'Unknown error'));
            }
        });
    },

    deleteAgent: function(id) {
        if (!confirm('Are you sure you want to delete this agent?')) return;
        fetch('api/agent.php?action=delete&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) this.loadAgents();
            });
    },

    openSettingsModal: function() {
        document.getElementById('settingsModalOverlay').classList.add('open');
    },

    closeSettingsModal: function() {
        document.getElementById('settingsModalOverlay').classList.remove('open');
    },

    saveSettings: function() {
        const key = document.getElementById('gemini_api_key').value;
        fetch('api/agent.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({agent_id: 0, gemini_api_key: key})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('API Key saved successfully!');
                this.closeSettingsModal();
            }
        });
    },

    loadRuns: function() {
        const tbody = document.getElementById('nuRunsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px; color: #64748b;">Loading execution logs...</td></tr>';

        fetch('api/agent.php?action=runs')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px; color: #64748b;">No execution runs recorded yet.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.data.map(r => `
                        <tr>
                            <td>#${r.run_id}</td>
                            <td>${r.agent_name || 'Agent #' + r.agent_id}</td>
                            <td>
                                <span class="nu-ai-badge ${r.run_status === 'completed' ? 'nu-badge-active' : 'nu-badge-inactive'}">
                                    ${r.run_status}
                                </span>
                            </td>
                            <td><div style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${r.input_prompt || ''}</div></td>
                            <td>${r.total_tokens || 0}</td>
                            <td><small>${r.created_at || ''}</small></td>
                            <td style="text-align:right;">
                                <button class="nu-ai-btn-outline" style="padding:4px 8px;" onclick="nuAiStudio.viewRunDetails(${r.run_id})"><i class="fas fa-eye"></i> Details</button>
                            </td>
                        </tr>
                    `).join('');
                }
            });
    },

    viewRunDetails: function(runId) {
        document.getElementById('runDetailsModalOverlay').classList.add('open');
        document.getElementById('runDetailsBody').innerHTML = 'Loading execution trace...';

        fetch('api/agent.php?action=run_details&run_id=' + runId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const run = data.run;
                    const messages = data.messages || [];
                    const toolCalls = data.tool_calls || [];

                    let html = `
                        <div style="margin-bottom:12px; font-size:13px;">
                            <strong>Status:</strong> ${run.run_status} |
                            <strong>Tokens:</strong> ${run.total_tokens} |
                            <strong>Started:</strong> ${run.created_at}
                        </div>
                        <h4 style="font-size:14px; margin-bottom:8px;">Conversation & Step Audit</h4>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                    `;

                    messages.forEach(m => {
                        html += `
                            <div style="padding:10px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc;">
                                <div style="display:flex; justify-between; font-weight:700; font-size:12px; text-transform:uppercase; margin-bottom:4px; color:#0284c7;">${m.msg_role}</div>
                                <div style="font-family:monospace; font-size:12px; white-space:pre-wrap;">${m.msg_content}</div>
                            </div>
                        `;
                    });

                    if (toolCalls.length > 0) {
                        html += `<h4 style="font-size:14px; margin-top:14px; margin-bottom:8px;">Tool Invocations (${toolCalls.length})</h4>`;
                        toolCalls.forEach(tc => {
                            html += `
                                <div style="padding:10px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; margin-bottom:8px;">
                                    <div style="font-weight:700; font-size:12px; color:#d97706; margin-bottom:4px;"><i class="fas fa-tools"></i> Tool: ${tc.tool_name}</div>
                                    <div style="font-size:11px; color:#64748b;">Arguments:</div>
                                    <pre style="background:#0f172a; color:#f8fafc; padding:8px; border-radius:4px; font-size:11px; margin:4px 0;">${tc.tool_arguments}</pre>
                                    <div style="font-size:11px; color:#64748b;">Result:</div>
                                    <pre style="background:#f1f5f9; color:#334155; padding:8px; border-radius:4px; font-size:11px; margin:4px 0;">${tc.tool_result}</pre>
                                </div>
                            `;
                        });
                    }

                    html += `</div>`;
                    document.getElementById('runDetailsBody').innerHTML = html;
                }
            });
    },

    closeRunDetailsModal: function() {
        document.getElementById('runDetailsModalOverlay').classList.remove('open');
    },

    runPlayground: function() {
        const agentId = document.getElementById('pgAgentSelect').value;
        const prompt = document.getElementById('pgPromptInput').value;

        if (!agentId || !prompt) {
            alert('Please select an agent and enter a prompt.');
            return;
        }

        document.getElementById('pgStatusBadge').innerText = 'Running...';
        document.getElementById('pgStatusBadge').className = 'nu-ai-badge nu-badge-info';
        document.getElementById('pgOutputArea').innerHTML = 'Agent is thinking & executing tools...';

        fetch('api/agent.php?action=run', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                agent_id: agentId,
                message: prompt
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pgStatusBadge').innerText = 'Completed';
                document.getElementById('pgStatusBadge').className = 'nu-ai-badge nu-badge-active';
                document.getElementById('pgOutputArea').innerText = data.answer || 'No text output returned.';
            } else {
                document.getElementById('pgStatusBadge').innerText = 'Failed';
                document.getElementById('pgStatusBadge').className = 'nu-ai-badge nu-badge-inactive';
                document.getElementById('pgOutputArea').innerText = 'Error: ' + (data.error || 'Execution failed');
            }
        });
    }
};

nuAiStudio.init();
</script>
