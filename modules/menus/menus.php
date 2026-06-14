<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';
// $auth, $nuConfig available. Session open under 'nu5sess'.

$db    = NuDatabase::getInstance();
$menus = $db->fetchAll("SELECT * FROM nu_menus ORDER BY menu_parent_id ASC, menu_order ASC");

// Build form list for target dropdown
$forms = $db->fetchAll("SELECT form_code, form_name, form_type FROM nu_forms WHERE form_active = 1 ORDER BY form_name");

// Organise into a parent -> children map for tree rendering
$menuMap = []; // parent_id => [items]
foreach ($menus as $m) {
    $pid = (int)($m['menu_parent_id'] ?? 0);
    $menuMap[$pid][] = $m;
}
?>

<style>
/* ── Menu Builder Styles ──────────────────────────────────────── */

/* ── Menu list tree ── */
.nb-menu-list { display:flex; flex-direction:column; gap:4px; }

.nb-menu-item {
  display:flex; align-items:center; gap:8px;
  padding:8px 12px; border-radius:8px;
  border:1px solid var(--border-color);
  background:var(--bg-surface);
  transition:border-color .15s, background .15s;
  cursor:default;
}
.nb-menu-item:hover { border-color:color-mix(in oklch,var(--color-primary) 40%,var(--border-color)); background:var(--bg-elevated); }
.nb-menu-item.is-child { margin-left:28px; background:color-mix(in oklch,var(--bg-elevated) 60%,var(--bg-surface)); }
.nb-menu-item.is-divider { opacity:.6; }

.nb-menu-drag-handle {
  cursor:grab; color:var(--text-tertiary); font-size:15px;
  padding:0 2px; user-select:none; flex-shrink:0;
}
.nb-menu-drag-handle:active { cursor:grabbing; }

.nb-menu-icon-preview {
  width:28px; height:28px; border-radius:6px; flex-shrink:0;
  background:color-mix(in oklch,var(--color-primary) 10%,transparent);
  color:var(--color-primary);
  display:flex; align-items:center; justify-content:center;
  font-size:14px;
}

