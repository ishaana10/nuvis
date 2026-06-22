/**
 * nu-select2-init.js
 *
 * Initialises Select2 on any <select> that carries:
 *   data-select-type="select2"   ← emitted by FormRenderer.php
 *   .nu-select2                  ← legacy class (backwards compat)
 *
 * data-select-mode="single|multiple"  controls single vs multi-value.
 * data-placeholder="…"                placeholder text.
 * data-allow-clear="true|false"       show/hide the × clear button.
 *
 * ─── Why the crash happened ──────────────────────────────────────────
 * Error: "r.GetData(...).destroy is not a function"
 *
 * Select2 v4's constructor calls GetData(el, 'select2').destroy()
 * internally before we can intercept it.  A previous (failed or partial)
 * Select2 init cycle left a stub object in jQuery's data store that had no
 * .destroy() method.
 *
 * Root cause (jQuery 3.x): jQuery 3 stores element data directly on
 * el[jQuery.expando] as a plain object.  Two separate data paths exist:
 *
 *   A) The PUBLIC path — $.data(el, key) / $.removeData(el, key)
 *      writes into el[expando].data[key].
 *
 *   B) The INTERNAL path — jQuery.cache is gone in v3; some Select2 builds
 *      write their instance at el[expando][key] (top-level, not nested
 *      under .data).
 *
 * Our old code nuked path B but called $.removeData for path A afterwards.
 * In some jQuery 3.x micro-versions $.removeData re-reads the expando to
 * find the key, and if the expando was already partially wiped it could
 * leave a ghost entry — or the reverse: the polite destroy ran first and
 * left a stub at path B.  Either way, Select2's next constructor call hit
 * the stub and crashed.
 *
 * Fix: nuke path B (raw expando) FIRST in a single comprehensive pass,
 * then call $.removeData so jQuery's own bookkeeping stays clean.
 * Guard polite destroy with a typeof check so a stub never throws.
 *
 * Public API
 *   nuDestroySelect2(el)        — hard-destroy a single element (safe if none)
 *   nuInitSelect2(scope)        — init/re-init all targets inside scope
 *   nuReinitSelect2(el)         — atomic destroy + re-init one element
 *                                 (called by field-type swap in form builder)
 */
