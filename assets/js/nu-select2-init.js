/**
 * nu-select2-init.js
 * Initialises Select2 on any <select data-select2="1"> inside a given scope.
 *
 * ROOT CAUSE: Select2 stores its instance using a private internal key
 * (not standard jQuery .data()), so .removeData() and even .select2('destroy')
 * do not fully clean up when the element was previously part of a discarded
 * innerHTML tree. Select2's constructor then finds orphaned internal state
 * and crashes: "r.GetData(...).destroy is not a function".
 *
 * FIX: Replace the <select> with a fresh clone before calling .select2().
 * cloneNode(true) copies the element and its children (options) but carries
 * NO jQuery data whatsoever, so Select2 always starts from a blank slate.
 */
(function () {

  function nuInitSelect2(scope) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
    var $ = jQuery;
    var root = scope || document;

    $(root).find('select[data-select2="1"]').each(function () {
      var original = this;

      // Replace with a pristine clone — no jQuery data, no Select2 internals.
      var clone = original.cloneNode(true);
      original.parentNode.replaceChild(clone, original);

      var $el = $(clone);
      var opts = {
        width: '100%',
        theme: 'default',
        dropdownParent: $(document.body)
      };
      var blank = clone.options[0];
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
