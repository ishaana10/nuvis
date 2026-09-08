<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/ProjectContext.php';

$db = NuDatabase::getInstance();
$auth = NuAuth::getInstance();
$currentUser = $auth->getUser();
$userRole = $currentUser['usr_role'] ?? 'user';
$isGlobeAdmin = ($userRole === 'globeadmin');

$currentPid = ProjectContext::getId();
$projects = ProjectContext::getAccessibleProjects();
?>

<div class="nu-projects-module">
  <!-- ── HEADER ── -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h2 style="font-size:20px;font-weight:600;margin:0;">Projects Management</h2>
      <p style="color:var(--text-secondary);font-size:13px;margin:2px 0 0;">Manage application projects, team member access, and project exports.</p>
    </div>
    <?php if ($isGlobeAdmin): ?>
    <button class="nu-btn nu-btn-primary" onclick="ProjectsModule.openNew()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Project
    </button>
    <?php endif; ?>
  </div>

  <!-- ── SEARCH BAR ── -->
  <div style="margin-bottom: 16px;">
    <input type="text" id="projectSearchInput" placeholder="Search projects..." oninput="ProjectsModule.filterList(this.value)" class="nu-input" style="max-width: 320px; font-size: 13px; padding: 6px 12px;">
  </div>

  <!-- ── PROJECTS GRID ── -->
  <div class="nu-grid" id="projectCards" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
    <?php foreach ($projects as $p): ?>
    <?php $isCurrent = ((int)$p['project_id'] === $currentPid); ?>
    <div class="nu-card proj-card" data-id="<?= $p['project_id'] ?>" data-name="<?= h($p['project_name']) ?>" data-code="<?= h($p['project_code']) ?>" style="<?= $isCurrent ? 'border: 2px solid var(--color-primary); background: rgba(99,102,241,0.03);' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:6px;">
            <h4 style="font-weight:600;font-size:15px;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($p['project_name']) ?></h4>
            <?php if ($isCurrent): ?>
              <span class="nu-badge nu-badge-primary" style="font-size:10px;">Active</span>
            <?php endif; ?>
          </div>
          <span class="nu-badge nu-badge-secondary" style="font-size:11px;margin-top:4px;display:inline-block;font-family:monospace;"><?= h($p['project_code']) ?></span>
        </div>
        <?php if (!empty($p['project_is_default'])): ?>
          <span class="nu-badge nu-badge-success" style="margin-left:8px;flex-shrink:0;">Default</span>
        <?php endif; ?>
      </div>

      <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px;min-height:32px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
        <?= h($p['project_description'] ?: 'No description provided.') ?>
      </p>

      <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php if (!$isCurrent): ?>
          <button class="nu-btn nu-btn-secondary nu-btn-sm" onclick="ProjectsModule.switchProject(<?= $p['project_id'] ?>)">Switch To</button>
        <?php endif; ?>

        <?php if ($isGlobeAdmin): ?>
          <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="ProjectsModule.openExport(<?= $p['project_id'] ?>, '<?= h($p['project_name'], ENT_QUOTES) ?>')">📥 Export SQL</button>
          <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="ProjectsModule.openMembers(<?= $p['project_id'] ?>, '<?= h($p['project_name'], ENT_QUOTES) ?>')">👥 Members</button>
          <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="ProjectsModule.edit(<?= $p['project_id'] ?>)">✏️ Edit</button>
          <?php if (empty($p['project_is_default'])): ?>
            <button class="nu-btn nu-btn-danger nu-btn-sm" onclick="ProjectsModule.delete(<?= $p['project_id'] ?>, '<?= h($p['project_name'], ENT_QUOTES) ?>')">Delete</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── NEW / EDIT PROJECT MODAL ── -->
<div id="projModal" class="nu-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div class="nu-card" style="width:100%; max-width:480px; padding:24px; background:var(--bg-card); border-radius:8px;">
    <h3 id="projModalTitle" style="font-size:16px; font-weight:600; margin:0 0 16px 0;">New Project</h3>
    <input type="hidden" id="projEditId">

    <div class="nu-field" style="margin-bottom:12px;">
      <label>Project Name <span style="color:var(--color-danger)">*</span></label>
      <input type="text" class="nu-input" id="projName" placeholder="e.g. E-Commerce Portal" oninput="ProjectsModule.autoCode()">
    </div>

    <div class="nu-field" style="margin-bottom:12px;">
      <label>Project Code (Slug) <span style="color:var(--color-danger)">*</span></label>
      <input type="text" class="nu-input" id="projCode" placeholder="e.g. ecommerce">
    </div>

    <div class="nu-field" style="margin-bottom:20px;">
      <label>Description</label>
      <textarea class="nu-input" id="projDescription" rows="3" placeholder="Briefly describe the project purpose"></textarea>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:8px;">
      <button class="nu-btn nu-btn-ghost" onclick="ProjectsModule.closeModal()">Cancel</button>
      <button class="nu-btn nu-btn-primary" onclick="ProjectsModule.saveProject()">Save Project</button>
    </div>
  </div>
