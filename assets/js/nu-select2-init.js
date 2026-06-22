/**
 * nu-select2-init.js
 *
 * Initialises Select2 on any <select> that carries:
 *   data-select-type="select2"   ← emitted by form.php
 *   .nu-select2                  ← legacy class (backwards compat)
 *
 * data-select-mode="single|multiple"  controls single vs multi-value.
 * data-placeholder="…"                placeholder text.
 * data-allow-clear="true|false"       show/hide the × clear button.
 *
 * ─── Why r.GetData(...).destroy is not a function ───────────────────
 * Select2 v4 constructor does:
 *   var id = el.getAttribute('data-select2-id'); // e.g. "select2-data-8-u9m9"
 *   $.data(el, id).destroy();
 *
 * If data-select2-id is still on the element when the constructor runs,
 * Select2 looks up that key via $.data(el, id). In jQuery 3 this reads
 * from an internal cache that is NOT the same as the raw expando bucket.
 * Previous fixes wiped the expando but left the $.data cache intact, so
 * the stale (destroy-less) stub survived and crashed — including on retry.
 *
 * Root causes addressed in this version:
 *   1. Server-rendered HTML from form.php can arrive with data-select2-id
 *      already baked in from a previous render cycle.
 *   2. _execModuleScripts() re-runs inline <script> tags which can call
 *      select2() before the nu:form:opened handler fires, stamping the
 *      attribute again before our cleanup runs.
 *   3. The retry path in _initOne was missing removeAttribute('data-select2-id')
 *      before $.removeData(), so the constructor still found the stale ID
 *      and crashed identically on every retry.
 *
 * Fix strategy:
 *   A. nuInitSelect2: strip data-select2-id from ALL targets up-front AND
 *      fully flush jQuery data before touching any element.
 *   B. nuDestroySelect2: always remove the attribute first, then flush data.
 *   C. _initOne: remove attribute + flush data at BOTH entry and retry paths.
 *   D. nuInitSelect2: remove the data-nu-s2 double-init guard entirely —
 *      it was preventing re-init after _execModuleScripts re-ran inline
 *      scripts, leaving elements in a half-initialised state.
 *
 * Public API
 *   nuDestroySelect2(el)   — hard-destroy a single element (safe if none)
 *   nuInitSelect2(scope)   — init/re-init all targets inside scope
 *   nuReinitSelect2(el)    — atomic destroy + re-init one element
 */
