/**
 * nu-select2-init.js
 *
 * ROOT CAUSE (confirmed by console logs):
 *   Select2 stores its instance via jQuery.data(el, 'select2').
 *   When a previous init cycle fails or is interrupted, it leaves a
 *   corrupted/partial object in jQuery's data cache. Our try/catch
 *   on $(el).select2('destroy') swallows the error but does NOT clear
 *   the stale jQuery data. So when we call $(el).select2(opts) fresh,
 *   Select2's own constructor runs:
 *       if (GetData(el, "select2")) GetData(el, "select2").destroy()
 *   ...finds the stale object, and crashes:
 *       "r.GetData(...).destroy is not a function"
 *
 * FIX:
 *   After the try/catch destroy (whether it succeeds or throws), always
 *   forcibly wipe the jQuery data entry with $.removeData(el, 'select2')
 *   AND remove the data-select2-id attribute. This guarantees Select2's
 *   constructor finds nothing and constructs a clean new instance.
 */
(function () {
  'use strict';

  function nuInitSelect2(scope) {
    var hasJQ      = typeof jQuery !== 'undefined';
    var hasSelect2 = hasJQ && typeof jQuery.fn.select2 !== 'undefined';

    console.group('[nuInitSelect2] called');
    console.log('  scope   :', scope || document);
    console.log('  jQuery  :', hasJQ      ? 'YES v' + (jQuery.fn.jquery || '?') : 'MISSING');
    console.log('  Select2 :', hasSelect2 ? 'YES' : 'MISSING — aborting');

    if (!hasJQ || !hasSelect2) { console.groupEnd(); return; }

    var $ = jQuery;
    var root = scope || document;
    var $selects = $(root).find('select[data-select2="1"]');

    console.log('  selects found:', $selects.length);

    $selects.each(function (i) {
      var el = this;
      console.group('  [' + i + '] <select name="' + el.name + '">');

      // Step 1: graceful destroy — catch any error
      try {
        if ($(el).data('select2')) {
          console.log('    existing instance found — destroying');
          $(el).select2('destroy');
          console.log('    destroy OK');
        } else {
          console.log('    no existing instance');
        }
      } catch (e) {
        console.warn('    destroy threw (ignored):', e.message);
      }

      // Step 2: ALWAYS force-wipe jQuery data + DOM attribute after destroy
      // (even if destroy threw — the stale data is still there)
      try { $.removeData(el, 'select2'); } catch (e) {}
      el.removeAttribute('data-select2-id');
      var staleOpts = el.querySelectorAll('[data-select2-id]');
      for (var j = 0; j < staleOpts.length; j++) {
        staleOpts[j].removeAttribute('data-select2-id');
      }
      console.log('    jQuery data + data-select2-id cleared');

      // Step 3: build options
      var s2opts = {
        width:          '100%',
        theme:          'default',
        dropdownParent: $(document.body),
      };
      var blank = el.options[0];
      if (blank && blank.value === '') {
        s2opts.placeholder = blank.textContent.trim() || 'Select…';
        s2opts.allowClear  = true;
        console.log('    placeholder:', s2opts.placeholder);
      }

      // Step 4: fresh init
      try {
        $(el).select2(s2opts);
        console.log('    select2() init OK ✓');
      } catch (initErr) {
        console.error('    select2() init FAILED:', initErr.message, initErr);
      }

      console.groupEnd();
    });

    console.groupEnd();
  }

  window.nuInitSelect2 = nuInitSelect2;

  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    console.log('[nuInitSelect2] nu:form:opened — scope:', scope || document);
    setTimeout(function () { nuInitSelect2(scope); }, 50);
  });

})();
