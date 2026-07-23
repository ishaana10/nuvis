// ─── Select2 helpers ─────────────────────────────────────────────────────────
(function () {
  'use strict';

  function _s2BuildFresh(el) {
    var fresh = document.createElement('select');
    var skip = { 'data-select2-id': 1, 'data-nu-s2': 1 };
    for (var i = 0; i < el.attributes.length; i++) {
      var attr = el.attributes[i];
      if (!skip[attr.name]) fresh.setAttribute(attr.name, attr.value);
    }
    for (var j = 0; j < el.childNodes.length; j++) {
      fresh.appendChild(el.childNodes[j].cloneNode(true));
    }
    var next = el.nextElementSibling;
    while (next && next.classList &&
           (next.classList.contains('select2') || next.classList.contains('select2-container'))) {
      var rem = next; next = next.nextElementSibling; rem.parentNode.removeChild(rem);
    }
    el.parentNode.replaceChild(fresh, el);
    return fresh;
  }

  function _s2InitOne(el) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
    var $ = jQuery;
    var fresh = _s2BuildFresh(el);
    var opts = {
      width: '100%',
      theme: (window.nuUXOptions && window.nuUXOptions.nuSelect2Theme) || 'default',
      placeholder: fresh.dataset.placeholder || 'Select\u2026',
      allowClear:  fresh.dataset.allowClear !== 'false',
      multiple:    fresh.dataset.selectMode === 'multiple' || fresh.hasAttribute('multiple'),
      dropdownParent: $(document.body),
    };
    try {
      $(fresh).select2(opts);
      fresh.setAttribute('data-nu-s2', '1');
    } catch (err) {
      console.error('[nu-select2] init FAILED', err.message, fresh);
    }
  }

  function nuInitSelect2(scope) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
    var root = (scope instanceof Element) ? scope : document;
    var targets = Array.prototype.slice.call(
      root.querySelectorAll('select[data-select-type="select2"], select.nu-select2')
    );
    targets.forEach(function (el) { _s2InitOne(el); });
  }

  window.nuInitSelect2    = nuInitSelect2;
  window.nuDestroySelect2 = function (el) { _s2BuildFresh(el); };
  window.nuReinitSelect2  = function (el) { _s2InitOne(el); };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { nuInitSelect2(document); });
  } else {
    nuInitSelect2(document);
  }

  document.addEventListener('nu:form:opened', function (e) {
    var scope = e.detail && e.detail.scope;
    if (scope && scope.dataset && scope.dataset.nuS2Done) return;
    nuInitSelect2(scope);
  });

}());


// ─── Permission helpers ───────────────────────────────────────────────────────
window.NuPerms = {
  _editRoles: new Set(['globeadmin', 'admin']),

  canEdit() {
    const role = (window.nuUserRole || '').toLowerCase();
    if (this._editRoles.has(role)) return true;
    return !!(window.nuUserPerms && window.nuUserPerms.canEdit);
  },

  canAdd() {
    const role = (window.nuUserRole || '').toLowerCase();
    if (this._editRoles.has(role)) return true;
    return !!(window.nuUserPerms && window.nuUserPerms.canAdd);
  },

  canDelete() {
    const role = (window.nuUserRole || '').toLowerCase();
    if (this._editRoles.has(role)) return true;
    return !!(window.nuUserPerms && window.nuUserPerms.canDelete);
  }
};


