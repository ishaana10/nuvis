window.NuApp = {
  currentModule: 'dashboard',
  _previewModalSize: 'standard', // compact | standard | full

  init() {
    this.bindEvents();
    this.loadTheme();
  },

  bindEvents() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      themeToggle.addEventListener('click', () => this.toggleTheme());
    }
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => this.logout());
    }
    window.addEventListener('hashchange', () => {
      const module = (window.location.hash || '').replace('#', '');
      if (module && module !== this.currentModule) {
        this.loadModule(module);
      }
    });
  },

  async logout() {
    try {
      await fetch('api/auth.php?action=logout', { method: 'POST', credentials: 'same-origin' });
    } catch (e) {}
    window.location.reload();
  },

  loadTheme() {
    let saved = 'auto';
    try { saved = localStorage.getItem('nu-theme') || 'auto'; } catch (e) {}
    document.documentElement.setAttribute('data-theme', saved);
  },

  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'light' ? 'dark' : current === 'dark' ? 'auto' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('nu-theme', next); } catch (e) {}
  },

  setActiveNavByModule(module) {
    document.querySelectorAll('.nu-nav-item').forEach((item) => item.classList.remove('active'));
    const active = document.querySelector('.nu-nav-item[data-module="' + module + '"]');
    if (active) active.classList.add('active');
  },

  toast(message, type) {
    let container = document.querySelector('.nu-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'nu-toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'nu-toast ' + (type || 'success');
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 4000);
  },

  _execModuleScripts(container) {
    container.querySelectorAll('script').forEach(function (oldScript) {
      const s = document.createElement('script');
      Array.from(oldScript.attributes).forEach(function (attr) {
        s.setAttribute(attr.name, attr.value);
      });
      s.textContent = oldScript.textContent;
      oldScript.parentNode.replaceChild(s, oldScript);
    });
  },

  async loadModule(module) {
    this._exitFullPage();
    this.currentModule = module;
    const container = document.getElementById('contentArea');
    const pageTitle = document.getElementById('pageTitle');
    if (!container) { console.error('contentArea not found'); return; }
    if (pageTitle) {
      pageTitle.textContent = module.charAt(0).toUpperCase() + module.slice(1);
    }
    this.setActiveNavByModule(module);
    container.innerHTML = '<div class="nu-spinner" style="margin:40px auto;"></div>';
    try {
      const res = await fetch('modules/' + module + '/' + module + '.php', { credentials: 'same-origin' });
      const html = await res.text();
      if (!res.ok) {
        container.innerHTML =
          '<div style="padding:24px;border:2px solid red;background:#fee;">' +
          '<h3>Module load failed</h3><p>Status: ' + res.status + '</p>' +
          '<pre style="font-size:12px;overflow:auto;">' + html.substring(0, 2000) + '</pre></div>';
        return;
      }
      container.innerHTML = html;
      container.style.display = 'block';
      container.style.visibility = 'visible';
      container.style.opacity = '1';
      this._execModuleScripts(container);
      this.initModuleScripts(module);
    } catch (err) {
      console.error('loadModule error', err);
      container.innerHTML =
        '<div style="padding:24px;border:2px solid red;background:#fee;">' +
        '<h3>Error</h3><p>' + String(err.message || err) + '</p></div>';
    }
  },

  initModuleScripts(module) {
    if (module === 'forms') {
      if (window.nbFormBuilder && typeof window.nbFormBuilder._initAfterLoad === 'function') {
        window.nbFormBuilder._initAfterLoad();
      }
    }
  },

  async apiJson(url, options) {
    const res = await fetch(url, options || {});
    const text = await res.text();
    console.log('api raw response:', url, text);
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response'); }
    return json;
  },

  // ─── PATCH 2 helper: stamp parent_id onto subform containers ────────────────
  _stampSubformParentId(box, recordId) {
    if (!recordId) return;
    box.querySelectorAll('.nu-subform-container').forEach(function (el) {
      el.dataset.parentId = String(recordId);
    });
  },

  _dispatchFormOpened(box) {
    if (window.nuSubform && typeof window.nuSubform.initAll === 'function') {
      window.nuSubform.initAll(box);
    }
    document.dispatchEvent(new CustomEvent('nu:form:opened', { detail: { scope: box } }));
  },

  // ─── BREADCRUMB HELPER ───────────────────────────────────────────────────────
  _renderBreadcrumb(crumbs) {
    const nav = document.createElement('nav');
    nav.setAttribute('aria-label', 'breadcrumb');
    nav.style.cssText = 'margin-bottom:14px;display:flex;align-items:center;flex-wrap:wrap;gap:4px;font-size:13px;';
    crumbs.forEach((crumb, i) => {
      if (i > 0) {
        const sep = document.createElement('span');
        sep.textContent = '/';
        sep.style.cssText = 'color:var(--text-muted,#999);margin:0 2px;';
        nav.appendChild(sep);
      }
      if (crumb.action && i < crumbs.length - 1) {
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = crumb.label;
        a.style.cssText = 'color:var(--primary,#4f6bed);text-decoration:none;font-weight:500;';
        a.addEventListener('mouseenter', () => a.style.textDecoration = 'underline');
        a.addEventListener('mouseleave', () => a.style.textDecoration = 'none');
        a.addEventListener('click', (e) => { e.preventDefault(); crumb.action(); });
        nav.appendChild(a);
      } else {
        const span = document.createElement('span');
        span.textContent = crumb.label;
        span.style.cssText = 'color:var(--text-muted,#666);font-weight:400;';
        nav.appendChild(span);
      }
    });
    return nav;
  },

  // ─── FULL-PAGE MODE HELPERS ──────────────────────────────────────────────────
  _enterFullPage() {
    const sidebar = document.querySelector('.nu-sidebar, #sidebar, [class*="sidebar"]');
    const header  = document.querySelector('.nu-header, #header, header');
    const main    = document.getElementById('contentArea');
    if (sidebar) { sidebar.dataset.nuFpHidden = sidebar.style.display; sidebar.style.display = 'none'; }
    if (header)  { header.dataset.nuFpHidden  = header.style.display;  header.style.display  = 'none'; }
    if (main) {
      main.dataset.nuFpStyle = main.getAttribute('style') || '';
      main.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;overflow-y:auto;padding:24px;background:var(--bg-page,#f5f6fa);';
    }
    document.body.dataset.nuFullPage = '1';
  },

  _exitFullPage() {
    if (!document.body.dataset.nuFullPage) return;
    const sidebar = document.querySelector('.nu-sidebar, #sidebar, [class*="sidebar"]');
    const header  = document.querySelector('.nu-header, #header, header');
    const main    = document.getElementById('contentArea');
    if (sidebar && sidebar.dataset.nuFpHidden !== undefined) { sidebar.style.display = sidebar.dataset.nuFpHidden; delete sidebar.dataset.nuFpHidden; }
    if (header  && header.dataset.nuFpHidden  !== undefined) { header.style.display  = header.dataset.nuFpHidden;  delete header.dataset.nuFpHidden;  }
    if (main    && main.dataset.nuFpStyle     !== undefined) { main.setAttribute('style', main.dataset.nuFpStyle);  delete main.dataset.nuFpStyle;     }
    delete document.body.dataset.nuFullPage;
  },

  // ─── PREVIEW FORM — resizable modal (compact / standard / full) ──────────────
  async previewForm(code, formLabel) {
    try {
      const json = await this.apiJson(
        'api/form.php?action=render&code=' + encodeURIComponent(code),
        { credentials: 'same-origin' }
      );
      if (!json.success) { this.toast(json.error || 'Preview failed', 'error'); return; }

      const label = formLabel || code;
      const sizes = {
        compact:  { maxWidth: '560px',  maxHeight: '80vh' },
        standard: { maxWidth: '900px',  maxHeight: '90vh' },
        full:     { maxWidth: '98vw',   maxHeight: '96vh' },
      };
      let currentSize = this._previewModalSize || 'standard';

      const overlay = document.createElement('div');
      overlay.className = 'nu-form-overlay';
      overlay.style.cssText =
        'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;';

      function applySize() {
        const s = sizes[currentSize] || sizes.standard;
        modal.style.maxWidth  = s.maxWidth;
        modal.style.maxHeight = s.maxHeight;
        ['compact','standard','full'].forEach(function (k) {
          const btn = overlay.querySelector('.nu-resize-btn[data-size="' + k + '"]');
          if (btn) btn.style.fontWeight = (k === currentSize) ? '700' : '400';
        });
      }

      const modal = document.createElement('div');
      modal.className = 'nu-modal';
      modal.style.cssText =
        'background:var(--bg-card,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.25);'
        + 'width:92vw;overflow:hidden;display:flex;flex-direction:column;transition:max-width .2s,max-height .2s;';

      const header = document.createElement('div');
      header.style.cssText =
        'display:flex;align-items:center;justify-content:space-between;padding:14px 18px;'
        + 'border-bottom:1px solid var(--border,#e0e0e0);flex-shrink:0;';
      header.innerHTML =
        '<span style="font-weight:600;font-size:15px;">' + label + '</span>'
        + '<span style="display:flex;align-items:center;gap:8px;">'
        + '<span style="font-size:12px;color:var(--text-muted,#888);">Size:</span>'
        + ['compact','standard','full'].map(function (k) {
            return '<button class="nu-btn nu-btn-ghost nu-btn-sm nu-resize-btn" data-size="' + k + '" style="padding:2px 8px;font-size:12px;">' + k + '</button>';
          }).join('')
        + '<button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="this.closest(\'.nu-form-overlay\').remove();" style="font-size:18px;line-height:1;padding:2px 8px;">&#x2715;</button>'
        + '</span>';

      header.querySelectorAll('.nu-resize-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          currentSize = btn.dataset.size;
          NuApp._previewModalSize = currentSize;
          applySize();
        });
      });

      const body = document.createElement('div');
      body.style.cssText = 'padding:20px;overflow-y:auto;flex:1;';
      body.innerHTML = json.html;

      modal.appendChild(header);
      modal.appendChild(body);
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      applySize();

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) overlay.remove();
      });

      NuApp._execModuleScripts(body);
      NuApp._dispatchFormOpened(body);

    } catch (err) {
      this.toast('Preview error: ' + err.message, 'error');
    }
  }
};


