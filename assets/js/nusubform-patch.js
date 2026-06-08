/**
 * nusubform-patch.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Fix — Bug 1: nu:parent:saved is never dispatched after the parent form saves.
 *
 * The restore commit (06ebb62) wiped the save handler from nubuilder-next.js.
 * This patch wraps NuApp.apiJson so that any call to api/form.php?action=save
 * that succeeds automatically:
 *   1. Stamps parent_id on every .nu-subform-container in the active modal
 *   2. Calls nuSubform.onParentSaved() to flush the pending queue → DB
 *   3. Fires the nu:parent:saved DOM event for any other listeners
 *
 * IMPORTANT: The URL check must match ONLY the parent-form save action
 * (action=save) and NOT the subform save action (action=subform_save).
 * Using indexOf('action=save') matches both, which causes onParentSaved()
 * to re-fire on every subform row flush → duplicate rows created.
 * We use a regex word-boundary check instead: /[?&]action=save(&|$)/.
 *
 * Load this file AFTER nubuilder-next.js and nusubform.js.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function (window) {
  'use strict';

  /* Matches api/form.php?action=save or &action=save but NOT action=subform_save */
  var PARENT_SAVE_RE = /[?&]action=save(&|$)/;

  function applyPatch() {
    var app = window.NuApp;
    if (!app || typeof app.apiJson !== 'function') return;

    var _origApiJson = app.apiJson.bind(app);

    app.apiJson = function (url, options) {
      return _origApiJson(url, options).then(function (json) {

        /* Only act on a successful PARENT-form save — not subform_save */
        if (
          typeof url === 'string' &&
          PARENT_SAVE_RE.test(url) &&
          json && json.success
        ) {
          /* Resolve the new record id from whichever shape the API returns */
          var savedId = String(
            (json.data && (json.data.id || json.data.record_id))
              || json.id
              || json.record_id
              || ''
          );

          if (savedId) {
            /* Find the innermost open modal/box that contains subform containers.
               NuApp modals are .nu-form-overlay > div (the box). */
            var box = null;
            var overlays = document.querySelectorAll('.nu-form-overlay');
            overlays.forEach(function (ov) {
              if (ov.querySelector('.nu-subform-container')) box = ov;
            });
            /* Fall back to the whole document scope if no overlay found */
            var scope = box || document;

            /* 1. Stamp parent_id onto every subform container in scope */
            if (typeof app._stampSubformParentId === 'function' && box) {
              app._stampSubformParentId(box, savedId);
            } else {
              scope.querySelectorAll('.nu-subform-container').forEach(function (el) {
                el.dataset.parentId = savedId;
              });
            }

            /* 2. Flush pending queue + reload grids */
            if (window.nuSubform && typeof window.nuSubform.onParentSaved === 'function') {
              window.nuSubform.onParentSaved(savedId, scope);
            }

            /* 3. Fire the DOM event so any other listeners catch it */
            document.dispatchEvent(new CustomEvent('nu:parent:saved', {
              detail: { id: savedId, scope: scope }
            }));
          }
        }

        return json; /* always pass the response through unchanged */
      });
    };

    console.log('[nusubform-patch] nu:parent:saved dispatch patch applied.');
  }

  /* Apply immediately if NuApp is already defined, otherwise wait for DOMContentLoaded */
  if (window.NuApp && window.NuApp.apiJson) {
    applyPatch();
  } else {
    document.addEventListener('DOMContentLoaded', applyPatch);
  }

}(window));
