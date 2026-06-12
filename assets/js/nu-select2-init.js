/**
 * nu-select2-init.js
 * Initialises Select2 on any <select data-select2="1"> inside a given scope.
 * Called after every form open (preview, edit, add).
 */
(function () {

  function nuInitSelect2(scope) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
    var $ = jQuery;
    var root = scope || document;
    $(root).find('select[data-select2="1"]').each(function () {
      if ($(this).data('select2')) return; // already inited
      var opts = {
        width: '100%',
        theme: 'default',
        dropdownParent: $(this).closest('.nu-form-overlay, .nu-app, body')
      };
      // honour placeholder from the blank first <option>
      var blank = this.options[0];
      if (blank && blank.value === '') {
        opts.placeholder = blank.textContent || blank.innerText || 'Select…';
        opts.allowClear  = true;
      }
      $(this).select2(opts);
    });
  }

  // expose globally so nubuilder-next.js can call it
  window.nuInitSelect2 = nuInitSelect2;

  // Re-init on the custom event fired by _dispatchFormOpened.
  // Defer one tick so the overlay is guaranteed to be in the live DOM
  // before Select2 tries to measure element dimensions.
  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    setTimeout(function () { nuInitSelect2(scope); }, 0);
  });

})();
