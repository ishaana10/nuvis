/**
 * nu-select2-init.js
 * Initialises Select2 on any <select data-select2="1"> inside a given scope.
 *
 * ROOT CAUSE: When formWrap.innerHTML is replaced, the old DOM nodes are
 * discarded but jQuery's internal $.data() store still holds a stale Select2
 * instance keyed to those nodes. When the new HTML is inserted the browser
 * may reuse the same node reference, so Select2's constructor finds the stale
 * data and crashes with "GetData(...).destroy is not a function".
 *
 * FIX: Call $.removeData on each <select> to wipe any orphaned jQuery data
 * before calling .select2(), ensuring a completely clean init every time.
 */
(function () {

  function nuInitSelect2(scope) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
    var $ = jQuery;
    var root = scope || document;

    $(root).find('select[data-select2="1"]').each(function () {
      var $el = $(this);

      // 1. If Select2 is already attached, destroy it cleanly.
      if ($el.hasClass('select2-hidden-accessible')) {
        try { $el.select2('destroy'); } catch (e) {}
      }

      // 2. Wipe ALL jQuery internal data on this element to clear any
      //    orphaned Select2 instance left over from a previous innerHTML swap.
      try { $el.removeData(); } catch (e) {}

      // 3. Fresh init — dropdownParent:body escapes modal overflow clipping.
      var opts = {
        width: '100%',
        theme: 'default',
        dropdownParent: $(document.body)
      };
      var blank = this.options[0];
      if (blank && blank.value === '') {
        opts.placeholder = blank.textContent || blank.innerText || 'Select…';
        opts.allowClear  = true;
      }
      $el.select2(opts);
    });
  }

  window.nuInitSelect2 = nuInitSelect2;

  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    setTimeout(function () { nuInitSelect2(scope); }, 0);
  });

})();
