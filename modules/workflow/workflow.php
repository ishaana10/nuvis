<?php
session_start();
if (!isset($_SESSION['nu_user_id'])) {
    http_response_code(403);
    echo '<p>Not authorised</p>';
    exit;
}
?>
<div style="padding:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h2 style="margin:0;">Workflow</h2>
        <button class="nu-btn nu-btn-primary" onclick="NuApp.toast('Workflow builder coming soon','info')">+ New Workflow</button>
    </div>
    <div class="nu-card" style="text-align:center;padding:60px;color:var(--text-secondary);">
        <div style="font-size:48px;margin-bottom:16px;">⚡</div>
        <h3>Workflow Builder</h3>
        <p>Automate actions between your forms, reports and external services.</p>
        <p style="font-size:13px;">Coming soon in v5.2</p>
    </div>
</div>