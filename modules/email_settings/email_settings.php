<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

// Only administrators can manage email settings
$currentUser = $auth->getCurrentUser();
$userRole = strtolower($currentUser['usr_role'] ?? '');
$isAdmin = ($userRole === 'globeadmin' || $userRole === 'admin');

if (!$isAdmin) {
    echo '<div style="padding:24px;border:2px solid var(--color-error);background:var(--color-error-highlight);border-radius:8px;"><h3>Access Denied</h3><p>Only administrators can manage email settings.</p></div>';
    exit;
}
?>

<div class="nu-email-settings-module">

  <!-- ── PAGE HEADER ────────────────────────────────────────────────── -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h2 style="font-size:20px;font-weight:600;margin:0;">Email Settings</h2>
      <p style="color:var(--text-secondary);font-size:13px;margin:2px 0 0;">Configure SMTP connections, manage email templates, and inspect the send log.</p>
    </div>
  </div>

  <div id="esAlertArea"></div>

  <!-- ── TABS BAR ───────────────────────────────────────────────────── -->
  <div class="es-tab-bar">
    <button class="es-tab-btn active" onclick="esSwitchTab('smtp', this)">SMTP Configuration</button>
    <button class="es-tab-btn" onclick="esSwitchTab('templates', this)">Email Templates</button>
    <button class="es-tab-btn" onclick="esSwitchTab('logs', this)">SMTP Send Log</button>
  </div>

  <!-- ── TAB 1: SMTP CONFIG ─────────────────────────────────────────── -->
  <div class="es-tab-panel active" id="es-tab-smtp">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- LEFT: Setup Form -->
      <div class="nu-card" style="padding:20px;">
        <h3 style="font-size:15px;font-weight:600;margin:0 0 16px;border-bottom:1px solid var(--color-divider);padding-bottom:10px;">SMTP Connection Properties</h3>

        <div class="nu-field" style="margin-bottom:12px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
            <input type="checkbox" id="esUseSmtp" onchange="esToggleSmtpFields()" style="width:16px;height:16px;">
            Enable SMTP Email Driver
          </label>
          <span style="font-size:12px;color:var(--color-text-faint);display:block;margin-top:2px;">When disabled, the system will fall back to using default PHP mail().</span>
        </div>

        <div id="esSmtpFieldsContainer">
          <div class="nu-field" style="margin-bottom:12px;">
            <label>SMTP Hostname</label>
            <input type="text" class="nu-input" id="esSmtpHost" placeholder="e.g. mail.yourdomain.com">
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div class="nu-field" style="margin:0;">
              <label>SMTP Port</label>
              <input type="number" class="nu-input" id="esSmtpPort" placeholder="587">
            </div>
            <div class="nu-field" style="margin:0;">
              <label>Encryption Mode</label>
              <select class="nu-input" id="esSmtpSecure">
                <option value="tls">TLS (STARTTLS)</option>
                <option value="ssl">SSL</option>
                <option value="">None / Plain</option>
              </select>
            </div>
          </div>

          <div class="nu-field" style="margin-bottom:12px;">
            <label>SMTP Username</label>
            <input type="text" class="nu-input" id="esSmtpUsername" placeholder="user@yourdomain.com">
          </div>

          <div class="nu-field" style="margin-bottom:16px;">
            <label>SMTP Password</label>
            <input type="password" class="nu-input" id="esSmtpPassword" placeholder="••••••••">
          </div>
        </div>

        <button class="nu-btn nu-btn-primary" onclick="esSaveSmtpSettings()" style="width:100%;">
          Save Email Configuration
        </button>
      </div>

      <!-- RIGHT: Test & Identity Form -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="nu-card" style="padding:20px;">
          <h3 style="font-size:15px;font-weight:600;margin:0 0 16px;border-bottom:1px solid var(--color-divider);padding-bottom:10px;">Default Sender Identity</h3>

          <div class="nu-field" style="margin-bottom:12px;">
            <label>Default From Name</label>
            <input type="text" class="nu-input" id="esFromName" placeholder="nuvis Notification">
          </div>

          <div class="nu-field" style="margin-bottom:16px;">
            <label>Default From Email Address</label>
            <input type="email" class="nu-input" id="esFromEmail" placeholder="noreply@yourdomain.com">
          </div>
        </div>

        <div class="nu-card" style="padding:20px;background:var(--color-surface-offset);">
          <h3 style="font-size:15px;font-weight:600;margin:0 0 10px;">Verify Configuration</h3>
          <p style="font-size:13px;color:var(--color-text-muted);margin:0 0 14px;line-height:1.5;">Send a test email to any recipient address to verify that your SMTP or PHP mail() settings connect and transmit successfully.</p>

          <div class="nu-field" style="margin-bottom:12px;">
            <label>Recipient Email Address</label>
            <input type="email" class="nu-input" id="esTestEmailTo" placeholder="you@example.com" style="background:#fff;">
          </div>

          <button class="nu-btn nu-btn-ghost" id="esTestBtn" onclick="esSendTestEmail()" style="width:100%;">
            Send Test Message
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- ── TAB 2: TEMPLATES ───────────────────────────────────────────── -->
  <div class="es-tab-panel" id="es-tab-templates" style="display:none;">
    <div class="nu-card" style="padding:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--color-divider);padding-bottom:10px;">
        <h3 style="font-size:15px;font-weight:600;margin:0;">Configured Email Templates</h3>
        <button class="nu-btn nu-btn-primary nu-btn-sm" onclick="esOpenTemplateModal(null)">+ Create Template</button>
      </div>

      <div style="overflow-x:auto;">
        <table class="es-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Subject</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody id="esTemplatesBody">
            <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--color-text-faint);">Loading templates…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── TAB 3: SEND LOG ────────────────────────────────────────────── -->
  <div class="es-tab-panel" id="es-tab-logs" style="display:none;">
    <div class="nu-card" style="padding:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--color-divider);padding-bottom:10px;">
        <h3 style="font-size:15px;font-weight:600;margin:0;">SMTP Delivery Logs</h3>
        <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="esLoadLogs()">Refresh Log</button>
      </div>

      <div style="overflow-x:auto;">
        <table class="es-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Recipient</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Details / Error</th>
            </tr>
          </thead>
          <tbody id="esLogsBody">
            <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--color-text-faint);">Loading logs…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── TEMPLATE EDIT MODAL ────────────────────────────────────────── -->
  <div id="esTemplateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;width:95%;max-width:600px;box-shadow:var(--shadow-lg);">
      <h3 id="esModalTemplateTitle" style="margin:0 0 16px;font-size:16px;font-weight:600;border-bottom:1px solid var(--color-divider);padding-bottom:10px;">Create Template</h3>
      <input type="hidden" id="esTplId">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="nu-field" style="margin:0;">
          <label>Template Name</label>
          <input type="text" class="nu-input" id="esTplName" placeholder="Welcome Email">
        </div>
        <div class="nu-field" style="margin:0;">
          <label>Unique Key / Slug</label>
          <input type="text" class="nu-input" id="esTplSlug" placeholder="welcome_email">
        </div>
      </div>

      <div class="nu-field" style="margin-bottom:12px;">
        <label>Subject Line <span style="font-weight:normal;color:var(--color-text-faint);">(supports {{placeholders}})</span></label>
        <input type="text" class="nu-input" id="esTplSubject" placeholder="Hello {{user_name}}!">
      </div>

      <div class="nu-field" style="margin-bottom:12px;">
        <label>Internal Description</label>
        <input type="text" class="nu-input" id="esTplDesc" placeholder="Optional description of use-case">
      </div>

      <div class="nu-field" style="margin-bottom:16px;">
        <label>HTML / Plain Body <span style="font-weight:normal;color:var(--color-text-faint);">(supports {{placeholders}})</span></label>
        <textarea class="nu-input" id="esTplBody" rows="8" style="font-family:monospace;font-size:13px;resize:vertical;" placeholder="<p>Hi {{user_name}}!</p>"></textarea>
      </div>

      <div class="nu-field" style="margin-bottom:20px;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
          <input type="checkbox" id="esTplActive" checked style="width:16px;height:16px;">
          Active and available for sending
        </label>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button class="nu-btn nu-btn-ghost" onclick="esCloseTemplateModal()">Cancel</button>
        <button class="nu-btn nu-btn-primary" onclick="esSaveTemplate()">Save Template</button>
      </div>
    </div>
  </div>

