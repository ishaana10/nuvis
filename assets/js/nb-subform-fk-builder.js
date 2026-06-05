/**
 * nb-subform-fk-builder.js
 * FK-aware subform panel patches for nbFormBuilder.
 *
 * GAP 1 — _fieldPanel() subform section
 *   Replaces the single bare text-input with a proper panel:
 *     • Child form dropdown  (fetched from api/forms.php?action=list)
 *     • FK field dropdown    (fetched from that child form's layout)
 *     • "＋ Create FK Field" button
 *     • 3 flag toggles: is_fk, hide_in_grid, server_readonly
 *
 * GAP 2 — layout serialiser (_readFieldCard)
 *   Persists new flags when collecting layout JSON.
 *
 * GAP 3 — createFkField()
 *   Auto-adds a hidden FK field to the child form's layout.
 */
(function () {
  'use strict';

  /* ── wait for nbFormBuilder ─────────────────────────────────────── */
  function waitForBuilder(cb) {
    if (window.nbFormBuilder && typeof window.nbFormBuilder.addField === 'function') {
      cb();
    } else {
      setTimeout(function () { waitForBuilder(cb); }, 60);
    }
  }

  waitForBuilder(function () {
    var fb = window.nbFormBuilder;

    /* ══════════════════════════════════════════════════════════════════
       GAP 1 — patch _fieldPanel() subform section
    ═══════════════════════════════════════════════════════════════════ */

    function upgradeSubformPanel(card) {
      if (card._sfPanelUpgraded) return;
      card._sfPanelUpgraded = true;

      var oldInput = card.querySelector('.nu-subform-config, input[placeholder*="order_id"], input[data-sf-config]');
      var panelTarget = oldInput
        ? oldInput.parentElement
        : card.querySelector('.nb-field-config, .nb-cfield-config, .nb-sf-config');
      if (!panelTarget) {
        panelTarget = card.querySelector('.nb-cfield-body') || card;
      }
      if (oldInput) oldInput.remove();

      var existingData = _readSubformData(card);
      panelTarget.insertAdjacentHTML('beforeend', _subformPanelHTML(existingData));

      var panel = panelTarget.querySelector('.nb-sf-fk-panel');
      if (!panel) return;

      _populateFormDropdown(panel, existingData.form_code, function () {
        if (existingData.form_code) {
          _populateFkDropdown(panel, existingData.form_code, existingData.fk_field);
        }
      });

      var formSel = panel.querySelector('.nb-sf-form-code');
      if (formSel) {
        formSel.addEventListener('change', function () {
          _populateFkDropdown(panel, formSel.value, '');
        });
      }

      var createBtn = panel.querySelector('.nb-sf-create-fk');
      if (createBtn) {
        createBtn.addEventListener('click', function () {
          _createFkField(panel);
        });
      }
    }

    function _subformPanelHTML(d) {
      var isFk     = d.is_fk           ? 'checked' : '';
      var hideGrid = d.hide_in_grid    ? 'checked' : '';
      var srvRo    = d.server_readonly ? 'checked' : '';
      return [
        '<div class="nb-sf-fk-panel" style="display:flex;flex-direction:column;gap:8px;padding:10px 0;">',
          '<div>',
            '<label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--text-muted,#666);">Child Form</label>',
            '<select class="nu-input nb-sf-form-code" style="width:100%;">',
              '<option value="">— select form —</option>',
            '</select>',
          '</div>',
          '<div>',
            '<label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--text-muted,#666);">FK Field (links child → parent)</label>',
            '<div style="display:flex;gap:6px;">',
              '<select class="nu-input nb-sf-fk-field" style="flex:1;">',
                '<option value="">— select FK field —</option>',
              '</select>',
              '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm nb-sf-create-fk" title="Auto-create hidden FK field in child form">＋ Create FK Field</button>',
            '</div>',
          '</div>',
          '<div style="display:flex;flex-direction:column;gap:4px;padding:6px 8px;background:var(--bg-elevated,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0);">',
            '<label style="font-size:11px;font-weight:700;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">FK Field Flags</label>',
            _toggleRow('is_fk',           'nb-sf-is-fk',           'is_fk',           isFk,     'FK field',        'Force hidden; builder locks this field'),
            _toggleRow('hide_in_grid',    'nb-sf-hide-in-grid',    'hide_in_grid',    hideGrid,  'Hide in grid',    'Excludes column from subform table'),
            _toggleRow('server_readonly', 'nb-sf-server-readonly', 'server_readonly', srvRo,     'Server readonly', 'PHP ignores POST value; always writes parent ID'),
          '</div>',
        '</div>'
      ].join('');
    }

    function _toggleRow(key, cls, dataKey, checkedAttr, label, hint) {
      return [
        '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;">',
          '<input type="checkbox" class="' + cls + '" data-fk-flag="' + dataKey + '" ' + checkedAttr + '>',
          '<span><strong>' + label + '</strong>',
            hint ? ' <span style="color:var(--text-muted,#999);font-size:11px;">— ' + hint + '</span>' : '',
          '</span>',
        '</label>'
      ].join('');
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
            opt.textContent = (f.form_name || f.name || f.form_code || '') + ' (' + opt.value + ')';
            if (opt.value === selectedCode) opt.selected = true;
            sel.appendChild(opt);
          });
          if (cb) cb();
        })
        .catch(function () { if (cb) cb(); });
    }

    function _populateFkDropdown(panel, formCode, selectedFk) {
      var sel = panel.querySelector('.nb-sf-fk-field');
      if (!sel || !formCode) return;
      fetch('api/form.php?action=subform_fields&code=' + encodeURIComponent(formCode), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          var fields = (json && json.success && json.data)
            ? (json.data.all_fields || json.data.layout || [])
            : [];
          while (sel.options.length > 1) sel.remove(1);
          fields.forEach(function (f) {
            var fname = f.name || f.fieldname || '';
            var ftype = f.type || f.fieldtype || 'text';
            if (!fname) return;
            if (['html','heading','divider','fieldset','subform','button'].indexOf(ftype) !== -1) return;
            var opt = document.createElement('option');
            opt.value = fname;
            opt.textContent = (f.label || f.fieldlabel || fname) + ' [' + fname + ']';
            if (fname === selectedFk) opt.selected = true;
            sel.appendChild(opt);
          });
        })
        .catch(function () {});
    }

    /* Read existing subform config from card data attributes
       Supports both data-sf-* attributes AND nested subform{} JSON
       stored in data-field-json (set by _rebuildCanvas). */
    function _readSubformData(card) {
      /* Try data-sf-* first (written back by our serialiser on prior saves) */
      var fromAttrs = {
        form_code:       card.dataset.sfFormCode       || '',
        fk_field:        card.dataset.sfFkField        || '',
        is_fk:           card.dataset.sfIsFk           === '1',
        hide_in_grid:    card.dataset.sfHideInGrid     === '1',
        server_readonly: card.dataset.sfServerReadonly === '1',
      };
      if (fromAttrs.form_code) return fromAttrs;

      /* Fallback: try data-field-json which _rebuildCanvas may set */
      var raw = card.dataset.fieldJson || card.dataset.fieldData || '';
      if (raw) {
        try {
          var obj = JSON.parse(raw);
          var sf  = obj.subform || {};
          return {
            form_code:       sf.form_code  || sf.formcode  || '',
            fk_field:        sf.fk_field   || sf.fkfield   || '',
            is_fk:           !!obj.is_fk,
            hide_in_grid:    !!obj.hide_in_grid,
            server_readonly: !!obj.server_readonly,
          };
        } catch (e) {}
      }

      /* Last resort: scan data-* attributes for any subform-related keys
         e.g. data-subform-form-code, data-form-code, data-fk-field */
      return {
        form_code:       card.dataset.subformFormCode || card.dataset.formCode || '',
        fk_field:        card.dataset.subformFkField  || card.dataset.fkField  || '',
        is_fk:           false,
        hide_in_grid:    false,
        server_readonly: false,
      };
    }

    /* ══════════════════════════════════════════════════════════════════
       GAP 3 — createFkField()
    ═══════════════════════════════════════════════════════════════════ */
    function _createFkField(panel) {
      var formCodeSel = panel.querySelector('.nb-sf-form-code');
      var fkFieldSel  = panel.querySelector('.nb-sf-fk-field');
      var formCode = formCodeSel ? formCodeSel.value : '';
      var fkName   = fkFieldSel  ? fkFieldSel.value  : '';

      if (!formCode) { _sfToast('Select a child form first', 'error'); return; }
      if (!fkName) {
        fkName = window.prompt('Enter FK field name (e.g. order_id):');
        if (!fkName || !fkName.trim()) return;
        fkName = fkName.trim().replace(/[^a-zA-Z0-9_]/g, '_');
      }

      fetch('api/forms.php?action=get_by_code&code=' + encodeURIComponent(formCode), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.success || !json.form) throw new Error(json.error || 'Child form not found');
          var form   = json.form;
          var layout = [];
          try { layout = JSON.parse(form.form_layout || '[]'); } catch (e) { layout = []; }
          if (!Array.isArray(layout)) layout = [];

          var exists = layout.some(function (f) {
            return (f.name || f.fieldname || '') === fkName;
          });
          if (exists) {
            _sfToast('Field "' + fkName + '" already exists in ' + formCode, 'error');
            return null;
          }

          layout.push({
            name: fkName, label: fkName, type: 'hidden',
            is_fk: true, hide_in_grid: true, server_readonly: true
          });

          return fetch(
            'api/forms.php?action=patch_layout&id=' + encodeURIComponent(form.form_id || form.id || ''),
            {
              method: 'POST', credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ form_layout: JSON.stringify(layout) })
            }
          ).then(function (r) { return r.json(); });
        })
        .then(function (saveJson) {
          if (!saveJson) return;
          if (!saveJson.success) { _sfToast(saveJson.error || 'Save failed', 'error'); return; }
          _sfToast('FK field "' + fkName + '" created in child form ' + formCode);
          _populateFkDropdown(panel, formCode, fkName);
        })
        .catch(function (e) { _sfToast('Error: ' + e.message, 'error'); });
    }

    function _sfToast(msg, type) {
      if (window.NuApp && NuApp.toast) { NuApp.toast(msg, type); return; }
      alert(msg);
    }

    /* ══════════════════════════════════════════════════════════════════
       GAP 2 — patch layout serialiser
    ═══════════════════════════════════════════════════════════════════ */
    function _augmentSubformData(fieldObj, card) {
      if ((fieldObj.type || fieldObj.fieldtype || '') !== 'subform') return fieldObj;
      var panel       = card.querySelector('.nb-sf-fk-panel');
      if (!panel) return fieldObj;
      var formCodeSel = panel.querySelector('.nb-sf-form-code');
      var fkFieldSel  = panel.querySelector('.nb-sf-fk-field');
      var isFkChk     = panel.querySelector('.nb-sf-is-fk');
      var hideChk     = panel.querySelector('.nb-sf-hide-in-grid');
      var srvRoChk    = panel.querySelector('.nb-sf-server-readonly');
      var formCode    = formCodeSel ? (formCodeSel.value || '') : '';
      var fkField     = fkFieldSel  ? (fkFieldSel.value  || '') : '';
      if (!fieldObj.subform) fieldObj.subform = {};
      if (formCode) fieldObj.subform.form_code = formCode;
      if (fkField)  fieldObj.subform.fk_field  = fkField;
      if (isFkChk)  { if (isFkChk.checked)  fieldObj.is_fk           = true; else delete fieldObj.is_fk;           }
      if (hideChk)  { if (hideChk.checked)   fieldObj.hide_in_grid    = true; else delete fieldObj.hide_in_grid;    }
      if (srvRoChk) { if (srvRoChk.checked)  fieldObj.server_readonly = true; else delete fieldObj.server_readonly; }
      return fieldObj;
    }

    var _serializerNames = ['getLayout','collectLayout','_serializeCanvas','serializeLayout','_getLayout'];
    _serializerNames.forEach(function (methodName) {
      if (typeof fb[methodName] !== 'function') return;
      var _orig = fb[methodName].bind(fb);
      fb[methodName] = function () {
        var layout = _orig.apply(fb, arguments);
        if (!Array.isArray(layout)) return layout;
        var canvas = document.getElementById('formCanvas');
        if (!canvas) return layout;
        var cards = canvas.querySelectorAll('.nb-cfield[data-type="subform"]');
        layout.forEach(function (fieldObj) {
          if ((fieldObj.type || '') !== 'subform') return;
          var fname = fieldObj.name || fieldObj.fieldname || '';
          var matchCard = null;
          cards.forEach(function (c) {
            if ((c.dataset.fieldName || c.dataset.name || '') === fname) matchCard = c;
          });
          if (!matchCard && cards.length === 1) matchCard = cards[0];
          if (matchCard) _augmentSubformData(fieldObj, matchCard);
        });
        return layout;
      };
    });

    /* ══════════════════════════════════════════════════════════════════
       Auto-upgrade: find every subform card and inject the panel
    ═══════════════════════════════════════════════════════════════════ */
    function upgradeAllSubformCards() {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return;
      canvas.querySelectorAll(
        '.nb-cfield[data-type="subform"], .nb-cfield[data-fieldtype="subform"]'
      ).forEach(upgradeSubformPanel);
    }

    /* ── MutationObserver: upgrade cards as they are added ─────────── */
    var _obs = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          var type = node.dataset && (node.dataset.type || node.dataset.fieldtype || '');
          if (type === 'subform') upgradeSubformPanel(node);
          if (node.querySelectorAll) {
            node.querySelectorAll(
              '.nb-cfield[data-type="subform"], .nb-cfield[data-fieldtype="subform"]'
            ).forEach(upgradeSubformPanel);
          }
        });
      });
    });

    function attachObserver() {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return;
      /* disconnect any existing observation first to avoid duplicates */
      try { _obs.disconnect(); } catch(e) {}
      _obs.observe(canvas, { childList: true, subtree: true });
    }

    /* ── Initial attach ─────────────────────────────────────────────── */
    attachObserver();
    upgradeAllSubformCards();

    /* ── Re-attach + upgrade every time the forms module opens ─────── */
    document.addEventListener('nu:form:opened', function () {
      /* Small delay gives _rebuildCanvas time to finish painting the DOM */
      setTimeout(function () {
        attachObserver();
        upgradeAllSubformCards();
      }, 150);
    });

    /* ── Also hook _rebuildCanvas directly so edit-load is covered ─── */
    if (typeof fb._rebuildCanvas === 'function') {
      var _origRebuild = fb._rebuildCanvas.bind(fb);
      fb._rebuildCanvas = function () {
        var result = _origRebuild.apply(fb, arguments);
        /* _rebuildCanvas may be async or sync; cover both */
        setTimeout(function () {
          attachObserver();
          upgradeAllSubformCards();
        }, 120);
        if (result && typeof result.then === 'function') {
          result.then(function () {
            setTimeout(function () {
              attachObserver();
              upgradeAllSubformCards();
            }, 120);
          });
        }
        return result;
      };
    }

    /* ── Also hook _initAfterLoad (called by NuApp.initModuleScripts) ─ */
    if (typeof fb._initAfterLoad === 'function') {
      var _origInit = fb._initAfterLoad.bind(fb);
      fb._initAfterLoad = function () {
        var result = _origInit.apply(fb, arguments);
        setTimeout(function () {
          attachObserver();
          upgradeAllSubformCards();
        }, 200);
        return result;
      };
    }

    window.nbCreateFkField = _createFkField;
    console.log('[nb-subform-fk-builder] FK-aware subform panel patches applied.');
  });

})();