/* ═══════════════════════════════════════════════════════════════════════════════
   LOOKUP FIELD HELPERS
   Called by api/form-handler.php renderField() for type='lookup':
     openLookupModal(fieldName, table, idCol, displayCol, filter)
     clearLookup(fieldName)
═══════════════════════════════════════════════════════════════════════════════ */

/**
 * Opens a search-and-select modal for a lookup field.
 *
 * @param {string} fieldName   - name attribute of the hidden input storing the value
 * @param {string} table       - DB table to search (from field_lookup_table)
 * @param {string} idCol       - column used as the stored value (e.g. 'id')
 * @param {string} displayCol  - column shown to the user (e.g. 'name')
 * @param {string} filter      - optional extra WHERE clause fragment (may be empty)
 */
window.openLookupModal = function (fieldName, table, idCol, displayCol, filter) {
  if (!table) {
    NuApp.toast('Lookup table not configured for this field.', 'error');
    return;
  }

  var overlay = document.createElement('div');
  overlay.className = 'nu-modal-overlay';
  overlay.style.cssText =
    'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);'
    + 'z-index:20000;display:flex;align-items:center;justify-content:center;';

  var modal = document.createElement('div');
  modal.style.cssText =
    'background:var(--bg-card,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.25);'
    + 'width:min(640px,94vw);max-height:80vh;display:flex;flex-direction:column;overflow:hidden;';

  modal.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;'
    + 'border-bottom:1px solid var(--border,#e0e0e0);flex-shrink:0;">'
    + '<span style="font-weight:600;font-size:14px;">Select ' + _escLookup(displayCol || table) + '</span>'
    + '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" style="font-size:18px;line-height:1;padding:2px 8px;">&#x2715;</button>'
    + '</div>'
    + '<div style="padding:12px 18px;border-bottom:1px solid var(--border,#e0e0e0);flex-shrink:0;">'
    + '<input type="text" class="nu-input nu-lookup-search" placeholder="Search\u2026" style="width:100%;">'
    + '</div>'
    + '<div class="nu-lookup-results" style="overflow-y:auto;flex:1;padding:8px 0;">'
    + '<div style="padding:20px;text-align:center;color:var(--text-muted,#999);">Type to search\u2026</div>'
    + '</div>';

  modal.querySelector('button').addEventListener('click', function () { overlay.remove(); });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });

  var searchInput  = modal.querySelector('.nu-lookup-search');
  var resultsBox   = modal.querySelector('.nu-lookup-results');
  var searchTimer  = null;

  function _doSearch(q) {
    resultsBox.innerHTML = '<div style="padding:20px;text-align:center;"><span class="nu-spinner" style="width:24px;height:24px;"></span></div>';
    var url = 'api/lookup.php?table=' + encodeURIComponent(table)
            + '&id_col=' + encodeURIComponent(idCol || 'id')
            + '&display_col=' + encodeURIComponent(displayCol || 'name')
            + '&q=' + encodeURIComponent(q || '')
            + (filter ? '&filter=' + encodeURIComponent(filter) : '');
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.success || !json.rows || !json.rows.length) {
          resultsBox.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted,#999);">No results found.</div>';
          return;
        }
        resultsBox.innerHTML = '';
        json.rows.forEach(function (row) {
          var item = document.createElement('div');
          item.style.cssText = 'padding:10px 18px;cursor:pointer;border-bottom:1px solid var(--border,#f0f0f0);font-size:14px;';
          item.textContent = row.display;
          item.addEventListener('mouseenter', function () { item.style.background = 'var(--bg-elevated,#f8f9fa)'; });
          item.addEventListener('mouseleave', function () { item.style.background = ''; });
          item.addEventListener('click', function () {
            _applyLookupValue(fieldName, row.id, row.display);
            overlay.remove();
          });
          resultsBox.appendChild(item);
        });
      })
      .catch(function () {
        resultsBox.innerHTML = '<div style="padding:16px;text-align:center;color:var(--danger,#e53e3e);">Error loading results.</div>';
      });
  }

  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { _doSearch(searchInput.value); }, 280);
  });

  // Load first page immediately on open
  _doSearch('');

  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  setTimeout(function () { searchInput.focus(); }, 80);
};

/**
 * Writes the selected id+display back into the lookup field pair.
 * Fires a native 'change' event so any onchange handlers fire.
 */
function _applyLookupValue(fieldName, id, display) {
  // hidden input (stores the id)
  var hidden = document.querySelector('input[type="hidden"][name="' + fieldName + '"], input[type="hidden"][data-field="' + fieldName + '"]');
  if (hidden) {
    hidden.value = id;
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
  }
  // visible read-only text input (shows display value)
  var visible = document.querySelector('input[readonly][data-field="' + fieldName + '"], .nu-field-wrapper[data-field="' + fieldName + '"] input[readonly]');
  if (!visible && hidden) {
    // fallback: next sibling text input in same wrapper
    var wrapper = hidden.closest('.nu-field-wrapper, div');
    if (wrapper) visible = wrapper.querySelector('input[type="text"][readonly]');
  }
  if (visible) visible.value = display;
}

/**
 * Clears the lookup field (both hidden value and visible display).
 */
window.clearLookup = function (fieldName) {
  _applyLookupValue(fieldName, '', '');
};

/** XSS-safe escape for strings injected into modal HTML */
function _escLookup(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