.nb-menu-label { flex:1; font-size:13px; font-weight:600; color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.nb-menu-label-sub { font-size:11px; color:var(--text-tertiary); font-weight:400; margin-left:4px; }

.nb-menu-type-badge {
  font-size:9px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
  padding:2px 8px; border-radius:20px; flex-shrink:0;
}
.nb-menu-type-badge.form    { background:color-mix(in oklch,var(--color-primary) 12%,transparent); color:var(--color-primary); }
.nb-menu-type-badge.report  { background:color-mix(in oklch,#10b981 15%,transparent); color:#047857; }
.nb-menu-type-badge.query   { background:color-mix(in oklch,#8b5cf6 15%,transparent); color:#6d28d9; }
.nb-menu-type-badge.url     { background:color-mix(in oklch,#f59e0b 15%,transparent); color:#b45309; }
.nb-menu-type-badge.divider { background:var(--bg-elevated); color:var(--text-tertiary); }
.nb-menu-type-badge.group   { background:color-mix(in oklch,#3b82f6 15%,transparent); color:#1d4ed8; }

.nb-menu-order-badge {
  font-size:10px; color:var(--text-tertiary); font-weight:600;
  background:var(--bg-elevated); border:1px solid var(--border-color);
  padding:1px 6px; border-radius:4px; flex-shrink:0;
}

.nb-menu-actions { display:flex; gap:4px; flex-shrink:0; }
.nb-menu-btn {
  padding:3px 8px; border-radius:5px; font-size:10px; font-weight:500;
  border:1px solid var(--border-color); background:none; cursor:pointer;
  color:var(--text-secondary); transition:all .15s;
}
.nb-menu-btn:hover { background:var(--bg-elevated); border-color:var(--color-primary); color:var(--color-primary); }
.nb-menu-btn.del:hover { background:#fee; border-color:#e55; color:#c33; }

/* drag-over indicator */
.nb-menu-item.drag-over {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 6%,var(--bg-surface));
}

/* ── Builder panel ── */
#menuBuilderCard .nb-fp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
#menuBuilderCard .nb-fp { display:flex; flex-direction:column; gap:4px; }
#menuBuilderCard .nb-fp label { font-size:11px; font-weight:600; color:var(--text-secondary); }
#menuBuilderCard .nb-fp-full { grid-column:1/-1; }

/* Type selector cards */
.nb-mtype-cards { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:0; }
.nb-mtype-card {
  flex:1; min-width:90px; border:2px solid var(--border-color); border-radius:10px;
  padding:10px 8px; cursor:pointer; background:var(--bg-surface);
  transition:all .15s; text-align:center;
}
.nb-mtype-card:hover { border-color:var(--color-primary); background:var(--bg-elevated); }
.nb-mtype-card.selected {
  border-color:var(--color-primary);
  background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface));
}
.nb-mtype-card input[type=radio] { display:none; }
.nb-mtype-icon  { font-size:18px; margin-bottom:4px; }
.nb-mtype-label { font-size:11px; font-weight:700; color:var(--text-primary); }

/* Icon grid picker */
.nb-icon-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(36px,1fr)); gap:4px;
  max-height:160px; overflow-y:auto; margin-top:6px;
  padding:6px; border:1px solid var(--border-color); border-radius:8px;
  background:var(--bg-elevated);
}
.nb-icon-btn {
  width:34px; height:34px; border-radius:6px; border:1.5px solid transparent;
  display:flex; align-items:center; justify-content:center;
  font-size:16px; cursor:pointer; transition:all .12s;
  background:var(--bg-surface);
}
.nb-icon-btn:hover { border-color:var(--color-primary); background:color-mix(in oklch,var(--color-primary) 8%,var(--bg-surface)); }
.nb-icon-btn.selected { border-color:var(--color-primary); background:color-mix(in oklch,var(--color-primary) 14%,var(--bg-surface)); }

/* Save bar */
.nb-menu-save-bar {
  display:flex !important; justify-content:flex-end; align-items:center;
  gap:8px; margin-top:20px; padding-top:16px;
  border-top:1px solid var(--border-color);
}

/* Empty state */
.nb-menu-empty {
  text-align:center; padding:56px 24px;
  color:var(--text-tertiary); font-size:13px;
}
.nb-menu-empty svg { margin:0 auto 12px; color:var(--text-tertiary); }
</style>

<div class="nu-menus">

  <!-- ── Menu List ──────────────────────────────────────────────── -->
  <div id="menuListSection">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 class="nu-card-title">Menu Builder</h3>
      <?php if ($auth->hasPermission('menus','create')): ?>
      <button class="nu-btn nu-btn-primary" onclick="nuMenuBuilder.open()">+ New Item</button>
      <?php endif; ?>
    </div>

    <div class="nu-card" style="padding:16px;">

      <!-- Tree list -->
      <div class="nb-menu-list" id="menuTree">

        <?php if (empty($menus)): ?>
        <div class="nb-menu-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            <circle cx="20" cy="6" r="2" fill="currentColor" stroke="none"/>
            <circle cx="20" cy="12" r="2" fill="currentColor" stroke="none"/>
            <circle cx="20" cy="18" r="2" fill="currentColor" stroke="none"/>
          </svg>
          <p style="margin-bottom:12px;">No menu items yet. Click &ldquo;New Item&rdquo; to build your navigation.</p>
          <button class="nu-btn nu-btn-primary" onclick="nuMenuBuilder.open()">+ New Item</button>
        </div>
        <?php else: ?>

        <?php
        // ── Render top-level items, then their children inline ──
        $topItems = $menuMap[0] ?? [];
        foreach ($topItems as $m):
          $mid    = (int)$m['menu_id'];
          $label  = htmlspecialchars($m['menu_label'], ENT_QUOTES);
          $type   = htmlspecialchars($m['menu_type']  ?? 'form', ENT_QUOTES);
          $target = htmlspecialchars($m['menu_target'] ?? '', ENT_QUOTES);
          $icon   = htmlspecialchars($m['menu_icon']   ?? '☰', ENT_QUOTES);
          $order  = (int)$m['menu_order'];
          $isDivider = $type === 'divider';
        ?>
        <!-- Top-level item -->
        <div class="nb-menu-item<?= $isDivider ? ' is-divider' : '' ?>" data-id="<?= $mid ?>" draggable="true">
          <span class="nb-menu-drag-handle" title="Drag to reorder">☰</span>
          <div class="nb-menu-icon-preview"><?= $icon ?></div>
          <span class="nb-menu-label">
            <?= htmlspecialchars($m['menu_label']) ?>
            <?php if ($target): ?><span class="nb-menu-label-sub">&rarr; <?= $target ?></span><?php endif; ?>
          </span>
          <span class="nb-menu-type-badge <?= $type ?>"><?= ucfirst($type) ?></span>
          <span class="nb-menu-order-badge"><?= $order ?></span>
          <div class="nb-menu-actions">
            <button class="nb-menu-btn" onclick="nuMenuBuilder.addChild(<?= $mid ?>, '<?= $label ?>')">+ Child</button>
            <button class="nb-menu-btn" onclick="nuMenuBuilder.edit(<?= $mid ?>)">&#x270E; Edit</button>
            <button class="nb-menu-btn del" onclick="nuMenuBuilder.del(<?= $mid ?>, '<?= $label ?>')">Delete</button>
          </div>
        </div>

        <?php
        // ── Children of this top-level item ──
        $children = $menuMap[$mid] ?? [];
        foreach ($children as $c):
          $cid    = (int)$c['menu_id'];
          $clabel = htmlspecialchars($c['menu_label'], ENT_QUOTES);
          $ctype  = htmlspecialchars($c['menu_type']  ?? 'form', ENT_QUOTES);
          $ctarget= htmlspecialchars($c['menu_target'] ?? '', ENT_QUOTES);
          $cicon  = htmlspecialchars($c['menu_icon']   ?? '&#x25B8;', ENT_QUOTES);
          $corder = (int)$c['menu_order'];
        ?>
        <div class="nb-menu-item is-child" data-id="<?= $cid ?>" data-parent="<?= $mid ?>" draggable="true">
          <span class="nb-menu-drag-handle" title="Drag to reorder">☰</span>
          <div class="nb-menu-icon-preview" style="font-size:12px;"><?= $cicon ?></div>
          <span class="nb-menu-label">
            <?= htmlspecialchars($c['menu_label']) ?>
            <?php if ($ctarget): ?><span class="nb-menu-label-sub">&rarr; <?= $ctarget ?></span><?php endif; ?>
          </span>
          <span class="nb-menu-type-badge <?= $ctype ?>"><?= ucfirst($ctype) ?></span>
          <span class="nb-menu-order-badge"><?= $corder ?></span>
          <div class="nb-menu-actions">
            <button class="nb-menu-btn" onclick="nuMenuBuilder.edit(<?= $cid ?>)">&#x270E; Edit</button>
            <button class="nb-menu-btn del" onclick="nuMenuBuilder.del(<?= $cid ?>, '<?= $clabel ?>')">Delete</button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <?php endif; ?>
      </div><!-- /menuTree -->
    </div><!-- /nu-card -->
  </div><!-- /menuListSection -->


  <!-- ── Builder Panel ──────────────────────────────────────────── -->
  <div class="nu-card" id="menuBuilderCard" style="display:none;margin-top:24px;">
    <input type="hidden" id="editMenuId" value="">
    <input type="hidden" id="editMenuParentId" value="0">
    <input type="hidden" id="editMenuIcon" value="☰">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 class="nu-card-title" id="menuBuilderTitle">New Menu Item</h3>
      <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="nuMenuBuilder.close()">&#x2715; Cancel</button>
    </div>

    <!-- Type selector -->
    <div style="margin-bottom:16px;">
      <label class="nu-label" style="margin-bottom:8px;display:block;">Item Type</label>
      <div class="nb-mtype-cards" id="nbMenuTypeCards">
        <label class="nb-mtype-card selected" onclick="nuMenuBuilder.selectType('form',this)">
          <input type="radio" name="menuItemType" value="form" checked>
          <div class="nb-mtype-icon">⊞</div>
          <div class="nb-mtype-label">Form</div>
        </label>
        <label class="nb-mtype-card" onclick="nuMenuBuilder.selectType('report',this)">
          <input type="radio" name="menuItemType" value="report">
          <div class="nb-mtype-icon">📊</div>
          <div class="nb-mtype-label">Report</div>
        </label>
        <label class="nb-mtype-card" onclick="nuMenuBuilder.selectType('query',this)">
          <input type="radio" name="menuItemType" value="query">
          <div class="nb-mtype-icon">🔍</div>
          <div class="nb-mtype-label">Query</div>
        </label>
        <label class="nb-mtype-card" onclick="nuMenuBuilder.selectType('url',this)">
          <input type="radio" name="menuItemType" value="url">
          <div class="nb-mtype-icon">🔗</div>
          <div class="nb-mtype-label">URL</div>
        </label>
        <label class="nb-mtype-card" onclick="nuMenuBuilder.selectType('group',this)">
          <input type="radio" name="menuItemType" value="group">
          <div class="nb-mtype-icon">📂</div>
          <div class="nb-mtype-label">Group</div>
        </label>
        <label class="nb-mtype-card" onclick="nuMenuBuilder.selectType('divider',this)">
          <input type="radio" name="menuItemType" value="divider">
          <div class="nb-mtype-icon">―</div>
          <div class="nb-mtype-label">Divider</div>
        </label>
      </div>
    </div>

    <!-- Fields grid -->
    <div class="nb-fp-grid">

      <div class="nb-fp">
        <label>Label</label>
        <input type="text" id="menuLabel" class="nu-input" placeholder="e.g. Customers">
      </div>

      <!-- Target — changes based on type -->
      <div class="nb-fp" id="menuTargetWrap">
        <label id="menuTargetLabel">Form Code</label>
        <!-- Form/Report select -->
        <select id="menuTargetSelect" class="nu-input">
          <option value="">-- select --</option>
          <?php foreach ($forms as $f): ?>
          <option value="<?= htmlspecialchars($f['form_code'], ENT_QUOTES) ?>"
                  data-type="<?= htmlspecialchars($f['form_type'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($f['form_name']) ?>
            (<?= htmlspecialchars($f['form_code']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <!-- URL text input (hidden by default) -->
        <input type="text" id="menuTargetUrl" class="nu-input" placeholder="https://" style="display:none;">
        <!-- Free-text code input (for query) -->
        <input type="text" id="menuTargetCode" class="nu-input" placeholder="query_code" style="display:none;">
      </div>

      <div class="nb-fp">
        <label>Parent Item <span style="font-weight:400;color:var(--text-tertiary);">(0 = top level)</span></label>
        <select id="menuParent" class="nu-input">
          <option value="0">-- Top Level --</option>
          <?php foreach ($menus as $pm): ?>
          <?php if (($pm['menu_parent_id'] ?? 0) == 0 && ($pm['menu_type'] ?? '') !== 'divider'): ?>
          <option value="<?= (int)$pm['menu_id'] ?>">
            <?= htmlspecialchars($pm['menu_label']) ?>
          </option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="nb-fp">
        <label>Order <span style="font-weight:400;color:var(--text-tertiary);">(lower = higher up)</span></label>
        <input type="number" id="menuOrder" class="nu-input" value="0" min="0">
      </div>

      <div class="nb-fp">
        <label>Role Visibility <span style="font-weight:400;color:var(--text-tertiary);">(blank = all roles)</span></label>
        <input type="text" id="menuRoles" class="nu-input" placeholder="admin,manager">
      </div>

      <div class="nb-fp">
        <label style="display:flex;align-items:center;gap:6px;">
          <input type="checkbox" id="menuActive" checked> Active
        </label>
      </div>

      <!-- Icon picker full-width -->
      <div class="nb-fp nb-fp-full">
        <label>Icon <span style="font-weight:400;color:var(--text-tertiary);">(click to select · or type emoji/SVG in the input below)</span></label>
        <div class="nb-icon-grid" id="iconGrid">
          <?php
          $icons = [
            '⌂','⊞','⊟','⊝','▦','▣','■','□','▪','▫',
            '📊','📈','📉','📌','📎','📋','📄','🗂','📁','📂',
            '🔍','🔎','💻','🖥','📱','📧','📨','🔔','🔕',
            '👤','👥','👨‍💼','👩‍💼','🧑‍💼',
            '⚙','🔧','🔨','💡','🔑','🔒','🔓','🛡','⚠',
            '✅','❌','✔','✖','➕','➖','↗','↘','↩','↪',
            '🏠','🏢','🏦','🏪','🏫','🏗',
            '💰','💳','💴','💵','📆','🗓',
            '☰','≡','≙','⋯','•','▸','▾','◂','▹',
          ];
          foreach ($icons as $ic):
          ?>
          <button type="button" class="nb-icon-btn" data-icon="<?= htmlspecialchars($ic, ENT_QUOTES) ?>"
                  onclick="nuMenuBuilder.selectIcon('<?= htmlspecialchars($ic, ENT_QUOTES) ?>', this)">
            <?= $ic ?>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="text" id="menuIconCustom" class="nu-input" placeholder="Custom icon / emoji" style="margin-top:6px;"
               oninput="nuMenuBuilder.setCustomIcon(this.value)">
      </div>

    </div><!-- /nb-fp-grid -->

    <!-- Save bar -->
    <div class="nb-menu-save-bar">
      <button type="button" class="nu-btn nu-btn-ghost" onclick="nuMenuBuilder.close()">Cancel</button>
      <button type="button" class="nu-btn nu-btn-primary" onclick="nuMenuBuilder.save()">💾 Save Item</button>
    </div>
  </div><!-- /menuBuilderCard -->

</div><!-- /nu-menus -->

<script>
// ── Guard: only initialise once per page load ──────────────────
if (!window._nbMenusModuleInit) {
  window._nbMenusModuleInit = true;

  window.nuMenuBuilder = {

    // ── Open builder for new item ──────────────────────────────────
    open: function(parentId, parentLabel) {
      document.getElementById('editMenuId').value       = '';
      document.getElementById('editMenuParentId').value = parentId || 0;
      document.getElementById('menuBuilderTitle').textContent =
        parentId ? ('New Item under \u201c' + (parentLabel || '#'+parentId) + '\u201d') : 'New Menu Item';

      // Reset fields
      document.getElementById('menuLabel').value    = '';
      document.getElementById('menuOrder').value    = 0;
      document.getElementById('menuRoles').value    = '';
      document.getElementById('menuActive').checked = true;
      document.getElementById('menuIconCustom').value = '';
      document.getElementById('editMenuIcon').value = '☰';
      document.getElementById('menuParent').value   = parentId || 0;

      // Reset type to form
      this.selectType('form', document.querySelector('.nb-mtype-card'));

      // Deselect all icon buttons
      document.querySelectorAll('.nb-icon-btn').forEach(b => b.classList.remove('selected'));
      var firstBtn = document.querySelector('.nb-icon-btn');
      if (firstBtn) { firstBtn.classList.add('selected'); document.getElementById('editMenuIcon').value = firstBtn.dataset.icon; }

      document.getElementById('menuListSection').style.display  = 'none';
      document.getElementById('menuBuilderCard').style.display  = '';
    },

    // ── Open builder pre-filled for a child item ───────────────────
    addChild: function(parentId, parentLabel) {
      this.open(parentId, parentLabel);
    },

    // ── Close builder, show list ────────────────────────────────────
    close: function() {
      document.getElementById('menuBuilderCard').style.display  = 'none';
      document.getElementById('menuListSection').style.display  = '';
    },

    // ── Edit an existing item (load via API) ───────────────────────
    edit: function(id) {
      var self = this;
      fetch('api/menus.php?action=get&id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d) {
          if (!d.success) { alert(d.message || 'Could not load item.'); return; }
          var m = d.menu;
          document.getElementById('editMenuId').value       = m.menu_id;
          document.getElementById('editMenuParentId').value = m.menu_parent_id || 0;
          document.getElementById('menuBuilderTitle').textContent = 'Edit: ' + m.menu_label;
          document.getElementById('menuLabel').value    = m.menu_label  || '';
          document.getElementById('menuOrder').value    = m.menu_order  || 0;
          document.getElementById('menuRoles').value    = m.menu_roles  || '';
          document.getElementById('menuActive').checked = (m.menu_active == 1);
          document.getElementById('menuParent').value   = m.menu_parent_id || 0;

          // Type
          var typeCard = document.querySelector('.nb-mtype-card[onclick*="\'' + m.menu_type + '\'"]');
          self.selectType(m.menu_type, typeCard);

          // Target
          var type = m.menu_type;
          if (type === 'url') {
            document.getElementById('menuTargetUrl').value = m.menu_target || '';
          } else if (type === 'query') {
            document.getElementById('menuTargetCode').value = m.menu_target || '';
          } else {
            document.getElementById('menuTargetSelect').value = m.menu_target || '';
          }

          // Icon
          var icon = m.menu_icon || '☰';
          document.getElementById('editMenuIcon').value   = icon;
          document.getElementById('menuIconCustom').value = icon;
          document.querySelectorAll('.nb-icon-btn').forEach(function(b) {
            b.classList.toggle('selected', b.dataset.icon === icon);
          });

          document.getElementById('menuListSection').style.display = 'none';
          document.getElementById('menuBuilderCard').style.display = '';
        })
        .catch(function(e){ console.error(e); alert('Network error.'); });
    },

    // ── Select item type ───────────────────────────────────────────
    selectType: function(type, card) {
      document.querySelectorAll('.nb-mtype-card').forEach(c => c.classList.remove('selected'));
      if (card) card.classList.add('selected');
      var radio = document.querySelector('input[name="menuItemType"][value="' + type + '"]');
      if (radio) radio.checked = true;

      var labelEl = document.getElementById('menuTargetLabel');
      var selEl   = document.getElementById('menuTargetSelect');
      var urlEl   = document.getElementById('menuTargetUrl');
      var codeEl  = document.getElementById('menuTargetCode');
      var wrapEl  = document.getElementById('menuTargetWrap');

      selEl.style.display  = 'none';
      urlEl.style.display  = 'none';
      codeEl.style.display = 'none';

      if (type === 'form' || type === 'report') {
        wrapEl.style.display = '';
        labelEl.textContent  = (type === 'form') ? 'Form' : 'Report';
        selEl.style.display  = '';
        Array.from(selEl.options).forEach(function(opt) {
          if (!opt.value) return;
          opt.style.display = (opt.dataset.type === type) ? '' : 'none';
        });
      } else if (type === 'query') {
        wrapEl.style.display = '';
        labelEl.textContent  = 'Query Code';
        codeEl.style.display = '';
      } else if (type === 'url') {
        wrapEl.style.display = '';
        labelEl.textContent  = 'URL';
        urlEl.style.display  = '';
      } else {
        // group / divider — no target
        wrapEl.style.display = 'none';
      }
    },

    // ── Icon selection ─────────────────────────────────────────────
    selectIcon: function(icon, btn) {
      document.querySelectorAll('.nb-icon-btn').forEach(b => b.classList.remove('selected'));
      if (btn) btn.classList.add('selected');
      document.getElementById('editMenuIcon').value   = icon;
      document.getElementById('menuIconCustom').value = icon;
    },

    setCustomIcon: function(val) {
      document.getElementById('editMenuIcon').value = val || '☰';
      document.querySelectorAll('.nb-icon-btn').forEach(b => {
        b.classList.toggle('selected', b.dataset.icon === val);
      });
    },

    // ── Save (create or update) ────────────────────────────────────
    save: function() {
      var id     = document.getElementById('editMenuId').value.trim();
      var type   = (document.querySelector('input[name="menuItemType"]:checked') || {}).value || 'form';
      var label  = document.getElementById('menuLabel').value.trim();
      var order  = parseInt(document.getElementById('menuOrder').value, 10) || 0;
      var parent = parseInt(document.getElementById('menuParent').value, 10) || 0;
      var roles  = document.getElementById('menuRoles').value.trim();
      var active = document.getElementById('menuActive').checked ? 1 : 0;
      var icon   = document.getElementById('editMenuIcon').value || '☰';

      var target = '';
      if (type === 'url') {
        target = document.getElementById('menuTargetUrl').value.trim();
      } else if (type === 'query') {
        target = document.getElementById('menuTargetCode').value.trim();
      } else if (type === 'form' || type === 'report') {
        target = document.getElementById('menuTargetSelect').value;
      }

      if (!label && type !== 'divider') {
        document.getElementById('menuLabel').focus();
        return;
      }

      var payload = { id: id, type: type, label: label, target: target,
                      parent: parent, order: order, roles: roles, active: active, icon: icon };

      fetch('api/menus.php?action=' + (id ? 'update' : 'create'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.success) {
          if (window.NuApp) NuApp.loadModule('menus');
          else location.reload();
        } else {
          alert(d.message || 'Save failed.');
        }
      })
      .catch(function(e){ console.error(e); alert('Network error.'); });
    },

    // ── Delete ─────────────────────────────────────────────────────
    del: function(id, label) {
      if (!confirm('Delete \u201c' + label + '\u201d? Its child items will also be removed.')) return;
      fetch('api/menus.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.success) {
          if (window.NuApp) NuApp.loadModule('menus');
          else location.reload();
        } else {
          alert(d.message || 'Delete failed.');
        }
      })
      .catch(function(e){ console.error(e); alert('Network error.'); });
    }

  }; // end nuMenuBuilder


  // ── Drag-to-reorder ────────────────────────────────────────────
  (function() {
    var dragging = null;

    function bindDrag() {
      document.querySelectorAll('.nb-menu-item[draggable]').forEach(function(el) {
        el.addEventListener('dragstart', function(e) {
          dragging = el;
          el.style.opacity = '.4';
          e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', function() {
          el.style.opacity = '';
          document.querySelectorAll('.nb-menu-item').forEach(function(r){ r.classList.remove('drag-over'); });
          dragging = null;
        });
        el.addEventListener('dragover', function(e) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          if (el !== dragging) el.classList.add('drag-over');
        });
        el.addEventListener('dragleave', function() {
          el.classList.remove('drag-over');
        });
        el.addEventListener('drop', function(e) {
          e.preventDefault();
          el.classList.remove('drag-over');
          if (!dragging || dragging === el) return;
          var tree = document.getElementById('menuTree');
          var nodes = Array.from(tree.children).filter(function(n){ return n.classList.contains('nb-menu-item'); });
          var fromIdx = nodes.indexOf(dragging);
          var toIdx   = nodes.indexOf(el);
          if (fromIdx < 0 || toIdx < 0) return;
          if (fromIdx < toIdx) { el.after(dragging); } else { el.before(dragging); }
          // Persist new order via API
          var ordered = Array.from(tree.querySelectorAll('.nb-menu-item')).map(function(n, i){
            return { id: n.dataset.id, order: i };
          });
          fetch('api/menus.php?action=reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: ordered })
          }).catch(function(err){ console.warn('reorder:', err); });
        });
      });
    }

    bindDrag();
  })();

}
</script>
