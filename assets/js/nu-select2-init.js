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
 *   After a failed destroy we clear stale jQuery data with $.removeData
 *   before re-initialising, preventing Select2's constructor from hitting
 *   the corrupted object a second time.
 */
(function () {
  'use strict';

  /**
   * Initialise all uninitialised Select2 fields within `scope`.
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

    // Match both the new data-select-type="select2" pattern and the legacy .nu-select2 class.
    // :not([data-select2-id]) skips elements already owned by Select2.
    var $targets = $(root).find(
      'select[data-select-type="select2"]:not([data-select2-id]), ' +
      'select.nu-select2:not([data-select2-id])'
    );

    if (!$targets.length) return;

    $targets.each(function () {
      var el = this;

      // Guard: if a stale (non-functional) Select2 object is in jQuery data,
      // clear it before re-initialising to prevent the
      // "r.GetData(...).destroy is not a function" crash.
      if ($.data(el, 'select2')) {
        try {
          $(el).select2('destroy');
        } catch (e) {
          // destroy threw — manually clear the corrupted reference
          $.removeData(el, 'select2');
        }
        // Always remove data after destroy attempt so the constructor
        // doesn't encounter a stale object on the first $.data() read.
        $.removeData(el, 'select2');
      }

      var placeholder = el.dataset.placeholder || 'Select\u2026';
      var allowClear  = el.dataset.allowClear !== 'false'; // default true
      var isMultiple  = el.dataset.selectMode === 'multiple' || el.hasAttribute('multiple');

      $(el).select2({
        width:          '100%',
        theme:          (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
        placeholder:    placeholder,
        allowClear:     allowClear,
        multiple:       isMultiple,
        dropdownParent: $(document.body),
      });
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
