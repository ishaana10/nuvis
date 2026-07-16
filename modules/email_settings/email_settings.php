<?php
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
