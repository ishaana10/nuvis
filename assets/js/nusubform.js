/**
 * nuSubform — FK-aware runtime subform engine
 * Supports three views: grid | form | inline
 */
(function (window) {
  'use strict';

  /* ── pending queue keyed by container element ─────────────────────── */
  var _pendingRows = new WeakMap();

  function getPending(container) {
    if (!_pendingRows.has(container)) _pendingRows.set(container, []);
    return _pendingRows.get(container);
  }

  /* ── tiny helpers ─────────────────────────────────────────────────── */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function apiJson(url, opts) {
    return fetch(url, opts || {}).then(function (r) { return r.json(); });
  }
  function toast(msg, type) {
    if (window.NuApp && window.NuApp.toast) { window.NuApp.toast(msg, type); return; }
    alert(msg);
  }

  /* ── determine if a field is FK / server-readonly ─────────────────── */
  function isFkField(f) {
    return !!(f.is_fk || f.isfk);
  }
  function isServerReadonly(f) {
    return !!(f.server_readonly || f.serverreadonly);
  }
  function shouldStripFromPost(f) {
    return isFkField(f) || isServerReadonly(f);
  }

  /* ── strip FK / server-readonly keys from a data object ──────────── */
  function stripProtectedFields(data, allFields) {
    if (!allFields || !allFields.length) return data;
    var out = Object.assign({}, data);
    allFields.forEach(function (f) {
      if (shouldStripFromPost(f)) {
        var fname = f.name || f.fieldname || '';
        if (fname) delete out[fname];
      }
    });
    return out;
  }

  /* ── get container meta ───────────────────────────────────────────── */
  function meta(container) {
    return {
      code:     container.dataset.subformCode || '',
      fk:       container.dataset.subformFk   || '',
      view:     container.dataset.subformView  || 'grid',
      parentId: container.dataset.parentId    || ''
    };
  }

  /* ── load & render rows ───────────────────────────────────────────── */
  function load(container) {
    var m    = meta(container);
    var body = container.querySelector('.nu-subform-body');
    if (!body) return;

    /* No parent_id yet (new unsaved parent) — show pending rows only */
    if (!m.parentId) {
      var fieldsUrl = 'api/form.php?action=subform_fields&code=' + encodeURIComponent(m.code);
      apiJson(fieldsUrl)
        .then(function (json) {
          if (!json.success) {
            body.innerHTML = '<div style="padding:12px;color:red;">' + esc(json.error) + '</div>';
            return;
          }
          var data      = json.data || {};
          var allFields  = data.all_fields  || data.layout || [];
          var gridFields = data.layout      || [];
          var pk         = data.pk || 'id';
          container._sfAllFields  = allFields;
          container._sfGridFields = gridFields;
          renderWithPending(container, gridFields, allFields, [], pk);
        })
        .catch(function (e) {
          body.innerHTML = '<div style="padding:12px;color:red;">' + esc(e.message) + '</div>';
        });
      return;
    }

    /* Has parentId — fetch real rows */
    body.innerHTML = '<div style="padding:20px;text-align:center;color:#666;font-size:13px;">Loading...</div>';

    var listUrl = 'api/form.php?action=subform_list&code=' + encodeURIComponent(m.code)
      + '&fk='        + encodeURIComponent(m.fk)
      + '&parent_id=' + encodeURIComponent(m.parentId);

    apiJson(listUrl)
    .then(function (json) {
      if (!json.success) {
        body.innerHTML = '<div style="padding:12px;color:red;">' + esc(json.error) + '</div>';
        return;
      }
      var data       = json.data || {};
      var gridFields = data.layout     || [];
      var allFields  = data.all_fields || gridFields;
      var records    = data.records    || [];
      var pk         = data.pk || 'id';

      container._sfAllFields  = allFields;
      container._sfGridFields = gridFields;

      renderWithPending(container, gridFields, allFields, records, pk);
    })
    .catch(function (e) {
      body.innerHTML = '<div style="padding:12px;color:red;">' + esc(e.message) + '</div>';
    });
  }

  /* ── merge saved records + pending rows before rendering ─────────── */
  function renderWithPending(container, gridFields, allFields, records, pk) {
    var pending = getPending(container);
    var pendingRecords = pending.map(function (item, idx) {
      var r = Object.assign({}, item.data);
      r[pk] = '__pending__' + idx;
      r._pending = true;
      return r;
    });
    var allRecords = records.concat(pendingRecords);
    render(container, gridFields, allFields, allRecords, pk);
  }

  function render(container, gridFields, allFields, records, pk) {
    var m    = meta(container);
    var body = container.querySelector('.nu-subform-body');
    if (!body) return;

    /* Grid columns: strip UI-only types AND FK fields */
    var displayCols = gridFields.filter(function (f) {
      var t = f.type || f.fieldtype || 'text';
      if (['html','heading','divider','fieldset','subform','button'].indexOf(t) !== -1) return false;
      if (f.hide_in_grid || f.hideingrid) return false;
      if (isFkField(f)) return false;
      return true;
    });

    if (m.view === 'grid')        body.innerHTML = renderGrid(displayCols, records, pk, m);
    else if (m.view === 'inline') body.innerHTML = renderInline(displayCols, records, pk, m);
    else                          body.innerHTML = renderFormList(displayCols, records, pk, m);

    /* ── bind delete buttons ── */
    body.querySelectorAll('[data-sf-delete]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.dataset.sfDelete;
        if (String(id).indexOf('__pending__') === 0) {
          var idx = parseInt(id.replace('__pending__', ''), 10);
          getPending(container).splice(idx, 1);
          load(container);
        } else {
          deleteRow(container, id, pk);
        }
      });
    });

    /* ── bind edit buttons ── */
    body.querySelectorAll('[data-sf-edit]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id         = btn.dataset.sfEdit;
        var useAllFlds = container._sfAllFields || allFields;
        if (String(id).indexOf('__pending__') === 0) {
          var idx  = parseInt(id.replace('__pending__', ''), 10);
          var item = getPending(container)[idx];
          if (item) openModal(container, item.allFields || useAllFlds, item.pk || pk, null, [], item.data, idx);
        } else {
          openModal(container, useAllFlds, pk, id, records);
        }
      });
    });

    /* ── bind inline save buttons ── */
    body.querySelectorAll('[data-sf-inline-save]').forEach(function (btn) {
      btn.addEventListener('click', function () { saveInlineRow(container, btn, pk, allFields); });
    });
  }

  /* ── grid view ────────────────────────────────────────────────────── */
  function renderGrid(displayCols, records, pk, m) {
    var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    html += '<thead><tr style="background:var(--bg-elevated,#f8f9fa);">';
    displayCols.forEach(function (f) {
      html += '<th style="padding:8px 10px;text-align:left;font-weight:600;border-bottom:1px solid #ddd;">'
        + esc(f.label || f.fieldlabel || f.name || f.fieldname || '') + '</th>';
    });
    html += '<th style="padding:8px 10px;width:100px;">Actions</th></tr></thead><tbody>';

    if (!records.length) {
      html += '<tr><td colspan="' + (displayCols.length + 1)
        + '" style="padding:20px;text-align:center;color:#999;">No rows yet</td></tr>';
    } else {
      records.forEach(function (row) {
        var id      = row[pk];
        var pending = !!row._pending;
        var rowStyle = pending
          ? 'border-bottom:1px solid #eee;opacity:0.7;background:#fffbe6;'
          : 'border-bottom:1px solid #eee;';
        html += '<tr style="' + rowStyle + '">';
        displayCols.forEach(function (f) {
          var fname = f.name || f.fieldname || '';
          var type  = f.type || f.fieldtype || 'text';
          var val   = row[fname + '_display'] !== undefined
            ? row[fname + '_display']
            : (row[fname] !== undefined ? row[fname] : '');
          html += '<td style="padding:8px 10px;">' + cellDisplay(type, val)
            + (pending ? ' <em style="color:#999;font-size:10px;">(pending)</em>' : '') + '</td>';
        });
        html += '<td style="padding:8px 10px;white-space:nowrap;">';
        html += '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" data-sf-edit="' + esc(id) + '" style="margin-right:4px;">Edit</button>';
        html += '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" data-sf-delete="' + esc(id) + '" style="color:#c00;">Del</button>';
        html += '</td></tr>';
      });
    }
    html += '</tbody></table>';
    return html;
  }

  /* ── form-card list view ──────────────────────────────────────────── */
  function renderFormList(displayCols, records, pk, m) {
    if (!records.length)
      return '<div style="padding:20px;text-align:center;color:#999;font-size:13px;">No rows yet</div>';
    var html = '<div style="display:grid;gap:8px;padding:8px;">';
    records.forEach(function (row) {
      var id = row[pk];
      html += '<div style="border:1px solid #ddd;border-radius:8px;padding:12px;">';
      displayCols.slice(0, 3).forEach(function (f) {
        var fname = f.name || f.fieldname || '';
        var val   = row[fname + '_display'] !== undefined
          ? row[fname + '_display']
          : (row[fname] || '');
        html += '<div style="font-size:12px;"><strong>' + esc(f.label || f.fieldlabel || fname) + ':</strong> ' + esc(val) + '</div>';
      });
      html += '<div style="display:flex;gap:8px;margin-top:8px;">';
      html += '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" data-sf-edit="' + esc(id) + '">Edit</button>';
      html += '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" data-sf-delete="' + esc(id) + '" style="color:#c00;">Delete</button>';
      html += '</div></div>';
    });
    html += '</div>';
    return html;
  }

  /* ── inline editable view ────────────────────────────────────────── */
  function renderInline(displayCols, records, pk, m) {
    var html = '<div style="padding:8px;">';
    if (!records.length) {
      html += '<div style="padding:12px;text-align:center;color:#999;font-size:13px;">No rows yet</div>';
    }
    records.forEach(function (row) {
      var id = row[pk];
      html += '<div class="nu-sf-inline-row" data-sf-row-id="' + esc(id)
        + '" style="border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:8px;display:grid;gap:8px;">';
      html += '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
      displayCols.forEach(function (f) {
        var fname = f.name || f.fieldname || '';
        var val   = row[fname] !== undefined ? row[fname] : '';
        var type  = f.type || f.fieldtype || 'text';
        html += '<div style="flex:1;min-width:120px;">';
        html += '<label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">'
          + esc(f.label || f.fieldlabel || fname) + '</label>';
        html += buildInlineInput(type, fname, val, f);
        html += '</div>';
      });
      html += '</div>';
      html += '<div style="display:flex;gap:6px;margin-top:4px;">';
      html += '<button type="button" class="nu-btn nu-btn-primary nu-btn-sm" data-sf-inline-save="' + esc(id) + '">Save</button>';
      html += '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" data-sf-delete="' + esc(id) + '" style="color:#c00;">Delete</button>';
      html += '</div></div>';
    });
    html += '</div>';
    return html;
  }

  function buildInlineInput(type, name, value, field) {
    var base = 'class="nu-input" name="' + esc(name) + '" style="width:100%;"';
    if (type === 'textarea')      return '<textarea ' + base + ' rows="2">' + esc(value) + '</textarea>';
    if (type === 'select') {
      var opts = '<option value="">—</option>';
      (field.options || []).forEach(function (o) {
        var sel = String(value) === String(o.value) ? ' selected' : '';
        opts += '<option value="' + esc(o.value) + '"' + sel + '>' + esc(o.label || o.value) + '</option>';
      });
      return '<select ' + base + '>' + opts + '</select>';
    }
    if (type === 'checkbox')  return '<input type="checkbox" name="' + esc(name) + '" value="1"' + (value ? ' checked' : '') + '>';
    if (type === 'date')      return '<input type="date" '            + base + ' value="' + esc(value) + '">';
    if (type === 'time')      return '<input type="time" '            + base + ' value="' + esc(value) + '">';
    if (type === 'datetime') {
      var v = value ? String(value).replace(' ', 'T').substring(0, 16) : '';
      return '<input type="datetime-local" ' + base + ' value="' + esc(v) + '">';
    }
    if (type === 'number')    return '<input type="number" '          + base + ' value="' + esc(value) + '">';
    return '<input type="text" ' + base + ' value="' + esc(value) + '">';
  }

  function cellDisplay(type, val) {
    if (type === 'checkbox') return val ? '&#10003;' : '&mdash;';
    return esc(val == null ? '' : val);
  }

  /* ── modal for add/edit ───────────────────────────────────────────── */
  function openModal(container, allFields, pk, rowId, records, prefillData, pendingIdx) {
    var row = prefillData || {};
    if (rowId && !prefillData) {
      (records || []).forEach(function (r) {
        if (String(r[pk]) === String(rowId)) row = r;
      });
    }

    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:20000;display:flex;align-items:center;justify-content:center;';
    overlay.setAttribute('data-sf-overlay', '1');

    var box = document.createElement('div');
    box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;max-width:640px;width:94%;max-height:90vh;overflow-y:auto;';
    box._sfAllFields = allFields;

    var isPending = pendingIdx !== undefined && pendingIdx !== null;
    var title     = (rowId || isPending) ? 'Edit Row' : 'Add Row';

    var headerEl = document.createElement('div');
    headerEl.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;';
    headerEl.innerHTML = '<h3 style="margin:0;">' + title + '</h3>'
      + '<button type="button" style="background:none;border:none;font-size:22px;cursor:pointer;line-height:1;" onclick="this.closest(\'[data-sf-overlay]\').remove()">&times;</button>';
    box.appendChild(headerEl);

    var fieldsEl = document.createElement('div');
    fieldsEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:12px;';

    (allFields || []).forEach(function (f) {
      var fname = f.name || f.fieldname || '';
      var ftype = f.type || f.fieldtype || 'text';
      var skip  = ['html','heading','divider','fieldset','subform','button'];
      if (skip.indexOf(ftype) !== -1 || !fname) return;

      var val = row[fname] !== undefined ? row[fname] : (f.default_value || f.defaultvalue || '');

      if (isFkField(f)) {
        var hiddenEl = document.createElement('input');
        hiddenEl.type  = 'hidden';
        hiddenEl.name  = fname;
        hiddenEl.value = val;
        fieldsEl.appendChild(hiddenEl);
        return;
      }

      var width   = f.width || '100%';
      var fieldWr = document.createElement('div');
      fieldWr.style.cssText = 'flex:1;min-width:calc(' + width + ' - 12px);';

      var labelEl = document.createElement('label');
      labelEl.style.cssText = 'font-size:12px;font-weight:600;display:block;margin-bottom:4px;';
      labelEl.innerHTML = esc(f.label || f.fieldlabel || fname)
        + (f.required ? ' <span style="color:red">*</span>' : '');
      fieldWr.appendChild(labelEl);

      fieldWr.insertAdjacentHTML('beforeend', buildInlineInput(ftype, fname, val, f));

      if (f.help_text || f.helptext) {
        var helpEl = document.createElement('div');
        helpEl.style.cssText = 'font-size:11px;color:#888;margin-top:3px;';
        helpEl.textContent = f.help_text || f.helptext;
        fieldWr.appendChild(helpEl);
      }
      fieldsEl.appendChild(fieldWr);
    });
    box.appendChild(fieldsEl);

    /* ── footer buttons ── */
    var footer = document.createElement('div');
    footer.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;margin-top:20px;';

    var cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'nu-btn nu-btn-ghost';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.onclick = function () { overlay.remove(); };

    var saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'nu-btn nu-btn-primary';
    saveBtn.textContent = 'Save';
    saveBtn.onclick = function () {
      var data = {};
      (allFields || []).forEach(function (f) {
        var fname = f.name || f.fieldname || '';
        var ftype = f.type || f.fieldtype || 'text';
        var skip  = ['html','heading','divider','fieldset','subform','button'];
        if (skip.indexOf(ftype) !== -1 || !fname) return;
        var el = box.querySelector('[name="' + CSS.escape(fname) + '"]');
        if (!el) return;
        data[fname] = (ftype === 'checkbox') ? (el.checked ? 1 : 0) : el.value;
      });

      var m = meta(container);

      if (!m.parentId) {
        var queue    = getPending(container);
        var safeData = stripProtectedFields(data, allFields);
        if (isPending) {
          queue[pendingIdx] = { allFields: allFields, pk: pk, data: safeData };
        } else {
          queue.push({ allFields: allFields, pk: pk, data: safeData });
        }
        overlay.remove();
        load(container);
        toast(isPending ? 'Row updated (will save when parent saves)' : 'Row queued — will save when parent is saved');
        return;
      }

      var postData = stripProtectedFields(data, allFields);
      var url = 'api/form.php?action=subform_save'
        + '&code='      + encodeURIComponent(m.code)
        + '&fk='        + encodeURIComponent(m.fk)
        + '&parent_id=' + encodeURIComponent(m.parentId)
        + (rowId ? '&id=' + encodeURIComponent(rowId) : '');

      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      apiJson(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(postData)
      })
      .then(function (json) {
        if (!json.success) {
          toast(json.error || 'Save failed', 'error');
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save';
          return;
        }
        overlay.remove();
        load(container);
        toast(rowId ? 'Row updated' : 'Row added');
      })
      .catch(function (e) {
        toast('Error: ' + e.message, 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
      });
    };

    footer.appendChild(cancelBtn);
    footer.appendChild(saveBtn);
    box.appendChild(footer);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
  }

  /* ── delete row ───────────────────────────────────────────────────── */
  function deleteRow(container, rowId, pk) {
    if (!confirm('Delete this row?')) return;
    var m = meta(container);
    apiJson(
      'api/form.php?action=subform_delete&code=' + encodeURIComponent(m.code)
      + '&id=' + encodeURIComponent(rowId),
      { method: 'DELETE' }
    )
    .then(function (json) {
      if (!json.success) { toast(json.error || 'Delete failed', 'error'); return; }
      load(container);
      toast('Row deleted');
    })
    .catch(function (e) { toast('Error: ' + e.message, 'error'); });
  }

  /* ── save inline row ─────────────────────────────────────────────── */
  function saveInlineRow(container, btn, pk, allFields) {
    var rowEl = btn.closest('.nu-sf-inline-row');
    var rowId = rowEl ? rowEl.dataset.sfRowId : '';
    var raw   = {};
    if (rowEl) {
      rowEl.querySelectorAll('[name]').forEach(function (el) {
        raw[el.name] = (el.type === 'checkbox') ? (el.checked ? 1 : 0) : el.value;
      });
    }
    var data = stripProtectedFields(raw, allFields || container._sfAllFields || []);
    btn.disabled = true;
    var m = meta(container);
    apiJson(
      'api/form.php?action=subform_save'
      + '&code='      + encodeURIComponent(m.code)
      + '&fk='        + encodeURIComponent(m.fk)
      + '&parent_id=' + encodeURIComponent(m.parentId)
      + (rowId ? '&id=' + encodeURIComponent(rowId) : ''),
      { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }
    )
    .then(function (json) {
      btn.disabled = false;
      if (!json.success) { toast(json.error || 'Save failed', 'error'); return; }
      load(container);
      toast('Saved');
    })
    .catch(function (e) { btn.disabled = false; toast('Error: ' + e.message, 'error'); });
  }

  /* ── flush pending queue to DB after parent saves ─────────────────── */
  function flushPending(container, parentId) {
    var queue = getPending(container);
    if (!queue.length) return Promise.resolve();
    var m = meta(container);

    return queue.reduce(function (chain, item) {
      return chain.then(function () {
        var allFlds  = item.allFields || container._sfAllFields || [];
        var postData = stripProtectedFields(item.data, allFlds);
        var url = 'api/form.php?action=subform_save'
          + '&code='      + encodeURIComponent(m.code)
          + '&fk='        + encodeURIComponent(m.fk)
          + '&parent_id=' + encodeURIComponent(parentId);
        return apiJson(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(postData)
        }).then(function (json) {
          if (!json.success) throw new Error(json.error || 'Subform row save failed');
        });
      });
    }, Promise.resolve())
    .then(function () { _pendingRows.set(container, []); });
  }

  /* ── public API ───────────────────────────────────────────────────── */
  var nuSubform = {

    initAll: function (scope) {
      scope = scope || document;
      var containers = scope.querySelectorAll('.nu-subform-container');
      containers.forEach(function (el) {
        var parentId = el.dataset.parentId || '';
        if (parentId) {
          delete el.dataset.sfInit;
          load(el);
        } else {
          if (!el.dataset.sfInit) {
            el.dataset.sfInit = '1';
            load(el);
          }
        }
      });
    },

    load: load,

    addRow: function (btn) {
      var container = btn.closest('.nu-subform-container');
      if (!container) return;
      var m = meta(container);
      if (!m.code) { toast('Subform not configured (missing form code)', 'error'); return; }

      if (container._sfAllFields && container._sfAllFields.length) {
        openModal(container, container._sfAllFields, container._sfPk || 'id', null, []);
        return;
      }

      apiJson('api/form.php?action=subform_fields&code=' + encodeURIComponent(m.code))
        .then(function (json) {
          if (!json.success) { toast(json.error || 'Failed to load subform', 'error'); return; }
          var data      = json.data || {};
          var allFields = data.all_fields || data.layout || [];
          var pk        = data.pk || 'id';
          container._sfAllFields = allFields;
          container._sfPk        = pk;
          openModal(container, allFields, pk, null, []);
        })
        .catch(function (e) { toast('Error: ' + e.message, 'error'); });
    },

    setView: function (container, view) {
      if (['grid','form','inline'].indexOf(view) === -1) return;
      container.dataset.subformView = view;
      load(container);
    },

    onParentSaved: function (newId, scope) {
      scope = scope || document;
      var id = String(newId || '');
      if (!id) return;

      var containers = Array.prototype.slice.call(
        scope.querySelectorAll('.nu-subform-container')
      );

      containers.forEach(function (el, i) {
        el.dataset.parentId = id;
        delete el.dataset.sfInit;

        flushPending(el, id)
          .then(function () { load(el); })
          .catch(function (e) {
            toast('Error saving queued subform rows: ' + e.message, 'error');
            load(el);
          });
      });
    }
  };

  window.nuSubform = nuSubform;

  /* ── auto-init after parent form opens ───────────────────────────── */
  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope ? e.detail.scope : document;
    nuSubform.initAll(scope);
  });

  /* ── listen for parent save event ────────────────────────────────── */
  document.addEventListener('nu:parent:saved', function (e) {
    var detail = e.detail || {};
    nuSubform.onParentSaved(detail.id, detail.scope || document);
  });

  /* ── (merged from nusubform-patch.js)
     Intercept NuApp.apiJson to dispatch nu:parent:saved automatically
     after a successful parent-form save (api/form.php?action=save).
     URL regex avoids matching action=subform_save.
  ── */
  var PARENT_SAVE_RE = /[?&]action=save(&|$)/;

  function installParentSavePatch() {
    var app = window.NuApp;
    if (!app || typeof app.apiJson !== 'function') return;
    if (app._nuSubformPatchApplied) return;
    app._nuSubformPatchApplied = true;

    var _origApiJson = app.apiJson.bind(app);
    app.apiJson = function (url, options) {
      return _origApiJson(url, options).then(function (json) {
        if (
          typeof url === 'string' &&
          PARENT_SAVE_RE.test(url) &&
          json && json.success
        ) {
          var savedId = String(
            (json.data && (json.data.id || json.data.record_id))
              || json.id
              || json.record_id
              || ''
          );
          if (savedId) {
            var box = null;
            document.querySelectorAll('.nu-form-overlay').forEach(function (ov) {
              if (ov.querySelector('.nu-subform-container')) box = ov;
            });
            var scope = box || document;
            scope.querySelectorAll('.nu-subform-container').forEach(function (el) {
              el.dataset.parentId = savedId;
            });
            document.dispatchEvent(new CustomEvent('nu:parent:saved', {
              detail: { id: savedId, scope: scope }
            }));
          }
        }
        return json;
      });
    };
  }

  if (window.NuApp && window.NuApp.apiJson) {
    installParentSavePatch();
  } else {
    document.addEventListener('DOMContentLoaded', installParentSavePatch);
  }

}(window));
