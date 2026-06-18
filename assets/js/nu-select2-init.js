/**
 * nu-select2-init.js
 *
 * Initialises Select2 on any <select> that has data-select-type="select2".
 * The renderer emits data-select-mode="single|multiple" which controls
 * whether the element is a single or multi-value Select2.
 *
 * Also handles the legacy .nu-select2 class for backwards compatibility.
 *
 * Fix for "r.GetData(...).destroy is not a function":
 *   1. The :not([data-select2-id]) guard is intentionally NOT used in the
 *      selector — after a failed init Select2 still stamps data-select2-id
 *      onto the element, so the guard would permanently skip broken selects.
 *   2. We detect a live instance via $.data(el,'select2') AND check that
 *      .destroy is actually a function before calling it.
 *   3. After any destroy attempt (successful or not) we always call
 *      $.removeData to flush any stale/corrupted object so Select2's
 *      constructor never hits a broken reference on the first $.data() read.
 */
(function () {
  'use strict';

  /**
   * Initialise (or re-initialise) all Select2 fields within `scope`.
   * @param {Element|null} scope  Container to search in (default: document)
   */
  function nuInitSelect2(scope) {
    var hasJQ      = typeof jQuery !== 'undefined';
    var hasSelect2 = hasJQ && typeof jQuery.fn.select2 !== 'undefined';

    if (!hasJQ || !hasSelect2) {
      console.warn('[nuInitSelect2] jQuery or Select2 not available — aborting');
      return;
    }

    var $    = jQuery;
    var root = scope instanceof Element ? scope : document;

    // Do NOT use :not([data-select2-id]) here.
    // A failed Select2 init still writes data-select2-id, so that guard would
    // permanently skip elements that never successfully initialised.
    var $targets = $(root).find(
      'select[data-select-type="select2"], ' +
      'select.nu-select2'
    );

    if (!$targets.length) return;

    $targets.each(function () {
      var el = this;
      var existing = $.data(el, 'select2');

      if (existing) {
        // Only call destroy if it's a real, functional Select2 instance.
        if (typeof existing.destroy === 'function') {
          try { $(el).select2('destroy'); } catch (e) { /* ignore */ }
        }
        // Always flush stale/corrupted data so the constructor starts clean.
        $.removeData(el, 'select2');
        // Also remove the id attribute stamp left by a failed init.
        el.removeAttribute('data-select2-id');
      } else {
        // No $.data entry, but element might still have a stale attribute
        // stamp from a previous failed init — clean that up too.
        if (el.hasAttribute('data-select2-id')) {
          el.removeAttribute('data-select2-id');
        }
      }

      var placeholder = el.dataset.placeholder || 'Select\u2026';
      var allowClear  = el.dataset.allowClear !== 'false'; // default true
      var isMultiple  = el.dataset.selectMode === 'multiple' || el.hasAttribute('multiple');

      try {
        $(el).select2({
          width:          '100%',
          theme:          (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
          placeholder:    placeholder,
          allowClear:     allowClear,
          multiple:       isMultiple,
          dropdownParent: $(document.body),
        });
      } catch (initErr) {
        console.warn('[nuInitSelect2] select2() init failed on element:', el, initErr);
        // Final safety net: clear any partial state left by the failed init.
        $.removeData(el, 'select2');
        el.removeAttribute('data-select2-id');
      }
    });
  }

  // Expose globally so other modules can call nuInitSelect2(containerEl)
  window.nuInitSelect2 = nuInitSelect2;

  // Auto-init on form open events
  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    // Small delay to ensure DOM is fully painted before Select2 measures widths
    setTimeout(function () { nuInitSelect2(scope); }, 50);
  });

  // Auto-init on DOMContentLoaded for any select2 fields present at page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { nuInitSelect2(); });
  } else {
    nuInitSelect2();
  }

})();