// ─── NuApp ────────────────────────────────────────────────────────────────────
window.NuApp = {
  currentModule: 'dashboard',
  _previewModalSize: 'standard',

  _systemModules: new Set([
    'dashboard','forms','reports','queries','calendar','ai','integrations',
    'menus','users','roles','audit','files','workflow','inspector','errorlog',
    'password_policy','appcloner','password','report_dashboards','email_settings',
    'updater','import_export'
  ]),

  init() {
    this.bindEvents();
    this.loadTheme();
    this.initNavGroups();
  },

  initNavGroups() {
    document.querySelectorAll('.nu-nav-group').forEach((group) => {
      const btn = group.querySelector('.nu-nav-group-label');
      const ul  = group.querySelector('.nu-nav-children');
      if (!btn || !ul) return;
      ul.classList.add('nu-nav-children--collapsed');
      btn.setAttribute('aria-expanded', 'false');
    });
  },

  bindEvents() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) themeToggle.addEventListener('click', () => this.toggleTheme());
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', () => this.logout());

    const nav = document.querySelector('.nu-nav');
    if (nav) {
      nav.addEventListener('click', (e) => {
        const btn = e.target.closest('.nu-nav-group-label');
        if (!btn) return;
        const group = btn.closest('.nu-nav-group');
        if (!group) return;
        const ul = group.querySelector('.nu-nav-children');
        if (!ul) return;
        const isOpen = btn.getAttribute('aria-expanded') !== 'false';
        btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        ul.classList.toggle('nu-nav-children--collapsed', isOpen);
      });
    }

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.nu-nav-form-item')) {
        document.querySelectorAll('.nu-nav-form-dropdown').forEach(d => d.classList.remove('open'));
      }
    });
  },

  async logout() {
    try { await fetch('api/auth.php?action=logout', { method: 'POST', credentials: 'same-origin' }); } catch (e) {}
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
    if (active) {
      active.classList.add('active');
      const group = active.closest('.nu-nav-group');
      if (group) {
        const ul  = group.querySelector('.nu-nav-children');
        const btn = group.querySelector('.nu-nav-group-label');
        if (ul)  ul.classList.remove('nu-nav-children--collapsed');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      }
    }
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
    setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 4000);
  },

  _execModuleScripts(container) {
    container.querySelectorAll('script').forEach(function (oldScript) {
      const s = document.createElement('script');
      Array.from(oldScript.attributes).forEach(function (attr) { s.setAttribute(attr.name, attr.value); });
      s.textContent = oldScript.textContent;
      oldScript.parentNode.replaceChild(s, oldScript);
    });
  },

  _initFormWidgets(scope) {
    if (scope) scope.dataset.nuS2Done = '1';
    if (typeof window.nuInitSelect2 === 'function') window.nuInitSelect2(scope);
  },

  _dispatchFormOpened(box) {
    if (window.nuSubform && typeof window.nuSubform.initAll === 'function') {
      window.nuSubform.initAll(box);
    }
    document.dispatchEvent(new CustomEvent('nu:form:opened', { detail: { scope: box } }));
  },

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

  async loadModule(module, defaultView, browseMode, previewMode) {
    module      = (module      || 'dashboard').trim();
    defaultView = ['browse','preview'].includes(defaultView) ? defaultView : 'browse';
    browseMode  = ['inline','popup'].includes(browseMode)    ? browseMode  : 'inline';
    previewMode = ['inline','popup'].includes(previewMode)   ? previewMode : 'inline';

    this._exitFullPage();
    this.currentModule = module;

    const container = document.getElementById('contentArea');
    const pageTitle  = document.getElementById('pageTitle');
    if (!container) { console.error('contentArea not found'); return; }
    if (pageTitle) pageTitle.textContent = module.charAt(0).toUpperCase() + module.slice(1);
    this.setActiveNavByModule(module);

    if (this._systemModules.has(module)) {
      container.innerHTML = '<div class="nu-spinner" style="margin:40px auto;"></div>';
      try {
        const res  = await fetch('modules/' + module + '/' + module + '.php?_t=' + Date.now(), { credentials: 'same-origin' });
        const html = await res.text();
        if (!res.ok) {
          container.innerHTML = '<div style="padding:24px;border:2px solid red;background:#fee;"><h3>Module load failed</h3><p>Status: ' + res.status + '</p><pre style="font-size:12px;overflow:auto;">' + html.substring(0, 2000) + '</pre></div>';
          return;
        }
        container.innerHTML = html;
        container.style.display    = 'block';
        container.style.visibility = 'visible';
        container.style.opacity    = '1';
        this._execModuleScripts(container);
        this.initModuleScripts(module);
      } catch (err) {
        console.error('loadModule error', err);
        container.innerHTML = '<div style="padding:24px;border:2px solid red;background:#fee;"><h3>Error</h3><p>' + String(err.message || err) + '</p></div>';
      }
      return;
    }

    if (defaultView === 'preview') {
      return previewMode === 'popup'
        ? this.previewForm(module, module, 'modal')
        : this._openFormInline(module, module, null, true);
    } else {
      return browseMode === 'popup'
        ? this._browseModal(module, 1, '', module)
        : this._browseInline(module, 1, '', module);
    }
  },

  initModuleScripts(module) {
    if (module === 'forms' && window.nbFormBuilder && typeof window.nbFormBuilder._initAfterLoad === 'function') {
      window.nbFormBuilder._initAfterLoad();
    }
  },

  async apiJson(url, options) {
    const res  = await fetch(url, options || {});
    const text = await res.text();
    console.log('api raw response:', url, text);
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response'); }
    return json;
  },

  async previewForm(code, formLabel, displayMode) {
    const mode = (displayMode || 'modal').toLowerCase();
    if (mode === 'inline')   return this._openFormInline(code, formLabel, null, true);
    if (mode === 'fullpage') return this._openFormFullPage(code, formLabel, null, true);

    try {
      const json = await this.apiJson('api/form.php?action=render&code=' + encodeURIComponent(code), { credentials: 'same-origin' });
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
      overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;width:92%;overflow-y:auto;transition:max-width 0.2s,max-height 0.2s;max-width:' + sizes[currentSize].maxWidth + ';max-height:' + sizes[currentSize].maxHeight + ';';

      const applySize = (s) => {
        currentSize = s; this._previewModalSize = s;
        box.style.maxWidth  = sizes[s].maxWidth;
        box.style.maxHeight = sizes[s].maxHeight;
        box.querySelectorAll('.nu-size-btn').forEach(b => {
          b.style.fontWeight = b.dataset.size === s ? '700' : '400';
          b.style.background = b.dataset.size === s ? 'var(--primary,#4f6bed)' : 'transparent';
          b.style.color      = b.dataset.size === s ? '#fff' : 'var(--text,#333)';
        });
      };

      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:8px;';
      const titleWrap = document.createElement('div');
      titleWrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;min-width:0;';
      const bc = this._renderBreadcrumb([
        { label: 'Forms', action: () => { overlay.remove(); this.loadModule('forms'); } },
        { label: label }
      ]);
      bc.style.marginBottom = '0';
      titleWrap.appendChild(bc);
      header.appendChild(titleWrap);

      const controls = document.createElement('div');
      controls.style.cssText = 'display:flex;align-items:center;gap:4px;flex-shrink:0;';
      ['compact','standard','full'].forEach(s => {
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'nu-size-btn'; btn.dataset.size = s;
        btn.textContent = s === 'compact' ? '\u25a3 Sm' : s === 'standard' ? '\u25a3 Md' : '\u26f6 Lg';
        btn.title = s.charAt(0).toUpperCase() + s.slice(1);
        btn.style.cssText = 'border:1px solid var(--border-color,#ddd);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;background:transparent;color:var(--text,#333);transition:all 0.15s;';
        btn.addEventListener('click', () => applySize(s));
        controls.appendChild(btn);
      });
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button'; closeBtn.innerHTML = '&times;';
      closeBtn.style.cssText = 'background:none;border:none;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;color:var(--text,#333);margin-left:4px;';
      closeBtn.addEventListener('click', () => overlay.remove());
      controls.appendChild(closeBtn);
      header.appendChild(controls);
      box.appendChild(header);

      const formWrap = document.createElement('div');
      formWrap.innerHTML = json.html;
      box.appendChild(formWrap);

      const formEl = box.querySelector('.nu-generated-form');
      if (formEl) formEl.dataset.displayMode = 'modal';

      overlay.appendChild(box);
      document.body.appendChild(overlay);
      applySize(currentSize);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

      this._initFormWidgets(box);
      this._execModuleScripts(box);
      this._dispatchFormOpened(box);
      if (window.nuForm && typeof window.nuForm.init === 'function') {
        if (formEl) window.nuForm.init(formEl.dataset.formCode || code, {}, true);
      }
    } catch (err) {
      console.error('previewForm error', err);
      this.toast('Preview error: ' + err.message, 'error');
    }
  },

  async editRecord(code, id, fromBrowseLabel, displayMode) {
    const mode        = (displayMode || 'modal').toLowerCase();
    const browseLabel = fromBrowseLabel || code;
    if (mode === 'inline')   return this._openFormInline(code, browseLabel, id, false);
    if (mode === 'fullpage') return this._openFormFullPage(code, browseLabel, id, false);

    try {
      const json = await this.apiJson('api/form.php?action=render&code=' + encodeURIComponent(code) + '&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      if (!json.success) { this.toast(json.error || 'Failed', 'error'); return; }

      const overlay = document.createElement('div');
      overlay.className = 'nu-form-overlay';
      overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;max-width:900px;max-height:90vh;overflow-y:auto;width:92%;';

      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;';
      const bc = this._renderBreadcrumb([
        { label: 'Forms',     action: () => { overlay.remove(); this.loadModule('forms'); } },
        { label: browseLabel, action: () => { overlay.remove(); this.browseForm(code, 1, '', browseLabel, mode); } },
        { label: 'Edit #' + id }
      ]);
      bc.style.marginBottom = '0';
      header.appendChild(bc);
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button'; closeBtn.innerHTML = '&times;';
      closeBtn.style.cssText = 'background:none;border:none;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;color:var(--text,#333);';
      closeBtn.addEventListener('click', () => overlay.remove());
      header.appendChild(closeBtn);
      box.appendChild(header);

      const formWrap = document.createElement('div');
      formWrap.innerHTML = json.html;
      box.appendChild(formWrap);

      const formEl = box.querySelector('.nu-generated-form');
      if (formEl) formEl.dataset.displayMode = 'modal';

      overlay.appendChild(box);
      document.body.appendChild(overlay);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

      this._initFormWidgets(box);
      this._execModuleScripts(box);
      this._dispatchFormOpened(box);
      if (window.nuForm && typeof window.nuForm.init === 'function') {
        if (formEl) window.nuForm.init(formEl.dataset.formCode || code, {}, false);
      }
    } catch (err) {
      console.error('editRecord error', err);
      this.toast('Error: ' + err.message, 'error');
    }
  },

  async viewRecord(code, id, fromBrowseLabel, displayMode) {
    const mode        = (displayMode || 'modal').toLowerCase();
    const browseLabel = fromBrowseLabel || code;

    try {
      const json = await this.apiJson(
        'api/form.php?action=render&code=' + encodeURIComponent(code) + '&id=' + encodeURIComponent(id),
        { credentials: 'same-origin' }
      );
      if (!json.success) { this.toast(json.error || 'Failed', 'error'); return; }

      const overlay = document.createElement('div');
      overlay.className = 'nu-form-overlay';
      overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;max-width:900px;max-height:90vh;overflow-y:auto;width:92%;';

      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;';
      const bc = this._renderBreadcrumb([
        { label: browseLabel, action: () => { overlay.remove(); this.browseForm(code, 1, '', browseLabel, mode); } },
        { label: 'View #' + id }
      ]);
      bc.style.marginBottom = '0';
      header.appendChild(bc);

      const badge = document.createElement('span');
      badge.textContent = '\uD83D\uDD12 View Only';
      badge.style.cssText = 'font-size:11px;padding:3px 8px;border-radius:10px;background:var(--bg-elevated,#f0f0f0);color:var(--text-muted,#888);margin-right:8px;';
      header.appendChild(badge);

      const closeBtn = document.createElement('button');
      closeBtn.type = 'button'; closeBtn.innerHTML = '&times;';
      closeBtn.style.cssText = 'background:none;border:none;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;color:var(--text,#333);';
      closeBtn.addEventListener('click', () => overlay.remove());
      header.appendChild(closeBtn);
      box.appendChild(header);

      const formWrap = document.createElement('div');
      formWrap.innerHTML = json.html;
      box.appendChild(formWrap);

      box.querySelectorAll('input, textarea, select, button[type="submit"]').forEach(el => {
        if (el.type === 'submit') { el.style.display = 'none'; return; }
        el.setAttribute('disabled', 'disabled');
        el.style.opacity = '0.7';
        el.style.cursor  = 'default';
      });
      box.querySelectorAll('button.nu-btn-ghost').forEach(el => {
        if (el.textContent.trim() === 'Cancel') el.textContent = 'Close';
      });

      const formEl = box.querySelector('.nu-generated-form');
      if (formEl) {
        formEl.dataset.displayMode = 'modal';
        formEl.dataset.viewOnly    = '1';
      }

      overlay.appendChild(box);
      document.body.appendChild(overlay);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

      this._initFormWidgets(box);
      this._execModuleScripts(box);
      this._dispatchFormOpened(box);
    } catch (err) {
      console.error('viewRecord error', err);
      this.toast('Error: ' + err.message, 'error');
    }
  },

  addRecord(code, formLabel, displayMode) {
    return this._openFormInline(code, formLabel, null, false);
  },

  async browseForm(code, page, query, formLabel, displayMode, sort, dir) {
    const mode = (displayMode || 'inline').toLowerCase();
    if (mode === 'modal')    return this._browseModal(code, page, query, formLabel, sort, dir);
    if (mode === 'fullpage') return this._browseFullPage(code, page, query, formLabel, sort, dir);
    return this._browseInline(code, page, query, formLabel, sort, dir);
  },

  async _fetchBrowseData(code, page, query, sort, dir) {
    page  = page  || 1;
    query = query || '';
    let url = 'api/form.php?action=list&code=' + encodeURIComponent(code) +
      '&page=' + encodeURIComponent(page) + '&q=' + encodeURIComponent(query);
    if (sort) url += '&sort=' + encodeURIComponent(sort) + '&dir=' + encodeURIComponent(dir || 'ASC');
    const json = await this.apiJson(url, { credentials: 'same-origin' });
    if (!json.success) throw new Error(json.error || 'Browse failed');
    return json;
  },

  // ── Browse table builder ─────────────────────────────────────────────────
  _buildBrowseTable(json, code, page, query, label, displayMode, container, onEdit, canEdit, canAdd, currentSortField, currentSortDir) {
    const _canEdit          = (canEdit !== undefined) ? canEdit : NuPerms.canEdit();
    const _canAdd           = (canAdd  !== undefined) ? canAdd  : NuPerms.canAdd();
    const data              = json.data || {};
    const layout            = Array.isArray(data.layout)  ? data.layout  : [];
    let records             = Array.isArray(data.records) ? data.records : [];
    const currentQuery      = data.query || query || '';
    const searchEnabled     = String(data.browsesearchenabled || 0) === '1';
    const searchPlaceholder = data.browsesearchplaceholder || 'Search...';
    const formTable         = data.form_table || '';
    const deleteEnabled     = data.browse_delete_enabled !== undefined ? (parseInt(data.browse_delete_enabled, 10) === 1) : true;

    // Parse browse columns custom layout configuration
    let browseLayout = [];
    if (data.browse_layout) {
      try { browseLayout = JSON.parse(data.browse_layout); } catch (e) { browseLayout = []; }
    }
    if (!Array.isArray(browseLayout) || browseLayout.length === 0) {
      browseLayout = layout.map(f => ({
        fieldname: f.fieldname || f.name,
        fieldlabel: f.fieldlabel || f.label || f.fieldname || f.name,
        width: '',
        align: 'left',
        formatter: 'text',
        sortable: true,
        frozen: false
      }));
    }

    container.innerHTML = '';

    // Escape Helper for cells HTML
    const escapeHTML = str => String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // ── Image Lightbox Helper ──
    if (!window._showImageLightbox) {
      window._showImageLightbox = function (src) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:100000;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
        const img = document.createElement('img');
        img.src = src;
        img.style.cssText = 'max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5);';
        overlay.appendChild(img);
        overlay.onclick = () => overlay.remove();
        document.body.appendChild(overlay);
      };
    }

    // ── Conditional Formatting style runner (multi-rule support, badge style) ──
    function _evaluateConditionalRules(col, val, cell) {
      let rules = col.rules || [];
      if (rules.length === 0 && col.cond_op) {
        rules = [{ op: col.cond_op, val: col.cond_val, fg: col.cond_fg, bg: col.cond_bg }];
      }
      if (rules.length === 0) return;

      const vStr = String(val).toLowerCase().trim();
      const vNum = parseFloat(val);

      for (const rule of rules) {
        let matched = false;
        const ruleVal = rule.val;
        const rStr = String(ruleVal).toLowerCase().trim();
        const rNum = parseFloat(ruleVal);

        switch (rule.op) {
          case '=':
            matched = (vStr === rStr);
            break;
          case '!=':
            matched = (vStr !== rStr);
            break;
          case '>':
            if (!isNaN(vNum) && !isNaN(rNum)) matched = (vNum > rNum);
            break;
          case '<':
            if (!isNaN(vNum) && !isNaN(rNum)) matched = (vNum < rNum);
            break;
          case 'contains':
            matched = (vStr.indexOf(rStr) !== -1);
            break;
        }

        if (matched) {
          // Render value inside a beautiful badge/pill with custom rule colors
          const fg = rule.fg || '#15803d';
          const bg = rule.bg || '#dcfce7';
          cell.innerHTML = `<span class="nu-badge-pill" style="padding:4px 10px;border-radius:12px;font-size:11.5px;font-weight:600;display:inline-block;color:${fg};background:${bg};border:1px solid ${fg}33;">${escapeHTML(String(val))}</span>`;
          break; // apply only the first matching rule
        }
      }
    }

    // ── Local interactive filter panel ──
    const filterRow = document.createElement('div');
    filterRow.id = 'nuAdvancedFiltersPanel';
    filterRow.style.cssText = 'display:none;background:var(--bg-elevated,#f8f9fa);padding:14px;border:1px solid var(--border-color,#e2e8f0);border-radius:8px;margin-bottom:16px;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:12px;';

    browseLayout.forEach((col) => {
      const colDiv = document.createElement('div');
      colDiv.style.cssText = 'display:flex;flex-direction:column;gap:4px;';
      const fLabel = document.createElement('label');
      fLabel.style.cssText = 'font-size:11px;font-weight:600;color:var(--text-secondary);';
      fLabel.textContent = col.fieldlabel || col.fieldname;

      let fInput;
      if (col.formatter === 'date') {
        fInput = document.createElement('div');
        fInput.style.cssText = 'display:flex;gap:4px;';
        const from = document.createElement('input');
        from.type = 'date'; from.className = 'nu-input'; from.placeholder = 'From';
        from.style.fontSize = '11px'; from.style.padding = '4px';
        const to = document.createElement('input');
        to.type = 'date'; to.className = 'nu-input'; to.placeholder = 'To';
        to.style.fontSize = '11px'; to.style.padding = '4px';
        fInput.appendChild(from);
        fInput.appendChild(to);

        const filterHandler = () => {
          const fromVal = from.value ? new Date(from.value) : null;
          const toVal = to.value ? new Date(to.value) : null;
          _runClientFiltering(col.fieldname, (val) => {
            if (!val) return !fromVal && !toVal;
            const dVal = new Date(val);
            if (isNaN(dVal)) return false;
            if (fromVal && dVal < fromVal) return false;
            if (toVal && dVal > toVal) return false;
            return true;
          });
        };
        from.onchange = filterHandler;
        to.onchange = filterHandler;
      } else {
        fInput = document.createElement('input');
        fInput.type = 'text'; fInput.className = 'nu-input';
        fInput.placeholder = 'Filter ' + (col.fieldlabel || col.fieldname);
        fInput.style.fontSize = '12px'; fInput.style.padding = '5px 8px';
        fInput.oninput = () => {
          const qVal = fInput.value.toLowerCase().trim();
          _runClientFiltering(col.fieldname, (val) => String(val).toLowerCase().indexOf(qVal) !== -1);
        };
      }
      colDiv.appendChild(fLabel);
      colDiv.appendChild(fInput);
      filterRow.appendChild(colDiv);
    });

    const activeFilters = {};
    function _runClientFiltering(field, predicate) {
      activeFilters[field] = predicate;
      const rows = tbody.querySelectorAll('tr:not(.nu-empty-row)');
      rows.forEach(r => {
        let visible = true;
        Object.keys(activeFilters).forEach(fKey => {
          const cell = r.querySelector(`[data-field-cell="${fKey}"]`);
          const cellVal = cell ? cell.dataset.rawVal : '';
          if (!activeFilters[fKey](cellVal)) visible = false;
        });
        r.style.display = visible ? '' : 'none';
      });
    }

    // ── Search & Actions bar row ──
    const searchWrap = document.createElement('div');
    searchWrap.style.cssText = 'margin-bottom:16px;display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;';

    if (searchEnabled) {
      const searchInput = document.createElement('input');
      searchInput.type = 'text'; searchInput.className = 'nu-input';
      searchInput.placeholder = searchPlaceholder; searchInput.value = currentQuery;
      searchInput.style.flex = '1';
      searchInput.style.minWidth = '200px';

      const searchBtn = document.createElement('button');
      searchBtn.className = 'nu-btn nu-btn-primary'; searchBtn.textContent = 'Search';
      searchBtn.onclick = () => this.browseForm(code, 1, searchInput.value.trim(), label, displayMode, currentSortField, currentSortDir);

      const clearBtn = document.createElement('button');
      clearBtn.className = 'nu-btn nu-btn-ghost'; clearBtn.textContent = 'Clear';
      clearBtn.onclick = () => this.browseForm(code, 1, '', label, displayMode);

      searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') this.browseForm(code, 1, searchInput.value.trim(), label, displayMode, currentSortField, currentSortDir);
      });
      searchWrap.appendChild(searchInput);
      searchWrap.appendChild(searchBtn);
      searchWrap.appendChild(clearBtn);
    } else {
      const spacer = document.createElement('div');
      spacer.style.flex = '1';
      searchWrap.appendChild(spacer);
    }

    // Advanced Filters Panel Toggle button
    const filtersBtn = document.createElement('button');
    filtersBtn.type = 'button';
    filtersBtn.className = 'nu-btn nu-btn-ghost';
    filtersBtn.innerHTML = '⚡ Advanced Filters';
    filtersBtn.onclick = () => {
      filterRow.style.display = filterRow.style.display === 'none' ? 'grid' : 'none';
    };
    searchWrap.appendChild(filtersBtn);

    // Export Dropdown menu
    const exportWrap = document.createElement('div');
    exportWrap.style.cssText = 'position:relative;display:inline-block;';
    const exportBtn = document.createElement('button');
    exportBtn.type = 'button';
    exportBtn.className = 'nu-btn nu-btn-ghost';
    exportBtn.innerHTML = '⬇ Export ▾';

    const exportMenu = document.createElement('div');
    exportMenu.style.cssText = 'display:none;position:absolute;right:0;top:100%;background:var(--bg-elevated,#fff);border:1px solid var(--border-color,#ccc);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;min-width:160px;margin-top:4px;overflow:hidden;';

    const _addExportItem = (lbl, action) => {
      const item = document.createElement('div');
      item.style.cssText = 'padding:8px 12px;font-size:12px;cursor:pointer;color:var(--text-primary);transition:background 0.2s;';
      item.textContent = lbl;
      item.addEventListener('mouseover', () => item.style.background = 'var(--bg-hover,#f5f7ff)');
      item.addEventListener('mouseout', () => item.style.background = 'none');
      item.onclick = (e) => {
        e.stopPropagation();
        exportMenu.style.display = 'none';
        action();
      };
      exportMenu.appendChild(item);
    };

    _addExportItem('Export Page as CSV', () => _localExport('csv'));
    _addExportItem('Export Page as JSON', () => _localExport('json'));
    _addExportItem('Export All Table (CSV)', () => {
      window.location.href = 'api/export.php?code=' + encodeURIComponent(code);
    });

    function _localExport(type) {
      if (!records.length) { NuApp.toast('No records to export', 'error'); return; }
      if (type === 'json') {
        const str = JSON.stringify(records, null, 2);
        const blob = new Blob([str], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = code + '_page_' + page + '.json';
        link.click();
      } else {
        const headers = browseLayout.map(c => c.fieldlabel || c.fieldname);
        const rows = records.map(r => browseLayout.map(c => r[c.fieldname] !== null ? r[c.fieldname] : ''));
        const csvContent = [headers.join(','), ...rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(','))].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = code + '_page_' + page + '.csv';
        link.click();
      }
      NuApp.toast('Exported successfully!');
    }

    exportBtn.onclick = (e) => {
      e.stopPropagation();
      exportMenu.style.display = exportMenu.style.display === 'none' ? 'block' : 'none';
    };
    document.addEventListener('click', () => exportMenu.style.display = 'none');
    exportWrap.appendChild(exportBtn);
    exportWrap.appendChild(exportMenu);
    searchWrap.appendChild(exportWrap);

    if (_canAdd) {
      const addBtn = document.createElement('button');
      addBtn.className = 'nu-btn nu-btn-primary';
      addBtn.textContent = '+ Add New Record';
      addBtn.onclick = () => this.addRecord(code, label, displayMode);
      searchWrap.appendChild(addBtn);
    }

    container.appendChild(searchWrap);
    container.appendChild(filterRow);

    // ── Bulk Actions Floating bar ──
    const bulkBar = document.createElement('div');
    bulkBar.id = 'nuBulkActionsFloatingBar';
    bulkBar.style.cssText = 'display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--bg-card,#1e293b);color:#fff;border-radius:30px;padding:10px 24px;box-shadow:0 8px 24px rgba(0,0,0,0.25);z-index:99999;align-items:center;gap:16px;border:1px solid rgba(255,255,255,0.1);transition:all 0.3s;';

    const bulkCount = document.createElement('span');
    bulkCount.style.cssText = 'font-size:13px;font-weight:600;';
    bulkBar.appendChild(bulkCount);

    const bulkDeleteBtn = document.createElement('button');
    bulkDeleteBtn.type = 'button';
    bulkDeleteBtn.className = 'nu-btn nu-btn-danger nu-btn-sm';
    bulkDeleteBtn.style.borderRadius = '20px';
    bulkDeleteBtn.textContent = '🗑 Bulk Delete';
    bulkDeleteBtn.onclick = () => _executeBulkDelete();
    bulkBar.appendChild(bulkDeleteBtn);

    container.appendChild(bulkBar);

    function _updateBulkSelectionCount() {
      const checkedBoxes = tbody.querySelectorAll('.nu-row-checkbox:checked');
      if (checkedBoxes.length > 0) {
        bulkCount.textContent = checkedBoxes.length + ' records selected';
        bulkBar.style.display = 'flex';
      } else {
        bulkBar.style.display = 'none';
      }
    }

    async function _executeBulkDelete() {
      const checkedBoxes = tbody.querySelectorAll('.nu-row-checkbox:checked');
      if (checkedBoxes.length === 0) return;
      if (!confirm(`Are you sure you want to delete all ${checkedBoxes.length} selected records permanently?`)) return;

      const ids = Array.from(checkedBoxes).map(cb => cb.dataset.recordId);
      let successCount = 0;
      let errorMsg = '';
      for (const rId of ids) {
        try {
          const res = await NuApp.apiJson(`api/crud.php?table=${encodeURIComponent(formTable)}&id=${encodeURIComponent(rId)}`, {
            method: 'DELETE', credentials: 'same-origin'
          });
          if (res && res.success) successCount++;
          else errorMsg = res.error || 'Failed';
        } catch (err) {
          errorMsg = err.message;
        }
      }
      if (successCount > 0) {
        NuApp.toast(`Successfully deleted ${successCount} records!`, 'success');
        NuApp.browseForm(code, page, currentQuery, label, displayMode);
      } else {
        NuApp.toast('Bulk delete failed: ' + errorMsg, 'error');
      }
    }

    // ── Columns Helper ──
    const _swapColumns = (i, j) => {
      if (i < 0 || i >= browseLayout.length || j < 0 || j >= browseLayout.length) return;
      const tmp = browseLayout[i];
      browseLayout[i] = browseLayout[j];
      browseLayout[j] = tmp;

      // Update data.browse_layout dynamically and trigger re-render of this table!
      data.browse_layout = JSON.stringify(browseLayout);
      this._buildBrowseTable(json, code, page, query, label, displayMode, container, onEdit, canEdit, canAdd, currentSortField, currentSortDir);
    };

    const _toggleHeaderSort = (field) => {
      const newDir = (currentSortField === field && currentSortDir === 'ASC') ? 'DESC' : 'ASC';
      this.browseForm(code, 1, currentQuery, label, displayMode, field, newDir);
    };

    // ── Sticky Frozen columns setup ──
    let stickyOffset = 0;

    const tableWrap = document.createElement('div');
    tableWrap.style.cssText = 'overflow-x:auto;border-radius:10px;border:1px solid var(--border-color);position:relative;background:var(--bg-card);';

    const table = document.createElement('table');
    table.style.cssText = 'width:100%;border-collapse:collapse;table-layout:fixed;';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.style.cssText = 'border-bottom:2px solid var(--border-color,#ddd);background:var(--bg-subtle,#f8f9fa);';

    // Bulk action select all checkbox column (conditional)
    if (deleteEnabled) {
      const bulkTh = document.createElement('th');
      bulkTh.style.cssText = 'width:40px;padding:12px;text-align:center;position:sticky;left:0;z-index:15;background:inherit;';
      const bulkSelectAll = document.createElement('input');
      bulkSelectAll.type = 'checkbox';
      bulkSelectAll.className = 'nu-select-all-checkbox';
      bulkSelectAll.style.cursor = 'pointer';
      bulkSelectAll.onchange = () => {
        const c = bulkSelectAll.checked;
        tbody.querySelectorAll('.nu-row-checkbox').forEach(cb => cb.checked = c);
        _updateBulkSelectionCount();
      };
      bulkTh.appendChild(bulkSelectAll);
      headRow.appendChild(bulkTh);
      stickyOffset = 40; // width of bulkTh is 40
    } else {
      stickyOffset = 0;
    }

    browseLayout.forEach((col, idx) => {
      const th = document.createElement('th');
      th.style.position = col.frozen ? 'sticky' : 'relative';
      if (col.frozen) {
        th.style.left = stickyOffset + 'px';
        th.style.zIndex = '15';
        th.style.background = 'inherit';
        // Add width
        const widthVal = parseInt(col.width, 10) || 150;
        stickyOffset += widthVal;
      }

      const widthStyle = col.width ? `width:${col.width};` : 'width:150px;';
      th.style.cssText += `padding:12px;text-align:${col.align || 'left'};font-size:13px;font-weight:600;min-width:100px;${widthStyle}`;

      const labelSpan = document.createElement('span');
      labelSpan.textContent = col.fieldlabel || col.fieldname;
      labelSpan.style.cursor = col.sortable !== false ? 'pointer' : 'default';
      if (col.sortable !== false) {
        labelSpan.onclick = () => _toggleHeaderSort(col.fieldname);
      }
      th.appendChild(labelSpan);

      // Sort indicator
      if (currentSortField === col.fieldname) {
        const indicator = document.createElement('span');
        indicator.style.cssText = 'margin-left:4px;font-size:11px;color:var(--primary);';
        indicator.textContent = currentSortDir === 'ASC' ? '▲' : '▼';
        th.appendChild(indicator);
      }

      // Reorder buttons
      const reorderWrap = document.createElement('span');
      reorderWrap.style.cssText = 'display:inline-flex;gap:1px;margin-left:8px;opacity:0;transition:opacity 0.2s;';
      th.addEventListener('mouseenter', () => reorderWrap.style.opacity = '1');
      th.addEventListener('mouseleave', () => reorderWrap.style.opacity = '0');

      if (idx > 0) {
        const moveLeft = document.createElement('button');
        moveLeft.type = 'button'; moveLeft.innerHTML = '◀';
        moveLeft.style.cssText = 'border:none;background:none;font-size:9px;cursor:pointer;padding:0;color:var(--text-secondary);';
        moveLeft.onclick = (e) => { e.stopPropagation(); _swapColumns(idx, idx - 1); };
        reorderWrap.appendChild(moveLeft);
      }
      if (idx < browseLayout.length - 1) {
        const moveRight = document.createElement('button');
        moveRight.type = 'button'; moveRight.innerHTML = '▶';
        moveRight.style.cssText = 'border:none;background:none;font-size:9px;cursor:pointer;padding:0;color:var(--text-secondary);';
        moveRight.onclick = (e) => { e.stopPropagation(); _swapColumns(idx, idx + 1); };
        reorderWrap.appendChild(moveRight);
      }
      th.appendChild(reorderWrap);

      // Resize handle
      const resizer = document.createElement('span');
      resizer.className = 'nu-th-resizer';
      resizer.style.cssText = 'position:absolute;top:0;right:0;width:5px;height:100%;cursor:col-resize;background:transparent;user-select:none;z-index:20;';
      resizer.addEventListener('mouseover', () => resizer.style.background = 'var(--primary)');
      resizer.addEventListener('mouseout', () => resizer.style.background = 'transparent');
      resizer.addEventListener('mousedown', (e) => {
        e.preventDefault(); e.stopPropagation();
        const startX = e.clientX;
        const startWidth = th.offsetWidth;
        const onMouseMove = (moveEvt) => {
          const newWidth = Math.max(50, startWidth + (moveEvt.clientX - startX));
          th.style.width = newWidth + 'px';
          th.style.minWidth = newWidth + 'px';
          col.width = newWidth + 'px';
        };
        const onMouseUp = () => {
          document.removeEventListener('mousemove', onMouseMove);
          document.removeEventListener('mouseup', onMouseUp);
        };
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
      });
      th.appendChild(resizer);

      headRow.appendChild(th);
    });

    const actionTh = document.createElement('th');
    actionTh.textContent = 'Actions';
    actionTh.style.cssText = 'padding:12px;text-align:left;font-size:13px;font-weight:600;width:120px;';
    headRow.appendChild(actionTh);
    thead.appendChild(headRow); table.appendChild(thead);

    const tbody = document.createElement('tbody');
    if (!records.length) {
      const tr = document.createElement('tr');
      tr.className = 'nu-empty-row';
      const td = document.createElement('td');
      td.colSpan = browseLayout.length + 2;
      td.style.cssText = 'padding:40px;text-align:center;color:var(--text-muted);background:transparent;';
      td.textContent = currentQuery ? 'No matching records' : 'No records found';
      tr.appendChild(td); tbody.appendChild(tr);
    } else {
      // ── Records iteration ──
      records.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cssText = 'border-bottom:1px solid var(--border-color,#ddd);transition:background 0.15s;background:inherit;';
        tr.addEventListener('mouseenter', () => tr.style.background = 'var(--bg-hover, #f1f5f9)');
        tr.addEventListener('mouseleave', () => tr.style.background = '');

        // Row checkbox cell (conditional)
        if (deleteEnabled) {
          const bulkTd = document.createElement('td');
          bulkTd.style.cssText = 'width:40px;padding:12px;text-align:center;position:sticky;left:0;z-index:10;background:inherit;';
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.className = 'nu-row-checkbox';
          checkbox.style.cursor = 'pointer';
          checkbox.dataset.recordId = row.id;
          checkbox.onchange = () => _updateBulkSelectionCount();
          bulkTd.appendChild(checkbox);
          tr.appendChild(bulkTd);
        }

        let cellStickyOffset = deleteEnabled ? 40 : 0;

        browseLayout.forEach((col) => {
          const td = document.createElement('td');
          td.dataset.fieldCell = col.fieldname;

          td.style.position = col.frozen ? 'sticky' : 'relative';
          if (col.frozen) {
            td.style.left = cellStickyOffset + 'px';
            td.style.zIndex = '10';
            td.style.background = 'inherit';
            const widthVal = parseInt(col.width, 10) || 150;
            cellStickyOffset += widthVal;
          }

          td.style.cssText += `padding:12px;text-align:${col.align || 'left'};word-break:break-all;overflow:hidden;text-overflow:ellipsis;`;

          const fieldName  = col.fieldname;
          const displayKey = fieldName + '_display';
          let value = '';
          if (row[displayKey] != null) value = row[displayKey];
          else if (row[fieldName] != null) value = row[fieldName];

          td.dataset.rawVal = String(value);

          // Apply Formatter
          let cellHtml = escapeHTML(String(value));
          if (col.formatter === 'currency') {
            const valNum = parseFloat(value);
            cellHtml = isNaN(valNum) ? escapeHTML(String(value)) : '$' + valNum.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
          } else if (col.formatter === 'badge') {
            const badgeColors = {
              'active': 'background:#e0f2fe;color:#0369a1;',
              'completed': 'background:#dcfce7;color:#15803d;',
              'pending': 'background:#fef3c7;color:#b45309;',
              'rejected': 'background:#fee2e2;color:#b91c1c;',
              'cancelled': 'background:#f1f5f9;color:#475569;'
            };
            const key = String(value).toLowerCase().trim();
            const colorStyle = badgeColors[key] || 'background:var(--bg-offset);color:var(--text-secondary);';
            cellHtml = `<span class="nu-badge" style="padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;display:inline-block;${colorStyle}">${escapeHTML(String(value))}</span>`;
          } else if (col.formatter === 'progress_bar') {
            let pct = parseInt(value, 10);
            if (isNaN(pct)) pct = 0;
            pct = Math.max(0, Math.min(100, pct));
            cellHtml = `
              <div style="display:flex;align-items:center;gap:8px;min-width:100px;">
                <div style="flex:1;background:var(--border-color);height:8px;border-radius:4px;overflow:hidden;">
                  <div style="background:var(--primary);width:${pct}%;height:100%;border-radius:4px;"></div>
                </div>
                <span style="font-size:11px;font-weight:600;color:var(--text-secondary);">${pct}%</span>
              </div>
            `;
          } else if (col.formatter === 'checkbox_toggle') {
            const checked = !!value && value !== '0' && value !== 'false';
            const icon = checked ? '✅' : '❌';
            const color = checked ? '#22c55e' : '#ef4444';
            cellHtml = `<span style="color:${color};font-weight:bold;font-size:14px;" title="${checked ? 'True' : 'False'}">${icon}</span>`;
          } else if (col.formatter === 'image') {
            cellHtml = value ? `<img src="uploads/${escapeHTML(String(value))}" style="max-height:40px;border-radius:4px;cursor:pointer;border:1px solid var(--border-color);" onclick="window._showImageLightbox('uploads/${escapeHTML(String(value))}')" title="Click to view">` : '<span style="color:var(--text-muted);font-size:11px;">No image</span>';
          } else if (col.formatter === 'date') {
            let dateVal = value;
            if (value && !isNaN(Date.parse(value))) {
              dateVal = new Date(value).toLocaleDateString();
            }
            cellHtml = escapeHTML(String(dateVal));
          } else if (col.formatter === 'html') {
            cellHtml = String(value);
          }

          if (col.formatter === 'html' || col.formatter === 'progress_bar' || col.formatter === 'badge' || col.formatter === 'image' || col.formatter === 'checkbox_toggle') {
            td.innerHTML = cellHtml;
          } else {
            td.textContent = cellHtml;
          }

          // Apply Conditional Formatting Rules (multi-rule, badge-style)
          _evaluateConditionalRules(col, value, td);

          tr.appendChild(td);
        });

        // Actions cell
        const actionTd = document.createElement('td');
        actionTd.style.cssText = 'padding:12px;display:flex;gap:8px;align-items:center;';

        if (_canEdit) {
          const editBtn = document.createElement('button');
          editBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm';
          editBtn.innerHTML = `
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
          `;
          editBtn.onclick = () => (onEdit ? onEdit(row) : this.editRecord(code, row.id, label, displayMode));
          actionTd.appendChild(editBtn);
        } else {
          const viewBtn = document.createElement('button');
          viewBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm';
          viewBtn.style.cssText = 'color:var(--text-muted,#666);';
          viewBtn.innerHTML = `
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg> View
          `;
          viewBtn.onclick = () => this.viewRecord(code, row.id, label, displayMode);
          actionTd.appendChild(viewBtn);
        }

        // Single row delete button (conditional on deleteEnabled)
        if (deleteEnabled && NuPerms.canDelete()) {
          const delBtn = document.createElement('button');
          delBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm';
          delBtn.style.color = '#ef4444';
          delBtn.innerHTML = `
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:4px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete
          `;
          delBtn.onclick = () => {
            if (confirm('Are you sure you want to delete this record permanently?')) {
              NuApp.apiJson(`api/crud.php?table=${encodeURIComponent(formTable)}&id=${encodeURIComponent(row.id)}`, {
                method: 'DELETE', credentials: 'same-origin'
              }).then(res => {
                if (res && res.success) {
                  NuApp.toast('Record deleted!', 'success');
                  NuApp.browseForm(code, page, currentQuery, label, displayMode);
                } else {
                  NuApp.toast(res.error || 'Delete failed', 'error');
                }
              });
            }
          };
          actionTd.appendChild(delBtn);
        }

        tr.appendChild(actionTd); tbody.appendChild(tr);
      });
    }

    table.appendChild(tbody);

    // ── Sticky Summary/Aggregation Footer row ──
    if (records.length > 0) {
      const tfoot = document.createElement('tfoot');
      const footRow = document.createElement('tr');
      footRow.style.cssText = 'border-top:2px solid var(--border-color);background:var(--bg-subtle,#f1f5f9);font-weight:bold;';

      if (deleteEnabled) {
        const bulkFoot = document.createElement('td');
        bulkFoot.style.cssText = 'padding:12px;text-align:center;position:sticky;left:0;z-index:10;background:inherit;';
        bulkFoot.textContent = 'Σ';
        footRow.appendChild(bulkFoot);
      }

      let footerStickyOffset = deleteEnabled ? 40 : 0;

      browseLayout.forEach((col) => {
        const td = document.createElement('td');
        td.style.position = col.frozen ? 'sticky' : 'relative';
        if (col.frozen) {
          td.style.left = footerStickyOffset + 'px';
          td.style.zIndex = '10';
          td.style.background = 'inherit';
          const widthVal = parseInt(col.width, 10) || 150;
          footerStickyOffset += widthVal;
        }

        td.style.cssText += `padding:12px;text-align:${col.align || 'left'};font-size:12px;`;

        // Calculate sum or average for numeric columns
        const isNumeric = records.some(r => {
          const v = r[col.fieldname];
          return v !== null && v !== '' && !isNaN(parseFloat(v));
        });

        if (isNumeric && col.fieldname !== 'id') {
          const sum = records.reduce((acc, r) => {
            const v = parseFloat(r[col.fieldname]);
            return acc + (isNaN(v) ? 0 : v);
          }, 0);

          if (col.formatter === 'currency') {
            td.textContent = 'Sum: $' + sum.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
          } else {
            td.textContent = 'Sum: ' + Number(sum.toFixed(2));
          }
        } else {
          td.textContent = '';
        }
        footRow.appendChild(td);
      });

      const actionFoot = document.createElement('td');
      actionFoot.textContent = '';
      footRow.appendChild(actionFoot);
      tfoot.appendChild(footRow);
      table.appendChild(tfoot);
    }

    tableWrap.appendChild(table); container.appendChild(tableWrap);

    // ── Pagination rendering ──
    if ((data.pages || 1) > 1) {
      const pagination = document.createElement('div');
      pagination.style.cssText = 'display:flex;gap:4px;flex-wrap:wrap;align-items:center;margin-top:16px;';
      const prevBtn = document.createElement('button');
      prevBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; prevBtn.textContent = '\u2190 Prev';
      prevBtn.disabled = (data.page || 1) <= 1;
      prevBtn.onclick = () => this.browseForm(code, (data.page || 1) - 1, currentQuery, label, displayMode, currentSortField, currentSortDir);
      pagination.appendChild(prevBtn);
      for (let i = 1; i <= (data.pages || 1); i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'nu-btn ' + (i === (data.page || 1) ? 'nu-btn-primary' : 'nu-btn-ghost') + ' nu-btn-sm';
        pageBtn.textContent = i;
        pageBtn.onclick = () => this.browseForm(code, i, currentQuery, label, displayMode, currentSortField, currentSortDir);
        pagination.appendChild(pageBtn);
      }
      const nextBtn = document.createElement('button');
      nextBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; nextBtn.textContent = 'Next \u2192';
      nextBtn.disabled = (data.page || 1) >= (data.pages || 1);
      nextBtn.onclick = () => this.browseForm(code, (data.page || 1) + 1, currentQuery, label, displayMode, currentSortField, currentSortDir);
      pagination.appendChild(nextBtn);
      const meta = document.createElement('span');
      meta.style.cssText = 'margin-left:8px;color:#666;font-size:13px;';
      meta.textContent = 'Total: ' + (data.total || 0) + ' records';
      pagination.appendChild(meta); container.appendChild(pagination);
    }
  },

  async _browseInline(code, page, query, formLabel, sort, dir) {
    try {
      const _canAdd  = NuPerms.canAdd();
      const _canEdit = NuPerms.canEdit();
      const json  = await this._fetchBrowseData(code, page, query, sort, dir);
      const data  = json.data || {};
      const label = formLabel || data.form_name || code;
      const container = document.getElementById('contentArea');
      if (!container) { this.toast('Content area not found', 'error'); return; }
      container.innerHTML = '';
      const bc = this._renderBreadcrumb([
        { label: 'Forms', action: () => this.loadModule('forms') },
        { label: label,   action: () => this._browseInline(code, 1, '', label, sort, dir) },
        { label: 'Browse' }
      ]);
      container.appendChild(bc);
      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;';
      const h3 = document.createElement('h3');
      h3.style.cssText = 'margin:0;font-size:18px;'; h3.textContent = label;
      header.appendChild(h3);
      const btnGroup = document.createElement('div');
      btnGroup.style.cssText = 'display:flex;gap:8px;align-items:center;';
      const previewBtn = document.createElement('button');
      previewBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; previewBtn.textContent = '\u229e Preview Form';
      previewBtn.onclick = () => this.previewForm(code, label);
      btnGroup.appendChild(previewBtn);
      const backBtn = document.createElement('button');
      backBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; backBtn.textContent = '\u2190 Forms';
      backBtn.onclick = () => this.loadModule('forms');
      btnGroup.appendChild(backBtn);
      header.appendChild(btnGroup); container.appendChild(header);
      this._buildBrowseTable(json, code, page, query, label, 'inline', container, null, _canEdit, _canAdd, sort, dir);
    } catch (err) { console.error('_browseInline error', err); this.toast('Error: ' + err.message, 'error'); }
  },

  async _browseModal(code, page, query, formLabel, sort, dir) {
    try {
      const _canAdd  = NuPerms.canAdd();
      const _canEdit = NuPerms.canEdit();
      const json  = await this._fetchBrowseData(code, page, query, sort, dir);
      const data  = json.data || {};
      const label = formLabel || data.form_name || code;
      let overlay = document.querySelector('.nu-browse-overlay');
      let isNew = false;
      if (!overlay) {
        isNew = true;
        overlay = document.createElement('div');
        overlay.className = 'nu-browse-overlay nu-form-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';
      }
      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:12px;padding:24px;width:96%;max-width:1100px;max-height:92vh;overflow-y:auto;';
      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:8px;';
      const bc = this._renderBreadcrumb([
        { label: 'Forms', action: () => { overlay.remove(); this.loadModule('forms'); } },
        { label: label,   action: () => this._browseModal(code, 1, '', label, sort, dir) },
        { label: 'Browse' }
      ]);
      bc.style.marginBottom = '0'; header.appendChild(bc);
      const rightBtns = document.createElement('div');
      rightBtns.style.cssText = 'display:flex;gap:6px;flex-shrink:0;align-items:center;';
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button'; closeBtn.innerHTML = '&times;';
      closeBtn.style.cssText = 'background:none;border:none;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;color:var(--text,#333);';
      closeBtn.addEventListener('click', () => overlay.remove());
      rightBtns.appendChild(closeBtn); header.appendChild(rightBtns);
      box.appendChild(header);
      const tableContainer = document.createElement('div');
      this._buildBrowseTable(json, code, page, query, label, 'modal', tableContainer, null, _canEdit, _canAdd, sort, dir);
      box.appendChild(tableContainer);
      overlay.innerHTML = ''; overlay.appendChild(box);
      if (isNew) {
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
      }
    } catch (err) { console.error('_browseModal error', err); this.toast('Error: ' + err.message, 'error'); }
  },

  async _browseFullPage(code, page, query, formLabel, sort, dir) {
    try {
      const _canAdd  = NuPerms.canAdd();
      const _canEdit = NuPerms.canEdit();
      const json  = await this._fetchBrowseData(code, page, query, sort, dir);
      const data  = json.data || {};
      const label = formLabel || data.form_name || code;
      this._enterFullPage();
      const container = document.getElementById('contentArea');
      if (!container) { this.toast('Content area not found', 'error'); return; }
      container.innerHTML = '';
      const bc = this._renderBreadcrumb([
        { label: 'Forms', action: () => { this._exitFullPage(); this.loadModule('forms'); } },
        { label: label,   action: () => this._browseFullPage(code, 1, '', label, sort, dir) },
        { label: 'Browse' }
      ]);
      container.appendChild(bc);
      const header = document.createElement('div');
      header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;';
      const h3 = document.createElement('h3');
      h3.style.cssText = 'margin:0;font-size:20px;'; h3.textContent = label;
      header.appendChild(h3);
      const btnGroup = document.createElement('div');
      btnGroup.style.cssText = 'display:flex;gap:8px;';
      const exitBtn = document.createElement('button');
      exitBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; exitBtn.textContent = '\u2715 Exit Full Page';
      exitBtn.onclick = () => { this._exitFullPage(); this.loadModule('forms'); };
      btnGroup.appendChild(exitBtn); header.appendChild(btnGroup); container.appendChild(header);
      this._buildBrowseTable(json, code, page, query, label, 'fullpage', container, null, _canEdit, _canAdd, sort, dir);
    } catch (err) { this._exitFullPage(); console.error('_browseFullPage error', err); this.toast('Error: ' + err.message, 'error'); }
  },

  async _openFormInline(code, formLabel, id, isPreview) {
    try {
      const url = 'api/form.php?action=render&code=' + encodeURIComponent(code) + (id ? '&id=' + encodeURIComponent(id) : '');
      const json = await this.apiJson(url, { credentials: 'same-origin' });
      if (!json.success) { this.toast(json.error || 'Failed', 'error'); return; }
      const label     = formLabel || code;
      const container = document.getElementById('contentArea');
      if (!container) { this.toast('Content area not found', 'error'); return; }
      container.innerHTML = '';
      const crumbs = [
        { label: 'Forms', action: () => this.loadModule('forms') },
        { label: label,   action: () => this.browseForm(code, 1, '', label, 'inline') },
        { label: isPreview ? 'Preview' : (id ? 'Edit #' + id : 'New Record') }
      ];
      container.appendChild(this._renderBreadcrumb(crumbs));
      const formWrap = document.createElement('div');
      formWrap.innerHTML = json.html;
      container.appendChild(formWrap);
      const formEl = container.querySelector('.nu-generated-form');
      if (formEl) {
        formEl.dataset.displayMode = 'inline';
        formEl.dataset.fromBrowse  = label;
      }
      this._initFormWidgets(container);
      this._execModuleScripts(container);
      this._dispatchFormOpened(container);
      if (window.nuForm && typeof window.nuForm.init === 'function') {
        if (formEl) window.nuForm.init(formEl.dataset.formCode || code, {}, isPreview);
      }
    } catch (err) { console.error('_openFormInline error', err); this.toast('Error: ' + err.message, 'error'); }
  },

  async _openFormFullPage(code, formLabel, id, isPreview) {
    try {
      const url = 'api/form.php?action=render&code=' + encodeURIComponent(code) + (id ? '&id=' + encodeURIComponent(id) : '');
      const json = await this.apiJson(url, { credentials: 'same-origin' });
      if (!json.success) { this.toast(json.error || 'Failed', 'error'); return; }
      const label = formLabel || code;
      this._enterFullPage();
      const container = document.getElementById('contentArea');
      if (!container) { this.toast('Content area not found', 'error'); return; }
      container.innerHTML = '';
      const exitAction = () => { this._exitFullPage(); this.loadModule('forms'); };
      container.appendChild(this._renderBreadcrumb([
        { label: 'Forms', action: exitAction },
        { label: label,   action: () => { this._exitFullPage(); this.browseForm(code, 1, '', label, 'fullpage'); } },
        { label: isPreview ? 'Preview' : (id ? 'Edit #' + id : 'New Record') }
      ]));
      const exitBtn = document.createElement('button');
      exitBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm'; exitBtn.style.cssText = 'margin-bottom:12px;';
      exitBtn.textContent = '\u2715 Exit Full Page'; exitBtn.onclick = exitAction;
      container.appendChild(exitBtn);
      const formWrap = document.createElement('div');
      formWrap.innerHTML = json.html;
      container.appendChild(formWrap);
      const formEl = container.querySelector('.nu-generated-form');
      if (formEl) formEl.dataset.displayMode = 'fullpage';
      this._initFormWidgets(container);
      this._execModuleScripts(container);
      this._dispatchFormOpened(container);
      if (window.nuForm && typeof window.nuForm.init === 'function') {
        if (formEl) window.nuForm.init(formEl.dataset.formCode || code, {}, isPreview);
      }
    } catch (err) { this._exitFullPage(); console.error('_openFormFullPage error', err); this.toast('Error: ' + err.message, 'error'); }
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () { NuApp.init(); });
} else {
  NuApp.init();
}

