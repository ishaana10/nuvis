// nb-ace-manager.js - Centralized Ace Editor manager and UI helpers
(function() {
  'use strict';

  if (window.nbAce) return; // Prevent duplicate initialization

  window.nbAce = (function() {
    var _editors = {};
    var _darkTheme  = 'ace/theme/one_dark';
    var _lightTheme = 'ace/theme/chrome';

    function _isDarkMode() {
      var t = document.documentElement.getAttribute('data-theme');
      if (t === 'dark')  return true;
      if (t === 'light') return false;
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function _init(editorId, hiddenId, mode) {
      if (!window.ace) return null;
      ace.require('ace/ext/language_tools');

      var editor = ace.edit(editorId);
      editor.setOptions({
        mode:                        'ace/mode/' + mode,
        theme:                       _isDarkMode() ? _darkTheme : _lightTheme,
        fontSize:                    '13px',
        tabSize:                     2,
        useSoftTabs:                 true,
        showPrintMargin:             false,
        enableBasicAutocompletion:   true,
        enableLiveAutocompletion:    true,
        enableSnippets:              true,
        wrap:                        false,
        scrollPastEnd:               0.3,
        showLineNumbers:             true,
        showGutter:                  true,
        highlightActiveLine:         true,
        fontFamily:                  'ui-monospace, "Cascadia Code", "Fira Code", monospace',
      });

      var hidden = document.getElementById(hiddenId);
      editor.session.on('change', function() {
        if (hidden) hidden.value = editor.getValue();
      });

      _editors[editorId] = { editor: editor, hiddenId: hiddenId, dark: _isDarkMode() };

      if (hidden && hidden.value) {
        editor.setValue(hidden.value, -1);
      }

      return editor;
    }

    function _setValue(editorId, value) {
      var entry = _editors[editorId];
      if (entry) {
        entry.editor.setValue(value || '', -1);
        entry.editor.clearSelection();
        entry.editor.getSession().getUndoManager().reset();
        var h = document.getElementById(entry.hiddenId);
        if (h) h.value = value || '';
      } else {
        var attempts = 0;
        function _retry() {
          attempts++;
          var e2 = _editors[editorId];
          if (e2) {
            e2.editor.setValue(value || '', -1);
            e2.editor.clearSelection();
            e2.editor.getSession().getUndoManager().reset();
            var h2 = document.getElementById(e2.hiddenId);
            if (h2) h2.value = value || '';
          } else if (attempts < 40) {
            requestAnimationFrame(_retry);
          }
        }
        requestAnimationFrame(_retry);
      }
    }

    function _getValue(editorId) {
      var entry = _editors[editorId];
      return entry ? entry.editor.getValue() : '';
    }

    function _syncAll() {
      Object.keys(_editors).forEach(function(id) {
        var entry = _editors[id];
        var h = document.getElementById(entry.hiddenId);
        if (h) h.value = entry.editor.getValue();
      });
    }

    function _toggleTheme(editorId) {
      var entry = _editors[editorId];
      if (!entry) return;
      entry.dark = !entry.dark;
      entry.editor.setTheme(entry.dark ? _darkTheme : _lightTheme);
    }

    function _resize(editorId) {
      var entry = _editors[editorId];
      if (entry) entry.editor.resize();
    }

    function _resizeAll() {
      Object.keys(_editors).forEach(_resize);
    }

    function _beautifyEditorInstance(editor, mode) {
      var code = editor.getValue();
      var beautified = code;

      if (mode.indexOf('javascript') !== -1) {
        if (window.js_beautify) {
          beautified = js_beautify(code, { indent_size: 2, space_in_empty_paren: true });
        }
      } else if (mode.indexOf('css') !== -1) {
        if (window.css_beautify) {
          beautified = css_beautify(code, { indent_size: 2 });
        }
      } else if (mode.indexOf('html') !== -1 || mode.indexOf('php') !== -1) {
        if (window.html_beautify) {
          beautified = html_beautify(code, { indent_size: 2 });
        }
      }

      editor.setValue(beautified, -1);
    }

    function _beautify(editorId) {
      var entry = _editors[editorId];
      if (!entry) return;
      var editor = entry.editor;
      var mode = editor.getSession().getMode().$id;
      _beautifyEditorInstance(editor, mode);
      if (window.nbFormBuilder) window.nbFormBuilder._isDirty = true;
    }

    function _openFullView(editorId) {
      var entry = _editors[editorId];
      if (!entry) return;

      var currentVal = entry.editor.getValue();
      var mode = entry.editor.getSession().getMode().$id;

      var modal = document.createElement('div');
      modal.className = 'nb-ace-fullscreen-modal';
      modal.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:var(--bg-surface,#ffffff);z-index:999999;display:flex;flex-direction:column;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:var(--text-main,#000000);';

      var header = document.createElement('div');
      header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--border-color,#dde1f0);background:var(--bg-offset,#f5f7ff);';

      var title = document.createElement('div');
      title.style.cssText = 'font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;';
      var modeName = mode.split('/').pop().toUpperCase();
      title.innerHTML = '<span>✏️ Editing: ' + editorId.replace('ace', '') + '</span> <span style="font-size:11px;padding:2px 6px;border-radius:4px;background:var(--color-primary,#4f6bed);color:#fff;font-weight:700;">' + modeName + '</span>';

      var btnGroup = document.createElement('div');
      btnGroup.style.cssText = 'display:flex;align-items:center;gap:8px;';

      var beautifyBtn = document.createElement('button');
      beautifyBtn.type = 'button';
      beautifyBtn.className = 'nu-btn nu-btn-secondary nu-btn-sm';
      beautifyBtn.innerHTML = '✨ Beautify Code';
      beautifyBtn.onclick = function() {
        _beautifyEditorInstance(fullEditor, mode);
      };

      var themeBtn = document.createElement('button');
      themeBtn.type = 'button';
      themeBtn.className = 'nu-btn nu-btn-ghost nu-btn-sm';
      themeBtn.innerHTML = '☀ Theme';
      themeBtn.onclick = function() {
        entry.dark = !entry.dark;
        var newTheme = entry.dark ? _darkTheme : _lightTheme;
        fullEditor.setTheme(newTheme);
        entry.editor.setTheme(newTheme);
      };

      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'nu-btn nu-btn-primary nu-btn-sm';
      closeBtn.innerHTML = '💾 Save & Close';
      closeBtn.onclick = function() {
        var newVal = fullEditor.getValue();
        entry.editor.setValue(newVal, -1);
        var hidden = document.getElementById(entry.hiddenId);
        if (hidden) hidden.value = newVal;
        if (window.nbFormBuilder) window.nbFormBuilder._isDirty = true;
        fullEditor.destroy();
        modal.remove();
      };

      btnGroup.appendChild(beautifyBtn);
      btnGroup.appendChild(themeBtn);
      btnGroup.appendChild(closeBtn);

      header.appendChild(title);
      header.appendChild(btnGroup);

      var editorContainer = document.createElement('div');
      editorContainer.style.cssText = 'flex:1;width:100%;';

      modal.appendChild(header);
      modal.appendChild(editorContainer);
      document.body.appendChild(modal);

      var fullEditor = ace.edit(editorContainer);
      fullEditor.setOptions({
        mode:                        mode,
        theme:                       entry.dark ? _darkTheme : _lightTheme,
        fontSize:                    '14px',
        tabSize:                     2,
        useSoftTabs:                 true,
        showPrintMargin:             false,
        enableBasicAutocompletion:   true,
        enableLiveAutocompletion:    true,
        enableSnippets:              true,
        wrap:                        true,
        showLineNumbers:             true,
        showGutter:                  true,
        highlightActiveLine:         true,
        fontFamily:                  'ui-monospace, "Cascadia Code", "Fira Code", monospace',
      });

      fullEditor.setValue(currentVal, -1);
      fullEditor.focus();

      modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeBtn.click();
        }
      });
    }

    function _openInTab(editorId) {
      var entry = _editors[editorId];
      if (!entry) return;

      var currentVal = entry.editor.getValue();
      var mode = entry.editor.getSession().getMode().$id;
      var modeName = mode.split('/').pop().toUpperCase();

      var newWin = window.open("", "_blank");
      if (!newWin) {
        alert("Popup blocked! Please allow popups for this site to open the editor in another tab.");
        return;
      }

      var doc = newWin.document;
      doc.open();
      doc.write(`
<!DOCTYPE html>
<html>
<head>
  <title>Editor Tab - ` + editorId.replace('ace', '') + `</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      font-family: system-ui, -apple-system, sans-serif;
      background: #ffffff;
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 20px;
      border-bottom: 1px solid #dde1f0;
      background: #f5f7ff;
    }
    .title {
      font-weight: 600;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .badge {
      font-size: 11px;
      padding: 2px 6px;
      border-radius: 4px;
      background: #4f6bed;
      color: #fff;
      font-weight: 700;
    }
    .btn {
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid #dde1f0;
      background: #ffffff;
      color: #333;
      cursor: pointer;
      margin-left: 8px;
    }
    .btn-primary {
      background: #4f6bed;
      color: white;
      border-color: #4f6bed;
    }
    #editor {
      flex: 1;
      width: 100%;
    }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.43.3/ace.min.js"><\/script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.43.3/ext-language_tools.min.js"><\/script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify.min.js"><\/script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify-css.min.js"><\/script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify-html.min.js"><\/script>
</head>
<body>
  <div class="header">
    <div class="title">
      <span>✏️ Editing in Tab: ` + editorId.replace('ace', '') + `</span>
      <span class="badge">` + modeName + `</span>
    </div>
    <div>
      <button class="btn" onclick="beautifyCode()">✨ Beautify Code</button>
      <button class="btn" onclick="toggleTheme()">☀ Theme</button>
      <button class="btn btn-primary" onclick="window.close()">💾 Close & Sync</button>
    </div>
  </div>
  <div id="editor"></div>

  <script>
    var originalEditorId = "` + editorId + `";
    var themeDark = ` + entry.dark + `;
    var editor = ace.edit("editor");

    editor.setOptions({
      mode: "` + mode + `",
      theme: themeDark ? "ace/theme/one_dark" : "ace/theme/chrome",
      fontSize: "14px",
      tabSize: 2,
      useSoftTabs: true,
      showPrintMargin: false,
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true,
      enableSnippets: true,
      wrap: true,
      showLineNumbers: true,
      showGutter: true,
      highlightActiveLine: true,
      fontFamily: 'ui-monospace, "Cascadia Code", "Fira Code", monospace'
    });

    // Load initial value
    var initialVal = window.opener ? window.opener.nbAce.getValue(originalEditorId) : "";
    editor.setValue(initialVal, -1);

    // Sync in real-time back to opener
    editor.session.on('change', function() {
      if (window.opener && !window.opener.closed) {
        var currentVal = editor.getValue();
        window.opener.nbAce.setValue(originalEditorId, currentVal);
        if (window.opener.nbFormBuilder) {
          window.opener.nbFormBuilder._isDirty = true;
        }
      }
    });

    function beautifyCode() {
      var code = editor.getValue();
      var mode = "` + mode + `";
      var beautified = code;

      if (mode.indexOf('javascript') !== -1) {
        if (window.js_beautify) {
          beautified = js_beautify(code, { indent_size: 2, space_in_empty_paren: true });
        }
      } else if (mode.indexOf('css') !== -1) {
        if (window.css_beautify) {
          beautified = css_beautify(code, { indent_size: 2 });
        }
      } else if (mode.indexOf('html') !== -1 || mode.indexOf('php') !== -1) {
        if (window.html_beautify) {
          beautified = html_beautify(code, { indent_size: 2 });
        }
      }
      editor.setValue(beautified, -1);
    }

    function toggleTheme() {
      themeDark = !themeDark;
      editor.setTheme(themeDark ? "ace/theme/one_dark" : "ace/theme/chrome");
      if (window.opener && !window.opener.closed) {
        window.opener.nbAce.toggleTheme(originalEditorId);
      }
    }
  <\/script>
</body>
</html>
      `);
      doc.close();
    }

    return {
      init:         _init,
      setValue:     _setValue,
      getValue:     _getValue,
      syncAll:      _syncAll,
      toggleTheme:  _toggleTheme,
      resize:       _resize,
      resizeAll:    _resizeAll,
      beautify:     _beautify,
      openFullView: _openFullView,
      openInTab:    _openInTab,
    };
  })();

  // Resize Ace when its parent tab becomes visible
  document.addEventListener('click', function(e) {
    var tab = e.target.closest('.nb-tab');
    if (tab) setTimeout(function(){ if (window.nbAce) nbAce.resizeAll(); }, 50);
  });

  // Drag-to-resize handles
  document.addEventListener('mousedown', function(e) {
    var handle = e.target.closest('.nb-ace-resize-handle');
    if (!handle) return;
    e.preventDefault();

    var aceId     = handle.dataset.ace;
    var editorDiv = document.getElementById(aceId);
    if (!editorDiv) return;

    var startY = e.clientY;
    var startH = editorDiv.offsetHeight;
    var minH   = 80;

    function onMove(ev) {
      var newH = Math.max(minH, startH + (ev.clientY - startY));
      editorDiv.style.height = newH + 'px';
      if (window.nbAce) nbAce.resize(aceId);
    }
    function onUp() {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      if (window.nbAce) nbAce.resize(aceId);
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });

})();
