<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

// Only admins may access this module
if (!$auth->hasPermission('system.config')) {
    http_response_code(403);
    echo '<div style="padding:32px;text-align:center;color:var(--danger,#dc2626);">';
    echo '<h3>Access Denied</h3><p>You do not have permission to manage password policy.</p>';
    echo '</div>';
    exit;
}

$db = NuDatabase::getInstance();
?>

<div class="nu-password-module">

<!-- ── Tab nav ──────────────────────────────────────────────────────────────── -->
<div class="nu-tabs" style="margin-bottom:24px;">
    <button class="nu-tab-btn nu-tab-active" onclick="plcShowTab('policy')" id="ptab-policy">Password Policy</button>
    <button class="nu-tab-btn" onclick="plcShowTab('reset')" id="ptab-reset">Admin Reset</button>
</div>

<!-- ── PANEL: Password Policy ───────────────────────────────────────────────────────── -->
<div id="plc-panel-policy">
    <div class="nu-card" style="max-width:640px;">
        <div class="nu-card-header"><h3 class="nu-card-title">Password Policy Settings</h3></div>
        <div class="nu-card-body">
            <div id="plc-policy-alert" style="display:none;margin-bottom:12px;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="nu-field">
                    <label>Minimum Length</label>
                    <input type="number" class="nu-input" id="plc-min-length" min="6" max="128" value="8">
                </div>
                <div class="nu-field">
                    <label>Password History (# to remember)</label>
                    <input type="number" class="nu-input" id="plc-history" min="0" max="24" value="5">
                    <small style="color:var(--text-secondary);">0 = disabled</small>
                </div>
                <div class="nu-field">
                    <label>Expiry (days, 0 = never)</label>
                    <input type="number" class="nu-input" id="plc-expiry" min="0" value="0">
                </div>
                <div class="nu-field">
                    <label>Expiry Warning (days before)</label>
                    <input type="number" class="nu-input" id="plc-expiry-warn" min="0" value="7">
                </div>
            </div>

            <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px;">
                <label class="nu-check-row"><input type="checkbox" id="plc-uppercase" checked>
                    Require at least one <strong>uppercase</strong> letter (A–Z)</label>
                <label class="nu-check-row"><input type="checkbox" id="plc-lowercase" checked>
                    Require at least one <strong>lowercase</strong> letter (a–z)</label>
                <label class="nu-check-row"><input type="checkbox" id="plc-number" checked>
                    Require at least one <strong>number</strong> (0–9)</label>
                <label class="nu-check-row"><input type="checkbox" id="plc-special">
                    Require at least one <strong>special character</strong> (!@#$…)</label>
                <label class="nu-check-row"><input type="checkbox" id="plc-no-username" checked>
                    Password must <strong>not contain</strong> the username</label>
                <label class="nu-check-row"><input type="checkbox" id="plc-force-first" checked>
                    Force password change on <strong>first login</strong></label>
            </div>
        </div>
        <div class="nu-card-footer" style="display:flex;justify-content:flex-end;gap:8px;">
            <button class="nu-btn nu-btn-ghost" onclick="plcLoadPolicy()">Reset</button>
            <button class="nu-btn nu-btn-primary" onclick="plcSavePolicy()">Save Policy</button>
        </div>
    </div>
</div>

<!-- ── PANEL: Admin Reset ────────────────────────────────────────────────────── -->
<div id="plc-panel-reset" style="display:none;">
    <div class="nu-card" style="max-width:480px;">
        <div class="nu-card-header"><h3 class="nu-card-title">Admin: Reset User Password</h3></div>
        <div class="nu-card-body">
            <div id="plc-reset-alert" style="display:none;margin-bottom:12px;"></div>
            <div class="nu-field">
                <label>Select User</label>
                <select class="nu-input" id="plc-rst-user-id">
                    <?php
                    $users = $db->fetchAll("SELECT usr_id, usr_username, usr_email FROM nu_users WHERE usr_active=1 ORDER BY usr_username");
                    foreach ($users as $u):
                    ?>
                    <option value="<?= (int)$u['usr_id'] ?>"><?= htmlspecialchars($u['usr_username'], ENT_QUOTES) ?><?= $u['usr_email'] ? ' (' . htmlspecialchars($u['usr_email'], ENT_QUOTES) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nu-field">
                <label for="plc-rst-pwd">New Password</label>
                <div class="nu-input-wrap">
                    <input type="password" class="nu-input" id="plc-rst-pwd" autocomplete="new-password">
                    <button type="button" class="nu-input-eye" onclick="plcToggleVis('plc-rst-pwd',this)" aria-label="Show/hide">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" style="margin-top:6px;" onclick="plcGenerate('plc-rst-pwd')">Generate Strong Password</button>
            </div>
            <div class="nu-field">
                <label class="nu-check-row"><input type="checkbox" id="plc-rst-force" checked>
                    Force user to change password on next login</label>
            </div>
        </div>
        <div class="nu-card-footer" style="display:flex;justify-content:flex-end;">
            <button class="nu-btn nu-btn-primary" onclick="plcAdminReset()">Reset Password</button>
        </div>
    </div>
</div>

</div><!-- /nu-password-module -->

<style>
.nu-input-wrap { position:relative; }
.nu-input-wrap .nu-input { padding-right:40px; }
.nu-input-eye { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:2px; }
.nu-input-eye:hover { color:var(--text-primary); }
.nu-tabs { display:flex; gap:4px; border-bottom:2px solid var(--border-color); }
.nu-tab-btn { background:none; border:none; padding:8px 18px; cursor:pointer; font-size:14px; font-weight:500; color:var(--text-secondary); border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; }
.nu-tab-btn:hover { color:var(--text-primary); }
.nu-tab-active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; }
.nu-check-row { display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; }
.nu-check-row input { width:16px; height:16px; flex-shrink:0; }
.nu-card-footer { padding:16px 24px; border-top:1px solid var(--border-color); }
</style>

<script>
(function () {

    // ── Tab switching ────────────────────────────────────────────────────────────────
    window.plcShowTab = function (tab) {
        ['policy', 'reset'].forEach(function (t) {
            var panel = document.getElementById('plc-panel-' + t);
            var btn   = document.getElementById('ptab-' + t);
            if (!panel) return;
            panel.style.display = t === tab ? '' : 'none';
            if (btn) btn.classList.toggle('nu-tab-active', t === tab);
        });
    };

    // ── Alert helper ──────────────────────────────────────────────────────────────
    function showAlert(elId, msg, type) {
        var el  = document.getElementById(elId);
        if (!el) return;
        var bg  = type === 'success' ? 'var(--success-light,#d1fae5)' : 'var(--danger-light,#fee2e2)';
        var col = type === 'success' ? 'var(--success,#059669)'       : 'var(--danger,#dc2626)';
        el.style.cssText = 'display:block;padding:10px 14px;border-radius:var(--radius-md);background:' + bg + ';color:' + col + ';font-size:14px;';
        el.textContent = msg;
    }

    // ── Show/hide password ─────────────────────────────────────────────────────────────
    window.plcToggleVis = function (inputId, btn) {
        var inp = document.getElementById(inputId);
        if (!inp) return;
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show);
    };

    // ── Generate strong password ─────────────────────────────────────────────────────
    window.plcGenerate = function (targetId) {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*';
        var pwd   = '';
        var arr   = new Uint32Array(16);
        crypto.getRandomValues(arr);
        arr.forEach(function (v) { pwd += chars[v % chars.length]; });
        var inp = document.getElementById(targetId);
        if (inp) { inp.value = pwd; inp.type = 'text'; }
    };

    // ── Load policy into form ───────────────────────────────────────────────────────────
    window.plcLoadPolicy = async function () {
        try {
            var res  = await fetch('api/password.php?action=get_policy');
            var data = await res.json();
            if (!data.success) return;
            var p = data.policy;
            var g = function (id) { return document.getElementById(id); };
            if (g('plc-min-length'))   g('plc-min-length').value   = p.policy_min_length;
            if (g('plc-history'))      g('plc-history').value      = p.policy_history_count;
            if (g('plc-expiry'))       g('plc-expiry').value       = p.policy_expiry_days;
            if (g('plc-expiry-warn'))  g('plc-expiry-warn').value  = p.policy_expiry_warning_days;
            if (g('plc-uppercase'))    g('plc-uppercase').checked  = !!+p.policy_require_uppercase;
            if (g('plc-lowercase'))    g('plc-lowercase').checked  = !!+p.policy_require_lowercase;
            if (g('plc-number'))       g('plc-number').checked     = !!+p.policy_require_number;
            if (g('plc-special'))      g('plc-special').checked    = !!+p.policy_require_special;
            if (g('plc-no-username'))  g('plc-no-username').checked= !!+p.policy_disallow_username;
            if (g('plc-force-first'))  g('plc-force-first').checked= !!+p.policy_force_change_on_first_login;
        } catch (e) { console.error('plcLoadPolicy', e); }
    };

    // ── Save policy ────────────────────────────────────────────────────────────────
    window.plcSavePolicy = async function () {
        var g = function (id) { return document.getElementById(id); };
        var payload = {
            policy_min_length:                  parseInt(g('plc-min-length')?.value  || 8),
            policy_require_uppercase:           g('plc-uppercase')?.checked  ? 1 : 0,
            policy_require_lowercase:            g('plc-lowercase')?.checked  ? 1 : 0,
            policy_require_number:              g('plc-number')?.checked     ? 1 : 0,
            policy_require_special:             g('plc-special')?.checked    ? 1 : 0,
            policy_disallow_username:           g('plc-no-username')?.checked ? 1 : 0,
            policy_history_count:               parseInt(g('plc-history')?.value     || 5),
            policy_expiry_days:                 parseInt(g('plc-expiry')?.value      || 0),
            policy_expiry_warning_days:         parseInt(g('plc-expiry-warn')?.value || 7),
            policy_force_change_on_first_login: g('plc-force-first')?.checked ? 1 : 0,
        };
        try {
            var res  = await fetch('api/password.php?action=save_policy', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            var data = await res.json();
            showAlert('plc-policy-alert', data.message || (data.success ? 'Policy saved.' : 'Error.'), data.success ? 'success' : 'error');
        } catch (e) { showAlert('plc-policy-alert', 'Network error.', 'error'); }
    };

    // ── Admin reset ────────────────────────────────────────────────────────────────
    window.plcAdminReset = async function () {
        var payload = {
            user_id:      parseInt(document.getElementById('plc-rst-user-id')?.value || 0),
            new_password: document.getElementById('plc-rst-pwd')?.value || '',
            force_change: document.getElementById('plc-rst-force')?.checked ?? true,
        };
        try {
            var res  = await fetch('api/password.php?action=admin_reset', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            var data = await res.json();
            showAlert('plc-reset-alert', data.message || (data.success ? 'Password reset.' : 'Error.'), data.success ? 'success' : 'error');
            if (data.success) {
                var pwd = document.getElementById('plc-rst-pwd');
                if (pwd) pwd.value = '';
            }
        } catch (e) { showAlert('plc-reset-alert', 'Network error.', 'error'); }
    };

    // ── Auto-load policy on mount ──────────────────────────────────────────────────
    plcLoadPolicy();

})();
</script>