window.closeNuForm = function (btn) {
  const overlay = btn ? btn.closest('.nu-form-overlay') : null;
  if (overlay) { overlay.remove(); return; }
  NuApp.loadModule('forms');
};

window.submitNuForm = async function (formElement) {
  if (!formElement) { NuApp.toast('Form element not found', 'error'); return; }

  if (formElement.dataset.viewOnly === '1') { NuApp.toast('View only \u2014 saving is disabled', 'error'); return; }

  // Clear existing validation highlights & error messages
  formElement.querySelectorAll('.nu-validation-error-msg').forEach(el => el.remove());
  formElement.querySelectorAll('.nu-input-error').forEach(el => {
    el.classList.remove('nu-input-error');
    el.style.borderColor = '';
  });

  // Real-time Visual validation feedback
  let isFormValid = true;
  let firstInvalidElement = null;
  formElement.querySelectorAll('[required]').forEach((el) => {
    const isSelect2 = el.classList.contains('nu-select2');
    const targetEl = isSelect2 ? el.nextElementSibling || el : el;
    const value = el.value ? el.value.trim() : '';
    if (value === '') {
      isFormValid = false;
      el.classList.add('nu-input-error');
      targetEl.style.borderColor = '#ef4444';
      targetEl.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.2)';

      const errorMsg = document.createElement('div');
      errorMsg.className = 'nu-validation-error-msg';
      errorMsg.style.cssText = 'color:#ef4444;font-size:11px;font-weight:600;margin-top:4px;display:flex;align-items:center;gap:4px;transition:all 0.2s;';
      errorMsg.innerHTML = '⚠️ This field is required';
      targetEl.parentNode.appendChild(errorMsg);

      if (!firstInvalidElement) firstInvalidElement = el;
    }
  });

  if (!isFormValid) {
    NuApp.toast('Please fill out all required fields.', 'error');
    if (firstInvalidElement) {
      firstInvalidElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalidElement.focus();
    }
    return;
  }

  const formCode    = formElement.dataset.formCode;
  const recordId    = formElement.dataset.recordId;
  const displayMode = formElement.dataset.displayMode || 'inline';
  const fromBrowse  = formElement.dataset.fromBrowse  || null;
  const url = 'api/form.php?action=save&code=' + encodeURIComponent(formCode) +
    (recordId ? '&id=' + encodeURIComponent(recordId) : '');

  const formData = new FormData(formElement);
  const data = {};

  formData.forEach((value, key) => {
    if (Object.prototype.hasOwnProperty.call(data, key)) {
      if (!Array.isArray(data[key])) data[key] = [data[key]];
      data[key].push(value);
    } else {
      data[key] = value;
    }
  });

  formElement.querySelectorAll('select[name]').forEach((sel) => {
    const name = sel.name;
    if (!name) return;
    const selected = Array.from(sel.options)
      .filter(function (o) { return o.selected; })
      .map(function (o) { return o.value; });
    if (sel.multiple) {
      data[name] = selected;
    } else {
      data[name] = selected.length ? selected[0] : '';
    }
  });

  formElement.querySelectorAll('input[type="checkbox"]').forEach((el) => {
    if (!Object.prototype.hasOwnProperty.call(data, el.name)) data[el.name] = '';
  });

  if (window._nuFormBeforeSave && typeof window._nuFormBeforeSave[formCode] === 'function') {
    const result = window._nuFormBeforeSave[formCode](formElement, data);
    if (result === false) return;
    if (result && typeof result.then === 'function') {
      try { const v = await result; if (v === false) return; } catch (e) { return; }
    }
  }
  try {
    const json = await NuApp.apiJson(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    if (!json.success) { NuApp.toast(json.error || 'Save failed', 'error'); return; }
    NuApp.toast(recordId ? 'Updated' : 'Saved');
    if (window._nuFormAfterSave && typeof window._nuFormAfterSave[formCode] === 'function') {
      window._nuFormAfterSave[formCode](formElement, json);
    }
    const overlay = formElement.closest('.nu-form-overlay');
    if (overlay) overlay.remove();
    if (typeof NuApp.browseForm === 'function' && formCode) {
      NuApp.browseForm(formCode, 1, '', fromBrowse || null, 'inline');
    } else {
      NuApp.loadModule('forms');
    }
  } catch (e) { console.error('submitNuForm error', e); NuApp.toast('Error: ' + e.message, 'error'); }
};

