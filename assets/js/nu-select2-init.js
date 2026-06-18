/**
 * nu-select2-init.js
 *
 * APPROACH: Option A — select and select2 are separate element types.
 *
 * FormRenderer.php emits:
 *   - <select class="nu-input">            for type="select"  (plain, never touched here)
 *   - <select class="nu-input nu-select2"> for type="select2" (always initialised below)
 *
 * Because .nu-select2 elements are always freshly rendered before this
 * runs, there is never a pre-existing Select2 instance on them.
 * The :not([data-select2-id]) guard is a safety net — Select2 stamps
 * data-select2-id on every element it owns, so a second call is a no-op.
 *
 * No destroy, no try/catch, no $.removeData needed.
 * Eliminates the 'r.GetData(...).destroy is not a function' errors entirely.
 */
(function () {
  'use strict';

  /**
   * Initialise all un-initialised .nu-select2 elements within `scope`.
   * @param {Element|null} scope  Container to search in (default: document)
   */
  function nuInitSelect2(scope) {
    var hasJQ      = typeof jQuery !== 'undefined';
    var hasSelect2 = hasJQ && typeof jQuery.fn.select2 !== 'undefined';

    if (!hasJQ || !hasSelect2) {
      console.warn('[nuInitSelect2] jQuery or Select2 not available — aborting');
      return;
    }

    var $  = jQuery;
    var root = scope instanceof Element ? scope : document;

    // Target only .nu-select2 elements that have NOT yet been initialised.
    // Select2 stamps data-select2-id on every element it owns, so
    // :not([data-select2-id]) guarantees we never double-init.
    var $targets = $(root).find('select.nu-select2:not([data-select2-id])');

    if (!$targets.length) return;

    $targets.each(function () {
      var el          = this;
      var placeholder = el.dataset.placeholder || 'Select…';
      var allowClear  = el.dataset.allowClear === 'true';

      $(el).select2({
        width:          '100%',
        theme:          (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
        placeholder:    placeholder,
        allowClear:     allowClear,
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