(function () {
  'use strict';

  // ── DEBUG FLAG ────────────────────────────────────────────────────────────
  // Set window.NU_SELECT2_DEBUG = false in your app bootstrap to silence logs.
  var DEBUG = (window.NU_SELECT2_DEBUG !== false);

  function dbg() {
    if (!DEBUG) return;
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[nu-select2]');
    console.log.apply(console, args);
  }

  /**
   * Hard-destroy any Select2 instance on `el`, including corrupted ones.
   * Safe to call when no instance exists.
   *
   * @param {Element} el
   */
  function nuDestroySelect2(el) {
    if (typeof jQuery === 'undefined') return;
    var $ = jQuery;

    var dataSelectId = el.getAttribute('data-select2-id');
    dbg('destroy START', el.tagName,
        'name=' + (el.name || el.getAttribute('data-field') || '?'),
        'data-select2-id before clean:', dataSelectId);

    // ── Step 1: hard-nuke the jQuery internal expando bucket FIRST ─────
    //
    // We do this BEFORE the polite destroy so that if $.removeData or
    // the Select2 destroy implementation internally re-reads the expando,
    // it will find nothing rather than the stale stub.
    //
    // jQuery 3.x:  el[expando] is a plain object  { data: { select2: … }, … }
    // jQuery 1/2:  el[expando] is a numeric cache key → $.cache[key]
    //
    var expando = $.expando;
    if (expando && el[expando] !== undefined) {
      var bucket = el[expando];

      if (typeof bucket === 'object' && bucket !== null) {
        // jQuery 3.x — bucket IS the data container.
        // Wipe every known Select2 key at both levels.
        if (bucket.data) {
          delete bucket.data['select2'];
          delete bucket.data['select2-id'];
        }
        // Some Select2 builds write at the top level of the expando object
        delete bucket['select2'];
        delete bucket['select2-id'];

      } else if (typeof bucket === 'number') {
        // jQuery 1.x / 2.x — bucket is a numeric cache key
        var cache = $.cache && $.cache[bucket];
        if (cache && cache.data) {
          delete cache.data['select2'];
          delete cache.data['select2-id'];
        }
      }
    }

    // ── Step 2: polite destroy via the public API ──────────────────────
    // Now that the expando is clean, attempt the official teardown.
    // Guard with typeof so a stub object (no .destroy method) never throws.
    try {
      if (typeof $.fn.select2 !== 'undefined') {
        var existing = $.data(el, 'select2');
        dbg('existing Select2 instance found —', existing ? 'destroying' : 'none');
        if (existing && typeof existing.destroy === 'function') {
          $(el).select2('destroy');
        }
      }
    } catch (e) { dbg('destroy threw (ignored):', e.message); }

    // ── Step 3: flush the public $.data entry ─────────────────────────
    // Runs after Steps 1 & 2 so jQuery's internal bookkeeping is correct.
    $.removeData(el, 'select2');

    // ── Step 4: remove the HTML attribute stamp ────────────────────────
    el.removeAttribute('data-select2-id');

    // ── Step 5: remove orphaned Select2 container siblings from the DOM ─
    // Select2 injects a <span class="select2 …"> (or "select2-container …")
    // sibling after the <select>.  If destroy() above didn't remove it
    // (because the instance was corrupted), remove it manually so the next
    // init does not produce a duplicate widget.
    var next = el.nextElementSibling;
    while (next && next.classList && (
      next.classList.contains('select2') ||
      next.classList.contains('select2-container')
    )) {
      var toRemove = next;
      next = next.nextElementSibling;
      toRemove.parentNode.removeChild(toRemove);
    }

    dbg('destroy END', el.tagName,
        'name=' + (el.name || el.getAttribute('data-field') || '?'));
  }

  // Expose globally — nb-form-builder.js and other modules call this
  window.nuDestroySelect2 = nuDestroySelect2;

  /**
   * Initialise (or re-initialise) all Select2 targets within `scope`.
   *
   * @param {Element|null} scope  Container to search in (default: document)
   */
  function nuInitSelect2(scope) {
    var hasJQ      = typeof jQuery !== 'undefined';
    var hasSelect2 = hasJQ && typeof jQuery.fn.select2 !== 'undefined';

    if (!hasJQ || !hasSelect2) {
      console.warn('[nuInitSelect2] jQuery or Select2 not available — aborting');
      return;
    }

    var $ = jQuery;
    var root = scope instanceof Element ? scope : document;

    // ── DIAGNOSTIC BLOCK ──────────────────────────────────────────────────
    // Scans every element whose name/data-field contains "select2" and logs
    // whether it is actually a <select> or some other tag (e.g. <input>).
    // This is the key diagnostic to determine whether the renderer is
    // outputting the wrong element type for select2 fields.
    if (DEBUG) {
      var allEls = root.querySelectorAll('[name],[data-field]');
      var suspects = [];
      allEls.forEach(function (el) {
        var nm = el.name || el.getAttribute('data-field') || '';
        if (/select2/i.test(nm)) {
          suspects.push({
            tag:            el.tagName,
            type:           el.type || '(none)',
            name:           nm,
            classes:        el.className,
            dataSelectType: el.getAttribute('data-select-type') || '(none)',
            isSelect:       el.tagName === 'SELECT'
          });
        }
      });
      if (suspects.length) {
        console.group('[nu-select2] DIAGNOSTIC — fields whose name/data-field contains "select2"');
        suspects.forEach(function (s) {
          var status = s.isSelect
            ? '✅ SELECT (correct)'
            : '❌ NOT a <select> — got <' + s.tag + ' type=' + s.type + '> — Select2 will never run on this';
          console.log(
            status,
            '| name/data-field:', s.name,
            '| data-select-type:', s.dataSelectType,
            '| classes:', s.classes
          );
          if (!s.isSelect) {
            console.warn(
              '[nu-select2] ROOT CAUSE: field "' + s.name + '" is rendered as <' + s.tag + '> not <select>.',
              'Check the renderer that built this element:',
              '  • FormRenderer.php renderField() for type "select2"',
              '  • nb-form-builder.js live-preview renderer field-type switch',
              'One of them is outputting <input> instead of <select> for this field type.'
            );
          }
        });
        console.groupEnd();
      }

      // Also log all <select> elements found in scope for comparison
      var allSelects = root.querySelectorAll('select');
      dbg('All <select> elements in scope (' + allSelects.length + '):');
      allSelects.forEach(function (el) {
        dbg('  <select>',
            'name=' + (el.name || '?'),
            '| data-select-type=' + (el.getAttribute('data-select-type') || '(none)'),
            '| class=' + el.className,
            '| options count:', el.options.length,
            '| in DOM:', document.contains(el));
      });
    }
    // ── END DIAGNOSTIC BLOCK ──────────────────────────────────────────────

    var $targets = $(root).find(
      'select[data-select-type="select2"], ' +
      'select.nu-select2'
    );

    dbg('nuInitSelect2 called — scope:', root === document ? 'document' : (root.tagName + '#' + (root.id || '?')),
        '| targets matched:', $targets.length);

    if (!$targets.length) {
      if (DEBUG) {
        console.warn(
          '[nu-select2] nuInitSelect2: 0 targets matched.',
          'Selector: select[data-select-type="select2"], select.nu-select2',
          'If select2 fields are showing as <input> elements (see DIAGNOSTIC above),',
          'the renderer is outputting the wrong tag — fix the PHP or JS renderer.'
        );
      }
      return;
    }

    $targets.each(function () {
      var el = this;

      dbg('init [' + (el.name || el.getAttribute('data-field') || '?') + ']',
          '| options count:', el.options.length,
          '| in DOM:', document.contains(el));

      // Hard-nuke any stale/corrupted instance BEFORE Select2's constructor
      // gets a chance to call GetData(el,'select2').destroy() itself.
      nuDestroySelect2(el);

      var placeholder = el.dataset.placeholder || 'Select\u2026';
      var allowClear  = el.dataset.allowClear !== 'false'; // default true
      var isMultiple  = el.dataset.selectMode === 'multiple' || el.hasAttribute('multiple');

      dbg('  placeholder:', placeholder,
          '| allowClear:', allowClear,
          '| multiple:', isMultiple);

      try {
        $(el).select2({
          width:          '100%',
          theme:          (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
          placeholder:    placeholder,
          allowClear:     allowClear,
          multiple:       isMultiple,
          dropdownParent: $(document.body),
        });
        dbg('  select2() init OK ✅', el.name || el.getAttribute('data-field') || '?');
      } catch (initErr) {
        console.error('[nu-select2] select2() init FAILED ❌', initErr.message, el, initErr);
        // Final safety net — clean up so the next attempt starts fresh
        nuDestroySelect2(el);
      }
    });
  }

  // Expose globally so other modules can call nuInitSelect2(containerEl)
  window.nuInitSelect2 = nuInitSelect2;

  /**
   * Atomically destroy + re-initialise Select2 on a single element.
   * Use this when swapping field type between select ↔ select2 in the
   * form builder, or when dynamically changing data-select-mode.
   *
   * @param {Element} el   The <select> element to reinitialise
   */
  function nuReinitSelect2(el) {
    if (!el) return;
    dbg('nuReinitSelect2 called on', el.tagName,
        el.name || el.getAttribute('data-field') || '?');
    nuDestroySelect2(el);
    // Re-init just this one element
    nuInitSelect2(el.parentElement || document);
  }

  window.nuReinitSelect2 = nuReinitSelect2;

  // ── Auto-init hooks ─────────────────────────────────────────────────

  // Re-init whenever a form modal/panel is opened
  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    dbg('nu:form:opened event — will init in 50ms, scope:',
        scope ? (scope.tagName + '#' + (scope.id || '?')) : 'null');
    // Small delay to ensure DOM is fully painted before Select2 measures widths
    setTimeout(function () { nuInitSelect2(scope); }, 50);
  });

  // Init on page load for any select2 fields already in the DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { nuInitSelect2(); });
  } else {
    nuInitSelect2();
  }

}());
