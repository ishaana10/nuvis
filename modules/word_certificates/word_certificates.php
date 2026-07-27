<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db = NuDatabase::getInstance();

// Self-healing database structure
try {
    $db->query("CREATE TABLE IF NOT EXISTS `nu_word_certificates` (
      `cert_id` INT AUTO_INCREMENT PRIMARY KEY,
      `cert_title` VARCHAR(255) NOT NULL,
      `cert_form_code` VARCHAR(255) NULL,
      `cert_file_id` INT NULL,
      `cert_html_template` LONGTEXT NULL,
      `cert_button_label` VARCHAR(255) NULL,
      `cert_output_name_template` VARCHAR(255) NULL,
      `cert_created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `cert_created_by` VARCHAR(36) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Idempotent column additions
    try {
        $db->query("ALTER TABLE `nu_word_certificates` ADD COLUMN `cert_button_label` VARCHAR(255) NULL");
    } catch (\Throwable $t) {}
    try {
        $db->query("ALTER TABLE `nu_word_certificates` ADD COLUMN `cert_output_name_template` VARCHAR(255) NULL");
    } catch (\Throwable $t) {}
} catch (\Throwable $e) {}

$forms = $db->fetchAll("SELECT form_code, form_name FROM nu_forms WHERE form_active = 1 ORDER BY form_name ASC");
$certs = $db->fetchAll(
    "SELECT c.*, f.file_original_name
     FROM nu_word_certificates c
     LEFT JOIN nu_files f ON c.cert_file_id = f.file_id
     ORDER BY c.cert_created_at DESC"
);
?>

<div class="nu-word-certificates">
    <div class="nu-card" style="margin-bottom: 24px;">
        <div class="nu-card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="nu-card-title">Word Certificates &amp; Templates</h3>
            <?php
            $role = strtolower((string)($_SESSION['nu_role'] ?? ''));
            if ($role === 'globeadmin' || $role === 'admin'): ?>
                <button class="nu-btn nu-btn-primary" onclick="openCertModal()">+ New Certificate</button>
            <?php endif; ?>
        </div>
        <p style="color:var(--text-secondary); font-size:13px; margin: -10px 0 20px 0; padding:0 24px;">
            Define dynamic certificate layouts and associate them with specific forms or keep them global. Embed <code>{{fieldname}}</code> tags within your uploaded Word (<code>.docx</code>) files or HTML templates to replace them with record values at runtime.
        </p>
        <div class="nu-table-wrap">
            <table class="nu-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Associated Form</th>
                        <th>Word Template File</th>
                        <th>Created</th>
                        <th style="width:160px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="wordCertsTableBody">
                    <?php foreach ($certs as $c): ?>
                    <tr data-id="<?php echo $c['cert_id']; ?>">
                        <td><strong><?php echo htmlspecialchars($c['cert_title']); ?></strong></td>
                        <td>
                            <?php
                            if (empty($c['cert_form_code']) || $c['cert_form_code'] === 'global') {
                                echo '<span class="nu-badge" style="background:var(--color-primary-light); color:var(--color-primary);">Global (All Forms)</span>';
                            } else {
                                echo '<span class="nu-badge" style="background:var(--bg-secondary);">' . htmlspecialchars($c['cert_form_code']) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($c['file_original_name']): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:13px;">
                                    📝 <?php echo htmlspecialchars($c['file_original_name']); ?>
                                </span>
                            <?php else: ?>
                                <em style="color:var(--text-tertiary); font-size:12px;">No file uploaded</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y H:i', strtotime($c['cert_created_at'])); ?></td>
                        <td style="text-align:right;">
                            <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="editCert(<?php echo $c['cert_id']; ?>)">Edit</button>
                            <?php if ($role === 'globeadmin' || $role === 'admin'): ?>
                                <button class="nu-btn nu-btn-danger nu-btn-sm" onclick="deleteCert(<?php echo $c['cert_id']; ?>)">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($certs)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:var(--text-tertiary); padding: 30px;">
                            No certificate templates created yet. Click "+ New Certificate" to configure one.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Dialog -->
<div class="nu-modal-overlay" id="certModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="nu-modal" style="background:var(--bg-elevated); border:1px solid var(--border-color); border-radius:var(--radius-lg); width:100%; max-width:650px; padding:24px; position:relative;">
        <div class="nu-modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 class="nu-modal-title" id="certModalTitle">New Certificate Template</h3>
            <button class="nu-modal-close" onclick="closeCertModal()" style="background:none; border:none; cursor:pointer; color:var(--text-secondary);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="nu-modal-body" style="display:flex; flex-direction:column; gap:16px; max-height:calc(100vh - 200px); overflow-y:auto; padding-right:4px;">
            <input type="hidden" id="certId" value="">

            <div class="nu-field">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Certificate Title <span style="color:#ef4444;">*</span></label>
                <input type="text" class="nu-input" id="certTitle" placeholder="e.g. Employee Achievement Certificate" style="width:100%;">
            </div>

            <div class="nu-field">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Custom Button Label <span style="font-weight:400; color:var(--text-tertiary);">(Optional, e.g. "Print Contract")</span></label>
                <input type="text" class="nu-input" id="certButtonLabel" placeholder="defaults to 'Certificates' if blank" style="width:100%;">
            </div>

            <div class="nu-field">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Custom Output File Name Template <span style="font-weight:400; color:var(--text-tertiary);">(Optional)</span></label>
                <input type="text" class="nu-input" id="certOutputNameTemplate" placeholder="e.g. {{customer_name}}_{{certificate_no}}" style="width:100%;">
                <p style="font-size:11px; color:var(--text-tertiary); margin-top:4px; margin-bottom:0;">
                    Tip: Use <code>{{fieldname}}</code> placeholders based on form fields. e.g. <code>{{customer_name}}_{{certificate_no}}</code>.
                </p>
            </div>

            <div class="nu-field">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Associated Form</label>
                <select class="nu-input" id="certFormCode" style="width:100%;">
                    <option value="">-- Global / Available on All Forms --</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?php echo htmlspecialchars($f['form_code']); ?>">
                            <?php echo htmlspecialchars($f['form_name'] . ' (' . $f['form_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nu-field" style="border: 1px dashed var(--border-color); border-radius:6px; padding:12px; background:var(--bg-secondary);">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Upload Word Template (.docx) <span style="color:#ef4444;">*</span></label>
                <input type="file" id="certFile" accept=".docx" style="display:none;" onchange="handleFileSelected(this)">
                <div style="display:flex; gap:10px; align-items:center;">
                    <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick="document.getElementById('certFile').click()">Choose File</button>
                    <span id="selectedFileName" style="font-size:12px; color:var(--text-secondary);">No file chosen</span>
                </div>
                <input type="hidden" id="certFileId" value="">
                <p style="font-size:11px; color:var(--text-tertiary); margin-top:6px; margin-bottom:0;">
                    Tip: Upload a MS Word document. You can type placeholders like <code>{{first_name}}</code>, <code>{{date}}</code> inside the Word document.
                </p>
            </div>

            <div class="nu-field">
                <label style="font-weight:600; font-size:12px; margin-bottom:4px; display:block;">Custom PDF HTML Template (Optional)</label>
                <p style="font-size:11px; color:var(--text-tertiary); margin-top: -2px; margin-bottom:6px;">
                    If provided, this HTML/CSS layout will be used to render high-fidelity PDF outputs. Leave blank to default to standard certificate styling.
                </p>
                <textarea class="nu-input" id="certHtmlTemplate" rows="6" placeholder="<div style='text-align:center;'><h1>Certificate</h1><p>{{first_name}} {{last_name}}</p></div>" style="width:100%; font-family:monospace; font-size:12px;"></textarea>
            </div>
        </div>
        <div class="nu-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid var(--border-color); padding-top:12px;">
            <button class="nu-btn nu-btn-ghost" onclick="closeCertModal()">Cancel</button>
            <button class="nu-btn nu-btn-primary" onclick="saveCert()">Save Certificate</button>
        </div>
    </div>
</div>

<script>
function openCertModal() {
    document.getElementById('certId').value = '';
    document.getElementById('certTitle').value = '';
    document.getElementById('certButtonLabel').value = '';
    document.getElementById('certOutputNameTemplate').value = '';
    document.getElementById('certFormCode').value = '';
    document.getElementById('certFileId').value = '';
    document.getElementById('certHtmlTemplate').value = '';
    document.getElementById('selectedFileName').textContent = 'No file chosen';
    document.getElementById('certFile').value = '';
    document.getElementById('certModalTitle').textContent = 'New Certificate Template';
    document.getElementById('certModal').style.display = 'flex';
}

function closeCertModal() {
    document.getElementById('certModal').style.display = 'none';
}

function handleFileSelected(input) {
    let file = input.files[0];
    if (!file) return;

    document.getElementById('selectedFileName').textContent = 'Uploading: ' + file.name + '...';

    let formData = new FormData();
    formData.append('file', file);

    fetch('api/upload.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('certFileId').value = data.file_id;
            document.getElementById('selectedFileName').textContent = '✓ ' + file.name;
            NuApp.toast('Word template file uploaded successfully!', 'success');
        } else {
            document.getElementById('selectedFileName').textContent = 'Error during upload';
            NuApp.toast('File upload failed: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        document.getElementById('selectedFileName').textContent = 'Error during upload';
        NuApp.toast('File upload connection error', 'error');
    });
}

function saveCert() {
    let certId = document.getElementById('certId').value;
    let title = document.getElementById('certTitle').value.trim();
    let btnLabel = document.getElementById('certButtonLabel').value.trim();
    let nameTemplate = document.getElementById('certOutputNameTemplate').value.trim();
    let formCode = document.getElementById('certFormCode').value;
    let fileId = document.getElementById('certFileId').value;
    let htmlTemplate = document.getElementById('certHtmlTemplate').value;

    if (!title) {
        NuApp.toast('Please specify a Certificate Title', 'error');
        return;
    }
    if (!fileId && !certId) {
        NuApp.toast('Please upload a Word (.docx) Template file', 'error');
        return;
    }

    let payload = {
        cert_id: certId,
        cert_title: title,
        cert_button_label: btnLabel,
        cert_output_name_template: nameTemplate,
        cert_form_code: formCode,
        cert_file_id: fileId,
        cert_html_template: htmlTemplate
    };

    fetch('api/word_certificates.php?action=save_template', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            NuApp.toast('Certificate template saved successfully!', 'success');
            closeCertModal();
            // Reload module
            NuApp.loadModule('word_certificates');
        } else {
            NuApp.toast('Failed to save template: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        NuApp.toast('Connection error while saving', 'error');
    });
}

function editCert(id) {
    fetch('api/word_certificates.php?action=list_templates', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            let cert = data.templates.find(c => parseInt(c.cert_id) === id);
            if (cert) {
                document.getElementById('certId').value = cert.cert_id;
                document.getElementById('certTitle').value = cert.cert_title;
                document.getElementById('certButtonLabel').value = cert.cert_button_label || '';
                document.getElementById('certOutputNameTemplate').value = cert.cert_output_name_template || '';
                document.getElementById('certFormCode').value = cert.cert_form_code || '';
                document.getElementById('certFileId').value = cert.cert_file_id || '';
                document.getElementById('certHtmlTemplate').value = cert.cert_html_template || '';
                document.getElementById('selectedFileName').textContent = cert.file_original_name ? '✓ ' + cert.file_original_name : 'No file chosen';
                document.getElementById('certModalTitle').textContent = 'Edit Certificate Template';
                document.getElementById('certModal').style.display = 'flex';
            }
        }
    });
}

function deleteCert(id) {
    if (!confirm('Are you sure you want to permanently delete this certificate template?')) return;

    fetch('api/word_certificates.php?action=delete_template&id=' + id, {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            NuApp.toast('Certificate template deleted successfully', 'success');
            NuApp.loadModule('word_certificates');
        } else {
            NuApp.toast('Failed to delete template: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        NuApp.toast('Connection error while deleting', 'error');
    });
}
</script>