(function () {
  'use strict';

  var READY_ATTR = 'data-nu-s2';

  var DEBUG = (window.NU_SELECT2_DEBUG !== false);
  function dbg() {
    if (!DEBUG) return;
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[nu-select2]');
    console.log.apply(console, args);
  }

  /**
   * Atomically wipe ALL Select2 state from a single element.
   * Safe to call even if Select2 was never initialised on the element.
   */
  function _hardWipe(el) {
    if (typeof jQuery === 'undefined') return;
    var $ = jQuery;

    // ── 1. Remove the attribute FIRST — this is the critical step.
    //       Select2's constructor reads this attribute before touching $.data,
    //       so removing it here means no stale lookup can ever reach .destroy().
    el.removeAttribute('data-select2-id');

    // ── 2. Flush the entire jQuery data store (both cache layers).
    //       $.removeData(el) with no key is the only call that clears both
    //       the jQuery 3 internal cache AND the expando bucket atomically.
    try { $.removeData(el); } catch (e) { /* ignore */ }

    // ── 3. Belt-and-braces: also wipe raw expando keys that start with 'select2'
    try {
      var expando = $.expando;
      if (expando && el[expando]) {
        var bucket = el[expando];
        if (typeof bucket === 'object' && bucket !== null) {
          if (bucket.data) bucket.data = {};
          Object.keys(bucket).forEach(function (k) {
            if (k.indexOf('select2') === 0) delete bucket[k];
          });
        }
      }
    } catch (e) { /* ignore */ }
  }

  /**
   * Hard-destroy any Select2 instance on `el`, including corrupted ones.
   * Clears the ready stamp so the element can be re-initialised.
   */
  function nuDestroySelect2(el) {
    if (typeof jQuery === 'undefined') return;
    var $ = jQuery;

    el.removeAttribute(READY_ATTR);
    dbg('destroy', el.name || el.getAttribute('data-field') || '?');

    // Wipe attribute + data BEFORE attempting polite destroy
    _hardWipe(el);

    // Attempt polite destroy via public API (may be a no-op if already wiped)
    try {
      if (typeof $.fn.select2 !== 'undefined') {
        var existing = $.data(el, 'select2');
        if (existing && typeof existing.destroy === 'function') {
          $(el).select2('destroy');
          dbg('  polite destroy OK');
        }
      }
    } catch (e) { dbg('  destroy threw (ignored):', e.message); }

    // Second wipe to clean up anything polite destroy re-stamped
    _hardWipe(el);

    // Remove orphaned Select2 DOM siblings
    var next = el.nextElementSibling;
    while (next && next.classList && (
      next.classList.contains('select2') ||
      next.classList.contains('select2-container')
    )) {
      var toRemove = next;
      next = next.nextElementSibling;
      toRemove.parentNode.removeChild(toRemove);
    }
  }

  window.nuDestroySelect2 = nuDestroySelect2;

  /* ── Run select2() on one already-cleaned element ────────────────── */
  function _initOne(el) {
    var $ = jQuery;
    var placeholder = el.dataset.placeholder || 'Select\u2026';
    var allowClear  = el.dataset.allowClear !== 'false';
    var isMultiple  = el.dataset.selectMode === 'multiple' || el.hasAttribute('multiple');

    // Always hard-wipe before calling select2() — covers the case where
    // _execModuleScripts already ran an inline script that stamped the element.
    _hardWipe(el);

    var opts = {
      width:          '100%',
      theme:          (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
      placeholder:    placeholder,
      allowClear:     allowClear,
      multiple:       isMultiple,
      dropdownParent: $(document.body),
    };

    try {
      $(el).select2(opts);
      el.setAttribute(READY_ATTR, '1');
      dbg('init OK \u2705', el.name || el.getAttribute('data-field') || '?');
      return true;
    } catch (err) {
      console.error('[nu-select2] init FAILED \u274c', err.message, el);

      // Full hard-wipe then one retry — attribute MUST be removed before
      // $.removeData, otherwise Select2's constructor still finds the stale
      // ID on retry and crashes identically.
      _hardWipe(el);

      try {
        $(el).select2(opts);
        el.setAttribute(READY_ATTR, '1');
        dbg('retry OK \u2705', el.name || el.getAttribute('data-field') || '?');
        return true;
      } catch (retryErr) {
        console.error('[nu-select2] retry FAILED \u274c', retryErr.message, el);
        return false;
      }
    }
  }

  /**
   * Initialise all Select2 targets within `scope`.
   *
   * NOTE: The data-nu-s2 double-init guard has been intentionally removed.
   * nubuilder-next.js calls _execModuleScripts() which re-runs any inline
   * <script> tags inside the form HTML — these can call select2() directly
   * and stamp data-nu-s2="1" before nuInitSelect2 fires from nu:form:opened.
   * When nuInitSelect2 then skips those elements (because the guard is set),
   * it leaves them in a state where data-select2-id is present but the jQuery
   * data cache has been invalidated by the DOM re-injection, causing the
   * destroy crash on the NEXT open. Removing the guard ensures every call
   * to nuInitSelect2 does a clean destroy+reinit cycle via _hardWipe.
   */
  function nuInitSelect2(scope) {
    var hasJQ      = typeof jQuery !== 'undefined';
    var hasSelect2 = hasJQ && typeof jQuery.fn.select2 !== 'undefined';
    if (!hasJQ || !hasSelect2) {
      console.warn('[nuInitSelect2] jQuery or Select2 not available');
      return;
    }

    var $ = jQuery;
    var root = scope instanceof Element ? scope : document;

    var $targets = $(root).find(
      'select[data-select-type="select2"], select.nu-select2'
    );

    dbg('nuInitSelect2 \u2014 targets:', $targets.length, '| options:', $targets.length > 0 ? $targets.first()[0].options.length : 'n/a');

    // ── KEY FIX A: Strip stale data-select2-id + flush jQuery data from ALL
    //    targets up-front, BEFORE any per-element loop work.
    //    Server-rendered HTML from form.php can arrive with these attributes
    //    already baked in from a previous render, and _execModuleScripts can
    //    re-stamp them via inline scripts before this function runs.
    $targets.each(function () { _hardWipe(this); });

    $targets.each(function () {
      var el = this;
      dbg('init [' + (el.name || el.getAttribute('data-field') || '?') + ']',
          '| options:', el.options.length);
      nuDestroySelect2(el);
      _initOne(el);
    });
  }

  window.nuInitSelect2 = nuInitSelect2;

  /**
   * Force destroy + re-init a single element regardless of stamp.
   */
  function nuReinitSelect2(el) {
    if (!el) return;
    el.removeAttribute(READY_ATTR);
    _hardWipe(el);
    nuDestroySelect2(el);
    _initOne(el);
  }

  window.nuReinitSelect2 = nuReinitSelect2;

  // Auto-init: nu:form:opened fires after modal is in DOM
  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    // Small delay lets any inline scripts run and stamp their elements first,
    // then nuInitSelect2 hard-wipes everything and does a clean re-init.
    setTimeout(function () { nuInitSelect2(scope); }, 50);
  });

  // Page-load init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { nuInitSelect2(); });
  } else {
    nuInitSelect2();
  }

}());
