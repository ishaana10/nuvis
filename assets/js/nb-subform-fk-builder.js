/**
 * nb-subform-fk-builder.js
 * FK-aware subform panel patches for nbFormBuilder.
 *
 * What this file does (3 targeted builder gaps from the FK proposal):
 *
 *  GAP 1 — _fieldPanel() subform section
 *    Replaces the single bare text-input (<input class="nu-subform-config">)
 *    with a proper panel:
 *      • Child form dropdown  (fetched from api/forms.php?action=list)
 *      • FK field dropdown    (fetched from that child form's layout)
 *      • "＋ Create FK Field" button — calls createFkField()
 *      • 3 flag toggles: is_fk, hide_in_grid, server_readonly
 *
 *  GAP 2 — _readFieldCard()
 *    Persists the new flags (is_fk, hide_in_grid, server_readonly,
 *    subform.form_code, subform.fk_field) when collecting layout JSON.
 *
 *  GAP 3 — createFkField()
 *    Auto-adds a hidden FK field to the child form's layout in one click:
 *      { name: "<fk>", type: "hidden", is_fk: true,
 *        hide_in_grid: true, server_readonly: true }
 *
 * Loaded AFTER nubuilder-next.js (which defines nbFormBuilder).
 * Uses the same monkey-patch pattern as nb-form-builder-layout.js.
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
       We hook into the existing field-card DOM after it is built.
       nbFormBuilder builds a card and appends it; we find any card
       whose type === 'subform' and replace its config panel HTML.
    ═══════════════════════════════════════════════════════════════════ */

    /* Replace the raw text input with the full FK-aware panel */
    function upgradeSubformPanel(card) {
      if (card._sfPanelUpgraded) return;
      card._sfPanelUpgraded = true;

      /* Find the container that currently holds the bare text input */
      var oldInput = card.querySelector('.nu-subform-config, input[placeholder*="order_id"], input[data-sf-config]');
      var panelTarget = oldInput ? oldInput.parentElement : card.querySelector('.nb-field-config, .nb-cfield-config, .nb-sf-config');
      if (!panelTarget) {
        /* Fallback: append to card body */
        panelTarget = card.querySelector('.nb-cfield-body') || card;
      }
      if (oldInput) oldInput.remove();

      /* Read existing values (for edit-restore) */
      var existingData = _readSubformData(card);

      panelTarget.insertAdjacentHTML('beforeend', _subformPanelHTML(existingData));

      var panel = panelTarget.querySelector('.nb-sf-fk-panel');
      if (!panel) return;

      /* Populate child form list asynchronously */
      _populateFormDropdown(panel, existingData.form_code, function () {
        /* After forms load, populate FK field dropdown */
        if (existingData.form_code) {
          _populateFkDropdown(panel, existingData.form_code, existingData.fk_field);
        }
      });

      /* When child form selection changes → reload FK dropdown */
      var formSel = panel.querySelector('.nb-sf-form-code');
      if (formSel) {
        formSel.addEventListener('change', function () {
          _populateFkDropdown(panel, formSel.value, '');
        });
      }

      /* "＋ Create FK Field" button */
      var createBtn = panel.querySelector('.nb-sf-create-fk');
      if (createBtn) {
        createBtn.addEventListener('click', function () {
          _createFkField(panel);
        });
      }
    }

    function _subformPanelHTML(d) {
      var fc = d.form_code || '';
      var fk = d.fk_field  || '';
      var isFk    = d.is_fk            ? 'checked' : '';
      var hideGrid= d.hide_in_grid     ? 'checked' : '';
      var srvRo   = d.server_readonly  ? 'checked' : '';
      return [
        '<div class="nb-sf-fk-panel" style="display:flex;flex-direction:column;gap:8px;padding:10px 0;">',

          /* ── Child form ── */
          '<div>',
            '<label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--text-muted,#666);">Child Form</label>',
            '<select class="nu-input nb-sf-form-code" style="width:100%;">',
              '<option value="">— select form —</option>',
            '</select>',
          '</div>',

          /* ── FK field ── */
          '<div>',
            '<label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--text-muted,#666);">FK Field (links child → parent)</label>',
            '<div style="display:flex;gap:6px;">',
              '<select class="nu-input nb-sf-fk-field" style="flex:1;">',
                '<option value="">— select FK field —</option>',
              '</select>',
              '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm nb-sf-create-fk" title="Auto-create hidden FK field in child form">＋ Create FK Field</button>',
            '</div>',
          '</div>',

          /* ── Flag toggles ── */
          '<div style="display:flex;flex-direction:column;gap:4px;padding:6px 8px;background:var(--bg-elevated,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0);">',
            '<label style="font-size:11px;font-weight:700;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">FK Field Flags</label>',
            _toggleRow('is_fk',           'nb-sf-is-fk',           'is_fk',           isFk,     'FK field',       'Force hidden; builder locks this field'),
            _toggleRow('hide_in_grid',    'nb-sf-hide-in-grid',    'hide_in_grid',    hideGrid, 'Hide in grid',   'Excludes column from subform table'),
            _toggleRow('server_readonly', 'nb-sf-server-readonly', 'server_readonly', srvRo,    'Server readonly','PHP ignores POST value; always writes parent ID'),
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

    /* Fetch all forms and populate the <select> */
    function _populateFormDropdown(panel, selectedCode, cb) {
      var sel = panel.querySelector('.nb-sf-form-code');
      if (!sel) { if (cb) cb(); return; }

      fetch('api/forms.php?action=list', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          var forms = (json && json.success && json.forms) ? json.forms : [];
          /* Remove all except the placeholder */
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

    /* Fetch child form layout and populate the FK <select> */
    function _populateFkDropdown(panel, formCode, selectedFk) {
      var sel = panel.querySelector('.nb-sf-fk-field');
      if (!sel || !formCode) return;

      fetch('api/form.php?action=subform_fields&code=' + encodeURIComponent(formCode), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          var fields = (json && json.success && json.data) ? (json.data.all_fields || json.data.layout || []) : [];
          while (sel.options.length > 1) sel.remove(1);
          fields.forEach(function (f) {
            var fname = f.name || f.fieldname || '';
            var ftype = f.type || f.fieldtype || 'text';
            if (!fname) return;
            var skip  = ['html','heading','divider','fieldset','subform','button'];
            if (skip.indexOf(ftype) !== -1) return;
            var opt = document.createElement('option');
            opt.value = fname;
            opt.textContent = (f.label || f.fieldlabel || fname) + ' [' + fname + ']';
            if (fname === selectedFk) opt.selected = true;
            sel.appendChild(opt);
          });
        })
        .catch(function () {});
    }

    /* Read current subform config from card data attributes */
    function _readSubformData(card) {
      return {
        form_code:       card.dataset.sfFormCode       || '',
        fk_field:        card.dataset.sfFkField        || '',
        is_fk:           card.dataset.sfIsFk           === '1',
        hide_in_grid:    card.dataset.sfHideInGrid     === '1',
        server_readonly: card.dataset.sfServerReadonly === '1',
      };
    }

    /* ══════════════════════════════════════════════════════════════════
       GAP 3 — createFkField()
       Adds { name, type:"hidden", is_fk:true, hide_in_grid:true,
               server_readonly:true } to the child form layout via API.
    ═══════════════════════════════════════════════════════════════════ */
    function _createFkField(panel) {
      var formCodeSel = panel.querySelector('.nb-sf-form-code');
      var fkFieldSel  = panel.querySelector('.nb-sf-fk-field');
      var formCode = formCodeSel ? formCodeSel.value : '';
      var fkName   = fkFieldSel  ? fkFieldSel.value  : '';

      if (!formCode) {
        _sfToast('Select a child form first', 'error'); return;
      }
      if (!fkName) {
        /* Prompt for a name */
        fkName = window.prompt('Enter FK field name (e.g. order_id):');
        if (!fkName || !fkName.trim()) return;
        fkName = fkName.trim().replace(/[^a-zA-Z0-9_]/g, '_');
      }

      /* Fetch the child form's current layout, then PATCH in the FK field */
      fetch('api/forms.php?action=get_by_code&code=' + encodeURIComponent(formCode), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.success || !json.form) throw new Error(json.error || 'Child form not found');
          var form   = json.form;
          var layout = [];
          try { layout = JSON.parse(form.form_layout || '[]'); } catch (e) { layout = []; }
          if (!Array.isArray(layout)) layout = [];

          /* Check if FK field already exists */
          var exists = layout.some(function (f) {
            return (f.name || f.fieldname || '') === fkName;
          });
          if (exists) {
            _sfToast('Field "' + fkName + '" already exists in ' + formCode, 'error');
            return null;
          }

          /* Append the hidden FK field */
          layout.push({
            name:            fkName,
            label:           fkName,
            type:            'hidden',
            is_fk:           true,
            hide_in_grid:    true,
            server_readonly: true
          });

          /* Save back via patch_layout */
          return fetch('api/forms.php?action=patch_layout&id=' + encodeURIComponent(form.form_id || form.id || ''), {
            method:  'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ form_layout: JSON.stringify(layout) })
          }).then(function (r) { return r.json(); });
        })
        .then(function (saveJson) {
          if (!saveJson) return;
          if (!saveJson.success) { _sfToast(saveJson.error || 'Save failed', 'error'); return; }
          _sfToast('FK field "' + fkName + '" created in child form ' + formCode);
          /* Refresh the FK dropdown so the new field appears */
          _populateFkDropdown(panel, formCode, fkName);
        })
        .catch(function (e) { _sfToast('Error: ' + e.message, 'error'); });
    }

    function _sfToast(msg, type) {
      if (window.NuApp && NuApp.toast) { NuApp.toast(msg, type); return; }
      alert(msg);
    }

    /* ══════════════════════════════════════════════════════════════════
       GAP 2 — patch layout serialiser (_readFieldCard)
       We wrap whichever method nbFormBuilder uses to serialise the
       canvas (getLayout / collectLayout / _serializeCanvas etc.) and
       augment subform field objects with the FK panel values.
    ═══════════════════════════════════════════════════════════════════ */

    function _augmentSubformData(fieldObj, card) {
      var type = fieldObj.type || fieldObj.fieldtype || '';
      if (type !== 'subform') return fieldObj;

      var panel = card.querySelector('.nb-sf-fk-panel');
      if (!panel) return fieldObj;

      var formCodeSel = panel.querySelector('.nb-sf-form-code');
      var fkFieldSel  = panel.querySelector('.nb-sf-fk-field');
      var isFkChk     = panel.querySelector('.nb-sf-is-fk');
      var hideChk     = panel.querySelector('.nb-sf-hide-in-grid');
      var srvRoChk    = panel.querySelector('.nb-sf-server-readonly');

      var formCode = formCodeSel ? (formCodeSel.value || '') : '';
      var fkField  = fkFieldSel  ? (fkFieldSel.value  || '') : '';

      if (!fieldObj.subform) fieldObj.subform = {};
      if (formCode) fieldObj.subform.form_code = formCode;
      if (fkField)  fieldObj.subform.fk_field  = fkField;

      if (isFkChk)  { if (isFkChk.checked)  fieldObj.is_fk           = true; else delete fieldObj.is_fk;           }
      if (hideChk)  { if (hideChk.checked)   fieldObj.hide_in_grid    = true; else delete fieldObj.hide_in_grid;    }
      if (srvRoChk) { if (srvRoChk.checked)  fieldObj.server_readonly = true; else delete fieldObj.server_readonly; }

      return fieldObj;
    }

    /* Wrap all known layout-serialiser method names */
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
       Auto-upgrade existing subform cards when the builder canvas loads
    ═══════════════════════════════════════════════════════════════════ */

    function upgradeAllSubformCards() {
      var canvas = document.getElementById('formCanvas');
      if (!canvas) return;
      canvas.querySelectorAll('.nb-cfield').forEach(function (card) {
        var type = card.dataset.type || card.dataset.fieldtype || '';
        if (type === 'subform') upgradeSubformPanel(card);
      });
    }

    /* MutationObserver: upgrade new subform cards as they are added */
    var _canvasObs = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          var type = node.dataset && (node.dataset.type || node.dataset.fieldtype || '');
          if (type === 'subform') upgradeSubformPanel(node);
          if (node.querySelectorAll) {
            node.querySelectorAll('.nb-cfield[data-type="subform"], .nb-cfield[data-fieldtype="subform"]')
              .forEach(upgradeSubformPanel);
          }
        });
      });
    });

    var canvas = document.getElementById('formCanvas');
    if (canvas) {
      _canvasObs.observe(canvas, { childList: true, subtree: true });
      upgradeAllSubformCards();
    }

    /* Re-run when forms module is dynamically loaded */
    document.addEventListener('nu:form:opened', function () {
      setTimeout(function () {
        var c = document.getElementById('formCanvas');
        if (c) {
          _canvasObs.observe(c, { childList: true, subtree: true });
          upgradeAllSubformCards();
        }
      }, 100);
    });

    /* Expose globally for direct HTML onclick usage */
    window.nbCreateFkField = _createFkField;

    console.log('[nb-subform-fk-builder] FK-aware subform panel patches applied.');
  });

})();