</div>

<style>
.nu-email-settings-module {
  font-family:var(--font-body,sans-serif);
}
.es-tab-bar {
  display:flex;
  gap:6px;
  background:var(--color-surface-offset);
  padding:4px;
  border-radius:var(--radius-lg);
  width:fit-content;
  margin-bottom:20px;
}
.es-tab-btn {
  padding:6px 16px;
  border-radius:var(--radius-md);
  font-size:13px;
  font-weight:500;
  color:var(--color-text-muted);
  cursor:pointer;
  transition:all 150ms;
}
.es-tab-btn.active {
  background:var(--color-surface);
  color:var(--color-text);
  box-shadow:var(--shadow-sm);
}
.es-tab-btn:hover:not(.active) {
  color:var(--color-text);
}
.es-table {
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
.es-table th {
  background:var(--color-bg);
  padding:8px 12px;
  text-align:left;
  font-weight:600;
  color:var(--color-text-muted);
  text-transform:uppercase;
  font-size:11px;
  letter-spacing:0.04em;
  border-bottom:1px solid var(--color-border);
}
.es-table td {
  padding:10px 12px;
  border-bottom:1px solid var(--color-divider);
  vertical-align:middle;
}
.es-table tr:last-child td {
  border-bottom:none;
}
.es-table tr:hover td {
  background:var(--color-surface-offset);
}
.es-status-dot {
  width:8px;
  height:8px;
  border-radius:50%;
  display:inline-block;
  margin-right:6px;
}
.es-status-sent { background:#10b981; }
.es-status-fail { background:#ef4444; }
</style>

<script>
(function () {

  // Guard: tear down previous instance before attaching new one
  if (window.ES && typeof window.ES._destroy === 'function') {
    window.ES._destroy();
  }

  // ── toast helper ────────────────────────────────────────────────────
  function toast(msg, type) {
    type = type || 'info';
    if (window.NuApp && typeof NuApp.toast === 'function') { NuApp.toast(msg, type); return; }
    if (typeof nuToast === 'function') { nuToast(msg, type); return; }
    console.log('[ES]', type, msg);
  }

  var API = 'api/email.php';
  var _templates = [];

  // Defer run until SPA layout is ready
  setTimeout(function () {
    esLoadSmtpSettings();
  }, 0);

  // ── Tab switching ───────────────────────────────────────────────────
  window.esSwitchTab = function (tabId, btn) {
    document.querySelectorAll('.es-tab-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');

    document.querySelectorAll('.es-tab-panel').forEach(function (panel) { panel.style.display = 'none'; });
    document.getElementById('es-tab-' + tabId).style.display = '';

    if (tabId === 'templates') esLoadTemplates();
    if (tabId === 'logs') esLoadLogs();
  };

  // ── SMTP settings controls ──────────────────────────────────────────
  window.esToggleSmtpFields = function () {
    var on = document.getElementById('esUseSmtp').checked;
    var container = document.getElementById('esSmtpFieldsContainer');
    container.style.opacity = on ? '1' : '0.4';
    container.style.pointerEvents = on ? 'auto' : 'none';
  };

  window.esLoadSmtpSettings = async function () {
    try {
      var res  = await fetch(API + '?action=get_settings', { credentials: 'same-origin' });
      var data = await res.json();
      if (!data.success) return;
      var s = data.data;

      document.getElementById('esUseSmtp').checked    = (s.driver === 'smtp');
      document.getElementById('esSmtpHost').value     = s.smtp_host     || '';
      document.getElementById('esSmtpPort').value     = s.smtp_port     || '587';
      document.getElementById('esSmtpSecure').value   = s.smtp_secure   || 'tls';
      document.getElementById('esSmtpUsername').value = s.smtp_username || '';
      document.getElementById('esSmtpPassword').value = s.smtp_password || '';
      document.getElementById('esFromName').value     = s.from_name     || '';
      document.getElementById('esFromEmail').value    = s.from_email    || '';

      esToggleSmtpFields();
    } catch(e) { console.warn('[ES] settings load failed:', e); }
  };

  window.esSaveSmtpSettings = async function () {
    var driver = document.getElementById('esUseSmtp').checked ? 'smtp' : 'mail';
    var settings = [
      { key: 'driver',        value: driver },
      { key: 'smtp_host',     value: document.getElementById('esSmtpHost').value },
      { key: 'smtp_port',     value: document.getElementById('esSmtpPort').value || '587' },
      { key: 'smtp_secure',   value: document.getElementById('esSmtpSecure').value },
      { key: 'smtp_username', value: document.getElementById('esSmtpUsername').value },
      { key: 'smtp_password', value: document.getElementById('esSmtpPassword').value },
      { key: 'from_name',     value: document.getElementById('esFromName').value },
      { key: 'from_email',    value: document.getElementById('esFromEmail').value },
    ];

    try {
      var res = await fetch(API, {
/**
 * modules/email_settings/email_settings.php
 * Email Settings Management Module
 * Allows administrators to configure email service settings
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/EmailService.php';

// Auth guard
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// Check admin role
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['globeadmin', 'admin'])) {
    http_response_code(403);
    die('Forbidden');
}

?>
<div class="email-settings-module">
    <style>
        .email-settings-module {
            padding: 20px;
        }
        .settings-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f9f9f9;
        }
        .settings-section h3 {
            margin-top: 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        .form-group textarea {
            max-width: 100%;
            min-height: 100px;
        }
        .button-group {
            margin-top: 20px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-primary {
            background: #4f6bed;
            color: white;
        }
        .btn-primary:hover {
            background: #3d52a0;
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .test-result {
            margin-top: 15px;
            padding: 12px;
            border-radius: 4px;
            display: none;
        }
        .test-result.show {
            display: block;
        }
        .spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #4f6bed;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <h1>Email Settings</h1>
    <p>Configure your email service settings (SMTP, PHP mail, or Sendmail)</p>

    <div id="alertContainer"></div>

    <div class="settings-section">
        <h3>Email Service Driver</h3>
        <form id="emailSettingsForm">
            <div class="form-group">
                <label for="driver">Email Driver</label>
                <select id="driver" name="driver">
                    <option value="mail">PHP mail()</option>
                    <option value="smtp">SMTP</option>
                    <option value="sendmail">Sendmail</option>
                </select>
                <small>Select which method to use for sending emails</small>
            </div>

            <div class="form-group">
                <label for="from_email">From Email Address</label>
                <input type="email" id="from_email" name="from_email" placeholder="noreply@example.com" required>
            </div>

            <div class="form-group">
                <label for="from_name">From Name</label>
                <input type="text" id="from_name" name="from_name" placeholder="nub5-dev">
            </div>

            <div class="form-group">
                <label for="reply_to">Reply-To Email (Optional)</label>
                <input type="email" id="reply_to" name="reply_to" placeholder="support@example.com">
            </div>

            <hr>

            <div id="smtpFields" style="display:none;">
                <h4>SMTP Configuration</h4>
                
                <div class="form-group">
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com">
                </div>

                <div class="form-group">
                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="587">
                </div>

                <div class="form-group">
                    <label for="smtp_secure">SMTP Security</label>
                    <select id="smtp_secure" name="smtp_secure">
                        <option value="">None</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="smtp_username">SMTP Username</label>
                    <input type="text" id="smtp_username" name="smtp_username" placeholder="your-email@gmail.com">
                </div>

                <div class="form-group">
                    <label for="smtp_password">SMTP Password</label>
                    <input type="password" id="smtp_password" name="smtp_password" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="smtp_auth" name="smtp_auth" checked> Require Authentication
                    </label>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="button" class="btn btn-secondary" onclick="testEmail()">Test Email</button>
            </div>
        </form>

        <div id="testResult" class="test-result"></div>
    </div>

    <div class="settings-section">
        <h3>Email Templates</h3>
        <p>Manage reusable email templates with variable substitution.</p>
        <button type="button" class="btn btn-primary btn-sm" onclick="manageTemplates()">Manage Templates</button>
    </div>

    <div class="settings-section">
        <h3>Email Log</h3>
        <p>View recent email send attempts and their status.</p>
        <button type="button" class="btn btn-primary btn-sm" onclick="viewEmailLog()">View Log</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    setupEventListeners();
});

function setupEventListeners() {
    const driverSelect = document.getElementById('driver');
    if (driverSelect) {
        driverSelect.addEventListener('change', function() {
            const smtpFields = document.getElementById('smtpFields');
            if (smtpFields) {
                smtpFields.style.display = this.value === 'smtp' ? 'block' : 'none';
            }
        });
    }

    const form = document.getElementById('emailSettingsForm');
    if (form) {
        form.addEventListener('submit', saveSettings);
    }
}

function loadSettings() {
    fetch('../../api/email.php?action=get_settings', {
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(json => {
        if (json.success && json.data) {
            const data = json.data;
            document.getElementById('driver').value = data.driver || 'mail';
            document.getElementById('from_email').value = data.from_email || '';
            document.getElementById('from_name').value = data.from_name || '';
            document.getElementById('reply_to').value = data.reply_to || '';
            document.getElementById('smtp_host').value = data.smtp_host || '';
            document.getElementById('smtp_port').value = data.smtp_port || '587';
            document.getElementById('smtp_secure').value = data.smtp_secure || 'tls';
            document.getElementById('smtp_username').value = data.smtp_username || '';
            document.getElementById('smtp_auth').checked = data.smtp_auth !== 'false';
            
            const smtpFields = document.getElementById('smtpFields');
            if (smtpFields) {
                smtpFields.style.display = data.driver === 'smtp' ? 'block' : 'none';
            }
        }
    })
    .catch(e => showAlert('Error loading settings: ' + e.message, 'error'));
}

function saveSettings(e) {
    e.preventDefault();
    
    const settings = [
        { key: 'driver', value: document.getElementById('driver').value },
        { key: 'from_email', value: document.getElementById('from_email').value },
        { key: 'from_name', value: document.getElementById('from_name').value },
        { key: 'reply_to', value: document.getElementById('reply_to').value },
        { key: 'smtp_host', value: document.getElementById('smtp_host').value },
        { key: 'smtp_port', value: document.getElementById('smtp_port').value },
        { key: 'smtp_secure', value: document.getElementById('smtp_secure').value },
        { key: 'smtp_username', value: document.getElementById('smtp_username').value },
        { key: 'smtp_password', value: document.getElementById('smtp_password').value },
        { key: 'smtp_auth', value: document.getElementById('smtp_auth').checked ? 'true' : 'false' }
    ];

    fetch('../../api/email.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_settings', settings: settings })
      });
      var data = await res.json();
      if (data.success) {
        toast('SMTP Settings saved successfully', 'success');
      } else {
        toast('Save failed: ' + data.message, 'error');
      }
    } catch(e) {
      toast('Network error: ' + e.message, 'error');
    }
  };

  // ── Send test email ─────────────────────────────────────────────────
  window.esSendTestEmail = async function () {
    var to = document.getElementById('esTestEmailTo').value.trim();
    if (!to) { toast('Please enter a recipient email address', 'warning'); return; }

    var btn = document.getElementById('esTestBtn');
    btn.textContent = 'Sending test…';
    btn.disabled = true;

    try {
      var res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test', to: to })
      });
      var data = await res.json();
      if (data.success) {
        toast('Test email sent to ' + to + ' successfully!', 'success');
      } else {
        toast('SMTP send failed: ' + data.message, 'error');
      }
    } catch(e) {
      toast('Network error: ' + e.message, 'error');
    }

    btn.textContent = 'Send Test Message';
    btn.disabled = false;
  };

  // ── Manage templates ────────────────────────────────────────────────
  window.esLoadTemplates = async function () {
    try {
      var res = await fetch(API + '?action=templates', { credentials: 'same-origin' });
      var j   = await res.json();
      _templates = j.success ? j.data : [];
      esRenderTemplatesTable();
    } catch(e) {
      document.getElementById('esTemplatesBody').innerHTML = '<tr><td colspan="5" style="color:var(--color-error)">Failed to load: ' + e.message + '</td></tr>';
    }
  };

  function esRenderTemplatesTable() {
    var tbody = document.getElementById('esTemplatesBody');
    if (!_templates.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--color-text-faint);">No email templates found. Click <strong>+ Create Template</strong> to add one.</td></tr>';
      return;
    }

    tbody.innerHTML = _templates.map(function (t) {
      return '<tr>' +
        '<td style="font-weight:600;">' + esc(t.name) + '</td>' +
        '<td><code style="background:var(--color-surface-offset);padding:2px 6px;border-radius:var(--radius-sm);font-family:monospace;font-size:12px;color:var(--color-primary);">' + esc(t.slug) + '</code></td>' +
        '<td style="color:var(--color-text-muted);">' + esc(t.subject) + '</td>' +
        '<td>' + (t.is_active == 1 ? '<span class="nu-status nu-status-active">Active</span>' : '<span class="nu-status">Inactive</span>') + '</td>' +
        '<td style="text-align:right;"><div style="display:inline-flex;gap:4px;">' +
          '<button class="nu-btn nu-btn-ghost nu-btn-sm" onclick=\'esEditTemplate(' + JSON.stringify(t).replace(/'/g, "&apos;") + ')\'>Edit</button>' +
          '<button class="nu-btn nu-btn-ghost nu-btn-sm" style="color:var(--color-error);" onclick="esDeleteTemplate(' + t.id + ')">Delete</button>' +
        '</div></td>' +
      '</tr>';
    }).join('');
  }

  window.esOpenTemplateModal = function (t) {
    if (t) {
      document.getElementById('esModalTemplateTitle').textContent = 'Edit Template';
      document.getElementById('esTplId').value          = t.id;
      document.getElementById('esTplName').value        = t.name;
      document.getElementById('esTplSlug').value        = t.slug;
      document.getElementById('esTplSubject').value     = t.subject;
      document.getElementById('esTplDesc').value        = t.description || '';
      document.getElementById('esTplBody').value        = t.body;
      document.getElementById('esTplActive').checked    = t.is_active == 1;
    } else {
      document.getElementById('esModalTemplateTitle').textContent = 'Create Template';
      document.getElementById('esTplId').value          = '';
      document.getElementById('esTplName').value        = '';
      document.getElementById('esTplSlug').value        = '';
      document.getElementById('esTplSubject').value     = '';
      document.getElementById('esTplDesc').value        = '';
      document.getElementById('esTplBody').value        = '';
      document.getElementById('esTplActive').checked    = true;
    }
    document.getElementById('esTemplateModal').style.display = 'flex';
  };

  window.esEditTemplate = function (t) {
    esOpenTemplateModal(t);
  };

  window.esCloseTemplateModal = function () {
    document.getElementById('esTemplateModal').style.display = 'none';
  };

  window.esSaveTemplate = async function () {
    var id     = document.getElementById('esTplId').value || 0;
    var name   = document.getElementById('esTplName').value.trim();
    var slug   = document.getElementById('esTplSlug').value.trim();
    var sub    = document.getElementById('esTplSubject').value.trim();
    var desc   = document.getElementById('esTplDesc').value.trim();
    var body   = document.getElementById('esTplBody').value.trim();
    var active = document.getElementById('esTplActive').checked ? 1 : 0;

    if (!name || !slug || !sub || !body) {
      toast('Name, Slug, Subject, and Body are all required', 'warning');
      return;
    }

    try {
      var res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_template',
          id: id, name: name, slug: slug, subject: sub, description: desc, body: body, is_active: active
        })
      });
      var data = await res.json();
      if (data.success) {
        esCloseTemplateModal();
        toast('Template saved successfully', 'success');
        esLoadTemplates();
      } else {
        toast('Save template failed: ' + data.message, 'error');
      }
    } catch (e) {
      toast('Network error: ' + e.message, 'error');
    }
  };

  window.esDeleteTemplate = async function (id) {
    if (!confirm('Are you sure you want to delete this template? This cannot be undone.')) return;
    try {
      var res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_template', id: id })
      });
      var data = await res.json();
      if (data.success) {
        toast('Template deleted', 'success');
        esLoadTemplates();
      } else {
        toast('Delete failed: ' + data.message, 'error');
      }
    } catch(e) {
      toast('Network error: ' + e.message, 'error');
    }
  };

  // ── SMTP send log ───────────────────────────────────────────────────
  window.esLoadLogs = async function () {
    var tbody = document.getElementById('esLogsBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--color-text-faint);">Loading logs…</td></tr>';
    try {
      var res = await fetch(API + '?action=logs&limit=50', { credentials: 'same-origin' });
      var data = await res.json();
      if (!data.success || !data.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--color-text-faint);">No email delivery log entries found.</td></tr>';
        return;
      }

      tbody.innerHTML = data.data.map(function (row) {
        return '<tr>' +
          '<td style="color:var(--color-text-muted);font-size:11px;white-space:nowrap;">' + esc(row.sent_at) + '</td>' +
          '<td>' + esc(row.recipient) + '</td>' +
          '<td style="font-weight:500;">' + esc(row.subject) + '</td>' +
          '<td>' + (row.status === 'SENT'
            ? '<span class="badge" style="background:#d4dfcc;color:#437a22;"><span class="es-status-dot es-status-sent"></span>Sent</span>'
            : '<span class="badge" style="background:#e0ced7;color:#a12c7b;"><span class="es-status-dot es-status-fail"></span>Failed</span>') + '</td>' +
          '<td style="color:var(--color-error);font-size:11px;">' + esc(row.error_message || '') + '</td>' +
        '</tr>';
      }).join('');
    } catch(e) {
      tbody.innerHTML = '<tr><td colspan="5" style="color:var(--color-error)">Failed to load logs: ' + e.message + '</td></tr>';
    }
  };

  // ── utility escaper ─────────────────────────────────────────────────
  function esc(s) {
    return String(s !== null && s !== undefined ? s : '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // Teardown / destroy guard
  window.ES = {
    _destroy: function () {
      window.ES = null;
    }
  };

})();
    })
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            showAlert('Settings saved successfully!', 'success');
        } else {
            showAlert(json.message || 'Failed to save settings', 'error');
        }
    })
    .catch(e => showAlert('Error: ' + e.message, 'error'));
}

function testEmail() {
    const testResult = document.getElementById('testResult');
    testResult.className = 'test-result show';
    testResult.innerHTML = '<div class="spinner"></div> Sending test email...';

    const userEmail = document.getElementById('from_email').value || 'admin@example.com';

    fetch('../../api/email.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test', to: userEmail })
    })
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            testResult.innerHTML = '<div class="alert alert-success"><strong>Success!</strong> Test email sent to ' + userEmail + '</div>';
        } else {
            testResult.innerHTML = '<div class="alert alert-error"><strong>Failed!</strong> ' + (json.message || 'Unknown error') + '</div>';
        }
    })
    .catch(e => {
        testResult.innerHTML = '<div class="alert alert-error"><strong>Error:</strong> ' + e.message + '</div>';
    });
}

function manageTemplates() {
    alert('Template management coming soon!');
}

function viewEmailLog() {
    alert('Email log viewer coming soon!');
}

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type;
    alert.innerHTML = message;
    container.innerHTML = '';
    container.appendChild(alert);
    
    if (type === 'success') {
        setTimeout(() => alert.remove(), 4000);
    }
}
</script>
