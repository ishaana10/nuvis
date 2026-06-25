/**
 * nb-form-builder.js
 * Single consolidated file replacing:
 *   nb-sf-data.js + nb-subform-fk-builder.js +
 *   nb-form-builder-layout.js + nb-form-edit.js
 *
 * Defines window.nbFormBuilder (the core builder object) and
 * window.saveForm. Subform panel injection, FK builder, layout
 * row/span patches, canvas rebuild, and edit restore are all here.
 *
 * Load after nubuilder-next.js, before nusubform.js.
 *
 * Class-naming convention:
 *   nu-field-*  per-field config inputs  (label, name, required, …)
 *   nu-lookup-* lookup-specific inputs   (table, display, store, filter, extra)
 *   nb-*        builder canvas structure (rows, card shell, span bar, …)
 */
(function (window) {
  'use strict';

  /* ════════════════════════════════════════════════════════════════════
     SECTION 1 — _nbSfData  (was nb-sf-data.js)
     Centralised read/write of data-sf-* attributes on canvas cards.
     Tracks subform_view (grid|form) and help_text.
     NOTE: is_fk / hide_in_grid / server_readonly are FK-field flags
     that belong to the *child* FK field, not the subform widget itself.
     They are stored inside the `subform` sub-object in the layout JSON
     and kept in data-sf-* only for the builder UI panel to pre-fill.
  ═══════════════════════════════════════════════════════════════════ */
  (function () {
    function _sfRead(card) {
      var fc = card.dataset.sfFormCode || '';
      if (fc) {
        return {
          form_code:       fc,
          fk_field:        card.dataset.sfFkField        || '',
          subform_view:    card.dataset.sfSubformView    || 'grid',
          help_text:       card.dataset.sfHelpText       || '',
          is_fk:           card.dataset.sfIsFk           === '1',
          hide_in_grid:    card.dataset.sfHideInGrid     === '1',
          server_readonly: card.dataset.sfServerReadonly === '1'
        };
      }
      var raw = card.dataset.fieldJson || card.dataset.fieldData || '';
      if (raw) {
        try {
          var obj = JSON.parse(raw);
          var sf  = (obj.subform && typeof obj.subform === 'object') ? obj.subform : {};
          var fc2 = sf.form_code || sf.formcode || '';
          if (fc2) {
            _sfWrite(card, {
              form_code:       fc2,
              fk_field:        sf.fk_field || sf.fkfield || '',
              subform_view:    obj.subform_view || sf.subform_view || 'grid',
              help_text:       obj.help_text || obj.field_help_text || '',
              is_fk:           !!sf.is_fk,
              hide_in_grid:    !!sf.hide_in_grid,
              server_readonly: !!sf.server_readonly
            });
            return _sfRead(card);
          }
        } catch (e) {}
      }
      return {
        form_code:       card.dataset.subformFormCode || card.dataset.formCode || '',
        fk_field:        card.dataset.subformFkField  || card.dataset.fkField  || '',
        subform_view:    'grid',
        help_text:       '',
        is_fk: false, hide_in_grid: false, server_readonly: false
      };
    }

    function _sfWrite(card, obj) {
      if (!obj) return;
      if (obj.form_code)    card.dataset.sfFormCode       = obj.form_code;
      if (obj.fk_field)     card.dataset.sfFkField        = obj.fk_field;
      if (obj.subform_view) card.dataset.sfSubformView    = obj.subform_view;
      if (obj.help_text !== undefined) card.dataset.sfHelpText = obj.help_text;
      card.dataset.sfIsFk           = obj.is_fk           ? '1' : '0';
      card.dataset.sfHideInGrid     = obj.hide_in_grid    ? '1' : '0';
      card.dataset.sfServerReadonly = obj.server_readonly ? '1' : '0';
    }

    function _sfClear(card) {
      ['sfFormCode','sfFkField','sfSubformView','sfHelpText',
       'sfIsFk','sfHideInGrid','sfServerReadonly',
       'fieldJson','fieldData'].forEach(function (k) { delete card.dataset[k]; });
    }

    window._nbSfData = { read: _sfRead, write: _sfWrite, clear: _sfClear };
  }());


  /* ════════════════════════════════════════════════════════════════════
     SECTION 2 — nbFormBuilder core object
  ═══════════════════════════════════════════════════════════════════ */
  var _fieldCounter = 0;

  function _esc(s) {
    return String(s || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Shared visibility/access flags block (appended to every field) ── */
  function _visibilityFlagsHTML(extra) {
    extra = extra || {};
    var isReadonly   = extra.readonly              ? ' checked' : '';
    var isHidden     = extra.hidden                ? ' checked' : '';
    var isHiddenNorm = extra.hidden_for_normal_users ? ' checked' : '';
    var isNoDup      = extra.no_duplicate          ? ' checked' : '';
    return '<div class="nb-fp nb-fp-full nb-vis-flags" style="grid-column:1/-1;display:flex;flex-wrap:wrap;gap:10px 18px;padding:8px 10px;background:var(--bg-offset,#f5f7ff);border:1px solid var(--border,#e0e4ef);border-radius:7px;margin-top:4px;">'
      + '<label style="font-size:11px;font-weight:700;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.5px;flex-basis:100%;margin-bottom:2px;">Field Options</label>'
      + '<label class="nb-fp-check" style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">'
        + '<input type="checkbox" class="nu-field-required"' + (extra.required ? ' checked' : '') + '> Required'
      + '</label>'
      + '<label class="nb-fp-check" style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">'
        + '<input type="checkbox" class="nu-field-no-duplicate"' + isNoDup + '> No Duplicate'
      + '</label>'
      + '<label class="nb-fp-check" style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">'
        + '<input type="checkbox" class="nu-field-readonly"' + isReadonly + '> Readonly'
      + '</label>'
      + '<label class="nb-fp-check" style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">'
        + '<input type="checkbox" class="nu-field-hidden"' + isHidden + '> Hidden'
      + '</label>'
      + '<label class="nb-fp-check" style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">'
        + '<input type="checkbox" class="nu-field-hidden-normal"' + isHiddenNorm + '> Hidden for normal users'
      + '</label>'
    + '</div>';
  }

  /* ════════════════════════════════════════════════════════════════════
     GROUP container — standalone canvas block with inner rows
  ═══════════════════════════════════════════════════════════════════ */
  function _makeGroupContainer(extra) {
    extra = extra || {};
    var label = extra.label || 'Group';
    var id    = 'nb-group-' + Date.now() + '-' + Math.random().toString(36).slice(2,5);

    var wrap = document.createElement('div');
    wrap.className   = 'nb-container nb-container-group';
    wrap.id          = id;
    wrap.dataset.containerType = 'group';
    wrap.innerHTML =
      '<div class="nb-container-header">'
        + '<span class="nb-row-drag" title="Drag group">⠇</span>'
        + '<span class="nb-container-type-badge">GROUP</span>'
        + '<input type="text" class="nb-container-label-input nu-input" value="' + _esc(label) + '" placeholder="Group label" style="flex:1;font-size:12px;padding:2px 6px;">'
        + '<button type="button" class="nb-row-btn" onclick="window.nbFormBuilder._addRowToContainer(this.closest(\'.nb-container\'))" title="Add row inside group">+ Row</button>'
        + '<button type="button" class="nb-row-btn del" onclick="this.closest(\'.nb-container\').remove();window.nbFormBuilder._updateEmptyState();">✕</button>'
      + '</div>'
      + '<div class="nb-container-body nb-container-group-body">'
        + '<div class="nb-row-drop-hint" style="grid-column:1/-1;">Add a row, then drop fields in</div>'
      + '</div>';

    return wrap;
  }

  /* ════════════════════════════════════════════════════════════════════
     TAB container — standalone canvas block with named tab panels
  ═══════════════════════════════════════════════════════════════════ */
  function _makeTabContainer(extra) {
    extra = extra || {};
    var tabs = (extra.tabs && extra.tabs.length) ? extra.tabs : [{ name: 'Tab 1' }];
    var id   = 'nb-tab-' + Date.now() + '-' + Math.random().toString(36).slice(2,5);

    var wrap = document.createElement('div');
    wrap.className   = 'nb-container nb-container-tab';
    wrap.id          = id;
    wrap.dataset.containerType = 'tab';
    wrap.innerHTML =
      '<div class="nb-container-header">'
        + '<span class="nb-row-drag" title="Drag tab container">⠇</span>'
        + '<span class="nb-container-type-badge nb-container-type-badge-tab">TAB</span>'
        + '<span style="font-size:11px;color:var(--text-tertiary);flex:1;">Tab Container</span>'
        + '<button type="button" class="nb-row-btn del" onclick="this.closest(\'.nb-container\').remove();window.nbFormBuilder._updateEmptyState();">✕</button>'
      + '</div>'
      + '<div class="nb-cfield-tab-nav" id="' + id + '-nav"></div>'
      + '<div class="nb-container-tab-panels" id="' + id + '-panels"></div>';

    document.body.appendChild(wrap); // temp attach for DOM queries
    var nav    = wrap.querySelector('#' + id + '-nav');
    var panels = wrap.querySelector('#' + id + '-panels');

    tabs.forEach(function (tab, i) {
      _addTabPanel(wrap, nav, panels, tab.name || ('Tab ' + (i+1)), i === 0, tab.rows || []);
    });

    // Add Tab button
    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'nb-cfield-tab-add-btn';
    addBtn.textContent = '+ Tab';
    addBtn.addEventListener('click', function () {
      var idx = nav.querySelectorAll('.nb-cfield-tab-nav-item').length;
      _addTabPanel(wrap, nav, panels, 'Tab ' + (idx+1), false, []);
    });
    nav.appendChild(addBtn);

    document.body.removeChild(wrap);
    return wrap;
  }

  function _addTabPanel(container, nav, panels, tabName, isActive, rows) {
    var panelId = 'nb-tabpanel-' + Date.now() + '-' + Math.random().toString(36).slice(2,5);

    // Nav item
    var navItem = document.createElement('div');
    navItem.className = 'nb-cfield-tab-nav-item' + (isActive ? ' active' : '');
    navItem.dataset.panelTarget = panelId;
    navItem.innerHTML =
      '<input type="text" class="nb-tab-name-input" value="' + _esc(tabName) + '" style="background:none;border:none;outline:none;font:inherit;cursor:pointer;width:' + Math.max(50, tabName.length * 8) + 'px;min-width:40px;" onclick="event.stopPropagation()">'
      + ' <span class="nb-tab-nav-del" style="font-size:10px;cursor:pointer;color:var(--text-tertiary);margin-left:2px;" title="Remove tab">×</span>';
    navItem.addEventListener('click', function (e) {
      if (e.target.classList.contains('nb-tab-nav-del')) {
        // Remove this tab
        var panel = document.getElementById(panelId);
        if (panel) panel.remove();
        navItem.remove();
        // Activate first remaining tab
        var firstNav = nav.querySelector('.nb-cfield-tab-nav-item');
        if (firstNav) {
          firstNav.classList.add('active');
          var fp = document.getElementById(firstNav.dataset.panelTarget);
          if (fp) fp.classList.add('active');
        }
        return;
      }
      nav.querySelectorAll('.nb-cfield-tab-nav-item').forEach(function (n) { n.classList.remove('active'); });
      panels.querySelectorAll('.nb-cfield-tab-panel').forEach(function (p) { p.classList.remove('active'); });
      navItem.classList.add('active');
      var tp = document.getElementById(panelId);
      if (tp) tp.classList.add('active');
    });
    // Insert before Add Tab button
    var addBtn = nav.querySelector('.nb-cfield-tab-add-btn');
    if (addBtn) nav.insertBefore(navItem, addBtn);
    else nav.appendChild(navItem);

    // Panel
    var panel = document.createElement('div');
    panel.className = 'nb-cfield-tab-panel' + (isActive ? ' active' : '');
    panel.id = panelId;
    panel.innerHTML =
      '<div style="display:flex;align-items:center;justify-content:flex-end;padding:4px 8px 2px;border-bottom:1px solid var(--border-color);">'
        + '<button type="button" class="nb-row-btn" onclick="window.nbFormBuilder._addRowToContainer(this.closest(\'.nb-cfield-tab-panel\'))">+ Row</button>'
      + '</div>'
      + '<div class="nb-tab-panel-rows" style="padding:6px;min-height:52px;"></div>';
    panels.appendChild(panel);

    var rowsBody = panel.querySelector('.nb-tab-panel-rows');
    if (rows && rows.length) {
      rows.forEach(function (rowDef) {
        _addRowToContainer(rowsBody, rowDef.fields || []);
      });
    } else {
      rowsBody.innerHTML = '<div class="nb-row-drop-hint">Add a row, then drop fields in</div>';
    }

    return panel;
  }

  /* ── _addRowToContainer ─────────────────────────────────────
     Adds a row inside a Group body or Tab panel rows area.
     target = .nb-container-group-body  or  .nb-tab-panel-rows  or  .nb-cfield-tab-panel
  ─────────────────────────────────────────────────────────────── */
  function _addRowToContainer(target, fields) {
    // Resolve the actual rows wrapper
    var rowsWrap = target;
    if (target && target.classList.contains('nb-cfield-tab-panel')) {
      rowsWrap = target.querySelector('.nb-tab-panel-rows');
    }
    if (target && target.classList.contains('nb-container')) {
      rowsWrap = target.querySelector('.nb-container-group-body');
    }
    if (!rowsWrap) return null;

    // Remove the placeholder hint once a real row is added
    var hint = rowsWrap.querySelector('.nb-row-drop-hint');
    if (hint) hint.remove();

    var row = document.createElement('div');
    row.className = 'nb-row nb-inner-row';
    row.innerHTML =
      '<div class="nb-row-header">'
        + '<span class="nb-row-drag" title="Drag row">⠇</span>'
        + '<span class="nb-row-label">Row</span>'
        + '<span class="nb-row-actions">'
          + '<button class="nb-row-btn del" onclick="var r=this.closest(\'.nb-row\');var p=r.parentNode;r.remove();if(!p.querySelector(\'.nb-row\')){p.innerHTML=\'<div class=\\"nb-row-drop-hint\\" style=\\"grid-column:1/-1;\\">Add a row, then drop fields in</div>\'}; window.nbFormBuilder._updateEmptyState();">✕</button>'
        + '</span>'
      + '</div>'
      + '<div class="nb-row-body">'
        + '<div class="nb-row-drop-hint">Drop fields here</div>'
      + '</div>';
    rowsWrap.appendChild(row);

    var body = row.querySelector('.nb-row-body');
    if (body) _attachRowBodyDrop(body);

    // Populate with fields if restoring
    if (fields && fields.length) {
      fields.forEach(function (f) {
        var type = f.type || 'text';
        var card = window.nbFormBuilder._makeFieldCard(type, f.label || '', f.name || '', !!f.required, f);
        if (card) {
          var dropHint = body.querySelector('.nb-row-drop-hint');
          if (dropHint) dropHint.remove();
          card.id = 'nb-card-' + Date.now() + '-' + Math.random().toString(36).slice(2,5);
          card.setAttribute('draggable','true');
          card.addEventListener('dragstart', function (ev) { ev.dataTransfer.setData('text/nb-card-id', card.id); card.classList.add('drag-source'); });
          card.addEventListener('dragend',   function ()   { card.classList.remove('drag-source'); });
          body.appendChild(card);
          window.nbFormBuilder._applyColSpan(card, parseInt(f.col, 10) || 12);
          _restoreFieldState(card, f);
        }
      });
    }

    return row;
  }

  window.nbFormBuilder = {

    open: function () {
      var card = document.getElementById('formBuilderCard');
      var list = document.getElementById('formsListSection');
      if (card) card.style.display = 'block';
      if (list) list.style.display = 'none';
      var editId = document.getElementById('editFormId');
      if (editId) editId.value = '';
      var title = document.getElementById('builderTitle');
      if (title) title.textContent = 'New Form';
      this._clearForm();
    },

    close: function () {
      var card = document.getElementById('formBuilderCard');
      var list = document.getElementById('formsListSection');
      if (card) card.style.display = 'none';
      if (list) list.style.display = '';
    },

    _clearForm: function () {
      ['builderFormName','builderFormCode','builderFormTable',
       'formBrowseSql','formBrowseColumns','formBrowseDefaultSort',
       'formBrowseSearchPlaceholder','formBrowseSearchFields',
       'formCustomJs','formJsBeforeSave','formJsAfterSave',
       'formCustomPhp','formCustomCss'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
      });
      var ps = document.getElementById('formBrowsePageSize');
      if (ps) ps.value = '20';
      var srch = document.getElementById('formBrowseSearchEnabled');
      if (srch) srch.checked = false;
      var canvas = document.getElementById('formCanvas');
      if (canvas) canvas.querySelectorAll('.nb-row,.nb-container').forEach(function (r) { r.remove(); });
      this._updateEmptyState();
      this.selectFormType('main',  document.querySelector('input[name="formType"][value="main"]')   ? document.querySelector('input[name="formType"][value="main"]').closest('.nb-ftype-card')  : null);
      this.selectTableMode('new',  document.querySelector('input[name="formTableMode"][value="new"]')? document.querySelector('input[name="formTableMode"][value="new"]').closest('.nb-tmode-card') : null);
      this.selectPkType('autoincrement', document.querySelector('input[name="formPkType"][value="autoincrement"]') ? document.querySelector('input[name="formPkType"][value="autoincrement"]').closest('.nb-pk-card') : null);
      this.selectDisplayMode('inline');
    },

    switchTab: function (btn) {
      if (!btn) return;
      document.querySelectorAll('.nb-tab').forEach(function (t) { t.classList.remove('active'); });
      document.querySelectorAll('.nb-tab-panel').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      var panel = document.getElementById(btn.dataset.panel);
      if (panel) panel.classList.add('active');
    },

    selectFormType: function (type, card) {
      document.querySelectorAll('.nb-ftype-card').forEach(function (c) { c.classList.remove('selected'); });
      if (card) card.classList.add('selected');
      var radio = document.querySelector('input[name="formType"][value="' + type + '"]');
      if (radio) radio.checked = true;
      var browseTabEl = document.getElementById('browseTab');
      var browseNotice = document.getElementById('browseNotApplicable');
      var isBrowseable = (type === 'main' || type === 'popup');
      if (browseTabEl)  browseTabEl.style.opacity  = isBrowseable ? '1' : '0.4';
      if (browseNotice) browseNotice.style.display  = isBrowseable ? 'none' : 'block';
    },

    selectTableMode: function (mode, card) {
      document.querySelectorAll('.nb-tmode-card').forEach(function (c) { c.classList.remove('selected'); });
      if (card) card.classList.add('selected');
      var radio = document.querySelector('input[name="formTableMode"][value="' + mode + '"]');
      if (radio) radio.checked = true;
      var nw = document.getElementById('newTableWrap');
      var ex = document.getElementById('existingTableWrap');
      if (nw) nw.style.display = (mode === 'new')      ? '' : 'none';
      if (ex) ex.style.display = (mode === 'existing') ? '' : 'none';
    },

    selectPkType: function (type, card) {
      document.querySelectorAll('.nb-pk-card').forEach(function (c) { c.classList.remove('selected'); });
      if (card) card.classList.add('selected');
      var radio = document.querySelector('input[name="formPkType"][value="' + type + '"]');
      if (radio) radio.checked = true;
    },

    selectDisplayMode: function (mode) {
      var sel = document.getElementById('browseDisplayMode');
      if (sel) sel.value = mode || 'inline';
    },

    _updateEmptyState: function () {
      var canvas = document.getElementById('formCanvas');
      var empty  = document.getElementById('canvasEmpty');
      if (!canvas || !empty) return;
      var hasContent = canvas.querySelector('.nb-cfield') || canvas.querySelector('.nb-container');
      empty.style.display = hasContent ? 'none' : 'block';
    },

    // ── addRow (top-level canvas row) ─────────────────────
    addRow: function () {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return null;
      var empty = document.getElementById('canvasEmpty');
      if (empty) empty.style.display = 'none';

      var row = document.createElement('div');
      row.className = 'nb-row';
      row.innerHTML =
        '<div class="nb-row-header">'
          + '<span class="nb-row-drag" title="Drag row">⠇</span>'
          + '<span class="nb-row-label">Row</span>'
          + '<span class="nb-row-actions">'
            + '<button class="nb-row-btn del" onclick="this.closest(\'.nb-row\').remove();window.nbFormBuilder._updateEmptyState();">✕</button>'
          + '</span>'
        + '</div>'
        + '<div class="nb-row-body">'
          + '<div class="nb-row-drop-hint">Drop fields here</div>'
        + '</div>';
      canvas.appendChild(row);

      var body = row.querySelector('.nb-row-body');
      if (body) _attachRowBodyDrop(body);
      return row;
    },

    // ── _addRowToContainer (proxy) ───────────────────────
    _addRowToContainer: function (target) {
      return _addRowToContainer(target, []);
    },

    // ── addField ──────────────────────────────────────────
    addField: function (type, extraData) {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return null;
      var extra = extraData || {};

      // Group and Tab are canvas-level containers, not field cards
      if (type === 'group') {
        var grp = _makeGroupContainer(extra);
        canvas.appendChild(grp);
        var empty = document.getElementById('canvasEmpty');
        if (empty) empty.style.display = 'none';
        return grp;
      }
      if (type === 'tab') {
        var tab = _makeTabContainer(extra);
        canvas.appendChild(tab);
        var empty2 = document.getElementById('canvasEmpty');
        if (empty2) empty2.style.display = 'none';
        return tab;
      }

      var label = extra.label || extra.fieldlabel || (type.charAt(0).toUpperCase() + type.slice(1) + ' Field');
      var name  = extra.name  || extra.fieldname  || (type + '_' + (++_fieldCounter));
      var col   = parseInt(extra.col || extra.colspan, 10) || 12;

      var card = this._makeFieldCard(type, label, name, !!extra.required, extra);
      if (!card) return null;

      var rows = canvas.querySelectorAll('.nb-row-body');
      var targetBody = rows.length ? rows[rows.length - 1] : null;
      if (!targetBody) {
        var newRow = this.addRow();
        targetBody = newRow ? newRow.querySelector('.nb-row-body') : null;
      }
      if (!targetBody) { canvas.appendChild(card); return card; }

      var hint = targetBody.querySelector('.nb-row-drop-hint');
      if (hint) hint.remove();

      card.id = card.id || ('nb-card-' + Date.now() + '-' + Math.random().toString(36).slice(2,5));
      card.setAttribute('draggable','true');
      card.addEventListener('dragstart', function (ev) {
        ev.dataTransfer.setData('text/nb-card-id', card.id);
        card.classList.add('drag-source');
      });
      card.addEventListener('dragend', function () { card.classList.remove('drag-source'); });
      targetBody.appendChild(card);
      this._applyColSpan(card, col);
      return card;
    },

    // ── _makeFieldCard ─────────────────────────────────────
    _makeFieldCard: function (type, label, name, required, extra) {
      extra = extra || {};
      extra = Object.assign({}, extra, { required: required || !!extra.required });
      var col = parseInt(extra.col || extra.colspan, 10) || 12;

      var canvasType = type;

      if (type === 'multiselect') {
        canvasType = 'select2';
        if (!extra.multiple) extra = Object.assign({}, extra, { multiple: true });
      }

      if (type === 'select' && (extra.select2 === true || extra.select2 === 'true' || extra.select2 === 1)) {
        canvasType = 'select2';
      }

      // Group and Tab are not field cards — return null to signal caller
      if (type === 'group' || type === 'tab') return null;

      var spanBtns = [3,4,6,8,12].map(function (n) {
        return '<button type="button" class="nb-span-btn' + (n === col ? ' active' : '') + '" data-span="' + n + '">' + n + '</button>';
      }).join('');

      var extraBody = '';

      // ── SELECT ───────────────────────────────────────────────────────────
      if (canvasType === 'select') {
        var selIsMulti = extra.multiple === true || extra.multiple === 'true' || extra.multiple === 1
                      || extra.select_type === 'multiselect';

        var optSource    = extra.options_source || 'manual';
        var fromTable    = extra.options_table  || '';
        var fromValCol   = extra.options_value_col || '';
        var fromLabelCol = extra.options_label_col || '';
        var fromFilter   = extra.options_filter || '';
        var isFromTable  = (optSource === 'table');

        var opts = (extra.options || []).map(function (o) {
          return typeof o === 'object' ? (o.value + '|' + o.label) : o;
        }).join('\n');

        extraBody +=
          '<div class="nb-fp">'
            + '<label style="font-size:11px;font-weight:600;">Select Mode</label>'
            + '<select class="nu-input nu-field-select-mode" style="font-size:12px;">'
              + '<option value="single"' + (!selIsMulti ? ' selected' : '') + '>Single</option>'
              + '<option value="multi"'  + ( selIsMulti ? ' selected' : '') + '>Multi-Select</option>'
            + '</select>'
          + '</div>'
          + '<div class="nb-fp nb-fp-full" style="grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options Source</label>'
            + '<div style="display:flex;gap:8px;margin-top:4px;">'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="manual"' + (isFromTable ? '' : ' checked') + '>'
                + ' Manual list'
              + '</label>'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="table"' + (isFromTable ? ' checked' : '') + '>'
                + ' From table'
              + '</label>'
            + '</div>'
          + '</div>'
          + '<div class="nb-select-manual" style="' + (isFromTable ? 'display:none;' : '') + 'grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options <span style="font-weight:400;color:var(--text-muted);">(value|label per line)</span></label>'
            + '<textarea class="nu-input nu-field-options" rows="3" style="width:100%;box-sizing:border-box;">' + _esc(opts) + '</textarea>'
          + '</div>'
          + '<div class="nb-select-from-table" style="' + (isFromTable ? '' : 'display:none;') + 'grid-column:1/-1;">'
            + '<div style="background:var(--bg-offset,#f0f4ff);border:1.5px solid var(--color-primary,#4f6bed);border-radius:8px;padding:12px 14px;">'
              + '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--color-primary,#4f6bed);margin-bottom:10px;">📋 OPTIONS FROM TABLE</div>'
              + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Table Name</label>'
                  + '<input type="text" class="nu-input nu-field-opt-table" value="' + _esc(fromTable) + '" placeholder="e.g. categories" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Value Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-val-col" value="' + _esc(fromValCol) + '" placeholder="e.g. id" style="font-size:12px;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Label Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-label-col" value="' + _esc(fromLabelCol) + '" placeholder="e.g. name" style="font-size:12px;"></div>'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Filter SQL <span style="font-weight:400;">(optional WHERE)</span></label>'
                  + '<input type="text" class="nu-input nu-field-opt-filter" value="' + _esc(fromFilter) + '" placeholder="e.g. active=1" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
              + '</div>'
            + '</div>'
          + '</div>';
      }

      // ── SELECT2 ──────────────────────────────────────────────────────────
      if (canvasType === 'select2') {
        var s2IsMulti      = extra.multiple === true || extra.multiple === 'true' || extra.multiple === 1
                           || extra.select_type === 'multiselect';
        var allowClearChk  = (extra.allow_clear === false || extra.allow_clear === 'false') ? '' : 'checked';
        var s2OptSource    = extra.options_source || 'manual';
        var s2FromTable    = extra.options_table  || '';
        var s2FromValCol   = extra.options_value_col || '';
        var s2FromLabelCol = extra.options_label_col || '';
        var s2FromFilter   = extra.options_filter || '';
        var s2IsFromTable  = (s2OptSource === 'table');

        var s2Opts = (extra.options || []).map(function (o) {
          return typeof o === 'object' ? (o.value + '|' + o.label) : o;
        }).join('\n');

        extraBody +=
          '<div style="background:var(--bg-offset,#eef2ff);border:1.5px solid var(--color-primary,#4f6bed);border-radius:8px;padding:12px 14px;grid-column:1/-1;margin-bottom:4px;">'
            + '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--color-primary,#4f6bed);margin-bottom:10px;">🔍 SELECT2 CONFIG</div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
              + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Selection Mode</label>'
                + '<select class="nu-input nu-field-select-mode" style="font-size:12px;">'
                  + '<option value="single"' + (!s2IsMulti ? ' selected' : '') + '>Single</option>'
                  + '<option value="multi"'  + ( s2IsMulti ? ' selected' : '') + '>Multi-Select</option>'
                + '</select>'
              + '</div>'
              + '<div style="display:flex;align-items:flex-end;padding-bottom:4px;">'
                + '<label class="nb-fp-check" style="font-size:11px;">'
                  + '<input type="checkbox" class="nu-field-allow-clear"' + (allowClearChk ? ' checked' : '') + '>'
                  + ' Allow Clear (×)'
                + '</label>'
              + '</div>'
            + '</div>'
          + '</div>'
          + '<div class="nb-fp nb-fp-full" style="grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options Source</label>'
            + '<div style="display:flex;gap:8px;margin-top:4px;">'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="manual"' + (s2IsFromTable ? '' : ' checked') + '>'
                + ' Manual list'
              + '</label>'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="table"' + (s2IsFromTable ? ' checked' : '') + '>'
                + ' From table'
              + '</label>'
            + '</div>'
          + '</div>'
          + '<div class="nb-select-manual" style="' + (s2IsFromTable ? 'display:none;' : '') + 'grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options <span style="font-weight:400;color:var(--text-muted);">(value|label per line)</span></label>'
            + '<textarea class="nu-input nu-field-options" rows="3" style="width:100%;box-sizing:border-box;">' + _esc(s2Opts) + '</textarea>'
          + '</div>'
          + '<div class="nb-select-from-table" style="' + (s2IsFromTable ? '' : 'display:none;') + 'grid-column:1/-1;">'
            + '<div style="background:var(--bg-offset,#f0f4ff);border:1.5px solid var(--color-primary,#4f6bed);border-radius:8px;padding:12px 14px;">'
              + '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--color-primary,#4f6bed);margin-bottom:10px;">📋 OPTIONS FROM TABLE</div>'
              + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Table Name</label>'
                  + '<input type="text" class="nu-input nu-field-opt-table" value="' + _esc(s2FromTable) + '" placeholder="e.g. categories" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Value Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-val-col" value="' + _esc(s2FromValCol) + '" placeholder="e.g. id" style="font-size:12px;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Label Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-label-col" value="' + _esc(s2FromLabelCol) + '" placeholder="e.g. name" style="font-size:12px;"></div>'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Filter SQL <span style="font-weight:400;">(optional WHERE)</span></label>'
                  + '<input type="text" class="nu-input nu-field-opt-filter" value="' + _esc(s2FromFilter) + '" placeholder="e.g. active=1" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
              + '</div>'
            + '</div>'
          + '</div>';
      }

      if (canvasType === 'radio' || canvasType === 'checkbox_group') {
        var rcOptSource    = extra.options_source || 'manual';
        var rcFromTable    = extra.options_table  || '';
        var rcFromValCol   = extra.options_value_col || '';
        var rcFromLabelCol = extra.options_label_col || '';
        var rcFromFilter   = extra.options_filter || '';
        var rcIsFromTable  = (rcOptSource === 'table');
        var rcOpts = (extra.options || []).map(function (o) {
          return typeof o === 'object' ? (o.value + '|' + o.label) : o;
        }).join('\n');

        extraBody +=
          '<div class="nb-fp nb-fp-full" style="grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options Source</label>'
            + '<div style="display:flex;gap:8px;margin-top:4px;">'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="manual"' + (rcIsFromTable ? '' : ' checked') + '>'
                + ' Manual list'
              + '</label>'
              + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;">'
                + '<input type="radio" name="opt-src-' + _esc(name) + '" class="nu-field-opt-src" value="table"' + (rcIsFromTable ? ' checked' : '') + '>'
                + ' From table'
              + '</label>'
            + '</div>'
          + '</div>'
          + '<div class="nb-select-manual" style="' + (rcIsFromTable ? 'display:none;' : '') + 'grid-column:1/-1;">'
            + '<label style="font-size:11px;font-weight:600;">Options <span style="font-weight:400;color:var(--text-muted);">(value|label per line)</span></label>'
            + '<textarea class="nu-input nu-field-options" rows="3" style="width:100%;box-sizing:border-box;">' + _esc(rcOpts) + '</textarea>'
          + '</div>'
          + '<div class="nb-select-from-table" style="' + (rcIsFromTable ? '' : 'display:none;') + 'grid-column:1/-1;">'
            + '<div style="background:var(--bg-offset,#f0f4ff);border:1.5px solid var(--color-primary,#4f6bed);border-radius:8px;padding:12px 14px;">'
              + '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--color-primary,#4f6bed);margin-bottom:10px;">📋 OPTIONS FROM TABLE</div>'
              + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Table Name</label>'
                  + '<input type="text" class="nu-input nu-field-opt-table" value="' + _esc(rcFromTable) + '" placeholder="e.g. categories" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Value Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-val-col" value="' + _esc(rcFromValCol) + '" placeholder="e.g. id" style="font-size:12px;"></div>'
                + '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Label Column</label>'
                  + '<input type="text" class="nu-input nu-field-opt-label-col" value="' + _esc(rcFromLabelCol) + '" placeholder="e.g. name" style="font-size:12px;"></div>'
                + '<div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Filter SQL</label>'
                  + '<input type="text" class="nu-input nu-field-opt-filter" value="' + _esc(rcFromFilter) + '" placeholder="e.g. active=1" style="font-size:12px;width:100%;box-sizing:border-box;"></div>'
              + '</div>'
            + '</div>'
          + '</div>';
      }

      if (canvasType === 'calculated') {
        extraBody += '<div class="nb-fp nb-fp-full"><label>Formula / Expression</label>'
          + '<textarea class="nu-input nu-field-formula" rows="2" placeholder="e.g. {qty} * {price}">'
          + _esc(extra.formula || extra.calc_formula || '') + '</textarea></div>';
      }

      if (canvasType === 'lookup') {
        var lk       = (extra.lookup && typeof extra.lookup === 'object') ? extra.lookup : {};
        var lkTable  = lk.table          || lk.form_code       || extra.lookup_form    || '';
        var lkDisp   = lk.display_column || lk.displaycolumn   || extra.lookup_display || '';
        var lkStore  = lk.id_column      || lk.idcolumn        || extra.lookup_store   || '';
        var lkFilter = lk.filter         || extra.lookup_filter || '';
        var lkExtra  = lk.extra          || extra.lookup_extra  || '';

        extraBody +=
          '<div style="background:var(--bg-offset,#f0f4ff);border:1.5px solid var(--color-primary,#4f6bed);border-radius:8px;padding:12px 14px;margin-top:6px;grid-column:1/-1;">'
            + '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--color-primary,#4f6bed);margin-bottom:10px;">🔗 LOOKUP CONFIG <span style="font-weight:400;color:var(--text-muted,#888);">— links this field to another table</span></div>'
            + '<div style="margin-bottom:8px;">'
              + '<label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:3px;">Lookup Table <span style="font-weight:400;">— the DB table to search</span></label>'
              + '<input type="text" class="nu-input nu-lookup-table" value="' + _esc(lkTable) + '" placeholder="e.g. customers" style="font-size:12px;width:100%;box-sizing:border-box;">'
            + '</div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">'
              + '<div>'
                + '<label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:3px;">Display Column <span style="font-weight:400;">(shown to user)</span></label>'
                + '<input type="text" class="nu-input nu-lookup-display" value="' + _esc(lkDisp) + '" placeholder="e.g. full_name" style="font-size:12px;">'
              + '</div>'
              + '<div>'
                + '<label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:3px;">Store Column <span style="font-weight:400;">(saved to DB)</span></label>'
                + '<input type="text" class="nu-input nu-lookup-store" value="' + _esc(lkStore) + '" placeholder="e.g. id" style="font-size:12px;">'
              + '</div>'
            + '</div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
              + '<div>'
                + '<label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:3px;">Filter SQL <span style="font-weight:400;">(optional WHERE clause)</span></label>'
                + '<input type="text" class="nu-input nu-lookup-filter" value="' + _esc(lkFilter) + '" placeholder="e.g. active=1" style="font-size:12px;">'
              + '</div>'
              + '<div>'
                + '<label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:3px;">Extra Mapping <span style="font-weight:400;">(src:field, comma-sep)</span></label>'
                + '<input type="text" class="nu-input nu-lookup-extra" value="' + _esc(lkExtra) + '" placeholder="e.g. code:dept_code" style="font-size:12px;">'
              + '</div>'
            + '</div>'
          + '</div>';
      }

      if (canvasType === 'subform') {
        var sf     = (extra.subform && typeof extra.subform === 'object') ? extra.subform : {};
        var sfData = {
          form_code:       sf.form_code    || sf.formcode  || extra.sf_form_code    || '',
          fk_field:        sf.fk_field     || sf.fkfield   || extra.sf_fk_field     || '',
          subform_view:    extra.subform_view                                        || 'grid',
          help_text:       extra.help_text  || extra.field_help_text                || '',
          is_fk:           !!sf.is_fk,
          hide_in_grid:    !!sf.hide_in_grid,
          server_readonly: !!sf.server_readonly
        };
        extraBody += _subformPanelHTML(sfData);
      }

      var card = document.createElement('div');
      card.className = 'nb-cfield';
      card.dataset.type = canvasType;
      card.dataset.runtimeType = type;
      card.style.gridColumn = 'span ' + col;
      card.dataset.col = String(col);
      card.innerHTML =
        '<div class="nb-cfield-header">'
          + '<span class="nb-cfield-drag">⠇</span>'
          + '<span class="nb-cfield-type-badge">' + _esc(canvasType) + '</span>'
          + '<span class="nb-cfield-label">' + _esc(label) + '</span>'
          + '<span class="nb-cfield-span-badge">' + col + '/12</span>'
          + '<span class="nb-cfield-actions">'
            + '<button class="nb-cfield-btn del" type="button">✕</button>'
          + '</span>'
        + '</div>'
        + '<div class="nb-span-bar">'
          + '<span class="nb-span-bar-label">Width</span>'
          + spanBtns
          + '<span class="nb-span-preview">' + col + '/12 cols</span>'
        + '</div>'
        + '<div class="nb-cfield-body">'
          + '<div class="nb-fp-grid">'
            + '<div class="nb-fp"><label>Label</label><input type="text" class="nu-input nu-field-label" value="' + _esc(label) + '"></div>'
            + '<div class="nb-fp"><label>Field Name</label><input type="text" class="nu-input nu-field-name" value="' + _esc(name) + '"></div>'
            + (canvasType !== 'subform' ? '<div class="nb-fp"><label>Placeholder</label><input type="text" class="nu-input nu-field-placeholder" value="' + _esc(extra.placeholder || '') + '"></div>' : '')
            + (canvasType !== 'subform' ? '<div class="nb-fp"><label>Default Value</label><input type="text" class="nu-input nu-field-default" value="' + _esc(extra.default_value || extra.defaultvalue || '') + '"></div>' : '')
            + '<div class="nb-fp nb-fp-full"><label>Help Text</label><input type="text" class="nu-input nu-field-help" value="' + _esc(extra.help_text || extra.field_help_text || '') + '"></div>'
            + extraBody
            + _visibilityFlagsHTML(extra)
          + '</div>'
        + '</div>';

      var header = card.querySelector('.nb-cfield-header');
      var body   = card.querySelector('.nb-cfield-body');
      if (header && body) {
        header.addEventListener('click', function (e) {
          if (e.target.closest('.nb-cfield-actions')) return;
          body.classList.toggle('open');
        });
      }

      var delBtn = card.querySelector('.nb-cfield-btn.del');
      if (delBtn) {
        delBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          card.remove();
          window.nbFormBuilder._updateEmptyState();
        });
      }

      var self = this;
      card.querySelectorAll('.nb-span-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          self._applyColSpan(card, parseInt(btn.dataset.span, 10) || 12);
        });
      });

      if (canvasType === 'select' || canvasType === 'select2' || canvasType === 'radio' || canvasType === 'checkbox_group') {
        _attachSelectOptionsToggle(card);
      }

      if (canvasType === 'subform') {
        _attachSubformPanelEvents(card, sfData);
      }

      return card;
    },

    // ── _applyColSpan ───────────────────────────────────────
    _applyColSpan: function (card, col) {
      var c = parseInt(col, 10) || 12;
      if (c < 1 || c > 12) c = 12;
      card.style.gridColumn = 'span ' + c;
      card.dataset.col = String(c);
      var badge = card.querySelector('.nb-cfield-span-badge');
      if (badge) badge.textContent = c + '/12';
      var preview = card.querySelector('.nb-span-preview');
      if (preview) preview.textContent = c + '/12 cols';
      card.querySelectorAll('.nb-span-btn').forEach(function (btn) {
        btn.classList.toggle('active', parseInt(btn.dataset.span, 10) === c);
      });
    },

    // ── getLayout ──────────────────────────────────────────
    getLayout: function () {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return [];
      var layout = [];

      // Walk top-level children: .nb-row and .nb-container
      Array.prototype.forEach.call(canvas.children, function (el) {
        if (el.classList.contains('nb-row')) {
          // Plain row → collect its fields
          var rowFields = _collectRowFields(el);
          rowFields.forEach(function (f) { layout.push(f); });

        } else if (el.classList.contains('nb-container')) {
          var ctype = el.dataset.containerType;

          if (ctype === 'group') {
            var groupLabel = '';
            var labelInput = el.querySelector('.nb-container-label-input');
            if (labelInput) groupLabel = labelInput.value || '';
            var groupRows  = _collectContainerRows(el.querySelector('.nb-container-group-body'));
            layout.push({
              type:       'group',
              label:      groupLabel,
              name:       'group_' + Date.now(),
              rows:       groupRows,
              col:        12,
              row_index:  -1
            });

          } else if (ctype === 'tab') {
            var tabsData = [];
            var nav = el.querySelector('[id$="-nav"]');
            if (nav) {
              Array.prototype.forEach.call(nav.querySelectorAll('.nb-cfield-tab-nav-item'), function (navItem) {
                var panelId = navItem.dataset.panelTarget;
                var panel   = panelId ? document.getElementById(panelId) : null;
                var tabName = '';
                var nameInput = navItem.querySelector('.nb-tab-name-input');
                if (nameInput) tabName = nameInput.value || '';
                var rows = panel ? _collectContainerRows(panel.querySelector('.nb-tab-panel-rows')) : [];
                tabsData.push({ name: tabName, rows: rows });
              });
            }
            layout.push({
              type:      'tab',
              label:     'Tab Container',
              name:      'tab_' + Date.now(),
              tabs:      tabsData,
              col:       12,
              row_index: -1
            });
          }
        }
      });

      if (typeof window._nbSfAugmentLayout === 'function') {
        layout = window._nbSfAugmentLayout(layout);
      }
      return layout;
    },

    // ── saveForm ──────────────────────────────────────────
    saveForm: async function () {
      var nameEl  = document.getElementById('builderFormName');
      var codeEl  = document.getElementById('builderFormCode');
      var tableEl = document.getElementById('builderFormTable');
      var editEl  = document.getElementById('editFormId');

      var name   = nameEl  ? nameEl.value.trim()  : '';
      var code   = codeEl  ? codeEl.value.trim()  : '';
      var table  = tableEl ? tableEl.value.trim()  : '';
      var editId = editEl  ? editEl.value.trim()   : '';

      if (!name) { NuApp.toast('Form name is required', 'error'); return; }

      var _r = function (n) { var e = document.querySelector('input[name="' + n + '"]:checked'); return e ? e.value : ''; };
      var _v = function (id) { var e = document.getElementById(id); return e ? e.value : ''; };
      var _c = function (id) { var e = document.getElementById(id); return !!(e && e.checked); };

      var layout = this.getLayout();

      var payload = {
        form_name:       name,
        form_code:       code,
        form_table:      table,
        form_type:       _r('formType')        || 'main',
        form_table_mode: _r('formTableMode')   || 'new',
        form_pk_type:    _r('formPkType')      || 'autoincrement',
        form_layout:     JSON.stringify(layout),
        browse_display_mode:       (function () { var e = document.getElementById('browseDisplayMode'); return e ? e.value : 'inline'; }()),
        browse_sql:                _v('formBrowseSql'),
        browse_columns:            _v('formBrowseColumns'),
        browse_page_size:          _v('formBrowsePageSize'),
        browse_default_sort:       _v('formBrowseDefaultSort'),
        browse_search_enabled:     _c('formBrowseSearchEnabled') ? 1 : 0,
        browse_search_placeholder: _v('formBrowseSearchPlaceholder'),
        browse_search_fields:      _v('formBrowseSearchFields'),
        form_custom_js:            _v('formCustomJs'),
        form_js_before_save:       _v('formJsBeforeSave'),
        form_js_after_save:        _v('formJsAfterSave'),
        form_custom_php:           _v('formCustomPhp'),
        form_custom_css:           _v('formCustomCss')
      };

      if (editId) payload.form_id = editId;

      try {
        var res = await NuApp.apiJson('api/forms.php?action=save', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (res && res.success) {
          NuApp.toast(editId ? 'Form updated!' : 'Form created!', 'success');
          this.close();
          NuApp.loadModule('forms');
        } else {
          NuApp.toast((res && res.error) || 'Save failed', 'error');
        }
      } catch (err) {
        NuApp.toast('Save error: ' + err.message, 'error');
      }
    },

    _initAfterLoad: function () {
      _attachAllRowDrops();
    }
  };

  window.saveForm = function () { return window.nbFormBuilder.saveForm(); };


  /* ════════════════════════════════════════════════════════════════════
     Helpers — collect rows/fields from a container for serialization
  ═══════════════════════════════════════════════════════════════════ */

  function _collectRowFields(rowEl) {
    var rowIndex = -1;
    // Determine row_index from position in parent
    var parent = rowEl.parentNode;
    if (parent) {
      var siblings = Array.prototype.filter.call(parent.children, function (c) { return c.classList.contains('nb-row'); });
      rowIndex = siblings.indexOf(rowEl);
    }
    var fields = [];
    rowEl.querySelectorAll('.nb-cfield').forEach(function (card) {
      var f = _readFieldCard(card);
      f.row_index = rowIndex;
      fields.push(f);
    });
    return fields;
  }

  function _collectContainerRows(rowsWrap) {
    if (!rowsWrap) return [];
    var rows = [];
    Array.prototype.forEach.call(rowsWrap.querySelectorAll('.nb-row'), function (rowEl) {
      var fields = [];
      rowEl.querySelectorAll('.nb-cfield').forEach(function (card) {
        fields.push(_readFieldCard(card));
      });
      rows.push({ fields: fields });
    });
    return rows;
  }

  function _readFieldCard(card) {
    var canvasType = card.dataset.type || 'text';
    var labelEl    = card.querySelector('.nu-field-label');
    var nameEl     = card.querySelector('.nu-field-name');
    var reqEl      = card.querySelector('.nu-field-required');
    var noDupEl    = card.querySelector('.nu-field-no-duplicate');
    var readonlyEl = card.querySelector('.nu-field-readonly');
    var hiddenEl   = card.querySelector('.nu-field-hidden');
    var hidNormEl  = card.querySelector('.nu-field-hidden-normal');
    var phEl       = card.querySelector('.nu-field-placeholder');
    var defEl      = card.querySelector('.nu-field-default');
    var optsEl     = card.querySelector('.nu-field-options');
    var helpEl     = card.querySelector('.nu-field-help');
    var formulaEl  = card.querySelector('.nu-field-formula');

    var lkTableEl  = card.querySelector('.nu-lookup-table');
    var lkDispEl   = card.querySelector('.nu-lookup-display');
    var lkStoreEl  = card.querySelector('.nu-lookup-store');
    var lkFilterEl = card.querySelector('.nu-lookup-filter');
    var lkExtraEl  = card.querySelector('.nu-lookup-extra');

    var runtimeType = canvasType;
    var isMultiSel  = false;
    var selModeEl   = card.querySelector('.nu-field-select-mode');

    if (canvasType === 'select' || canvasType === 'select2') {
      isMultiSel = selModeEl && selModeEl.value === 'multi';
    }

    var field = {
      type:                    runtimeType,
      label:                   labelEl    ? labelEl.value    : '',
      name:                    nameEl     ? nameEl.value     : '',
      required:                reqEl      ? reqEl.checked    : false,
      no_duplicate:            noDupEl    ? noDupEl.checked  : false,
      readonly:                readonlyEl ? readonlyEl.checked : false,
      hidden:                  hiddenEl   ? hiddenEl.checked : false,
      hidden_for_normal_users: hidNormEl  ? hidNormEl.checked : false,
      placeholder:             phEl       ? phEl.value       : '',
      default_value:           defEl      ? defEl.value      : '',
      help_text:               helpEl     ? helpEl.value     : '',
      col:                     parseInt(card.dataset.col, 10) || 12
    };

    if (canvasType === 'select') {
      field.multiple    = isMultiSel;
      field.select2     = false;
      field.select_type = isMultiSel ? 'multiselect' : 'select';
    }

    if (canvasType === 'select2') {
      field.select2     = true;
      field.multiple    = isMultiSel;
      field.select_type = 'select2';
      var allowClearEl  = card.querySelector('.nu-field-allow-clear');
      field.allow_clear = allowClearEl ? allowClearEl.checked : true;
    }

    if (canvasType === 'select' || canvasType === 'select2' || canvasType === 'radio' || canvasType === 'checkbox_group') {
      var optSrcEl  = card.querySelector('.nu-field-opt-src:checked');
      var optSource = optSrcEl ? optSrcEl.value : 'manual';
      field.options_source = optSource;

      if (optSource === 'table') {
        var otEl = card.querySelector('.nu-field-opt-table');
        var ovEl = card.querySelector('.nu-field-opt-val-col');
        var olEl = card.querySelector('.nu-field-opt-label-col');
        var ofEl = card.querySelector('.nu-field-opt-filter');
        field.options_table     = otEl ? otEl.value.trim() : '';
        field.options_value_col = ovEl ? ovEl.value.trim() : '';
        field.options_label_col = olEl ? olEl.value.trim() : '';
        field.options_filter    = ofEl ? ofEl.value.trim() : '';
        field.options = [];
      } else if (optsEl) {
        field.options = optsEl.value.split('\n').map(function (l) {
          l = l.trim(); if (!l) return null;
          var parts = l.split('|');
          return parts.length >= 2
            ? { value: parts[0].trim(), label: parts[1].trim() }
            : { value: l, label: l };
        }).filter(Boolean);
      }
    }

    if (formulaEl) field.formula = formulaEl.value;

    if (canvasType === 'lookup' && lkTableEl) {
      field.lookup = {
        table:          lkTableEl  ? lkTableEl.value.trim()  : '',
        display_column: lkDispEl   ? (lkDispEl.value.trim()  || 'name') : 'name',
        id_column:      lkStoreEl  ? (lkStoreEl.value.trim() || 'id')   : 'id',
        filter:         lkFilterEl ? lkFilterEl.value.trim() : '',
        extra:          lkExtraEl  ? lkExtraEl.value.trim()  : ''
      };
    }

    if (canvasType === 'subform') field.subform = {};

    return field;
  }


  /* ════════════════════════════════════════════════════════════════════
     SECTION 3 — Row/span canvas patches
  ═══════════════════════════════════════════════════════════════════ */

  if (!window.nuToggleContainer) {
    window.nuToggleContainer = function (btn) {
      if (!btn) return;
      var body = document.getElementById(btn.getAttribute('data-target'));
      if (!body) return;
      var hidden = body.style.display === 'none' || body.style.display === '';
      body.style.display = hidden ? 'block' : 'none';
      btn.innerHTML = hidden ? '&#9660;' : '&#9654;';
    };
  }

  function _attachSelectOptionsToggle(card) {
    var radios = card.querySelectorAll('.nu-field-opt-src');
    var manualPanel    = card.querySelector('.nb-select-manual');
    var fromTablePanel = card.querySelector('.nb-select-from-table');
    if (!radios.length || !manualPanel || !fromTablePanel) return;
    radios.forEach(function (r) {
      r.addEventListener('change', function () {
        var isTable = r.value === 'table' && r.checked;
        manualPanel.style.display    = isTable ? 'none' : '';
        fromTablePanel.style.display = isTable ? ''     : 'none';
      });
    });
    var checked = card.querySelector('.nu-field-opt-src:checked');
    if (checked) {
      var isTable = checked.value === 'table';
      manualPanel.style.display    = isTable ? 'none' : '';
      fromTablePanel.style.display = isTable ? ''     : 'none';
    }
  }

  function _attachRowBodyDrop(rowBody) {
    if (rowBody._nuDropPatched) return;
    rowBody._nuDropPatched = true;

    rowBody.addEventListener('dragover', function (e) {
      e.preventDefault(); e.stopPropagation();
      rowBody.classList.add('drag-col-over');
    });
    rowBody.addEventListener('dragleave', function (e) {
      if (!rowBody.contains(e.relatedTarget)) rowBody.classList.remove('drag-col-over');
    });
    rowBody.addEventListener('drop', function (e) {
      e.preventDefault(); e.stopPropagation();
      rowBody.classList.remove('drag-col-over');

      var cardId = e.dataTransfer.getData('text/nb-card-id');
      if (cardId) {
        var existing = document.getElementById(cardId);
        if (existing) {
          var oldRow = existing.closest('.nb-row');
          var hint = rowBody.querySelector('.nb-row-drop-hint');
          if (hint) hint.remove();
          rowBody.appendChild(existing);
          window.nbFormBuilder._applyColSpan(existing, existing.dataset.col || 12);
          if (oldRow && !oldRow.querySelector('.nb-cfield')) oldRow.remove();
          window.nbFormBuilder._updateEmptyState();
          return;
        }
      }
      var dtype = e.dataTransfer.getData('text/nb-type') || e.dataTransfer.getData('text/plain');
      if (dtype && dtype !== 'group' && dtype !== 'tab') {
        var card = window.nbFormBuilder._makeFieldCard(dtype, '', dtype + '_' + (++_fieldCounter), false, { col: 6 });
        if (card) {
          var hint2 = rowBody.querySelector('.nb-row-drop-hint');
          if (hint2) hint2.remove();
          card.id = 'nb-card-' + Date.now() + '-' + Math.random().toString(36).slice(2,5);
          card.setAttribute('draggable','true');
          card.addEventListener('dragstart', function (ev) { ev.dataTransfer.setData('text/nb-card-id', card.id); card.classList.add('drag-source'); });
          card.addEventListener('dragend',   function ()   { card.classList.remove('drag-source'); });
          rowBody.appendChild(card);
          window.nbFormBuilder._applyColSpan(card, 6);
        }
        window.nbFormBuilder._updateEmptyState();
      }
    });
  }

  function _attachAllRowDrops() {
    var canvas = document.getElementById('formCanvas');
    if (!canvas) return;
    canvas.querySelectorAll('.nb-row-body').forEach(_attachRowBodyDrop);
  }

  // Canvas-level drop: Group and Tab land directly on canvas
  document.addEventListener('dragover', function (e) {
    var canvas = document.getElementById('formCanvas');
    if (canvas && e.target === canvas) { e.preventDefault(); canvas.classList.add('drag-over'); }
  });
  document.addEventListener('dragleave', function (e) {
    var canvas = document.getElementById('formCanvas');
    if (canvas && e.target === canvas) canvas.classList.remove('drag-over');
  });
  document.addEventListener('drop', function (e) {
    var canvas = document.getElementById('formCanvas');
    if (!canvas || e.target !== canvas) return;
    e.preventDefault();
    canvas.classList.remove('drag-over');
    var dtype = e.dataTransfer.getData('text/nb-type') || e.dataTransfer.getData('text/plain');
    if (dtype === 'group' || dtype === 'tab') {
      window.nbFormBuilder.addField(dtype, {});
    }
  });

  document.addEventListener('dragstart', function (e) {
    var tool = e.target.closest ? e.target.closest('.nb-tool[data-type]') : null;
    if (tool) e.dataTransfer.setData('text/nb-type', tool.dataset.type);
    var card = e.target.closest ? e.target.closest('.nb-cfield[id]') : null;
    if (card) {
      if (!card.id) card.id = 'nb-card-' + Date.now();
      e.dataTransfer.setData('text/nb-card-id', card.id);
    }
  }, true);


  /* ════════════════════════════════════════════════════════════════════
     SECTION 4 — Subform FK panel
  ═══════════════════════════════════════════════════════════════════ */

  function _toggleRow(cls, dataKey, checkedAttr, label, hint) {
    return '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;">'
      + '<input type="checkbox" class="' + cls + '" data-fk-flag="' + dataKey + '" ' + checkedAttr + '>'
      + '<span><strong>' + label + '</strong>'
      + (hint ? ' <span style="color:var(--text-muted,#999);font-size:11px;">— ' + hint + '</span>' : '')
      + '</span></label>';
  }

  function _subformPanelHTML(d) {
    var isFk     = d.is_fk           ? 'checked' : '';
    var hideGrid = d.hide_in_grid    ? 'checked' : '';
    var srvRo    = d.server_readonly ? 'checked' : '';
    var viewGrid = (!d.subform_view || d.subform_view === 'grid') ? 'selected' : '';
    var viewForm = (d.subform_view === 'form') ? 'selected' : '';
    return [
      '<div class="nb-sf-fk-panel" style="display:flex;flex-direction:column;gap:8px;padding:10px 0;grid-column:1/-1;">',
        '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Child Form</label>',
        '<select class="nu-input nb-sf-form-code" style="width:100%;"><option value="">— select form —</option></select></div>',
        '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">FK Field (links child → parent)</label>',
        '<div style="display:flex;gap:6px;">',
          '<select class="nu-input nb-sf-fk-field" style="flex:1;"><option value="">— select FK field —</option></select>',
          '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm nb-sf-create-fk">＋ Create FK Field</button>',
        '</div></div>',
        '<div><label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Display Mode</label>',
        '<select class="nu-input nb-sf-view" style="width:100%;">',
          '<option value="grid" ' + viewGrid + '>Grid (table)</option>',
          '<option value="form" ' + viewForm + '>Form (stacked)</option>',
        '</select></div>',
        '<div style="display:flex;flex-direction:column;gap:4px;padding:6px 8px;background:var(--bg-elevated,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0);">',
          '<label style="font-size:11px;font-weight:700;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">FK Field Flags</label>',
          _toggleRow('nb-sf-is-fk',           'is_fk',           isFk,     'FK field',        'Force hidden; builder locks this field'),
          _toggleRow('nb-sf-hide-in-grid',    'hide_in_grid',    hideGrid, 'Hide in grid',    'Excludes column from subform table'),
          _toggleRow('nb-sf-server-readonly', 'server_readonly', srvRo,    'Server readonly', 'PHP ignores POST value; always writes parent ID'),
        '</div>',
      '</div>'
    ].join('');
  }

  function _attachSubformPanelEvents(card, initialData) {
    var panel = card.querySelector('.nb-sf-fk-panel');
    if (!panel) return;

    var d = initialData || {};

    _populateFormDropdown(panel, d.form_code || '', function () {
      if (d.form_code) _populateFkDropdown(panel, d.form_code, d.fk_field || '');
    });

    var viewSel = panel.querySelector('.nb-sf-view');
    if (viewSel && d.subform_view) viewSel.value = d.subform_view;

    var formSel = panel.querySelector('.nb-sf-form-code');
    if (formSel) {
      formSel.addEventListener('change', function () {
        _populateFkDropdown(panel, formSel.value, '');
      });
    }

    var createBtn = panel.querySelector('.nb-sf-create-fk');
    if (createBtn) {
      createBtn.addEventListener('click', function () { _createFkField(panel); });
    }
  }

  function _populateFormDropdown(panel, selectedCode, cb) {
    var sel = panel.querySelector('.nb-sf-form-code');
    if (!sel) { if (cb) cb(); return; }
    fetch('api/forms.php?action=list', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var forms = (json && json.success && json.forms) ? json.forms : [];
        while (sel.options.length > 1) sel.remove(1);
        forms.forEach(function (f) {
          var opt = document.createElement('option');
          opt.value = f.form_code || f.code || '';
          opt.textContent = (f.form_name || f.name || opt.value) + ' (' + opt.value + ')';
          sel.appendChild(opt);
        });
        if (selectedCode) {
          sel.value = selectedCode;
          if (sel.value !== selectedCode) {
            var m = document.createElement('option');
            m.value = selectedCode; m.textContent = selectedCode + ' (saved)';
            sel.insertBefore(m, sel.options[1] || null);
            sel.value = selectedCode;
          }
        }
        if (cb) cb();
      })
      .catch(function () { if (cb) cb(); });
  }

  function _populateFkDropdown(panel, formCode, selectedFk) {
    var sel = panel.querySelector('.nb-sf-fk-field');
    if (!sel || !formCode) return;
    fetch('api/forms.php?action=get_by_code&code=' + encodeURIComponent(formCode), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.success || !json.form) return;
        var layout = [];
        try { layout = JSON.parse(json.form.form_layout || '[]'); } catch (e) { layout = []; }
        var fields = Array.isArray(layout) ? layout : [];
        while (sel.options.length > 1) sel.remove(1);
        fields.forEach(function (f) {
          var fname = f.name || f.fieldname || '';
          var ftype = f.type || f.fieldtype || 'text';
          if (!fname || ['html','heading','divider','fieldset','subform','button','group','tab'].indexOf(ftype) !== -1) return;
          var opt = document.createElement('option');
          opt.value = fname;
          opt.textContent = (f.label || f.fieldlabel || fname) + ' [' + fname + ']';
          sel.appendChild(opt);
        });
        if (selectedFk) {
          sel.value = selectedFk;
          if (sel.value !== selectedFk) {
            var m = document.crea