</div>

<!-- ── MEMBERS ASSIGNMENT MODAL ── -->
<div id="projMembersModal" class="nu-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div class="nu-card" style="width:100%; max-width:600px; padding:24px; background:var(--bg-card); border-radius:8px;">
    <h3 id="projMembersModalTitle" style="font-size:16px; font-weight:600; margin:0 0 16px 0;">Project Members</h3>
    <input type="hidden" id="projMembersProjId">

    <!-- Add Member Form -->
    <div style="display:grid; grid-template-columns:1fr 140px auto; gap:8px; margin-bottom:16px; padding:12px; background:var(--bg-subtle); border-radius:6px;">
      <select id="projAddUserId" class="nu-input">
        <option value="">Select user...</option>
      </select>
      <select id="projAddRole" class="nu-input">
        <option value="member">Member</option>
        <option value="admin">Admin</option>
        <option value="owner">Owner</option>
      </select>
      <button class="nu-btn nu-btn-primary" onclick="ProjectsModule.addMember()">Add Member</button>
    </div>

    <!-- Members Table -->
    <div style="max-height:280px; overflow-y:auto; border:1px solid var(--border-color); border-radius:6px;">
      <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
          <tr style="background:var(--bg-subtle); text-align:left;">
            <th style="padding:8px 12px;">User</th>
            <th style="padding:8px 12px;">Role</th>
            <th style="padding:8px 12px; text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody id="projMembersTableBody">
          <!-- Populated dynamically -->
        </tbody>
      </table>
    </div>

    <div style="display:flex; justify-content:flex-end; margin-top:16px;">
      <button class="nu-btn nu-btn-secondary" onclick="ProjectsModule.closeMembersModal()">Close</button>
    </div>
  </div>
</div>