window.openFormBuilder = function ()                               { return NuApp.openFormBuilder ? NuApp.openFormBuilder() : (window.nbFormBuilder ? window.nbFormBuilder.open() : null); };
window.previewForm     = function (code, label, mode)              { return NuApp.previewForm(code, label, mode); };
window.editForm        = function (id)                             { return window.nbFormBuilder ? window.nbFormBuilder.edit(id) : null; };
window.addRecord       = function (code, label, mode)              { return NuApp.addRecord(code, label, mode); };
window.editRecord      = function (code, id, label, mode)          { return NuApp.editRecord(code, id, label, mode); };
window.viewRecord      = function (code, id, label, mode)          { return NuApp.viewRecord(code, id, label, mode); };
window.browseForm      = function (code, page, query, label, mode) { return NuApp.browseForm(code, page, query, label, mode); };
window.browseFormPage  = function (code, page, query, label, mode) { return NuApp.browseForm(code, page, query, label, mode); };
window.deleteForm      = function (id, name) {
  var msg = name ? 'Are you sure you want to delete ' + name + ' permanently?' : 'Are you sure you want to delete this permanently?';
  if (!confirm(msg)) return;
  NuApp.apiJson('api/crud.php?table=nu_forms&id=' + encodeURIComponent(id), {
    method: 'DELETE', credentials: 'same-origin'
  }).then(function (json) {
    if (json.success) { NuApp.toast('Deleted'); NuApp.loadModule('forms'); }
    else NuApp.toast(json.error || 'Failed', 'error');
  }).catch(function (e) { NuApp.toast('Error: ' + e.message, 'error'); });
};
