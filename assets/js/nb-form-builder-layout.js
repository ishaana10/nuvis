/**
 * nb-form-builder-layout.js
 * Patches the nbFormBuilder canvas to support:
 *  1. Multi-field rows — drop a field into an *existing* row body
 *  2. nuToggleContainer registered on window (preview collapse fix)
 *  3. Correct col-span applied inline on field cards inside rows
 *
 * This file must be loaded AFTER nubuilder-next.js.
 */
(function () {
  'use strict';

  // ── 1. Register nuToggleContainer on window for preview modals ────────────
  // (The PHP no longer emits an inline <script> — it relies on this instead)
  if (!window.nuToggleContainer) {
    window.nuToggleContainer = function (btn) {
      if (!btn) return;
      var tid  = btn.getAttribute('data-target');
      if (!tid) return;
      var body = document.getElementById(tid);
      if (!body) return;
      var hidden = body.style.display === 'none' || body.style.display === '';
      body.style.display = hidden ? 'block' : 'none';
      btn.innerHTML      = hidden ? '&#9660;' : '&#9654;';
    };
  }

  // ── 2. Wait for nbFormBuilder to exist, then patch the canvas ─────────────
  function patchBuilder() {
    var fb = window.nbFormBuilder;
    if (!fb) return;

    // ── 2a. _applyColSpan: set grid-column:span N on a field card ──────────
    fb._applyColSpan = function (card, col) {
      var c = parseInt(col, 10) || 12;
      if (c < 1 || c > 12) c = 12;
      card.style.gridColumn = 'span ' + c;
      card.dataset.col = String(c);
      // update the visible span badge
      var badge = card.querySelector('.nb-cfield-span-badge');
      if (badge) badge.textContent = c + '/12';
      // update active state on span buttons
      card.querySelectorAll('.nb-span-btn').forEach(function (btn) {
        btn.classList.toggle('active', parseInt(btn.dataset.span, 10) === c);
      });
    };

    // ── 2b. Patch _addField so it wires up span buttons properly ───────────
    var _origAddField = fb._addField;
    fb._addField = function (type, label, name, required, extraData) {
      var card = _origAddField.call(this, type, label, name, required, extraData);
      if (!card) return card;

      // wire span buttons inside the newly created card
      card.querySelectorAll('.nb-span-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var col = parseInt(btn.dataset.span, 10) || 12;
          fb._applyColSpan(card, col);
        });
      });

      return card;
    };

    // ── 2c. Make existing row bodies accept field drops ────────────────────
    //
    // The canvas already handles drops at the canvas level (creates a new row).
    // We also want: drag a toolbox item OR an existing card → drop onto an
    // existing .nb-row-body  →  append the card into that row.
    //
    // We delegate to the canvas's existing _makeDraggable / _renderField
    // pipeline; we only need to intercept the dragover/drop on row bodies.

    function attachRowBodyDrop(rowBody) {
      if (rowBody._nuDropPatched) return;
      rowBody._nuDropPatched = true;

      rowBody.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        rowBody.classList.add('drag-col-over');
      });

      rowBody.addEventListener('dragleave', function (e) {
        // only remove if leaving the rowBody itself, not a child
        if (!rowBody.contains(e.relatedTarget)) {
          rowBody.classList.remove('drag-col-over');
        }
      });

      rowBody.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        rowBody.classList.remove('drag-col-over');

        // ── Case A: dropping an existing card from another row ──
        var cardId = e.dataTransfer.getData('text/nb-card-id');
        if (cardId) {
          var existingCard = document.getElementById(cardId);
          if (existingCard && existingCard !== rowBody.parentElement) {
            // remove from current row
            var oldRow = existingCard.closest('.nb-row');
            existingCard.parentNode.removeChild(existingCard);
            rowBody.appendChild(existingCard);
            fb._applyColSpan(existingCard, existingCard.dataset.col || 12);
            // clean up old row if empty
            if (oldRow && !oldRow.querySelector('.nb-cfield')) {
              oldRow.parentNode && oldRow.parentNode.removeChild(oldRow);
            }
            fb._updateEmptyState();
            return;
          }
        }

        // ── Case B: dropping a toolbox type ──
        var dtype = e.dataTransfer.getData('text/nb-type');
        if (dtype) {
          var card = fb._addFieldToRow(dtype, rowBody);
          if (card) fb._applyColSpan(card, 6); // default half-width when dropped into existing row
          fb._updateEmptyState();
          return;
        }

        // ── Case C: plain text/type from old drag model ──
        var plain = e.dataTransfer.getData('text/plain');
        if (plain) {
          var card2 = fb._addFieldToRow(plain, rowBody);
          if (card2) fb._applyColSpan(card2, 6);
          fb._updateEmptyState();
        }
      });
    }

    // ── 2d. _addFieldToRow: create a field card directly inside a row body ──
    fb._addFieldToRow = function (type, rowBody) {
      var label    = type.charAt(0).toUpperCase() + type.slice(1) + ' Field';
      var name     = type + '_' + Date.now();
      var card     = fb._buildFieldCard(type, label, name, false, {});
      if (!card) return null;
      // give it a unique id so we can re-reference it
      if (!card.id) card.id = 'nb-card-' + Date.now();
      // make it draggable (for moving between rows later)
      card.setAttribute('draggable', 'true');
      card.addEventListener('dragstart', function (ev) {
        ev.dataTransfer.setData('text/nb-card-id', card.id);
        card.classList.add('drag-source');
      });
      card.addEventListener('dragend', function () {
        card.classList.remove('drag-source');
      });
      rowBody.appendChild(card);
      fb._applyColSpan(card, 6);
      return card;
    };

    // ── 2e. _buildFieldCard: expose the card-building step separately ───────
    // If the existing _addField already returns the card element we just
    // re-use that; otherwise we create one via the existing _renderField.
    if (!fb._buildFieldCard) {
      fb._buildFieldCard = function (type, label, name, required, extra) {
        // Use the existing field card creation path but don't append to canvas
        var tmpHolder = document.createElement('div');
        var card = fb._makeFieldCard
          ? fb._makeFieldCard(type, label, name, required, extra)
          : null;
        if (!card) {
          // Fallback: call _addField against a temp container
          var prevCanvas = fb._canvas;
          fb._canvas = tmpHolder;
          card = _origAddField.call(fb, type, label, name, required, extra);
          fb._canvas = prevCanvas;
          if (tmpHolder.firstElementChild && tmpHolder.firstElementChild !== card) {
            card = tmpHolder.firstElementChild;
          }
        }
        return card;
      };
    }

    // ── 2f. Patch addRow so new rows also get drop-enabled bodies ──────────
    var _origAddRow = fb.addRow;
    fb.addRow = function () {
      var row = _origAddRow ? _origAddRow.call(this) : null;
      if (row) {
        var body = row.querySelector('.nb-row-body');
        if (body) attachRowBodyDrop(body);
      }
      return row;
    };

    // ── 2g. Patch _rebuildCanvas so restored rows get drop listeners too ───
    var _origRebuild = fb._rebuildCanvas;
    fb._rebuildCanvas = function (layout) {
      var result = _origRebuild ? _origRebuild.call(this, layout) : undefined;
      // attach drop to all row bodies after rebuild
      var canvas = document.getElementById('formCanvas');
      if (canvas) {
        canvas.querySelectorAll('.nb-row-body').forEach(attachRowBodyDrop);
        // also wire span buttons on all rebuilt cards
        canvas.querySelectorAll('.nb-cfield').forEach(function (card) {
          card.querySelectorAll('.nb-span-btn').forEach(function (btn) {
            // remove existing listener then re-add to avoid doubles
            var freshBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(freshBtn, btn);
            freshBtn.addEventListener('click', function (e) {
              e.stopPropagation();
              fb._applyColSpan(card, parseInt(freshBtn.dataset.span, 10) || 12);
            });
          });
        });
      }
      return result;
    };

    // ── 2h. Set dataTransfer type on toolbox drags ─────────────────────────
    // The existing drag code uses 'text/plain'. We add 'text/nb-type' so our
    // row-body drop handler can distinguish toolbox drags from card moves.
    document.addEventListener('dragstart', function (e) {
      var tool = e.target.closest('.nb-tool[data-type]');
      if (tool) {
        e.dataTransfer.setData('text/nb-type', tool.dataset.type);
      }
      var card = e.target.closest('.nb-cfield[id]');
      if (card) {
        if (!card.id) card.id = 'nb-card-' + Date.now();
        e.dataTransfer.setData('text/nb-card-id', card.id);
      }
    }, true);

  } // end patchBuilder

  // ── 3. Run patch after DOMContentLoaded (or immediately if already loaded) ─
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchBuilder);
  } else {
    // Also re-run when the forms module is loaded dynamically
    patchBuilder();
  }

  // Re-run whenever the forms module loads (NuApp.loadModule fires a custom event)
  document.addEventListener('nu:form:opened', patchBuilder);

  // Also expose a manual call so nbFormBuilder._initAfterLoad can trigger it
  window._nuPatchBuilderLayout = patchBuilder;

})();