<script>
(function() {
  if (window.ProjectsModule && typeof window.ProjectsModule._destroy === 'function') {
    window.ProjectsModule._destroy();
  }

  window.ProjectsModule = (function() {
    var $ = function(id) { return document.getElementById(id); };

    function filterList(query) {
      query = (query || '').toLowerCase().trim();
      var cards = document.querySelectorAll('#projectCards .proj-card');
      cards.forEach(function(card) {
        var name = (card.dataset.name || '').toLowerCase();
        var code = (card.dataset.code || '').toLowerCase();
        if (name.includes(query) || code.includes(query)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }

    function autoCode() {
      if ($('projEditId') && $('projEditId').value) return;
      var name = $('projName') ? $('projName').value : '';
      if ($('projCode')) {
        $('projCode').value = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
      }
    }

    function openNew() {
      $('projEditId').value = '';
      $('projName').value = '';
      $('projCode').value = '';
      $('projDescription').value = '';
      $('projCode').readOnly = false;
      $('projModalTitle').textContent = 'New Project';
      $('projModal').style.display = 'flex';
    }

    async function edit(id) {
      try {
        var res = await fetch('api/projects.php?action=get&project_id=' + id);
        var data = await res.json();
        if (data.success && data.project) {
          var p = data.project;
          $('projEditId').value = p.project_id;
          $('projName').value = p.project_name;
          $('projCode').value = p.project_code;
          $('projCode').readOnly = true;
          $('projDescription').value = p.project_description || '';
          $('projModalTitle').textContent = 'Edit Project';
          $('projModal').style.display = 'flex';
        } else {
          alert(data.error || 'Failed to load project details');
        }
      } catch (e) {
        alert('Network error loading project');
      }
    }

    function closeModal() {
      $('projModal').style.display = 'none';
    }

    async function saveProject() {
      var id = $('projEditId').value;
      var name = $('projName').value.trim();
      var code = $('projCode').value.trim();
      var desc = $('projDescription').value.trim();

      if (!name || !code) {
        alert('Project Name and Code are required');
        return;
      }

      var action = id ? 'update' : 'create';
      var payload = {
        project_id: id ? parseInt(id) : null,
        project_name: name,
        project_code: code,
        project_description: desc
      };

      try {
        var res = await fetch('api/projects.php?action=' + action, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        var data = await res.json();
        if (data.success) {
          closeModal();
          window.location.reload();
        } else {
          alert(data.error || 'Failed to save project');
        }
      } catch (e) {
        alert('Network error saving project');
      }
    }

    async function switchProject(id) {
      try {
        var res = await fetch('api/projects.php?action=switch', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ project_id: id })
        });
        var data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert(data.error || 'Failed to switch project');
        }
      } catch (e) {
        alert('Network error switching project');
      }
    }

    async function delProject(id, name) {
      if (!confirm('Are you sure you want to delete project "' + name + '"?')) return;
      try {
        var res = await fetch('api/projects.php?action=delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ project_id: id })
        });
        var data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert(data.error || 'Failed to delete project');
        }
      } catch (e) {
        alert('Network error deleting project');
      }
    }

    function openExport(id, name) {
      window.location.href = 'api/projects.php?action=export_project&project_id=' + id;
    }

    async function openMembers(id, name) {
      $('projMembersProjId').value = id;
      $('projMembersModalTitle').textContent = 'Project Members: ' + name;
      $('projMembersModal').style.display = 'flex';
      await loadMembers(id);
    }

    async function loadMembers(id) {
      try {
        var res = await fetch('api/projects.php?action=members_list&project_id=' + id);
        var data = await res.json();
        if (data.success) {
          // Populate user select dropdown
          var userSelect = $('projAddUserId');
          userSelect.innerHTML = '<option value="">Select user...</option>';
          (data.all_users || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.usr_id;
            opt.textContent = (u.usr_name || u.usr_username) + ' (' + u.usr_username + ')';
            userSelect.appendChild(opt);
          });

          // Populate members table
          var tbody = $('projMembersTableBody');
          tbody.innerHTML = '';
          if (data.members && data.members.length > 0) {
            data.members.forEach(function(m) {
              var tr = document.createElement('tr');
              tr.style.borderBottom = '1px solid var(--border-color)';
              tr.innerHTML = '<td style="padding:8px 12px; font-weight:500;">' + (m.usr_name || m.usr_username) + ' <small style="color:var(--text-tertiary);">(' + m.usr_username + ')</small></td>' +
                             '<td style="padding:8px 12px;"><span class="nu-badge nu-badge-secondary" style="font-size:11px;">' + m.pm_role + '</span></td>' +
                             '<td style="padding:8px 12px; text-align:right;"><button class="nu-btn nu-btn-danger nu-btn-sm" onclick="ProjectsModule.removeMember(' + m.pm_user_id + ')">Remove</button></td>';
              tbody.appendChild(tr);
            });
          } else {
            tbody.innerHTML = '<tr><td colspan="3" style="padding:16px; text-align:center; color:var(--text-tertiary);">No members explicitly assigned. GlobeAdmin users access all projects.</td></tr>';
          }
        } else {
          alert(data.error || 'Failed to load members');
        }
      } catch (e) {
        alert('Network error loading members');
      }
    }

    async function addMember() {
      var projId = $('projMembersProjId').value;
      var userId = $('projAddUserId').value;
      var role   = $('projAddRole').value;

      if (!projId || !userId) {
        alert('Please select a user');
        return;
      }

      try {
        var res = await fetch('api/projects.php?action=member_add', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ project_id: parseInt(projId), user_id: parseInt(userId), role: role })
        });
        var data = await res.json();
        if (data.success) {
          await loadMembers(projId);
        } else {
          alert(data.error || 'Failed to add member');
        }
      } catch (e) {
        alert('Network error adding member');
      }
    }

    async function removeMember(userId) {
      var projId = $('projMembersProjId').value;
      if (!confirm('Remove this user from the project?')) return;

      try {
        var res = await fetch('api/projects.php?action=member_remove', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ project_id: parseInt(projId), user_id: parseInt(userId) })
        });
        var data = await res.json();
        if (data.success) {
          await loadMembers(projId);
        } else {
          alert(data.error || 'Failed to remove member');
        }
      } catch (e) {
        alert('Network error removing member');
      }
    }

    function closeMembersModal() {
      $('projMembersModal').style.display = 'none';
    }

    function _destroy() {
      window.ProjectsModule = null;
    }

    return {
      openNew: openNew,
      edit: edit,
      closeModal: closeModal,
      saveProject: saveProject,
      switchProject: switchProject,
      delete: delProject,
      openExport: openExport,
      openMembers: openMembers,
      addMember: addMember,
      removeMember: removeMember,
      closeMembersModal: closeMembersModal,
      autoCode: autoCode,
      filterList: filterList,
      _destroy: _destroy
    };
  })();
})();
</script>
